<?php

namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class iCount extends Engine {

    static $CARD_TYPES = [ 'VISA' => 1, 'MASTERCARD' => 2, 'AMEX' => 3, 'DINERS' => 5, 'JCB' => 6, 'MAESTRO' => 7, 'LEUMICARD' => 8, 'ISRACARD' => 22 ];

    public $name = 'iCount';
    public $api = [
      'bill' => 'https://api.icount.co.il/api/v3.php/cc/bill',
      'store' => 'https://api.icount.co.il/api/v3.php/cc_storage/store',
      'document' => 'https://api.icount.co.il/api/v3.php/doc/create'
    ];

    public function process($params) {
      $post = $params;
      if ($this->sandbox) $post['is_test'] = true;
      $post['currency_code'] = $this->param(SimplePayment::CURRENCY); // currency_code (cuurency_id / currency
      $post['is_credit'] = false;
      $status = $this->post($this->api['bill'], $post);
      $response = json_decode($status, true);
      $this->save([
        'transaction_id' => $this->transaction,
        'url' => $this->api['bill'],
        'status' => $response['status'],
        'description' => $response['error_description'],
        'request' => json_encode($post),
        'response' => json_encode($response)
      ]);
      if (!$response['status']) {
        throw new Exception($response['error_description'], intval($response['status']));
      }

      // num_of_payments/ sum
     // cc_token_id
     // cc_number, cc_type,  cc_cvv, cc_validity, cc_holder_id, cc_holder_name

      // Process the transaction, for example
      // - Call payment gateway API
      // - Redirect to the payment gateway url with params

      // Use: $this->url(SimplePayment::OPERATION_SUCCESS) to obtain success response
      // Throw Exception if transaction Failed
      // return true if handaled internally, false if to keep processing

      // confirmation_code, cc_type, success , error_details, error_description , reason, status
      return(false);
    }

    public function post_process($params) {
      parent::post_process($params);
      // Process the result of the transactions save
      // doctype invrec
      // paydate, duedate, currency_code
      // tax_exempt, vat_percent
      // totalsum
      // totalwithvat, paid, totalpaid
      // cc
      // autoinvoice_cc
      // doc_title
      // sanity_string
      // doc_lang
      // send_email
      // "items" => Array(
    /*Array(
      "description" => "First item",
      "unitprice" => 50,
      "quantity" => 1,
      ),*/
      return(true);
    }

    public function pre_process($params) {
      $this->transaction = $this->uuid();
      parent::pre_process($params);
      $params[SimplePayment::TRANSACTION_ID] = $this->transaction;
      $post = [];
      $post['cid'] = $this->param('business');
      $post['user'] = $this->param('username');
      $post['pass'] = $this->param('password');
  
      $post['client_name'] = isset($params[SimplePayment::FULL_NAME]) ? $params[SimplePayment::FULL_NAME] : $params[SimplePayment::CARD_OWNER];
      if (isset($params[SimplePayment::TAX_ID])) $post['vat_id'] = $params[SimplePayment::TAX_ID];
      // custom_client_id
      //$params['cc_type'] = $params[SimplePayment::CARD_TYPE]
      // custom_client_id,  , email  , cc_holder_id

      if ($this->param('use_storage')) {
        $post['cc_number'] = $params[SimplePayment::CARD_NUMBER];
        $post['cc_cvv'] = $params[SimplePayment::CARD_CVV];
        $post['cc_validity'] = $params[SimplePayment::CARD_EXPIRY_YEAR].'-'.$params[SimplePayment::CARD_EXPIRY_MONTH];
        $post['cc_holder_name'] = $params[SimplePayment::CARD_OWNER];
        $status = $this->post($this->api['store'], $post);
        $response = json_decode($status, true);
        $this->save([
          'transaction_id' => $this->transaction,
          'url' => $this->api['store'],
          'status' => $response['status'],
          'description' => $response['error_description'],
          'request' => json_encode($post),
          'response' => json_encode($response)
        ]);
        if (!$response['status']) {
          throw new Exception($response['error_description'], intval($response['status']));
        }
        $post['cc_token_id'] = $response['api']['cc_token_id'];
      } else {
        $post['cc_number'] = $params[SimplePayment::CARD_NUMBER];
        $post['cc_cvv'] = $params[SimplePayment::CARD_CVV];
        $post['cc_validity'] = $params[SimplePayment::CARD_EXPIRY_YEAR].'-'.$params[SimplePayment::CARD_EXPIRY_MONTH];
        $post['cc_holder_name'] = $params[SimplePayment::CARD_OWNER];
      }
      return($post);
    }

}
