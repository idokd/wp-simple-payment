<?php

namespace ACA\SimplePayment\Column;

use AC;
use ACA\SimplePayment;
use ACA\SimplePayment\Editing;
use ACA\SimplePayment\Export;
use ACA\SimplePayment\Search;
use ACP;
use SPAPI;

class Entry extends AC\Column implements ACP\Export\Exportable, ACP\Editing\Editable, ACP\Search\Searchable {

	/**
	 * @var SimplePayment\Field\Field
	 */
	private $field;

	public function __construct() {
		$this->set_original( true );
	}

	public function set_field( SimplePayment\Field\Field $field ) {
		$this->field = $field;
	}

	public function get_value( $id ) {
		return $this->get_formatted_value( $this->get_entry_value( $id ), $id );
	}

	public function get_entry_value( $id ) {
		return ( new SimplePayment\Value\EntryValue( $this->field ) )->get_value( $id );
	}

	public function get_raw_value( $id ) {
		$entry = SimplePayment::get_instance()->get_entry( $id );

		return ! is_wp_error( $entry ) && isset( $entry[ $this->get_field_id() ] )
			? $entry[ $this->get_field_id() ]
			: false;
	}

	protected function get_field() {
		return $this->field;
	}

	public function get_form_id() {
		return $this->field->get_form_id();
	}

	public function get_field_id() {
		return $this->field->get_id();
	}

	public function export() {
		return ( new Export\Model\EntryFactory )->create( $this, $this->get_field() );
	}

	public function editing() {
		return ( new Editing\EntryServiceFactory )->create( $this->get_field() );
	}

	public function search() {
		return ( new Search\Comparison\EntryFactory )->create( $this->get_field() );
	}
}