<?php

namespace SimplePayment;

use Exception;

class SimplePayment {

  const TRANSACTION_NEW = 'created';
  const TRANSACTION_PENDING = 'pending';
  const TRANSACTION_SUCCESS = 'success';
  const TRANSACTION_FAILED = 'failed';
  const TRANSACTION_CANCEL = 'canceled';

  const OPERATION_SUCCESS = 'success';
  const OPERATION_CANCEL = 'cancel';
  const OPERATION_STATUS = 'status';
  const OPERATION_ERROR = 'error';
  const OPERATION_ZAPIER = 'zapier';

  const TRANSACTION_ID = 'transaction_id'; const CURRENCY = 'currency'; const AMOUNT = 'amount'; 

  const FIRST_NAME = 'first_name'; const LAST_NAME = 'last_name'; const FULL_NAME = 'full_name'; const PHONE = 'phone'; const MOBILE = 'mobile'; const EMAIL = 'email';
  
  const CARD_OWNER = 'card_owner';  const CARD_NUMBER = 'card_number'; const CARD_EXPIRY_MONTH = 'expiry_month'; const CARD_EXPIRY_YEAR = 'expiry_year'; const CARD_CVV = 'cvv';

  const PAYMENTS = 'payments';  const TAX_ID = 'tax_id';

  protected $callback;
  protected $sandbox = true;
  protected $engine;
  protected $license;

  protected static $params;

  public function __construct($params = []) {
    self::$params = $params;
  }

  public function setEngine($engine) {
    if ($engine != 'Custom' && !$this->sandbox) {
      $this->validate_license($this->license, null, $engine);
      if (!isset($_SERVER['HTTPS']) || !$_SERVER['HTTPS']) throw new Exception('HTTPS_REQUIRED_LIVE_TRANSACTIONS', 500);
    }
    $class = __NAMESPACE__ . '\\Engines\\' . $engine;
    $this->engine = new $class(self::param(strtolower($engine)), $this, $this->sandbox);
  }

  public static function param($key = null, $default = false) {
    if (!$key) return(self::$params);
    if (!self::$params) return($default);
    $keys = explode('.', $key);
    $value = self::$params;
    if (!isset($value[$keys[0]])) return($default);
    foreach ($keys as $k) {
      $value = isset($value[$k]) ? $value[$k] : null;
    }
    return($value);
  }

  function process($params = []) {
    return($this->engine->process($params));
  }

  function status($params = []) {
    return($this->engine->status($params));
  }

  function post_process($params = []) {
    if ($this->engine->post_process($params)) {
      $this->status = self::TRANSACTION_SUCCESS;
      // TODO: run sucess webhook if necessary -
      return(true);
    } else {
      $this->status = self::TRANSACTION_FAILED;
      // TODO: run failed webhook if necessary.
    }
    return(false);
  }

  function pre_process($params = []) {
    return($this->engine->pre_process($params));
  }

  function recur($params = []) {
    return($this->engine->recur($params));
  }

  function callback() {}

  function save($schema, $params) {
    return(true);
  }

  protected function validate_key($key, $domain = null) {
      if ($domain == null) $domain = $_SERVER['SERVER_NAME'];
      $license = $this->fetch_license($key, $domain);
      if (isset($license->errors)) {
        $error = $license->errors[0];
        $msg = "{$error->title}: {$error->detail}";
        if (isset($error->source)) {
          $msg = "{$error->title}: {$error->source->pointer} {$error->detail}";
        }
        throw new Exception($msg, 401);
      }
      return($this->validate_license($license, $domain));
  }

  private function validate_license($license, $domain = null, $engine = null) {
    if ($domain == null) $domain = $_SERVER['SERVER_NAME'];
    if (is_object($license)) $license = json_decode(json_encode($license), true); $license;
    $meta = isset($license['meta']['valid']) ? $license['meta'] : $license;
    if (!isset($meta['valid']) || !$meta['valid']) throw new Exception(isset($meta['constant']) ? $meta['constant'] : 'INVALID', 401);
    $metadata = isset($license['data']['attributes']['metadata']) ? $license['data']['attributes']['metadata'] : $license['meta'];
    $attributes = isset($license['data']['attributes']) ? $license['data']['attributes'] : $license;

    $expiry = isset($attributes['expiry']) ? $attributes['expiry'] : null;
    if ($expiry) {
      $expiry = strtotime($expiry);
      if ($expiry < time()) throw new Exception('EXPIRED', 401);
    }

    $suspended = isset($attributes['suspended']) ? $attributes['suspended'] : null;
    if ($suspended) throw new Exception('SUSPENDED', 401);

    $domains = isset($metadata['domain']) ? $metadata['domain'] : null;
    if (!$domains) throw new Exception('FINGERPRINT_SCOPE_MISSING', 401);
    else if ($domains != 'ANY' && !in_array($domain, explode(',', $domains))) throw new Exception('FINGERPRINT_SCOPE_ERROR', 401);

    if ($engine) {
      $engines = isset($metadata['engine']) ? $metadata['engine'] : null;
      if (!$engines) throw new Exception('ENGINE_SCOPE_MISSING', 401);
      else if ($engines != 'ANY' && !in_array($engine, explode(',', $engines))) throw new Exception('ENGINE_SCOPE_ERROR', 401);
    }
    return($license);
  }

  private function fetch_license($key, $domain) {
    $res = $this->post('https://licensing.yalla-ya.com/validate', [
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
      'body' => json_encode([
          'fingerprint' => $domain,
          'key' => $key
      ])
    ]);
    return(json_decode($res));
  }

  protected function post($url, $post) {
    $curl = curl_init($url);
    if (isset($post['body']) && is_array($post['body'])) $payload = $post['body'];
    else $payload = $post['body'];
    $options = [
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_VERBOSE => TRUE,
      CURLOPT_STDERR => $verbose = fopen(SPWP_PLUGIN_DIR . '/curl.log', 'a+'),
      CURLOPT_TIMEOUT => 10,
      CURLOPT_HTTPHEADER => isset($post['headers']) ? $post['headers'] : [],
    ];
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    if (!empty($error)) throw new Exception($error, curl_errno($curl));
    curl_close($curl);
    return($response);
  }

  public static function tofloat($num) {
    $dotPos = strrpos($num, '.');
    $commaPos = strrpos($num, ',');
    $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
        ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);
    if (!$sep) {
        return floatval(preg_replace("/[^0-9]/", "", $num));
    }
    return floatval(
        preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' .
        preg_replace("/[^0-9]/", "", substr($num, $sep+1, strlen($num)))
    );
}
}
