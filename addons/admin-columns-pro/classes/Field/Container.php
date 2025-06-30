<?php

namespace ACA\SimplePayment\Field;

use ACA\SimplePayment\Field;

interface Container {

	/**
	 * @return Field[]
	 */
	public function get_sub_fields();

	/**
	 * @param string $id
	 *
	 * @return Field
	 */
	public function get_sub_field( $id );

}