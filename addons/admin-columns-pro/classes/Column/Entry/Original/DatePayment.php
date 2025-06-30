<?php

namespace ACA\SimplePayment\Column\Entry\Original;

use AC;
use ACA\SimplePayment\Search;
use ACP;

class DatePayment extends AC\Column implements ACP\Search\Searchable {

	public function __construct() {
		$this->set_original( true )
		     ->set_type( 'field_id-payment_date' );
	}

	public function get_raw_value( $id ) {
		$entry = SimplePaymentPlugin::instance()->get_entry( $id );

		return $entry ? $entry['payment_date'] : null;
	}

	public function search() {
		return new Search\Comparison\Entry\DateColumn( 'payment_date' );
	}

	protected function register_settings() {
		$this->add_setting( new AC\Settings\Column\Date( $this ) );
	}

}