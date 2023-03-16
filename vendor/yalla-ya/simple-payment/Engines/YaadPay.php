<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;

if ( !defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class YaadPay extends Engine {

  public static $name = 'YaadPay';
  public $interactive = true;
  protected $recurrAt = 'status'; // status, post, provider

  public static $supports = [ 'iframe', 'modal', 'tokenization' ];

  public static $methods = [ 'cc' ];

  public static $domains = [
    'icom.yaad.net',
  ]; // https://icom.yaad.net/p/

  public $api = [
    'live' => 'https://icom.yaad.net/p/',
    'sandbox' => 'https://icom.yaad.net/p/'
  ];

  public $terminal;
  public $username;
  public $password;
  public $apikey;

  const PAYMENT_ID = 'Order';

  const MESSAGES = [
    '901' => 'Terminal is not permitted to work in this method	No permission',
    '902' => 'Authentication error	A reference to the terminal differs from the authentication method defined',
    '903' => 'The number of payments configured in the terminal has been exceeded	For change please contact support',
    '999' => 'Comunication error - YaadPay',
    '998' => 'Deal canclled- YaadPay',
    '997' => 'Token is not valid',
    '996' => 'Terminal is not permitted to use token',
    '800' => 'Postpone charge',
    '700' => 'Approve without charge	Reserve a credit line without a deposit in J5',
    '600' => 'Receiving transaction details (J2)	Check card information - Check the integrity of the card number without checking the J2 frame',
    '990' => 'Card details are not fully readable, please pass the card again',
    '400' => 'Sum of Items differ from transaction amount	Parameter Amount and amount of items are not equal (Invoice module)',
    '401' => 'It is requierd to enter first or last name	you must send ClientName or ClientLName param',
    '402' => 'It is requierd to enter deal information',
  ];

  const CURRENCIES = [ 'ILS' => 1, 'USD' => 2, 'EUR' => 3, 'GBP' => 4 ] ; //'AUD' => 36,	'CAD' => 124, 'DKK' => 208, 'JPY' => 392, 'NZD' => 554, 'RUB' => 643, 'CHF' => 756, 'GBP' => 826 ];

  
  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
    $this->terminal = $this->param( 'terminal' );
    $this->username = $this->param( 'username' );
    $this->password = $this->param( 'password' );
    $this->apikey = $this->param( 'apikey' );
    $this->api = $this->api[ $this->sandbox ? 'sandbox' : 'live' ];
  }

  public function post( $url, $params, $headers = null, $fail = true  ) {
    $params[ 'Masof' ] = $this->terminal;
    $params[ 'PassP' ] = $this->password;
    $params[ 'KEY' ] = $this->apikey;
    return( parent::post( $url, $params, $headers, $fail ) );
  }

  public function payment_id( $params ) {
    $payment_id = parent::payment_id( $params );
    return( isset( $params[ self::PAYMENT_ID ] ) && $params[ self::PAYMENT_ID ] ? $params[ self::PAYMENT_ID ] : $payment_id );
  }

  public function process( $params ) {
    return( $this->api . '?' . http_build_query( $params ) );
  }

  public function verify( $transaction = null ) {
    if ( $transaction ) $this->transaction = $transaction[ 'transaction_id' ];
    $payment_id = isset( $transaction[ 'payment_id' ] ) && $transaction[ 'payment_id' ] ? $transaction[ 'payment_id' ] : $transaction[ self::PAYMENT_ID ];
    $params = [
      'action' => 'APISign',
      'What' => 'VERIFY',
    ];
    $status = $this->post( $this->api, $params ); 
    parse_str( $status, $status );
    if ( isset( $status[ 'ACode' ] ) && $status[ 'ACode' ] ) {
      $this->confirmation_code = $status[ 'ACode' ];
    }
    $this->save( [
      'payment_id' => $payment_id,
      'transaction_id' => $this->transaction,
      'url' => $this->api . '#APISign-VERIFY',
      'status' => isset( $status[ 'CCode' ] ) && $status[ 'CCode' ] ? $status[ 'CCode' ] : 0,
      'description' => isset( $status[ 'CCode' ] ) && $status[ 'CCode' ] ? self::MESSAGES[ $status[ 'CCode' ] ] : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $status )
    ] );
    
    if ( isset( $status[ 'CCode' ] ) && intval( $status[ 'CCode' ] ) ) {
      throw new Exception( self::MESSAGES[ $status[ 'CCode' ] ] , intval( $status[ 'CCode' ] ) );
    }

    if ( $this->confirmation_code ) {
      return( $this->confirmation_code );
    }
    return( false );
  }

  public function status( $params ) {
    return( $this->verify( $params ) );
    //if ( $params[ 'status' ] != 1 ) 
      //throw new Exception( isset( $params[ 'err' ] ) ? $params[ 'err' ][ 'message' ] : $params[ 'status' ], $params[ 'err' ][ 'id' ] );
      
    $token = null;
    if ( isset( $info[ 'cardToken' ] ) && $info[ 'cardToken' ] ) {
      $token = [
          'token' => $info[ 'cardToken' ],
          SimplePayment::CARD_OWNER => $info[ 'fullName' ],
          SimplePayment::CARD_EXPIRY_YEAR => substr( $info[ 'cardExp' ], 2 ),
          SimplePayment::CARD_EXPIRY_MONTH => substr( $info[ 'cardExp' ], 0, 2 ),
          'card_type' => $info[ 'cardType' ],
          'expiry' => $info[ 'cardExp' ],
          'brand' => $info[ 'cardBrand' ],
          'suffix' => $info[ 'cardSuffix' ],
      ];
    }
    $data = [
      'transaction_id' => $this->transaction,
      'payment_id' => $info[ 'customFields' ][ 'cField1' ],
      'url' => $_SERVER[ 'REQUEST_URI' ],
      'status' => isset( $params[ 'status' ] ) && $params[ 'status' ] ? $params[ 'status' ] : $params[ 'err' ][ 'id' ],
      'description' => isset( $params[ 'err' ] ) && isset( $params[ 'err' ][ 'message' ] ) ? $params[ 'err' ][ 'message' ] : null,
      'request' => json_encode( $_REQUEST ),
      'response' => null
    ];
    if ( $token ) $data[ 'token' ] = $token;
    $this->save( $data );

    $this->confirmation_code = $params[ 'status' ] == 1 ? $info[ 'asmachta' ] : null;

    switch( $info[ 'transactionTypeId' ] ) {
      case 1:
        $password = $this->param( 'subscriptions' );
        break;
      case 3:
        $password = $this->param( 'bit' );
        break;
      default:
        $password = $this->password;
    }
    $info[ 'pageCode' ] = $password;
    $status = $this->post( $this->api[ 'approveTransaction' ], $info ); 
    $status = json_decode( $status, true );
    $response = $status[ 'data' ];
    $this->save([
      'transaction_id' => $this->transaction,
      'payment_id' => $info[ 'customFields' ][ 'cField1' ],
      'url' => $this->api[ 'approveTransaction' ],
      'status' => isset( $status[ 'status' ] ) && $status[ 'status' ] ? $status[ 'status' ] : $status[ 'err' ][ 'id' ],
      'description' => isset( $status[ 'err' ] ) && isset( $status[ 'err' ][ 'message' ] ) ? $status[ 'err' ][ 'message' ] : null,
      'request' => json_encode( $info ),
      'response' => json_encode( $status ),
    ] );
    return( $this->confirmation_code );
  }

  public function post_process( $params ) {
    $payment_id = $params[ self::PAYMENT_ID ];
    $this->save( [
      'url' => ':post_process',
      'payment_id' => $payment_id,
      'status' => isset( $params[ 'CCode' ] ) && intval( $params[ 'CCode' ] ) ? intval( $params[ 'CCode' ] ) : false,
      'description' => isset( $params[ 'errMsg' ] ) ? $params[ 'errMsg' ] : ( intval( $params[ 'CCode' ] ) ? self::MESSAGES[ $params[ 'CCode' ] ] : null ),
      'request' => json_encode( $params ),
      'response' => json_encode( $params )
    ] );
    // TODO: implement; verify
    $this->confirmation_code = isset( $params[ 'ACode' ] ) && $params[ 'ACode' ] ? $params[ 'ACode' ] : $this->verify( $params );
    return( $this->confirmation_code );
  }

  public function pre_process( $params ) {
    $currency = isset( $params[ SimplePayment::CURRENCY ] ) && $params[ SimplePayment::CURRENCY ] ? $params[ SimplePayment::CURRENCY ] : $this->param( 'currency' );
    if ( $currency ) {
      if ( $currency = self::CURRENCIES[ $currency ] ) $post[ 'Coin' ] = $currency;
      else throw new Exception( 'CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500 );
    }
    // Coin
    /*if ( isset( $params[ 'payments' ] ) && $params[ 'payments' ] ) {
      if ( $params[ 'payments' ] == 'monthly' ) {
        $this->password = $this->param( 'subscriptions' );
        $method = 1;
      }
      if ( $params[ 'payments' ] == 'installments' ) {
        $payments = $this->param( 'installments_max' );
        $post[ 'maxPaymentNum' ] = $payments ? $payments : ( isset( $params[ 'installments' ] ) ? $params[ 'installments' ] : 12 );
        $payments = $this->param( 'installments_min' );
        if ( $payments != '' ) $post[ 'paymentNum' ] = $payments;
        $post[ 'paymentNum' ] = isset( $params[ 'installments' ] ) && $params[ 'installments' ] ? $params[ 'installments' ] : $this->param( 'installments_default' );
      }
    }*/
    //if ( isset( $params[ SimplePayment::METHOD ] ) && strtolower( $params[ SimplePayment::METHOD ] ) == 'bit' ) $method = 3;
    $post = [
      'action' => 'APISign',
      'What' => 'SIGN',
      'Info' => $params[ SimplePayment::PRODUCT ],
      'Amount' => $params[ SimplePayment::AMOUNT ],

    ];

    // TODO: check payments && tokenize
//    if ( isset( $params[ 'tokenize' ] ) && $params[ 'tokenize' ] ) {
//      $post[ 'saveCardToken' ] = $params[ 'tokenize' ];
//    }

  //Order
    if ( isset( $params[ 'payment_id' ] ) && $params[ 'payment_id' ] ) $post[ self::PAYMENT_ID ] = $params[ 'payment_id' ];
    // Fild1, Fild2, Fild3

  // UTF8, UTF8out, , J5, MoreData
  // CC, CC2, cvv, Tmonth, Tyear
  // AuthNum , Postpone, Token, tOwner
  // sendemail, TashFirstPayment, Tash, tashType
  // PageLang

    if ( isset( $params[ SimplePayment::FULL_NAME ] ) && $params[ SimplePayment::FULL_NAME ] ) $post[ 'ClientName' ] = strpos( ' ', $params[ SimplePayment::FULL_NAME ] ) === false ? $params[ SimplePayment::FULL_NAME ] . ' .' : $params[ SimplePayment::FULL_NAME ];
    if ( isset( $params[ SimplePayment::FIRST_NAME ] ) && $params[ SimplePayment::FIRST_NAME ] ) $post[ 'ClientName' ] = $params[ SimplePayment::FIRST_NAME ];
    if ( isset( $params[ SimplePayment::LAST_NAME ] ) && $params[ SimplePayment::LAST_NAME ] ) $post[ 'ClientLName' ] = $params[ SimplePayment::LAST_NAME ];

    if ( isset( $params[ SimplePayment::CARD_OWNER_ID ] ) && $params[ SimplePayment::CARD_OWNER_ID ] ) $post[ 'UserId' ] = $params[ SimplePayment::CARD_OWNER_ID ]; // 000000000
    if ( isset( $params[ SimplePayment::MOBILE ] ) && $params[ SimplePayment::MOBILE ]) $post[ 'cell' ] = preg_replace( '/\D/', '', $params[ SimplePayment::MOBILE ] );
    if ( isset( $params[ SimplePayment::PHONE ] ) && $params[ SimplePayment::PHONE ]) $post[ 'phone' ] = preg_replace('/\D/', '', $params[ SimplePayment::PHONE ] );
    if ( isset( $params[ SimplePayment::EMAIL ] ) && $params[ SimplePayment::EMAIL ]) $post[ 'email' ] = $params[ SimplePayment::EMAIL ];
    //if ( isset( $params[ SimplePayment::COMPANY ] ) && $params[ SimplePayment::COMPANY ]) $post[ 'pageField[invoiceName]' ] = $params[ SimplePayment::COMPANY ];

    $status = $this->post( $this->api, $post ); 
 
    parse_str( $status, $status );
    $response = $status;
    $this->transaction = $this->transaction ? : self::uuid();

    $this->save( [
      'payment_id' => $params[ 'payment_id' ],
      'transaction_id' => $this->transaction,
      'url' => $this->api . '#APISign-SIGN',
      'status' => isset( $status[ 'CCode' ] ) && $status[ 'CCode' ] ? $status[ 'CCode' ] : 0,
      'description' => isset( $status[ 'CCode' ] ) && $status[ 'CCode' ] ? self::MESSAGES[ $status[ 'CCode' ] ] : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $status )
    ] );
    
    if ( isset( $status[ 'CCode' ] ) && intval( $status[ 'CCode' ] ) ) {
      throw new Exception( self::MESSAGES[ $status[ 'CCode' ] ] , intval( $status[ 'CCode' ] ) );
    }
    return( $response );
  }

  function authorize( $params, $transaction_id = null ) {
    // TODO: finish the params values
    $this->transaction = $transaction_id;
    $params = [
      'pageCode' => $this->password,
      'transactionId' => '',
      'transactionToken' => '',
      'sum' => 0,
    ];
    $status = $this->post( $this->api[ 'settleSuspendedTransaction' ], $params );
    $status = json_decode( $status, true );
    $response = $status[ 'data' ];
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api[ 'settleSuspendedTransaction' ],
      'status' => isset( $status[ 'status' ] ) && $status[ 'status' ] ? $status[ 'status' ] : $status[ 'err' ][ 'id' ],
      'description' => isset( $status[ 'err' ] ) && isset( $status[ 'err' ][ 'message' ] ) ? $status[ 'err' ][ 'message' ] : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $status )
    ] );
    return( $status[ 'status' ] == 1 );
  }

  // TODO: using soft
/*
  function charge( $params, $transaction_id = null, $operation = 2 ) {
    // TODO: finish the params values
    $this->transaction = $transaction_id;
    $params = [
      'userId' => $this->username,
      'pageCode' => $this->password,
      'cardToken' => '',
      'sum' => '',
      'description' => 0,
      'paymentType' => $operation,
      'paymentNum' => 1,
    ];
    // pageField
    $status = $this->post( $this->api[ 'createTransactionWithToken' ], $params );
    $status = json_decode( $status, true );
    $response = $status[ 'data' ];
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api[ 'createTransactionWithToken' ],
      'status' => isset( $status[ 'status' ] ) && $status[ 'status' ] ? $status[ 'status' ] : $status[ 'err' ][ 'id' ],
      'description' => isset( $status[ 'err' ] ) && isset( $status[ 'err' ][ 'message' ] ) ? $status[ 'err' ][ 'message' ] : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $status )
    ] );
    return( $status[ 'status' ] == 1 );
    
  }
*/
}
