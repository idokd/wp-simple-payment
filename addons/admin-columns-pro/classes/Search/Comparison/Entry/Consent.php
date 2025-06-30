<?php

namespace ACA\SimplePayment\Search\Comparison\Entry;

use ACA\SimplePayment\Search;
use ACP;

class Consent extends Search\Comparison\Entry {

	public function __construct( $field ) {
		$operators = new ACP\Search\Operators( [
			ACP\Search\Operators::IS_EMPTY,
			ACP\Search\Operators::NOT_IS_EMPTY,
		] );

		parent::__construct( $field, $operators, ACP\Search\Value::STRING );
	}

}