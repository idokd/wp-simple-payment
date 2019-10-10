<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class Cardcom extends Engine {

  public $name = 'Cardcom';
  protected $cancelType = 2;
  // CancelType (0 - no cancel, 1 - back button, 2 - cancel url)

  public $api = [
    'version' => 10,
    // POST
    'payment_request' => 'https://secure.cardcom.solutions/Interface/LowProfile.aspx',
    'indicator_request' => 'https://secure.cardcom.solutions/Interface/BillGoldGetLowProfileIndicator.aspx',
    'recurring_request' => 'https://secure.cardcom.solutions/Interface/RecurringPayment.aspx',
    'charge_token_request' => 'https://secure.cardcom.co.il/Interface/ChargeToken.aspx',

    // WebService
    'ws' => 'https://secure.cardcom.solutions/Interface/BillGoldService.asmx',
    'payment_action' => 'CreateLowProfileDeal',
    'indicator_action' => 'GetLowProfileIndicator',
    'recurring_action' => 'AddUpdateRecurringOrder',
    'charge_token_action' => 'LowProfileChargeToken',
  ];

  protected $terminal = 1000;
  protected $username = 'barak9611';
  protected $password = 'c1234567!';

  const LANGUAGES = [ 'he' => 'Hebrew', 'en' => 'English' ];
  const CURRENCIES = [ 1 =>	'ILS', 2 =>	'USD', 36 =>	'AUD', 124 =>	'CAD', 208 =>	'DKK', 392 =>	'JPY', 554 =>	'NZD', 643 =>	'RUB', 756 =>	'CHF', 826 =>	'GBP', 840 =>	'USD' ];
  const OPERATIONS = [ 1 => 'Charge', 2 => 'Charge & Token', 3 => 'Token (Charge Pending)', 4 => 'Suspended Deal' ];
  const DOC_TYPES = [ 1 => 'Invoice', 3 => 'Donation Receipt', 101 => 'Order Confirmation', 400 => 'Receipt' ];
  const FIELD_STATUS = [ 'require' => 'Shown & Required', 'show' => 'Shown', 'hide' => 'Hidden'];
  const CREDIT_TYPES = [ 1 => 'Normal', 6 => 'Credit'];
  const DOC_OPERATIONS = [ 0 => 'No Invoice', 1 => 'Invoice', 2 => 'Forward (Do not show)'];

  public function __construct($params = null, $handler = null, $sandbox = true) {
    parent::__construct($params, $handler, $sandbox);
    $sandbox = $this->sandbox ? : !($this->param('terminal') && $this->param('username'));
    if (!$sandbox) {
      $this->terminal = $this->param('terminal');
      $this->username = $this->param('username');
      $this->password = $this->param('password');
    }
  }

  public function process($params) {
    header("Location: ".$params['url']);
    return(true);
  }

  public function post_process($params) {
    $this->transaction = $_REQUEST['lowprofilecode'];
    $this->record($params, $_REQUEST);
    return($_REQUEST['ResponeCode'] == 0);
  }

  public function pre_process($params) {
    $post = [];

    $post['APILevel'] = $this->api['version'];
    $post['TerminalNumber'] = $this->terminal;
    $post['UserName'] = $this->username;
    // $post['Password'] = $this->password;

    $post['Operation'] = $this->param('operation');

    $post['ProductName'] = $params['product'];
    $post['SumToBill'] = $params['amount'];

    if (isset($params['card_holder_name']) && $params['card_holder_name']) $post['CardOwnerName'] = $params['card_holder_name'];
    if (!isset($post['CardOwnerName']) && isset($params['full_name']) && $params['full_name']) $post['CardOwnerName'] = $params['full_name']; // card_holder

    if (isset($params['phone']) && $params['phone']) $post['CardOwnerPhone'] = $params['phone'];
    if (isset($params['email']) && $params['email']) $post['CardOwnerEmail'] = $params['email'];

    if (isset($params['payment_id']) && $params['payment_id']) $post['ReturnValue'] = $params['payment_id'];

    $post['codepage'] = 65001; // Codepage fixed to enable hebrew

    $currency = $this->param('currency_id'); // TODO: if currency set, convert to local id
    if ($currency != '') $post['CoinID'] = $currency;

    $language = $this->param('language');
    if ($language != '') $post['Language'] = $language; // TODO: detect wordpress language
    $payments = $this->param('max_payments');
    if ($payments != '') $post['MaxNumOfPayments'] = $payments;
    $payments = $this->param('min_payments');
    if ($payments != '') $post['MinNumOfPayments'] = $payments;
    $payments = $this->param('default_payments');
    if ($payments != '') $post['DefaultNumOfPayments'] = isset($params['payments']) && $params['payments'] ? $params['payments'] : $payments;

    $post['SuccessRedirectUrl'] = $this->url(SimplePayment::OPERATION_SUCCESS);
    $post['ErrorRedirectUrl'] = $this->url(SimplePayment::OPERATION_ERROR);
    $post['IndicatorUrl'] = $this->url(SimplePayment::OPERATION_STATUS);
    $post['CancelUrl'] = $this->url(SimplePayment::OPERATION_CANCEL);
    if ($this->param('css') != '') $post['CSSUrl'] = $this->callback.(strpos($this->callback, '?') ? '&' : '?').'op=css';

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

    if (isset($params['full_name']) && $params['full_name']) $post['InvoiceHead.CustName'] = $params['full_name'];

    if ($language) $post['InvoiceHead.Languge'] = $language;
    if ($currency) $post['InvoiceHead.CoinID'] = $currency;

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

   // HideCreditCardUserId - TODO: for aborad clients

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
    $this->record($post, $status);
    if (isset($status['LowProfileCode']) && $status['LowProfileCode']) $this->transaction = $status['LowProfileCode'];
    if (isset($status['ResponseCode']) && $status['ResponseCode'] != 0) {
      throw new Exception($status['Description'], $status['ResponseCode']);
    }
    return($status);
  }



  protected function record($request, $response) {
    $fields = [
        'terminal' => ['TerminalNumber', 'terminalnumber'],
        'profile_code' => ['LowProfileCode', 'lowprofilecode'],
        'transaction_id' => ['LowProfileCode', 'lowprofilecode'],
        'code' => '',
        'operation' => 'Operation',
        'response_code' => 'ResponseCode',
        'status_code' => 'Status',
        'deal_response' => 'DealResponse',
        'token_response' => 'TokenResponse',
        'token' => 'Token',
        'operation_response' => 'OperationResponse',
        'operation_description' => 'Description',
  ];
  $params = [ 'request' => json_encode($request),
  'response' => json_encode($response) ];
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
// InternalDealNumber	, TokenExDate, CoinId, CardOwnerID, CardValidityYear, CardValidityMonth, TokenApprovalNumber, SuspendedDealResponseCode, SuspendedDealId, SuspendedDealGroup, InvoiceResponseCode, InvoiceNumber, InvoiceType, CallIndicatorResponse, ReturnValue, NumOfPayments, CardOwnerEmail, CardOwnerName, CardOwnerPhone, AccountId, ForeignAccountNumber, SiteUniqueId

    return($this->save($params));
  }

}
