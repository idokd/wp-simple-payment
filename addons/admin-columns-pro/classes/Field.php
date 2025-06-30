<?php

namespace ACA\SimplePayment;

interface Field {

	/**
	 * @return string
	 */
	public function get_id();

	/**
	 * @return bool
	 */
	public function is_required();

}