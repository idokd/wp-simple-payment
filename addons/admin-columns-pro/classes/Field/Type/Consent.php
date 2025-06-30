<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment\Field\Field;

class Consent extends Field {

	public function get_consent_text() {
		return $this->SP_field->offsetGet( 'checkboxLabel' );
	}

}