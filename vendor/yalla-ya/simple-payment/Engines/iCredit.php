<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class iCredit extends Engine {

  public $name = 'iCredit';
  public $interactive = true;
  public $box = '7cd9f149-d25a-4df3-9b01-d1ecce097c31';

  public static $supports = ['iframe', 'modal', 'tokenization'];

  public $api = [
    'live' => [
      'verify' => 'https://icredit.rivhit.co.il/API/PaymentPageRequest.svc/Verify',
      'get-url' => 'https://icredit.rivhit.co.il/API/PaymentPageRequest.svc/GetUrl',
      'charge-token' => 'https://icredit.rivhit.co.il/API/PaymentPageRequest.svc/SaleChargeToken',
      'token-details' => 'https://pci.rivhit.co.il/api/RivhitRestAPIService.svc/GetTokenDetails',
      'sale-details' => 'https://icredit.rivhit.co.il/API/PaymentPageRequest.svc/SaleDetails',
    ],
    'sandbox' => [
      'verify' => 'https://testicredit.rivhit.co.il/API/PaymentPageRequest.svc/Verify',
      'get-url' => 'https://testicredit.rivhit.co.il/API/PaymentPageRequest.svc/GetUrl',
      'charge-token' => 'https://testicredit.rivhit.co.il/API/PaymentPageRequest.svc/SaleChargeToken',
      'token-details' => 'https://testpci.rivhit.co.il/api/RivhitRestAPIService.svc/GetTokenDetails',
      'sale-details' => 'https://testicredit.rivhit.co.il/API/PaymentPageRequest.svc/SaleDetails',
    ]
  ];

  public $password = 'bb8a47ab-42e0-4b7f-ba08-72d55f2d9e41';

  // demo access: demo / 123 / 8888
  const LANGUAGES = [ 'he' => 'Hebrew', 'en' => 'English' ];
  const CURRENCIES = [ 'ILS' => 1, 'USD' => 2, 'EUR' => 3,	'GBP' => 4, 'AUD' => 5, 'CAD' => 6 ];
  const OPERATIONS = [ 1 => 'Charge', 2 => 'Authorize' ];

  public function __construct($params = null, $handler = null, $sandbox = true) {
    parent::__construct($params, $handler, $sandbox);
    $this->password = $this->sandbox ? $this->password : $this->param('password');
    $this->box = $this->sandbox ? $this->box : $this->param('box');
    $this->api = $this->api[$this->sandbox? 'sandbox' : 'live'];
  }

  public function process($params) {
    return($params['URL']);
  }

  public function verify($id) {
    $this->transaction = $id;
    $post = [];
    $post['GroupPrivateToken'] = $this->password;
    $post['SaleId'] = $this->transaction;
    $post['TotalAmount'] = $response['Amount'];

    $response = $this->post($this->api['verify'], json_encode($post), [ 'Content-Type: application/json' ]);
    $response = json_decode($response, true);
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['verify'],
      'status' => isset($response['Status']) ? $response['Status'] : null,
      'description' => isset($response['DebugMessage']) ? $response['DebugMessage'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
    $code = isset($response['AuthNum']) ? $response['AuthNum'] : null;
    if ($code) return($code);
    throw new Exception(isset($response['Status']) ? $response['Status'] : 'DID_NOT_VERIFY', $code);
  }

  public function status($params) {
    parent::status($params);
    $this->transaction = $params['Token'];
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $_SERVER["REQUEST_URI"],
      'status' => isset($params['Status']) ? $params['Status'] : null,
      'description' => isset($params['Status']) ? $params['Status'] : null,
      'request' => json_encode($_REQUEST),
      'response' => null
    ]);

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

    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $this->api['sale-details'],
      'status' => isset($response['Status']) ? $response['Status'] : null,
      'description' => isset($response['DebugMessage']) ? $response['DebugMessage'] : null,
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
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

  public function post_process($params) {
    $this->transaction = $_REQUEST['Token'];
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
    if (!isset($response['Status']) || $response['Status'] != 'VERIFIED') {
      throw new Exception(isset($response['Status']) ? $response['Status'] : 'UNKOWN_ERROR');
    }

    //if ($params['Operation'] == 2 && isset($params['payments']) && $params['payments'] == "monthly") {
    //  if ($this->param('recurr_at') == 'post' && $this->param('reurring') == 'provider') return($this->recur_by_provider($params));
    //}
    return($code);
  }

  public function pre_process($params) {
    $post = [];

    /*$postData = array(
      'Custom1'=>$order->id,
      'Custom2'=>$wpml_token,
      'Custom3'=>$ipn_integration,
      'Custom4'=>get_current_user_id(),
      'Custom5'=>$_POST['wc-icredit_payment-new-payment-method'],
    );*/

    $post['CreditFromPayment'] = $this->param('credit') ? : 0;

    $post['GroupPrivateToken'] = $this->password;
    $post['IPNMethod'] = 1; // 1 - POST, 2 - GET
    
    $items = [[
      'Id' => 0,
      'CatalogNumber' => isset($params['product_code']) ? $params['product_code'] : null,
      'UnitPrice' => $params['amount'],
      'Quantity' => 1,
      'Description' => $params['product']
    ]];
    $post['Items'] = $items;
    $post['HideItemList'] = true;
    // $post['Discount'] = 0;
    $post['CreateToken'] = $this->param('tokenize') ? true : false;
    $post['SaleType'] = intval($this->param('operation'));
    $post['ExemptVAT'] = $this->param('vat_free') ? true : false;

    // ProjectId, AgentId, CustomerId, IdNumber, FaxNumber, POB, Reference, Discount
    // Custom1 - Custom9

    $token = false;
    if ($token) {
      $post['CreditcardToken'] = $token;
    }

    if (isset($params['payment_id']) && $params['payment_id']) $post['Order'] = $params['payment_id'];

    if (isset($params['first_name']) && $params['first_name']) $post['CustomerFirstName'] = $params['first_name'];
    if (isset($params['last_name']) && $params['last_name']) $post['CustomerLastName'] = $params['last_name'];

    if (isset($params['phone']) && $params['phone']) $post['PhoneNumber'] = $params['phone'];
    if (isset($params['mobile']) && $params['mobile']) $post['PhoneNumber2'] = $params['mobile'];
    if (isset($params['email']) && $params['email']) $post['EmailAddress'] = $params['email'];
    
    if (isset($params[SimplePayment::CARD_OWNER_ID]) && $params[SimplePayment::CARD_OWNER_ID]) $post['IdNumber'] = $params[SimplePayment::CARD_OWNER_ID];

    if (isset($params['tax_id']) && $params['tax_id']) $post['VatNumber'] = $params['tax_id'];
    
    if (isset($params['address']) && $params['address']) $post['Address'] = $params['address'];
    if (isset($params['address2']) && $params['address2']) $post['Address'] .=  ' '.$params['address2'];
    if (isset($params['city']) && $params['city']) $post['City'] = $params['city'];
    if (isset($params['zipcode']) && $params['zipcode']) $post['Zipcode'] = $params['zipcode'];

    if (isset($params['comment']) && $params['comment']) $post['Comments'] = $params['comment'];

    $currency = isset($params[SimplePayment::CURRENCY]) && $params[SimplePayment::CURRENCY] ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    if ($currency) {
      if ($currency = self::CURRENCIES[$currency]) $post['Currency'] = $currency;
      else throw new Exception('CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500);
    }

    $language = isset($params['language']) ? $params['language'] : $this->param('language');
    if ($language != '') $post['DocumentLanguage'] = $language;

    if (isset($params['payments']) && $params['payments']) {
      if ($params['payments'] == 'installments') {
        $payments = $this->param('installments_max');
        $post['MaxPayments'] = $payments ? $payments : (isset($params['installments']) ? $params['installments'] : 12);
        $payments = $this->param('installments_min');
        //if ($payments != '') $post['MinNumOfPayments'] = $payments;
        //$post['DefaultNumOfPayments'] = isset($params['installments']) && $params['installments'] ? $params['installments'] : $this->param('installments_default');
      }
    }

    $post['RedirectURL'] = $this->url(SimplePayment::OPERATION_SUCCESS, $params);
    $post['IPNURL'] = $this->url(SimplePayment::OPERATION_STATUS, $params);

    $api = $this->api[$token ? 'charge-token' : 'get-url'];
    $response = $this->post($api, json_encode($post), [ 'Content-Type: application/json' ]);
    $response = json_decode($response, true);

    //$parse = parse_url($response['URL']);
    //parse_str($parse['query'], $parse);
    $this->transaction = isset($response['PublicSaleToken']) ? $response['PublicSaleToken'] :  null;
    
    $this->save([
      'transaction_id' => $this->transaction,
      'url' => $api,
      'status' => isset($response['Status']) ? $response['Status'] : '',
      'description' => isset($response['DebugMessage']) ? $response['DebugMessage'] : '',
      'request' => json_encode($post),
      'response' => json_encode($response)
    ]);
    if (isset($response['Status']) && $response['Status'] != 0) {
      throw new Exception(isset($response['DebugMessage']) && $response['DebugMessage'] ? $response['DebugMessage'] : 'REDIRECT_URL_NOT_PROVIDED', 500);
    }
    return($response);
  }

}
