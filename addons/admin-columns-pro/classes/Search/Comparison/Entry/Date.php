<?php

namespace ACA\SimplePayment\Search\Comparison\Entry;

use ACA\SimplePayment\Search;
use ACP;
use ACP\Search\Value;

class Date extends Search\Comparison\Entry {

	public function __construct( $field ) {
		$operators = new ACP\Search\Operators( [
			ACP\Search\Operators::EQ,
			ACP\Search\Operators::LT,
			ACP\Search\Operators::GT,
			ACP\Search\Operators::BETWEEN,
			ACP\Search\Operators::TODAY,
			ACP\Search\Operators::LT_DAYS_AGO,
			ACP\Search\Operators::GT_DAYS_AGO,
		] );

		parent::__construct( $field, $operators, ACP\Search\Value::DATE, new ACP\Search\Labels\Date() );
	}

	protected function create_query_bindings( $operator, Value $value ) {
		if ( $operator === ACP\Search\Operators::TODAY ) {
			$operator = ACP\Search\Operators::EQ;
			$value = new Value(
				date( 'Y-m-d' ),
				$value->get_type()
			);
		}

		return parent::create_query_bindings( $operator, $value );
	}

}