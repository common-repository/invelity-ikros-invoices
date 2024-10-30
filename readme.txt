=== Invelity iKros Invoices ===
Author: Invelity s.r.o.
Author URI: https://www.invelity.com
Tags: iKros, invoices, WooCommerce
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=38W6PN4WHLK32
Requires at least: 5.2
Tested up to: 5.8
Stable tag: 4.9.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin Invelity iKros invoices is designed for Wordpress (WooCommerce) online stores who have purchased invoicing software iKros. Plugin automates the connecting and sending data from e-shop to invoicing system. You can create invoices directly from your e-shop orders.

== Description ==
Plugin automates the connection and communication process of e-shop communicating with iKros invoicing system.
If you have purchased iKros invoicing system license and you want to create iKros invoices directly from your Woocommerce store, this plugin is right for you.
Simply download the plugin, set up all necessary information including your iKros API Key and you can start creating your iKros invoices directly from e-shop Orders page.


== Installation ==

This section describes how to install the plugin and get it working.

1. Download the plugin and upload files directly via FTP (`/wp-content/plugins/invelity-ikros-invoices).
2. Activate the plugin through the 'Plugins' screen in WordPress.
3 In the main menu to the left you will see new menu item "Invelity plugins" with a dropdown containing "Invelity iKros Invoices" settings page.
4 Input all your settings to the plugin settings page.
5 After the plugin is set up, you can proceed to generate invoices from your orders.
6 Navigate to your orders screen, mark orders you wish to generate invoices for.
7 From the drop down menu (Bulk actions) select option to Export orders to iKros.
8 In your iKros administrator screen select e-shop and download the invoices

== Frequently Asked Questions ==

= Do I need anything else for this plugin to work? =

Yes, you need active subscription with iKros invoicing system https://app.ikros.sk
You also need online store build using WooCommerce plugin.

= Is the plugin free? =

Yes. The plugin is free of charge except your iKros subscription you have to get from https://app.ikros.sk.
We offer support, installation, setup and customization for a fee, contact us at https://www.invelity.com/ or directly at mike@invelity.com

== Screenshots ==

1. Plugin configuration
/assets/screenshot-1.png
2. Plugin usage
/assets/screenshot-2.png

== Change log ==

= 1.0.0 =
* Plugin Release

= 1.1.1 - 1.1.6 =
* Various fixes and tweaking

= 1.1.7 =
* Fixed shipping / billing company
* Fixed error when generating order with non existing product
* Removed Licenses

= 1.1.8 =
* Fixed null value for discountValue when sending invoice to ikros (Parse error)

= 1.1.9 =
* Added multicurrency support. The invoice will be generated in currency that the order was created in.

= 1.2.0 =
* Added multicurrency support. for older woocommerce. Fixed logic with explicit taxes

= 1.2.1 =
* Added option to automatically generate invoices on order status change that can be selected in plugin options

= 1.2.2 =
*Fixed remote data call on servers that block it

= 1.2.3 =
*Fixed Vat percentage in free shipping options.

= 1.2.4 =
*Fixed substr to mb_substr

= 1.2.5 =
*Fixed tax calculation in small prices

= 1.2.6 =
*Fixed tax calculation in small prices

= 1.2.7 =
*Fixed tax calculation in free shippings

= 1.2.8 =
*Fixed billing and shipping first name typos

= 1.2.9 =
*Fixed order number
*Added variable symbol option
*Added ICO,DIC,IC DPH fields support if set correctly

= 1.3.0 =
*Added sku field

= 1.3.1 =
*measuring unit hotfix

= 1.3.2 =
*Added invoice into the email template

= 1.3.3 =
*Fixed automated generation when called from outside of admin