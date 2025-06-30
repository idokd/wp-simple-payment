<?php

namespace ACA\SimplePayment\Column\Entry;

use ACA\SimplePayment\Column;

class Product extends Column\Entry {

	public function get_value( $id ) {
		$entry = SimplePaymentPlugin::instance()->get_entry( $id );
		$title_key = $this->get_field_id() . '.1';
		$price_key = $this->get_field_id() . '.2';
		$quantity_key = $this->get_field_id() . '.3';

		$value = '';
		if ( isset( $entry[ $quantity_key ] ) && $entry[ $quantity_key ] ) {
			$value .= $entry[ $quantity_key ] . 'x ';
		}

		$value .= $entry[ $title_key ];
		if ( $entry[ $price_key ] ) {
			$value .= sprintf( '<br />(%s)', $entry[ $price_key ] );
		}

		return $value;
	}

}