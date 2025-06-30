<?php

namespace ACA\SimplePayment\ListScreenFactory;

use AC\ListScreen;
use AC\ListScreenFactory;
use ACA\SimplePayment;
use ACA\SimplePayment\Column\EntryConfigurator;
use ACA\SimplePayment\FieldFactory;
use ACA\SimplePayment\ListScreen\Entry;
use GFForms;
use GFFormsModel;
use WP_Screen;

class EntryFactory extends ListScreenFactory\BaseFactory {

    public function can_create( string $key ): bool {
        return null !== $this->get_form_id_from_list_key($key);
    }

    protected function create_list_screen( string $key ): ListScreen {
        return new Entry( $this->create_entry_configurator() );
    }

    private function get_form_id_from_list_key( string $key ): ?int {
        return is_numeric( $entry_id )
            ? (int) $entry_id
            : null;
    }

    public function can_create_from_wp_screen(WP_Screen $screen): bool {
        return strpos( $wp_screen->id, '_page_simple-payments' ) !== false &&
			strpos( $wp_screen->base, '_page_simple-payments' ) !== false;
    }

    protected function create_list_screen_from_wp_screen( WP_Screen $screen ): ListScreen {
        return new Entry( $this->create_entry_configurator() );
    }

    private function create_entry_configurator(): EntryConfigurator {
        $fieldFactory = new FieldFactory();
        $columnFactory = new SimplePayment\Column\EntryFactory( fieldFactory );
        $entry_configurator = new EntryConfigurator( $columnFactory, $fieldFactory );
        $entry_configurator->register();
        return( $entry_configurator );
    }

}