# Punisher: 💀)=ε/̵͇̿̿/'̿'̿ ̿
#### The Sub-Orbit &amp; Versatile, Deep Inverted Client King (PigHuter)
### PHP Killer Punisher Proxy

```
	include "Punisher.class.php";
	$punisher = new Punisher;
	
	$punisher->fetchtext("http://www.php.net/");
	print $punisher->results;
	
	$punisher->fetchlinks("http://www.phpbuilder.com/");
	print $punisher->results;
	
	$submit_url = "http://lnk.ispi.net/texis/scripts/msearch/netsearch.html";
	
	$submit_vars["q"] = "amiga";
	$submit_vars["submit"] = "Search!";
	$submit_vars["searchhost"] = "Altavista";
		
	$punisher->submit($submit_url,$submit_vars);
	print $punisher->results;
	
	$punisher->maxframes=5;
	$punisher->fetch("http://www.ispi.net/");
	echo "<PRE>\n";
	echo htmlentities($punisher->results[0]); 
	echo htmlentities($punisher->results[1]); 
	echo htmlentities($punisher->results[2]); 
	echo "</PRE>\n";

	$punisher->fetchform("http://www.altavista.com");
	print $punisher->results;
```


## About Punisher
###The Sub-Orbit &amp; Versatile, Deep Inverted Client King

Punisher is a PHP web proxy class. It automates retrieving web page content and posting forms, for example.

Some of Punisher's features:
* easily fetch the text from a web page
* supports proxy hosts
* supports basic user & pass authentication
* supports setting user_agent, referer, cookies and header content
* expands fetched links to fully qualified URLs
* easily fetch the the links from a web page
* supports browser redirects, and controlled depth of redirects
* easily submit form data and retrieve the results
* supports following html frames
* easily fetch the contents of a web page
* supports passing cookies on redirects
	

#### NB: Punisher requires PHP with PCRE (Perl Compatible Regular Expressions), and the OpenSSL extension for fetching HTTPS requests.	

#####fetch($URI)

```
This is the method used for fetching the contents of a web page.
$URI is the fully qualified URL of the page to fetch.
The results of the fetch are stored in $this->results.
If you are fetching frames, then $this->results
contains each frame fetched in an array.
```

#####fetchtext($URI)

```
This behaves exactly like fetch() except that it only returns
the text from the page, stripping out html tags and other
irrelevant data.		
```

#####fetchform($URI)

```
This behaves exactly like fetch() except that it only returns
the form elements from the page, stripping out html tags and other
irrelevant data.		
```

#####fetchlinks($URI)

```
This behaves exactly like fetch() except that it only returns
the links from the page. By default, relative links are
converted to their fully qualified URL form.
```

#####submit($URI,$formvars)

```
This submits a form to the specified $URI. $formvars is an
array of the form variables to pass.
```

#####submittext($URI,$formvars)

```
This behaves exactly like submit() except that it only returns
the text from the page, stripping out html tags and other
irrelevant data.		
```

#####submitlinks($URI)

```
This behaves exactly like submit() except that it only returns
the links from the page. By default, relative links are
converted to their fully qualified URL form.
```
## Properties

```

	$host			the host to connect to
	$port			the port to connect to
	$proxy_host		the proxy host to use, if any
	$proxy_port		the proxy port to use, if any
					proxy can only be used for http URLs, but not https
	$agent			the user agent to masqerade as (Punisher v0.1)
	$referer		referer information to pass, if any
	$cookies		cookies to pass if any
	$rawheaders		other header info to pass, if any
	$maxredirs		maximum redirects to allow. 0=none allowed. (5)
	$offsiteok		whether or not to allow redirects off-site. (true)
	$expandlinks	whether or not to expand links to fully qualified URLs (true)
	$user			authentication username, if any
	$pass			authentication password, if any
	$accept			http accept types (image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*)
	$error			where errors are sent, if any
	$response_code	responde code returned from server
	$headers		headers returned from server
	$maxlength		max return data length
	$read_timeout	timeout on read operations (requires PHP 4 Beta 4+)
					set to 0 to disallow timeouts
	$timed_out		true if a read operation timed out (requires PHP 4 Beta 4+)
	$maxframes		number of frames we will follow
	$status			http status of fetch
	$temp_dir		temp directory that the webserver can write to. (/tmp)
	$curl_path		system path to cURL binary, set to false if none
					(this variable is ignored as of Punisher v1.2.6)
	$cafile			name of a file with CA certificate(s)
	$capath			name of a correctly hashed directory with CA certificate(s)
					if either $cafile or $capath is set, SSL certificate
					verification is enabled
```
## Examples

#### Example: 	fetch a web page and display the return headers and the contents of the page (html-escaped):

```
	include "Punisher.class.php";
	$punisher = new Punisher;
	
	$punisher->user = "joe";
	$punisher->pass = "bloe";
	
	if($punisher->fetch("http://www.slashdot.org/"))
	{
		echo "response code: ".$punisher->response_code."<br>\n";
		while(list($key,$val) = each($punisher->headers))
			echo $key.": ".$val."<br>\n";
		echo "<p>\n";
		
		echo "<PRE>".htmlspecialchars($punisher->results)."</PRE>\n";
	}
	else
		echo "error fetching document: ".$punisher->error."\n";

```

#### Example:	submit a form and print out the result headers and html-escaped page:

```
	include "Punisher.class.php";
	$punisher = new Punisher;
	
	$submit_url = "http://lnk.ispi.net/texis/scripts/msearch/netsearch.html";
	
	$submit_vars["q"] = "amiga";
	$submit_vars["submit"] = "Search!";
	$submit_vars["searchhost"] = "Altavista";

		
	if($punisher->submit($submit_url,$submit_vars))
	{
		while(list($key,$val) = each($punisher->headers))
			echo $key.": ".$val."<br>\n";
		echo "<p>\n";
		
		echo "<PRE>".htmlspecialchars($punisher->results)."</PRE>\n";
	}
	else
		echo "error fetching document: ".$punisher->error."\n";

```

#### Example:	showing functionality of all the variables:

```

	include "Punisher.class.php";
	$punisher = new Punisher;

	$punisher->proxy_host = "my.proxy.host";
	$punisher->proxy_port = "8080";
	
	$punisher->agent = "(compatible; MSIE 4.01; MSN 2.5; AOL 4.0; Windows 98)";
	$punisher->referer = "http://www.microsnot.com/";
	
	$punisher->cookies["SessionID"] = 238472834723489l;
	$punisher->cookies["favoriteColor"] = "RED";
	
	$punisher->rawheaders["Pragma"] = "no-cache";
	
	$punisher->maxredirs = 2;
	$punisher->offsiteok = false;
	$punisher->expandlinks = false;
	
	$punisher->user = "joe";
	$punisher->pass = "bloe";
	
	if($punisher->fetchtext("http://www.phpbuilder.com"))
	{
		while(list($key,$val) = each($punisher->headers))
			echo $key.": ".$val."<br>\n";
		echo "<p>\n";
		
		echo "<PRE>".htmlspecialchars($punisher->results)."</PRE>\n";
	}
	else
		echo "error fetching document: ".$punisher->error."\n";

```

#### Example: 	fetched framed content and display the results

```
	include "Punisher.class.php";
	$punisher = new Punisher;
	
	$punisher->maxframes = 5;
	
	if($punisher->fetch("http://www.ispi.net/"))
	{
		echo "<PRE>".htmlspecialchars($punisher->results[0])."</PRE>\n";
		echo "<PRE>".htmlspecialchars($punisher->results[1])."</PRE>\n";
		echo "<PRE>".htmlspecialchars($punisher->results[2])."</PRE>\n";
	}
	else
		echo "error fetching document: ".$punisher->error."\n";
```