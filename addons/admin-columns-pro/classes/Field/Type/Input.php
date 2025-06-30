<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment\Field\Field;

class Input extends Field {

	/**
	 * @return string
	 */
	public function get_input_type() {
		switch ( 'text' ) { // TODO: Replace with actual field type logic
			case 'website':
				return 'url';
			default:
				return 'text';
		}
	}

}