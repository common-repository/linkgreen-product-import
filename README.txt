=== LinkGreen Product Import ===
Contributors: LinkGreen
Tags: linkgreen, link green, API, WooCommerce, wholesale
Requires at least: 3.0.1
Requires PHP: 5.6
Tested up to: 5.2.3
Stable tag: 1.0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The official LinkGreen plugin. Allows sellers on the LinkGreen platform to link their products with WooCommerce (and other extra things!)

== Description ==

Users of the LinkGreen platform can now export their product catalogs into their WooCommerce product catalogs, complete with
images, pricing, and categories.

This plugin also allows for Google Maps integration on your site to show where your distributors are located.

== Installation ==

There are a few options for installing and setting up this plugin.

= Upload Manually =

1. Download and unzip the plugin
2. Upload the 'linkgreen-product-import' folder into the '/wp-content/plugins/' directory
3. Go to the Plugins admin page and activate the plugin

= Install Via Admin Area =

1. In the admin area go to Plugins > Add New and search for "LinkGreen Product Import"
2. Click install and then click activate

= To Setup The Plugin =

1. Find your LinkGreen API token (see instructions under FAQ).
2. In the WordPress admin area go to Tools > LinkGreen Product Import Admin and then paste your API token

== Frequently Asked Questions ==

= How do I find my LinkGreen API Key? =

1. Login to your LinkGreen account.
2. Find your user profile
3. Select the "API Key" tab.

= How do I integrate Google Maps? =

1. Obtain a Google Maps API key
1. Navigate to Tools > LinkGreen Product Import
1. Paste the Google Maps token into the correct box

== Changelog ==

= 1.0.8 =
* Support for multiple categories from the LinkGreen WooCommerce plugin for product pushing
* LinkGreen fulfillment support to automatically create orders in LinkGreen for drop-ship products
* Display in cart when items will be drop-shipped
* Display in order details when item(s) will be drop-shipped
* WooCommerce orders list filtering by LinkGreen fulfillment

= 1.0.7 =
* Fix broken admin page options
* Add multiple source image support for imported products

= 1.0.6 =
* Repository cleanup

= 1.0.5 =
* Addition of direct product link for single products (push from LinkGreen to WooCommerce)
* Added to the WordPress plugin store

= 1.0.0 =
* Nuke and pave products in WooCommerce with those from your LinkGreen catalog
* Google Maps integration

== Upgrade Notice ==

= 1.0.5 =
To support direct link for single products

= 1.0.7 =
To fix admin page issues