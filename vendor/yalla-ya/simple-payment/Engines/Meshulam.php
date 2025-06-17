<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;

if ( !defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class Meshulam extends Engine {

  public static $name = 'Meshulam';
  public $interactive = true;
  protected $recurrAt = 'status'; // status, post, provider

  public static $supports = [ 'iframe', 'modal', 'tokenization' ];

  public static $methods = [ 'cc', 'bit' ];

  public static $domains = [
    'secure.meshulam.co.il',
    'sandbox.meshulam.co.il'
  ];

  public $api = [
    'live' => [
      'createPaymentProcess' => 'https://secure.meshulam.co.il/api/light/server/1.0/createPaymentProcess',
      'getPaymentProcessInfo' => 'https://secure.meshulam.co.il/api/light/server/1.0/getPaymentProcessInfo',
      'approveTransaction' => 'https://secure.meshulam.co.il/api/light/server/1.0/approveTransaction',
      'getTransactionInfo' => 'https://secure.meshulam.co.il/api/light/server/1.0/getTransactionInfo',
      'refundTransaction' => 'https://secure.meshulam.co.il/api/light/server/1.0/refundTransaction',
      'createTransactionWithToken' => 'https://secure.meshulam.co.il/api/light/server/1.0/createTransactionWithToken',
      'settleSuspendedTransaction' => 'https://secure.meshulam.co.il/api/light/server/1.0/settleSuspendedTransaction',
    ],
    'sandbox' => [
      'createPaymentProcess' => 'https://sandbox.meshulam.co.il/api/light/server/1.0/createPaymentProcess',
      'getPaymentProcessInfo' => 'https://sandbox.meshulam.co.il/api/light/server/1.0/getPaymentProcessInfo',
      'approveTransaction' => 'https://sandbox.meshulam.co.il/api/light/server/1.0/approveTransaction',
      'getTransactionInfo' => 'https://sandbox.meshulam.co.il/api/light/server/1.0/getTransactionInfo',
      'refundTransaction' => 'https://sandbox.meshulam.co.il/api/light/server/1.0/refundTransaction',
      'createTransactionWithToken' => 'https://sandbox.meshulam.co.il/api/light/server/1.0/cancel-subscription',
      'settleSuspendedTransaction' => 'https://sandbox.meshulam.co.il/api/light/server/1.0/settleSuspendedTransaction',
    ]
  ];

  public $username;
  public $password;

  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
    $this->username = $this->param( 'username' );
    $this->password = $this->param( 'password' );
    $this->api = $this->api[ $this->sandbox ? 'sandbox' : 'live' ];
  }

  public function process( $params ) {
    return( $params[ 'url' ] );
  }

  public function verify( $transaction = null ) {
    if ( $transaction ) $this->transaction = $transaction[ 'transaction_id' ];
    $ids = explode( '-', $this->transaction );
    $method = isset( $ids[ 2 ] ) ? $ids[ 2 ] : 2;
    switch( $method ) {
      case 1:
        $password = $this->param( 'subscriptions' );
        break;
      case 3:
        $password = $this->param( 'bit' );
        break;
      default:
        $password = $this->password;
    }
    $params = [
      'pageCode' => $password, // TODO: check if method = bit/direct debit to validate bit transactions
      'processId' => $ids[ 0 ],
      'processToken' => $ids[ 1 ],
    ];
    $status = $this->post( $this->api[ 'getPaymentProcessInfo' ], $params ); 
    $status = json_decode( $status, true );
    $response = $status[ 'data' ];
    $data = isset( $response[ 'transactions' ] ) ? $response[ 'transactions' ][ 0 ] : null;
    if ( isset( $data[ 'asmachta' ] ) && $data[ 'asmachta' ] ) {
      $this->confirmation_code = $data[ 'asmachta' ];
    }
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api[ 'getPaymentProcessInfo' ],
      'status' => isset( $data[ 'statusCode' ] ) ? $data[ 'statusCode' ] : $status[ 'status' ],
      'description' => isset( $data[ 'status' ] ) ? $data[ 'status' ] : null,
      'request' => json_encode( $params ),
      'response' => json_encode( $status )
    ] );

    if ( $status[ 'status' ] != 1 ) 
      throw new Exception( isset( $status[ 'err' ] ) ? $status[ 'err' ][ 'message' ] : $status[ 'status' ], $status[ 'err' ][ 'id' ] );
      
    if ( $this->confirmation_code ) {
      return( $this->confirmation_code );
    }
    return( false );
  }

  public function status( $params ) {
    parent::status( $params );
    $info = $params[ 'data' ];
    $this->transaction = $this->trans_id( $info );
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
    $payment_id = $_REQUEST[ 'cField1' ];
    $this->transaction = $params[ 'transaction_id' ];
    $response = $_REQUEST;
    $this->save( [
      'url' => ':post_process',
      'status' => isset( $response[ 'response' ] ) && $response[ 'response' ] == 'success' ? true : false,
      'description' => $response[ 'response' ],
      'request' => json_encode( $params ),
      'response' => json_encode( $response )
    ] );
    $this->confirmation_code = $this->verify();
    return( $this->confirmation_code );
  }

  public function pre_process( $params ) {
    $currency = isset( $params[ SimplePayment::CURRENCY ] ) && $params[ SimplePayment::CURRENCY ] ? $params[ SimplePayment::CURRENCY ] : $this->param( 'currency' );
    $method = 2;
    if ( isset( $params[ 'payments' ] ) && $params[ 'payments' ] ) {
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
    }
    if ( isset( $params[ SimplePayment::METHOD ] ) && strtolower( $params[ SimplePayment::METHOD ] ) == 'bit' ) $method = 3;
    $post = [
      'userId' => $this->username,
      'pageCode' => isset( $params[ SimplePayment::METHOD ] ) && strtolower( $params[ SimplePayment::METHOD ] ) == 'bit' ? $this->param( 'bit' ) : $this->password,
      'description' => $params[ 'product' ],
      'sum' => $params[ 'amount' ],
      'successUrl' => $this->url( SimplePayment::OPERATION_SUCCESS, $params ),
      'cancelUrl' => $this->url( SimplePayment::OPERATION_CANCEL, $params ),
    ];

    if ( isset( $params[ 'apikey' ] ) && $params[ 'apikey' ] ) {
      $post[ 'apiKey' ] = $params[ 'apikey' ];
    }

    if ( isset( $params[ 'comission' ] ) && $params[ 'comission' ] ) {
      $post[ 'companyCommission' ] = $params[ 'comission' ];
    }
    // TODO: check payments && tokenize
    if ( isset( $params[ 'tokenize' ] ) && $params[ 'tokenize' ] ) {
      $post[ 'saveCardToken' ] = $params[ 'tokenize' ];
    }

    if ( isset( $params[ 'payment_id' ] ) && $params[ 'payment_id' ] ) $post[ 'cField1' ] = $params[ 'payment_id' ];
  
    if ( isset( $params[ SimplePayment::FULL_NAME ]) && $params[SimplePayment::FULL_NAME]) $post[ 'pageField[fullName]' ] = strpos( ' ', $params[ SimplePayment::FULL_NAME ] ) === false ? $params[ SimplePayment::FULL_NAME ] . ' .' : $params[ SimplePayment::FULL_NAME ];
    if ( isset( $params[ SimplePayment::MOBILE ] ) && $params[ SimplePayment::MOBILE ]) $post[ 'pageField[phone]' ] = preg_replace( '/\D/', '', $params[ SimplePayment::MOBILE ] );
    if ( isset( $params[ SimplePayment::PHONE ] ) && $params[ SimplePayment::PHONE ]) $post[ 'pageField[phone]' ] = preg_replace('/\D/', '', $params[ SimplePayment::PHONE ] );
    if ( isset( $params[ SimplePayment::EMAIL ] ) && $params[ SimplePayment::EMAIL ]) $post[ 'pageField[email]' ] = $params[ SimplePayment::EMAIL ];
    if ( isset( $params[ SimplePayment::COMPANY ] ) && $params[ SimplePayment::COMPANY ]) $post[ 'pageField[invoiceName]' ] = $params[ SimplePayment::COMPANY ];


    $status = $this->post( $this->api[ 'createPaymentProcess' ], $post ); 
    $status = json_decode( $status, true );
    $response = $status[ 'data' ];
    $this->transaction = $this->transaction ? : $this->trans_id( array_merge( [ 'transactionTypeId' => $method ], $response ) );

    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api[ 'createPaymentProcess' ],
      'status' => isset( $status[ 'status' ] ) && $status[ 'status' ] ? $status[ 'status' ] : $status[ 'err' ][ 'id' ],
      'description' => isset( $status[ 'err' ] ) && isset( $status[ 'err' ][ 'message' ] ) ? $status[ 'err' ][ 'message' ] : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $status )
    ] );
    if ( isset( $status[ 'status' ] ) && $status[ 'status' ] == 0 ) {
      throw new Exception( $status[ 'err' ][ 'message' ], intval( $status[ 'err' ][ 'id' ] ) );
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

  function refund( $params, $transaction_id = null ) {
    // TODO: finish the params values
    $this->transaction = $transaction_id;
    $params = [
      'pageCode' => $this->password,
      'transactionId' => '',
      'transactionToken' => '',
      'refundSum' => 0,
      'stopDirectDebit' => 0
    ];
    $status = $this->post( $this->api[ 'refundTransaction' ], $params );
    $status = json_decode( $status, true );
    $response = $status[ 'data' ];
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api[ 'refundTransaction' ],
      'status' => isset( $status[ 'status' ] ) && $status[ 'status' ] ? $status[ 'status' ] : $status[ 'err' ][ 'id' ],
      'description' => isset( $status[ 'err' ] ) && isset( $status[ 'err' ][ 'message' ] ) ? $status[ 'err' ][ 'message' ] : null,
      'request' => json_encode( $post ),
      'response' => json_encode( $status )
    ] );
    return( $status[ 'status' ] == 1 );
  }

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

  public function trans_id( $params ) { 
    return( $params[ 'processId' ] . '-' . $params[ 'processToken' ] . ( isset( $params[ 'transactionTypeId' ] ) ?  '-' . $params[ 'transactionTypeId' ] : '' ) );
  }
/*
  public function subscriptions( $params = [] ) {
    $post = [
      'seller_payme_id' => $this->password,
    ];
    $response = $this->post( $this->api[ 'get-subscriptions' ], json_encode( $post ), [ 'Content-Type: application/json' ]);
    $response = json_decode( $response, true );
    if ( isset( $response[ 'status_code' ] ) && $response[ 'status_code' ] != 0 ) {
      throw new Exception( $status[ 'status_error_details' ], $status[ 'status_error_code' ] );
      // status_additional_info
    }
    return( $response[ 'items' ] );
  }
*/
  /*
  public function recur_by_provider($params) {
    $post = [];
    
    if ($this->param('recurring_terminal')) $post['RecurringPayments.ChargeInTerminal'] = $this->param('recurring_terminal');
    $post['Operation'] = $this->param('reurring_operation');
    $post['LowProfileDealGuid'] = isset($params['lowprofilecode']) ? $params['lowprofilecode'] : $params['transaction_id'];
    
    if ($this->param('department_id')) $post['RecurringPayments.DepartmentId'] = $this->param('department_id');
    if ($this->param('site_id')) $post['Account.SiteUniqueId'] = $this->param('site_id');
    
    if (isset($params['payment_id']) && $params['payment_id']) $post['RecurringPayments.ReturnValue'] = $params['payment_id'];

    $post['RecurringPayments.FlexItem.Price'] = $params['amount'];
    $post['RecurringPayments.FlexItem.InvoiceDescription'] = isset($params['product']) ? $params['product'] : $params['concept'];
    $post['RecurringPayments.InternalDecription'] = isset($params['product']) ? $params['product'] : $params['concept'];
    
    if (isset($params[SimplePayment::CARD_OWNER]) && $params[SimplePayment::CARD_OWNER]) {
      $post['Account.ContactName'] = $params[SimplePayment::CARD_OWNER];
    }
    if (!isset($post['CardOwnerName']) && isset($params['full_name']) && $params['full_name']) {
      $post['Account.ContactName'] = $params['full_name']; // card_holder
    }

    if (isset($params['first_name']) && $params['first_name']) $post['Account.FirstName'] = $params['first_name'];

    if (isset($params['phone']) && $params['phone']) $post['Account.PhLine'] = $params['phone'];
    if (isset($params['mobile']) && $params['mobile']) $post['Account.PhMobile'] = $params['mobile'];
    if (isset($params['email']) && $params['email']) $post['Account.Email'] = $params['email'];

    if (isset($params['address']) && $params['address']) $post['Account.Street1'] = $params['address'];
    if (isset($params['address2']) && $params['address2']) $post['Account.Street2'] = $params['address2'];
    if (isset($params['zipcode']) && $params['zipcode']) $post['Account.ZipCode'] = $params['zipcode'];
    if (isset($params['city']) && $params['city']) $post['Account.City'] = $params['city'];

    if (isset($params['comment']) && $params['comment']) $post['Account.Comments'] = $params['comment'];

    if (isset($params['tax_id']) && $params['tax_id']) $post['Account.RegisteredBusinessNumber'] = $params['tax_id'];

    if ($this->param('vat_free')) $post['Account.VatFree'] = 'true';

    $language = isset($params['language']) ? $params['language'] : $this->param('language');
    if ($language != '') $post['Account.IsDocumentLangEnglish'] = $language == 'he' ? 'false' : 'true';

    $currency = isset($params['currency']) && $params['currency'] ? $params['currency'] : $this->param('currency');
    if ($currency != '') {
      if ($currency = self::CURRENCIES[$currency]) $post['RecurringPayments.FinalDebitCoinId'] = $currency;
      else throw new Exception('CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500);
    }

    $post['codepage'] = 65001; // Codepage fixed to enable hebrew
    // month from now 28 days
    $date = new DateTime();
    $date->add(new DateInterval('P28D')); // P1D means a period of 28 day
    $post['RecurringPayments.NextDateToBill'] = $date->format('d/m/Y');

    $limit = $this->param('recurring_total');
    $post['RecurringPayments.TotalNumOfBills'] = $limit ? : 999999;

    $interval = $this->param('recurring_interval');
    if ($interval) $post['TimeIntervalId'] = $interval;
  
    $docType = $this->param('doc_type');
    if ($docType != '') $post['RecurringPayments.DocTypeToCreate'] = $docType;

    $post['RecurringPayments.FlexItem.IsPriceIncludeVat'] = 'true'; // Must be true - API requirement
    // TODO: assure to verifiy first_name / lasstname or use full name
    if (isset($params['full_name']) && trim($params['full_name'])) $post['Account.CompanyName'] = trim($params['full_name']);
    if (!isset($post['Account.CompanyName']) && isset($params['first_name']) && trim($params['first_name'])) $post['Account.CompanyName'] = trim($params['first_name'].(isset($params['last_name']) ? ' '.$params['last_name'] : ''));
    if (!isset($post['Account.CompanyName']) && isset($params['last_name']) && trim($params['last_name'])) $post['Account.CompanyName'] = trim($params['last_name']);
    if (isset($params['tax_id']) && trim($params['tax_id'])) $post['Account.CompanyName'] = trim($params['tax_id']);
    // Not in use:
    //  Account.AccountId	, 
    //  Account.DontCheckForDuplicate	RecurringPayments.RecurringId
    // Account.ForeignAccountNumber	, RecurringPayments.IsActive	
    // BankInfo.Bank	 BankInfo.Branch	BankInfo.AccountNumber	 BankInfo.Description	
    $status = $this->post($this->api['recurring_request'], $post);
    parse_str($status, $status);
    $response = $status;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['recurring_request'],
      'status' => isset($response['ResponseCode']) ? $response['ResponseCode'] : $response['response_code'],
      'description' => isset($response['Description']) ? $response['Description'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
    return($status); // OperationResponseText, OperationResponse
  }

  public function recur() {
    $post = [];
   
    $post['TokenToCharge.SumToBill'] = $params['amount'];

    $currency = isset($params[SimplePayment::CURRENCY]) && $params[SimplePayment::CURRENCY] ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    if ($currency != '') {
      if ($currency = self::CURRENCIES[$currency]) $post['CoinID'] = $currency;
      else throw new Exception('CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500);
    }

    $post['TokenToCharge.CoinID'] = $currency;

    $language = $this->param('language');
    if ($language != '') $post['Language'] = $language;

    //  TokenToCharge.CardOwnerName
    // TokenToCharge.Token, TokenToCharge.CardValidityMonth
    // TokenToCharge.CardValidityYear
    // TokenToCharge.IdentityNumber

    $post['TokenToCharge.RefundInsteadOfCharge'] = 'false';
    $post['TokenToCharge.IsAutoRecurringPayment'] = 'true';

    if (isset($params['approval_number']) && $params['approval_number']) $post['TokenToCharge.ApprovalNumber'] = $params['approval_number'];



    $status = $this->post($this->api['payment_recur'], $post);
    parse_str($status, $status);
    $response = $status;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['payment_recur'],
      'status' => isset($response['ResponseCode']) ? $response['ResponseCode'] : $response['response_code'],
      'description' => isset($response['Description']) ? $response['Description'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
    // Not in use:
    // TokenToCharge.Salt, TokenToCharge.SumInStars, TokenToCharge.NumOfPayments
    // TokenToCharge.ExtendedParameters,  TokenToCharge.SapakMutav
    //  TokenToCharge.TokenCompanyUserName,  TokenToCharge.TokenCompanyPassword
    //  TokenToCharge.FirstPaymentSumAgorot, TokenToCharge.ConstPaymentAgorot
    // TokenToCharge.JParameter
    // TokenToCharge.UniqAsmachta
    // TokenToCharge.AvsCity, TokenToCharge.AvsAddress, TokenToCharge.AvsZip
  }
  */

}
