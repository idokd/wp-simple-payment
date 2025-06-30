<?php

namespace ACA\SimplePayment\Editing\Storage\Entry;

use ACA\SimplePayment\Field\Field;
use ACP\Editing\Storage;

class Checkbox implements Storage {

	/**
	 * @var Field
	 */
	private $field;

	public function __construct( Field $field ) {
		$this->field = $field;
	}

	public function get( int $id ) {
		$entry = SimplePaymentPlugin::instance()->get_entry( $id );

		if ( is_wp_error( $entry ) ) {
			return [];
		}

		$value = [];

		foreach ( array_keys( $this->field->get_sub_fields() ) as $key ) {
			if ( isset( $entry[ $key ] ) && $entry[ $key ] ) {
				$value[] = $entry[ $key ];
			}
		}

		return $value;
	}

	private function get_id_for_value( $value ): ?string {
		foreach ( $this->field->get_sub_fields() as $key => $subfield ) {
			if ( $subfield->get_value() === $value ) {
				return (string) $key;
			}
		}

		return null;
	}

	public function update( int $id, $data ): bool {
		// First remove each value for each subfield
		foreach ( array_keys( $this->field->get_sub_fields() ) as $key ) {
			SimplePaymentPlugin::instance()->update_entry_field( $id, $key, '' );
		}

		if ( ! empty( $data ) ) {
			// Populate subfields if value exists
			foreach ( $data as $item ) {
				$field_id = $this->get_id_for_value( $item );
				if ( $field_id ) {
					SimplePaymentPlugin::instance()->update_entry_field( $id, $field_id, $item );
				}
			}
		}

		return true;
	}

}