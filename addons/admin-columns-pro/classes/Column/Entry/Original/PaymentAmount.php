<?php

namespace ACA\SimplePayment\Column\Entry\Original;

use AC;
use ACA\SimplePayment\Search;
use ACP;

class PaymentAmount extends AC\Column implements ACP\Search\Searchable {

	public function __construct() {
		$this->set_original( true )
		     ->set_type( 'field_id-payment_amount' );
	}

	public function search() {
		return new Search\Comparison\Entry\PaymentAmount();
	}

}