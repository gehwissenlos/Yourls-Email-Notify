# Yourls-Email-Notify
Have Yourls send you an email when someone clicks a shortened link.

This plugin is very easy to configure.  Simply add your To & From email addresses to the "Click Notification Email Addresses" admin panel and you're good to go!

There are a few optional settings in the script that you can customize to fit your situation.  They are:
* $path - this is the base path where you want to install the following files.
* $my_ip_file - this file contains your IP address.  It's used to compare to the visitors IP so you can see which clicks were made by you.
* $error_log - name and path of an error log, in case you have a problem with this script.

Plus you can customize the generated email to your heart's content!

### Installation Instructions

1. Copy everything to your YOURLS_DIRECTORY
2. Visit the "Click Notification Email Addresses" admin panel to add your email addresses

#### Caveats

* This plugin is really only useful for sites that have a low volume of clicks and you need to know when the recipients click on them.  Example: say you have an e-commerce site and you need to send links to your customers for quotes or other important information.  This plugin allows you to know when/if your customer clicks on that link so you can take the appropriate action.
