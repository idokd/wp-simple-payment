<?php

namespace ACA\SimplePayment\Column\Entry;

use ACA\SimplePayment\Column;

class Name extends Column\Entry {

	public function get_value( $id ) {
		return SimplePaymentPlugin::instance()::get_field( $this->get_form_id(), $this->get_field_id() )->get_value_entry_detail( SimplePaymentPlugin::instance()->get_entry( $id ) );
	}

}