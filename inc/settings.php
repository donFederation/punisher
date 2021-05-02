<?php


$CONFIG['license_key'] = '';


$CONFIG['enable_blockscript'] = false;


$CONFIG['asset'] = 'default';

$CONFIG['plugins'] = 'facebook.com,google.com,hotmail.com,live.com,msn.com,myspace.com,twitter.com,yahoo.com,youtube.com,ytimg.com';

$CONFIG['tmp_dir'] = PUNISH_ROOT . '/tmp/';


$CONFIG['gzip_return'] = false;


$CONFIG['ssl_warning'] = true;




$CONFIG['override_javascript'] = false;



$CONFIG['load_limit'] = 0;


$CONFIG['footer_include'] = '';



$CONFIG['path_info_urls'] = false;



$CONFIG['unique_urls'] = false;

$CONFIG['stop_hotlinking'] = true;


$CONFIG['hotlink_domains'] = array();


$CONFIG['enable_logging'] = false;




$CONFIG['logging_destination'] = $CONFIG['tmp_dir'] . 'logs/';



$CONFIG['log_all'] = false;

$CONFIG['whitelist'] = array();

$CONFIG['blacklist'] = array();

$CONFIG['ip_bans'] = array();



$CONFIG['connection_timeout'] = 5;


$CONFIG['transfer_timeout'] = 0;


$CONFIG['max_filesize'] = 0;



$CONFIG['download_speed_limit'] = 0;




$CONFIG['resume_transfers'] = false;


$CONFIG['queue_transfers'] = true;

$CONFIG['cookies_on_server'] = false;



$CONFIG['cookies_folder'] = $CONFIG['tmp_dir'] . 'cookies/';




$CONFIG['encode_cookies'] = false;

$CONFIG['tmp_cleanup_interval'] = 0;


$CONFIG['tmp_cleanup_logs'] = 0;


$CONFIG['options']['encodeURL'] = array(
    'title' => 'Encrypt URL',
    'desc' => 'Encrypts the URL of the page you are viewing so that it does not contain the target site in plaintext.',
    'default' => true,
    'force' => false
);

$CONFIG['options']['encodePage'] = array(
    'title' => 'Encrypt Page',
    'desc' => 'Helps avoid filters by encrypting the page before sending it and decrypting it with javascript once received.',
    'default' => false,
    'force' => false
);

$CONFIG['options']['showForm'] = array(
    'title' => 'Show Form',
    'desc' => 'This provides a mini form at the top of each page to allow you to quickly jump to another site without returning to our homepage.',
    'default' => true,
    'force' => true
);

$CONFIG['options']['allowCookies'] = array(
    'title' => 'Allow Cookies',
    'desc' => 'Cookies may be required on interactive websites (especially where you need to log in) but advertisers also use cookies to track your browsing habits.',
    'default' => true,
    'force' => false
);

$CONFIG['options']['tempCookies'] = array(
    'title' => 'Force Temporary Cookies',
    'desc' => 'This option overrides the expiry date for all cookies and sets it to at the end of the session only - all cookies will be deleted when you shut your browser. (Recommended)',
    'default' => true,
    'force' => true
);

$CONFIG['options']['stripTitle'] = array(
    'title' => 'Remove Page Titles',
    'desc' => 'Removes titles from proxied pages.',
    'default' => false,
    'force' => true
);

$CONFIG['options']['stripJS'] = array(
    'title' => 'Remove Scripts',
    'desc' => 'Remove scripts to protect your anonymity and speed up page loads. However, not all sites will provide an HTML-only alternative. (Recommended)',
    'default' => true,
    'force' => false
);

$CONFIG['options']['stripObjects'] = array(
    'title' => 'Remove Objects',
    'desc' => 'You can increase page load times by removing unnecessary Flash, Java and other objects. If not removed, these may also compromise your anonymity.',
    'default' => true,
    'force' => false
);


$CONFIG['version'] = '1.4.15';


