<?php

defined( 'ABSPATH' ) or exit;

// Make sure Gutenberg is present
if (!function_exists('register_block_type')) return;

register_block_type(
    'simple-payment/simple-payment', array(
    'style'         => 'simple-payment-gb-style-css',
    'editor_script' => 'simple-payment-gb-block-js',
    'editor_style'  => 'simple-payment-gb-editor-css',
    )
);

add_action('init', 'sp_gutenberg_assets');

function sp_gutenberg_assets() { // phpcs:ignore
    include(SPWP_PLUGIN_DIR.'/settings.php');
    global $SPWP_CURRENCIES;

    wp_register_style(
        'simple-payment-gb-style-css', 
        plugin_dir_url( __FILE__ ).'blocks.style.build.css', // Block style CSS.
        array( 'wp-editor' ), 
        null // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.style.build.css' ) // Version: 1.6.4File modification time.
    );
    wp_register_script(
        'simple-payment-gb-block-js',
        plugin_dir_url( __FILE__ ).'blocks.build.js',
        array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-shortcode', 'wp-editor' ), 
        null, // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.build.js' ), // Version: 1.6.4filemtime — Gets file modification time.
        true 
    );
    wp_register_style(
        'simple-payment-gb-editor-css', 
        plugin_dir_url( __FILE__ ).'blocks.editor.build.css', 
        array( 'wp-edit-blocks' ), 
        null // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.editor.build.css' ) // Version: 1.6.4File modification time.
    );

    wp_localize_script(
        'simple-payment-gb-block-js',
        'spGlobal', // Array containing dynamic data for a JS Global.
        [
            'pluginDirPath' => plugin_dir_path( __DIR__ ),
            'pluginDirUrl'  => plugin_dir_url( __DIR__ ),
            'Currencies' => $SPWP_CURRENCIES,
			'Engines' => SimplePaymentPlugin::$engines,
        ]
    );
    
}