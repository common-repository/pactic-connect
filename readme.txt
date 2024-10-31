=== Pactic Connect ===
Contributors: furgefutarhu
Tags: pactic.hu, pactic, furgefutar, allpacka, woocommerce
Requires at least: 6.0
Tested up to: 6.6.1
Requires PHP: 8.0
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pactic delivery solution for WooCommerce webshops

== Description ==

We provide the most efficient parcel delivery solution to countries in the European Union. Join thousands of satisfied customers.

Connect your WooCommerce online store with Pactic's online logistics service. The free plugin provides the possibility to generate waybills through the Pactic Dashboard.

== Features ==
*	For both parcel and home delivery
*	Your customers can choose from a map or a drop-down menu to select parcel points
*	Unique option for customizing cash on delivery fees
*	You can also set the delivery price depending on the value of the purchase
*	Send order details to [Pactic](https://pactic.com/)
*	For each successful delivery, we store the order ID of [Pactic](https://pactic.com/) and the waybill number
*	The generated waybill can be downloaded and printed immediately or anytime later on
*	Bulk waybill generation is also possible, downloading multiple labels in a single PDF
*	Track the status of your parcels via [Pactic](https://pactic.com/)



To use this plugin, you need a Pactic [business contract](https://dashboard.pactic.com/en/business-registration).
Please contact us via the link above or via email at [sales_hu@pactic.com](mailto:sales_hu@pactic.com)!

Terms and conditions: [Pactic T&C](https://allpacka.com/terms-conditions-tc)

The plugin relies on 3rd party services for:

*	Listing the countries with available parcel points
*	Displaying parcel points on a map (optional to use)
*	Listing the available parcel points

Endpoint used for country listing: https://api.pactic.com/webservices/shipment/parcelpoints_v2/countries.ashx
Endpoint used for map display: https://maps.googleapis.com/maps/api/js?key='.$pactic_connect__google_map_api_key.'&loading=async
Endpoint used for parcel point listing: https://api.pactic.com/webservices/shipment/parcelpoints_v2/downloadparcelpoints.ashx

== Installation ==

= UPLOAD FROM FILE =

1. Download this plugin. You can ask for the installation file from our [sales team](mailto:sales_hu@pactic.com).
2. In WordPress, use Plugins / Add New / Upload Plugin to install it.
3. Go to WooCommerce / Settings / Pactic Connect to enable the plugin.
4. The plugin is ready to use.

= DOWNLOAD FROM WP LIBRARY =

1. On the admin site, click Plugins / Add New and search for Pactic Connect in the search field.
2. Click on the Install now button next to the plugin.
3. Go to WooCommerce / Settings / Pactic Connect to enable the plugin.
4. The plugin is ready to use.

== Frequently Asked Questions ==

= I can't synchronize my orders. What could be the problem? =
To be able to synchronize your orders you need to do the following steps:
*	Create a REST API key. To do this go to WooCommerce / Settings / Advanced / REST API and click add key. Enter a name and select read/write (!) permissions, then Generate API key. Make sure to copy your new keys as the secret key will be hidden once you leave the page. You have to add these keys on Pactic later.
*	Enable Legacy API. To do this go to WooCommerce / Settings / Advanced / Legacy API and check in the check box, then save. Important to note that ️The Legacy REST API will be removed in WooCommerce 9.0. A separate [WooCommerce extension](https://wordpress.org/plugins/woocommerce-legacy-rest-api/) is available to keep it enabled.
*	Go to WooCommerce / Settings / Advanced / Features and set Order data storage to High-performance order storage and enable compatibility mode.

= How should I mark different payment methods as COD? =
You can select the desired payment methods from the list under Cash on delivery payment.
To select one or more options use ctrl+left click or if you would like to de-select options do the same.

== Screenshots ==
1. Pactic Connect – here you can enable the plugin, add new carriers and select how you would like to display the parcel points. view screenshot-1.jpeg
2. Add shipping method – Add our parcel point or home delivery solution as shipping method to yor shipping zone view screenshot-2.jpeg
3. Enable shipping method – Turn on or off your shipping methods freely in the given zone 
4. Edit shipping method – Editing the shipping method let's you rename it, change taxable status, and select between your services added on the Pactic Connect page. Select Open detailed pricing settings to customize pricing for that service or add a general price with the Default shipping cost.
5. Detailed pricing – You can add the prices for weight ranges, additional COD fee and set cart value based free shipping here

== Changelog ==

= 1.0 =
* First uploaded version to WordPress.org

== Upgrade Notice ==

= 1.0 =
* First uploaded version to WordPress.org