<?php

 // Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define('SPGB_PLUGIN_URL', plugin_dir_url(SPWP_PLUGIN_FILE).'addons/gutenberg'.);
define('SPGB_PLUGIN_DIR', plugin_dir_path(__FILE__));


class SimplePaymentInstantBuilder {

    /** Refers to a single instance of this class. */
    private static $instance = null;

    /**
     * Creates or returns an instance of this class.
     *
     * @return  SimplePaymentInstantBuilder A single instance of this class.
     */
    public static function get_instance() {

        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;

    } // end get_instance;


	/**
	 * Enqueue Gutenberg block assets for both frontend + backend.
	 *
	 * `wp-blocks`: includes block type registration and related functions.
	 *
	 * @since 1.0.0
	 */

    private function __construct() {

		// Hook: Frontend assets.
		add_action( 'enqueue_block_assets', [$this, 'fronted_block_assets']);
		// Hook: Editor assets.
		add_action( 'enqueue_block_editor_assets', [$this, 'editor_backend_assets']);
		add_filter( 'body_class', [$this, 'body_class']);
		add_filter( 'admin_body_class', [$this, 'body_class']);


    } // end constructor

    /*--------------------------------------------*
     * Functions
     *--------------------------------------------*/

		function fronted_block_assets() {
			// Styles.
			wp_enqueue_style(
				'sp-gb-styles-css',
				SPGB_PLUGIN_URL.'dist/blocks.style.build.css',
				array()
			);
		}


	/**
	 * Enqueue Gutenberg block assets for backend editor.
	 *
	 * `wp-blocks`: includes block type registration and related functions.
	 * `wp-element`: includes the WordPress Element abstraction for describing the structure of your blocks.
	 * `wp-i18n`: To internationalize the block's text.
	 *
	 * @since 1.0.0
	 */
	function editor_backend_assets() {
		// Scripts.
		wp_enqueue_script(
			'sp-gb-js', // Handle.
			SPGB_PLUGIN_URL.'/dist/blocks.build.js', // Block.build.js: We register the block here. Built with Webpack.
			array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-editor' ), // Dependencies, defined above.
			true // Enqueue the script in the footer.
		);

		// Styles.
		wp_enqueue_style(
			'sp-gb-css', // Handle.
			SPGB_PLUGIN_URL.'dist/blocks.editor.build.css', // Block editor CSS.
			array( 'wp-edit-blocks' ) // Dependency to include the CSS after it.
			// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.editor.build.css' ) // Version: filemtime — Gets file modification time.
		);

		$pathToPlugin = SPGB_PLUGIN_URL.'dist/';
		wp_add_inline_script( 'wp-blocks', 'var sp_gutenberg_path = "' .$pathToPlugin.'"', 'before');
	}
	//Load body class
	function body_class( $classes ) {
		if ( is_array($classes) ){ $classes[] = 'sp-body'; }
		else { $classes. = 'sp-body'; }
		return $classes;
	}

} // end class

SimplePaymentInstantBuilder::get_instance();
