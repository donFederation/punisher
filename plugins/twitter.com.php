<?php


$options['stripJS'] = true;

#function preRequest() {
#	global $toSet,$URL;
#	header('Content-Type: text/plain');
#	if ($URL['host'] != 'mobile.twitter.com') {
#		$URL['host'] = 'mobile.twitter.com';
#		$URL['href'] = preg_replace('#^[a-z]+://[^/]+#i', 'https://mobile.twitter.com', $URL['href']);
#	}
#}
