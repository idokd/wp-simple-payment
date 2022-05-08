<?php

namespace SimplePayment;

use Exception;

class SimplePayment {

  const VERSION = 1.1;
  const TRANSACTION_NEW = 'created';
  const TRANSACTION_PENDING = 'pending';
  const TRANSACTION_SUCCESS = 'success';
  const TRANSACTION_FAILED = 'failed';
  const TRANSACTION_CANCEL = 'cancelled';

  const OPERATION_SUCCESS = 'success';
  const OPERATION_CANCEL = 'cancel';
  const OPERATION_STATUS = 'status';
  const OPERATION_ERROR = 'error';

  const ENGINE = 'engine'; const METHOD = 'method';

  const TRANSACTION_ID = 'transaction_id'; const CURRENCY = 'currency'; const AMOUNT = 'amount'; const PRODUCT = 'product'; const PRODUCT_CODE = 'product_code'; const PRODUCTS = 'products';

  const FIRST_NAME = 'first_name'; const LAST_NAME = 'last_name'; const FULL_NAME = 'full_name'; const PHONE = 'phone'; const MOBILE = 'mobile'; const EMAIL = 'email';
  
  const CARD_TYPE = 'card_type'; const CARD_OWNER = 'card_owner';  const CARD_NUMBER = 'card_number'; const CARD_EXPIRY_MONTH = 'expiry_month'; const CARD_EXPIRY_YEAR = 'expiry_year'; const CARD_CVV = 'cvv'; const CARD_OWNER_ID = 'card_owner_id'; 

  const ADDRESS = 'address'; const ADDRESS2 = 'address2'; const CITY = 'city'; const STATE = 'state'; const COUNTRY = 'country'; const ZIPCODE = 'zipcode';

  const PAYMENTS = 'payments';  const COMPANY = 'company'; const TAX_ID = 'tax_id';

  const LANGUAGE = 'language'; const COMMENT = 'comment'; const INSTALLMENTS = 'installments';
  
  public static $license;
  protected $callback;
  protected $sandbox = true;
  public $engine;

  protected static $params;

  public function __construct($params = []) {
    self::$params = $params;
  }

  public function setEngine($engine) {
    if ( $engine != 'Custom' && !$this->sandbox ) {
      // TODO: Consider removing  the validate_license here, so customer will not have end of service
      // after end.
      $this->validate_license( self::$license, null, $engine );
      if ( !isset($_SERVER['HTTPS'] ) || !$_SERVER['HTTPS']) throw new Exception('HTTPS_REQUIRED_LIVE_TRANSACTIONS', 500);
    }
    $class = __NAMESPACE__ . '\\Engines\\' . $engine;
    $settings = self::param(strtolower($engine));
    foreach (self::$params as $key => $value) if (!is_array($value) && !isset($settings[$key])) $settings[$key] = $value; 
    $this->engine = new $class($settings, $this, $this->sandbox);
  }

  public static function supports($feature, $engine = null) {
    if (!$engine) {
      $engine = $this->engine;
      $class = get_class($this->engine);
    } else $class = __NAMESPACE__ . '\\Engines\\' . $engine;
    return(in_array($feature, $class::$supports) || self::param(strtolower($engine).'.'.$feature));
  }

  public static function param($key = null, $default = false) {
    if (!$key) return(self::$params);
    if (!self::$params) return($default);
    $keys = explode('.', $key);
    $value = self::$params;
    if (!isset($value[$keys[0]])) return($default);
    foreach ($keys as $k) {
      $value = isset($value[$k]) ? $value[$k] : $default;
    }
    return($value);
  }

  function refund($params = []) {
    return($this->engine->refund($params));
  }

  function recharge($params = []) {
    return($this->engine->recharge($params));
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
    // Return array of data, or thorw exception
    return($this->engine->pre_process($params));
  }

  function recur($params = []) {
    return($this->engine->recur($params));
  }

  function callback() {}

  function save( $params, $schema = null ) {
    return( true );
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
    if (!$license) throw new Exception('NO_LICENSE', 401);
    if ($domain == null) $domain = $_SERVER['SERVER_NAME'];
		$domain = preg_replace('/(^www\\.)?(.*)/', '${2}', $domain, -1);
    if (is_object($license)) $license = json_decode(json_encode($license), true);
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

  private function fetch_license( $key, $domain ) {
    $res = $this->post( 'https://licensing.yalla-ya.com/validate', [
      'body' => [
          'fingerprint' => $domain,
          'key' => $key
      ]
    ]) ;
    return( json_decode( $res ) );
  }

  protected function post($url, $post) {
    $curl = curl_init($url);
    if (isset($post['body']) && is_array($post['body'])) $payload = http_build_query($post['body'], null, '&');
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

  public static function validate($params, $type = null) {
    $errors = [];
    switch ($type) {
        default:
        case 'required':
          $required = [self::CARD_EXPIRY_MONTH, self::CARD_EXPIRY_YEAR, self::CARD_OWNER, self::CARD_NUMBER];
          foreach ($required as $field) if (!isset($params[$field]) || !$params[$field]) $errors[$field] = 'REQUIRED_FIELD_'.strtoupper($field);
          if ($type) break;
        case 'cvv':
        if (isset($params[self::CARD_CVV])) {
          $len = strlen($params[self::CARD_CVV]);
            if ($len < 3 || $len > 4) $errors[self::CARD_CVV] = 'INVALID_CARD_CVV';
          }
          if ($type) break;
        case 'number':
          if (isset($params[self::CARD_NUMBER])) {
            $len = strlen($params[self::CARD_NUMBER]);
            if ($len < 15 || $len > 16) $errors[self::CARD_NUMBER] = 'INVALID_CARD_NUMBER';
          }
          if ($type) break;
        case 'validity':
          if (isset($params[self::CARD_EXPIRY_MONTH])) {
            $expires = \DateTime::createFromFormat('Y-m', $params[self::CARD_EXPIRY_YEAR].'-'.$params[self::CARD_EXPIRY_MONTH]);
            if (!$expires) {
              $errors[self::CARD_EXPIRY_MONTH] = 'INVALID_CARD_EXPIRATION';
              break;
            }
            $expires->modify('+1 month first day of midnight');
            $now = new \DateTime('now');
            if ($expires < $now) $errors[self::CARD_EXPIRY_MONTH] = 'INVALID_CARD_EXPIRATION';
          }
          if ($type) break;
    }
    return($errors);
  }

}
