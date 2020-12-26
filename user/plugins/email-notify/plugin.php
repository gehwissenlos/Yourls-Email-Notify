<?php
/*
Plugin Name: Email Notifier
Plugin URI: https://github.com/s22-tech/Yourls-email-notify/
Description: Send admin an email when someone clicks on the short URL that was sent to them.
Version: 1.4.9
Original: 2016-12-15
Date: 2020-20-26
Author: s22_tech

NOTES:
$code is the Short URL name used when you create the link.
*/

////////////////////////////////////////////
// If you want to keep a log, customize
// these settings to your particular setup.
////////////////////////////////////////////
$user_name  = get_current_user();
$path       = '/home/'.$user_name.'/projects';
$my_ip_file = $path .'/data/files_to_watch/my_ip.txt';
$error_log  = $path.'/logs/yourls_errors.txt';
$log_errors = 'yes';

define('EMAIL_SUBJECT', 'Yourls Click Notification');

// Enforce UTC timezone to suppress PHP warnings -- correct date/time will be managed using the config time offset.
date_default_timezone_set('America/Los_Angeles');

// No direct call.
if (!defined('YOURLS_ABSPATH')) die();

// Get values from database.
define('ADMIN_EMAIL', yourls_get_option('admin_email') );
define('EMAIL_TO',    yourls_get_option('email_to') );

// How to pass arguments
// https://github.com/YOURLS/YOURLS/issues/1349
// https://github.com/YOURLS/YOURLS/wiki/Plugins

yourls_add_action('pre_redirect', 's22_email_notification');
// This says: when YOURLS does action 'pre_redirect', call the function 's22_email_notification'.
// 'pre_redirect' happens *before* the redirect but *after* the click's been logged in the db.


function s22_email_notification($args) {
   global $path, $my_ip_file;
   $code = 'xxx';
   $long_url = isset( $args[0] ) ? $args[0] : null;
   // $args[0] is the URL that I'm passing.  Example:
   // http://www.domain.com/store.cgi?c=info.htm&itemid=21246CP7&i_21246CP7=3&name=Joe_Blow&code=9260A

   $keywords = yourls_get_longurl_keywords($long_url);
   if ($keywords) {
      $code = $keywords[0];  // This is the keyword from the shorturl.
   }
   print_to_log( 'path: '.$path );
   print_to_log( 'code: '.$code );

   $test_message = '';
   if (strpos(yourls_get_keyword_longurl($code), 'test') !== false) {
      $test_message = 'This was a test.  My click was counted in the db.';
   }

   print_to_log( 'code: '.$code );
   print_to_log( 'keywords_array: '.implode(',', $keywords) );

   list($is_bot, $hostname) = s22_bot_check();
   // No keyword from the shorturl was found.
   if ($code === 'xxx' || $is_bot === 'yes') exit;

   $hostname = '';

//    yourls_debug_log('code: ' . $code);  // How is this supposed to work?

   if ($url_parts = parse_url($long_url, PHP_URL_QUERY)) {
      $query_parts = explode('&', $url_parts['query']);
   }

   $name = '';
   if (file_exists($my_ip_file)) {
      $my_ip     = ip2long(trim(file_get_contents($my_ip_file)));
      $remote_ip = ip2long(trim($_SERVER['REMOTE_ADDR']));
      // file_get_contents() reads entire file into a string.  MUST use the full path for the file.
      // ip2long allows the comparison of 2 IP addresses.
      if ($remote_ip === $my_ip) {
         // Use the pronoun "I" in the email when I'm the one who clicked the link.
         $name = 'I';
      }
      else {
         // else, use the generic "someone".
         $name = 'Someone';
      }
   }

   $qs_count = 0;
   if (isset($query_parts)) {
      $elements = '<table cellpadding="0" cellspacinging="0">';
      foreach ($query_parts as $element) {
         $qs_count++;
         $key_value = explode('=', $element);
         $resultarray[$key_value[0]] = $key_value[1];  // What does this line do?
         $key_value[1] = str_replace( array( '%20' ), '_', $key_value[1]);
         $elements .= '<tr> <td>&nbsp;&nbsp;&nbsp;&nbsp;'.$key_value[0].'</td> <td>= '.$key_value[1]."</td> </tr>\n";
         if ($remote_ip !== $my_ip && $key_value[0] == 'name') {
            $name = $key_value[1];
         }
      }
      $elements .= "</table>\n";
   }

   $name = str_replace(array('%20', '_', '-', '.'), ' ', $name);  // Remove underscores, dashes, and dots from $name.

   $long_url = preg_replace('/(.*)&name=.*$/', '$1', $long_url);  // Remove customer name from longurl.

   $host     = YOURLS_DB_HOST;  // These CONSTANTS are from /user/config.php
   $database = YOURLS_DB_NAME;
   $username = YOURLS_DB_USER;
   $password = YOURLS_DB_PASS;

   $mysqli = new mysqli($host, $username, $password, $database, 3306);
   if ($mysqli->connect_errno) {
      $error = 'Failed to connect to MySQL: ('. $mysqli->connect_errno . ') '. $mysqli->connect_error;
      error_log($error, 1, ADMIN_EMAIL);
   }

   $statement_1 = $mysqli->query("SELECT `clicks`,`title`,`timestamp`
                                  FROM `yourls_url`
                                  WHERE `keyword`='$code'"
                                );

   while ($result1 = $statement_1->fetch_object()) {
      $clicks     = $result1->clicks;
      $title      = $result1->title;
      $timestamp  = $result1->timestamp;
   }
   $statement_1->close();
   $click_text = ($clicks > 1) ? 'times': 'time';  // Used in the email.

   $statement_2 = $mysqli->query("SELECT `click_time`,`ip_address`
                                  FROM `yourls_log`
                                  WHERE `shorturl`='$code'
                                  ORDER BY `click_id` DESC
                                  LIMIT 1"
                                 );

   while ($result2 = $statement_2->fetch_object()) {
      $click_time = $result2->click_time;
      $ip_address = $result2->ip_address;
   }
   $statement_2->close();
   $mysqli->close();

   $date_time = explode(' ', $click_time);
   $date = $date_time[0];
   $time = $date_time[1];
   $time_in_12_hour_format = date('g:i A', (strtotime($time) + YOURLS_HOURS_OFFSET * 3600) );

   $date_now = date('Y-m-d');  // These are needed because not every click is saved to the db.  Why?
   $time_now = date('g:i a');

   $date_created = explode(' ', $timestamp);
   $date_created = $date_created[0];

   $email_from    = ADMIN_EMAIL;
   $email_subject = EMAIL_SUBJECT;
   if (preg_match('/^aff/', $code)) {
      $email_subject = FilterCChars("Re: Affiliate Link clicked for Customer # $code");
   }
   else {
      $email_subject = FilterCChars("Re: Short Link clicked for Quote # $code");
   }

   $email_header = 'From: '. $email_from . PHP_EOL
   . 'MIME-Version: 1.0'.PHP_EOL
   . 'Content-Type: text/html; charset=UTF-8'.PHP_EOL
   . "\n";

   $email_body = '<html>'.PHP_EOL
   . '<head>'.PHP_EOL
   . '<title>'.$email_subject.'</title>'.PHP_EOL
   . '</head>'.PHP_EOL
   . '<body>'.PHP_EOL
   . $name .' viewed the '.$title .' on '. $date_now .' @ '. $time_now .'.<br><br>'.PHP_EOL
   . 'IP Address: <a href="https://whatismyipaddress.com/ip/'. $ip_address .'">'. $ip_address .'</a><br><br>'.PHP_EOL
   . 'Hostname: ' . $hostname .'<br><br>'.PHP_EOL
   . 'This short URL has been clicked '. $clicks .' '. $click_text .'<br>'.PHP_EOL
   . 'and was created on '. $date_created .'.<br><br>'.PHP_EOL

   . 'YOURLS_REFERER =     '.@$_SERVER['HTTP_REFERER'].'<br>'.PHP_EOL
   . 'YOURLS_REMOTE_ADDR = '.@$_SERVER['REMOTE_ADDR'].'<br>'.PHP_EOL
   . '<br>'.PHP_EOL
   ;

   if ( preg_match('/^[0-9]{2}[a-m]{1}[0-9]{2}[a-z]{2}[0-9]{2}$/i', $code) ) {
      // Test for quote numbers.  See Note 1.
      $email_body .= 'fmp://$/Quotes.fmp12?script=Go_To_Quote_from_YOURLS_Link&$_quote='.$code.'<br><br>'.PHP_EOL;
   }

   if ($qs_count > 0) {
      $email_body .= 'Query items passed:<br><pre>'.$elements.'</pre><br><br>'.PHP_EOL.PHP_EOL;
   }

   $email_body .= 'The corresponding long URL is:<br>'.$long_url.'<br><br>'.PHP_EOL
   . $test_message.PHP_EOL
   . 'Last recorded click_time was '.$click_time.PHP_EOL
   . '</body>'.PHP_EOL
   . '</html>';

   print_to_log( ' ' );  // Print a blank line.

   mail(EMAIL_TO, $email_subject, $email_body, $email_header);
}

/////////////////
// FUNCTIONS ////
/////////////////

function FilterCChars ($the_string) {
   return preg_replace('/[\x00-\x1F]/', '', $the_string);
}

// Register our plugin admin page.
yourls_add_action( 'plugins_loaded', 's22_email_admin_page' );

function s22_email_admin_page () {
   yourls_register_plugin_page( 'email_notify', 'Click Notification Email Addresses', 's22_email_admin_do_page' );
   // Parameters: page slug, page title, and function that will display the page itself.
}

// Display admin page.
function s22_email_admin_do_page () {
   // Check if a form was submitted.
   if (isset($_POST['admin_email'])) s22_update_email_notify_address();

   $admin_email = ADMIN_EMAIL;
   $email_to    = EMAIL_TO;

   echo <<<"HTML"
   <h2>Click Notification E-mail Addresses</h2>
   <p>Enter the email addresses for sending and receiving the &quot;click notifications&quot; when someone clicks a short URL.</p>
   <form method="post">
      <p><label for="admin_email">From Address:</label> <input type="text" size="50" id="admin_email" name="admin_email" value="$admin_email" /></p>
      <p><label for="email_to">To Address:</label> <input type="text" size="50" id="email_to" name="email_to" value="$email_to" /></p>
      <p><input type="submit" value="Add / Change" /></p>
   </form>
   From <a href="https://github.com/s22-tech/Yourls-Email-Notify">s22_tech</a>
HTML;
}

// Update option in database.
function s22_update_email_notify_address () {
   $email = $_POST['admin_email'];

   if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      // Validate test_option. ALWAYS validate and sanitize user input.
      echo 'Email is not valid';
   }
   else {
      // Update value in database.
      yourls_update_option( 'admin_email', $email );
   }
}

function print_to_log ($string_to_log) {
   global $log_errors, $error_log;
   if ($log_errors === 'yes') {
      error_log( date('Y-m-d h:i:s', time()) .' -- '. $string_to_log ."\n", 3, $error_log );
   }
}

function s22_bot_check () {
   global $path;
   $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']) ?: '';
   $is_bot = 'no';
   $bots_file = $path . '/data/bots.txt';
   if (file_exists($bots_file)) {
      $rows = file( $bots_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

      foreach ($rows as $key) {
         if (stripos($hostname, $key) !== false) {
            $is_bot = 'yes';
            break;
         }
      }
   }
   return array($is_bot, $hostname);
}

__halt_compiler();

1] Using a '$' in the protocol fmp://$/Quotes.fmp12 will open the file on the local machine - no IP address necessary, so this can be used on any Mac.  However, FMP db must already be open for the link to work.


