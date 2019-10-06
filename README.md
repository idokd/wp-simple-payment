# Simple Payment Wordpress Plugins


# Theme Payment Gateway Example
```
<?php
namespace SimplePayment\Engines;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

class Theme extends Engine {

  public $name = 'Theme';

    public function __construct() {

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
      // Process any parameters necessary before
      // calling process
      return($params); // Return array with values to be logged
    }

}
