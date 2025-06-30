<?php

namespace ACA\SimplePayment\ListScreen;

use AC;
use ACA\SimplePayment;
use ACA\SimplePayment\TableFactory;
use AC\Type\Uri;
use ACA\SimplePayment\Column;
use ACA\SimplePayment\Column\EntryConfigurator;
use ACP\Editing;
use ACP\Export;

class Entry extends AC\ListScreen implements Editing\ListScreen, Export\ListScreen, AC\ListScreen\ManageValue,
                                             AC\ListScreen\ListTable {
	/**
	 * @var EntryConfigurator
	 */
	private $column_configurator;

	public function __construct(  EntryConfigurator $column_configurator ) {
		$this->column_configurator = $column_configurator;

		$this->set_group( SimplePayment\SimplePayment::GROUP )
		     ->set_page( 'simple-payments' )
		     ->set_screen_id( 'toplevel_page_simple-payments' )
		     ->set_screen_base( 'toplevel_page_simple-payments' )
		     ->set_key( 'simple-payments' )
		     ->set_meta_type( SimplePayment\MetaTypes::SIMPLE_PAYMENT_ENTRY );
	}

	public function list_table(): AC\ListTable {
        return new ListTable( $this->get_list_table() );
    }

    public function manage_value(): AC\Table\ManageValue {
        return new SimplePayment\Table\ManageValue\Entry(new ColumnRepository($this));
    }

	public function editing() {
		return new SimplePayment\Editing\Strategy\Entry( $this->get_list_table() );
	}

	public function export() {
		return new SimplePayment\Export\Strategy\Entry( $this );
	}

    public function get_table_url(): Uri {
        $url = new AC\Type\Url\ListTable( 'admin.php', $this->has_id() ? $this->get_id() : null) ;
        return $url->with_arg( 'page', 'simple-payment' );
    }

	/**
	 * @return string
	 */
	public function get_heading_hookname(): string {
		return 'manage_' . $this->get_screen_id() . '_columns';
	}

	protected function get_object( $id ) {
		return( SimplePaymentPlugin::instance()->get_entry( $id ) );
	}

	public function set_manage_value_callback() {
		add_filter( 'sp_entries_field_value', [ $this, 'manage_value_entry' ], 10, 4 );
	}

	/**
	 * @param string $original_value
	 * @param string $field_id
	 * @param array  $entry
	 *
	 * @return string
	 */
	public function manage_value_entry( $original_value, $field_id, $entry ) {
		$custom_column_value = $this->get_display_value_by_column_name( $field_id, $entry['id'], $original_value );

		if ( $custom_column_value ) {
			return $custom_column_value;
		}

		$value = $this->get_display_value_by_column_name( 'field_id-' . $field_id, $entry['id'], $original_value );

		return $value ?: $original_value;
	}

	/**
	 * @return string
	 */
	public function get_label(): string {
		return( __( 'Transactions', 'simple-payments' ) );
	}

	public function is_current_screen( $wp_screen ): bool {
		return
			strpos( $wp_screen->id, '_page_simple-payments' ) !== false &&
			strpos( $wp_screen->base, '_page_simple-payments' ) !== false;
	}

	protected function get_admin_url() {
		return admin_url( 'admin.php' );
	}
/*
	public function get_screen_link() {
		return add_query_arg( [ 'id' => $this->get_form_id() ], parent::get_screen_link() );
	}
*/
	public function get_list_table() {
		return( new TableFactory() )->create( $this->get_screen_id() );
	}

	public function register_column_types(): void {
		$this->column_configurator->register_entry_columns( $this );

		$this->register_column_types_from_list( [
			Column\Entry\Original\EntryId::class,
			// TODO: Uncomment and implement the following columns as needed.
			/*Column\Entry\Custom\User::class,
			Column\Entry\Original\DateCreated::class,
			Column\Entry\Original\DatePayment::class,
			Column\Entry\Original\EntryId::class,
			Column\Entry\Original\PaymentAmount::class,
			Column\Entry\Original\SourceUrl::class,
			Column\Entry\Original\Starred::class,
			Column\Entry\Original\TransactionId::class,
			Column\Entry\Original\User::class,
			Column\Entry\Original\UserIp::class,*/
		] );
	}

}