=== Error Manager  ===
Contributors: DLS Studios
Tags: error, handler, logging, alert
Requires at least: 3.3
Tested up to: 4.1
Stable tag: 1.0
License: GPLv2 or later

Log and send alerts for various errors as they happen throughout your WordPress site.


== Description ==

Keeping your site error free just got easier.  Get email alerts of PHP errors and 404's on your site.  The Error Manager plugin allows you to get better insights as to how your site is performing by sending you more visible alerts to the errors that are happening on your site.

= PHP Errors Options =
* Instant email alerts on PHP Fatal Errors
* Scheduled email alerts on all PHP Errors in your WordPress error log (hourly, twice daily, or daily)
* Log PHP Fatal Errors to your database

= 404 Errors Options =
* Instant email alerts on 404 errors
* Log 404 errors to your database

For additional features you can also check out our [Error Manager Pro](https://www.dlssoftwarestudios.com/downloads/error-manager-wordpress-plugin/) version.


== Installation ==

1. Download the plugin and extract the files
2. Copy the `error-manager` directory and all its files to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Make sure debugging is turned on in your wp-config.php file (see FAQ for more info)


== Frequently Asked Questions ==

= What version of PHP does this plugin work with? =

This plugin requires at least PHP version 5.2 or higher

= How do I turn on debugging in WordPress? =

The simplest way is to add the line `define('WP_DEBUG', true);` in to your wp-config.php file.  However, for production sites you will want to make sure that errors are not displayed to the public on your frontend.  We recommend the following settings which will turn on debugging to you log file only...

`
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
`

For additional information on these settings, read [Debugging in WordPress](http://codex.wordpress.org/Debugging_in_WordPress).

= How can I suggest an idea for the plugin? =

Please post your suggestion on your [Error Manager Suggestions Forum](https://www.dlssoftwarestudios.com/forums/forum/wordpress/error-manager/suggestions/). We appreciate any and all feedback, but can't guarantee it will make it into the next version.  If you are in need a modification immediately, we are available for hire.  Please send the details of the feature you would like to have through our [Request a Quote](https://www.dlssoftwarestudios.com/contact-us/) form and we can provide a quote.


== Screenshots ==

1. Settings Page


== Upgrade Notice ==

= 1.0 =
* Initial public version


== Changelog ==

= 1.0 =
* Initial public version