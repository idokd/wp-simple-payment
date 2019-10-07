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
  protected $callback;

  public function __construct($params = null) {
  }

  protected function param($key) {
    return(SimplePayment\SimplePayment::param($key));
  }

  public function process($params) {
    // Process the transaction, for example
    // - Call payment gateway API
    // - Redirect to the payment gateway url with params
    // Return FALSE if transaction failed
    return(true);
  }

  public function post_process($params) {
    // Process the result of the transactions save
    return(true);
  }

  public function pre_process($params) {
    $request = [];
    $response = [];
    $this->record($request, $response);
    return($params);
  }

  public function setCallback($url) {
    $this->callback = $url;
  }

  protected function record($request, $response) {
    return($this->save(array('request' => $request, 'response' => $response)));
  }

  protected function save($params) {
      return($this->handler->save(strtolower($this->name), $params));
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

    public function url($type) {
      $url = $this->callback;
      return($url.(strpos($url, '?') ? '&' : '?').'op='.$type.'&engine='.$this->name);
    }

}
