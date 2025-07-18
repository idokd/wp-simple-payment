<?php

namespace ACA\SimplePayment\Search\Comparison;

use ACA\SimplePayment\Search\Query\Bindings;
use ACP\Search\Comparison;
use ACP\Search\Helper\Sql\ComparisonFactory;
use ACP\Search\Operators;
use ACP\Search\Value;

abstract class Entry extends Comparison {

	/**
	 * @var string
	 */
	protected $meta_key;

	public function __construct( $meta_key, Operators $operators, $value_type = null, $labels = null ) {
		parent::__construct( $operators, $value_type, $labels );

		$this->meta_key = (string) $meta_key;
	}

	protected function create_query_bindings( string $operator, Value $value ): Bindings {
		if ( Operators::IS_EMPTY === $operator ) {
			return $this->create_empty_query_bindings();
		}

		$bindings = new Bindings();

		$alias = $bindings->get_entry_meta_table_name_alias();
		$where = ComparisonFactory::create(
			$alias . '.meta_value',
			$operator,
			$value
		);

		$bindings->join_entry_meta_table( $alias, $this->meta_key )
		         ->where( $where->prepare() );

		return $bindings;
	}

	/**
	 * @return Bindings
	 */
	protected function create_empty_query_bindings() {
		$bindings = new Bindings();
		$alias = $bindings->get_entry_meta_table_name_alias();

		$where = sprintf( '%s.meta_value IS NULL', $alias );

		$bindings->join_entry_meta_table( $alias, $this->meta_key, 'LEFT' )
		         ->where( $where );

		return $bindings;
	}

}