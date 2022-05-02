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

  public $name = 'CreditGuard';
  public $interactive = true;
  public $terminal = null;
  public $version = '2000';
  public $password = null;

  public static $supports = [ 'iframe', 'modal', 'tokenization' ];

  public $api = null;

  const LANGUAGES = [ 'heb' => 'Hebrew', 'eng' => 'English' ];

  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
    $this->sandbox = false;
    $this->username = $this->sandbox ? $this->username : $this->param( 'username' );
    $this->password = $this->sandbox ? $this->password : $this->param( 'password' );
    $this->api =  $this->param( 'gateway' );
  }

  public function process( $params ) {

    // TODO: Handle standard processing without iframe
    return( $params[ 'doDeal' ][ 'mpiHostedPageUrl' ] );
  }

  public function xml2array( $xmlObject, $out = [] ) {
    foreach ( ( array ) $xmlObject as $index => $node )
        $out[$index] = ( is_object ( $node ) ) ? $this->xml2array ( $node ) : $node;
    return( $out );
  }

  public function array2xml( $params ) {
    $xml = '';
    if ( !$params ) return( $xml );
    foreach ( $params as $key => $value ) {
      if ( !$value ) continue;
      if ( is_array( $value ) ) $xml .= '<' . $key . '>' . $this->array2xml( $value ) . '</' . $key . '>';
      else $xml .= '<' . $key . '>' . $value . '</' . $key . '>';
    }
    return( $xml );
  }

  public function command( $command, $params = null, $version = null ) {  
    $xml = 'user=' . $this->username . '&password= ' . $this->password . '&int_in=<ashrait>
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
    $headers = [ 'Content-Type: application/x-www-form-urlencoded' ];
    $xml = $this->command( $command, $vars, $version );
    $response = parent::post( $this->api, $xml, $headers, $fail );
    $response = iconv( 'utf-8', 'iso-8859-8', $response );
    return( $this->xml2array( simplexml_load_string( $response ) ) );
  }

  public function verify( $id ) {
    $this->transaction = $id;
    $inquire = [];
    $post[ 'terminalNumber' ] = $this->param( 'terminal' );
    $post[ 'queryName' ] = 'mpiTransaction';
    $post[ 'mid' ] = $this->param( 'supplier' );
    $post[ 'mpiTransactionId' ] = $this->transaction;
    $this->post( 'inquireTransactions', $post );
    
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api,
      'status' => isset( $response[ 'Status' ] ) ? $response[ 'Status' ] : null,
      'description' => isset( $response[ 'DebugMessage' ] ) ? $response[ 'DebugMessage' ] : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $response )
    ] );
    $code = isset( $response[ 'AuthNum' ] ) ? $response[ 'AuthNum' ] : null;
    if ( $code ) {
      $this->confirmation_code = $code;
      return( $code ); 
    } 
    throw new Exception( isset( $response[ 'Status' ] ) ? $response[ 'Status' ] : 'DID_NOT_VERIFY', $code );
  }

  public function status( $params ) {
    parent::status( $params );
    $this->transaction = $params['Token'];
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $_SERVER[ 'REQUEST_URI' ],
      'status' => isset( $params[ 'Status' ] ) ? $params[ 'Status' ] : null,
      'description' => isset( $params[ 'Status' ] ) ? $params[ 'Status' ] : null,
      'request' => json_encode( $_REQUEST ),
      'response' => null
    ] );
    $post = [];

    $post['SaleId'] = $this->transaction;
    $response = $this->post($this->api['sale-details'], json_encode($post), [ 'Content-Type: application/json' ]);
    $response = json_decode($response, true);

    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['sale-details'],
      'status' => isset($response['Status']) ? $response['Status'] : null,
      'description' => isset($response['Status']) ? $response['Status'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
    if (!isset($response['Status']) || $response['Status'] != 'VERIFIED') {
      throw new Exception(isset($response['Status']) ? $response['Status'] : 'UNKOWN_ERROR');
    }

    $post = [];
    $post['GroupPrivateToken'] = $this->password;
    $post['SaleId'] = $this->transaction;
    $post['TotalAmount'] = $response['Amount'];

    $response = $this->post($this->api['sale-details'], json_encode($post), [ 'Content-Type: application/json' ]);
    $response = json_decode($response, true);

    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api['sale-details'],
      'status' => isset($response['Status']) ? $response['Status'] : null,
      'description' => isset($response['DebugMessage']) ? $response['DebugMessage'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response)
    ] );
    if (!isset($response['Status']) || $response['Status'] != 'VERIFIED') {
      throw new Exception(isset($response['Status']) ? $response['Status'] : 'UNKOWN_ERROR');
    }

    $this->confirmation_code = $response['AuthNum'];

    // if token found, fetch token info...
    if (isset($response['Token']) && $response['Token'] != '00000000-0000-0000-0000-000000000000') {
      $post = [
        'Token' => $response['Token'],
        'CreditboxToken'=> $this->box,
      ];
      $response = $this->post($this->api['sale-details'], json_encode($post), [ 'Content-Type: application/json' ]);
      $response = json_decode($response, true);
      $this->save([
        'transaction_id' => $this->transaction,
        'url' => $this->api['sale-details'],
        'status' => isset($response['Status']) ? $response['Status'] : null,
        'description' => isset($response['DebugMessage']) ? $response['DebugMessage'] : null,
        'request' => json_encode($post),
        'response' => json_encode($response)
      ]);
    }

    //if ($params['Operation'] == 2 && isset($params['payments']) && $params['payments'] == "monthly") {
    //  if ($this->param('recurr_at') == 'status' && $this->param('reurring') == 'provider') $this->recur_by_provider($params);
    //}
    return($response['AuthNum']);
  }

  public function post_process( $params ) {
    $this->transaction = $_REQUEST[ 'Token' ];
    $response = $_REQUEST;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => ':post_process',
      'status' => isset($response['Status']) ? $response['Status'] : '',
      'description' => isset($response['DebugMessage']) ? $response['DebugMessage'] : null,
      'request' => json_encode($params),
      'response' => json_encode($response)
    ]);

    $post = [];
    $post['GroupPrivateToken'] = $this->password;
    $post['SaleId'] = $this->transaction;
    $post['TotalAmount'] = $params['amount'];

    $response = $this->post($this->api['verify'], json_encode($post), [ 'Content-Type: application/json' ]);
    $response = json_decode($response, true);

    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['verify'],
      'status' => isset($response['Status']) ? $response['Status'] : null,
      'description' => isset($response['DebugMessage']) ? $response['DebugMessage'] : $response['Status'],
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
    if (!isset($response['Status']) || $response['Status'] != 'VERIFIED') {
      // Do not fail if not verified
      throw new Exception(isset($response['Status']) ? $response['Status'] : 'UNKOWN_ERROR');
    }

    //if ($params['Operation'] == 2 && isset($params['payments']) && $params['payments'] == "monthly") {
    //  if ($this->param('recurr_at') == 'post' && $this->param('reurring') == 'provider') return($this->recur_by_provider($params));
    //}
    return( $code );
  }

  public function pre_process( $params ) {
    $post = [];
    $mode = $this->param( 'mode' );

    $post[ 'terminalNumber' ] = $this->param( 'terminal' );
    
    // track2 if swiped
    if ( isset( $params[ 'token' ] ) && $params[ 'token' ] ) $post[ 'cardId' ] = $params[ 'token' ];
    else if ( $mode == 'redirect' ) $post[ 'cardNo' ] = 'CGMPI'; 
    else {
      $post[ 'cardNo' ] = $param[ SimplePayment::CARD_NUMBER ];
      if ( isset( $params[ SimplePayment::CARD_EXPIRY_YEAR ] ) ) $post[ 'cardExpiration' ] = str_pad( $params[ SimplePayment::CARD_EXPIRY_MONTH ], 2, "0", STR_PAD_LEFT ) . ( $param[ SimplePayment::CARD_EXPIRY_YEAR ] - 2000 );
      if ( isset( $params[ SimplePayment::CARD_CVV ] ) ) $post[ 'cvv' ] = $param[ SimplePayment::CARD_CVV ];
    }
    $amount = floatval( $params[ SimplePayment::AMOUNT ] ) * 100;
    $post[ 'total' ] = $amount;
    $post[ 'transactionType' ] = 'Debit'; // Credit, RecurringDebit
    $post[ 'currency' ] = isset( $params[ SimplePayment::CURRENCY ] ) ? $params[ SimplePayment::CURRENCY ] : $this->param( SimplePayment::CURRENCY );
    $post[ 'transactionCode' ] = $this->param( 'operation' );

    if ( isset( $params[ SimplePayment::PAYMENTS ] ) && is_numeric( $params[ SimplePayment::PAYMENTS ] ) && $params[ SimplePayment::PAYMENTS ] > 1 ) {
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
      $post[ 'mid' ] = $this->param( 'merchant' );
      $post[ 'mpiValidation' ] = 'AutoComm'; // TODO: determine the validation via parameter
      $post[ 'successUrl' ] = $this->url( SimplePayment::OPERATION_SUCCESS, $params );
      $post[ 'errorUrl' ] = $this->url( SimplePayment::OPERATION_ERROR, $params );
     // $post[ 'IndicatorUrl' ] = $this->url( SimplePayment::OPERATION_STATUS, $params );
    } else {
      $post[ 'validation' ] = 'TxnSetup'; // TODO: determine the validation via parameter
    }
    $post[ 'uniqueid' ] = self::uuid();
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

    // paymentPageData

    $response = $this->post( 'doDeal', $post );
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
    return( $mode == 'redirect' ? $response[ 'response'][ 'doDeal' ][ 'mpiHostedPageUrl' ] : $response[ 'response'][ 'doDeal' ] );
    /* 
    $xml_to_send = "user={$user_name}&password={$pass}&int_in=<ashrait>
						   <request>
							" . ( $this->ott_options[ 'enable_emv' ] == 'on' ? '<version>2000</version>' : '<version>1000</version>' ) . "			
				<language>HEB</language>
							<dateTime></dateTime>
							<command>doDeal</command>
							<doDeal>
								 <terminalNumber>{$terminal}</terminalNumber>
								 <mainTerminalNumber/>
								 <cardNo>CGMPI</cardNo>
								 <total>{$sum}</total>
								 <transactionType>Debit</transactionType>
								 <creditType>{$payment_type}</creditType>
								 <currency>ILS</currency>
								 <transactionCode>" . ( $this->ott_options[ 'enable_emv' ] == 'on' ? 'Phone' : 'Phone' ). "</transactionCode>
								 <paymentPageData><useCvv>1</useCvv><useId>1</useId></paymentPageData>
								 <authNumber/>
								 <numberOfPayments>{$num_of_payments}</numberOfPayments>
								 <firstPayment/>
								 <periodicalPayment/>
								 <validation>TxnSetup</validation>
								 <dealerNumber/>
								 <user>{$unique_order_id}</user>
								 <mid>{$mid}</mid>
								 <uniqueid>".uniqid()."</uniqueid>
								 <mpiValidation>autoComm</mpiValidation>
								 <successUrl>{$this->ipn_redirect}</successUrl><errorUrl>{$this->ipn_redirect}</errorUrl>
								 <clientIP/>
								 <customerData>
								  <userData1>{$this->order_id}</userData1>
								 </customerData>
							</doDeal>
						   </request>
						  </ashrait>";
    */
    /*$postData = array(
      'Custom1'=>$order->id,
      'Custom2'=>$wpml_token,
      'Custom3'=>$ipn_integration,
      'Custom4'=>get_current_user_id(),
      'Custom5'=>$_POST['wc-icredit_payment-new-payment-method'],
    );*/
  }

}