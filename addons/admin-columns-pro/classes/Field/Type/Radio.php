<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;

class Radio extends SimplePayment\Field\Field implements SimplePayment\Field\Options {

	public function get_options() {
		return SimplePayment\Utils\FormField::formatChoices( $this->SP_field->choices );
	}

}