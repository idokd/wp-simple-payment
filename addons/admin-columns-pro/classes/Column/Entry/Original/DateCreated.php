<?php

namespace ACA\SimplePayment\Column\Entry\Original;

use AC;
use ACA\SimplePayment\Search;
use ACP;

class DateCreated extends AC\Column implements ACP\Search\Searchable {

	public function __construct() {
		$this->set_original( true )
		     ->set_type( 'field_id-date_created' );
	}

	public function get_raw_value( $id ) {
		$entry = SimplePaymentPlugin::instance()->get_entry( $id );

		return $entry ? $entry['date_created'] : null;
	}

	public function search() {
		return new Search\Comparison\Entry\DateColumn( 'date_created' );
	}

}