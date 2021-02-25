<?php
/*
Plugin Name: Email Notifier
Plugin URI: https://github.com/s22-tech/Yourls-email-notify/
Description: Send admin an email when someone clicks on the short URL that was sent to them.
Version: 1.5.5
Original: 2016-12-15
Date: 2021-02-25
Author: s22_tech

NOTES:
$code is the Short URL name used when you create the link.
*/


////////////////////////////////////////////
// USER CUSTOMIZABLE SETTINGS  /////////////
////////////////////////////////////////////

// If you want to keep a log, change these settings to your particular setup.
$user_name  = get_current_user();
define('S22_SERVER_IP', '');  // Your server's IP address.
define('S22_PATH', '/home/'.$user_name.'/projects');
define('S22_MY_IP_FILE', S22_PATH .'/data/files_to_watch/my_ip.txt');
define('S22_ERROR_LOG', S22_PATH.'/logs/yourls_errors.txt');
define('S22_LOG_ERRORS', 'no');

define('S22_EMAIL_SUBJECT', 'Yourls Click Notification');

// The correct date/time will be managed using the config time offset.
date_default_timezone_set('US/Pacific');

////////////////////////////////////////////


// No direct call.
if (!defined('YOURLS_ABSPATH')) die();

// Get values from database.
define('S22_ADMIN_EMAIL', yourls_get_option('admin_email') );
define('S22_EMAIL_TO',    yourls_get_option('email_to') );

// How to pass arguments
// https://github.com/YOURLS/YOURLS/issues/1349
// https://github.com/YOURLS/YOURLS/wiki/Plugins

yourls_add_action('pre_redirect', 's22_email_notification');
// This says: when YOURLS does action 'pre_redirect', call the function 's22_email_notification'.
// 'pre_redirect' happens *before* the redirect but *after* the click's been logged in the db.


function s22_email_notification($args) {
	s22_print_to_log(PHP_EOL);  // Start each section in the log with a blank line.
   $long_url = isset( $args[0] ) ? $args[0] : null;
   // $args[0] is the URL that I'm passing.  Example:
   // http://www.domain.com/store.cgi?c=info.htm&itemid=21246CP7&i_21246CP7=3&name=Joe_Blow&code=9260A

   $keywords = yourls_get_longurl_keywords($long_url);  // Produces a list of keywords (shorturls) that point to this long url.
   $code = $keywords[0] ?? 'mmm';  // This is the keyword from the shorturl.
   s22_print_to_log('my_ip_file: '. S22_MY_IP_FILE);

   $test_message = '';
   if (strpos(yourls_get_keyword_longurl($code), 'test') !== false) {
      $test_message = 'This was a test.';
   }

   s22_print_to_log('args    : '.implode(',', $args));
   s22_print_to_log('long_url: '.$long_url);
   s22_print_to_log('keywords: '.implode(',', $keywords));
   s22_print_to_log('code    : '.$code);

   $hostname = $first_name = $last_name = '';
	$name = 'Someone';  // default

   list($is_bot, $hostname) = s22_bot_check();

   if ($code !== 'mmm' || $is_bot !== 'yes') {  // mmm = no keyword from the shorturl was found.

		$query_parts =  '';
		if ($url_parts = parse_url($long_url)) {
			if (isset($url_parts['query'])) {
				$query_parts = explode('&', $url_parts['query']);
			}
		}

		clearstatcache();  // To clear file_exists from cache.
		if (file_exists(S22_MY_IP_FILE)) {
			$my_ip       = trim(file_get_contents(S22_MY_IP_FILE));
			$my_ip_c     = ip2long($my_ip);
			$remote_ip   = trim($_SERVER['REMOTE_ADDR']);
			$remote_ip_c = ip2long($remote_ip);
			$server_ip_c = ip2long(S22_SERVER_IP);
			// file_get_contents() reads entire file into a string.  MUST use the full path for the file.
			// ip2long allows the comparison of 2 IP addresses.
			if ($remote_ip_c === $my_ip_c) {
				// Use the pronoun "I" in the email when I'm the one who clicked the link.
				$name = 'I';
			}
		}
		else {
			$my_ip = 'x.x.x.x';
			$name  = 'The file "'. S22_MY_IP_FILE .'" could not be found.';
		}
		s22_print_to_log('my_ip: '.$my_ip);
	//    if ($remote_ip_c === $server_ip_c) return;

		$qs_count = 0;
		if ($query_parts) {
			s22_print_to_log('query_parts : '.implode(',', $query_parts));
			$elements = '<table cellpadding="0" cellspacinging="0">';
			foreach ($query_parts as $element) {
				$qs_count++;
				$tab = '&nbsp; &nbsp;';
				$key_value_pairs = explode('=', $element);
				$key   = urldecode($key_value_pairs[0]);
				$value = isset($key_value_pairs[1]) ? urldecode($key_value_pairs[1]) : '';
				$elements .= '<tr> <td>'. $tab .'</td> <td>'. $key. '</td> <td>&nbsp;=&nbsp;</td> <td>'. $value .'</td> </tr>'. PHP_EOL;
				if ($remote_ip_c !== $my_ip_c) {
					if ($key == 'name') {
						$name = $value;
					}
					else if ($key == 'first') {
						$name = $value;
					}
					else if ($key == 'last') {
						$name .= ' '. $value;
					}
				}
			}
			$elements .= '</table>'. PHP_EOL;
		}

		$name = str_replace( ['_', '-', '.'], ' ', $name);  // Remove underscores, dashes, and dots from $name.

	//    $long_url = preg_replace('/(.*)&name=.*$/', '$1', $long_url);  // Remove customer name from longurl.

		$host      = YOURLS_DB_HOST;  // These CONSTANTS are from '/user/config.php'
		$database  = YOURLS_DB_NAME;
		$username  = YOURLS_DB_USER;
		$password  = YOURLS_DB_PASS;
		$table_url = YOURLS_DB_TABLE_URL;  // These CONSTANTS are from '/includes/Config/Config.php'
		$table_log = YOURLS_DB_TABLE_LOG;

		$mysqli = new mysqli($host, $username, $password, $database, 3306);
		if ($mysqli->connect_errno) {
			$error = 'Failed to connect to MySQL: ('. $mysqli->connect_errno . ') '. $mysqli->connect_error;
			s22_print_to_log('error   : '.$error);
		}

		$statement_1 = $mysqli->query("SELECT * FROM `$table_url`
												 WHERE `keyword` = '$code'"
											  );

		if ($statement_1) {
			while ($result1 = $statement_1->fetch_object()) {
				$clicks     = $result1->clicks    ?? -9999;
				$title      = $result1->title     ?? 'NONE';
				$timestamp  = $result1->timestamp ?? '1111-22-33 11:22:33';
				$shorturl   = $result1->url;
				s22_print_to_log('shorturl:  ' . $shorturl);
				s22_print_to_log('clicks:    ' . $clicks);
				s22_print_to_log('title:     ' . $title);
				s22_print_to_log('timestamp: ' . $timestamp);

				$statement_1->close();
				$click_text = ngettext('time', 'times', $clicks);

				if ($clicks > 0) {
					$statement_2 = $mysqli->query("SELECT * FROM `$table_log`
															 WHERE `shorturl` = '$code'
															 ORDER BY `click_id` DESC
															 LIMIT 1"
															);

					if ($statement_2) {
						while ($result2 = $statement_2->fetch_object()) {
							$click_time = $result2->click_time;
							$ip_address = $result2->ip_address;
							$referer    = $result2->referrer;
							$keyword    = $result2->shorturl;
						}
					}
					$mysqli->close();
				}
				else {
					// Since I'm blocking my clicks from the db, these fields need to be manually populated for my visits.
					$click_time = date('Y-m-d h:i:s', time());
					$ip_address = $my_ip;
				}
				$ip_address = trim($ip_address);

				list($click_date, $click_time) = explode(' ', $click_time);
			//    $time_in_12_hour_format = date('g:i A', (strtotime($click_time) + YOURLS_HOURS_OFFSET * 3600) );

				$date_now = date('Y-m-d');  // These are needed because not every click is saved to the db.  Why?
				$time_now = date('g:i a');  // 12 hour time format.

				$date_created = explode(' ', $timestamp);
				$date_created = $date_created[0];

			// Create the email.
				$email_from    = S22_ADMIN_EMAIL;
				$email_subject = S22_EMAIL_SUBJECT;
				if (preg_match('/^aff/', $code)) {
					$email_subject = FilterCChars("Re: Yourls - Affiliate Link clicked for Customer # $code");
				}
				else {
					$email_subject = FilterCChars("Re: Yourls - Short Link clicked for Quote # $code");
				}


				// As of PHP 7.2, $headers can be an associative array.
				$headers['MIME-Version'] = '1.0';
				$headers['Content-type'] = 'text/html;charset=UTF-8';
				$headers['From'] = $email_from;

				if ($referer === 'direct') {
					$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'xxxx';
				}

				$email_body = <<<"HTML"
				<html>
				<head>
				<title> $email_subject </title>
				</head>
				<body>
				<h2> Yourls Email Notify </h2>
				$name viewed the "$title" link on $date_now @ $time_now.
				<br>
				<br>
				IP Address: <a href="https://whatismyipaddress.com/ip/$remote_ip">$remote_ip</a>
				<br>
				Hostname: $hostname
				<br>
				<br>
				This short URL has been clicked $clicks $click_text
				<br>
				and was created on $date_created.
				<br>
				<br>
				keyword = $keyword
				<br>
				shorturl = $shorturl
				<br>
				YOURLS_REFERER =  $referer
				<br>
				YOURLS_LOG_IP = $ip_address
				<br>
				<br>
				HTML;
				$email_body .= PHP_EOL;

				if ( preg_match('/^[0-9]{2}[a-m]{1}[0-9]{2}[a-z][0-9]{2}[a-z]$/i', $code) ) {
					// Test for quote numbers.  See Note 1.
					$email_body .= 'Open quote in FileMaker Pro: <br>'. PHP_EOL;
					$email_body .= 'fmp://$/Quotes.fmp12?script=Go_To_Quote_from_YOURLS_Link&$_quote='.$code.'<br><br>'. PHP_EOL;
				}

				if ($qs_count > 0) {
					$email_body .= 'Query items passed:<br>'. PHP_EOL;
					$email_body .= '<pre>'. PHP_EOL . $elements.'</pre>'. PHP_EOL;
					$email_body .= '(query items have been <b>urldecode</b>\'d.)<br><br><br>'. PHP_EOL;
				}

				$email_body .= 'The corresponding long URL is:<br>'.$long_url.'<br><br>'. PHP_EOL
				. $test_message. PHP_EOL
				. 'Last recorded click_time was '.$click_time. PHP_EOL
				. '</body>'. PHP_EOL
				. '</html>';

				s22_print_to_log( ' ' );  // Print a blank line.

				mail(S22_EMAIL_TO, $email_subject, $email_body, $headers);
			}
		}
   }
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
   $admin_email = S22_ADMIN_EMAIL;
   $email_to    = S22_EMAIL_TO;

   // Check if a form was submitted.
   if (isset($_POST['submit'])) {
      s22_update_email_notify_addresses('admin_email', $_POST['admin_email']);
      s22_update_email_notify_addresses('email_to',    $_POST['email_to']);
      yourls_redirect_javascript(yourls_site_url() .   $_SERVER['REQUEST_URI']);
   }

   echo <<<"HTML"
   <h2>Click Notification E-mail Addresses</h2>
   <p>Enter the email addresses for sending and receiving the &quot;click notifications&quot; when someone clicks on a short URL.</p>
   <form method="post">
      <p><label for="admin_email">From Address:</label> <input type="text" size="50" id="admin_email" name="admin_email" value="$admin_email" /></p>
      <p><label for="email_to">To Address:</label> <input type="text" size="50" id="email_to" name="email_to" value="$email_to" /></p>
      <p><input type="submit" name="submit" value="Add / Change" /></p>
   </form>
   From the <a href="https://github.com/s22-tech/Yourls-Email-Notify" target="blank">s22_tech</a> GitHub page.
HTML;
}

// Update option in database.
function s22_update_email_notify_addresses ($type, $email) {
   if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      // Validate test_option. ALWAYS validate and sanitize user input.
      echo 'Email is not valid';
   }
   else {
      // Update value in database.
      yourls_update_option($type, $email);
   }
}

function s22_print_to_log ($string_to_log) {
   if (S22_LOG_ERRORS === 'yes') {
      error_log( date('Y-m-d h:i:s', time()) .' -- '. $string_to_log ."\n", 3, S22_ERROR_LOG );
   }
}

function s22_bot_check () {
   $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']) ?: '';
   $is_bot = 'no';
   $bots_file = PATH . '/data/bots.txt';
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
