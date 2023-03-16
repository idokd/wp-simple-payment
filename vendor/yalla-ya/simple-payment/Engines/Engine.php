<?php
namespace SimplePayment\Engines;

use SimplePayment;
use Exception;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

abstract class Engine {

  public static $name = 'Base';
  public $transaction = null;
  public $confirmation_code = null;
  public $handler;
  public $interactive;
  public $password;
  public $amount = null;
  public $payments = null;

  public static $supports = [];
  
  protected $callback;
  protected static $params;
  protected $sandbox;

  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    self::$params = $params;
    $this->handler = $handler;
    $this->sandbox = $sandbox;
    $this->password = $this->sandbox ? $this->password : $this->param( 'password' );
  }

  protected function param( $key ) {
    if ( isset( self::$params[ $key ] ) ) return( self::$params[ $key ] );
    return( SimplePayment\SimplePayment::param( $key ) );
  }

  public function set( $key, $value ) {
    self::$params[ $key ] = $value;
  }

  public function payment_id( $params ) {
    return( isset( $params[ 'payment_id' ] ) && $params[ 'payment_id' ] ? $params[ 'payment_id' ] : false );
  }

  public function process( $params ) {
    // Process the transaction, for example
    // - Call payment gateway API
    // - Redirect to the payment gateway url with params
    // Return FALSE if transaction failed
    // Return array of params to move to post_process if payment was handleded without being redirecting
    return( true );
  }

  public function feedback( $params ) {
    // Process the feedback, for example
    // Return FALSE if transaction failed
    return( true );
  }

  public function status( $params ) {
    // Process the statuc callback, for example
    // Return FALSE if transaction failed
    return( true );
  }

  public function verify( $params ) {
    // Process the statuc callback, for example
    // Return FALSE if transaction failed
    return( false );
  }

  public function refund( $params ) {
    // Process the statuc callback, for example
    // Return FALSE if transaction failed
    return( false );
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
    $params[ 'request' ] = json_encode( $request );
    $params[ 'response' ] = json_encode( $response );
    return( $this->save( $params ) );
  }

  protected function save( $params ) {
    if ( !isset( $params[ 'transaction_id' ] ) && $this->transaction ) $params[ 'transaction_id' ] = $this->transaction;
    return( $this->handler->save( $params, static::$name ) );
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

    public function url( $type, $params = null ) {
      $url = $this->callback;
      $qry = [];
      if (strpos($url, 'transaction_id') === false) $qry['transaction_id'] = $this->transaction;
      if (isset($params['payments']) && strpos($url, 'payments') === false) $qry['payments'] = $params['payments'];
      if (isset($params['payment_id']) && strpos($url, 'payment_id') === false) $qry['payment_id'] = $params['payment_id'];
      if (isset($params['target']) && strpos($url, 'target') === false) $qry['target'] = $params['target'];
      if (isset($params['redirect_url']) && strpos($url, 'redirect_url') === false) $qry['redirect_url'] = $params['redirect_url'];
      return($url.(strpos($url, '?') ? '&' : '?').'op='.$type.'&engine='. static::$name . ($qry ? '&'.http_build_query($qry) : ''));
    }

    protected function post( $url, $vars, $headers = null, $fail = true ) {
      $curl = curl_init();
      curl_setopt( $curl, CURLOPT_URL, $url );
      curl_setopt( $curl, CURLOPT_POST, 1 );
      curl_setopt( $curl, CURLOPT_POSTFIELDS, is_array( $vars ) ? http_build_query( $vars, null, '&' ) : $vars );
      curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
      curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, 0 ); // TODO: consider enabling it
      curl_setopt( $curl, CURLOPT_FAILONERROR, $fail );
      //curl_setopt( $curl, CURLOPT_VERBOSE, true );
      if ( $headers ) curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
      $response = curl_exec( $curl );
      $error = curl_error( $curl );
      # some error , send email to developer // TODO: Handle Error
      if ( !empty( $error ) ) throw new Exception( $error . ' ' . $response. ' - ' . $url . print_r( $vars, true ), 500 );
      curl_close( $curl );
      return( $response );
    }

    public static function century() {
      return( floor( date( 'Y' ) / 100 ) * 100 );
    }

    public function subscriptions( $params = [] ) {
      return( null );
    }

}
