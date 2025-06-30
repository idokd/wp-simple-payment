<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;
use SPCommon;

class ProductSelect extends SimplePayment\Field\Field implements SimplePayment\Field\Multiple {

	public function get_options() {
		$options = [];

		foreach ( $this->SP_field->offsetGet( 'choices' ) as $choice ) {
			$options[ sprintf( '%s|%s', $choice['value'], SPCommon::to_number( $choice['price'], SPCommon::get_currency() ) ) ] = sprintf( '%s (%s)', $choice['text'], $choice['price'] );
		}

		return $options;
	}

	public function is_multiple() {
		return false;
	}

}