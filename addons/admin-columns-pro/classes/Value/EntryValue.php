<?php

namespace ACA\SimplePayment\Value;

use ACA\SimplePayment\Field\Field;
use ACA\SimplePayment\Value;

class EntryValue implements Value {

	/**
	 * @var Field
	 */
	private $field;

	public function __construct( Field $field ) {
		$this->field = $field;
	}

	public function get_value( $id ) {
		$entry = SimplePaymentPlugin::instance()->get_entry( $id );
		$value = isset( $entry[ $id ] ) ? $entry[ $id ] : '';
		return( $value );
	}

}