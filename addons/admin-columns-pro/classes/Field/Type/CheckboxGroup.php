<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;

class CheckboxGroup extends SimplePayment\Field\Field
	implements SimplePayment\Field\Options, SimplePayment\Field\Container {

	public function get_options() {
		return SimplePayment\Utils\FormField::formatChoices( $this->SP_field->choices );
	}

	/**
	 * @return Checkbox[]
	 */
	public function get_sub_fields() {
		$fields = [];

		foreach ( $this->SP_field->inputs as $key => $input ) {
			$fields[ $input['id'] ] = new Checkbox( $this->get_form_id(), $this->get_id(), $this->SP_field, $this->SP_field->choices[ $key ]['value'], $input['label'] );
		}

		return $fields;
	}

	public function get_sub_field( $id ) {
		$fields = $this->get_sub_fields();

		return array_key_exists( $id, $fields ) ? $fields[ $id ] : null;
	}

}