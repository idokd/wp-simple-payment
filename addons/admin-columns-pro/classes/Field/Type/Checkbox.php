<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;
use SP_Field;

class Checkbox extends SimplePayment\Field\Field {

	/**
	 * @var string
	 */
	private $value;

	/**
	 * @var string
	 */
	private $label;

	public function __construct( $form_id, $field_id, SP_Field $field, $value, $label ) {
		parent::__construct( $form_id, $field_id, $field );

		$this->value = (string) $value;
		$this->label = (string) $label;
	}

	public function get_value() {
		return $this->value;
	}

	public function get_label() {
		return $this->label;
	}

}