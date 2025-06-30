<?php

namespace ACA\SimplePayment\Editing\Storage\Entry;

use ACA\SimplePayment\Editing\Storage;
use ACA\SimplePayment\Field\Field;
use ACA\SimplePayment\Value\EntryValue;
use SP_Field_MultiSelect;

class MultiSelect extends Storage\Entry {

	/**
	 * @var Field
	 */
	private $field;

	public function __construct( Field $field ) {
		parent::__construct( $field->get_id() );

		$this->field = $field;
	}

	public function get( int $id ) {
		$entry_value = ( new EntryValue( $this->field ) )->get_value( $id );

		return ( new SP_Field_MultiSelect )->to_array( $entry_value );
	}

	public function update( int $id, $data ): bool {
		return parent::update( $id, $data ? json_encode( $data ) : '' );
	}

}