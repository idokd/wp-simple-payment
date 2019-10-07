<?php

namespace SimplePayment;

class SimplePayment {

  const LICENSE_ACCOUNT = 'cb4df000-f4e5-4c07-85d7-5ff0e0b069de';

  const TRANSACTION_NEW = 'created';
  const TRANSACTION_PENDING = 'pending';
  const TRANSACTION_SUCCESS = 'success';
  const TRANSACTION_FAILED = 'failed';
  const TRANSACTION_CANCEL = 'canceled';

  const OPERATION_SUCCESS = 'success';
  const OPERATION_CANCEL = 'cancel';
  const OPERATION_STATUS = 'status';
  const OPERATION_ERROR = 'error';

  protected $callback = '/sp';
  protected $testing = true;
  protected $engine;
  protected $license;

  protected static $params = [];

  public function __construct() {
    // TODO: check if license dates are  valid and general validity, domain
  }

  public function setEngine($engine) {
    // TODO: check if license is valid for engine
    $class = __NAMESPACE__ . '\\Engines\\' . $engine;
    $this->engine = new $class(self::param());
    $this->engine->handler = $this;
  }

  public static function param($key = null) {
    if (!$key) return(self::$params);
    return(isset(self::$params[$key]) ? self::$params[$key] : null);
  }

  function process($params = []) {
    return($this->engine->process($params));
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

  function callback() {}

  function save($schema, $params) {
    return(true);
  }

}
