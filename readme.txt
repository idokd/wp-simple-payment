=== Simple Payment ===
Contributors: idokd

Simple Payment enables a simple, fast and easy integration to process payments. Convert any Post/Page to a product - easy and powerful tool

Tags: credit card, donation, membership, payment request, payment gateway, sales, woocommerce, store, ecommerce, e-commerce, commerce

Requires at least: 4.0
Requires PHP: 7.2
Tested up to: 5.2
Stable tag: 1.0.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html



== Description ==

Simple Payment enables a simple, fast and easy integration to process payments, converting any post or page to a Product or Service, no need to install complicated plugins.

Simple Payment works with many payment processing, and enables you to add you customized gateway easily

Major features in Simple Payment include:

* Convert Any Post / Page to a Service/ Product
* Automatically takes Post/Page Title as Product Name
* Simple integrate Buy Button everywhere
* Custom Field: amount - will be the amount to be charged
* Full Form with Templates: Bootstrap, Legacy
* Support for personalized theme payment forms templates
* Simple Use of Shortcode to convert any post/page
* Enable Multiple Payment Engines
* Transactions / Payments Log with Filtering
* Export Transactions to CSV

PS: You'll need an [Simple Payment API key for advanced gateways](https://simple-payment.yalla-ya.com/get/) to use it.  Keys are available for personal blogs; single domain, multiple domains, businesses and commercial sites.

== Installation ==

1. Upload the Simple Payment plugin to your site, Activate it, then enter your [Simple Payment API key](ttps://simple-payment.yalla-ya.com/get/).

2. Select Payment Page where you will have the Payment Form integrated

3. add shortcode on the Payment Page

4. Activate your Payment Processing on the Admin Menu: Settings -> Simple Payment

4. That's it, track your payments on the Payments Admin Menu log.

== Frequently Asked Questions ==

= Which Payment Gateway this plugin support? =

Currently it supports PayPal & Cardcom, with another new payment gateway added every month.

== Changelog ==

Checkout changelog.txt file

== Screenshots ==

1. Create Payment Page; use Admin -> Settings -> Reading to define which is your Payment Page.
2. Configure the gateway parameters, you may use one of our many preinstalled gateways.
3. Integrate our [simple_payment] shortcode on that page, to show one of our wonderful forms
4. Track each transaction, keep record of parameters sent and responds.
5. Payment Form example (our Legacy form) - we have bootstrap ready, donation etc.
6. REMEMBER! Set the Payment Page so you can enjoy the plugin.

== Advanced Configuration: Theme Custom Payment Processing ==

To write your own payment processing integration, to be plugin, read the information at this link: https://github.com/idokd/wp-simple-payment

you will require to write a simple php class that Pre Process, Process and Post Process the transaction with your your payment gateway.
