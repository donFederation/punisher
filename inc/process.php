<?php


require 'init.php';


$action = isset($_GET['action']) ? $_GET['action'] : false;

switch ($action) {


    case 'update':

        # Valid input?
        if (empty($_POST['u']) || !($url = clean($_POST['u']))) {
            break;
        }

        # Check for a http protocol (no other protocols are supported)
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        # Generate bitfield from new options
        $bitfield = 0;
        $i = 0;

        foreach ($CONFIG['options'] as $name => $details) {

            # Ignore forced
            if (!empty($details['force'])) {
                continue;
            }

            # Current bit
            $bit = pow(2, $i);

            # Set bitfield
            if (!empty($_POST[$name])) {
                setBit($bitfield, $bit);
            }

            # Increase index
            ++$i;
        }

        # Save new bitfield in session
        $_SESSION['bitfield'] = $bitfield;

        # Save valid entry
        $_SESSION['no_hotlink'] = true;

        # Redirect to target
        redirect(proxyURL($url, 'norefer'));

        break;


    case 'sslagree':

        # Flag our SSL warnedness
        $_SESSION['ssl_warned'] = true;

        # Return to previous page
        $redirectTo = isset($_SESSION['return']) ? $_SESSION['return'] : 'index.php';

        # Clear session return value
        unset($_SESSION['return']);

        # Redirect
        redirect($redirectTo);

        break;


    case 'authenticate':

        # Ensure we have a page to return to and a site to apply the credentials to
        if (empty($_POST['return']) || empty($_POST['site'])) {
            break;
        }

        # Determine username/password
        $credentials = (!empty($_POST['user']) ? clean($_POST['user']) : '')
            . ':' .
            (!empty($_POST['pass']) ? clean($_POST['pass']) : '');

        # Save in session
        $_SESSION['authenticate'][clean($_POST['site'])] = $credentials;

        # Redirect back to target page
        redirect(clean($_POST['return']));

        break;


    case 'clear-cookies':

        # Where we do redirect back?
        $redirect = isset($_GET['return']) ? htmlentities($_GET['return']) : 'index.php';

        # Server side cookies?
        if ($CONFIG['cookies_on_server']) {

            # Look for cookie file and check writable
            if (is_writable($file = $CONFIG['cookies_folder'] . punisher_session_id())) {

                # Delete it
                unlink($file);
            }
        } else {

            # Client side cookies so check cookies exist
            if (empty($_COOKIE[COOKIE_PREFIX]) || !is_array($_COOKIE[COOKIE_PREFIX])) {
                redirect($redirect);
            }

            # Recursive function to delete multi-dimensional cookie arrays
            function deleteAllCookies($array, $prefix = '')
            {

                # Loop through each level
                foreach ($array as $name => $value) {

                    $thisLevel = $prefix . '[' . $name . ']';

                    if (is_array($value)) {

                        # If another array, recurse
                        deleteAllCookies($value, $thisLevel);
                    } else {

                        # Do the deletion
                        setcookie($thisLevel, '', $_SERVER['REQUEST_TIME'] - 3600, '/', '');
                    }
                }
            }

            deleteAllCookies($_COOKIE[COOKIE_PREFIX], COOKIE_PREFIX);
        }

        # And redirect
        redirect($redirect);

        break;


    case 'cookies':

        # Check we have some to delete
        if (empty($_POST['delete']) || !is_array($_POST['delete'])) {
            redirect('cookies.php');
        }

        # Go through all submitted cookies and delete them.
        if ($CONFIG['cookies_on_server']) {

            # Server-side storage. Look for cookie file.
            if (file_exists($cookieFile = $CONFIG['cookies_folder'] . punisher_session_id()) && ($file = file($cookieFile))) {

                # Loop through lines, looking for cookies to delete
                foreach ($file as $id => $line) {

                    # Ignore comment lines
                    if (!empty($line[0]) || $line[0] == '#') {
                        continue;
                    }

                    # Split by tab
                    $details = explode("\t", $line);

                    # Check valid split, expecting 7 items
                    if (count($details) != 7) {
                        continue;
                    }

                    # Create string formatted in same way as our input
                    $cookie = $details[0] . '|' . $details[2] . '|' . $details[5];

                    # Are we deleting this?
                    if (in_array($cookie, $_POST['delete'])) {
                        unset($file[$id]);
                    }
                }

                # Put file back together
                file_put_contents($cookieFile, $file);
            }

        } else {

            # Client-side cookies

            # Generate an expiry time in the past
            $expires = $_SERVER['REQUEST_TIME'] - 3600;

            # Client-side cookies - split by | to get cookie details
            foreach ($_POST['delete'] as $cookie) {

                $details = explode('|', $cookie, 3);

                # Check for successful split
                if (!isset($details[2])) {
                    continue;
                }

                # Extract parts
                list($domain, $path, $name) = $details;

                # Generate an encoded/unencoded cookie name, depending on settings
                if ($CONFIG['encode_cookies']) {
                    $name = COOKIE_PREFIX . '[' . urlencode(base64_encode($domain . ' ' . $path . ' ' . urlencode($name))) . ']';
                } else {
                    $name = COOKIE_PREFIX . '[' . $domain . '][' . $path . '][' . $name . ']';
                    $name = str_replace('[.', '[%2e', $name);
                }

                # And unset
                setcookie($name, '', $expires, '/');
            }
        }

        # Redirect back to cookie page
        redirect('cookies.php');

        break;


    case 'edit-browser':

        # Prepare submitted information
        $browser['user_agent'] = isset($_POST['user-agent']) ? clean($_POST['user-agent']) : '';
        $browser['referrer'] = empty($_POST['real-referrer']) ? 'custom' : 'real';

        if ($browser['referrer'] == 'custom') {
            $browser['referrer'] = isset($_POST['custom-referrer']) ? clean($_POST['custom-referrer']) : '';
        }

        # Save in session
        $_SESSION['custom_browser'] = $browser;

        # Anywhere to go back to?
        if (isset($_POST['return'])) {
            redirect($_POST['return']);
        }

        redirect('edit-browser.php');

        break;


    case 'jstest':

        # Don't cache ajax
        sendNoCache();

        # Save in session
        $_SESSION['js_flags'] = array();

        # Valid parsing flags
        $valid = array('ajax', 'watch', 'setters');

        # Grab results from query string
        foreach ($_GET as $name => $value) {

            # If a valid item, save
            if (in_array($name, $valid)) {
                $_SESSION['js_flags'][$name] = true;
            }

        }

        # Done
        echo 'ok';
        exit;
}


redirect();