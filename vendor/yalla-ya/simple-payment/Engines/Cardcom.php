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

  public $name = 'Cardcom';
  public $interactive = true;
  protected $cancelType = 2;
  // CancelType (0 - no cancel, 1 - back button, 2 - cancel url)
  protected $recurrAt = 'post'; // status

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
  protected $username = 'barak9611';
  public $password = 'c1234567!';

  const LANGUAGES = [ 'he' => 'Hebrew', 'en' => 'English' ];
  const CURRENCIES = [ 'ILS' => 1, 'USD' => 2, 'AUD' => 36,	'CAD' => 124, 'DKK' => 208, 'JPY' => 392, 'NZD' => 554, 'RUB' => 643, 'CHF' => 756, 'GBP' => 826 ];
  const OPERATIONS = [ 1 => 'Charge', 2 => 'Charge & Token', 3 => 'Token (Charge Pending)', 4 => 'Suspended Deal' ];
  const DOC_TYPES = [ 1 => 'Invoice', 3 => 'Donation Receipt', 101 => 'Order Confirmation', 400 => 'Receipt' ];
  const FIELD_STATUS = [ 'require' => 'Shown & Required', 'show' => 'Shown', 'hide' => 'Hidden'];
  const CREDIT_TYPES = [ 1 => 'Normal', 6 => 'Credit'];
  const DOC_OPERATIONS = [ 0 => 'No Invoice', 1 => 'Invoice', 2 => 'Forward (Do not show)'];

  public function __construct($params = null, $handler = null, $sandbox = true) {
    parent::__construct($params, $handler, $sandbox);
    $this->sandbox = $this->sandbox ? : !($this->param('terminal') && $this->param('username'));
  }

  public function process($params) {
    //header("Location: ".$params['url']);
    //return(true);
    return($params['url']);
  }

  public function status($params) {
    parent::status($params);
    $this->transation = $params['lowprofilecode'];
    $post = [];
    if (!$this->sandbox) {
      $post['terminalnumber'] = $this->param_part($params);
      $post['username'] = $this->param_part($params, 'username');
    } else {
      $post['terminalnumber'] = $this->terminal;
      $post['username'] = $this->username;
    }
    $post['lowprofilecode'] = $params['lowprofilecode'];
    $status = $this->post($this->api['indicator_request'], $post);
    parse_str($status, $status);
    //$this->record($params, $status);
    $this->transaction = $params['lowprofilecode'];
    $this->confirmation_code = $status['TokenApprovalNumber'];
    $response = $status;
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['indicator_request'],
      'status' => isset($response['OperationResponse']) ? $response['OperationResponse'] : $response['DealResponse'],
      'description' => isset($response['OperationResponseText']) ? $response['OperationResponseText'] : $response['Description'],
      'request' => json_encode($post),
      'response' => json_encode($response)
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
    $this->transaction = $_REQUEST['lowprofilecode'];
    //$this->record($params, $_REQUEST);
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

    $post['Operation'] = $this->param('operation');

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
    if ($show != '') $post['InvoiceHeadOperation'] = $show;

    $show = $this->param('show_invoice_info');
    if ($show != '') $post['ShowInvoiceHead'] = $show;

    $docType = $this->param('doc_type');
    if ($docType != '') $post['DocTypeToCreate'] = $docType;

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

    if (isset($params['full_name']) && $params['full_name']) $post['InvoiceHead.CustName'] = $params['full_name'];

    $post = array_merge($post, $this->document(array_merge($params, ['language' => $language, 'currency' => $currency])));

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
    //$this->record($post, $status);
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
    if (!$this->sandbox) {
      $post['terminalnumber'] = $this->param_part($params);
      $post['username'] = $this->param_part($params, 'username');
      // $post['TokenToCharge.UserPassword'] = $this->param_part($params, 'password');
    } else {
      $post['terminalnumber'] = $this->terminal;
      $post['username'] = $this->username;
      //$post['TokenToCharge.UserPassword'] = $this->password;
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
    // TokenToCharge.Token, TokenToCharge.CardValidityMonth
    // TokenToCharge.CardValidityYear
    // TokenToCharge.IdentityNumber

    $post['TokenToCharge.RefundInsteadOfCharge'] = 'false';
    $post['TokenToCharge.IsAutoRecurringPayment'] = 'true';

    if (isset($params['approval_number']) && $params['approval_number']) $post['TokenToCharge.ApprovalNumber'] = $params['approval_number'];


    $post = array_merge($post, $this->document(array_merge($params, ['language' => $language, 'currency' => $currency])));

    $status = $this->post($this->api['payment_recur'], $post);
    parse_str($status, $status);
    //$this->record($post, $status);
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

  protected function document($params) {
    $post = [];
    $language = $this->param('language');
    $currency = isset($params[SimplePayment::CURRENCY]) ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    if ($language) $post['InvoiceHead.Languge'] = $params['language'];
    if ($currency) $post['InvoiceHead.CoinID'] = $params['currency'];
    if (isset($params['email']) && $params['email']) $post['InvoiceHead.Email'] = $params['email'];
    if (isset($params['address']) && $params['address']) $post['InvoiceHead.CustAddresLine1'] = $params['address'];
    if (isset($params['address2']) && $params['address2']) $post['InvoiceHead.CustAddresLine2'] = $params['address2'];
    if (isset($params['city']) && $params['city']) $post['InvoiceHead.CustCity'] = $params['city'];
    if (isset($params['phone']) && $params['phone']) $post['InvoiceHead.CustLinePH'] = $params['phone'];
    if (isset($params['mobile']) && $params['mobile']) $post['InvoiceHead.CustMobilePH'] = $params['mobile'];
    if (isset($params['tax_id']) && $params['tax_id']) $post['InvoiceHead.CompID'] = $params['tax_id'];
    if (isset($params['comment']) && $params['comment']) $post['InvoiceHead.Comments'] = $params['comment'];
    if ($this->param('email_invoice')) $post['InvoiceHead.SendByEmail'] = 'true';
    if ($this->param('vat_free')) $post['InvoiceHead.ExtIsVatFree'] = 'true';
    if ($this->param('auto_create_account')) $post['InvoiceHead.IsAutoCreateUpdateAccount'] = 'true';
    if ($this->param('auto_load_account')) $post['InvoiceHead.IsLoadInfoFromAccountID'] = 'true';
    if ($this->param('department_id')) $post['InvoiceHead.DepartmentId'] = $this->param('department_id');
    if ($this->param('site_id')) $post['InvoiceHead.SiteUniqueId'] = $this->param('site_id');
    $post['InvoiceLines1.Description'] = $params['product'];
    $post['InvoiceLines1.Price'] = $params['amount'];
    $post['InvoiceLines1.Quantity'] = 1;
    $post['InvoiceLines1.IsPriceIncludeVAT'] = 'true'; // Must be true - API requirement
    // TODO: support per item: $post['InvoiceLines1.IsVatFree'] = 'true';
    if (isset($params['id']) && $params['id']) $post['InvoiceLines1.ProductID'] = $params['id'];
/*
    InvoiceHead.ManualInvoiceNumber - require special support from CardCom
    AccountForeignKey 	- not needed at the moment, TODO: maybe use user_id from wordpress (string)
    InvoiceHead.AccountID	 - not needed (int)
    InvoiceHead.ValueDate, InvoiceHead.Date - not supported on this API

   // TODO: Support Custom Fields
   CustomFields.Field1 .. 25 */
    return($post);
  }
  
  protected function record($request, $response, $fields = []) {
    $fields = [
        'terminal' => ['TerminalNumber', 'terminalnumber'],
        'transaction_id' => ['LowProfileCode', 'lowprofilecode', 'LowProfileDealGuid'],
        'operation' => ['Operation', 'Recurring0_RecurringId'],
        'response_code' => ['ResponseCode', 'response_code'],
        'deal_response' => 'DealResponse',
        'token_response' => 'TokenResponse',
        'token' => ['Token', 'Recurring0_RecurringId'],
        'operation_response' => ['OperationResponse','Status'],
        'operation_description' => ['Description', 'OperationResponseText'],
    ];
// InternalDealNumber	, TokenExDate, CoinId, CardOwnerID, CardValidityYear, CardValidityMonth, TokenApprovalNumber, SuspendedDealResponseCode, SuspendedDealId, SuspendedDealGroup, InvoiceResponseCode, InvoiceNumber, InvoiceType, CallIndicatorResponse, ReturnValue, NumOfPayments, CardOwnerEmail, CardOwnerName, CardOwnerPhone, AccountId, ForeignAccountNumber, SiteUniqueId
    return(parent::record($request, $response, $fields));
  }

  
}
