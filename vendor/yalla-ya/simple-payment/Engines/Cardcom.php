<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class Cardcom extends Engine {

  public static $name = 'Cardcom';
  public $interactive = true;
  protected $cancelType = 2;
  // CancelType (0 - no cancel, 1 - back button, 2 - cancel url)
  protected $recurrAt = 'post'; // status

  public static $supports = [ 'iframe', 'modal', 'tokenization', 'subscriptions' ];

  public static $domains = [
    'secure.cardcom.solutions'
  ];

  public $api = [
    'version' => 10,
    // POST
    'payment_request' => 'https://secure.cardcom.solutions/Interface/LowProfile.aspx',
    'indicator_request' => 'https://secure.cardcom.solutions/Interface/BillGoldGetLowProfileIndicator.aspx',
    'payment_recur' => 'https://secure.cardcom.solutions/interface/ChargeToken.aspx',
    'recurring_request' => 'https://secure.cardcom.solutions/Interface/RecurringPayment.aspx',

    // WebService
    'ws' => 'https://secure.cardcom.solutions/Interface/BillGoldService.asmx',
    'payment_action' => 'CreateLowProfileDeal',
    'indicator_action' => 'GetLowProfileIndicator',
    'recurring_action' => 'AddUpdateRecurringOrder',
    'recur_action' => 'LowProfileChargeToken',
  ];

  protected $terminal = 1000;
  protected $username = 'test9611';
  public $password = 'c1234567!';

  const LANGUAGES = [ 'he' => 'Hebrew', 'en' => 'English' ];
  const CURRENCIES = [ 'ILS' => 1, 'USD' => 2, 'AUD' => 36,	'CAD' => 124, 'DKK' => 208, 'JPY' => 392, 'NZD' => 554, 'RUB' => 643, 'CHF' => 756, 'GBP' => 826, 'EUR' => 978, 'ZAR' => 710, 'EGP' => 818, 'SEK' => '752', 'NOK' => 578, 'LBP' => 422, 'JOD' => 400 ];
  const OPERATIONS = [ 1 => 'Charge', 2 => 'Charge & Token', 3 => 'Token (Charge Pending)', 4 => 'Suspended Deal' ];
  const DOC_TYPES = [ 1 => 'Invoice', 3 => 'Formal Receipt', 101 => 'Order Confirmation', 400 => 'Receipt' , 405 => 'Donation' ];
  const FIELD_STATUS = [ 'require' => 'Shown & Required', 'show' => 'Shown', 'hide' => 'Hidden'];
  const CREDIT_TYPES = [ 1 => 'Normal', 6 => 'Credit'];
  const DOC_OPERATIONS = [ 0 => 'No Invoice', 1 => 'Invoice', 2 => 'Forward (Do not show)'];

  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
    $this->sandbox = $this->sandbox ? : !( $this->param( 'terminal' ) && $this->param( 'username' ) );
  }

  public function process( $params ) {
    //header("Location: ".$params['url']);
    //return(true);
    return( $params[ 'url' ] );
  }

  public function verify( $transaction ) {
    $this->transaction = $transaction[ 'transaction_id' ];
    $post = [];
    if ( !$this->sandbox ) {
      $post['terminalnumber'] = $this->param_part($params);
      $post['username'] = $this->param_part($params, 'username');
    } else {
      $post['terminalnumber'] = $this->terminal;
      $post['username'] = $this->username;
    }
    //$post['terminalnumber'] = $this->terminal;
    //$post['username'] = $this->username;
    $post['lowprofilecode'] = $this->transaction;
    $post['codepage'] = 65001;

    $response = $this->post( $this->api['indicator_request' ], $post );
    parse_str( $response, $response );

    $token = null;
    if ( $response[ 'Token' ] ) {
      $token = [
          'token' => $response[ 'Token' ],
          SimplePayment::CARD_OWNER => $response[ 'CardOwnerID' ],
          SimplePayment::CARD_EXPIRY_YEAR => $response[ 'CardValidityYear' ],
          SimplePayment::CARD_EXPIRY_MONTH => $response[ 'CardValidityMonth' ],
        //  'card_type' => '',
          'expiry' => $response[ 'TokenExDate' ],
      ];
    }
    $this->confirmation_code = $response[ 'InternalDealNumber' ];
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->api[ 'indicator_request' ],
      'status' => isset($response['OperationResponse']) ? $response['OperationResponse'] : (isset($response['DealResponse']) ? $response['DealResponse'] : ''),
      'description' => isset($response['OperationResponseText']) ? $response['OperationResponseText'] : $response['Description'],
      'request' => json_encode( $post ),
      'response' => json_encode( $response )
    ] );
    if ( $token ) {
      $this->save( [
        'transaction_id' => $this->transaction,
        'url' => $this->api[ 'indicator_request' ],
        'status' => isset($response['OperationResponse']) ? $response['OperationResponse'] : (isset($response['DealResponse']) ? $response['DealResponse'] : ''),
        'description' => isset($response['OperationResponseText']) ? $response['OperationResponseText'] : $response['Description'],
        'request' => json_encode( $post ),
        'response' => json_encode( $response ),
        'token' => $token
      ] );
    }
    $operation = isset($response['Operation']) ? $response['Operation'] : null;
    $code = isset($response['OperationResponse']) ? $response['OperationResponse'] : 999;
    switch($operation) {
      case 1:
      case "1":
        $code = $response['DealResponse'];
        if (isset($response['OperationResponse']) && $response['OperationResponse'] == '0' && isset($response['DealResponse']) && $response['DealResponse'] == '0' ) return($this->confirmation_code);
        break;
      case 2:
      case "2":
        $code = $response['TokenResponse'];
        if (isset($response['OperationResponse']) && $response['OperationResponse'] == '0' && isset($response['DealResponse']) && $response['DealResponse'] == '0' && isset($response['TokenResponse']) && $response['TokenResponse'] == '0') return($this->confirmation_code);
        break;
      case 3:
      case "3":
        $code = $response['SuspendedDealResponseCode'];
        if (isset($response['OperationResponse']) && $response['OperationResponse'] == '0' &&  isset($response['SuspendedDealResponseCode']) && $response['SuspendedDealResponseCode'] == '0') return($this->confirmation_code);
        break;
      case 4:
      case "4":
        break;
    }
    throw new Exception(isset($response['OperationResponseText']) ? $response['OperationResponseText'] : $response['Description'], $code);
  }

  public function feedback( $params ) {
    parent::feedback( $params );
    $params[ 'payment_id' ] = $params[ 'ReturnValue' ];
    $this->save( [
      'payment_id' => $params[ 'payment_id' ],
      'url' => ':callback',
      'status' => isset( $params[ 'ResposeCode' ] ) ? $params[ 'ResposeCode' ] : $params[ 'ResponseCode' ],
      'description' => isset( $params[  'OperationResponseText' ] ) ? $params[  'OperationResponseText  '] : $params['Description'],
      'request' => json_encode( $params ),
      'response' => null
    ] );
    return( $params );
  }

  public function status($params) {
    parent::status($params);
    $this->transaction = $params['lowprofilecode'];
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $_SERVER["REQUEST_URI"],
      'status' => isset($params['OperationResponse']) ? $params['OperationResponse'] : $params['DealResponse'],
      'description' => isset($params['OperationResponseText']) ? $params['OperationResponseText'] : $params['Description'],
      'request' => json_encode($_REQUEST),
      'response' => null
    ]);
    $post = [];
    if ( !$this->sandbox ) {
      $post['terminalnumber'] = $this->param_part($params);
      $post['username'] = $this->param_part($params, 'username');
    } else {
      $post['terminalnumber'] = $this->terminal;
      $post['username'] = $this->username;
    }
    $post['lowprofilecode'] = $params['lowprofilecode'];
    $status = $this->post($this->api['indicator_request'], $post);
    parse_str($status, $status);
    //$this->transaction = $params['lowprofilecode'];

    // TODO: fetch VISA, YEAR, MONTH, ID???
    $token = null;
    if ( isset( $status[ 'Token' ] ) && $status[ 'Token' ] ) {
      $token = [
          'token' => $status['Token'],
          SimplePayment::CARD_OWNER => $status['CardOwnerID'],
          SimplePayment::CARD_EXPIRY_YEAR => $status['CardValidityYear'],
          SimplePayment::CARD_EXPIRY_MONTH => $status['CardValidityMonth'],
        //  'card_type' => '',
          'expiry' => $status['TokenExDate'],
      ];
    }
    $response = $status;
    $this->confirmation_code = isset( $response[ 'InternalDealNumber' ] ) ? $response[ 'InternalDealNumber' ] : null;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['indicator_request'],
      'status' => isset($response['OperationResponse']) ? $response['OperationResponse'] : $response['DealResponse'],
      'description' => isset($response['OperationResponseText']) ? $response['OperationResponseText'] : $response['Description'],
      'request' => json_encode($post),
      'response' => json_encode($response),
      'token' => $token
    ]);
    if (!isset($response['OperationResponse']) || $response['OperationResponse'] != 0) {
      throw new Exception(isset($response['OperationResponseText']) ? $response['OperationResponseText'] : $response['Description'], isset($response['OperationResponse']) ? $response['OperationResponse'] : $response['DealResponse']);
    }
    if ($params['Operation'] == 2 && isset($params['payments']) && $params['payments'] == "monthly") {
      if ($this->param('recurr_at') == 'status' && $this->param('reurring') == 'provider') $this->recur_by_provider($params);
    }
    return($this->confirmation_code);
  }

  public function post_process($params) {
    $this->transaction = isset( $_REQUEST['lowprofilecode'] ) ? $_REQUEST['lowprofilecode'] : $params[ 'transaction_id' ];
    $response = $_REQUEST;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => ':post_process',
      'status' => isset($response['ResponseCode']) ? $response['ResponseCode'] : $response['response_code'],
      'description' => isset($response['Description']) ? $response['Description'] : null,
      'request' => json_encode($params),
      'response' => json_encode($response)
    ]);
    if ($params['Operation'] == 2 && isset($params['payments']) && $params['payments'] == "monthly") {
      if ($this->param('recurr_at') == 'post' && $this->param('reurring') == 'provider') return($this->recur_by_provider($params));
    }
    // TODO: update confirmation code con status
    //$this->confirmation_code = $response['confirmation_code'];
    return($_REQUEST['ResponeCode'] == 0);
  }

  protected function param_part($params, $name = 'terminal') {
    $terminal = isset($params['terminal']) ? $params['terminal'] : null;
    if (!$terminal) $terminal = isset($params['terminalnumber']) ? $params['terminalnumber'] : null;
    $index = array_search($terminal, explode(';', $this->param('terminal')));
    if ($index !== FALSE && $name == 'terminal') return($terminal);

    $parts = explode(';', $this->param($name));
    $part = $parts[0];

    if ($index !== FALSE) return(count($parts) > $index ? $parts[$index] : $parts[count($parts) - 1]);

    if (isset($params['payments']) && isset($parts[1]) && $params['payments'] && ($params['payments'] != 'single' || $params['payments'] != 1)) {
      if (isset($parts[1])) $part = $parts[1];
      if ($params['payments'] != 'installments' && issset($parts[2])) $part = isset($parts[2]) ? : $part;
    }
    return($part);
  }

  public function pre_process($params) {
    $post = [];
    $post['APILevel'] = $this->api['version'];
    if (!$this->sandbox) {
      $post['TerminalNumber'] = $this->param_part($params);
      $post['UserName'] = $this->param_part($params, 'username');
      // $this->password = $this->param_part($params, 'password');
      // $post['Password'] = $this->password;
    } else {
      $post['TerminalNumber'] = $this->terminal;
      $post['UserName'] = $this->username;
      $post['Password'] = $this->password;
    }

    $operation = $this->param('operation');

    // TODO: maybe add flag to determine this feature?
    if ($operation == 2 && !$params['amount']) $operation = 3;
    $post['Operation'] = $operation;


    $post['ProductName'] = $params['product'];
    $post['SumToBill'] = $params['amount'];

    if (isset($params[SimplePayment::CARD_OWNER]) && $params[SimplePayment::CARD_OWNER]) $post['CardOwnerName'] = $params[SimplePayment::CARD_OWNER];
    if (!isset($post['CardOwnerName']) && isset($params['full_name']) && $params['full_name']) $post['CardOwnerName'] = $params['full_name']; // card_holder

    if (isset($params['phone']) && $params['phone']) $post['CardOwnerPhone'] = $params['phone'];
    if (isset($params['email']) && $params['email']) $post['CardOwnerEmail'] = $params['email'];

    if (isset($params['payment_id']) && $params['payment_id']) $post['ReturnValue'] = $params['payment_id'];

    $post['codepage'] = 65001; // Codepage fixed to enable hebrew
    $currency = isset($params[SimplePayment::CURRENCY]) && $params[SimplePayment::CURRENCY] ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    if ($currency) {
      if ($currency = self::CURRENCIES[$currency]) $post['CoinID'] = $currency;
      else throw new Exception('CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500);
    }

    $language = isset($params['language']) ? $params['language'] : $this->param('language');
    if ($language != '') $post['Language'] = $language;

    if (isset($params['payments']) && $params['payments']) {
      if ($params['payments'] == 'installments') {
        $payments = $this->param('installments_max');
        $post['MaxNumOfPayments'] = $payments ? $payments : (isset($params['installments']) ? $params['installments'] : 12);
        $payments = $this->param('installments_min');
        if ($payments != '') $post['MinNumOfPayments'] = $payments;
        $post['DefaultNumOfPayments'] = isset($params['installments']) && $params['installments'] ? $params['installments'] : $this->param('installments_default');
      }
    }

    $post['SuccessRedirectUrl'] = $this->url(SimplePayment::OPERATION_SUCCESS, $params);
    $post['ErrorRedirectUrl'] = $this->url(SimplePayment::OPERATION_ERROR, $params);
    $post['IndicatorUrl'] = $this->url(SimplePayment::OPERATION_STATUS, $params);
    $post['CancelUrl'] = $this->url(SimplePayment::OPERATION_CANCEL, $params);
    if ($this->param('css') != '') $post['CSSUrl'] = $this->callback.(strpos($this->callback, '?') !== false ? '&' : '?').'op=css';

    $post['CancelType'] = $this->cancelType;

    $creditType = $this->param('credit_type');
    if ($creditType != '') $post['CreditType'] = $creditType;

    $show = $this->param('show_invoice_operation');
    if ($operation != 3 && $show != '') $post['InvoiceHeadOperation'] = $operation == 3 ? 0 : $show;

    $show = $this->param('show_invoice_info');
    if ($operation != 3 && $show != '') $post['ShowInvoiceHead'] = $show;

    $docType = $this->param('doc_type');
    if ($operation != 3 && $docType != '') $post['DocTypeToCreate'] = $docType;

    $field = $this->param('field_name');
    if ($field != '') {
        $post['HideCardOwnerName'] = $field == 'hide' ? 'true' : 'false';
    }

    $field = $this->param('field_phone');
    if ($field != '') {
        $post['ReqCardOwnerPhone'] = $field == 'require' ? 'true' : 'false';
        $post['ShowCardOwnerPhone'] = $field == 'show' || $field == 'require' ? 'true' : 'false';
    }

    $field = $this->param('field_email');
    if ($field != '') {
        $post['ReqCardOwnerEmail'] = $field == 'require' ? 'true' : 'false';
        $post['ShowCardOwnerEmail'] = $field == 'show' || $field == 'require' ? 'true' : 'false';
    }
    
    if ((!isset($params['payments']) || $params['payments'] != "monthly") && $this->param('hide_user_id')) $post['HideCreditCardUserId'] = $this->param('hide_user_id') == 'true' ? 'true' : 'false';

    if ($operation != 3 && isset($params['company']) && $params['company']) $post['InvoiceHead.CustName'] = $params['company'];
    if ($operation != 3 && !isset($post['InvoiceHead.CustName']) && isset($params['full_name']) && $params['full_name']) $post['InvoiceHead.CustName'] = $params['full_name'];
    if ($operation != 3) $post = array_merge($post, $this->document(array_merge($params, ['language' => $language, 'currency' => $currency])));

    // TODO: Analyze how to use those parameters
    // SumInStars
    // SapakMutav
    // RefundDeal
    // IsAVSEnable =
    // AutoRedirect - false
    // IsVirtualTerminalMode - false
    // IsOpenSum - false
    // ChargeOnSwipe - false
    // ?? HideCVV

    // TODO: check specific requirments for operations
    // Operation type 2 / 3
    // CreateTokenDeleteDate // DeleteDate
    // TokenToCreate.JValidateType
    // CreateTokenJValidateType (2 / 5 )

    // Operation 4
    // SuspendedDealJValidateType
    // SuspendedDealGroup
    $status = $this->post($this->api['payment_request'], $post);
    parse_str($status, $status);
    $status['url'] = $this->param('method') == 'paypal' ? $status['PayPalUrl'] : $status['url'];
    $this->transaction = $this->transaction ? : $status['LowProfileCode'];
    $response = $status;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['payment_request'],
      'status' => isset($response['ResponseCode']) ? $response['ResponseCode'] : $response['response_code'],
      'description' => isset($response['Description']) ? $response['Description'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
    if (isset($status['LowProfileCode']) && $status['LowProfileCode']) $this->transaction = $status['LowProfileCode'];
    if (isset($status['ResponseCode']) && $status['ResponseCode'] != 0) {
      throw new Exception($status['Description'], $status['ResponseCode']);
    }
    return($response);
  }

  public function recur_by_provider($params) {
    $post = [];
    
    if (!$this->sandbox) {
      $post['TerminalNumber'] = $this->param_part($params);
      $post['UserName'] = $this->param_part($params, 'username');
      $terminals = $this->param('terminal');
      $terminals = explode(';', $terminals);
    } else {
      $post['TerminalNumber'] = $this->terminal;
      $post['UserName'] = $this->username;
    }

    if ($this->param('recurring_terminal')) $post['RecurringPayments.ChargeInTerminal'] = $this->param('recurring_terminal');
    $post['Operation'] = $this->param('reurring_operation');
    $post['LowProfileDealGuid'] = isset($params['lowprofilecode']) ? $params['lowprofilecode'] : $params['transaction_id'];
    
    if ($this->param('department_id')) $post['RecurringPayments.DepartmentId'] = $this->param('department_id');
    //if (isset($params['payment_id']) && $params['payment_id']) $post['Account.SiteUniqueId'] = $params['payment_id'];

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
    if (isset($params['last_name']) && $params['last_name']) $post['Account.FirstName'] = (isset($post['Account.FirstName']) && $post['Account.FirstName'] ? ' ' : '').$params['last_name'];

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

    $limit = $this->param( 'recurring_total' );
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
    if (isset($params['company']) && trim($params['company'])) $post['Account.CompanyName'] = trim($params['company']);
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
    return( $status ); // OperationResponseText, OperationResponse
  }

  public function refund( $params ) {
    $this->transaction = self::uuid();
    $this->confirmation_code = $this->charge($params, true);
    return( $this->confirmation_code );
  }

  public function recharge( $params ) {
    $this->transaction = self::uuid();
    $this->confirmation_code = $this->charge( $params );
    return( $this->confirmation_code );
  }

  public function recur( $params ) {
    $this->transaction = self::uuid();
    //$this->transaction = $params['transaction_id'];
    $this->confirmation_code = $this->charge( $params );
    return( $this->confirmation_code );
  }

  public function charge( $params, $refund = false ) {
    $post = [];
    if ( !$this->sandbox ) {
      $post[ 'terminalnumber' ] = $this->param_part( $params );
      $post[ 'username' ] = $this->param_part( $params, 'username' );
     // $post['TokenToCharge.UserPassword'] = $this->param_part($params, 'password');
    } else {
      $post[ 'terminalnumber' ] = $this->terminal;
      $post[ 'username' ] = $this->username;
    }

    if ($refund) $post['TokenToCharge.UserPassword'] = $this->password;

    $token = $params['token'] ? json_decode($params['token'], true) : null;
    if ($token) {
      $post['TokenToCharge.Token'] = $token['token'];//$this->transaction;
      if (isset($token[SimplePayment::CARD_EXPIRY_YEAR])) $post['TokenToCharge.CardValidityYear'] = $token[SimplePayment::CARD_EXPIRY_YEAR];
      if (isset($token[SimplePayment::CARD_EXPIRY_MONTH])) $post['TokenToCharge.CardValidityMonth'] = $token[SimplePayment::CARD_EXPIRY_MONTH];      
      if (isset($token[SimplePayment::CARD_OWNER_ID])) $post['TokenToCharge.IdentityNumber'] = $token[SimplePayment::CARD_OWNER_ID];
    }
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
    // TokenToCharge.CardValidityMonth
    // TokenToCharge.CardValidityYear
    // TokenToCharge.IdentityNumber
// UniqAsmachta


    if ($refund) $post['TokenToCharge.RefundInsteadOfCharge'] = $refund;
    if ($params['PAYMENTS'] == 'monthly') $post['TokenToCharge.IsAutoRecurringPayment'] = 'true';

    if (isset($params['approval_number']) && $params['approval_number']) $post['TokenToCharge.ApprovalNumber'] = $params['approval_number'];


    // $post = array_merge($post, $this->document(array_merge($params, ['language' => $language, 'currency' => $currency])));

    $status = $this->post($this->api['payment_recur'], $post);
    parse_str($status, $status);
    $response = $status;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['payment_recur'],
      'status' => isset($response['ResponseCode']) ? $response['ResponseCode'] : $response['response_code'],
      'description' => isset($response['Description']) ? $response['Description'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response),
      'token' => isset($token) ? $token : null
    ]);
    // InternalDealNumber
    return($response['ResponseCode'] == 0 ? $response['InternalDealNumber'] : false);
    // Not in use:
    // TokenToCharge.Salt, TokenToCharge.SumInStars, TokenToCharge.NumOfPayments
    // TokenToCharge.ExtendedParameters,  TokenToCharge.SapakMutav
    //  TokenToCharge.TokenCompanyUserName,  TokenToCharge.TokenCompanyPassword
    //  TokenToCharge.FirstPaymentSumAgorot, TokenToCharge.ConstPaymentAgorot
    // TokenToCharge.JParameter
    // TokenToCharge.UniqAsmachta
    // TokenToCharge.AvsCity, TokenToCharge.AvsAddress, TokenToCharge.AvsZip
  }

  protected function document($params) {
    $post = [];
    $language = $this->param('language');
    $currency = isset($params[SimplePayment::CURRENCY]) ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    if ($language) $post['InvoiceHead.Languge'] = $params['language'];
    if ($currency) $post['InvoiceHead.CoinID'] = $params['currency'];

    if (isset($params['email']) && $params['email']) $post['InvoiceHead.Email'] = $params['email'];
    
    if (!$this->param('doc_details') || in_array($this->param('doc_details'), ['address', 'full'])) {
      if (isset($params['address']) && $params['address']) $post['InvoiceHead.CustAddresLine1'] = $params['address'];
      if (isset($params['address2']) && $params['address2']) $post['InvoiceHead.CustAddresLine2'] = $params['address2'];
      if (isset($params['city']) && $params['city']) $post['InvoiceHead.CustCity'] = $params['city'];
    }
    if (!$this->param('doc_details') || in_array($this->param('doc_details'), ['contact', 'full'])) {
      if (isset($params['phone']) && $params['phone']) $post['InvoiceHead.CustLinePH'] = $params['phone'];
      if (isset($params['mobile']) && $params['mobile']) $post['InvoiceHead.CustMobilePH'] = $params['mobile'];
    }

    if (isset($params['tax_id']) && $params['tax_id']) $post['InvoiceHead.CompID'] = $params['tax_id'];
    if (isset($params['comment']) && $params['comment']) $post['InvoiceHead.Comments'] = $params['comment'];
    if ($this->param('email_invoice')) $post['InvoiceHead.SendByEmail'] = 'true';
    if ($this->param('vat_free')) $post['InvoiceHead.ExtIsVatFree'] = 'true';
    if ($this->param('auto_create_account')) $post['InvoiceHead.IsAutoCreateUpdateAccount'] = 'true';
    if ($this->param('auto_load_account')) $post['InvoiceHead.IsLoadInfoFromAccountID'] = 'true';
    if ($this->param('department_id')) $post['InvoiceHead.DepartmentId'] = $this->param('department_id');
    //if ( isset($params['payment_id']) && $params['payment_id']) $post['InvoiceHead.SiteUniqueId'] = $params['payment_id'];

    if ( isset( $params[ 'products' ] ) && is_array( $params[ 'products' ] ) ) {
      $index = 1;
      foreach ( $params[ 'products' ] as $product ) {
        $post[ 'InvoiceLines' . $index . '.Description' ] = $product[ 'name' ];
        if ( isset( $product[ 'description' ] ) ) $post[ 'InvoiceLines' . $index . '.Description' ] .= ' ' . $product[ 'description' ];
        $post[ 'InvoiceLines' . $index . '.Price' ] = $product[ 'amount' ];
        $post[ 'InvoiceLines' . $index . '.Quantity' ] = $product[ 'qty' ];
        $post[ 'InvoiceLines' . $index . '.IsPriceIncludeVAT' ] = 'true'; // Must be true - API requirement
        // TODO: support per item: $post['InvoiceLines1.IsVatFree'] = 'true';
        if ( isset( $product[ 'id' ] ) && $product[ 'id' ] ) $post[ 'InvoiceLines' . $index . '.ProductID' ] = $product[ 'id' ];
        $index++;
      }
    } else {
      $post[ 'InvoiceLines1.Description'] = $params['product'];
      $post[ 'InvoiceLines1.Price'] = $params['amount'];
      $post[ 'InvoiceLines1.Quantity'] = 1;
      $post[ 'InvoiceLines1.IsPriceIncludeVAT'] = 'true'; // Must be true - API requirement
      // TODO: support per item: $post['InvoiceLines1.IsVatFree'] = 'true';
      if ( isset( $params[ 'id' ] ) && $params[ 'id' ] ) $post[ 'InvoiceLines1.ProductID' ] = $params[ 'id' ];
    }
    
/*

    AccountForeignKey 	- not needed at the moment, TODO: maybe use user_id from wordpress (string)

    InvoiceHead.ManualInvoiceNumber - require special authorization & support from CardCom, confict may cause failed document creation

    InvoiceHead.AccountID	 - not needed (int) - this is account id in cardcom system, we dont have it and we don't care
    
    InvoiceHead.ValueDate, InvoiceHead.Date - not supported on this LowProfile API

   // TODO: Support Custom Fields
   CustomFields.Field1 .. 25 */
   if ( isset( $params[ 'custom' ] ) && $params[ 'custom' ] ) {
     $fid = 1;
     foreach ( $params[ 'custom' ] as $key => $value ) {
       //if ( $key ) {
       //  
       //}
       $post[ 'CustomFields.Field' . $fid ] = $value;
       $fid++;
     }
   }
    return($post);
  }
  
}