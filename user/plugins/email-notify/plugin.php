<?php
/*
Plugin Name: Email Notifier
Plugin URI: https://github.com/gehwissenlos/Yourls-Email-Notify/
Description: Send admin an email when someone adds a short URL
Version: 1.0.0
Author: gehwissenlos

*/


////////////////////////////////////////////
// There are NO user configureable options in this file.
// Set them on the "Click Notification Email" admin page (located under "Manage Plugins").
////////////////////////////////////////////

// No direct call.
if (!defined('YOURLS_ABSPATH')) die();

define('GEHWISSENLOS_SERVER_IP', $_SERVER['SERVER_ADDR']);

// Get these values from the `yourls_options` table.
define('GEHWISSENLOS_EMAIL_FROM', yourls_get_option('email_from') );
define('GEHWISSENLOS_EMAIL_TO',   yourls_get_option('email_to') );

// How to pass arguments
// https://github.com/YOURLS/YOURLS/issues/1349
// https://github.com/YOURLS/YOURLS/wiki/Plugins

yourls_add_action('post_add_new_link', 'gehwissenlos_email_notification');

function gehwissenlos_email_notification($args) {
	
	// get the info we want to send per mail
	//$techInfo = $args[3];
	$url = $args[0];
	$keyword = $args[1];
	$shortURL = YOURLS_SITE . "/" . $keyword;
	$shortSite = substr(YOURLS_SITE, strpos(YOURLS_SITE, "://") + 3);
	$title = empty($args[2]) ? $shortURL : $args[2];
	
	$email_subject = 'New shortened url from ' . $shortSite;
	// As of PHP 7.2, $headers can be an associative array.
	$headers['MIME-Version'] = '1.0';
	$headers['Content-type'] = 'text/html;charset=UTF-8';
	$headers['FROM'] = GEHWISSENLOS_EMAIL_FROM;
	
	$email_body = 'The url ' . $url . ' was shortened to ' . $shortURL;
	
	mail(GEHWISSENLOS_EMAIL_TO, $email_subject, $email_body, $headers);
	
}

// Register our plugin admin page.
yourls_add_action( 'plugins_loaded', 'gehwissenlos_email_admin_page' );

function gehwissenlos_email_admin_page () {
   yourls_register_plugin_page( 'email_notify', 'Notification Email', 'gehwissenlos_email_admin_do_page' );
   // Parameters: page slug, page title, and function that will display the page itself.
}

// Display admin page.
function gehwissenlos_email_admin_do_page () {
   $email_from = GEHWISSENLOS_EMAIL_FROM;
   $email_to   = GEHWISSENLOS_EMAIL_TO;

   // Check if a form was submitted.
   if (isset($_POST['submit'])) {
      gehwissenlos_update_email_notify_addresses('email_from', $_POST['email_from']);
      gehwissenlos_update_email_notify_addresses('email_to',   $_POST['email_to']);
      yourls_redirect_javascript(yourls_site_url() .  $_SERVER['REQUEST_URI']);
   }
   echo <<<"HTML"
   <style>
   .container {
	  width: 400px;
	  clear: both;
	}

	.container input {
	  width: 100%;
	  clear: both;
	}
	form label {font-weight:bold}
	</style>
   <h2>Click Notification E-mail Addresses</h2>
   <p>Enter the email addresses for sending and receiving the &quot;click notifications&quot; when someone clicks on a short URL.</p>
   <form method="post">
   <div class="container">
      <p><label for="email_to">To Email Address:</label> <input type="text" size="50" id="email_to" name="email_to" value="$email_to" /></p>
      <p><label for="email_from">From Email Address:</label> <input type="text" size="50" id="email_from" name="email_from" value="$email_from" /></p>
   </div>
      <p><input type="submit" name="submit" value="Add / Change" /></p>
   </form>
HTML;
}

// Update option in database.
function gehwissenlos_update_email_notify_addresses ($type, $email) {
   if (gehwissenlos_str_contains($email, '@') && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      // Validate test_option. ALWAYS validate and sanitize user input.
      echo 'Email is not valid';
   }
   else {
      // Update value in database.
      yourls_update_option($type, $email);
   }
}

function gehwissenlos_str_contains ($string, $word, $case='') {
	if ($case === 'i') {
		// Case insensitive.
		if (stripos($string, $word) !== false) return true;
	}
	else {
		if (strpos($string, $word) !== false) return true;
	}
}

?>
