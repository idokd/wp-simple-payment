=== Simple Payment ===
Contributors: idokd
Donate link: https://simple-payment.yalla-ya.com/get
Tags: credit card, donation, membership, checkout, payment request, payment gateway, sales, woocommerce, store, ecommerce, e-commerce, commerce, gutenberg, elementor
Requires at least: 4.6
Tested up to: 5.2.4
Stable tag: 1.3.8
Requires PHP: 5.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple Payment enables a simple, fast and easy integration to process payments. Convert any Post/Page to a product - easy and powerful tool

== Description ==

Simple Payment enables a simple, fast and easy integration to process payments, converting any post or page to a Product or Service, no need to install complicated plugins.

Simple Payment works with many payment processing, and enables you to add you customized gateway easily

Major features in Simple Payment include:

* Integrate any of the supported Payment gateways (PayPal, Cardcom, iCount)
* Selection of Payment Forms to choose from (Basic, Bootstrap, Legacy, Donation)
* Donation Form for free entry amount
* PCI-DSS Data Protection Ready (All sensitive are masked in database)
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
* Support for Gutenberg Editor

PS: You'll need an [Simple Payment API key for advanced gateways](https://simple-payment.yalla-ya.com/get/) to use it.  Keys are available for personal blogs; single domain, multiple domains, businesses and commercial sites.

== Installation ==

1. Upload the Simple Payment plugin to your site, Activate it, then enter your [Simple Payment API key](https://simple-payment.yalla-ya.com/get/).

2. Select Payment Page where you will have the Payment Form integrated

3. add shortcode on the Payment Page

4. Activate your Payment Processing on the Admin Menu: Settings -> Simple Payment

4. That's it, track your payments on the Payments Admin Menu log.

== Frequently Asked Questions ==

= Which Payment Gateway this plugin support? =

Currently it supports PayPal, Cardcom, iCount, with another new payment gateway added every month.

== Feedback and Support ==

I would be happy to receive your feedback to improve this plugin.

Please let me know through [support forums](https://wordpress.org/support/plugin/simple-payment/) if you like it and please be sure to leave a review..

Also you can contact me on my personal page [Ido Kobelkowsky](https://wordpress.org/support/users/idokd/) or even visit [Github](https://github.com/idokd/wp-simple-payment) of Simple Payment where you can find all the development code of this plugin.

I hope it is useful for you and look forward to reading your reviews! 😉 Thanks!

== Changelog ==

Checkout [changelog.txt](http://plugins.svn.wordpress.org/simple-payment/trunk/changelog.txt) file

== Screenshots ==

1. Create Payment Page; use Admin -> Settings -> Reading to define which is your Payment Page.
2. Configure the gateway parameters, you may use one of our many preinstalled gateways.
3. Integrate our [simple_payment] shortcode on that page, to show one of our wonderful forms
4. Track each transaction, keep record of parameters sent and responds.
5. Payment Form example (our Legacy form) - we have bootstrap ready, donation etc.
6. REMEMBER! Set the Payment Page so you can enjoy the plugin.
7. Gutenberg Editor support - easy configuration for your multiple payment forms.
8. Tranaction Log
9. Extended configuration
10. PCI-DSS ready, masking data directly in database


== Demo Site ==
You can access admin and checkout our site demo to see how the plugin works
URL: [https://wordpress.yalla-ya.com/](https://wordpress.yalla-ya.com/)
Username: admin
Password: admin

== Advanced Configuration: Theme Custom Payment Processing ==

To write your own payment processing integration, to be plugin, read the information at this link: [https://github.com/idokd/wp-simple-payment](https://github.com/idokd/wp-simple-payment)

you will require to write a simple php class that Pre Process, Process and Post Process the transaction with your your payment gateway.
