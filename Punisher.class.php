<?php

/*************************************************
 *
 * Punisher - the PHP net client
 * Author: don Pablo <don@obeyi.com>
 * Copyright (c): 1999-2014, all rights reserved
 * Version: 1.0.0
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * You may contact the author of Punisher by e-mail at:
 * don@obeyi.com
 *************************************************/
class Punisher
{


    var $scheme = 'http';
    var $host = "www.php.net";
    var $port = 80;
    var $proxy_host = "";
    var $proxy_port = "";
    var $proxy_user = "";
    var $proxy_pass = "";

    var $agent = "Punisher v2.0.0";
    var $referer = "";
    var $cookies = array();

    var $rawheaders = array();


    var $maxredirs = 5;
    var $lastredirectaddr = "";
    var $offsiteok = true;
    var $maxframes = 0;
    var $expandlinks = true;


    var $passcookies = true;


    var $user = "";
    var $pass = "";


    var $accept = "image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*";

    var $results = ""; // where the content is put

    var $error = ""; // error messages sent here
    var $response_code = ""; // response code returned from server
    var $headers = array(); // headers returned from server sent here
    var $maxlength = 500000; // max return data length (body)
    var $read_timeout = 0; // timeout on read operations, in seconds
    // supported only since PHP 4 Beta 4
    // set to 0 to disallow timeouts
    var $timed_out = false; // if a read operation timed out
    var $status = 0; // http request status

    var $temp_dir = "/tmp"; // temporary directory that the webserver
    // has permission to write to.
    // under Windows, this should be C:\temp

    var $curl_path = false;
    // deprecated, punisher no longer uses curl for https requests,
    // but instead requires the openssl extension.

    // send Accept-encoding: gzip?
    var $use_gzip = true;

    // file or directory with CA certificates to verify remote host with
    var $cafile;
    var $capath;

    /**** Private variables ****/

    var $_maxlinelen = 4096;

    var $_httpmethod = "GET";
    var $_httpversion = "HTTP/1.0";
    var $_submit_method = "POST";
    var $_submit_type = "application/x-www-form-urlencoded";
    var $_mime_boundary = "";
    var $_redirectaddr = false;
    var $_redirectdepth = 0;
    var $_frameurls = array();
    var $_framedepth = 0;

    var $_isproxy = false;
    var $_fp_timeout = 30;

    function fetch($URI)
    {

        $URI_PARTS = parse_url($URI);
        if (!empty($URI_PARTS["user"]))
            $this->user = $URI_PARTS["user"];
        if (!empty($URI_PARTS["pass"]))
            $this->pass = $URI_PARTS["pass"];
        if (empty($URI_PARTS["query"]))
            $URI_PARTS["query"] = '';
        if (empty($URI_PARTS["path"]))
            $URI_PARTS["path"] = '';

        $fp = null;

        switch (strtolower($URI_PARTS["scheme"])) {
            case "https":
                if (!extension_loaded('openssl')) {
                    trigger_error("openssl extension required for HTTPS", E_USER_ERROR);
                    exit;
                }
                $this->port = 443;
            case "http":
                $this->scheme = strtolower($URI_PARTS["scheme"]);
                $this->host = $URI_PARTS["host"];
                if (!empty($URI_PARTS["port"]))
                    $this->port = $URI_PARTS["port"];
                if ($this->_connect($fp)) {
                    if ($this->_isproxy) {

                        $this->_httprequest($URI, $fp, $URI, $this->_httpmethod);
                    } else {
                        $path = $URI_PARTS["path"] . ($URI_PARTS["query"] ? "?" . $URI_PARTS["query"] : "");

                        $this->_httprequest($path, $fp, $URI, $this->_httpmethod);
                    }

                    $this->_disconnect($fp);

                    if ($this->_redirectaddr) {

                        if ($this->maxredirs > $this->_redirectdepth) {

                            if (preg_match("|^https?://" . preg_quote($this->host) . "|i", $this->_redirectaddr) || $this->offsiteok) {

                                $this->_redirectdepth++;
                                $this->lastredirectaddr = $this->_redirectaddr;
                                $this->fetch($this->_redirectaddr);
                            }
                        }
                    }

                    if ($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0) {
                        $frameurls = $this->_frameurls;
                        $this->_frameurls = array();

                        while (list(, $frameurl) = each($frameurls)) {
                            if ($this->_framedepth < $this->maxframes) {
                                $this->fetch($frameurl);
                                $this->_framedepth++;
                            } else
                                break;
                        }
                    }
                } else {
                    return false;
                }
                return $this;
                break;
            default:

                $this->error = 'Invalid protocol "' . $URI_PARTS["scheme"] . '"\n';
                return false;
                break;
        }
        return $this;
    }


    function submit($URI, $formvars = "", $formfiles = "")
    {
        unset($postdata);

        $postdata = $this->_prepare_post_body($formvars, $formfiles);

        $URI_PARTS = parse_url($URI);
        if (!empty($URI_PARTS["user"]))
            $this->user = $URI_PARTS["user"];
        if (!empty($URI_PARTS["pass"]))
            $this->pass = $URI_PARTS["pass"];
        if (empty($URI_PARTS["query"]))
            $URI_PARTS["query"] = '';
        if (empty($URI_PARTS["path"]))
            $URI_PARTS["path"] = '';

        switch (strtolower($URI_PARTS["scheme"])) {
            case "https":
                if (!extension_loaded('openssl')) {
                    trigger_error("openssl extension required for HTTPS", E_USER_ERROR);
                    exit;
                }
                $this->port = 443;
            case "http":
                $this->scheme = strtolower($URI_PARTS["scheme"]);
                $this->host = $URI_PARTS["host"];
                if (!empty($URI_PARTS["port"]))
                    $this->port = $URI_PARTS["port"];
                if ($this->_connect($fp)) {
                    if ($this->_isproxy) {

                        $this->_httprequest($URI, $fp, $URI, $this->_submit_method, $this->_submit_type, $postdata);
                    } else {
                        $path = $URI_PARTS["path"] . ($URI_PARTS["query"] ? "?" . $URI_PARTS["query"] : "");

                        $this->_httprequest($path, $fp, $URI, $this->_submit_method, $this->_submit_type, $postdata);
                    }

                    $this->_disconnect($fp);

                    if ($this->_redirectaddr) {

                        if ($this->maxredirs > $this->_redirectdepth) {
                            if (!preg_match("|^" . $URI_PARTS["scheme"] . "://|", $this->_redirectaddr))
                                $this->_redirectaddr = $this->_expandlinks($this->_redirectaddr, $URI_PARTS["scheme"] . "://" . $URI_PARTS["host"]);


                            if (preg_match("|^https?://" . preg_quote($this->host) . "|i", $this->_redirectaddr) || $this->offsiteok) {

                                $this->_redirectdepth++;
                                $this->lastredirectaddr = $this->_redirectaddr;
                                if (strpos($this->_redirectaddr, "?") > 0)
                                    $this->fetch($this->_redirectaddr);
                                else
                                    $this->submit($this->_redirectaddr, $formvars, $formfiles);
                            }
                        }
                    }

                    if ($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0) {
                        $frameurls = $this->_frameurls;
                        $this->_frameurls = array();

                        while (list(, $frameurl) = each($frameurls)) {
                            if ($this->_framedepth < $this->maxframes) {
                                $this->fetch($frameurl);
                                $this->_framedepth++;
                            } else
                                break;
                        }
                    }

                } else {
                    return false;
                }
                return $this;
                break;
            default:

                $this->error = 'Invalid protocol "' . $URI_PARTS["scheme"] . '"\n';
                return false;
                break;
        }
        return $this;
    }


    function fetchlinks($URI)
    {
        if ($this->fetch($URI) !== false) {
            if ($this->lastredirectaddr)
                $URI = $this->lastredirectaddr;
            if (is_array($this->results)) {
                for ($x = 0; $x < count($this->results); $x++)
                    $this->results[$x] = $this->_striplinks($this->results[$x]);
            } else
                $this->results = $this->_striplinks($this->results);

            if ($this->expandlinks)
                $this->results = $this->_expandlinks($this->results, $URI);
            return $this;
        } else
            return false;
    }


    function fetchform($URI)
    {

        if ($this->fetch($URI) !== false) {

            if (is_array($this->results)) {
                for ($x = 0; $x < count($this->results); $x++)
                    $this->results[$x] = $this->_stripform($this->results[$x]);
            } else
                $this->results = $this->_stripform($this->results);

            return $this;
        } else
            return false;
    }


    function fetchtext($URI)
    {
        if ($this->fetch($URI) !== false) {
            if (is_array($this->results)) {
                for ($x = 0; $x < count($this->results); $x++)
                    $this->results[$x] = $this->_striptext($this->results[$x]);
            } else
                $this->results = $this->_striptext($this->results);
            return $this;
        } else
            return false;
    }


    function submitlinks($URI, $formvars = "", $formfiles = "")
    {
        if ($this->submit($URI, $formvars, $formfiles) !== false) {
            if ($this->lastredirectaddr)
                $URI = $this->lastredirectaddr;
            if (is_array($this->results)) {
                for ($x = 0; $x < count($this->results); $x++) {
                    $this->results[$x] = $this->_striplinks($this->results[$x]);
                    if ($this->expandlinks)
                        $this->results[$x] = $this->_expandlinks($this->results[$x], $URI);
                }
            } else {
                $this->results = $this->_striplinks($this->results);
                if ($this->expandlinks)
                    $this->results = $this->_expandlinks($this->results, $URI);
            }
            return $this;
        } else
            return false;
    }


    function submittext($URI, $formvars = "", $formfiles = "")
    {
        if ($this->submit($URI, $formvars, $formfiles) !== false) {
            if ($this->lastredirectaddr)
                $URI = $this->lastredirectaddr;
            if (is_array($this->results)) {
                for ($x = 0; $x < count($this->results); $x++) {
                    $this->results[$x] = $this->_striptext($this->results[$x]);
                    if ($this->expandlinks)
                        $this->results[$x] = $this->_expandlinks($this->results[$x], $URI);
                }
            } else {
                $this->results = $this->_striptext($this->results);
                if ($this->expandlinks)
                    $this->results = $this->_expandlinks($this->results, $URI);
            }
            return $this;
        } else
            return false;
    }


    function set_submit_multipart()
    {
        $this->_submit_type = "multipart/form-data";
        return $this;
    }


    function set_submit_normal()
    {
        $this->_submit_type = "application/x-www-form-urlencoded";
        return $this;
    }


    function _striplinks($document)
    {
        preg_match_all("'<\s*a\s.*?href\s*=\s*			# find <a href=
						([\"\'])?					# find single or double quote
						(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
													# quote, otherwise match up to next space
						'isx", $document, $links);


        while (list($key, $val) = each($links[2])) {
            if (!empty($val))
                $match[] = $val;
        }

        while (list($key, $val) = each($links[3])) {
            if (!empty($val))
                $match[] = $val;
        }


        return $match;
    }


    function _stripform($document)
    {
        preg_match_all("'<\/?(FORM|INPUT|SELECT|TEXTAREA|(OPTION))[^<>]*>(?(2)(.*(?=<\/?(option|select)[^<>]*>[\r\n]*)|(?=[\r\n]*))|(?=[\r\n]*))'Usi", $document, $elements);


        $match = implode("\r\n", $elements[0]);


        return $match;
    }


    function _striptext($document)
    {


        $search = array("'<script[^>]*?>.*?</script>'si",
            "'<[\/\!]*?[^<>]*?>'si",
            "'([\r\n])[\s]+'",
            "'&(quot|#34|#034|#x22);'i",
            "'&(amp|#38|#038|#x26);'i",
            "'&(lt|#60|#060|#x3c);'i",
            "'&(gt|#62|#062|#x3e);'i",
            "'&(nbsp|#160|#xa0);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
            "'&(reg|#174);'i",
            "'&(deg|#176);'i",
            "'&(#39|#039|#x27);'",
            "'&(euro|#8364);'i",
            "'&a(uml|UML);'",
            "'&o(uml|UML);'",
            "'&u(uml|UML);'",
            "'&A(uml|UML);'",
            "'&O(uml|UML);'",
            "'&U(uml|UML);'",
            "'&szlig;'i",
        );
        $replace = array("",
            "",
            "\\1",
            "\"",
            "&",
            "<",
            ">",
            " ",
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            chr(174),
            chr(176),
            chr(39),
            chr(128),
            "ä",
            "ö",
            "ü",
            "Ä",
            "Ö",
            "Ü",
            "ß",
        );

        $text = preg_replace($search, $replace, $document);

        return $text;
    }


    function _expandlinks($links, $URI)
    {

        preg_match("/^[^\?]+/", $URI, $match);

        $match = preg_replace("|/[^\/\.]+\.[^\/\.]+$|", "", $match[0]);
        $match = preg_replace("|/$|", "", $match);
        $match_part = parse_url($match);
        $match_root =
            $match_part["scheme"] . "://" . $match_part["host"];

        $search = array("|^http://" . preg_quote($this->host) . "|i",
            "|^(\/)|i",
            "|^(?!http://)(?!mailto:)|i",
            "|/\./|",
            "|/[^\/]+/\.\./|"
        );

        $replace = array("",
            $match_root . "/",
            $match . "/",
            "/",
            "/"
        );

        $expandedLinks = preg_replace($search, $replace, $links);

        return $expandedLinks;
    }


    function _httprequest($url, $fp, $URI, $http_method, $content_type = "", $body = "")
    {
        $cookie_headers = '';
        if ($this->passcookies && $this->_redirectaddr)
            $this->setcookies();

        $URI_PARTS = parse_url($URI);
        if (empty($url))
            $url = "/";
        $headers = $http_method . " " . $url . " " . $this->_httpversion . "\r\n";
        if (!empty($this->host) && !isset($this->rawheaders['Host'])) {
            $headers .= "Host: " . $this->host;
            if (!empty($this->port) && $this->port != '80')
                $headers .= ":" . $this->port;
            $headers .= "\r\n";
        }
        if (!empty($this->agent))
            $headers .= "User-Agent: " . $this->agent . "\r\n";
        if (!empty($this->accept))
            $headers .= "Accept: " . $this->accept . "\r\n";
        if ($this->use_gzip) {


            if (function_exists('gzinflate')) {
                $headers .= "Accept-encoding: gzip\r\n";
            } else {
                trigger_error(
                    "use_gzip is on, but PHP was built without zlib support." .
                    "  Requesting file(s) without gzip encoding.",
                    E_USER_NOTICE);
            }
        }
        if (!empty($this->referer))
            $headers .= "Referer: " . $this->referer . "\r\n";
        if (!empty($this->cookies)) {
            if (!is_array($this->cookies))
                $this->cookies = (array)$this->cookies;

            reset($this->cookies);
            if (count($this->cookies) > 0) {
                $cookie_headers .= 'Cookie: ';
                foreach ($this->cookies as $cookieKey => $cookieVal) {
                    $cookie_headers .= $cookieKey . "=" . urlencode($cookieVal) . "; ";
                }
                $headers .= substr($cookie_headers, 0, -2) . "\r\n";
            }
        }
        if (!empty($this->rawheaders)) {
            if (!is_array($this->rawheaders))
                $this->rawheaders = (array)$this->rawheaders;
            while (list($headerKey, $headerVal) = each($this->rawheaders))
                $headers .= $headerKey . ": " . $headerVal . "\r\n";
        }
        if (!empty($content_type)) {
            $headers .= "Content-type: $content_type";
            if ($content_type == "multipart/form-data")
                $headers .= "; boundary=" . $this->_mime_boundary;
            $headers .= "\r\n";
        }
        if (!empty($body))
            $headers .= "Content-length: " . strlen($body) . "\r\n";
        if (!empty($this->user) || !empty($this->pass))
            $headers .= "Authorization: Basic " . base64_encode($this->user . ":" . $this->pass) . "\r\n";


        if (!empty($this->proxy_user))
            $headers .= 'Proxy-Authorization: ' . 'Basic ' . base64_encode($this->proxy_user . ':' . $this->proxy_pass) . "\r\n";


        $headers .= "\r\n";


        if ($this->read_timeout > 0)
            socket_set_timeout($fp, $this->read_timeout);
        $this->timed_out = false;

        fwrite($fp, $headers . $body, strlen($headers . $body));

        $this->_redirectaddr = false;
        unset($this->headers);


        $is_gzipped = false;

        while ($currentHeader = fgets($fp, $this->_maxlinelen)) {
            if ($this->read_timeout > 0 && $this->_check_timeout($fp)) {
                $this->status = -100;
                return false;
            }

            if ($currentHeader == "\r\n")
                break;


            if (preg_match("/^(Location:|URI:)/i", $currentHeader)) {

                preg_match("/^(Location:|URI:)[ ]+(.*)/i", chop($currentHeader), $matches);

                if (!preg_match("|\:\/\/|", $matches[2])) {

                    $this->_redirectaddr = $URI_PARTS["scheme"] . "://" . $this->host . ":" . $this->port;

                    if (!preg_match("|^/|", $matches[2]))
                        $this->_redirectaddr .= "/" . $matches[2];
                    else
                        $this->_redirectaddr .= $matches[2];
                } else
                    $this->_redirectaddr = $matches[2];
            }

            if (preg_match("|^HTTP/|", $currentHeader)) {
                if (preg_match("|^HTTP/[^\s]*\s(.*?)\s|", $currentHeader, $status)) {
                    $this->status = $status[1];
                }
                $this->response_code = $currentHeader;
            }

            if (preg_match("/Content-Encoding: gzip/", $currentHeader)) {
                $is_gzipped = true;
            }

            $this->headers[] = $currentHeader;
        }

        $results = '';
        do {
            $_data = fread($fp, $this->maxlength);
            if (strlen($_data) == 0) {
                break;
            }
            $results .= $_data;
        } while (true);


        if ($is_gzipped) {

            $results = substr($results, 10);
            $results = gzinflate($results);
        }

        if ($this->read_timeout > 0 && $this->_check_timeout($fp)) {
            $this->status = -100;
            return false;
        }


        if (preg_match("'<meta[\s]*http-equiv[^>]*?content[\s]*=[\s]*[\"\']?\d+;[\s]*URL[\s]*=[\s]*([^\"\']*?)[\"\']?>'i", $results, $match)) {
            $this->_redirectaddr = $this->_expandlinks($match[1], $URI);
        }


        if (($this->_framedepth < $this->maxframes) && preg_match_all("'<frame\s+.*src[\s]*=[\'\"]?([^\'\"\>]+)'i", $results, $match)) {
            $this->results[] = $results;
            for ($x = 0; $x < count($match[1]); $x++)
                $this->_frameurls[] = $this->_expandlinks($match[1][$x], $URI_PARTS["scheme"] . "://" . $this->host);
        } elseif (is_array($this->results))
            $this->results[] = $results;

        else
            $this->results = $results;

        return $this;
    }


    function setcookies()
    {
        for ($x = 0; $x < count($this->headers); $x++) {
            if (preg_match('/^set-cookie:[\s]+([^=]+)=([^;]+)/i', $this->headers[$x], $match))
                $this->cookies[$match[1]] = urldecode($match[2]);
        }
        return $this;
    }


    function _check_timeout($fp)
    {
        if ($this->read_timeout > 0) {
            $fp_status = socket_get_status($fp);
            if ($fp_status["timed_out"]) {
                $this->timed_out = true;
                return true;
            }
        }
        return false;
    }


    function _connect(&$fp)
    {
        if (!empty($this->proxy_host) && !empty($this->proxy_port)) {
            $this->_isproxy = true;

            $host = $this->proxy_host;
            $port = $this->proxy_port;

            if ($this->scheme == 'https') {
                trigger_error("HTTPS connections over proxy are currently not supported", E_USER_ERROR);
                exit;
            }
        } else {
            $host = $this->host;
            $port = $this->port;
        }

        $this->status = 0;

        $context_opts = array();

        if ($this->scheme == 'https') {


            if (isset($this->cafile) || isset($this->capath)) {
                $context_opts['ssl'] = array(
                    'verify_peer' => true,
                    'CN_match' => $this->host,
                    'disable_compression' => true,
                );

                if (isset($this->cafile))
                    $context_opts['ssl']['cafile'] = $this->cafile;
                if (isset($this->capath))
                    $context_opts['ssl']['capath'] = $this->capath;
            }

            $host = 'ssl://' . $host;
        }

        $context = stream_context_create($context_opts);

        if (version_compare(PHP_VERSION, '5.0.0', '>')) {
            if ($this->scheme == 'http')
                $host = "tcp://" . $host;
            $fp = stream_socket_client(
                "$host:$port",
                $errno,
                $errmsg,
                $this->_fp_timeout,
                STREAM_CLIENT_CONNECT,
                $context);
        } else {
            $fp = fsockopen(
                $host,
                $port,
                $errno,
                $errstr,
                $this->_fp_timeout,
                $context);
        }

        if ($fp) {

            return true;
        } else {

            $this->status = $errno;
            switch ($errno) {
                case -3:
                    $this->error = "socket creation failed (-3)";
                case -4:
                    $this->error = "dns lookup failure (-4)";
                case -5:
                    $this->error = "connection refused or timed out (-5)";
                default:
                    $this->error = "connection failed (" . $errno . ")";
            }
            return false;
        }
    }


    function _disconnect($fp)
    {
        return (fclose($fp));
    }


    function _prepare_post_body($formvars, $formfiles)
    {
        settype($formvars, "array");
        settype($formfiles, "array");
        $postdata = '';

        if (count($formvars) == 0 && count($formfiles) == 0)
            return;

        switch ($this->_submit_type) {
            case "application/x-www-form-urlencoded":
                reset($formvars);
                while (list($key, $val) = each($formvars)) {
                    if (is_array($val) || is_object($val)) {
                        while (list($cur_key, $cur_val) = each($val)) {
                            $postdata .= urlencode($key) . "[]=" . urlencode($cur_val) . "&";
                        }
                    } else
                        $postdata .= urlencode($key) . "=" . urlencode($val) . "&";
                }
                break;

            case "multipart/form-data":
                $this->_mime_boundary = "Punisher" . md5(uniqid(microtime()));

                reset($formvars);
                while (list($key, $val) = each($formvars)) {
                    if (is_array($val) || is_object($val)) {
                        while (list($cur_key, $cur_val) = each($val)) {
                            $postdata .= "--" . $this->_mime_boundary . "\r\n";
                            $postdata .= "Content-Disposition: form-data; name=\"$key\[\]\"\r\n\r\n";
                            $postdata .= "$cur_val\r\n";
                        }
                    } else {
                        $postdata .= "--" . $this->_mime_boundary . "\r\n";
                        $postdata .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
                        $postdata .= "$val\r\n";
                    }
                }

                reset($formfiles);
                while (list($field_name, $file_names) = each($formfiles)) {
                    settype($file_names, "array");
                    while (list(, $file_name) = each($file_names)) {
                        if (!is_readable($file_name)) continue;

                        $fp = fopen($file_name, "r");
                        $file_content = fread($fp, filesize($file_name));
                        fclose($fp);
                        $base_name = basename($file_name);

                        $postdata .= "--" . $this->_mime_boundary . "\r\n";
                        $postdata .= "Content-Disposition: form-data; name=\"$field_name\"; filename=\"$base_name\"\r\n\r\n";
                        $postdata .= "$file_content\r\n";
                    }
                }
                $postdata .= "--" . $this->_mime_boundary . "--\r\n";
                break;
        }

        return $postdata;
    }


    function getResults()
    {
        return $this->results;
    }
}

?>
