<?php

namespace ACA\SimplePayment\Column\Entry;

use ACA\SimplePayment\Column;
use ACA\SimplePayment\Field\Options;
use ACA\SimplePayment\Settings\ChoiceDisplay;

class Choices extends Column\Entry {

	public function register_settings() {
		$field = $this->get_field();

		$this->add_setting( new ChoiceDisplay( $this, $field instanceof Options ? $field->get_options() : [] ) );
	}

}