<?php
namespace SimplePayment\Engines;

use SimplePayment;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class Engine {

  public $name = 'Base';
  public $transaction = null;
  public $handler;
  public $interactive;
  protected $callback;
  protected static $params;
  protected $sandbox;

  public function __construct($params = null, $handler = null, $sandbox = true) {
    self::$params = $params;
    $this->handler = $handler;
    $this->sandbox = $sandbox;
  }

  protected function param($key) {
    if (isset(self::$params[$key])) return(self::$params[$key]);
    return(SimplePayment\SimplePayment::param($key));
  }

  public function process($params) {
    // Process the transaction, for example
    // - Call payment gateway API
    // - Redirect to the payment gateway url with params
    // Return FALSE if transaction failed
    return(true);
  }

  public function status($params) {
    // Process the statuc callback, for example
    // Return FALSE if transaction failed
    return(true);
  }

  public function post_process($params) {
    // Process the result of the transactions save
    return(true);
  }

  public function pre_process($params) {
    return($params);
  }

  public function setCallback($url) {
    $this->callback = $url;
  }

  protected function record($request, $response, $fields = []) {
    $params = [];
    foreach ($fields as $field => $keys) {
      if (!is_array($keys)) $keys = [ $keys ];
      foreach ($keys as $key) {
          if (isset($request[$key])) {
              $params[$field] = $request[$key];
              break;
          }
          if (isset($response[$key])) {
              $params[$field] = $response[$key];
              break;
          }
      }
    }
    $params['request'] = json_encode($request);
    $params['response'] = json_encode($response);
    return($this->save($params));
  }

  protected function save($params) {
    if (!isset($params['transaction_id'])) $params['transaction_id'] = $this->transaction;
    return($this->handler->save($params, $this->name));
  }

  public static function uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
       // 32 bits for "time_low"
       mt_rand(0, 0xffff), mt_rand(0, 0xffff),
       // 16 bits for "time_mid"
       mt_rand(0, 0xffff),
       // 16 bits for "time_hi_and_version",
       // four most significant bits holds version number 4
       mt_rand(0, 0x0fff) | 0x4000,
       // 16 bits, 8 bits for "clk_seq_hi_res",
       // 8 bits for "clk_seq_low",
       // two most significant bits holds zero and one for variant DCE1.1
       mt_rand(0, 0x3fff) | 0x8000,
       // 48 bits for "node"
       mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
      );
    }

    public function url($type, $params = null) {
      $url = $this->callback;
      $addition = [];
      if ($params) {
          if (isset($params['payment_id'])) $addition['payment_id'] = $params['payment_id'];
      }
      return($url.(strpos($url, '?') ? '&' : '?').'op='.$type.'&engine='.$this->name.($addition ? '&'.http_build_query($addition) : ''));
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
}
