<?php

namespace ACA\SimplePayment;

interface Value {

	/**
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function get_value( $id );

}