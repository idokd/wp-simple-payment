<?php
namespace SimplePayment\Engines;

use SimplePayment;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class Engine {

  public $name = 'Base';
  public $transaction = null;
  public $table_name;
  protected $callback;

  public function __construct() {
    $this->table_name = $wpdb->prefix . 'sp_' . $this->name;
  }

  protected function param($key) {
    return(SimplePayment\SimplePayment::param($key));
  }

  public function process($params) {
    // Process the transaction, for example
    // - Call payment gateway API
    // - Redirect to the payment gateway url with params
    // Return FALSE if transaction failed
    return(true);
  }

  public function post_process($params) {
    // Process the result of the transactions save
    return(true);
  }

  public function pre_process($params) {
    $request = [];
    $response = [];
    $this->record($request, $response);
    return($response);
  }

  public function setCallback($url) {
    $this->callback = $url;
  }

  protected function record($request, $response) {
    global $wpdb;
    $user_id = get_current_user_id();
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
    $result = $wpdb->insert($this->table_name, $params);
    return($result ? $wpdb->insert_id : false);
  }

}
