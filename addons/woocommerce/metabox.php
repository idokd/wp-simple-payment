<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/** @class WC_SimplePayment_Metabox */
class WC_SimplePayment_Metabox {
	
	protected static $transactions = array();
	
	/** The single instance of the class. */
	protected static $_instance = null;
	
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	
	public function __construct() {
		// Add hooks
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}
	
	/**
	 * Register and enqueue admin scripts.
	 *
	 * @return void
	 */
	public function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		
		/*if ( 'shop_order' === $screen_id ) {
			wp_enqueue_script( 'jquery-ui-accordion' );
			wp_enqueue_script(
				'wc-simple-payment-metabox', 
				SPWP_PLUGIN_URL.'/addons/woocommerce/js/metabox.js', 
				['jquery'], 
				$this->SPWP::$version 
			);
		}*/
	}
	
	/**
	 * Transaction data metabox
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		global $post;
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		
		if ( 'shop_order' === $screen_id && ( self::$transactions = get_post_meta( $post->ID, '_transaction_data' ) ) ) {
			add_meta_box( 'simple-payment-transaction-data', __( 'Simple Payment Transaction Data', 'simple-payment' ), array( $this, 'transaction_data' ), 'shop_order', 'advanced', 'default' );
		}
	}
	
	/**
	 * Transaction data
	 *
	 * @param  object $post
	 * @return void
	 */
	public function transaction_data( $post ) {
		// TODO: show html here
		//wc_get_template( 'order/admin-order-transaction.php', array( 'transactions' => self::$transactions ), null, WC_Pelecard()->plugin_path() . '/templates/' );
	}
	
}
