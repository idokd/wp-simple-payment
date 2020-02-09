<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;
use DateTime;
use DateInterval;
use SoapClient;
use stdClass;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class Credit2000 extends Engine {

  public $name = 'Credit2000';
  public $interactive = true;
  protected $recurrAt = 'post'; // status

  public static $supports = ['iframe', 'modal', 'tokenization', 'card_owner_id'];

  public $api = [
    'wsdl' => 'https://www.credit2000.co.il/pci_tkn_ver7/wcf/wscredit2000.asmx?WSDL'
    // Old One
    //'http://www.credit2000.co.il/web_2202/wcf/wscredit2000.asmx?WSDL';
  ];


  const LANGUAGES = [ 'HE' => 'Hebrew', 'EN' => 'English' ];
  const CURRENCIES = [ 'ILS' => 1, 'USD' => 2, 'EUR' => 3 ];
  const OPERATIONS = [ 4 => 'Charge', 5 => 'Authorize', 7 => 'Refund' ];
  const CREDIT_TYPES = [ 1 => 'Normal', 2 => 'Installments', 3 => 'Credit', 4 => 'Isracard Flexible', 5 => 'Alfa', 6 => 'USD Based', 7 => 'Visa 30', 8 => 'Club', 9 => 'Club Installments'];

  protected $username = 'TestDcs';
  public $password = 'Dcstest==';

  public function __construct($params = null, $handler = null, $sandbox = true) {
    parent::__construct($params, $handler, $sandbox);
    $this->sandbox = $this->sandbox ? : !($this->param('vendor_name') && $this->param('password'));
  }

  public function process($params) {
    // If coming from a non cvv we have url to redirect to
    if (isset($params['url'])) return($params['url']);
    // if here because we try to charge direct cvv style.
    switch ($this->param('operation')) {
      case 4:
        $response = $this->charge($params);
        break;
      case 5:
        $response = $this->authorize($params);
        break;
      case 7:
        $response = $this->refund($params);
        break;
    }
    return($response);
  }

  public function post_process($params) {
    if (isset($_REQUEST['params']) && $_REQUEST['params']) {
      $this->transaction = $_REQUEST['params'];
      $token = $this->token();
      return($token['returnCode'] == 0);
    }
    if (isset($params['confirmationNumber']) && $params['confirmationNumber']) $this->confirmation_code = $params['confirmationNumber'];
    return($params);
  }

  public function pre_process($params) {
    if ($this->handler->supports('cvv')) {
      return($params);
    }
    $post = [];
    if (!$this->sandbox) {
      $post['vendor_Name'] = $this->param('vendor_name');
      $post['company_Key'] = $this->password;
    } else {
      $post['vendor_Name'] = $this->username; // Cus1802
      $post['company_Key'] = $this->password; // Bytkebd75ALnhDEthkmjjo==
    }
    $post['action_Type'] = $this->param('operation');

    $language = isset($params['language']) ? $params['language'] : $this->param('language');
    if ($language != '') $post['Lang'] = strtoupper($language);

    $currency = isset($params[SimplePayment::CURRENCY]) && $params[SimplePayment::CURRENCY] ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    if ($currency) {
      if ($currency = self::CURRENCIES[$currency]) $post['currency'] = $currency;
      else throw new Exception('CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500);
    }

    $post['host'] = $this->url(SimplePayment::OPERATION_SUCCESS, $params);

    $post['total_Pyment'] = $params[SimplePayment::AMOUNT] * 100; 
    $post['product_Id'] = $params[SimplePayment::PRODUCT]; 
    $post['client_Name'] = $params[SimplePayment::FIRST_NAME] . '/' . $params[SimplePayment::LAST_NAME];

    //$post['tz_Number'] = isset($params[SimplePayment::CARD_OWNER_ID]) && $params[SimplePayment::CARD_OWNER_ID] ? $params[SimplePayment::CARD_OWNER_ID] : ''; //
    $post['card_Reader'] = 2; // 1 - Connected, 2 - Not Connected

    $installments = 1;
    if (isset($params[SimplePayment::PAYMENTS]) && $params[SimplePayment::PAYMENTS] && isset($params[SimplePayment::INSTALLMENTS]) && $params[SimplePayment::INSTALLMENTS]) $installments = $params[SimplePayment::INSTALLMENTS] ? $params[SimplePayment::INSTALLMENTS] : $this->param('installments_default');
    $post['payments_Number'] = $installments;
    
    $post['first_Payment'] = 100; // TODO: Validate probably 100%
    $post['fixed_Amount'] = '000'; // TODO: Probably Recurring Payments amount to recurrenly debit

    $post['purchase_Type'] = 1; //

    $post['reader_Data'] = 0; //
    $post['club'] = 0; //
    $post['confirmation_Source'] = 0; //
    $post['uID'] = 1; // ??
    $post['Approve'] = 0; // ??
    $post['stars'] = 0; // ??
    $post['ValidDate'] = '1212'; // ??
    $post['return_Code'] = 123; //$params['payment_id'];

    if ($this->param('css') != '') $post['StyleSheet'] = $this->callback.(strpos($this->callback, '?') !== false ? '&' : '?').'op=css';
    elseif ($this->param('company_name') || $this->param('company_logo')) $post['StyleSheet'] = $this->param('company_name') . ';' . $this->param('company_logo');
    $response = $this->soap('SendParamToCredit2000', $post); 
    return($response);
  }

  function token($transaction_id = null) {
    $transaction_id = $transaction_id ? $transaction_id : $this->transaction;
    if (!$transaction_id) return(false);
    $post = [];
    $post['uid'] = $transaction_id;
    $post['approveNum'] = ''; // ?
    $post['returnCode'] = ''; // ?
    $post['customerId'] = ''; // ?
    $post['cardType'] = ''; // ?
    $response = $this->soap('getTokenAndApprove', (object) $post);
    if ($response['returnCode'] != 0) throw new Exception('TOKEN_NOT_FOUND_WITH_ENGINE', 500);
    return($response);
  }

  function authorize($params, $transaction_id = null) {
    return($this->charge($params, $transaction_id, 5));
  }

  function refund($params, $transaction_id = null) {
    return($this->charge($params, $transaction_id, 7));
  }

  function charge($params, $transaction_id = null, $operation = 4) {
    $post = [];
    if (!$this->sandbox) {
      $post['vendorName'] = $this->param('vendor_name');
      $post['ClientKey'] = $this->password;
    } else {
      $post['vendorName'] = $this->username; // Cus1802
      $post['ClientKey'] = $this->password; // Bytkebd75ALnhDEthkmjjo==
    }
    $token = $this->token($transaction_id);

    if ($token) {
      $forward = ['approveNum', 'returnCode', 'customerId', 'cardType'];
      foreach ($forward as $key) $post[$key] = isset($token[$key]) ? $token[$key] : '';
      $validation = $token['validDate'];
      $valid = str_split($validation, 2);
      $post['validationMonth'] = $valid[0];
      $post['validationYear']= $valid[1]; // 20'.
      $post['cardNumber'] = $token['getTokenAndApproveResult'];
    } else {
      $post['cardNumber'] = $params[SimplePayment::CARD_NUMBER];
      $post['cvvNumber'] = $params[SimplePayment::CARD_CVV];
      $post['validationMonth'] = $params[SimplePayment::CARD_EXPIRY_MONTH];
      $post['validationYear']= $params[SimplePayment::CARD_EXPIRY_YEAR] - 2000;
      $post['customerId'] = $params['payment_id'];
    }

    $post['actionType'] = $operation;

    $currency = isset($params[SimplePayment::CURRENCY]) && $params[SimplePayment::CURRENCY] ? $params[SimplePayment::CURRENCY] : $this->param('currency');
    if ($currency) {
      if ($currency = self::CURRENCIES[$currency]) $post['currency'] = $currency;
      else throw new Exception('CURRENCY_NOT_SUPPORTED_BY_ENGINE', 500);
    }

    $post['totalPayment'] = $params[SimplePayment::AMOUNT] * 100; 

    $post['tzNumber'] = isset($params[SimplePayment::CARD_OWNER_ID]) ? $params[SimplePayment::CARD_OWNER_ID] : ''; //

    $installments = 1;
    if (isset($params[SimplePayment::PAYMENTS]) && $params[SimplePayment::PAYMENTS] && isset($params[SimplePayment::INSTALLMENTS]) && $params[SimplePayment::INSTALLMENTS]) $installments = $params[SimplePayment::INSTALLMENTS] ? $params[SimplePayment::INSTALLMENTS] : $this->param('installments_default');
    $post['paymentsNumber'] = $installments;
    
    // TODO: review if required on charge after authorize
    $post['firstPayment'] = $params[SimplePayment::AMOUNT] * 100; // TODO: Validate probably 100%
    $post['fixedAmmount'] = 0; // TODO: Probably Recurring Payments amount to recurrenly debit

    if (isset($params[SimplePayment::CARD_OWNER])) $post['FullName'] = $params[SimplePayment::CARD_OWNER];

    //if (isset($params[SimplePayment::FULL_NAME])) $post['FullName'] = $params[SimplePayment::FULL_NAME];
    if (isset($params[SimplePayment::EMAIL])) $post['Email'] = $params[SimplePayment::EMAIL];
    if (isset($params[SimplePayment::PHONE])) $post['Phone'] = $params[SimplePayment::PHONE];
    if (isset($params[SimplePayment::COMMENT])) $post['Coments'] = $params[SimplePayment::COMMENT];

    $post['cardReader'] = 2;
    $post['readerData'] = '';

    $post['returnCode'] = 888;
    $post['confirmationSource'] = 0;
    $post['cardType'] = 0;
    $post['club'] = 0;
    $post['stars'] = 0;

    $post['purchaseType'] = 1;

    
    $post['confirmationNumber'] = 0;
    $post['confirmationSource'] = 0;
    $post['Fax'] = '';

    
    $response = $this->soap('CreditXMLPro', $post);
    if ($response['returnCode'] != 0) throw new Exception('FAILED_CHARGE_WITH_ENGINE', 500);
    return($response);
  }
  
  function soap($action, $params) {
    $soapclient = new SoapClient( $this->api['wsdl'], [
        'trace' => true,
       /* 'stream_context' => stream_context_create([
            'ssl' => [
                //'cafile' => __DIR__	. DIRECTORY_SEPARATOR . 'cert.pem',
                'ciphers' => 'DHE-RSA-AES256-SHA:DHE-DSS-AES256-SHA:AES256-SHA:KRB5-DES-CBC3-MD5:KRB5-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:EDH-DSS-DES-CBC3-SHA:DES-CBC3-SHA:DES-CBC3-MD5:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA:AES128-SHA:RC2-CBC-MD5:KRB5-RC4-MD5:KRB5-RC4-SHA:RC4-SHA:RC4-MD5:RC4-MD5:KRB5-DES-CBC-MD5:KRB5-DES-CBC-SHA:EDH-RSA-DES-CBC-SHA:EDH-DSS-DES-CBC-SHA:DES-CBC-SHA:DES-CBC-MD5:EXP-KRB5-RC2-CBC-MD5:EXP-KRB5-DES-CBC-MD5:EXP-KRB5-RC2-CBC-SHA:EXP-KRB5-DES-CBC-SHA:EXP-EDH-RSA-DES-CBC-SHA:EXP-EDH-DSS-DES-CBC-SHA:EXP-DES-CBC-SHA:EXP-RC2-CBC-MD5:EXP-RC2-CBC-MD5:EXP-KRB5-RC4-MD5:EXP-KRB5-RC4-SHA:EXP-RC4-MD5:EXP-RC4-MD5',
                'CN_Match' => parse_url($this->api['wsdl'])['host'],
                'SNI_enabled' => true,
                'verify_peer' => false, // TODO: Check should be true
                'verify_peer_name' => false
            ]
        ])*/
    ]);
    $response = [];
    $parameters = [];
    foreach ($params as $key => $value) $parameters[$key] = $value;

    switch ($action) {
        case 'SendParamToCredit2000':
            //$parameters = new stdClass();
            //$parameters->parametr = $params;
            $parameters = [];
            $parameters['parametr'] = $params;
            try {
                $result = $soapclient->SendParamToCredit2000($parameters);
                $response['url'] = $result->SendParamToCredit2000Result;
                parse_str(substr($response['url'], strpos($response['url'], '?') + 1),$params);
                $this->transaction = $params['params'];
                $this->save([
                  'transaction_id' => $this->transaction,
                  'url' => $this->api['wsdl'],
                  'status' => isset($response['returnCode']) ? $response['returnCode'] : null,
                  'description' => isset($response['Description']) ? $response['Description'] : null,
                  'request' => json_encode($parameters),
                  'response' => json_encode($result)
                ]);
            } catch (Exception $e) {
              $this->save([
                'url' => $this->api['wsdl'],
                'status' => $e->getCode(),
                'request' => json_encode($parameters),
                'response' => $e->getMessage()
              ]);
              throw $e;
            }
            break;
        case 'getTokenAndApprove':
            try {
                $result = $soapclient->getTokenAndApprove($parameters);
                $response = json_decode(json_encode($response), true);
                $this->save([
                  'transaction_id' => $this->transaction,
                  'url' => $this->api['wsdl'],
                  'status' => isset($response['returnCode']) ? $response['returnCode'] : null,
                  'description' => isset($response['Description']) ? $response['Description'] : null,
                  'request' => json_encode($parameters),
                  'response' => json_encode($result)
                ]);
            } catch (Exception $e) {
              $this->save([
                'url' => $this->api['wsdl'],
                'status' => $e->getCode(),
                'request' => json_encode($parameters),
                'response' => $e->getMessage()
              ]);
              throw $e;
            }
            break;
        case 'CreditXMLPro':
            try {
                $response = $soapclient->CreditXMLPro($parameters);
                $response = json_decode(json_encode($response), true);
                $this->transaction = $response['cardNumber'];
                $this->save([
                  'transaction_id' => $this->transaction,
                  'url' => $this->api['wsdl'],
                  'status' => isset($response['returnCode']) ? $response['returnCode'] : null,
                  'description' => isset($response['Description']) ? $response['Description'] : null,
                  'request' => json_encode($parameters),
                  'response' => json_encode($response)
                ]);
            } catch (Exception $e) {
              $this->save([
                'url' => $this->api['wsdl'],
                'status' => $e->getCode(),
                'request' => json_encode(['params' => $parameters, 'xml' => $soapclient->__getLastRequest()]),
                'response' => $e->getMessage()
              ]);
             //print $soapclient->__getLastRequest(); die;
              throw $e;
            }
            break;
      }
      return($response);
    }
  
}
