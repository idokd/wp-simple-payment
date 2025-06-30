<?php

namespace ACA\SimplePayment\Field;

use ACA\SimplePayment;
use SP_Field;

class Field implements SimplePayment\Field {

	/**
	 * @var string
	 */
	private $field_id;

	/**
	 * @var is_required
	 */
	protected $is_required;

	public function __construct( $field_id ) {
		$this->field_id = (string) $field_id;
	}

	public function get_id() {
		return $this->field_id;
	}

	public function is_required() {
		return (bool) $this->is_required;
	}

}