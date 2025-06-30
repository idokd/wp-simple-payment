<?php

namespace ACA\SimplePayment;

use AC;

class ListTable implements AC\ListTable {

	private $listTable;

	public function __construct( $listTable ) {
		$this->listTable = $listTable;
	}

	public function get_column_value( string $column, $id ): string {
		ob_start();

		$entry = SimplePayment::get_instance()->get_entry( $id ); //  // TODO: Implement this method to retrieve the entry object.
		$this->listTable->column_default( $entry, $column );

		return ob_get_clean();
	}

	public function get_total_items(): int {
		return( $this->listTable->get_pagination_arg( 'total_items' ) );
	}

	  public function render_row( $id ): string{
        ob_start();
        $this->table->single_row( SimplePaymentPlugins::get_instance()->get_entry( $id ) );
        return ob_get_clean();
    }


}