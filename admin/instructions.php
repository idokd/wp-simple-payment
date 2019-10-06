<?php
?>

<h2>About Simple Payment</h2>

Simple Payment integrates some sample Payment Gateway, and allows you to extend your own theme gateway easiy, Simple Payment will handle all the logic, provide you with the workflow and transaction log for what you require. you will focus on 3 Steps implementation:

<ol>
  <li>Pre Process - Prepare all data and validation before Processing Payment</li>
  <li>Process - Receive all parameters and process the transaction with your Payment Gateway</li>
  <li>Post Process - On sucessfull transaction post process any aditional actions.</li>
</ol>

<br />
For further implementation checkout: <a href="https://github.com/idokd/simple-payment" target="_blank">Custom Theme Payment Engine</a>
<h2>Simple Payment Configuration</h2>

You require 3 Steps configurations:

<ol>
  <li>Create Payment Page and assign it on Admin Menu: Settings -> Reading</li>
  <li>Add [simple_payment] shortcode on the new created page</li>
  <li>Configure Simple Payment Settings on Admin Menu: Settings -> Simple Payment</li>
  <li>Optional: Create a thank you page and set url on Simple Payments Settings</li>
</ol>


<h2>Coming Soon</h2>

<ul>
  <li>Simple integration with Elementor & Gutenberg</li>
  <li>Automatic Process for Recurring Payments (for memberships etc.)</li>
  <li>More Payment Gateways: iCount, PayPal, Conekta</li>
  <li>Spanish Language Support</li>
</ul>
