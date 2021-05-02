<?php

define('ADMIN_PUNISH_SETTINGS', 'inc/settings.php');

define('ADMIN_TIMEOUT', 60 * 60);

define('ADMIN_STATS_LIMIT', 50);

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ADMIN_URI', $_SERVER['PHP_SELF']);

define('ADMIN_VERSION', '1.4.15');

ob_start();

define('PUNISH_URL', pathToURL(dirname(ADMIN_PUNISH_SETTINGS) . '/..'));
define('PUNISH_ROOT', str_replace('\\', '/', dirname(dirname(realpath(ADMIN_PUNISH_SETTINGS)))));

function findURL()
{
    return PUNISH_URL;
}

define('LCNSE_KEY', '');
define('proxyPATH', PUNISH_ROOT . '/');

$settingsLoaded = file_exists(ADMIN_PUNISH_SETTINGS) && (@include ADMIN_PUNISH_SETTINGS);

$action = isset($_SERVER['QUERY_STRING']) && preg_match('#^([a-z-]+)#', $_SERVER['QUERY_STRING'], $tmp) ? $tmp[1] : '';

$cache_bust = filemtime(__FILE__) + filemtime(ADMIN_PUNISH_SETTINGS);

define('NL', "\r\n");

if (isset($_GET['image'])) {

    function sendImage($str)
    {
        header('Content-Type: image/gif');
        header('Last-Modified: ' . gmdate("D, d M Y H:i:s", filemtime(__FILE__)) . 'GMT');
        header('Expires: ' . gmdate("D, d M Y H:i:s", filemtime(__FILE__) + (60 * 60)) . 'GMT');
        echo base64_decode($str);
        exit;
    }

}

function quote($str)
{
    $str = str_replace('\\', '\\\\', $str);
    $str = str_replace("'", "\'", $str);
    return "'$str'";
}

function formatSize($bytes)
{

    $types = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0, $l = count($types) - 1; $bytes >= 1024 && $i < $l; $bytes /= 1024, $i++) ;

    return (round($bytes, 2) . ' ' . $types[$i]);

}

function pathToURL($filePath)
{

    $realPath = realpath($filePath);

    if (is_file($realPath)) {

        $dir = dirname($realPath);

    } elseif (is_dir($realPath)) {

        $dir = $realPath;

    } else {
        return false;
    }

    $_SERVER['DOCUMENT_ROOT'] = realpath($_SERVER['DOCUMENT_ROOT']);

    if (strlen($dir) < strlen($_SERVER['DOCUMENT_ROOT'])) {
        return false;
    }

    $rootPos = strlen($_SERVER['DOCUMENT_ROOT']);

    if (($tmp = substr($_SERVER['DOCUMENT_ROOT'], -1)) && ($tmp == '/' || $tmp == '\\')) {
        --$rootPos;
    }

    $pathFromRoot = substr($realPath, $rootPos);

    $path = 'http' . (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $pathFromRoot;

    if (DIRECTORY_SEPARATOR == '\\') {
        $path = str_replace('\\', '/', $path);
    }

    return $path;
}

function jsWrite($str)
{
    return '<script type="text/javascript">document.write(' . quote($str) . ')</script>';
}

function bool($str)
{
    if ($str == 'false') {
        return false;
    }
    if ($str == 'true') {
        return true;
    }
    return NULL;
}

class Location
{

    private $observers;

    public function redirect($to = '')
    {

        $this->notifyObservers('redirect');

        header('Location: ' . ADMIN_URI . '?' . $to);
        exit;
    }

    public function notifyObservers($action)
    {

        $method = 'on' . ucfirst($action);

        $params = func_get_args();
        array_shift($params);

        foreach ($this->observers as $obj) {

            if (method_exists($obj, $method)) {

                call_user_func_array(array(&$obj, $method), $params);

            }

        }

    }

    public function cleanRedirect($to = '')
    {

        header('Location: ' . ADMIN_URI . '?' . $to);
        exit;
    }

    public function addObserver(&$obj)
    {
        $this->observers[] = $obj;
    }

}


class Input
{

    public function __construct()
    {

        $this->GET = $this->prepare($_GET);
        $this->POST = $this->prepare($_POST);
        $this->COOKIE = $this->prepare($_COOKIE);

    }

    private function prepare($array)
    {

        $return = array();

        foreach ($array as $key => $value) {
            $return[strtolower($key)] = self::clean($value);
        }

        return $return;

    }


    static public function clean($val)
    {

        static $magicQuotes;


        if (!isset($magicQuotes)) {
            $magicQuotes = get_magic_quotes_gpc();
        }

        switch (true) {
            case is_string($val):

                if ($magicQuotes) {
                    $val = stripslashes($val);
                }

                $val = trim($val);

                break;

            case is_array($val):

                $val = array_map(array('Input', 'clean'), $val);

                break;

            default:
                return $val;
        }

        return $val;

    }


    public function __get($name)
    {

        if (!isset($name[1])) {
            return NULL;
        }

        $from = strtolower($name[0]);
        $var = strtolower(substr($name, 1));

        $targets = array('g' => $this->GET,
            'p' => $this->POST,
            'c' => $this->COOKIE);

        if (isset($targets[$from][$var])) {
            return $targets[$from][$var];
        }

        return NULL;

    }

}

class Overloader
{

    protected $data;


    public function __get($name)
    {
        $name = strtolower($name);
        return isset($this->data[$name]) ? $this->data[$name] : '';
    }


    public function __set($name, $value)
    {
        $this->data[strtolower($name)] = $value;
    }

}

abstract class Output extends Overloader
{

    protected $output;

    # Content only
    protected $content;

    # Array of observers
    protected $observers = array();


    # Output the page
    final public function out()
    {

        # Notify our observers we're about to print
        $this->notifyObservers('print', $this);

        # Wrap content in our wrapper
        $this->wrap();

        # Send headers
        $this->sendHeaders();

        # Send body
        print $this->output;

        # Page completed, finish
        exit;

    }


    # Override this to send custom headers instead of default (html)

    public function notifyObservers($action)
    {

        # Determine method to call
        $method = 'on' . ucfirst($action);

        # Prepare parameters
        $params = func_get_args();
        array_shift($params);

        # Loop through all observers
        foreach ($this->observers as $obj) {

            # If an observing method exists, call it
            if (method_exists($obj, $method)) {

                call_user_func_array(array(&$obj, $method), $params);

            }

        }

    }


    # Wrapper for body content

    protected function wrap()
    {
        $this->output = $this->content;
    }


    # Add content

    protected function sendHeaders()
    {
    }


    # Register observers

    public function addContent($content)
    {
        $this->content .= $content;
    }


    # Notify observers

    public function addObserver(&$obj)
    {
        $this->observers[] = $obj;
    }


    # Send status code

    public function sendStatus($code)
    {
        header(' ', true, $code);
    }

    # More overloading. Set value with key.
    public function __call($func, $args)
    {
        if (substr($func, 0, 3) == 'add' && strlen($func) > 3 && !isset($args[2])) {

            # Saving with key or not?
            if (isset($args[1])) {
                $this->data[strtolower(substr($func, 3))][$args[0]] = $args[1];
            } else {
                $this->data[strtolower(substr($func, 3))][] = $args[0];
            }

        }
    }

}

# Output with our HTML skin
class SkinOutput extends Output
{

    # Print all
    protected function wrap()
    {

        # Self
        $self = ADMIN_URI;

        # Prepare date
        $date = date('H:i, d F Y');

        # Append "punisher control panel" to title
        $title = $this->title . ($this->title ? ' : ' : '') . 'Punisher control panel';

        # Buffer so we can get this into a variable
        ob_start();

        # Print output
        echo <<<OUT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
<title>{$title}</title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<script type="text/javascript">var offsetx=12;var offsety=8;function newelement(a){if(document.createElement){var b=document.createElement('div');b.id=a;with(b.style){display='none';position='absolute'}b.innerHTML='&nbsp;';document.body.appendChild(b)}}var ie5=(document.getElementById&&document.all);var ns6=(document.getElementById&&!document.all);var ua=navigator.userAgent.toLowerCase();var isapple=(ua.indexOf('applewebkit')!=-1?1:0);function getmouseposition(e){if(document.getElementById){var a=(document.compatMode&&document.compatMode!='BackCompat')?document.documentElement:document.body;pagex=(isapple==1?0:(ie5)?a.scrollLeft:window.pageXOffset);pagey=(isapple==1?0:(ie5)?a.scrollTop:window.pageYOffset);mousex=(ie5)?event.x:(ns6)?clientX=e.clientX:false;mousey=(ie5)?event.y:(ns6)?clientY=e.clientY:false;var b=document.getElementById('tooltip');b.style.left=(mousex+pagex+offsetx)+'px';b.style.top=(mousey+pagey+offsety)+'px'}}function tooltip(a){if(!document.getElementById('tooltip'))newelement('tooltip');var b=document.getElementById('tooltip');b.innerHTML=a;b.style.display='block';document.onmousemove=getmouseposition}function exit(){document.getElementById('tooltip').style.display='none'}window.domReadyFuncs=[];window.addDomReadyFunc=function(a){window.domReadyFuncs.push(a)};function init(){if(arguments.callee.done)return;arguments.callee.done=true;if(_timer)clearInterval(_timer);for(var i=0;i<window.domReadyFuncs.length;++i){try{window.domReadyFuncs[i]()}catch(ignore){}}}if(document.addEventListener){document.addEventListener("DOMContentLoaded",init,false)}/*@cc_on@*//*@if(@_win32)document.write("<script id=__ie_onload defer src=javascript:void(0)><\\\/script>");var script=document.getElementById("__ie_onload");script.onreadystatechange=function(){if(this.readyState=="complete"){init()}};/*@end@*/if(/WebKit/i.test(navigator.userAgent)){var _timer=setInterval(function(){if(/loaded|complete/.test(document.readyState)){init()}},10)}window.onload=init;if(!window.XMLHttpRequest){window.XMLHttpRequest=function(){return new ActiveXObject('Microsoft.XMLHTTP')}}function runAjax(a,b,c,d){var e=new XMLHttpRequest();var f=b?'POST':'GET';e.open(f,a,true);e.setRequestHeader("Content-Type","application/x-javascript;");e.onreadystatechange=function(){if(e.readyState==4&&e.status==200){if(e.responseText){c.call(d,e.responseText)}}};e.send(b)}</script>
<script type="text/javascript">
OUT;

        # Add domReady javascript
        if ($this->domReady) {
            echo 'window.addDomReadyFunc(function(){', $this->printAll('domReady'), '});';
        }

        # Add other javascript
        if ($this->javascript) {
            echo $this->printAll('javascript');
        }

        echo <<<OUT
</script>
<link rel="icon" type="image/png" href="favicon.ico">
<style type="text/css">body{margin:0;font-size:62.5%;font-family:Verdana, Arial, Helvetica, sans-serif;padding:15px 0;background:#000}#wrap{width:820px;margin:0 auto;}#top_content{padding:0 10px}#topheader{padding:25px 15px 15px;margin:0 auto;}#rightheader{float:right;width:375px;height:40px;color:#FFF;text-align:right}#rightheader p{padding:35px 15px 0 0;margin:0;text-align:right}#leftheader{float:left;width:375px;height:40px;color:#FFF;text-align:left}#leftheader p{padding:35px 15px 0 0;margin:0;text-align:left}#title{padding:0;margin:0;font-size:2.5em;color:#FFF}#title span{font-size:0.5em;font-style:italic}#title a:link,#title a:visited{color:#FFF;text-decoration:none}#title a:hover{color:#E1F3C7}#navigation{background:#a10000;border-top:1px solid #fff;height:25px;clear:both}#navigation ul{padding:0;margin:0;list-style:none;font-size:1.1em;height:25px}#navigation ul li{display:inline}#navigation ul li a{color:#FFF;display:block;text-decoration:none;float:left;line-height:25px;padding:0 16px;border-right:1px solid #fff}#navigation ul li a:hover{background:#5494F3}#content{padding:15px;margin:0 auto;color: #eee;text-shadow: 0px 0px 2px #b30000;background: linear-gradient(180deg, #111,black)}#content h1,#content h2,#content h3,#content h4,#content h5{color:#a10000}#content h1{font-family:"Trebuchet MS", Arial, Helvetica;padding:0;margin:0 0 15px;font-size:2em}#content h2{font-family:"Trebuchet MS", Arial, Helvetica;padding:0;margin:0 0 15px;font-size:1.5em}#top_body,#content_body{padding:0 25px}#footer{color:#FFF;padding:0 10px 13px}#footer p a:link,#footer p a:visited{color:#FFF;font-style:italic;text-decoration:none}#footer #footer_bg{padding:15px 15px 25px;border-top:1px solid #000000}#footer #design{display:block;width:150px;height:30px;float:right;line-height:20px;padding:0 5px;text-align:right;color:#E1F3C7}#footer #design a,#rightheader a:link,#rightheader a:visited{color:#FFF;text-decoration:underline}.table{margin-bottom:15px;width:100%;border-collapse:collapse}.table_header td a:link,.table_header td a:visited{text-decoration:underline;color:#467aa7}.table_header td{padding:5px 10px;color:#467aa7;border-top:1px solid #CBD6DE;border-bottom:1px solid #ADBECB;font-size:1.1em;font-weight:bold;border:1px solid #CBD6DE}.row1 td,.row2 td,.row3 td,.row_hover td,.paging_row td{padding:5px 10px;color:#666;border:1px solid #CBD6DE}.row1 td{background:#fff}.row2 td{background:#f6f6f6}.row3 td{background:#eee}.row1:hover td,.row2:hover td,.row3:hover td{background:#FBFACE;color:#000}.hidden{display:none}#content .little{font-size:9px}.clear{clear:both}.img_left{float:left;padding:1px;border:1px solid #ccc;margin:0 10px 10px 0}#content ul{font-size:1.1em;line-height:1.8em;margin:0 0 15px;padding:0;list-style-type:none}#content p{font-size:1.2em;margin:0;padding:0 0 15px;line-height:150%}#content p a:hover,.table a:hover,.form_table a:hover,.link a:hover{text-decoration:underline}#content ul.green li{padding:0 0 0 20px;margin:0;font-size:1.1em}#content ul.black li{padding:0 0 0 20px;margin:0;font-size:1.1em}#content ul.black li a:link,#content ul.black li a:visited{color:#666;text-decoration:none}#content ol{padding:0 0 0 25px;margin:0 0 15px;line-height:1.8em}#content ol li{font-size:1.1em}#content ol li a:link,#content ol li a:visited,#content ul.green li a:link,#content ul.green li a:visited,#content p a,#content p a:visited,.table a,.table a:visited,.form_table a,.link a{color:#73A822;text-decoration:none}#content ol li a:hover,#content ul.green li a:hover,.table_header td a:hover{color:#73A822;text-decoration:underline}#content p.paging{padding:5px;border:1px solid #CBD6DE;text-align:center;margin-bottom:15px;background:#eee}.small_input{font-size:10px}.form_table{margin-bottom:15px;font-size:1.1em}.form_table td{padding:5px 10px}input.button{margin:0;padding:5px;background:#000;color:#fff;border:none;font-size:11px;font-family:Verdana, Arial, Helvetica, sans-serif}input.inputgri,select.inputgri,textarea.inputgri{background:#eee;font-size:14px;border:1px solid #ccc;padding:5px}input.inputgri:focus,select.inputgri:focus,textarea.inputgri:focus{background:#fff;}textarea.inputgri{font-size:12px;font-family:Verdana, Arial, Helvetica, sans-serif;height:60px}.notice{background:#fff;border:1px solid #ff0000;padding:15px;margin-bottom:15px;font-size:1.2em;color:#000}.notice_error{background:#FEDCDA;border:1px solid #CE090E;padding:15px;margin-bottom:15px;font-size:1.2em;color:#333}.notice .close,.notice_error .close{cursor:pointer;color:#000;background:none;padding:5px;margin-right:2px;border:none}#notice a{color:#333;text-decoration:underline}.other_links{background:#eee;border-top:1px solid #ccc;padding:5px;margin:0 0 15px}#content .other_links h2{color:#999;padding:0 0 0 3px;margin:0}#content .other_links ul li{padding:0 0 0 20px;}#content .other_links a,#content .other_links a:visited,#content ul.black li a:hover{color:#999;text-decoration:underline}#content .other_links a:hover{color:#666}code{font-size:1.2em;color:#73A822}#tooltip{width:20em;color:#fff;background:#555;font-size:12px;font-weight:normal;padding:5px;border:3px solid #333;text-align:left}.hr{border-top:2px solid #ccc;margin:5px 0 15px}.bold,#rightheader p span{font-weight:bold}.center{text-align:center}.right{text-align:right}.error-color{color:#CE090E}.ok-color{color:#70A522}.wide-input{width:350px}.small-input{width:50px}.tooltip{padding-bottom:1px;border-bottom:1px dotted #70A522;cursor:help}.bar{background:#73A822;height:10px;font-size:xx-small;padding:2px;color:#000}.comment{padding:5px;border:1px solid #CBD6DE;border-width:1px 0 1px 0;margin-bottom:15px;background:#f6f6f6}#content .comment p,#content .comment ul,#content .other_links ul,form,.checkbox_nomargins,.form_table p,#footer p{margin:0;padding:0}#preload{position:absolute;height:10px;top:-100px}</style>
</head>
<body>
	<div id="wrap">

		<div id="top_content">

			<div id="header">
			
                <div id="leftheader">
                    <p>punisher control panel beta v1.0.1</p>
                </div>

				<div id="rightheader">
					<p>
						{$date}
						<br />
OUT;

        # Add the "welcome" and log out link
        if ($this->admin) {
            echo "welcome, <i>{$this->admin}</i> : <strong><a href=\"{$self}?logout\">log out</a></strong>\r\n";
        }

        $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        echo <<<OUT
					</p>
				</div>

				<div id="topheader">
				    Punisher
					<h1 id="title">
						<a href="{$self}"><img alt="Punisher" src="./assets/banner.png" width="90%" height="auto" /></a><br>						
					</h1>
				</div>

				<div id="navigation">
					<ul>
OUT;

        # Add navigation
        if (is_array($this->navigation)) {

            foreach ($this->navigation as $text => $href) {
                if (stripos($href, $self) !== false) {
                    echo "<li><a href=\"{$href}\">{$text}</a></li>\r\n";
                } else {
                    echo "<li><a href=\"{$href}\" target=\"_blank\">{$text}</a></li>\r\n";
                }
            }

        }

        echo <<<OUT
					</ul>
				</div>

			</div>

			<div id="content">

				<h1>{$this->bodyTitle}</h1>
OUT;

        # Do we have any error messages?
        if ($this->error) {

            # Print all
            foreach ($this->error as $id => $message) {
                echo <<<OUT
				<div class="notice_error" id="notice_error_{$id}">
					<a class="close" title="Dismiss" onclick="document.getElementById('notice_error_{$id}').style.display='none';">X</a>
					{$message}
				</div>
OUT;
            }

        }

        # Do we have any confirmation messages?
        if ($this->confirm) {

            # Print all
            foreach ($this->confirm as $id => $message) {
                echo <<<OUT
				<div class="notice" id="notice_{$id}">
					<a class="close" title="Dismiss" onclick="document.getElementById('notice_{$id}').style.display='none';">X</a>
					{$message}
				</div>
OUT;
            }

        }

        # Print content
        echo $this->content;

        # Print footer links
        if (is_array($this->footerLinks)) {

            echo '
				<br>
				<div class="other_links">
					<h2>See also</h2>
					<ul class="other">
					';

            foreach ($this->footerLinks as $text => $href) {
                echo "<li><a href=\"{$href}\">{$text}</a></li>\r\n";
            }

            echo '
					</ul>
				</div>
					';

        }


        # And finish off the page
        echo <<<OUT

			</div>

		</div>

		<div id="footer">

			<div id="footer_bg">
				<p><a href="http://www.ţ.com/">Punisher</a>&reg; &copy; 1985 - 2021 All rights reserved</p>
			</div>

		</div>

	</div>

	<div id="preload">
		<span class="ajax-loading">&nbsp;</span>
	</div>

</body>
</html>
OUT;

        $this->output = ob_get_contents();

        # Discard buffer
        ob_end_clean();

    }

    # Wrap content in HTML skin

    private function printAll($name)
    {
        $name = strtolower($name);
        if (isset($this->data[$name]) && is_array($this->data[$name])) {
            foreach ($this->data[$name] as $item) {
                echo $item;
            }
        }
    }

}


# Send output in "raw" form
class RawOutput extends Output
{

    protected function sendHeaders()
    {
        header('Content-Type: text/plain; charset="utf-8"');
        header('Content-Disposition: inline; filename=""');
    }

}


# Manage sessions and stores user data
class User
{

    # Username we're logged in as
    public $name;

    # Our user agent
    public $userAgent;

    # Our IP address
    public $IP;

    # Reason for aborting a session
    public $aborted;


    # Constructor sets up session
    public function __construct()
    {

        # Don't try to start if autostarted
        if (session_id() == '') {

            # Set up new session
            session_name('admin');
            session_start();

        }

        # Always use a fresh ID for security
        session_regenerate_id();

        # Prepare user data
        $this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $this->IP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        # Use user-agent and IP as identifying data since these shouldn't change mid-session
        $authKey = $this->userAgent . $this->IP;

        # Do we have a stored auth key?
        if (isset($_SESSION['auth_key'])) {

            # Compare our current auth_key to stored key
            if ($_SESSION['auth_key'] != $authKey) {

                # Mismatch. Session may be stolen.
                $this->clear();
                $this->aborted = 'Session data mismatch.';

            }

        } else {

            # No stored auth key, save it
            $_SESSION['auth_key'] = $authKey;

        }

        # Are we verified?
        if (!empty($_SESSION['verified'])) {
            $this->name = $_SESSION['verified'];
        }

        # Have we expired? Only expire if we're logged in of course...
        if ($this->isAdmin() && isset($_SESSION['last_click']) && $_SESSION['last_click'] < (time() - ADMIN_TIMEOUT)) {
            $this->clear();
            $this->aborted = 'Your session timed out after ' . round(ADMIN_TIMEOUT / 60) . ' minutes of inactivity.';
        }

        # Set last click time
        $_SESSION['last_click'] = time();

    }

    # Log out, destroy all session data
    public function clear()
    {

        # Clear existing
        session_destroy();

        # Unset existing variables
        $_SESSION = array();
        $this->name = false;

        # Restart session
        session_start();

    }

    # Log in, saving username session for future requests

    public function isAdmin()
    {
        return (bool)$this->name;
    }

    # Are we verified or not?

    public function login($name)
    {
        $this->name = $name;
        $_SESSION['verified'] = $name;
    }

}



class Notice
{

    # Storage of messages
    private $data = array();

    # Type of notice handler
    private $type;

    # Constructor fetches any stored from session and clears session
    public function __construct($type)
    {

        # Save type
        $this->type = $type;

        # Array key
        $key = 'notice_' . $type;

        # Any existing?
        if (isset($_SESSION[$key])) {

            # Extract
            $this->data = $_SESSION[$key];

            # And clear
            unset($_SESSION[$key]);

        }

    }

    # Get messages
    public function get($id = false)
    {

        # Requesting an individual message?
        if ($id !== false) {
            return isset($this->data[$id]) ? $this->data[$id] : false;
        }

        # Requesting all
        return $this->data;

    }

    # Add message
    public function add($msg, $id = false)
    {

        # Add with or without an explicit key
        if ($id) {
            $this->data[$id] = $msg;
        } else {
            $this->data[] = $msg;
        }

    }

    # Do we have any messages?
    public function hasMsg()
    {
        return !empty($this->data);
    }

    # Observer the print method of output
    public function onPrint($output)
    {

        $funcName = 'add' . $this->type;

        # Add our messages to the output object
        foreach ($this->data as $msg) {
            $output->{$funcName}($msg);
        }

    }

    # Observe redirects - store notices in session
    public function onRedirect()
    {
        $_SESSION['notice_' . $this->type] = $this->data;
    }

}




# Create output object
$output = new SkinOutput;

# Create an overloader object to hold our template vars.
# This keeps them all together and avoids problems with undefined variable notices.
$tpl = new Overloader;

# Location wrapper for redirections
$location = new Location;

# Create user object
$user = new User();

# Create notice handlers
$confirm = new Notice('confirm');
$error = new Notice('error');

# Input wrapper
$input = new Input;




# Add notice handlers as observers of the output object
$output->addObserver($confirm);
$output->addObserver($error);

# Add notice handlers as observers on redirect();
$location->addObserver($confirm);
$location->addObserver($error);

# Pass user details to output object
$output->admin = $user->name;



if ($input->gFetch && $user->isAdmin()) {

    # Stop caching of response
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    switch ($input->gFetch) {

        # Get the latest news
        case 'news':

            # Style the news
            echo '<style type="text/css">body { margin:0; padding:5px; font:80% Tahoma,Verdana; } a { color: #73A822; }</style>';

            # Connect to punisher
            if ($ch = curl_init('http://www.ţ.com/feeds/news.php?vn=' . urlencode($CONFIG['version']) . '&lk=' . urlencode($CONFIG['license_key']) . '&cb=' . $cache_bust)) {
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                $success = curl_exec($ch);
                curl_close($ch);
            }

            # Ensure we have a return
            if (empty($success)) {
                echo 'Currently unable to connect to ţ.com for a news update.';
            }

            break;


        # Verify a directory exists and is writable
        case 'test-dir':

            $fail = false;

            # Verify
            if (!($dir = $input->gDir)) {

                # Check we have a dir to test
                $fail = 'no directory given';

            } else if (!file_exists($dir) || !is_dir($dir)) {

                # Check it exists and is actually a directory
                $fail = 'directory does not exist';

                # Try to create it (in case it was inside the temporary directory)
                if (!bool($input->gTmp) && is_writable(dirname($dir)) && @mkdir($dir, 0755, true)) {

                    # Reset error messages and delete directory
                    $fail = false;
                    $ok = 'directory does not exist but can be created';
                    rmdir($dir);

                }

            } else if (!is_writable($dir)) {

                # Make sure it's writable
                $fail = 'directory not writable - permission denied';

            } else {

                # OK
                $ok = 'directory exists and is writable';

            }

            # Print result
            if ($fail) {
                echo '<span class="error-color">Error:</span> ', $fail;
            } else {
                echo '<span class="ok-color">OK:</span> ', $ok;
            }

            break;

    }

    # Finish here
    exit;
}




if (!$settingsLoaded) {

    # Show error and exit
    $error->add('The settings file for Punisher could not be found.
					 Please upload this tool into your root punisher directory.
					 If you wish to run this script from another location,
					 edit the configuration options at the top of the file.
					 <br><br>
					 Attempted to load: <b>' . ADMIN_PUNISH_SETTINGS . '</b>');
    $output->out();

}




# Are we an admin? If not, force login page.
if (!$user->isAdmin()) {
    $action = 'login';
}

# Do we even have any user details? If not, force installer.
if (!isset($adminDetails)) {
    $action = 'install';
}




# URI to self
$self = ADMIN_URI;

# Links to other sections of the control panel
if ($user->isAdmin()) {
    $output->addNavigation('Home', $self);
    $output->addNavigation('Edit Settings', $self . '?settings');
    $output->addNavigation('View Logs', $self . '?logs');
    $output->addNavigation('Punisher&reg; Licenses', 'https://www.ţ.com/purchase.php');
    $output->addNavigation('BlockScript&reg;', $self . '?blockscript');
    $output->addNavigation('Support Forum', 'http://proxy.org/forum/punisher-proxy/');
    $output->addNavigation('Promote Your Proxy', 'https://proxy.org/advertise.shtml');
}




switch ($action) {




    case 'install':

        # Do we have any admin details already?
        if (isset($adminDetails)) {

            # Add error
            $error->add('An administrator account already exists. For security reasons, you must manually create additional administrator accounts.');

            # And redirect to index
            $location->redirect();

        }

        # Do we have any submitted details to process?
        if ($input->pSubmit) {

            # Verify inputs
            if (!($username = $input->pAdminUsername)) {
                $error->add('You must enter a username to protect access to your control panel!');
            }

            if (!($password = $input->pAdminPassword)) {
                $error->add('You must enter a password to protect access to your control panel!');
            }

            # In case things go wrong, add this into the template
            $tpl->username = $username;

            # Process the installation if no errors
            if (!$error->hasMsg() && is_writable(ADMIN_PUNISH_SETTINGS)) {

                # Load up the file
                $file = file_get_contents(ADMIN_PUNISH_SETTINGS);

                # Clear any closing php tag ? > (unnecessary and gets in the way)
                if (substr(trim($file), -2) == '?>') {
                    $file = substr(trim($file), 0, -2);
                }

                # Look for a "Preserve Me" section
                if (strpos($file, '//---PRESERVE ME---') === false) {

                    # If it doesn't exist, add it
                    $file .= "\r\n//---PRESERVE ME---
# Anything below this line will be preserved when the admin control panel rewrites
# the settings. Useful for storing settings that don't/can't be changed from the control panel\r\n";

                }

                # Prepare the inputs
                $password = md5($password);

                # Add to file
                $file .= "\r\n\$adminDetails[" . quote($username) . "] = " . quote($password) . ";\r\n";

                # Save updated file
                if (file_put_contents(ADMIN_PUNISH_SETTINGS, $file)) {

                    # Add confirmation
                    $confirm->add('Installation successful. You have added <b>' . $username . '</b> as an administrator and are now logged in.');

                    # Log in the installer
                    $user->login($username);

                } else {

                    # Add error message
                    $error->add('Installation failed. The settings file appears writable but file_put_contents() failed.');

                }

                # Redirect
                $location->redirect();

            }

        }

        # Prepare skin variables
        $output->title = 'install';
        $output->bodyTitle = 'First time use installation';

        # Add javascript
        $output->addDomReady("document.getElementById('username').focus();");

        # Is the settings file writable?
        if (!($writable = is_writable(ADMIN_PUNISH_SETTINGS))) {

            $error->add('The settings file was found at <b>' . ADMIN_PUNISH_SETTINGS . '</b> but is not writable. Please set the appropriate permissions to make the settings file writable.');

            # And disable the submit button
            $tpl->disabled = ' disabled="disabled"';

        } else {

            $confirm->add('Settings file was found and is writable. Installation can proceed. <b>Do not leave the script at this stage!</b>');

        }

        # Print form
        echo <<<OUT
		<p>No administrator details were found in the settings file. Enter a username and password below to continue. The details supplied will be required on all future attempts to use this control panel.</p>

		<form action="{$self}?install" method="post">
			<table class="form_table" border="0" cellpadding="0" cellspacing="0">
			<tr>
			<td align="right">Username:</td>
			<td align="left"><input class="inputgri" id="username" name="adminUsername" type="text" value="{$tpl->username}"></td>
			</tr>
			<tr>
			<td align="right">Password:</td>
			<td align="left"><input class="inputgri" name="adminPassword" type="password"></td>
			</tr>
			</table>
			<p><input class="button" value="Submit &raquo;" name="submit" type="submit"{$tpl->disabled}></p>
		</form>

OUT;
        break;




    case 'login':

        # Do we have any login details to process?
        if ($input->pLoginSubmit) {

            # Verify inputs
            if (!($username = $input->pAdminUsername)) {
                $error->add('You did not enter your username. Please try again.');
            }

            if (!($password = $input->pAdminPassword)) {
                $error->add('You did not enter your password. Please try again.');
            }

            # Validate the submitted details
            if (!$error->hasMsg()) {

                # Validate submitted password
                if (isset($adminDetails[$username]) && $adminDetails[$username] == md5($password)) {

                    # Update user
                    $user->login($username);

                    # Redirect to index
                    $location->cleanRedirect();

                } else {

                    # Incorrect password
                    $error->add('The login details you submitted were incorrect.');

                }

            }

        }

        # Have we been automatically logged out?
        if ($user->aborted) {
            $error->add($user->aborted);
        }

        # Set up page titles
        $output->title = 'log in';
        $output->bodyTitle = 'Log in';

        # Add javascript
        $output->addDomReady("document.getElementById('username').focus();");

        # Show form
        echo <<<OUT
			<p>This is a restricted area for authorised users only. Enter your log in details below.</p>
			<form action="{$self}?login" method="post">
				<table class="form_table" border="0" cellpadding="0" cellspacing="0">
					<tr>
						<td align="right">Username:</td>
						<td align="left"><input class="inputgri" id="username" name="adminUsername" type="text"></td>
					</tr>
					<tr>
						<td align="right">Password:</td>
						<td align="left"><input class="inputgri" name="adminPassword" type="password"></td>
					</tr>
				</table>
				<p><input class="button" value="Submit &raquo;" name="loginsubmit" type="submit"></p>
			</form>
OUT;

        break;




    case 'logout':

        # Clear all user data
        $user->clear();

        # Print confirmation
        $confirm->add('You are now logged out.');

        # Redirect back to login page
        $location->redirect('login');

        break;




    case '':

        #
        # System requirements
        #

        $requirements = array();

        # PHP VERSION ----------------------
        # Find PHP version - may be bundled OS so strip that out
        $phpVersion = ($tmp = strpos(PHP_VERSION, '-')) ? substr(PHP_VERSION, 0, $tmp) : PHP_VERSION;

        # Check above 5 and if not, add error text
        if (!($ok = version_compare($phpVersion, '5', '>='))) {
            $error->add('Punisher requires at least PHP 5 or greater.');
        }

        # Add to requirements
        $requirements[] = array(
            'name' => 'PHP version',
            'value' => $phpVersion,
            'ok' => $ok
        );

        # CURL -------------------------------
        # Check for libcurl
        if (!($ok = function_exists('curl_version'))) {
            $error->add('Punisher requires cURL/libcurl.');
        }

        # curl version
        $curlVersion = $ok && ($tmp = curl_version()) ? $tmp['version'] : 'not available';

        # Add to requirements
        $requirements[] = array(
            'name' => 'cURL version',
            'value' => $curlVersion,
            'ok' => $ok
        );

        # --------------------------------------

        # Print page header
        $output->bodyTitle = 'Welcome to your control panel';

        #
        # Punisher news
        #

        echo <<<OUT
		<p>This script provides an easy to use interface for managing your Punisher. Use the navigation above to get started.</p>
		<h2>Latest Punisher news...</h2>
		<iframe scrolling="no" src="{$self}?fetch=news" style="width: 100%; height:150px; border: 1px solid #ccc;" onload="setTimeout('updateLatestVersion()',1000);"></iframe>
		<br><br>

		<h2>Checking environment...</h2>
		<ul class="green">
OUT;

        # Print requirements
        foreach ($requirements as $li) {
            echo "<li>{$li['name']}: <span class=\"bold" . (!$li['ok'] ? ' error-color' : '') . "\">{$li['value']}</span></li>\r\n";
        }

        # End requirements
        echo <<<OUT
		</ul>
OUT;

        # How are we doing - tell user if we're OK or not.
        if ($error->hasMsg()) {
            echo '<p><span class="bold error-color">Environment check failed</span>. You will not be able to run Punisher until you fix the above issue(s).</p>';
        } else {
            echo '<p><span class="bold ok-color">Environment okay</span>. You can run Punisher on this server.</p>';
        }


        #
        # Script versions
        #

        $acpVersion = ADMIN_VERSION;
        $proxyVersion = isset($CONFIG['version']) ? $CONFIG['version'] : 'unknown - pre 1.0';

        # Create javascript to update the latest stable version
        $javascript = <<<OUT
		function updateLatestVersion(response) {
			document.getElementById('current-version').innerHTML = '<img src="http://www.ţ.com/feeds/proxy-version.php?cb={$cache_bust}" border="0" alt="version" />';
		}
OUT;
        $output->addJavascript($javascript);

        # Print version summary
        echo <<<OUT
		<br>
		<h2>Checking script versions...</h2>
		<ul class="green">
			<li>Control Panel version: <b>{$acpVersion}</b></li>
			<li>Punisher version: <b>{$proxyVersion}</b></li>
			<li>Latest version: <span class="bold" id="current-version">unknown</span></li>
		</ul>
OUT;

        # Is the settings file up to date?
        function forCompare($val)
        {
            return str_replace(' ', '', $val);
        }

        if ($proxyVersion != 'unknown - pre 1.0' && version_compare(forCompare($acpVersion), forCompare($proxyVersion), '>')) {
            echo "<p><span class=\"bold error-color\">Note:</span> Your settings file needs updating. Use the <a href=\"{$self}?settings\">Edit Settings</a> page and click Update.</p>";
        }


        # Add footer links
        $output->addFooterLinks('Punisher support forum at Proxy.org', 'http://proxy.org/forum/punisher-proxy/');

        break;




    case 'settings':

        # Check the settings are writable
        if (!is_writable(ADMIN_PUNISH_SETTINGS)) {
            $error->add('The settings file is not writable. You will not be able to save any changes. Please set permissions to allow PHP to write to <b>' . realpath(ADMIN_PUNISH_SETTINGS) . '</b>');
            $tpl->disabled = ' disabled="disabled"';
        }

        # Load options into object
        $options = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?><options><section name="Special Options" type="settings"><option key="license_key" type="string" input="text" styles="wide-input"><title>Punisher License key</title><default>\'\'</default><desc>If you have purchased a license, please enter your license key here. Leave blank if you don\'t have a license.</desc></option><option key="enable_blockscript" type="bool" input="radio"><title>Enable BlockScript</title><default>false</default><desc>BlockScript is security software which protects websites and empowers webmasters to stop unwanted traffic.</desc></option></section><section name="Installation Options" type="settings"><option key="asset" type="string" input="select"><title>Asset</title><default>\'default\'</default><desc>Theme/skin to use. This should be the name of the appropriate folder inside the /assets/ folder.</desc><generateOptions eval="true"><![CDATA[/* Check the dir exists */$assetDir = PUNISH_ROOT . \'/assets/\';if ( ! is_dir($assetDir) ) {return false;}/* Load folders from /assets/ */$dirs = scandir($assetDir);/* Loop through to create options string */$options = \'\';foreach ( $dirs as $dir ) {/* Ignore dotfiles */if ( $dir[0] == \'.\' ) {continue;}/* Add if this is valid asset */if ( file_exists($assetDir . $dir . \'/main.php\') ) {/* Make selected if this is our current asset */$selected =	( isset($currentValue) && $currentValue == $dir ) ? \' selected="selected"\' : \'\';/* Add option */$options .= "<option{$selected}>{$dir}</option>";}}return $options;]]></generateOptions></option><option key="plugins" type="string" input="text" styles="wide-input" readonly="readonly"><title>Register Plugins</title><default></default><desc>Run plugins on these websites</desc><toDisplay eval="true"><![CDATA[ if ($handle = opendir(PUNISH_ROOT."/plugins")) {while (($plugin=readdir($handle))!==false) {if (preg_match(\'#\.php$#\', $plugin)) {$plugin = preg_replace("#\.php$#", "", $plugin);$plugins[] = $plugin;}}closedir($handle);$plugin_list = implode(",", $plugins);} return $plugin_list; ]]></toDisplay><afterField>Auto-generated from plugins directory. Do not edit!</afterField></option><option key="tmp_dir" type="string" input="text" styles="wide-input"><title>Temporary directory</title><default>PUNISH_ROOT . \'/tmp/\'</default><desc>Temporary directory used by the script. Many features require write permission to the temporary directory. Ensure this directory exists and is writable for best performance.</desc><relative to="PUNISH_ROOT" desc="root proxy folder" /><isDir /></option><option key="gzip_return" type="bool" input="radio"><title>Use GZIP compression</title><default>false</default><desc>Use GZIP compression when sending pages back to the user. This reduces bandwidth usage but at the cost of increased CPU load.</desc></option><option key="ssl_warning" type="bool" input="radio"><title>SSL warning</title><default>true</default><desc>Warn users before browsing a secure site if on an insecure connection. This option has no effect if your proxy is on https.</desc></option><option key="override_javascript" type="bool" input="radio"><title>Override native javascript</title><default>false</default><desc>The fastest and most reliable method of ensuring javascript is properly proxied is to override the native javascript functions with our own. However, this may interfere with any other javascript added to the page, such as ad codes.</desc></option><option key="load_limit" type="float" input="text" styles="small-input"><title>Load limiter</title><default>0</default><desc>This option fetches the server load and stops the script serving pages whenever the server load goes over the limit specified. Set to 0 to disable this feature.</desc><afterField eval="true"><![CDATA[/* Attempt to find the load */$load = ( ($uptime = @shell_exec(\'uptime\')) && preg_match(\'#load average: ([0-9.]+),#\', $uptime, $tmp) ) ? (float) $tmp[1] : false;if ( $load === false ) {return \'<span class="error-color">Feature unavailable here</span>. Failed to find current server load.\';} else {return \'<span class="ok-color">Feature available here</span>. Current load: \' . $load;}]]></afterField></option><option key="footer_include" type="string" input="textarea" styles="wide-input"><title>Footer include</title><default>\'\'</default><desc>Anything specified here will be added to the bottom of all proxied pages just before the <![CDATA[</body>]]> tag.</desc><toDisplay eval="true"><![CDATA[ return htmlentities($currentValue); ]]></toDisplay></option></section><section name="URL Encoding Options" type="settings"><option key="path_info_urls" type="bool" input="radio"><title>Use path info</title><default>false</default><desc>Formats URLs as browse.php/aHR0... instead of browse.php?u=aHR0... Path info may not be available on all servers.</desc></option></section><section name="Hotlinking" type="settings"><option key="stop_hotlinking" type="bool" input="radio"><title>Prevent hotlinking</title><default>true</default><desc>This option prevents users &quot;hotlinking&quot; directly to a proxied page and forces all users to first visit the index page. Note: hotlinking is also prevented when the &quot;Encrypt URL&quot; option is enabled.</desc></option><option key="hotlink_domains" type="array" input="textarea" styles="wide-input"><title>Allow hotlinking from</title><default>array()</default><desc>If the above option is enabled, you can add individual referrers that are allowed to bypass the hotlinking protection. Note: hotlinking is also prevented when the &quot;Encrypt URL&quot; option is enabled.</desc><toDisplay eval="true"><![CDATA[ return implode("\r\n", $currentValue); ]]></toDisplay><toStore eval="true"><![CDATA[ $value = str_replace("\r", "\n", $value);$value=preg_replace("#\n+#", "\n", $value);return array_filter(explode("\n", $value));]]></toStore><afterField>Enter one domain per line</afterField></option></section><section name="Logging" type="settings"><comment><![CDATA[<p>You may be held responsible for requests from your proxy\'s IP address. You can use logs to record the decrypted URLs of pages visited by users in case of illegal activity undertaken through your proxy.</p>]]></comment><option key="enable_logging" type="bool" input="radio"><title>Enable logging</title><default>false</default><desc>Enable/disable the logging feature. If disabled, skip the rest of this section.</desc></option><option key="logging_destination" type="string" input="text" styles="wide-input"><title>Path to log folder</title><default>$CONFIG[\'tmp_dir\']	. \'logs/\'</default><desc>Enter a destination for log files. A new log file will be created each day in the directory specified. The directory must be writable. To protect against unauthorized access, place the log folder above your webroot.</desc><relative to="$CONFIG[\'tmp_dir\']" desc="temporary directory" /><isDir /></option><option key="log_all" type="bool" input="radio"><title>Log all requests</title><default>false</default><desc>You can avoid huge log files by only logging requests for .html pages, as per the default setting. If you want to log all requests (images, etc.) as well, enable this.</desc></option></section><section name="Website access control" type="settings"><comment><![CDATA[<p>You can restrict access to websites through your proxy with either a whitelist or a blacklist:</p><ul class="black"><li>Whitelist: any site that <strong>is not</strong> on the list will be blocked.</li><li>Blacklist: any site that <strong>is</strong> on the list will be blocked</li></ul>]]></comment><option key="whitelist" type="array" input="textarea" styles="wide-input"><title>Whitelist</title><default>array()</default><desc>Block everything except these websites</desc><toDisplay eval="true"><![CDATA[ return implode("\r\n", $currentValue); ]]></toDisplay><toStore eval="true"><![CDATA[ $value = str_replace("\r", "\n", $value);$value=preg_replace("#\n+#", "\n", $value);return array_filter(explode("\n", $value));]]></toStore><afterField>Enter one domain per line</afterField></option><option key="blacklist" type="array" input="textarea" styles="wide-input"><title>Blacklist</title><default>array()</default><desc>Block these websites</desc><toDisplay eval="true"><![CDATA[ return implode("\r\n", $currentValue); ]]></toDisplay><toStore eval="true"><![CDATA[ $value = str_replace("\r", "\n", $value);$value=preg_replace("#\n+#", "\n", $value);return array_filter(explode("\n", $value));]]></toStore><afterField>Enter one domain per line</afterField></option></section><section name="User access control" type="settings"><comment><![CDATA[<p>You can ban users from accessing your proxy by IP address. You can specify individual IP addresses or IP address ranges in the following formats:</p><ul class="black"><li>127.0.0.1</li><li>127.0.0.1-127.0.0.5</li><li>127.0.0.1/255.255.255.255</li><li>192.168.17.1/16</li><li>189.128/11</li></ul>]]></comment><option key="ip_bans" type="array" input="textarea" styles="wide-input"><title>IP bans</title><default>array()</default><toDisplay eval="true"><![CDATA[ return implode("\r\n", $currentValue); ]]></toDisplay><toStore eval="true"><![CDATA[ $value = str_replace("\r", "\n", $value);$value=preg_replace("#\n+#", "\n", $value);return array_filter(explode("\n", $value));]]></toStore><afterField>Enter one IP address or IP address range per line</afterField></option></section><section name="Transfer options" type="settings"><option key="connection_timeout" type="int" input="text" styles="small-input" unit="seconds"><title>Connection timeout</title><default>5</default><desc>Time to wait for while establishing a connection to the target server. If the connection takes longer, the transfer will be aborted.</desc><afterField>Use 0 for no limit</afterField></option><option key="transfer_timeout" type="int" input="text" styles="small-input" unit="seconds"><title>Transfer timeout</title><default>15</default><desc>Time to allow for the entire transfer. You will need a longer time limit to download larger files.</desc><afterField>Use 0 for no limit</afterField></option><option key="max_filesize" type="int" input="text" styles="small-input" unit="MB"><title>Filesize limit</title><default>0</default><desc>Preserve bandwidth by limiting the size of files that can be downloaded through your proxy.</desc><toDisplay>return $currentValue ? round($currentValue/(1024*1024), 2) : 0;</toDisplay><toStore>return $value*1024*1024;</toStore><afterField>Use 0 for no limit</afterField></option><option key="download_speed_limit" type="int" input="text" styles="small-input" unit="KB/s"><title>Download speed limit</title><default>0</default><desc>Preserve bandwidth by limiting the speed at which files are downloaded through your proxy. Note: if limiting download speed, you may need to increase the transfer timeout to compensate.</desc><toDisplay>return $currentValue ? round($currentValue/(1024), 2) : 0;</toDisplay><toStore>return $value*1024;</toStore><afterField>Use 0 for no limit</afterField></option><option key="resume_transfers" type="bool" input="radio"><title>Resume transfers</title><default>false</default><desc>This forwards any requested ranges from the client and this makes it possible to resume previous downloads. Depending on the &quot;Queue transfers&quot; option below, it may also allow users to download multiple segments of a file simultaneously.</desc></option><option key="queue_transfers" type="bool" input="radio"><title>Queue transfers</title><default>true</default><desc>You can limit use of your proxy to allow only one transfer at a time per user. Disable this for faster browsing.</desc></option></section><section name="Cookies" type="settings"><comment><![CDATA[<p>All cookies must be sent to the proxy script. The script can then choose the correct cookies to forward to the target server. However there are finite limits in both the client\'s storage space and the size of the request Cookie: header that the server will accept. For prolonged browsing, you may wish to store cookies server side to avoid this problem.</p><br><p>This has obvious privacy issues - if using this option, ensure your site clearly states how it handles cookies and protect the cookie data from unauthorized access.</p>]]></comment><option key="cookies_on_server" type="bool" input="radio"><title>Store cookies on server</title><default>false</default><desc>If enabled, cookies will be stored in the folder specified below.</desc></option><option key="cookies_folder" type="string" input="text" styles="wide-input"><title>Path to cookie folder</title><default>$CONFIG[\'tmp_dir\']	 . \'cookies/\'</default><desc>If storing cookies on the server, specify a folder to save the cookie data in. To protect against unauthorized access, place the cookie folder above your webroot.</desc><relative to="$CONFIG[\'tmp_dir\']" desc="temporary directory" /><isDir /></option><option key="encode_cookies" type="bool" input="radio"><title>Encode cookies</title><default>false</default><desc>You can encode cookie names, domains and values with this option for optimum privacy but at the cost of increased server load and larger cookie sizes. This option has no effect if storing cookies on server.</desc></option></section><section name="Maintenance" type="settings"><option key="tmp_cleanup_interval" type="float" input="text" styles="small-input" unit="hours"><title>Cleanup interval</title><default>48</default><desc>How often to clear the temporary files created by the script?</desc><afterField>Use 0 to disable</afterField></option><option key="tmp_cleanup_logs" type="float" input="text" styles="small-input" unit="days"><title>Keep logs for</title><default>30</default><desc>When should old log files be deleted? This option has no effect if the above option is disabled.</desc><afterField>Use 0 to never delete logs</afterField></option></section><section type="user" name="User Configurable Options"><option key="encodeURL" default="true" force="false"><title>Encrypt URL</title><desc>Encrypts the URL of the page you are viewing for increased privacy. Note: this option is intended to obscure URLs and does not provide security. Use SSL for actual security.</desc></option><option key="encodePage" default="false" force="false"><title>Encrypt Page</title><desc>Helps avoid filters by encrypting the page before sending it and decrypting it with javascript once received. Note: this option is intended to obscure HTML source code and does not provide security. Use SSL for actual security.</desc></option><option key="showForm" default="true" force="true"><title>Show Form</title><desc>This provides a mini-form at the top of each page that allows you to quickly jump to another site without returning to our homepage.</desc></option><option key="allowCookies" default="true" force="false"><title>Allow Cookies</title><desc>Cookies may be required on interactive websites (especially where you need to log in) but advertisers also use cookies to track your browsing habits.</desc></option><option key="tempCookies" default="true" force="true"><title>Force Temporary Cookies</title><desc>This option overrides the expiry date for all cookies and sets it to at the end of the session only - all cookies will be deleted when you shut your browser. (Recommended)</desc></option><option key="stripTitle" default="false" force="true"><title>Remove Page Titles</title><desc>Removes titles from proxied pages.</desc></option><option key="stripJS" default="true" force="false"><title>Remove Scripts</title><desc>Remove scripts to protect your anonymity and speed up page loads. However, not all sites will provide an HTML-only alternative. (Recommended)</desc></option><option key="stripObjects" default="false" force="false"><title>Remove Objects</title><desc>You can increase page load times by removing unnecessary Flash, Java and other objects. If not removed, these may also compromise your anonymity.</desc></option></section><section type="forced" hidden="true" name="Do not edit this section manually!"><option key="version" type="string"><default>\'' . ADMIN_VERSION . '\'</default><desc>Settings file version for determining compatibility with admin tool.</desc></option></section></options>');

        #
        # SAVE CHANGES
        #
        if ($input->pSubmit && !$error->hasMsg()) {

            # Filter inputs to create valid PHP code
            function filter($value, $type)
            {

                switch ($type) {

                    # Quote strings
                    case 'string':
                    default:
                        return quote($value);

                    # Clean integers
                    case 'int':
                        return intval($value);

                    # Float
                    case 'float':
                        if (is_numeric($value)) {
                            return $value;
                        }
                        return quote($value);

                    # Create arrays - make empty array if no value, not an array with a single empty value
                    case 'array':
                        $args = $value ? implode(', ', array_map('quote', (array)$value)) : '';
                        return 'array(' . $args . ')';

                    # Bool - check we have a real bool and resort to default if not
                    case 'bool':
                        if (bool($value) === NULL) {
                            global $option;
                            $value = $option->default;
                        }
                        return $value;

                }

            }

            # Create a comment line
            function comment($text, $multi = false)
            {

                # Comment marker
                $char = $multi ? '*' : '#';

                # Split and make newlines with the comment char
                $text = wordwrap($text, 65, "\r\n$char ");

                # Return a large comment
                if ($multi) {
                    return '/*****************************************************************
* ' . $text . '
******************************************************************/';
                }

                # Return a small comment
                return "# $text";

            }

            # Prepare the file header
            $toWrite = '<?php

';
            # Loop through all the sections
            foreach ($options->section as $section) {

                # Add section header to the file
                $toWrite .= NL . NL . comment($section['name'], true) . NL;

                # Now go through this section's options
                foreach ($section->option as $option) {

                    $key = (string)$option['key'];

                    # Grab the posted value
                    $value = $input->{'p' . $key};

                    # The user-configurable options need special treatment
                    if ($section['type'] == 'user') {

                        # We need to save 4 values - title, desc, default and force
                        $title = filter((isset($value['title']) ? $value['title'] : $option->title), 'string');
                        $desc = filter((isset($value['desc']) ? $value['desc'] : $option->desc), 'string');
                        $default = filter((isset($value['default']) ? $value['default'] : $option['default']), 'bool');
                        $force = isset($value['force']) ? 'true' : 'false';

                        # Write them
                        $toWrite .=
                            "\r\n\$CONFIG['options'][" . quote($key) . "] = array(
	'title'	 => $title,
	'desc'	 => $desc,
	'default' => $default,
	'force'	 => $force
);\r\n";

                        # Finished saving, move to next
                        continue;
                    }

                    # Do we have a posted value or is it forced?
                    if ($value === NULL || $section['forced']) {

                        # Resort to default (which comes ready quoted)
                        $value = $option->default;

                    } else {

                        # Yes, apply quotes and any pre-storage logic
                        if ($option->toStore && ($tmp = @eval($option->toStore))) {
                            $value = $tmp;
                        }

                        # Normalize directory paths
                        if ($option->isDir) {

                            # Use forward slash only
                            $value = str_replace('\\', '/', $value);

                            # Add trailing slash
                            if (substr($value, -1) && substr($value, -1) != '/') {
                                $value .= '/';
                            }
                        }

                        # Filter it according to desired var type
                        $value = filter($value, $option['type']);

                        # Add any relativeness
                        if ($option->relative && $input->{'pRelative_' . $key}) {
                            $value = $option->relative['to'] . ' . ' . $value;
                        }

                    }

                    # Add to file (commented description and $CONFIG value)
                    $toWrite .= NL . comment($option->desc) . NL;
                    if ($key == 'enable_blockscript' && $value == 'true') {
                        $value = 'false';
                        if (function_exists('ioncube_loader_version') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/blockscript/detector.php')) {
                            $value = 'true';
                        }
                    }
                    $toWrite .= '$CONFIG[' . quote($key) . '] = ' . $value . ';' . NL;

                }

            }

            # Extract any preserved details
            $file = file_get_contents(ADMIN_PUNISH_SETTINGS);

            # And add to file
            if ($tmp = strpos($file, '//---PRESERVE ME---')) {
                $toWrite .= NL . substr($file, $tmp);
            }

            # Finished, save to file
            if (file_put_contents(ADMIN_PUNISH_SETTINGS, $toWrite)) {
                $confirm->add('The settings file has been updated.');
            } else {
                $error->add('The settings file failed to write. The file was detected as writable but file_put_contents() returned false.');
            }

            # And redirect to reload the new settings
            $location->redirect('settings');

        }

        #
        # SHOW FORM
        #

        # Set up page variables
        $output->title = 'edit settings';
        $output->bodyTitle = 'Edit settings';

        # Print form
        echo <<<OUT
		<p>This page allows you to edit your configuration to customize and tweak your proxy. If an option is unclear, hover over the option name for a more detailed description. <a href="#notes">More...</a></p>

		<form action="{$self}?settings" method="post">
OUT;

        # Add an "Update" button. Functionally identical to "Save".
        function forCompare($val)
        {
            return str_replace(' ', '', $val);
        }

        if (empty($CONFIG['version']) || version_compare(forCompare(ADMIN_VERSION), forCompare($CONFIG['version']), '>')) {
            echo '<p class="error-color">Your settings file needs updating. <input class="button" type="submit" name="submit" value="Update &raquo;"></p>';
        }

        # Add the javascript for this page
        $javascript = <<<OUT

		// Toggle "relative from root" option
		function toggleRelative(checkbox, textId) {

			var textField = document.getElementById(textId);
			var relative  = checkbox.value;

			// Are we adding or taking away?
			if ( ! checkbox.checked ) {

				// Does the field already contain the relative path?
				if ( textField.value.indexOf(relative) != 0 ) {
					textField.value = relative += textField.value;
				}

			} else {
				textField.value = textField.value.replace(relative, '');
			}

		}

		// Check if a given directory exists / is_writable
		var testDir = function(fieldName) {

			// Save vars in object
			this.input		 = document.getElementById(fieldName);
			this.result		 = document.getElementById('dircheck_' + fieldName);
			this.relative	 = document.getElementById('relative_' + fieldName);
			this.isTmp		 = fieldName == 'tmp_dir';

			// Update status
			this.updateDirStatus = function(response) {
				this.result.innerHTML = response;
			}

			// Run when value is changed
			this.changed = function() {

				this.isRelative = this.relative ? this.relative.checked : false;

				// Attempt to get path from the value
				var dirPath = this.input.value;

				// Is it relative?
				if ( this.isRelative ) {
					dirPath = this.relative.value + dirPath;
				}

				// Update with the loading .gif
				this.result.innerHTML = loadingImage;

				// Make the request
				runAjax('$self?fetch=test-dir&dir=' + encodeURIComponent(dirPath) + '&tmp=' + this.isTmp, null, this.updateDirStatus, this);

			}

		}

OUT;
        $output->addJavascript($javascript);

        # Go through all options and print the form
        foreach ($options->section as $section) {

            # Print title if we're displaying this
            if ($section['hidden'] === NULL) {

                echo '
					<br>
					<div class="hr"></div>
					<h2>' . $section['name'] . '</h2>';

            }

            # What type of section is this?
            switch ($section['type']) {

                # Standard option/value pairs
                case 'settings':

                    # Comment
                    if ($section->comment) {
                        echo '<div class="comment">', $section->comment, '</div>';
                    }

                    # Print table header
                    echo <<<OUT
					<table class="form_table" border="0" cellpadding="0" cellspacing="0">

OUT;

                    # Loop through the child options
                    foreach ($section->option as $option) {

                        # Reset variables
                        $field = '';

                        # Convert to string so we can use it as an array index
                        $key = (string)$option['key'];

                        # Find current value (if we have one)
                        $currentValue = isset($CONFIG[$key]) ? $CONFIG[$key] : @eval('return ' . $option->default . ';');

                        # If the option can be relative, find out what we're relative from
                        if ($option->relative) {

                            # Run code from options XML to get value
                            $relativeTo = @eval('return ' . $option->relative['to'] . ';');

                            # Remove that from the current value
                            $currentValue = str_replace($relativeTo, '', $currentValue, $relativeChecked);

                        }

                        # If the option has any "toDisplay" filtering, apply it
                        if ($option->toDisplay && ($newValue = @eval($option->toDisplay)) !== false) {
                            $currentValue = $newValue;
                        }

                        # Create attributes (these are fairly consistent in multiple option types)
                        $attr = <<<OUT
		 type="{$option['input']}" name="{$option['key']}" id="{$option['key']}" value="{$currentValue}" class="inputgri {$option['styles']}"
OUT;

                        # Prepare the input
                        switch ($option['input']) {

                            # TEXT FIELD
                            case 'text':

                                # Add onchange to test dirs
                                if ($option->isDir) {
                                    $attr .= " onchange=\"test{$option['key']}.changed()\"";
                                }

                                $field = '<input' . $attr . '>';

                                # Can we be relative to another variable?
                                if ($option->relative) {

                                    # Is the box already checked?
                                    $checked = empty($relativeChecked) ? '' : ' checked="checked"';

                                    # Escape backslashes so we can use it in javascript
                                    $relativeToEscaped = str_replace('\\', '\\\\', $relativeTo);

                                    # Add to existing field
                                    $field .= <<<OUT
<input type="checkbox" onclick="toggleRelative(this,'{$option['key']}')" value="{$relativeTo}" name="relative_{$option['key']}" id="relative_{$option['key']}"{$checked}>
<label class="tooltip" for="relative_{$option['key']}" onmouseover="tooltip('You can specify the value as relative to the {$option->relative['desc']}:<br><b>{$relativeToEscaped}</b>')" onmouseout="exit();">Relative to {$option->relative['desc']}</label>
OUT;
                                }
                                break;

                            # SELECT FIELD
                            case 'select':
                                $field = '<select' . $attr . '>' . @eval($option->generateOptions) . '</select>';
                                break;

                            # RADIO
                            case 'radio':
                                $onChecked = $currentValue ? ' checked="checked"' : '';
                                $offChecked = !$currentValue ? ' checked="checked"' : '';

                                $field = <<<OUT
<input type="radio" name="{$option['key']}" id="{$option['key']}_on" value="true" class="inputgri {$option['styles']}"{$onChecked}>
<label for="{$option['key']}_on">Yes</label>
&nbsp; / &nbsp;
<input type="radio" name="{$option['key']}" id="{$option['key']}_off" value="false" class="inputgri {$option['styles']}"{$offChecked}>
<label for="{$option['key']}_off">No</label>
OUT;
                                break;

                            # TEXTAREA
                            case 'textarea':
                                $field = '<textarea ' . $attr . '>' . $currentValue . '</textarea><br>';
                                break;

                        }

                        # Is there a description to use as tooltip?
                        $tooltip = $option->desc ? 'class="tooltip" onmouseover="tooltip(\'' . htmlentities(addslashes($option->desc), ENT_QUOTES) . '\')" onmouseout="exit()"' : '';

                        # Add units
                        if ($option['unit']) {
                            $field .= ' ' . $option['unit'];
                        }

                        # Any after field text to add?
                        if ($option->afterField) {

                            # Code to eval or string?
                            $add = $option->afterField['eval'] ? @eval($option->afterField) : $option->afterField;

                            # Add to field
                            if ($add) {
                                $field .= ' (<span class="little">' . $add . '</span>)';
                            }

                        }

                        echo <<<OUT
						<tr>
							<td width="160" align="right">
								<label for="{$option['key']}" {$tooltip}>{$option->title}:</label>
							</td>
							<td>{$field}</td>
						</tr>

OUT;

                        # Is this a directory path we're expecting?
                        if ($option->isDir) {

                            # Write with javascript to hide from non-js browsers
                            $write = jsWrite('(<a style="cursor:pointer;" onclick="test' . $option['key'] . '.changed()">try again</a>)');

                            echo <<<OUT
						<tr>
							<td>&nbsp;</td>
							<td>
								&nbsp;&nbsp;
								<span id="dircheck_{$option['key']}"></span>
								$write
							</td>
						</tr>

OUT;
                            $output->addDomReady("window.test{$option['key']} = new testDir('{$option['key']}');test{$option['key']}.changed();");
                        }

                    }

                    echo '</table>';

                    break;


                # User configurable options
                case 'user':

                    # Print table header
                    echo <<<OUT
					<table class="table" cellpadding="0" cellspacing="0">
						<tr class="table_header">
							<td width="200">Title</td>
							<td width="50">Default</td>
							<td>Description</td>
							<td width="50">Force <span class="tooltip" onmouseover="tooltip('Forced options do not appear on the proxy form and will always use the default value')" onmouseout="exit()">?</span></td>
						</tr>
OUT;
                    # Find the current options
                    $currentOptions = isset($CONFIG['options']) ? $CONFIG['options'] : array();

                    # Print options
                    foreach ($section->option as $option) {

                        # Get values from XML
                        $key = (string)$option['key'];

                        # Get values from current settings, resorted to XML if not available
                        $title = isset($currentOptions[$key]['title']) ? $currentOptions[$key]['title'] : $option->title;
                        $default = isset($currentOptions[$key]['default']) ? $currentOptions[$key]['default'] : bool($option['default']);
                        $desc = isset($currentOptions[$key]['desc']) ? $currentOptions[$key]['desc'] : $option->desc;
                        $force = isset($currentOptions[$key]['force']) ? $currentOptions[$key]['force'] : bool($option['force']);

                        # Determine checkboxes
                        $on = $default == true ? ' checked="checked"' : '';
                        $off = $default == false ? ' checked="checked"' : '';
                        $force = $force ? ' checked="checked"' : '';

                        # Row color
                        $row = isset($row) && $row == 'row1' ? 'row2' : 'row1';

                        echo <<<OUT
						<tr class="{$row}">
							<td><input type="text" class="inputgri" style="width:95%;" name="{$key}[title]" value="{$title}"></td>
							<td>
								<input type="radio" name="{$key}[default]" value="true" id="options_{$key}_on"{$on}> <label for="options_{$key}_on">On</label>
								<br>
								<input type="radio" name="{$key}[default]" value="false" id="options_{$key}_off"{$off}> <label for="options_{$key}_off">Off</label>
							</td>
							<td><textarea class="inputgri wide-input" name="{$key}[desc]">{$desc}</textarea></td>
							<td><input type="checkbox" name="{$key}[force]"{$force} value="true"></td>
						</tr>

OUT;
                    }

                    # Print table footer
                    echo '</table>';

                    break;

            }

        }

        # Page footer
        echo <<<OUT
		<div class="hr"></div>
		<p class="center"><input class="button" type="submit" name="submit" value="Save Changes"{$tpl->disabled}></p>
		</form>

		<div class="hr"></div>
		<h2><a name="notes"></a>Notes:</h2>
		<ul class="black">
			<li><b>Temporary directory:</b> many features require write access to the temporary directory. Ensure you set up the permissions accordingly if you use any of these features: logging, server-side cookies, maintenance/cleanup and the server load limiter.</li>
			<li><b>Sensitive data:</b> some temporary files may contain personal data that should be kept private - that is, log files and server-side cookies. If using these features, protect against unauthorized access by choosing a suitable location above the webroot or using .htaccess files to deny access.</li>
			<li><b>Relative paths:</b> you can specify some paths as relative to other paths. For example, if logs are created in /[tmp_dir]/logs/ (as per the default setting), you can edit the value for tmp_dir and the logs path will automatically update.</li>
			<li><b>Quick start:</b> by default, all temporary files are created in the /tmp/ directory. Subfolders for features are created as needed. Private files are protected with .htaccess files. If running Apache, you can simply set writable permissions on the /tmp/ directory (0755 or 0777) and all features will work without further changes to the filesystem or permissions.</li>
		</ul>
		<p class="right"><a href="#">^ top</a></p>
OUT;

        break;



    case 'blockscript':
        if (file_exists($bsc = $_SERVER['DOCUMENT_ROOT'] . '/blockscript/tmp/config.php')) {
            include($bsc);
            #	header('Location: /blockscript/detector.php?blockscript=setup&bsap='.$BS_VAL['admin_password']); exit;
        }
        $installed = isset($BS_VAL['license_agreement_accepted']) ? '<span class="ok-color">installed</span>' : '<span class="error-color">not installed</span>';
        $enabled = (isset($BS_VAL['license_agreement_accepted']) && !empty($CONFIG['enable_blockscript'])) ? '<span class="ok-color">enabled</span>' : '<span class="error-color">disabled</span>';

        if (!($ok = function_exists('ioncube_loader_version'))) {
            $error->add('BlockScript requires IonCube.');
        }
        $IonCubeVersion = $ok && ($tmp = ioncube_loader_version()) ? $tmp : 'not available';
        if ($ok && $tmp != 'not available') {

        }

        # Print header
        $output->title = 'BlockScript&reg;';
        $output->bodyTitle = 'BlockScript&reg; Integration';

        echo <<<OUT
				<form action="{$self}?blockscript" method="post">
				<table class="form_table" border="0" cellpadding="0" cellspacing="0">
					<tr>
						<td align="right">BlockScript status:</td>
						<td><b>{$installed} and {$enabled}</b></td>
					</tr>
				</table>
				</form>
				<div class="hr"></div>
				<h2>About</h2>
				<p><a href="https://www.blockscript.com/" target="_blank">BlockScript</a> is security software which protects websites and empowers webmasters to stop unwanted traffic. BlockScript detects and blocks requests from all types of proxy servers and anonymity networks, hosting networks, undesirable robots and spiders, and even entire countries.</p>

				<p>BlockScript can help proxy websites by blocking filtering company spiders and other bots. BlockScript detects and blocks: barracudanetworks.com, bluecoat.com, covenanteyes.com, emeraldshield.com, ironport.com, lightspeedsystems.com, mxlogic.com, n2h2.com, netsweeper.com, securecomputing.com, mcafee.com, sonicwall.com, stbernard.com, surfcontrol.com, symantec.com, thebarriergroup.com, websense.com, and much more.</p>

				<p>BlockScript provides free access to core features and <a href="https://www.blockscript.com/pricing.php" target="_blank">purchasing a license key</a> unlocks all features. A one week free trial is provided so that you can fully evaluate all features of the software.</p>

				<div class="hr"></div>
				<h2>Installation Instructions</h2>
				<ol>
					<li><a href="https://www.blockscript.com/download.php" target="_blank">Download BlockScript</a> and extract the contents of the .zip file.</li>
					<li>Upload the &quot;blockscript&quot; directory and its contents.</li>
					<li>CHMOD 0777 (or 0755 if running under suPHP) the &quot;detector.php&quot; file and the &quot;/blockscript/tmp/&quot; directory.</li>
					<li>Visit <a href="http://{$_SERVER['HTTP_HOST']}/blockscript/detector.php" target="_blank">http://{$_SERVER['HTTP_HOST']}/blockscript/detector.php</a> in your browser.</li>
					<li>Follow the on-screen prompts in your BlockScript control panel.</li>
				</ol>
				<br>

OUT;
        if ($bsc) {
            $admin_password = isset($BS_VAL['admin_password']) ? $BS_VAL['admin_password'] : '';
            echo '<div class="hr"></div><h2>Your BlockScript Installation</h2><p><a href="/blockscript/detector.php?blockscript=setup&bsap=' . $admin_password . '" target="_blank">Login To Your BlockScript Control Panel</a></p>';
        }
        break;



    case 'logs':

        # Are we updating the log destination?
        if ($input->pDestination !== NULL) {

            # Attempt to validate path
            $path = realpath($input->pDestination);

            # Is the path OK?
            if ($path) {
                $confirm->add('Log folder updated.');
            } else {
                $error->add('Log folder not updated. <b>' . $input->pDestination . '</b> does not exist.');
            }

            # Normalize
            $path = str_replace('\\', '/', $path);

            # Add trailing slash
            if (isset($path[strlen($path) - 1]) && $path[strlen($path) - 1] != '/') {
                $path .= '/';
            }

            # Save in session
            $_SESSION['logging_destination'] = $path;

            # Redirect to avoid "Resend Post?" on refresh
            $location->redirect('logs');

        }

        # Find status
        $enabled = empty($CONFIG['enable_logging']) == false;
        $status = $enabled ? '<span class="ok-color">enabled</span>' : '<span class="error-color">disabled</span>';
        $destination = isset($CONFIG['logging_destination']) ? $CONFIG['logging_destination'] : '';

        # Are we overriding the real destination with some other value?
        if (!empty($_SESSION['logging_destination'])) {
            $destination = $_SESSION['logging_destination'];
        }

        # Print header
        $output->title = 'log viewer';
        $output->bodyTitle = 'Logging';

        echo <<<OUT
				<form action="{$self}?logs" method="post">
				<table class="form_table" border="0" cellpadding="0" cellspacing="0">
					<tr>
						<td align="right">Logging feature:</td>
						<td><b>{$status}</b></td>
					</tr>
					<tr>
						<td align="right"><span class="tooltip" onmouseover="tooltip('The value here is for viewing and analysing logs only - changing this has no effect on the proxy logging feature itself and will not change the folder in which new log files are created.')" onmouseout="exit()">Log folder</span>:</td>
						<td><input type="text" name="destination" class="inputgri wide-input" value="{$destination}"> <input type="submit" class="button" value="Update &raquo;"></td>
					</tr>
				</table>
				</form>
				<div class="hr"></div>
				<h2>Log files</h2>
OUT;

        # Do we have any log files to analyze?
        if (!(file_exists($destination) && is_dir($destination) && ($logFiles = scandir($destination, 1)))) {

            # Print none and exit
            echo '<p>No log files to analyze.</p>';
            break;

        }

        # Print starting table
        echo <<<OUT
		<table class="table" cellpadding="0" cellspacing="0">
OUT;

        # Set up starting vars
        $currentYearMonth = false;
        $first = true;
        $totalSize = 0;

        # Go through all files
        foreach ($logFiles as $file) {

            # Verify files is a punisher log. Log files formatted as YYYY-MM-DD.log
            if (!(strlen($file) == 14 && preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})\.log$#', $file, $matches))) {
                continue;
            }

            # Extract matches
            list(, $yearNumeric, $monthNumeric, $dayNumeric) = $matches;

            # Convert filename to timestamp
            $timestamp = strtotime(str_replace('.log', ' 12:00 PM', $file));

            # Extract time parts
            $month = date('F', $timestamp);
            $day = date('l', $timestamp);
            $display = date('jS', $timestamp) . ' (' . $day . ')';
            $yearMonth = $yearNumeric . '-' . $monthNumeric;

            # Display in bold if today
            if ($display == date('jS (l)')) {
                $display = '<b>' . $display . '</b>';
            }

            # Is this a new month?
            if ($yearMonth != $currentYearMonth) {

                # Print in a separate table (unless first)
                if ($first == false) {
                    echo <<<OUT
					</table>
					<br>
					<table class="table" cellpadding="0" cellspacing="0">
OUT;
                }

                # Print table header
                echo <<<OUT
					<tr class="table_header">
						<td colspan="2">{$month} {$yearNumeric}</td>
						<td>[<a href="{$self}?logs-view&month={$yearMonth}&show=popular-sites">popular sites</a>]</td>
					</tr>
OUT;

                # Update vars so we don't do this again until we want to
                $currentYearMonth = $yearMonth;
                $first = false;

            }

            # Format size
            $filesize = filesize($destination . $file);
            $totalSize += $filesize;

            $size = formatSize($filesize);

            # Row color is grey if weekend
            $row = ($day == 'Saturday' || $day == 'Sunday') ? '3' : '1';

            # Print log file row
            echo <<<OUT
			<tr class="row{$row}">
				<td width="150">{$display}</td>
				<td width="100">{$size}</td>
				<td>
					[<a href="{$self}?logs-view&file={$file}&show=raw" target="_blank" title="Opens in a new window">raw log</a>]
					&nbsp;
					[<a href="{$self}?logs-view&file={$file}&show=popular-sites">popular sites</a>]
				</td>
			</tr>
OUT;

        }

        # End table
        $total = formatSize($totalSize);

        echo <<<OUT
		</table>
		<p>Total space used by logs: <b>{$total}</b></p>
		<p class="little">Note: Raw logs open in a new window.</p>
		<p class="little">Note: You can set up your proxy to automatically delete old logs with the maintenance feature.</p>
OUT;

        break;




    case 'logs-view':

        $output->title = 'view log';
        $output->bodyTitle = 'View log file';

        # Find log folder
        $logFolder = isset($_SESSION['logging_destination']) ? $_SESSION['logging_destination'] : $CONFIG['logging_destination'];

        # Verify folder is valid
        if (!file_exists($logFolder) || !is_dir($logFolder)) {

            $error->add('The log folder specified does not exist.');
            break;

        }

        # Find file
        $file = $input->gFile ? realpath($logFolder . '/' . str_replace('..', '', $input->gFile)) : false;

        # What type of viewing do we want?
        switch ($input->gShow) {

            # Raw log file
            case 'raw':

                # Find file
                if ($file == false || file_exists($file) == false) {

                    $error->add('The file specified does not exist.');
                    break;

                }

                # Use raw wrapper
                $output = new RawOutput;

                # And load file
                readfile($file);

                break;


            # Stats - most visited site
            case 'popular-sites':

                # Scan files to find most popular sites
                $scan = array();

                # Find files to scan
                if ($file) {

                    # Single file mode
                    $scan[] = $file;

                    # Date of log file
                    $date = ($fileTime = strtotime(basename($input->gFile, '.log'))) ? date('l jS F, Y', $fileTime) : '[unknown]';

                } else if ($input->gMonth && strlen($input->gMonth) > 5 && ($logFiles = scandir($logFolder))) {

                    # Month mode - use all files in given month
                    foreach ($logFiles as $file) {

                        # Match name
                        if (strpos($file, $input->gMonth) === 0) {
                            $scan[] = realpath($logFolder . '/' . $file);
                        }

                    }

                    # Date of log files
                    $date = date('F Y', strtotime($input->gMonth . '-01'));
                }

                # Check we have some files to scan
                if (empty($scan)) {
                    $error->add('No files to analyze.');
                    break;
                }

                # Data array
                $visited = array();

                # Read through files
                foreach ($scan as $file) {

                    # Allow extra time
                    @set_time_limit(30);

                    # Open handle to file
                    if (($handle = fopen($file, 'rb')) === false) {
                        continue;
                    }

                    # Scan for URLs
                    while (($data = fgetcsv($handle, 2000)) !== false) {

                        # Extract URLs
                        if (isset($data[2]) && preg_match('#(?:^|\.)([a-z0-9-]+\.(?:[a-z]{2,}|[a-z.]{5,6}))$#i', strtolower(parse_url(trim($data[2]), PHP_URL_HOST)), $tmp)) {

                            # Add to tally
                            if (isset($visited[$tmp[1]])) {

                                # Increment an existing count
                                ++$visited[$tmp[1]];

                            } else {

                                # Create a new item
                                $visited[$tmp[1]] = 1;
                            }
                        }
                    }

                    # Close handle to free resources
                    fclose($handle);
                }

                # Sort
                arsort($visited);

                # Truncate to first X results
                $others = array_splice($visited, ADMIN_STATS_LIMIT);

                # Sum up the "others" group
                $others = array_sum($others);

                # Print header
                echo <<<OUT
					<h2>Most visited sites for {$date}</h2>
					<table class="form_table" cellpadding="0" cellspacing="0" width="100%">
OUT;

                # Find largest value
                $max = max($visited);

                # Create horizontal bar chart type thing
                foreach ($visited as $site => $count) {

                    $rowWidth = round(($count / $max) * 100);

                    # Print it
                    echo <<<OUT
							<tr>
								<td width="200" align="right">{$site}</td>
								<td><div class="bar" style="width: {$rowWidth}%;">{$count}</div></td>
							</tr>
OUT;

                }

                # Table footer
                echo <<<OUT
							<tr>
								<td align="right"><i>Others</i></td>
								<td>{$others}</td>
							</tr>
						</table>
						<p class="align-center">&laquo; <a href="{$self}?logs">Back</a></p>
OUT;

                break;

            # Anything else - ignore
            default:

                $error->add('Missing input. No log view specified.');

        }

        break;




    default:

        # Send 404 status
        $output->sendStatus(404);

        # And print the error page
        $output->title = 'page not found';
        $output->bodyTitle = 'Page Not Found (404)';

        echo <<<OUT
			<p>The requested page <b>{$_SERVER['REQUEST_URI']}</b> was not found.</p>
OUT;

}



# Get buffer
$content = ob_get_contents();

# Clear buffer
ob_end_clean();

# Add content
$output->addContent($content);

# And print
$output->out();
