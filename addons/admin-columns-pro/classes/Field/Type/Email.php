<?php

namespace ACA\SimplePayment\Field\Type;

class Email extends Input {

	/**
	 * @return string
	 */
	public function get_input_type() {
		return 'email';
	}

}