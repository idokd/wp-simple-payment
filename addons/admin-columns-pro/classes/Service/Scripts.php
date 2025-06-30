<?php

declare(strict_types=1);

namespace ACA\SimplePayment\Service;

use AC\Asset\Location\Absolute;
use AC\Asset\Script;
use AC\Asset\Style;
use AC\ListScreen;
use AC\Registerable;
use ACA\SimplePayment\ListScreen\Entry;

class Scripts implements Registerable {

    private $location;

    public function __construct( Absolute $location ) {
        $this->location = $location;
    }

    public function register(): void {
        add_action( 'ac/admin_scripts', [ $this, 'admin_scripts' ] );
        add_action( 'ac/table_scripts', [ $this, 'table_scripts' ] );
    }

    public function admin_scripts(): void
    {
        //wp_enqueue_style('gform_font_awesome');
    }

    public function table_scripts(ListScreen $list_screen): void
    {
        if ( ! $list_screen instanceof Entry) {
            return;
        }

        $style = new Style( 'aca-sp-table', $this->location->with_suffix( 'assets/css/table.css' ) );
        $style->enqueue();

        $script = new Script( 'aca-sp-table', $this->location->with_suffix( 'assets/js/table.js' ) );
        $script->enqueue();

        wp_enqueue_script( 'wp-tinymce' );
    }

}