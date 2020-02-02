=== Simple Payment ===
Contributors: idokd
Donate link: https://simple-payment.yalla-ya.com/get
Tags: credit card, simple payment, donation, membership, checkout, payment request, payment gateway, sales, woocommerce, store, ecommerce, e-commerce, commerce, gutenberg, elementor, cardcom, icount, icredit, payme, isracard, paypal
Requires at least: 4.6
Tested up to: 5.3
Stable tag: 1.8.1
Requires PHP: 5.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple Payment enables a simple, fast and powerful integration to process payments. Convert any Post/Page to a product - easy and very customizable tool

== Description ==

Simple Payment enables a simple, fast and powerful integration to process payments, converting any post or page to a Product or Service, no need to install complicated plugins.

Simple Payment works with many payment gateways, and enables you to add you customized gateway easily

Major features in Simple Payment include:

* Integrate any of the supported Payment gateways (PayPal, Cardcom, iCount, PayMe, iCredit)
* Selection of Payment Forms to choose from (Basic, Bootstrap, Legacy, Donation)
* Works with plugins such as: Gutenberg Editor, WooCommerce, WPJobBoard, GravityForms, Form Maker.
* Extend workflow with Zapier - get triggers and preform actions on payments via Zapier.
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

Currently on Beta: iCredit, PayMe - please contact if require assistance.

Soon to be released: Pelecard, Tranzilla, CreditGuard

PS: You'll need an [Simple Payment API key for advanced gateways](https://simple-payment.yalla-ya.com/get/) to use it.  Keys are available for personal blogs; single domain, multiple domains, businesses and commercial sites.

== Installation ==

1. Upload the Simple Payment plugin to your site, Activate it, then enter your [Simple Payment API key](https://simple-payment.yalla-ya.com/get/).

2. Select Payment Page where you will have the Payment Form integrated

3. add shortcode on the Payment Page

4. Activate your Payment Processing on the Admin Menu: Settings -> Simple Payment

4. That's it, track your payments on the Payments Admin Menu log.

== Frequently Asked Questions ==

= Which Payment Gateway this plugin support? =

Currently it supports PayPal, Cardcom, iCount, PayMe, iCredit, with another new payment gateway added every month.

= How does Simple Payment complies with PCI-DSS =

When a payment is passed through the plugin, it restricts the use of sensible data to the minimum possible, 
avoids any replication of variables and data, and upon saving on the database is masks and hides the data
so no sensible data is saved in the database.

Additional it allows you to automaticaly purge any records older the X days.

= Can I work with Simple Payments and other plugins =

Simple Payment is ready to be extended, it exposes actions and filters (add_action & add_filters), to hook
in differnet parts of the payment process.

You can also extend any of the existing Payment Gateways (Engines) or write your own Custom Engine, and finally
you can integrate Simple Payment with [Zapier](https://zapier.com/developer/public-invite/66167/f63e9e617b9e5e534c26c308f15087ee/)




== Feedback and Support ==

I would be happy to receive your feedback to improve this plugin.

Please let me know through [support forums](https://wordpress.org/support/plugin/simple-payment/) if you like it and please be sure to leave a review..

Also you can contact me on my personal page [Ido Kobelkowsky](https://wordpress.org/support/users/idokd/) or even visit [Github](https://github.com/idokd/wp-simple-payment) of Simple Payment where you can find all the development code of this plugin.

I hope it is useful for you and look forward to reading your reviews! 😉 Thanks!

== Changelog ==

= 1.7.9 =
*Release Date - 23 January 2020*
* Support for GravityForms
* General bug fixes and improvements

= 1.7.6 =
*Release Date - 11 December 2019*
* Adding support to iCredit & PayMe
* Support for Elementor Page Builder
* Internet Explorer bug fix
* Improvement of Gutenberg Editor block type
* General bug fixes and improvements

= 1.7.4 =
*Release Date - 4 December 2019*
* Adding Company field for Cardcom & general usage
* Improvement of Gutenberg Editor block type
* Bug fix of SQL error after archive/unarchive on transaction list 
* General bug fixes and improvements

= 1.7.1 =
*Release Date - 24 November 2019*
* Bugfix on Cardcom special cases.

= 1.7.0 =
*Release Date - 24 November 2019*
* Better support for [WPJobBoard](https://wpjobboard.net/)] & WooCommerce
* Enable single item receipt in WooCommerce & WPJobBoard
* Enable customize product name in WooCommerce & WPJobBoard
* Enable iframe, modal & redirect mode in WooCommerce & WPJobBoard
* Cardcom flag to minimize information on receipt - will show only minimum necessary information
* Enables supports() to Engines
* Bugfix on verification process.

= 1.6.8 =
*Release Date - 12 November 2019*

* Beta support to [WPJobBoard](https://wpjobboard.net/) plugin

= 1.6.7 =
*Release Date - 12 November 2019*

* Introducing visible and resetable API_KEY
* Cron Schedule for Simple Payment maintenance
* Auto validate open transactions
* Auto fail open transactions after a certain period

= 1.6.5 =
*Release Date - 11 November 2019*

* Unarchive feature
* General improvements and addons code structure

= 1.4.5 =
*Release Date - 5 November 2019*

Great news! we now support Zapier & WooCommerce, so all Payment Gateways (Engines) can now 
work transparently in WooCommerce.

* Zapier integration; use this link to connect and configure: [Zapier](https://zapier.com/developer/public-invite/66167/f63e9e617b9e5e534c26c308f15087ee/)
* WooCommerce enable Simple Payment on your WooCommerce and enjoy the same control of Simple Payment
* Shortcode now accept: currency & installments
* Stability improvements
* General Bug fixes

= 1.4.0 =
*Release Date - 4 November 2019*

* Introduction of Archive & Purge transaction
* Introduction of Beta Zapier integration
* Create Wordpress User (Secret or Via Register) on payment (pre or post payment)
* General Bug fixes

= 1.0.0 =
*Release Date - 1 October 2019*

* First release
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
