<?php

namespace ACA\SimplePayment\Column\Entry;

use ACA\SimplePayment\Column;

class ProductSelect extends Column\Entry {

	public function get_value( $id ) {
		$field = SPFormsModel::get_field( $this->get_form_id(), $this->get_field_id() );

		return $field ? $field->get_value_export( SimplePaymentPlugin::instance()->get_entry( $id ) ) : null;
	}

}