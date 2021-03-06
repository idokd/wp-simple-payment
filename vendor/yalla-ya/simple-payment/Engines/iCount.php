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
      'document' => 'https://api.icount.co.il/api/v3.php/doc/create',
      'verify' => 'https://api.icount.co.il/api/v3.php/cc/transactions'
    ];

    public static $supports = ['cvv', 'tokenization', 'card_owner_id'];

    public function process($params) {
      $post = $this->basics($params);
      if ($this->sandbox) $post['is_test'] = true;
      $post['currency_code'] = isset($params[SimplePayment::CURRENCY]) ? $params[SimplePayment::CURRENCY] : $this->param(SimplePayment::CURRENCY); // currency_code (cuurency_id / currency
      $post['is_credit'] = false;
      $post['sum'] = $params[SimplePayment::AMOUNT];
      if (isset($params[SimplePayment::PAYMENTS]) && is_numeric($params[SimplePayment::PAYMENTS])) $post['num_of_payments'] = $params[SimplePayment::PAYMENTS];

      $status = $this->post($this->api['bill'], $post);
      $response = json_decode($status, true);
      $this->save([
        'transaction_id' => $this->transaction,
        'url' => $this->api['bill'],
        'status' => $response['status'], 
        'description' => isset($response['error_description']) ? $response['error_description'] : $response['reason'],
        'request' => json_encode($post),
        'response' => json_encode($response)
      ]);
      if (!$response['status']) {
       throw new Exception($response['error_description'], intval($response['status']));
      }
      
      $params['currency_code'] = $response['currency_code'];
      $params['confirmation_code'] = $response['confirmation_code'];
      $params['cc_card_type'] = $response['cc_card_type'];
      $params['cc_type'] = $response['cc_type'];
      $this->confirmation_code = $response['confirmation_code'];
      return($params);
    }

    public function post_process($params) {
      parent::post_process($params);

      $doctype = $this->param('doc_type');
      if (!$doctype || $doctype == 'none') return($params);
      // Process the result of the transactions save

      $post = $this->basics($params, false);

      $post['doc_title'] = $params[SimplePayment::PRODUCT];
      $post['doctype'] = $doctype;
      if (isset($params[SimplePayment::LANGUAGE])) $post['lang'] = $params[SimplePayment::LANGUAGE];

      //vat_percent, tax_exempt
      $post['currency_code'] = $params['currency_code'];
      $amount = $params[SimplePayment::AMOUNT];
      // Amount to be in ILS only
      //$post['totalsum'] = $amount;
     // $post['totalwithvat'] = $amount;
      //$post['totalpaid'] = $amount;
      //$post['paid'] = $amount;
      $item = [
        'description' => $params[SimplePayment::PRODUCT],  
        'quantity' => 1
      ];
      $post['items'] = [];
      if ($this->param('doc_vat') == 'exempt') {
        $item['unitprice_exempt'] = $amount;
        $item['unitprice'] = $amount;
      } else if ($this->param('doc_vat') == 'include') {
        $item['unitprice_incvat'] = $amount;
      } else $item['unitprice'] = $amount;
      if (isset($params[SimplePayment::PRODUCT_CODE])) $item['sku'] = $params[SimplePayment::PRODUCT_CODE];
      $post['items'][] = $item; 
      // unitprice_incvat
      /*
      $date = new DateTime();
      $post['paydate'] =  $date->format('Y-m-y');
      $post['duedate'] =  $date->format('Y-m-y');
      */

      if ($this->param('auto_invoice')) $post['autoinvoice_cc'] = $this->param('auto_invoice');
      if ($this->param('email_document')) $post['send_email'] = $this->param('email_document');

      $post['sanity_string'] = $this->transaction;
      $status = $this->post($this->api['document'], $post);
      $response = json_decode($status, true);
      $this->save([
        'transaction_id' => $this->transaction,
        'url' => $this->api['document'],
        'status' => $response['status'],
        'description' => isset($response['error_description']) ? $response['error_description'] : $response['reason'],
        'request' => json_encode($post),
        'response' => json_encode($response)
      ]);
      if (!$response['status']) {
       throw new Exception($response['error_description'], intval($response['status']));
      }
      return(true);
    }

    public function basics($params, $cc = true) {
      $post = [];
      $post['cid'] = $this->param('business');
      $post['user'] = $this->param('username');
      $this->password = $this->param('password');
      $post['pass'] = $this->password;
      $post['client_name'] = isset($params[SimplePayment::FULL_NAME]) ? $params[SimplePayment::FULL_NAME] : $params[SimplePayment::CARD_OWNER];
      if (isset($params[SimplePayment::TAX_ID])) $post['vat_id'] = $params[SimplePayment::TAX_ID];
      // custom_client_id
      if (isset($params[SimplePayment::EMAIL])) $post['email'] = $params[SimplePayment::EMAIL]; 
      if ($cc) {
        if (isset($params['cc_type'])) $post['cc_type'] = $params['cc_type']; // else maybe= $params[SimplePayment::CARD_TYPE]
        if (isset($params['cc_token_id'])) $post['cc_token_id'] = $params['cc_token_id'];
        else {
          $post['cc_number'] = $params[SimplePayment::CARD_NUMBER];
          $post['cc_cvv'] = $params[SimplePayment::CARD_CVV];
          $post['cc_validity'] = $params[SimplePayment::CARD_EXPIRY_YEAR].'-'.$params[SimplePayment::CARD_EXPIRY_MONTH];
          $post['cc_holder_name'] = $params[SimplePayment::CARD_OWNER];
          if (isset($params[SimplePayment::CARD_OWNER_ID])) $post['cc_holder_id'] = $params[SimplePayment::CARD_OWNER_ID];
        }
      } else {
        $post['cc'] = [
            'sum' => $params[SimplePayment::AMOUNT],
            'card_type' => $params['cc_type'],
            'card_number' => substr($params[SimplePayment::CARD_NUMBER], -4),
            'exp_year' => $params[SimplePayment::CARD_EXPIRY_YEAR],
            'exp_month' => $params[SimplePayment::CARD_EXPIRY_MONTH],
            'holder_id' => isset($params[SimplePayment::CARD_OWNER_ID]) ? $params[SimplePayment::CARD_OWNER_ID] : null,
            'holder_name' => $params[SimplePayment::CARD_OWNER],
            'confirmation_code' => $params['confirmation_code'],
        ];
      }

      return($post);
    }

    public function pre_process($params) {
      parent::pre_process($params);
      $this->transaction = self::uuid();
      $params[SimplePayment::TRANSACTION_ID] = $this->transaction;
      $post = $this->basics($params);
      if ($this->param('use_storage')) {
        $status = $this->post($this->api['store'], $post);
        $response = json_decode($status, true);
        $this->save([
          'transaction_id' => $this->transaction,
          'url' => $this->api['store'],
          'status' => $response['status'],
          'description' => isset($response['error_description']) ? $response['error_description'] : $response['reason'],
          'request' => json_encode($post),
          'response' => json_encode($response)
        ]);
        if (!$response['status']) {
          throw new Exception($response['error_description'], intval($response['status']));
        }
        $params['cc_token_id'] = $response['api']['cc_token_id'];
      }
      return($params);
    }

    public static function uuid() {
      $uuid = parent::uuid();
      $uuid = str_replace( '-', '', $uuid );
      return( base64_encode( pack( 'h*', $uuid ) ) );
    }
}
