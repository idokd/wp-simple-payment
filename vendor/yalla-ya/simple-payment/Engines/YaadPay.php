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
    return( is_bool( $params ) ? $params : $this->api . '?' . http_build_query( $params ) );
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
    if ( $params[ 'token' ] ) return( $this->charge( $params ) ); 
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
      'Amount' => $params[ SimplePayment::AMOUNT ]
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

  public function recharge( $params ) {
    $this->transaction = self::uuid();
    return( $this->charge( $params ) ? $this->confirmation_code : false );
  }

  public function charge( $params, $refund = false ) {

    //https://yaadpay.docs.apiary.io/#introduction/soft-protocol-transaction-in-web-server/parameters-soft

    //https://icom.yaad.net/p/?
  /*
      action=soft&
      Masof=0010131918&
      PassP=yaad&

      CC=1315872608557940000&

      Coin=1&
      Info=test-api&
      Order=12345678910&
      city=netanya&
      street=levanon+3&
      zip=42361&
      J5=False&
      MoreData=True&
      Postpone=False&
      Pritim=True&
      SendHesh=True&
      heshDesc=%5B0~Item+1~1~8%5D%5B0~Item+2~2~1%5D&
      sendemail=True&
      UTF8=True&
      UTF8out=True&
      Fild1=freepram&
      Fild2=freepram&
      Fild3=freepram&
      Token=True
    // TODO: finish the params values
    $this->transaction = $transaction_id;
*/
    $this->transaction = $this->transaction ? : self::uuid();

    $post = [
      'action' => 'soft',
      'Info' => $params[ SimplePayment::PRODUCT ],
      'Amount' => $params[ SimplePayment::AMOUNT ],
      'sendemail' => 'False',
      'UTF8' => 'True',
      'UTF8out' => 'True',
      'MoreData' => 'True',
      'J5' => 'False', // J2
      'Postpone' => 'False'
      // CC2
      // cvv
      // tOwner
    ];

    if ( isset( $params[ SimplePayment::INSTALLMENTS ] ) && $params[ SimplePayment::INSTALLMENTS ] ) {
      $post[ 'Tash' ] = $params[ SimplePayment::INSTALLMENTS ];
      //$post[ 'tashType' ] = 1; // 1 - Regular, 6 -  Credit
      //$post[ 'TashFirstPayment' ] = '';
    }

    $currency = isset( $params[ SimplePayment::CURRENCY ] ) && $params[ SimplePayment::CURRENCY ] ? $params[ SimplePayment::CURRENCY ] : $this->param( 'currency' );
    if ( $currency ) {
      if ( $currency = self::CURRENCIES[ $currency ] ) $post[ 'Coin' ] = $currency;
      else throw new Exception( 'CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500 );
    }

    if ( isset( $params[ SimplePayment::FULL_NAME ] ) && $params[ SimplePayment::FULL_NAME ] ) $post[ 'ClientName' ] = strpos( ' ', $params[ SimplePayment::FULL_NAME ] ) === false ? $params[ SimplePayment::FULL_NAME ] . ' .' : $params[ SimplePayment::FULL_NAME ];
    if ( isset( $params[ SimplePayment::FIRST_NAME ] ) && $params[ SimplePayment::FIRST_NAME ] ) $post[ 'ClientName' ] = $params[ SimplePayment::FIRST_NAME ];
    if ( isset( $params[ SimplePayment::LAST_NAME ] ) && $params[ SimplePayment::LAST_NAME ] ) $post[ 'ClientLName' ] = $params[ SimplePayment::LAST_NAME ];

    if ( isset( $params[ SimplePayment::CARD_OWNER_ID ] ) && $params[ SimplePayment::CARD_OWNER_ID ] ) $post[ 'UserId' ] = $params[ SimplePayment::CARD_OWNER_ID ]; // 000000000
    if ( isset( $params[ SimplePayment::MOBILE ] ) && $params[ SimplePayment::MOBILE ]) $post[ 'cell' ] = preg_replace( '/\D/', '', $params[ SimplePayment::MOBILE ] );
    if ( isset( $params[ SimplePayment::PHONE ] ) && $params[ SimplePayment::PHONE ]) $post[ 'phone' ] = preg_replace('/\D/', '', $params[ SimplePayment::PHONE ] );
    if ( isset( $params[ SimplePayment::EMAIL ] ) && $params[ SimplePayment::EMAIL ]) $post[ 'email' ] = $params[ SimplePayment::EMAIL ];

    if ( isset( $params[ SimplePayment::CARD_NUMBER ] ) ) $post[ 'CC' ] = $params[ SimplePayment::CARD_NUMBER ];
    if ( isset( $params[ SimplePayment::CARD_EXPIRY_YEAR ] ) ) $post[ 'Tyear' ] = $params[ SimplePayment::CARD_EXPIRY_YEAR ];
    if ( isset( $params[ SimplePayment::CARD_EXPIRY_MONTH ] ) ) $post[ 'Tmonth' ] = $params[ SimplePayment::CARD_EXPIRY_MONTH ];
    if ( isset( $params[ SimplePayment::CARD_OWNER_ID ] ) ) $post[ 'UserId' ] = $params[ SimplePayment::CARD_OWNER_ID ];
    if ( isset( $params[ 'reference' ] ) && $params[ 'reference' ]) $post[ 'AuthNum' ] = $params[ 'reference' ];

    // TODO: check payments && tokenize
    //    if ( isset( $params[ 'tokenize' ] ) && $params[ 'tokenize' ] ) {
    //      $post[ 'saveCardToken' ] = $params[ 'tokenize' ];
    //    }

    //Order
    if ( $payment_id = $this->payment_id( $params ) ) $post[ self::PAYMENT_ID ] = $payment_id;

    $token = $params[ 'token' ] ? ( is_array( $params[ 'token' ] )  ? $params[ 'token' ] : json_decode( $params[ 'token' ], true ) ) : null;
    if ( $token ) {
      $post[ 'Token' ] = 'True';
      if ( isset( $token[ SimplePayment::CARD_NUMBER ] ) ) $post[ 'CC' ] = isset( $token[ 'token' ] ) && $token[ 'token' ] ? $token[ 'token' ] :$token[ SimplePayment::CARD_NUMBER ];
      if ( isset( $token[ SimplePayment::CARD_EXPIRY_YEAR ] ) ) $post[ 'Tyear' ] = $token[ SimplePayment::CARD_EXPIRY_YEAR ];
      if ( isset( $token[ SimplePayment::CARD_EXPIRY_MONTH ] ) ) $post[ 'Tmonth' ] = $token[ SimplePayment::CARD_EXPIRY_MONTH ];
      if ( isset( $token[ SimplePayment::CARD_OWNER_ID ] ) ) $post[ 'UserId' ] = $token[ SimplePayment::CARD_OWNER_ID ];
      if ( isset( $token[ 'reference' ] ) ) $post[ 'AuthNum' ] = $token[ 'reference' ]; // SimplePayment::REFERENCE
    }

    $status = $this->post( $this->api, $post );   
    parse_str( $status, $status );
    $response = $status;

    if ( isset( $status[ 'CCode' ] ) && $status[ 'CCode' ] == 0 && isset( $status[ 'ACode' ] ) && $status[ 'ACode' ] ) {
      $this->confirmation_code = $status[ 'ACode' ];
    }
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api . '#soft',
      'status' => isset( $status[ 'CCode' ] ) && $status[ 'CCode' ] ? $status[ 'CCode' ] : 0,
      'description' => isset( $status[ 'CCode' ] ) && $status[ 'CCode' ] ? ( isset( self::MESSAGES[ $status[ 'CCode' ] ] ) ? self::MESSAGES[ $status[ 'CCode' ] ] : $status[ 'errMsg' ] ) : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $status )
    ] );

    // Id=12852058&CCode=0&Amount=10&ACode=0012345&Fild1=freepram&Fild2=freepram&Fild3=freepram&Hesh=49&Bank=6&tashType=&Payments=2&noKPayments=1&nFirstPayment=5&firstPayment=5&TashFirstPayment=&UserId=203269535&Brand=2&Issuer=2&L4digit=0000&firstname=Israel&lastname=Israeli&info=test-api&street=levanon%203&city=netanya&zip=42361&cell=050555555555&email=testsoft%40yaad.net&Coin=1&Tmonth=04&Tyear=2020&CardName=%28%3F%3F%3F%3F%29%20Cal&errMsg= (0)
    return( $status[ 'CCode' ] == 0 ); // ? $this->confirmation_code : false 
  }

}