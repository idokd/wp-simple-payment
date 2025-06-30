<?php

namespace ACA\SimplePayment\Search\Comparison\Entry;

use ACA\SimplePayment\Search\Query\Bindings;
use ACP;
use ACP\Search\Value;

class Starred extends ACP\Search\Comparison {

	public function __construct() {
		$operators = new ACP\Search\Operators( [
			ACP\Search\Operators::IS_EMPTY,
			ACP\Search\Operators::NOT_IS_EMPTY,
		] );

		parent::__construct( $operators, ACP\Search\Value::STRING );
	}

	protected function create_query_bindings( $operator, Value $value ) {
		$starred_value = $operator === ACP\Search\Operators::IS_EMPTY ? 0 : 1;

		return ( new Bindings )->where( sprintf( '`is_starred` = %d', $starred_value ) );
	}

}