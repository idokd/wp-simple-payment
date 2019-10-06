<?php

namespace SimplePayment;

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

require_once('db/simple-payment-database.php');
sp_uninstall();
