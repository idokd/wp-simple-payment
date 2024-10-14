<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class PayMe extends Engine {

  public static $name = 'PayMe';
  public $interactive = true;
  protected $recurrAt = 'post'; // status

  public static $supports = [ 'iframe', 'modal', 'tokenization' ];

  // TODO: validate those domains
  public static $domains = [
    'live.payme.io',
    'sandbox.payme.io'
  ];

  public $api = [
    'live' => [
      'capture-sale' => 'https://live.payme.io/api/capture-sale',
      'generate-sale' => 'https://live.payme.io/api/generate-sale',
      'refund-sale' => 'https://live.payme.io/api/refund-sale',
      'get-sales' => 'https://live.payme.io/api/get-sales',
      'generate-subscription' => 'https://live.payme.io/api/generate-subscription',
      'cancel-subscription' => 'https://live.payme.io/api/cancel-subscription',
      'get-subscriptions' => 'https://live.payme.io/api/get-subscriptions',
    ],
    'sandbox' => [
      'capture-sale' => 'https://sandbox.payme.io/api/capture-sale',
      'generate-sale' => 'https://sandbox.payme.io/api/generate-sale',
      'refund-sale' => 'https://sandbox.payme.io/api/refund-sale',
      'get-sales' => 'https://sandbox.payme.io/api/get-sales',
      'generate-subscription' => 'https://sandbox.payme.io/api/generate-subscription',
      'cancel-subscription' => 'https://sandbox.payme.io/api/cancel-subscription',
      'get-subscriptions' => 'https://sandbox.payme.io/api/get-subscriptions',
    ]
  ];

  public $password = 'XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX';

  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
    // payme_client_key, payme_merchant_secret
    //$this->password = $this->sandbox ? $this->password : $this->param( 'password' );
    $this->api = $this->api[ $this->sandbox ? 'sandbox' : 'live' ];
  }

  public function process($params) {
    $url = $params['url'];
    $qry = [];
    if (isset($params[SimplePayment::FIRST_NAME]) && strpos($url, 'first_name') === false) $qry['first_name'] = $params[SimplePayment::FIRST_NAME];
    if (isset($params[SimplePayment::LAST_NAME]) && strpos($url, 'last_name') === false) $qry['last_name'] = $params[SimplePayment::LAST_NAME];
    if (isset($params[SimplePayment::PHONE]) && strpos($url, 'phone') === false) $qry['phone'] = $params[SimplePayment::PHONE];
    if (isset($params[SimplePayment::EMAIL]) && strpos($url, 'email') === false) $qry['email'] = $params[SimplePayment::EMAIL];
    if (isset($params[SimplePayment::CARD_OWNER_ID]) && strpos($url, 'social_id') === false) $qry['social_id'] = $params[SimplePayment::CARD_OWNER_ID];
    return($url.(strpos($url, '?') ? '&' : '?').($qry ? '&'.http_build_query($qry) : ''));
  }

  public function verify($id) {
    $this->transaction = $id;
    $json = [
      'seller_payme_id' => $this->password,
      'sale_payme_id' => $this->transaction
    ];
    $response = $this->post($this->api['get-sales'], json_encode($post), [ 'Content-Type: application/json' ]);
    $response = json_decode($response, true);
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['get-sales'],
      'status' => isset($response['status_code']) ? $response['status_code'] :  $response['status_code'],
      'description' => isset($response['status_error_details']) ? $response['status_error_details'] : $response['status_error_details'],
      'request' => json_encode($json),
      'response' => json_encode($response)
    ]);
    if ($response['items_count'] && $response['items']) {
      $code = $response['items'][0]['sale_auth_number'];
      $this->confirmation_code = $code;
      return( $code );
    }
    throw new Exception(isset($response['status_error_details']) ? $response['status_error_details'] : $response['status_code'], $response['status_code']);
  }

  public function status($params) {
    parent::status($params);
    $this->transaction = $params['payme_sale_id'];
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $_SERVER["REQUEST_URI"],
      'status' => isset($params['payme_status']) ? $params['payme_status'] : $params['status_code'],
      'description' => isset($params['status_error_code']) ? $params['status_error_code'] : $params['status_error_code'],
      'request' => json_encode($_REQUEST),
      'response' => null
    ]);
    // payme_signature
    //$signature = md5($payme_client_key . $payme_merchant_secret . $payme_transaction_id . $params['payme_sale_id']);
    $this->confirmation_code = $params['sale_auth_number'];
    return($this->confirmation_code);
  }

  public function post_process($params) {
    $this->transaction = $_REQUEST['payme_transaction_id'];
    $response = $_REQUEST;

    $this->save([
      'transaction_id' => $this->transaction,
      'url' => ':post_process',
      'status' => isset($response['status_code']) && $response['status_code'] != 0 ? $response['status_error_code'] : $response['status_code'],
      'description' => isset($response['status_error_details']) ? $response['status_error_details'] : null,
      'request' => json_encode($params),
      'response' => json_encode($response)
    ]);
    $this->confirmation_code = $_REQUEST[ 'payme_transaction_auth_number' ];
    // TODO: if subscription do subscription
    //if ($params['Operation'] == 2 && isset($params['payments']) && $params['payments'] == "monthly") {
    //  if ($this->param('recurr_at') == 'post' && $this->param('reurring') == 'provider') return($this->recur_by_provider($params));
    //}
    return( ( isset( $_REQUEST[ 'status_code' ] ) && $_REQUEST[ 'status_code' ] === 0 ) || ( isset( $_REQUEST[ 'status' ] ) && $_REQUEST[ 'status' ] === 'success' ) );
  }

  public function pre_process($params) {
    $currency = isset($params[SimplePayment::CURRENCY]) && $params[SimplePayment::CURRENCY] ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    $language = isset($params['language']) ? $params['language'] : $this->param('language');
    $json = [
      'seller_payme_id' => $this->password,
      'sale_price' =>  intval( $params[ 'amount' ] * 100 ),
      'currency' => $currency,
      'product_name' => $params[ 'product' ],
      'installments' => 1, // 103 {min}{max}
      'sale_callback_url' => $this->url(SimplePayment::OPERATION_STATUS, $params),
      'sale_return_url' => $this->url(SimplePayment::OPERATION_SUCCESS, $params),
      'language' => $language
    ];
    // sale_type: authorize, token
    if (isset($params['payment_id']) && $params['payment_id']) $json['transaction_id'] = $params['payment_id'];
    if (isset($params[SimplePayment::FULL_NAME]) && $params[SimplePayment::FULL_NAME]) $json['sale_name'] = $params[SimplePayment::FULL_NAME];

    if (isset($params[SimplePayment::MOBILE]) && $params[SimplePayment::MOBILE]) $json['sale_mobile'] = $params[SimplePayment::MOBILE];
    if (isset($params[SimplePayment::EMAIL]) && $params[SimplePayment::EMAIL]) $json['sale_email'] = $params[SimplePayment::EMAIL];

    $json['sale_send_notification'] = $this->param('notify') ? 1 : 0;
    $method = isset( $params[ SimplePayment::METHOD ] ) ? $params[ SimplePayment::METHOD ] : 'credit-card';
    if ( $method ) {
      $json[ 'sale_payment_method' ] = $method;
    }
    
    switch( $method ) {
        case 'credit-card':
          $json[ 'capture_buyer' ] = 1;
          $operation = 'capture-sale';
          break;
        default:
          $operation = 'generate-sale';

          if ( $method == 'bit' ) $json[ 'layout' ] = 'qr-sms'; //dynamic, qr-sms or dynamic-loose 
    }

    // market_fee , capture_buyer
    // for tokenization: buyer_key

    $status = $this->post($this->api[ $operation ], json_encode($json), [ 'Content-Type: application/json' ], false);
    $status = json_decode($status, true);
    $status['url'] = $status['sale_url'];
    $this->transaction = $this->transaction ? : $status['payme_sale_id'];
    $response = $status;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['capture-sale'],
      'status' => isset($response['status_code']) ? $response['status_code'] : $response['status_code'],
      'description' => isset($response['status_error_details']) ? $response['status_error_details'] : null,
      'request' => json_encode($json),
      'response' => json_encode($response)
    ]);
    if (isset($status['status_code']) && $status['status_code'] != 0) {
      throw new Exception($status['status_error_details'], $status['status_error_code']);
      // status_additional_info
    }
    return($response);
  }

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
