<?php

namespace ACA\SimplePayment\Column\Entry\Custom;

use AC;
use ACA\SimplePayment;
use ACP;

class User extends AC\Column implements ACP\Search\Searchable, ACP\Export\Exportable {

	public function __construct() {
		$this->set_group( SimplePayment\SimplePayment::GROUP );
		$this->set_label( __( 'User', 'codepress-admin-columns' ) );
		$this->set_type( 'entry_user' );
	}

	public function export() {
		return new ACP\Export\Model\StrippedValue( $this );
	}

	public function search() {
		return new SimplePayment\Search\Comparison\Entry\User();
	}

	public function get_value( $id ) {
		$user_id = $this->get_raw_value( $id );

		return $user_id
			? $this->get_formatted_value( $user_id, $user_id )
			: $this->get_empty_char();
	}

	public function get_raw_value( $id ) {
		return SimplePaymentPlugin::instance()->get_entry( $id )[ 'created_by' ];
	}

	protected function register_settings() {
		parent::register_settings();

		$this->add_setting( new \ACP\Settings\Column\User( $this ) );
	}

}