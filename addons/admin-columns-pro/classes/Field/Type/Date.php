<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;

class Date extends SimplePayment\Field\Field {

	public function get_stored_date_format() {
		return 'Y-m-d';
	}
}