# Simple Payment Wordpress Plugin


# Theme Payment Gateway Example

Create your implementation of your Payment Gateways

save it on your active theme directory;

example: theme-simple-payment.php

```
<?php

namespace SimplePayment\Engines;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class Theme extends Engine {

    public $name = 'Theme';

    public function __construct() {
      parent::__construct();
    }

    public function process($params) {
      parent::process($params);
      // Process the transaction, for example
      // - Call payment gateway API
      // - Redirect to the payment gateway url with params

      // Throw Exception or return false if transaction Failed
      return(true);
    }

    public function post_process($params) {
      parent::post_process($params);
      // Process the result of the transactions save

      return(true);
    }

    public function pre_process($params) {
      parent::pre_process($params);
      // Process any parameters necessary before
      // calling process

      // If your payment providers doesn't create a unique payment id use:
      //$this->transaction = $this->uuid();

      // Throw Exception or return false if Failed
      return($params); // Return array with values to be logged and processed
    }

}

```

Make sure to include the file in your theme functions.php

require_once('theme-simple-payment.php');
