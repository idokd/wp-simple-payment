<?php
namespace SimplePayment\Engines;

use SimplePayment\Engines\SimplePaymentEngine;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}
/*
buttons
<script
    src="https://www.paypal.com/sdk/js?client-id=SB_CLIENT_ID">
  </script>

  <div id="paypal-button-container"></div>

  <script>
    paypal.Buttons().render('#paypal-button-container');
  </script>
  */
class PayPal extends Engine {

  public $name = 'PayPal';
  protected $callback;

  public $api = [
    'version' => 10,
    // POST

  ];

  protected $username = 'barak9611';
  protected $password = 'c1234567!';

  public function __construct($username = null, $password = null) {
      if ($username) {
        $this->username = $username;
        $this->password = $password;
      }
  }

  public function pre_process($params) {
  }

  public function post_process($params) {
  }

  public function process($params) {
    $post = [];


    $status = $this->post($this->api['payment_request'], $post);
    parse_str($status, $status);
    return($status);
  }

  protected function post($url, $vars) {
    $urlencoded = http_build_query($vars);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $urlencoded);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // TODO: consider enabling it
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    # some error , send email to developer // TODO: Handle Error
    if (!empty( $error )) die($error);
    curl_close($curl);
    return($response);
  }

  public function setCallback($url) {
    $this->callback = $url;
  }

}
