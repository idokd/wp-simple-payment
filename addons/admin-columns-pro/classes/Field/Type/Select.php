<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;
use ACA\SimplePayment\Field;
use SP_Field;

class Select extends SimplePayment\Field\Field implements Field\Options, Field\Multiple {

	/**
	 * @var array
	 */
	private $choices;

	/**
	 * @var bool
	 */
	private $multiple;

	public function __construct( $form_id, $field_id, SP_Field $SP_field, array $choices, $multiple ) {
		parent::__construct( $form_id, $field_id, $SP_field );

		$this->choices = $choices;
		$this->multiple = (bool) $multiple;
	}

	public function get_options() {
		return $this->choices;
	}

	public function is_multiple() {
		return $this->multiple;
	}

}