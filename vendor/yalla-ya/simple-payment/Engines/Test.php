<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;

if ( !defined( 'ABSPATH' ) )
  exit; // Exit if accessed directly

class Test extends Engine {

  public static $name = 'Test';
  public $interactive = true;

  public static $supports = [ 'cvv', 'tokenization', 'card_owner_id' ];
  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
  }

  public static function uuid() {
    $uuid = parent::uuid();
    $uuid = str_replace( '-', '', $uuid );
    return( base64_encode( pack( 'h*', $uuid ) ) );
  }
  
  public function pre_process( $params ) {
    parent::pre_process( $params );
    $this->transaction = self::uuid();
    $params[ SimplePayment::TRANSACTION_ID ] = $this->transaction;
    return( $params );
  }

  public function process( $params ) {
    $this->confirmation_code = '1000000';
    return($params );
  }

  public function verify( $transaction ) {
    $this->transaction = $transaction[ 'transaction_id' ];
    $this->confirmation_code = '1000000';
    return( $this->confirmation_code ? true : false );
  }

  public function status( $params ) {
    $this->confirmation_code = '1000000';
    return( $this->confirmation_code );
  }

  public function post_process( $params ) {
    return( $this->confirmation_code ? true : false );
  }

  public function subscriptions( $params = [] ) {
    return( $params );
  }

}