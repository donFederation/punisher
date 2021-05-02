<?php


$CONFIG['license_key'] = '';


$CONFIG['enable_blockscript'] = false;


$CONFIG['theme'] = 'default';

$CONFIG['plugins'] = 'facebook.com,google.com,hotmail.com,live.com,msn.com,myspace.com,twitter.com,yahoo.com,youtube.com,ytimg.com';



$CONFIG['tmp_dir'] = PUNISH_ROOT . '/tmp/';


$CONFIG['gzip_return'] = false;


$CONFIG['ssl_warning'] = true;




$CONFIG['override_javascript'] = false;



$CONFIG['load_limit'] = 0;


$CONFIG['footer_include'] = '';



$CONFIG['path_info_urls'] = false;



$CONFIG['unique_urls'] = false;

# page and forces all users to first visit the index page.
$CONFIG['stop_hotlinking'] = true;

# If the above option is enabled, you can add individual referrers
# that are allowed to bypass the hotlinking protection.
$CONFIG['hotlink_domains'] = array();


# Enable/disable the logging feature. If disabled, skip the rest of
# this section.
$CONFIG['enable_logging'] = false;

# Enter a destination for log files. A new log file will be created
# each day in the directory specified. The directory must be
# writable. To protect against unauthorised access, place the log
# folder above your webroot.
$CONFIG['logging_destination'] = $CONFIG['tmp_dir'] . 'logs/';

# You can avoid huge log files by only logging requests for .html
# pages, as per the default setting. If you want to log all
# requests (images, etc.) as well, enable this.
$CONFIG['log_all'] = false;


# Block everything except these websites
$CONFIG['whitelist'] = array();

# Block these websites
$CONFIG['blacklist'] = array();


#
$CONFIG['ip_bans'] = array();


# Time to wait for while establishing a connection to the target
# server. If the connection takes longer, the transfer will be
# aborted.
$CONFIG['connection_timeout'] = 5;

# Time to allow for the entire transfer. You will need a longer
# time limit to download larger files.
$CONFIG['transfer_timeout'] = 0;

# Preserve bandwidth by limiting the size of files that can be
# downloaded through your proxy.
$CONFIG['max_filesize'] = 0;

# Preserve bandwidth by limiting the speed at which files are
# downloaded through your proxy. Note: if limiting download speed,
# you may need to increase the transfer timeout to compensate.
$CONFIG['download_speed_limit'] = 0;

# This forwards any requested ranges from the client and this makes
# it possible to resume previous downloads. Depending on the "Queue
# transfers" option below, it may also allow users to download
# multiple segments of a file simultaneously.
$CONFIG['resume_transfers'] = false;

# You can limit use of your proxy to allow only one transfer at a
# time per user. Disable this for faster browsing.
$CONFIG['queue_transfers'] = true;


# If enabled, cookies will be stored in the folder specified below.
$CONFIG['cookies_on_server'] = false;

# If storing cookies on the server, specify a folder to save the
# cookie data in. To protect against unauthorised access, place the
# cookie folder above your webroot.
$CONFIG['cookies_folder'] = $CONFIG['tmp_dir'] . 'cookies/';

# You can encode cookie names, domains and values with this option
# for optimum privacy but at the cost of increased server load and
# larger cookie sizes. This option has no effect if storing cookies
# on server.
$CONFIG['encode_cookies'] = false;


# How often to clear the temporary files created by the script?
$CONFIG['tmp_cleanup_interval'] = 0;

# When should old log files be deleted? This option has no effect
# if the above option is disabled.
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


# Settings file version for determining compatability with admin
# tool.
$CONFIG['version'] = '1.4.15';

//---PRESERVE ME---
// Anything below this line will be preserved when the admin control panel rewrites
// the settings. Useful for storing settings that don't/can't be changed from the control panel
