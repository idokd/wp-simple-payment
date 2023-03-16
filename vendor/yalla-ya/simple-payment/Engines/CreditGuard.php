<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class CreditGuard extends Engine {

  public static $name = 'CreditGuard';
  public $interactive = true;
  public $terminal = null;
  public $version = '2000';
  public $password = null;
  public $merchant = null;

  public static $supports = [ 'iframe', 'modal', 'tokenization', 'card_owner_id', 'cvv' ];

  public $api = null;

  const LANGUAGES = [ 'heb' => 'Hebrew', 'eng' => 'English' ];

  public static $domains = [
    'cgmpiuat.creditguard.co.il',
  ];

  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
    $this->sandbox = false;
    $this->username = $this->sandbox ? $this->username : $this->param( 'username' );
    $this->password = $this->sandbox ? $this->password : $this->param( 'password' );
    $this->terminal = $this->sandbox ? $this->terminal : $this->param( 'terminal' );
    $this->merchant = $this->sandbox ? $this->merchant : $this->param( 'merchant' );
    $this->api = $this->param( 'gateway' );
  }

  public function process( $params ) {
    $mode = $this->param( 'mode' );
    if ( $mode == 'redirect' ) {
      if ( isset( $params[ 'mpiHostedPageUrl' ] ) ) return( $params[ 'mpiHostedPageUrl' ] );
      throw new Exception( 'REDIRECT_URL_NOT_PROVIDED' );
    }
    if ( isset( $params[ 'status' ] ) && intval( $params[ 'status' ] ) != 0 ) {
      throw new Exception( isset( $params[ 'statusText' ] ) && $params[ 'statusText' ] ? $params[ 'statusText' ] : 'UNKOWN ERROR', $params[ 'status' ] );
    }
    // TODO: Handle standard processing without iframe
    return( $params );
  }

  public function xml2array( $xmlObject, $out = [] ) {
		foreach ( $xmlObject->children() as $index => $node ) {
			if ( !is_object ( $node ) ) $out[ $index ] = $node;
			else {
				$attributes = (array) $node->attributes();
				if ( isset( $attributes[ '@attributes' ] ) ) foreach( $attributes[ '@attributes' ] as $key => $value ) $out[ $index . ':attributes' ][ $key ] = is_array( $value ) ? $value[ 0 ] : $value;
				$out[ $index ] = $node->count() ? $this->xml2array( $node ) : dom_import_simplexml( $node )->nodeValue;;
			}
		}
		return( $out );
	}

  public function array2xml( $params ) {
    $xml = '';
    if ( !$params ) return( $xml );
    foreach ( $params as $key => $value ) {
      if ( !$value ) continue;
      if ( is_array( $value ) ) $xml .= '<' . $key . '>' . $this->array2xml( $value ) . '</' . $key . '>';
      else $xml .= '<' . $key . '>' . $value . '</' . $key . '>'; // htmlentities
    }
    return( $xml );
  }

  public function command( $command, $params = null, $version = null ) { 
    $xml = '<ashrait>
          <request>' .
                '<command>' . $command . '</command>' .
                '<dateTime>' . date( 'Y-m-d h:i:s' ) . '</dateTime>' .
                '<requestId></requestId>' .
                '<version>' . ( $version ? $version : $this->version ) . '</version>' . 
                '<language>' . $this->param( 'language' ) . '</language>' .
                '<mayBeDuplicate>' . ( $this->param( 'duplicates' ) ? 1 : 0 ) . '</mayBeDuplicate>' .
                '<' . $command . '>' . $this->array2xml( $params ) . '</' . $command . '>' .
          '</request>' .
      '</ashrait>';
      return( $xml );
  }

  public function post( $command, $vars, $version = null, $headers = null, $fail = true ) {
    // TODO: do we need?
    //$headers = [ 'Content-Type: application/x-www-form-urlencoded' ];
    $xml = $this->command( $command, $vars, $version );
    $post = [
      'user' => $this->username,
      'password' => $this->password,
      'int_in' => $xml

    ];
    $response = parent::post( $this->api, $post, $headers, $fail );
    $response = iconv( 'utf-8', 'iso-8859-8', $response );
    $data = $this->xml2array( simplexml_load_string( $response  ) );
    $this->save( [
      'transaction_id' => $this->transaction ? $this->transaction : ( isset( $response[ 'response' ][ 'doDeal' ][ 'token' ] ) ? $response[ 'response' ][ 'doDeal' ][ 'token' ] : null ),
      'url' => $this->api,
      'status' => null,
      'description' => $command,
      'request' => $xml,
      'response' => $response
    ] );
    return( $data );
  }

  public function verify( $transaction = null ) {
    $mode = $this->param( 'mode' );
    if ( $transaction ) {
      $this->transaction = $transaction[ 'transaction_id' ];
      $mode = isset( $transaction[ 'card_number' ] ) && $transaction[ 'card_number' ] ? 'direct' : $this->param( 'mode' );
    }
    $post = [];
    $post[ 'terminalNumber' ] = $this->terminal;
    if (  $mode == 'redirect' ) {
      $post[ 'queryName' ] = 'mpiTransaction'; // TODO: when it id direct, not redirect
      $post[ 'mpiTransactionId' ] = $this->transaction;
    } else {
      $post[ 'tranId' ] = $this->transaction;
    }
    $post[ 'mid' ] = $this->merchant;
    
    $response = $this->post( 'inquireTransactions', $post );
    $transaction = isset( $response[ 'response' ][ 'inquireTransactions' ][ 'row' ] ) ? $response[ 'response' ][ 'inquireTransactions' ][ 'row' ] : $response[ 'response' ][ 'inquireTransactions' ][ 'transactions' ][ 'transaction' ];
    $status = $mode == 'redirect' 
      ? isset( $transaction[ 'errorCode' ] ) && intval( $transaction[ 'errorCode' ] ) == 0
      : isset( $transaction[ 'status' ] ) && intval( $transaction[ 'status' ] ) == 0;
    $message = $mode == 'redirect' 
      ? $transaction[ 'errorText' ]
      : $transaction[ 'statusText' ];
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api,
      'status' => $mode == 'redirect' ? $transaction[ 'errorCode' ] : $transaction[ 'status' ],
      'description' => $message,
      'request' => json_encode( $post ),
      'response' => json_encode( $response )
    ] );
    if ( !$transaction || !$status ) 
      throw new Exception( $message ? $message  : 'DID_NOT_VERIFY', $status );
    $code = isset( $transaction[ 'authNumber' ] ) ? $transaction[ 'authNumber' ] : null;
    if ( $code ) {
      $this->confirmation_code = $code;
      return( $code ); 
    } 
    return( false );
  }

  public function status( $params ) {
    parent::status( $params );
    $this->transaction = $params[ 'mpiTransactionId' ];
    // cgGatewayResponseXML, amount, merchantUniqueOrderId, currency, languageCode, cgGatewayResponseCode, cgGatewayResponseText
    // personalId, cardExpiration, authNumber, creditCardToken, errorCode, errorText, cardExpiration
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => ':status', //$_SERVER[ 'REQUEST_URI' ],
      'status' => isset( $params[ 'statusCode' ] ) ? $params[ 'statusCode' ] : null,
      'description' => isset( $params[ 'statusText' ] ) ? $params[ 'statusText' ] : null,
      'request' => json_encode( $_REQUEST ),
      'response' => null
    ] );
    $this->confirmation_code = $this->verify();
    // TODO: should we use tokens here too?
    
    // TODO: do we enable recurring here?
    //if ( $params[ 'Operation' ] == 2 && isset( $params[ 'payments' ] ) && $params[ 'payments' ] == 'monthly' ) {
    //  if ( $this->param( 'recurr_at' ) == 'status' && $this->param( 'reurring' ) == 'provider' ) $this->recur_by_provider( $params );
    //}
    return( $this->confirmation_code );
  }

  public function post_process( $params ) {
    $response = $_REQUEST;
    if ( $this->param( 'mode' ) == 'redirect' ) {
      $status = !isset( $params[ 'ErrorCode' ] ) || intval( $params[ 'ErrorCode' ] ) == 0;
      $this->transaction = $params[ 'txId' ];
      $token = ( isset( $params[ 'cardToken' ] ) && $params[ 'cardToken' ] ) ? $params[ 'cardToken' ] : null;
      $owner_id = ( isset( $params[ 'personalId' ] ) && $params[ 'personalId' ] ) ? $params[ 'personalId' ] : null;
      $uniqueId = ( isset( $params[ 'uniqueID' ] ) && $params[ 'uniqueID' ] ) ? $params[ 'uniqueID' ] : null;
      $signature = hash( 'sha256', 
        $this->password . $this->transaction . ( $params[ 'ErrorCode' ] ? : '000' ) . $token . $params[ 'cardExp' ] . $owner_id . $uniqueId
      );
      $status = $status && $signature == $params[ 'responseMac' ];
      $this->save( [
        'transaction_id' => $this->transaction,
        'url' => ':signature_process',
        'status' => $status,
        'description' => isset( $params[ 'statusText' ] ) ? $params[ 'statusText' ] : null,
        'request' => json_encode( $params ),
        'response' => json_encode( $response )
      ] );
      if ( $status ) {
        $status = $this->verify();
      }
      $expiration = $params[ 'cardExp' ];
    } else {
      $status = isset( $params[ 'status' ] ) && intval( $params[ 'status' ] ) == 0;
      $token = $params[ 'cardId' ];
      $expiration = $params[ 'cardExpiration' ];
    }
    $this->confirmation_code = $params[ 'authNumber' ];
    $args = [
      'transaction_id' => $this->transaction,
      'url' => 'post_process',
      'status' => isset( $params[ 'status' ] ) ? $params[ 'status' ] : '',
      'description' => isset( $params[ 'statusText' ] ) ? $params[ 'statusText' ] : null,
      'request' => json_encode( $params ),
      'response' => $this->param( 'mode' ) == 'redirect' ? json_encode( $response ) : null,
    ];
    // TODO: should we consider the engine confirmation for keeping tokens?
    if ( $status && $token && $this->param( 'tokenize' ) ) $args[ 'token' ] = [
      'token' => $token,
      SimplePayment::CARD_OWNER_ID => isset( $owner_id ) ? $owner_id : $params[ SimplePayment::CARD_OWNER_ID ],
      SimplePayment::CARD_EXPIRY_YEAR => intval( substr( $params[ 'cardExp' ], 2, 2 ) ) + self::century(), 
      SimplePayment::CARD_EXPIRY_MONTH => substr( $params[ 'cardExp' ], 0, 2 ),
      'card_type' => $params[ 'cardBrand' ] ? $params[ 'cardBrand' ] : null  
    ];
    $this->save( $args );
    if ( !$status )
      throw new Exception( isset( $params[ 'ErrorText' ] ) && $params[ 'ErrorText' ] ? $params[ 'ErrorText' ] : 'ERROR_IN_TRANSACTION', $params[ 'ErrorCode' ] );
   
    // TODO: enable recurring payments
    //if ( $params[ 'Operation' ] == 2 && isset( $params[ 'payments' ] ) && $params[ 'payments' ] == 'monthly' ) {
    //  if ( $this->param( 'recurr_at' ) == 'post' && $this->param( 'reurring' ) == 'provider' ) $this->recur_by_provider( $params );
    //}
    return( $status ? $this->confirmation_code : false );
  }

  public function pre_process( $params ) {
    $post = [];
    $mode = $this->param( 'mode' );

    $post[ 'terminalNumber' ] = $this->terminal;
    
    // track2 if swiped
    if ( isset( $params[ 'token' ] ) && $params[ 'token' ] ) $post[ 'cardId' ] = $params[ 'token' ];
    else if ( $mode == 'redirect' ) $post[ 'cardNo' ] = 'CGMPI'; 
    else {
      $this->transaction = self::uuid();
      $post[ 'cardNo' ] = $params[ SimplePayment::CARD_NUMBER ];
      if ( isset( $params[ SimplePayment::CARD_EXPIRY_YEAR ] ) ) $post[ 'cardExpiration' ] = str_pad( $params[ SimplePayment::CARD_EXPIRY_MONTH ], 2, '0', STR_PAD_LEFT ) . ( $params[ SimplePayment::CARD_EXPIRY_YEAR ] - self::century() );
      if ( isset( $params[ SimplePayment::CARD_CVV ] ) ) $post[ 'cvv' ] = $params[ SimplePayment::CARD_CVV ];
    }
    $amount = floatval( $params[ SimplePayment::AMOUNT ] ) * 100;
    $post[ 'total' ] = $amount;
    $post[ 'transactionType' ] = 'Debit'; // Credit
    $post[ 'currency' ] = isset( $params[ SimplePayment::CURRENCY ] ) ? $params[ SimplePayment::CURRENCY ] : $this->param( SimplePayment::CURRENCY );
    $post[ 'transactionCode' ] = $this->param( 'operation' );

    $subscription = static::is_subscription( $params );
    if ( $subscription ) { // && $this->param( 'reurring' ) == 'provider'
      $post[ 'transactionType' ] = 'RecurringDebit';
      $post[ 'recurringTotalNo' ] = 'RecurringDebit';
      $post[ 'recurringTotalSum' ] = 'RecurringDebit';
      $post[ 'recurringFrequency' ] = $subscription;
      $post[ 'total' ] = $amount ? : 100; // 1 NIS for creating verification/tokenization
      $post[ 'creditType' ] = 'RegularCredit';
    } else if ( isset( $params[ SimplePayment::PAYMENTS ] ) && is_numeric( $params[ SimplePayment::PAYMENTS ] ) && $params[ SimplePayment::PAYMENTS ] > 1 ) {
      $post[ 'creditType' ] = $this->param( 'credit' ) ? 'IsraCredit' : 'Payments';
      $post[ 'numberOfPayments' ] = $post[ 'creditType' ] == 'Payments' ? $params[ SimplePayment::PAYMENTS ] : $params[ SimplePayment::PAYMENTS ];
      if ( !$this->param( 'credit' ) ) {
        $first = false;
        if ( $first ) {       
          $each = $amount / $params[ SimplePayment::PAYMENTS ];
          $post[ 'firstPayment' ] = $each; // round
          $post[ 'periodicalPayment' ] = $each; // round
          $post[ 'numberOfPayments' ] -= 1;
        }
      }
    } else {
      $post[ 'creditType' ] = 'RegularCredit'; 
    }
    // authNumber
    
    if ( $mode == 'redirect' ) {
      $post[ 'description' ] = $params[ SimplePayment::PRODUCT ];
      $post[ 'validation' ] = 'TxnSetup';
      $post[ 'mid' ] = $this->merchant;
      $post[ 'mpiValidation' ] = $this->param( 'validation' ); 
      $post[ 'successUrl' ] = htmlentities( $this->url( SimplePayment::OPERATION_SUCCESS, $params ) );
      $post[ 'errorUrl' ] = htmlentities( $this->url( SimplePayment::OPERATION_ERROR, $params ) );
      $post[ 'cancelUrl' ] = htmlentities( $this->url( SimplePayment::OPERATION_CANCEL, $params ) );
      $post[ 'uniqueid' ] = self::uuid();
    } else {
      $post[ 'validation' ] = $this->param( 'validation' ); // TODO: determine the validation via parameter
      
    }
    if ( isset( $params[ SimplePayment::EMAIL ] ) ) $post[ 'email' ] = $params[ SimplePayment::EMAIL ];

    /*$post[ 'customerData' ] = [

    ];*/
      // description

    // 
    // mainTerminalNumber, slaveTerminalNumber
    // dealerNumber
    if ( isset( $params[ 'payment_id' ] ) && $params[ 'payment_id' ] ) $post[ 'user' ] = $params[ 'payment_id' ];
    if ( isset( $params[ SimplePayment::CARD_OWNER_ID ] ) ) $post[ 'id' ] = $params[ SimplePayment::CARD_OWNER_ID ];

    // paymentsInterest

    // addonData, cavv, eci, xid, shiftId1, shiftId2, shiftId3, shiftTxnDate, 
    // cgUid 
    // customerData
      // routeCode
      // userData1..10
    // ashraitEmvData
      // paymentsIndexType, offerCode, deferMonths, dueDate, ipayCode

      //$this->param('tokenize') ? true : false;
    // TODO: implement 
    // paymentPageData
    // useId, useCvv, customStyle, customText, iframeAnchestor
    
    $response = $this->post( 'doDeal', $post );
    $this->transaction = $mode == 'redirect' ? ( isset( $response[ 'response' ][ 'doDeal' ][ 'token' ] ) ? $response[ 'response' ][ 'doDeal' ][ 'token' ] : $this->transaction ) : $response[ 'response' ][ 'tranId' ];
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api,
      'status' => isset( $response[ 'response'][ 'result' ] ) ? $response[ 'response'][ 'result' ] : '',
      'description' => isset( $response[ 'response'][ 'message' ] ) ? $response[ 'response'][ 'message' ] : '',
      'request' => json_encode( $post ),
      'response' => json_encode( $response )
    ] );
    if ( isset( $response[ 'response'][ 'result' ] ) && intval( $response[ 'response'][ 'result' ] ) != 0 ) {
      throw new Exception( isset( $response[ 'response'][ 'message' ] ) && $response[ 'response'][ 'message' ] ? $response[ 'response'][ 'message' ] : 'REDIRECT_URL_NOT_PROVIDED', $response[ 'response'][ 'result' ] );
    }
    return( $response[ 'response'][ 'doDeal' ] );
  }


  public static function is_subscription( $params ) {
    if ( !isset( $params[ 'payments' ] ) ) return( false );
    $period = false;
    switch( $params[ 'payments' ] ) {
      case 'yearly':
        $period = 52;
        break;
      case 'quarterly':
        $period = 14;
        break;
      case 'semesterly':
        $period = 26;
        break;	
      case 'monthly':
        $period = 4;
    }
    return( $period );
  }

}