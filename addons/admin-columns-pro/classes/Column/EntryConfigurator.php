<?php

namespace ACA\SimplePayment\Column;

use AC;
use ACA\SimplePayment\Column;
use ACA\SimplePayment\FieldFactory;
use ACA\SimplePayment\ListScreen;

final class EntryConfigurator implements AC\Registerable {

	private $form_id;

	private $column_factory;

	private $field_factory;

	public function __construct( EntryFactory $column_factory, FieldFactory $field_factory ) {
		$this->column_factory = $column_factory;
		$this->field_factory = $field_factory;
	}

	public function register():void {
		add_action( 'ac/list_screen/column_created', [ $this, 'configure_column' ] );
	}

	public function configure_column( AC\Column $column ) {
		if ( ! $column instanceof Column\Entry ) {
			return;
		}

		$list_screen = $column->get_list_screen();

		if ( ! $list_screen instanceof ListScreen\Entry ) {
			return;
		}

		$column->set_field( $this->field_factory->create( $this->get_field_id_by_type( $column->get_type() ), $this->form_id ) );
	}

	private function get_field_id_by_type( $type ) {
		return str_replace( 'field_id-', '', $type );
	}

	public function register_entry_columns( ListScreen\Entry $list_screen ): void {
		foreach ( ( new AC\DefaultColumnsRepository() )->get( $list_screen->get_key() ) as $type => $label ) {
			$field_id = $this->get_field_id_by_type( $type );

			if ( ! $this->column_factory->has_field( $field_id ) ) {
				continue;
			}

			$column = $this->column_factory->create( $field_id );
			$column->set_type( $type )
			       ->set_label( $label )
			       ->set_list_screen( $list_screen );

			$this->configure_column( $column );

			$list_screen->register_column_type( $column );
		}
	}

}