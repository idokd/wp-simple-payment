<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;
use SP_Field_Address;

class Address extends SimplePayment\Field\Field implements SimplePayment\Field\Container {

	/**
	 * @return SimplePayment\Field[]
	 */
	public function get_sub_fields() {
		$sub_fields = [];

		foreach ( $this->SP_field->offsetGet( 'inputs' ) as $input ) {
			$sub_id = (int) explode( '.', $input['id'] )[1];

			if ( 6 === $sub_id ) {
				$countries = $this->SP_field instanceof SP_Field_Address ? $this->SP_field->get_countries() : [];

				$sub_fields[ $input['id'] ] = new Select( $this->get_form_id(), $this->get_id(), $this->SP_field, array_combine( $countries, $countries ), false );
			} else {
				$sub_fields[ $input['id'] ] = new Input( $this->get_form_id(), $this->get_id(), $this->SP_field );
			}
		}

		return $sub_fields;
	}

	public function get_sub_field( $id ) {
		$sub_fields = $this->get_sub_fields();

		return array_key_exists( $id, $sub_fields ) ? $sub_fields[ $id ] : null;
	}

}