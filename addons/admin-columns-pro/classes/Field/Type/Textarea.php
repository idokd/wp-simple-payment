<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment\Field\Field;

class Textarea extends Field {

	/**
	 * @return string
	 */
	public function get_input_type() {
		return 'textarea';
	}

}