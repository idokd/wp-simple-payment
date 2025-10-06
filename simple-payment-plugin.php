<?php
/**
 * Plugin Name: Simple Payment
 * Plugin URI: https://simple-payment.yalla-ya.com
 * Description: Simple Payment enables integration with multiple payment gateways, and customize multiple payment forms.
 * Version: 2.4.7
 * Author: Ido Kobelkowsky / yalla ya!
 * Author URI: https://github.com/idokd
 * License: GPLv2
 * Text Domain: simple-payment
 * Domain Path: /languages
 * WC tested up to: 9.9.5 
 * WC requires at least: 2.6
 * Requires PHP: 7.4
 */

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// TODO: Fix double post success calling, by checking payment status
// TODO: Change admin payment listing to use standard wordpress / seperate excel export
// TODO: validate refund and add refund on all other engines
// TODO: better configure/test subscription  functionlity (differ between provdier/internal)

define( 'SPWP_PLUGIN_FILE', __FILE__ );
define( 'SPWP_PLUGIN_DIR', dirname( SPWP_PLUGIN_FILE ) );
define( 'SPWP_PLUGIN_URL', plugin_dir_url( __FILE__  ) );
define( 'SPWP_VERSION', get_file_data( __FILE__, [ 'Version' => 'Version' ], false )[ 'Version' ] );

require_once( SPWP_PLUGIN_DIR . '/vendor/autoload.php' );

class SimplePaymentPlugin extends SimplePayment\SimplePayment {

	const OP = 'op';
	const SPOP = '_spop';
	const PAYMENT_ID = 'payment_id';
	const TYPE_FORM = 'form'; const TYPE_BUTTON = 'button'; const TYPE_TEMPLATE = 'template'; const TYPE_HIDDEN = 'hidden';

	const OPERATION_CSS = 'css';
	const OPERATION_PCSS = 'pcss';
	const OPERATION_REDIRECT = 'redirect';
	const USERNAME = 'username';

	public static $version;
	public static $instance;

	public static $table_name = 'sp_transactions';
	public static $table_name_metadata = 'sp_transactions_metadata';
	
	public static $engines = [ 'PayPal', 'Cardcom', 'iCount', 'PayMe', 'iCredit', 'CreditGuard', 'Meshulam', 'YaadPay', 'Credit2000', 'Custom', 'Test' ];

	public static $fields = [ 'payment_id', 'transaction_id', 'token', 'target', 'type', 'callback', 'display', 'concept', 'redirect_url', 'source', 'source_id', self::ENGINE, self::AMOUNT, self::PRODUCT, self::PRODUCT_CODE, self::PRODUCTS, self::METHOD, self::FULL_NAME, self::FIRST_NAME, self::LAST_NAME, self::PHONE, self::MOBILE, self::ADDRESS, self::ADDRESS2, self::EMAIL, self::COUNTRY, self::STATE, self::ZIPCODE, self::PAYMENTS, self::INSTALLMENTS, self::CARD_CVV, self::CARD_EXPIRY_MONTH, self::CARD_EXPIRY_YEAR, self::CARD_NUMBER, self::CURRENCY, self::COMMENT, self::CITY, self::COMPANY, self::TAX_ID, self::CARD_OWNER, self::CARD_OWNER_ID, self::LANGUAGE ];

	public static $max_retries = 5;

	public $payment_id = null;

	protected $payment_page = null;
	protected $secrets = [];

	protected $defaults = [
		'form_type' => 'legacy',
		'amount_field' => 'amount',
		'engine' => 'PayPal',
		'mode' => 'sandbox',
		'currency' => 'USD'
	];

	public function __construct( $params = [] ) {
		$option = get_option( 'sp' ) ? : [];
		parent::__construct( apply_filters( 'sp_settings', array_merge( array_merge( $this->defaults, $params ), $option ) ) );
		self::$license = get_option( 'sp_license' );
		$plugin = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
		self::$version = $plugin[ 'Version' ];
		$this->load();

		if ( is_admin() ) {
			require_once( SPWP_PLUGIN_DIR . '/admin/admin.php' );
			SimplePaymentAdmin::instance( $this->param() );
			do_action( 'sp_admin_loaded' );
		}
	}

	public static function instance($params = []) {
		if ( !self::$instance ) self::$instance = new self( $params );
		return( self::$instance );
	}

	public function setEngine( $engine ) {
		if ( $this->param( 'mode' ) == 'live' ) {
			$this->sandbox = false;
		}
		if ( $engine != 'Custom' && $this->engine && $this->engine::$name == $engine ) return;
		$filename =  strtolower( sanitize_file_name( wp_basename( $engine, '.php' ) ) );
		if ( file_exists( SPWP_PLUGIN_DIR . '/engines/' . $filename . '.php' ) )
			require_once( SPWP_PLUGIN_DIR . '/engines/' . $filename . '.php' );
		parent::setEngine( $engine );
		$callback = $this->callback_url();
		if ( $this->engine ) $this->engine->setCallback( strpos( $callback, '://' ) ? $callback : site_url( $callback ) );
	}

	public function load() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'wp_loaded', [ $this, 'init' ] );

		add_filter( 'cron_schedules', [ get_class( $this ), 'cron_schedule' ] );

		if ( self::param( 'safe_redirect' ) ) {
			add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_hosts' ] );
		}

		add_action( 'sp_cron', [ get_class( $this ), 'cron' ] );
		add_action( 'sp_cron_purge', [ get_class( $this ), 'cron_purge' ] );

		add_action( 'admin_init', function() {
			if ( function_exists( 'as_schedule_recurring_action' ) ) {
				$min = self::param( 'cron_period' );
				if ( $min && !as_has_scheduled_action( 'sp_cron' ) ) {
					as_schedule_recurring_action( time(), $min * 60, 'sp_cron' );
					wp_clear_scheduled_hook( 'sp_cron' );
				}
				if ( !as_has_scheduled_action( 'sp_cron_purge' ) ) {
					as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'sp_cron_purge' );
					wp_clear_scheduled_hook( 'sp_cron_purge' );
				}
			} else {
				if ( !wp_next_scheduled( 'sp_cron' ) ) wp_schedule_event( time(), 'sp_cron_schedule', 'sp_cron' );
				if ( !wp_next_scheduled( 'sp_cron_purge' ) ) wp_schedule_event( time(), 'daily', 'sp_cron_purge' );
			}
		} );
		
		add_action( 'parse_request', [ $this, 'callback' ] );
		add_shortcode( 'simple_payment', [ $this, 'shortcode' ] );
		
		add_action( 'sp_validate_license', [ $this, 'validate_plugin_license' ] );
		add_action( 'upgrader_process_complete', [ $this, 'validate_periodic_license' ] );
		do_action( 'sp_loaded' );
	}

	function validate_periodic_license() {
		try {
			$this->validate_plugin_license();
			delete_transient( 'sp_license_issue' );
		} catch ( Exception $e ) {
			// TODO: add admin notice.
			if ( self::param( 'mode' ) == 'live' ) {
				set_transient( 'sp_license_issue', sprintf( __( 'Simple Payment License Error: %s', 'simple-payment' ), ( isset( self::$license[ 'status' ] ) ? self::$license[ 'status' ] : __( 'License error, or no license.', 'simple-payment' ) ) ) );
			}
		}
	}
	
	function validate_plugin_license( $key = null ) {
		return( $this->validate_key( $key ? : ( self::$license[ 'key' ] ?? self::$license ) ) );
	}
	
	function allowed_hosts( $hosts ) {
		$domains = self::param( 'safe_redirect_domains' );
		if ( $domains ) $hosts = array_merge( $hosts, explode( ',', $domains ) );
		if ( $this->engine && isset( $this->engine::$domains ) && is_array( $this->engine::$domains ) ) {
			$hosts = array_merge( $hosts, $this->engine::$domains );
		}
		return( $hosts );
	}

	public function init( $callback = null ) {
		$this->payment_page = self::param( 'payment_page');
		$this->callback = $this->payment_page( $callback );
	}

	public function callback_url() {
		return( apply_filters( 'sp_payment_callback', $this->callback ) );
	}

	public function payment_page( $callback = null, $params = null ) {
		if ( $this->payment_page ) $this->callback = get_page_link( $this->payment_page );
		else $this->callback = $callback ? $callback : self::param( 'callback_url' );
		if ( !$this->callback ) $this->callback = $_SERVER[ 'REQUEST_URI' ];
		if ( !$this->callback ) $this->callback = get_bloginfo( 'url' );
		if ( $params ) 
			foreach( $params as $key => $value ) $this->callback = add_query_arg( $key, $value, $this->callback );
		return( $this->callback );
	}

	public function render() {
		do_action( 'sp_form_render' );
	}

	public static function cron_schedule( $schedules ) {
		$min = self::param( 'cron_period' );
		if ( !$min ) return( $schedules );
		$schedules[ 'sp_cron_schedule' ] = [
			'interval' => $min * 60,
			'display'  => sprintf( esc_html__( 'Every %s Minutes', 'simple-payment' ), $min )
		];
		return( $schedules );
	}

	public static function cron() {
		$mins = self::param( 'verify_after' );
		if ( $mins ) self::process_verify( $mins );

		$mins = self::param( 'pending_period' );
		if ( $mins ) self::process_pending( $mins );

		do_action( 'sp_payment_cron' );
	}

	public static function cron_purge() {
		$archive_purge = self::param( 'auto_purge' );
		$period = absint( self::param( 'purge_period' ) );
		if ( !$period || !$archive_purge || $archive_purge == 'disabled' ) return;
		switch ( $archive_purge ) {
			case 'archive_purge': 
			case 'archive':
				self::process_archive( $period );
				if ( $archive_purge == 'archive' ) break;
			case 'purge':
				self::process_purge( $archive_purge == 'purge' ? $period : $period * 2 );
				break;
		}
		do_action( 'sp_cron_purge_done', $archive_purge, $period );
	}

	public static function process_verify( $mins ) {
		if ( !$mins ) return;
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix.self::$table_name . " WHERE `transaction_id` IS NOT NULL AND `retries` < " . self::$max_retries . " AND (`status` = 'pending' OR (`status` = 'success' AND  `confirmation_code` IS NULL)) AND `archived` = 0 AND `created` < DATE_SUB(NOW(), INTERVAL %d MINUTE)", $mins );
		$transactions = $wpdb->get_results( $sql , 'ARRAY_A' );
		foreach( $transactions as $transaction ) {
			self::verify( $transaction[ 'id' ] );
		}
		do_action( 'sp_process_verify' );
	}

	protected static function process_pending( $mins ) {
		if ( !$mins ) return;
		global $wpdb;
		$sql = $wpdb->prepare( "UPDATE " . $wpdb->prefix . self::$table_name . " SET `status` = 'failed', `modified` = NOW(), `error_code` = 600, `error_description` = 'CRON_PENDING_TO_VERIFY' WHERE `status` IN ( 'created', 'pending' ) AND `created` < DATE_SUB( NOW(),INTERVAL %d MINUTE )", $mins );
		$wpdb->query( $sql );
		do_action( 'sp_payment_process_pending', $mins );
	}

	protected static function process_archive( $days ) {
		if ( !$days ) return;
		global $wpdb;
		$sql = $wpdb->prepare( 'UPDATE ' . $wpdb->prefix.self::$table_name . ' SET `archived` = 1, `modified` = NOW() WHERE `created` < DATE_SUB( NOW(),INTERVAL %d DAY )', $days );
		$wpdb->query( $sql );
		do_action( 'sp_payment_process_archive', $days );
	}

	protected static function process_purge( $days ) {
		if ( !$days ) return;
		global $wpdb;
		$sql = $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'sp_history' . ' WHERE `transaction_id` IN ( SELECT `transaction_id` FROM ' . $wpdb->prefix . self::$table_name . ' WHERE `created` < DATE_SUB( NOW(),INTERVAL %d DAY ) )', $days );
		$wpdb->query( $sql );
		$sql = $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'sp_history' . ' WHERE `payment_id` IN ( SELECT `id` FROM ' . $wpdb->prefix . self::$table_name . ' WHERE `created` < DATE_SUB( NOW(),INTERVAL %d DAY ) )', $days );
		$wpdb->query( $sql );
		$sql = $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . self::$table_name . ' WHERE `created` < DATE_SUB( NOW(), INTERVAL %d DAY  )', $days );
		$wpdb->query( $sql );
		do_action( 'sp_payment_process_purge', $days );
	}

	public static function param( $key = null, $default = false ) {
		return( apply_filters( 'sp_settings_param', parent::param( $key, $default ), $key, $default ) );
	}

	function process( $params = [] ) {
		$params = apply_filters( 'sp_payment_process_filter', $params, $this->engine );
		$status = parent::process( $params );
		// TODO: should we allow update of amount/product code
		if ( $this->engine->transaction ) self::update( isset( $params[ 'payment_id' ] ) ? $params[ 'payment_id' ] : $this->payment_id, [ 'transaction_id' => $this->engine->transaction ] );
		do_action( 'sp_payment_process', $params, $this->engine );
		return( $status );
	}

	function status( $params = [] ) {
		$params = apply_filters( 'sp_payment_status_filter', $params, $this->engine );
		$data = [];
		if ( isset( $params[ 'payment_id' ] )) $data = $this->fetch( $params[ 'payment_id' ] );
		$status = false; 
		if ( $code = parent::status( array_merge( $data, $params ) ) ) {
			$params[ 'confirmation_code' ] = $code;
			$status = self::update( $this->payment_id ? : $this->engine->transaction, [
				'status' => self::TRANSACTION_SUCCESS,
				'confirmation_code' => $code,
			], !$this->payment_id );
		}
		do_action( 'sp_payment_status', $params, $this->engine );
		return( $status );
	}

	function payment_id( $params = [], $engine = null ) {
		$this->setEngine( $engine ? : $this->param( 'engine' ) );
		return( $this->engine->payment_id( $params ) );
	}

	function post_process( $params = [], $engine = null ) {
		$this->setEngine( $engine ? : $this->param( 'engine' ) );
		$params = apply_filters( 'sp_payment_post_process_filter', $params, $this->engine );
		if ( parent::post_process( $params ) ) {
			$args = [
				'status' => self::TRANSACTION_SUCCESS
			];
			if ( $this->engine->confirmation_code) $args[ 'confirmation_code' ] = $this->engine->confirmation_code;
			if ( $this->engine->payments) $args[ 'payments' ] = $this->engine->payments;
			if ( $this->engine->amount) $args[ 'amount' ] = $this->engine->amount;
			if ( $this->param( 'user_create' ) != 'disabled' && $this->param( 'user_create_step' ) == 'post' && !get_current_user_id() ) {
				$user_id = $this->create_user( $params );
				if ( $user_id ) $args[ 'user_id' ] = $user_id;
			}
			self::update( $this->payment_id ? : $this->engine->transaction, $args , !$this->payment_id );
			do_action( 'sp_payment_post_process', $params, $this->engine );
			return( true );
		}
		return( false );
	}

	function pre_process( $pre_params = [] ) {
		$method = isset( $pre_params[ self::METHOD ] ) ? strtolower( sanitize_text_field( $pre_params[ self::METHOD ] ) ) : null;
		foreach ( self::$fields as $field ) if ( isset( $pre_params[ $field ] ) && $pre_params[ $field ] ) $params[ $field ] = in_array( $field, [ 'redirect_url', 'token' ] ) ? $pre_params[ $field ] : sanitize_text_field( $pre_params[ $field ] );

		$params[ self::AMOUNT ] = isset( $params[ self::AMOUNT ]) && $params[ self::AMOUNT ] ? self::tofloat( $params[ self::AMOUNT ] ) : null;
		$secrets = [ self::CARD_NUMBER, self::CARD_CVV ];
		foreach ($secrets as $field) if (isset($params[$field])) $this->secrets[$field] = $params[$field];
		if (isset($this->engine->password)) $this->secrets['engine.password'] = $this->engine->password;

		if (!isset($params[self::LANGUAGE])) {
			$parts = explode('-', get_bloginfo('language'));
			$params[self::LANGUAGE] = $parts[0];
		}
		if (!isset($params['concept']) && isset($params[self::PRODUCT])) $params['concept'] = $params[self::PRODUCT];
		if ($method) $params[self::METHOD] = $method;
		if (isset($params[self::FULL_NAME]) && trim($params[self::FULL_NAME])) {
			$names = explode(' ', $params[self::FULL_NAME]);
			$first_name = $names[0];
			$last_name = substr($params[self::FULL_NAME], strlen($first_name));
			if (!isset($params[self::FIRST_NAME]) || !trim($params[self::FIRST_NAME])) $params[self::FIRST_NAME] = $first_name;
			if (!isset($params[self::LAST_NAME]) || !trim($params[self::LAST_NAME])) $params[self::LAST_NAME] = $last_name;
		}
		if (!isset($params[self::FULL_NAME]) && (isset($params[self::FIRST_NAME]) || isset($params[self::LAST_NAME]))) $params[self::FULL_NAME] = trim((isset($params[self::FIRST_NAME]) ? $params[self::FIRST_NAME] : '').' '.(isset($params[self::LAST_NAME]) ? $params[self::LAST_NAME] : ''));
		if (!isset($params[self::CARD_OWNER]) && isset($params[self::FULL_NAME])) $params[self::CARD_OWNER] = $params[self::FULL_NAME];
		//if ( !isset( $params[ 'payment_id' ] ) || !$params[ 'payment_id' ] ) $params[ 'payment_id' ] = 
		$params = apply_filters( 'sp_payment_pre_process_filter', $params, $this->engine );
		//if ( empty( $params[ 'payment_id' ] ) )
		$params[ 'payment_id' ] = $this->payment_id = $this->register( $params );
		try {
			$process = parent::pre_process( $params );
			self::update( $params[ 'payment_id' ], [ 
				'status' => self::TRANSACTION_PENDING, 
				'transaction_id' => $this->engine->transaction
			] );
		} catch ( Exception $e ) {
			self::update( $params[ 'payment_id' ], [
				'status' => self::TRANSACTION_FAILED,
				'transaction_id' => $this->engine->transaction,
				'error_code' => $e->getCode(),
				'error_description' => substr( $e->getMessage(), 0, 250 ),
			] );
			throw $e;
		}
		if ( $this->param( 'user_create' ) != 'disabled' && $this->param( 'user_create_step' ) == 'pre' && !get_current_user_id() ) {
			$user_id = $this->create_user( $params );
			$params[ 'user_id' ] = $user_id;
		}
		do_action( 'sp_payment_pre_process', $params, $this->engine  );
		return( $process );
	}

	function recur( $params = [] ) {
		$params = apply_filters( 'sp_payment_recur_filter', $params, $this->engine  );
		if ( parent::recur( $params ) ) {
			do_action( 'sp_payment_recur', $params );
			return( true );
		}
		return( false );
	}

	function store( $params = [], $engine = null ) {
		$engine = $engine ? : $this->param( 'engine' );
		$this->setEngine( $engine );
		$params = apply_filters( 'sp_creditcard_token_params', $params, $this->engine  );
		$params[ 'payment_id' ] = $this->register( $params );
		if ( $params = parent::store( $params ) ) {
			if ( isset( $params[ 'token' ] ) ) {
				self::update( $this->payment_id ? : $this->engine->transaction, [
					'status' => self::TRANSACTION_SUCCESS,
					'confirmation_code' => $this->engine->confirmation_code
				], !$this->payment_id );
				do_action( 'sp_creditcard_token', $params[ 'token' ], ( $this->payment_id ? $this->payment_id : $this->engine->transaction ), $params );
			}
			return( true );
		}
		return( false );
	}

	public static function supports( $feature, $engine = null ) {
		return( parent::supports( $feature, $engine ? : self::param( 'engine' ) ) );
	}

	function callback() {
		$callback = parse_url( $this->callback );
		$info = parse_url( $_SERVER[ 'REQUEST_URI' ] );
		if ( isset( $info[ 'path' ] ) && isset( $callback[ 'path' ] ) && $info[ 'path' ] != $callback[ 'path' ] ) return;
		$ops = explode( '/', $info[ 'path' ] );

		if ( !( isset( $_REQUEST[ self::OP ] ) || ( count( $ops ) == 4 && strtolower( $ops[ 1 ] ) == 'sp' ) ) ) return;
		$url = null;

		$engine = isset( $_REQUEST[ 'engine' ] ) ? sanitize_text_field( $_REQUEST[ 'engine' ] ) : ( isset( $ops[ 2 ] ) && $ops[ 2 ] ? $ops[ 2 ] : self::param( 'engine' ) );
		$op = isset( $_REQUEST[ self::OP ] ) ? strtolower( sanitize_text_field( $_REQUEST[ self::OP ] ) ) : strtolower( isset( $ops[ 3 ] ) ? $ops[ 3 ] : '' );
		try {
			switch ( $op ) {
				case self::OPERATION_REDIRECT:
					$uid = isset( $_REQUEST[ '_spr' ] ) ? $_REQUEST[ '_spr' ] : null;
					if ( $uid ) {
						$url = get_transient( 'sp_' . $uid );
					}
					break;
				case self::OPERATION_SUCCESS:
					$rmop = false;
					$this->payment_id = $this->payment_id( $_REQUEST, $engine );
					$url = isset( $_REQUEST[ 'redirect_url' ] ) && $_REQUEST[ 'redirect_url' ] ? esc_url_raw( $_REQUEST[ 'redirect_url' ] ) : self::param( 'redirect_url' );
					if ( !$url && $this->payment_id ) {
						$payment = $this->fetch( $this->payment_id );
						if ( isset( $payment[ 'parameters' ] ) && $payment[ 'parameters' ] ) {
							$payment_parameters = json_decode( $payment[ 'parameters' ], true );
							if ( isset( $payment_parameters[ 'redirect_url' ] ) && $payment_parameters[ 'redirect_url' ] ) $url = $payment_parameters[ 'redirect_url' ];
						}
					}
					if ( !$url ) $url = $this->callback_url();
					if ( !$url ) $url = get_bloginfo( 'url' );
					// TODO: consider only passing _GET, or  transient the data to the next page, not via query.
					set_transient( 'sp-payment-last', $_REQUEST );
					$url .= ( strpos( $url, '?' ) ? '&' : '?' );// . http_build_query( $_REQUEST );
					$url = remove_query_arg( self::OP, $url );
					if ( isset( $_REQUEST[ 'redirect_url' ] ) ) remove_query_arg( 'redirect_url', $url );
					parse_str( parse_url( $url, PHP_URL_QUERY ), $vars );
					if ( isset( $vars[ 'target' ] ) && $vars[ 'target' ] ) $target = $vars[ 'target' ];
					try {
						if ( isset( $_REQUEST[ self::PAYMENT_ID ] ) && $_REQUEST[ self::PAYMENT_ID ] ) $params = array_merge( $this->fetch( $_REQUEST[ self::PAYMENT_ID ] ), $_REQUEST );
						else $params = $_REQUEST;
						$this->post_process( $params, $engine );
						do_action( 'sp_payment_success', $params );
					} catch ( Exception $e ) {
						$status[ self::OP ] = self::OPERATION_ERROR;
						if ( trim( $e->getCode() ) ) $status[ 'status' ] = trim( $e->getCode() );
						if ( trim( $e->getMessage() ) ) $status[ 'message' ] = self::set_message( trim( $e->getMessage() ) );
						$url = $this->error( 
							isset( $status ) ? $status : $_REQUEST, 
							( isset( $status[ 'status' ] ) ? $status[ 'status' ] : null ),
							$e->getMessage() );
						break;
					} 
					break;
					/*  case 'form':
            if (isset($_REQUEST['payment_id']) && $_REQUEST['payment_id']) $params = array_merge($this->fetch($_REQUEST['payment_id']), $_REQUEST);
            else $params = $_REQUEST;
            $engine = $params['engine'] ? : $this->param('engine');
            $this->setEngine($engine);
            $this->process($params);
            break;*/
				case 'purchase':
				case 'payment':
				case 'redirect':
					try {
						$url = $this->payment( $_REQUEST, $engine );
						if ( $url === true ) $url = isset( $_REQUEST[ 'redirect_url' ] ) && $_REQUEST[ 'redirect_url' ] ? esc_url_raw( $_REQUEST[ 'redirect_url' ] ) : self::param( 'redirect_url' );
						if ( !$url ) {
							$url = $this->callback_url();
							$rmop = true;
						}
						if ( !$url ) $url = get_bloginfo( 'url' );
						if ( isset( $rmop ) && $rmop ) $url = remove_query_arg( self::OP, $url );
						break;
					} catch ( Exception $e ) {
						$status[ 'payment_id' ] = $this->payment_id;
						$status[ 'status' ] = $e->getCode();
						$status[ 'message' ] = self::set_message( $e->getMessage() );
						$this->error( $status, $e->getCode(), $e->getMessage() );
					}
				case self::OPERATION_ERROR:
					$this->payment_id = $this->payment_id( $_REQUEST, $engine );
					$status = isset( $status ) ? $status : $_REQUEST;
					$url = $this->error( $status, ( isset( $status[ 'status' ] ) ? $status[ 'status' ]  : null ), ( isset( $status[ 'message' ] ) ? $status[ 'message' ] : null ) ); // TODO: check if it gets the message key or the message
					do_action( 'sp_payment_error', $url, $_REQUEST );
					break;
				case self::OPERATION_STATUS:
					try {
						$this->setEngine( $engine );
						$this->status( $_REQUEST ); 
					} catch ( Exception $e ) {
						$status[ 'transaction_id' ] = $this->engine->transaction;
						$status[ 'payment_id' ] = $this->payment_id;
						$status[ 'status' ] = $e->getCode();
						$status[ 'message' ] = self::set_message( $e->getMessage() );
						$url = $this->error( isset( $status ) ? $status : $_REQUEST, $e->getCode(), $e->getMessage() );
					}
					die; break;
				case self::OPERATION_CANCEL:
					$url = $this->cancel( $_REQUEST );
					do_action( 'sp_payment_cancel', $url, $_REQUEST );
					break;
				case self::OPERATION_PCSS:
					header( 'Content-Type: text/css' );
					echo self::param( strtolower( $engine ) . '.css' );
					die; break;
				case self::OPERATION_CSS:
					header( 'Content-Type: text/css' );
					echo self::param( 'css' );
					die; break;
				case self::OPERATION_FEEDBACK:
					$this->setEngine( $engine );
					$payment = $this->feedback( $_REQUEST );
					if ( $payment ) {
						$payment_id = isset( $payment[ 'transaction_id' ] ) && $payment[ 'transaction_id' ] ? $payment[ 'transaction_id' ] : $payment[ 'payment_id' ];
						$data = $payment_id ? $this->fetch( $payment_id, ( isset( $payment[ 'transaction_id' ] ) && $payment[ 'transaction_id' ] ? $engine : null ) ) : [];
						do_action( 'sp_payment_feedback', array_merge( $payment, $data ) );
					}
					die; break;
				case 'recur':
					die; break;
				default:
					do_action( 'sp_extension_' . $op );
					die; break;
			}
		} catch ( Exception $e ) {
			$url = $this->callback_url();
			$status[ self::OP ] = self::OPERATION_ERROR;
			$status[ 'status' ] = $e->getCode();
			$status[ 'message' ] = self::set_message( $e->getMessage() );
			$url .= ( strpos( $url, '?' ) ? '&' : '?' ) . http_build_query( $status );
		} 
		if ( $url ) {
			if ( $op == 'purchase ') $target = '';
			else $target = isset( $target ) && $target ? $target : ( isset( $_REQUEST[ 'target' ] ) ? $_REQUEST[ 'target' ] : null );
			self::redirect( $url, $target );
			wp_die();
		}
	}

	public static function set_message( $message ) {
		$key = wp_generate_password( 20, false );
		set_transient( 'sp-msg-' . $key, $message );
		return( $key );
	}

	public static function get_message( $key ) {
		return( get_transient( 'sp-msg-' . $key ) );
	}

	function payment_refund($payment_id, $params) {
		try {
			$payment = $this->fetch($payment_id);
			if (!$payment) return(false);
			$this->setEngine($payment['engine']);
			$params['payments'] = 'refund';
			$params['payment_id'] = $payment_id;
			$payment_id  = $this->register($params);
			$confirmation_code = $this->refund(array_merge($payment, $params)) ? $params['payment_id'] : false;
			if ($confirmation_code) {
				self::update($payment_id  ? $payment_id : $transaction_id, [
					'status' => self::TRANSACTION_SUCCESS,
					'confirmation_code' => $this->engine->confirmation_code,
					'transaction_id' => $this->engine->transaction
				], !$payment_id);
				return($payment_id);
			}
		} catch (Exception $e) {
			$data = [];
			$data[ 'status' ] = elf::TRANSACTION_FAILED;
			$data[ 'error_code' ] = $e->getCode();
			$data[ 'error_description' ] = substr($e->getMessage(), 0, 250);
			$data[ 'transaction_id' ] = $this->engine->transaction;
			self::update( $payment_id  ? $payment_id : $transaction_id, $data, !$payment_id );
		}
		return(false);
	}

	function payment_recharge( $params, $payment_id = null ) {
		try {
			if ( $payment_id ) {
				$payment = $this->fetch( $payment_id );
				if ( !$payment ) return( false ); // TODO: Enable recharge with token, assuming no payment id present
				$params[ 'payment_id' ] = $payment_id;
				$params = array_merge( $payment, $params );
			}
			$this->setEngine( $payment ? $payment[ 'engine' ] : $params[ 'engine' ] );
			$params[ 'payments' ] = 'recharge';
			$payment_id = $this->register( $params );
			// TODO: fetch - concept
			// TODO: transaction id, approval number
			if ( $this->recharge( $params ) ) {
				self::update( $payment_id  ? $payment_id : $transaction_id, [
					'status' => self::TRANSACTION_SUCCESS,
					'confirmation_code' => $this->engine->confirmation_code,
					'transaction_id' => $this->engine->transaction
				], !$payment_id );

			} else if ( $payment_id ) {
				throw new Exception(  __( 'Error payment recharge' ,'simple-payment', 500 ) );
			}
		} catch ( Exception $e ) {
			$data = [];
			$data[ 'status' ] = self::TRANSACTION_FAILED;
			$data[ 'error_code' ] = $e->getCode();
			$data[ 'error_description' ] = substr( $e->getMessage(), 0, 250 );
			$data[ 'transaction_id' ] = $this->engine->transaction;
			self::update( $payment_id  ? $payment_id : $transaction_id, $data, !$payment_id );
		}
		return( false );
	}

	function payment( $params = [], $engine = null ) {
		$return = false;
		$engine = $engine ? : $this->param( 'engine' );
		$this->setEngine( $engine );
		try {
			if ( $process = $this->pre_process( $params ) ) {
				if ( !is_array( $process ) && !is_bool( $process ) && $process !== true ) {
					self::redirect( $process );
					wp_die();
				}
				// TODO: allow redirect if returned is url
				$process = $this->process( $process );
				if ( $process === true ) return( true );
				if ( !$process ) return( false ); 
				$return = is_array( $process ) ? $this->post_process( $process, $engine ) : $process;
			}
		} catch ( Exception $e ) {
			$this->error( $params, $e->getCode(), $e->getMessage() );
			throw $e;
		}
		return( $return );
	}

	function error( $params = [], $code = null, $description = null ) {
		$url = $this->callback_url();
		if ( !$url ) $url = get_bloginfo( 'url' );
		$url .= ( strpos( $url, '?' ) ? '&' : '?' ) . http_build_query( $params );
		$url = remove_query_arg( self::OP, $url );
		$payment_id = isset( $params[ 'payment_id' ] ) && $params[ 'payment_id' ] ? $params[ 'payment_id' ] : $this->payment_id;
		$data = [
			'status' => self::TRANSACTION_FAILED
		];
		if ( $code ) $data[ 'error_code' ] = $code;
		if ( $description ) $data[ 'error_description' ] = substr( $description, 0, 250 );
		if ( $this->engine->transaction ) $data[ 'transaction_id' ] = $this->engine->transaction;
		self::update( $payment_id ? $payment_id : $this->engine->transaction, $data, !$payment_id );
		return( $url );
	}

	function cancel( $params = [] ) {
		$url = $this->callback_url();
		$url .= ( strpos( $url, '?' ) ? '&' : '?' ) . http_build_query( $params );
		$url = remove_query_arg( self::OP, $url );
		$payment_id = isset( $params[ 'payment_id' ] ) ? $params[ 'payment_id' ]: null;
		self::update( $payment_id ? : $this->engine->transaction, [
			'status' => self::TRANSACTION_CANCEL
		], !$payment_id );
		return( $url );
	}

	function create_user( $params ) {
		$email = isset( $params[ self::EMAIL ] ) ? $params[ self::EMAIL ] : false;
		if ( !$email ) return( false );
		$user_id = email_exists( $email );
		if ( !$user_id ) {
			$username = isset( $params[ self::USERNAME ] ) ? $params[ self::USERNAME ] : false;
			$user_id = $username ? username_exists( $username ) : false;
			if ( !$user_id ) {
				$username = isset( $params[ self::FIRST_NAME ] ) ? $params[ self::FIRST_NAME ] : false;
				if ( !$username ) $username = isset( $params[ self::LAST_NAME ] ) ? $params[ self::LAST_NAME ] : false;
				if ( !$username ) $username = isset($params[ self::FULL_NAME ] ) ? explode( ' ', $params[ self::FULL_NAME ])[ 0 ] : false;
				if ( !$username ) $username = wp_generate_password( 12, false );
				$username = $this->generate_unique_username( strtolower( $username ) );
				if ( $this->param( 'user_create_step' ) == 'register' ) $user_id = register_new_user( $username, $email );
				else $user_id = wp_create_user( $username, wp_generate_password( 12, false ), $email );
				do_action( 'sp_user_created', $user_id, $params, false );

				// TODO: allow ssetting for autologin, or not
                wp_set_auth_cookie( $user_id );
			}
		}
		if ( $user_id ) {
            // TODO: Login should be handled by developer, implementation,
            // could be redirecting user to login screen, or validating 
            // other data field that comes in $params that indicates it the user. 
            // wp_set_auth_cookie( $user_id );
            do_action( 'sp_user_created', $user_id, $params, true );
        }
		return( $user_id );
	}
		
	function generate_unique_username( $username ) {
		$username = sanitize_user( $username );
		$i = 1;
		$new_username = $username;
		while ( username_exists( $new_username ) ) {
			$new_username = $username . '-' . $i++;
		}
		return( $new_username );
	}

	function shortcode( $atts ) {
		extract( shortcode_atts( [
			'id' => null,
			'amount' => null,
			'currency' => null,
			'product' => null,
			'product_code' => null,
			'fixed' => false,
			'title' => null,
			'type' => self::TYPE_FORM,
			'enable_query' => false,
			'target' => null,
			'engine' => null,
			'redirect_url' => null,
			'method' => null,
			'display' => null,
			'form' => self::param('form_type'),
			'template' => null,
			'installments' => false,
			'amount_field' => self::param('amount_field'),
			'product_field' => null,
		], $atts ) );
		if (!$amount || !$product) {
			$id = $id ? $id : get_the_ID();
			if ( !$amount ) $amount = get_post_meta( $id, $amount_field, true );
			if ( !$product ) $product = $product_field ? get_post_meta( $id, $product_field, true ) : get_the_title( $id );
		}
		$params = [
			'amount' => $amount,
			'product' => $product,
			'engine' => $engine ? : self::param('engine'),
			'method' => $method,
			'target' => $target,
			'template' => $template,
			'product_code' => $product_code,
			'type' => $type,
			'display' => isset($display) && $this->supports($display, $engine ? : self::param('engine')) ? $display : null,
			'redirect_url' => $redirect_url,
			'installments' => $installments && $installments == 'true' ? true : false,
			'currency' => $currency ? : null,
			'callback' => $this->callback,
			'title' => $title ? : null,
			'form' => $form ? : null
		];

		if ( $enable_query ) {
			if (isset($_REQUEST[self::FULL_NAME])) $params[self::FULL_NAME] = sanitize_text_field($_REQUEST[self::FULL_NAME]);
			if (isset($_REQUEST[self::PHONE])) $params[self::PHONE] = sanitize_text_field($_REQUEST[self::PHONE]);
			if (isset($_REQUEST[self::EMAIL])) $params[self::EMAIL] = sanitize_email($_REQUEST[self::EMAIL]);
		}
		return( $this->checkout( $params ) );
	}

	function secretive($key, $value) {
		if (!isset($this->secrets[$key])) $this->secrets[$key] = $value;
	}

	function checkout( $params ) {
		$type = isset($params['type']) ? $params['type'] : null;
		$target = isset($params['target']) ? $params['target'] : null;
		$title = isset($params['title']) ? $params['title'] : null;
		$form = isset($params['form']) ? $params['form'] : null;
		$template = isset($params['template']) ? $params['template'] : null;
		switch ($type) {
			case self::TYPE_BUTTON:
				$url = $this->callback;
				$params[ self::OP ] = 'redirect';
				if ( !isset( $params[ 'callback' ] ) ) $params[ 'callback' ] = $this->callback;
				if ( $target ) $params[ 'callback' ] .= ( strpos( $params[ 'callback' ], '?' ) ? '&' : '?' ) . http_build_query( [ 'target' => $params[ 'target' ] ] );
				$this->settings( $params );
				return( sprintf( '<a class="btn" href="%1$s"' . ( $target ? ' target="' . $target . '"' : '' ) . '>%2$s</a>',
								$url . '?' . http_build_query( $params ),
								esc_html( $title ? $title : 'Buy' ) ) );
				break;
			case self::TYPE_HIDDEN:
				$form = 'hidden';
			default:
			case self::TYPE_FORM:
				$template = 'form-'.$form;
			case self::TYPE_TEMPLATE:
				$this->scripts();
				if (!isset($params['callback'])) $params['callback'] = $this->callback;
				if ($target) $params['callback'] .= (strpos($params['callback'], '?') ? '&' : '?').http_build_query(['target' => $params['target']]);
				$this->settings($params);
				ob_start();
				if (!locate_template($template.'.php', true) && file_exists(SPWP_PLUGIN_DIR.'/templates/'.$template.'.php')) load_template(SPWP_PLUGIN_DIR.'/templates/'.$template.'.php');
				return ob_get_clean();
				break;
		}
	}

	public function settings($params = null) {
		global $wp_query;
		if ($params) foreach ($params as $key => $value) set_query_var($key, $value);
		$params = [];
		foreach (self::$fields as $field) if (isset($wp_query->query_vars[$field])) $params[$field] = $wp_query->query_vars[$field];
		return($params);
	}

	public function scripts() {
		$plugin = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
		wp_register_script( 'simple-payment-checkout-js', plugin_dir_url( __FILE__ ) . 'assets/js/form-checkout.js', [], $plugin[ 'Version' ], true );
		wp_enqueue_script( 'simple-payment-js', plugin_dir_url( __FILE__ ) . 'assets/js/simple-payment.js', [ 'jquery' ], $plugin['Version'], true );
		wp_enqueue_style( 'simple-payment-css', plugin_dir_url( __FILE__ ) . 'assets/css/simple-payment.css', [], $plugin['Version'], 'all' );
		if ( self::param( 'css' ) ) wp_enqueue_style( 'simple-payment-custom-css', $this->callback . '?' . http_build_query( [ self::OP => self::OPERATION_CSS ] ), [], md5( self::param( 'css' ) ), 'all' );
	}

	protected function register( $params ) {
		global $wpdb;
		$values = [
			'engine' => $this->engine::$name,
			'currency' => isset( $params[ self::CURRENCY ] ) && $params[ self::CURRENCY ] ? $params[ self::CURRENCY ] : $this->param('currency' ),
			'amount' => self::tofloat( $params[ self::AMOUNT ] ),
			'concept' => $params[ 'product' ] ?? ( isset( $params[ self::PRODUCT ] ) ? $params[ self::PRODUCT ] : null ),
			'payments' => isset( $params[ self::PAYMENTS ] ) ? $params[ self::PAYMENTS ] : null,
			'parameters' => $this->sanitize_pci_dss( json_encode( $params ) ),
			'url' => wp_get_referer() ? $this->sanitize_pci_dss(wp_get_referer()) : '',
			'status' => self::TRANSACTION_NEW,
			'sandbox' => $this->sandbox,
			'user_id' => $params[ 'user_id' ] ?? get_current_user_id(),
			'ip_address' => $_SERVER[ 'REMOTE_ADDR' ] ?? null,
			'user_agent' => $_SERVER[ 'HTTP_USER_AGENT' ] ?? null,
			'parent_id' => isset( $params[ 'payment_id' ] ) ? $params[ 'payment_id' ] : null,
			'modified' => current_time( 'mysql' ),
			'created' => current_time( 'mysql' ),
		];
		$result = $wpdb->insert( $wpdb->prefix . self::$table_name, $values );
		if ( $result === false ) 
			throw new Exception( 
				sprintf( __( "Couldn't register transaction: %s", 'simple-payment' ), 
				$wpdb->last_error
			) );
		$this->payment_id = $wpdb->insert_id;
		return( $this->payment_id ? : false );
	}

	protected static function update( $id, $params, $transaction_id = false ) {
		global $wpdb; $token = null;
		$table_name = $wpdb->prefix . self::$table_name;
		if ( isset( $params[ 'token' ] ) && $params[ 'token' ] ) {
			$token = $params[ 'token' ];
			$params[ 'token' ] = json_encode( $params[ 'token' ] );
		}
		if ( !isset( $params[ 'modified' ] ) ) $params[ 'modified' ] = current_time( 'mysql' ); // TODO: try with NOW()
		// No need to update user_id, we are keeping original user
		//$user_id = get_current_user_id();
		//if ( $user_id ) $params[ 'user_id' ] = $user_id;
		$result = $wpdb->update( $table_name, $params, [ 
			( $transaction_id ? 'transaction_id' : 'id' ) => $id 
		] );
		if ( $result === false ) 
			throw new Exception( 
				sprintf( __( "Couldn't update transaction: %s", 'simple-payment' ), $wpdb->last_error ) 
			);
		if ( isset( $token ) && $token ) {
			do_action( 'sp_creditcard_token', $token, ( $id ? $id : $transaction_id ), $params );
		}
		return( $result );
	}

	public function get_entry(  $id, $engine = null ){ 
		return( $this->fetch( $id, $engine ) );
	}

	public function fetch( $id, $engine = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix.self::$table_name;
		if ( !$engine ) {
			$sql = "SELECT * FROM " . $table_name . " WHERE `id` = %d LIMIT 1";
			$sql = sprintf( $sql, absint( $id ) );
		} else {
			$sql = "SELECT * FROM " . $table_name . " WHERE `engine` = '%s' AND `transaction_id` = %d LIMIT 1";
			$sql = sprintf( $sql, esc_sql( $engine ), esc_sql( $id ) );
		}
		$result = $wpdb->get_results( $sql , 'ARRAY_A' );
		$data = count( $result ) ? $result[ 0 ] : null;
		if ( $data && isset( $data[ 'parameters' ] ) && $data[ 'parameters' ] ) $data = array_merge( $data, json_decode( $data[ 'parameters' ], true ) );
		return( $data );
	}

	/**
     * Load Simple Payment Text Domain.
     * This will load the translation textdomain depending on the file priorities.
     *      1. Global Languages /wp-content/languages/simple-payment/ folder
     *      2. Local directory /wp-content/plugins/simple-payment/languages/ folder
     *
     * @since  1.0.0
     * @return void
     */
	public function load_textdomain() {
		$lang_dir = apply_filters( 'sp_languages_directory', SPWP_PLUGIN_DIR . '/languages/' );
		load_plugin_textdomain( 'simple-payment', $lang_dir, str_replace(WP_PLUGIN_DIR, '', $lang_dir) );
	}

	public static function verify( $id = null, $counter = true ) {
		$sp = self::instance();
		$transaction = $sp->fetch( $id );
		$sp->payment_id = $transaction[ 'id' ];
		$sp->setEngine( $transaction[ 'engine' ] );
		$retries = intval( $transaction[ 'retries' ] ) + 1;
		$data = [];
		try {
			$status = $sp->engine->verify( $transaction );
			if ( $status ) {
				$transaction[ 'status' ] = self::TRANSACTION_SUCCESS;
				self::update( $sp->payment_id  ? : $transaction[ 'transaction_id' ], [
					'status' => self::TRANSACTION_SUCCESS,
					'confirmation_code' => $sp->engine->confirmation_code,
					'retries' => $retries,
					'error_code' => null,
					'error_description' => null
				], !$sp->payment_id );
			}
		} catch ( Exception $e ) {
			$data[ 'error_code' ] = $e->getCode();
			$data[ 'error_description' ] = substr( $e->getMessage(), 0, 250 );
		}
		if ( $retries >= self::$max_retries ) {
			if ( $transaction[ 'status' ] != self::TRANSACTION_SUCCESS ) $data[ 'status' ] = self::TRANSACTION_FAILED;
		}
		if ( $counter ) {
			$data[ 'retries' ] = $retries;
		}

		if ( $data && count( $data ) ) self::update( $sp->payment_id  ? $sp->payment_id  : $transaction[ 'transaction_id' ],
													$data, !$sp->payment_id );
		do_action( 'sp_payment_verify', $sp->fetch( $id ), $sp->engine );
	}

	public static function archive( $id = null ) {
		self::update( $id ? : $_REQUEST[ 'transaction' ], [
			'archived' => true
		] );
		do_action( 'sp_payment_archive', $id );
	}

	public static function unarchive( $id = null ) {
		self::update($id ? : $_REQUEST[ 'transaction' ], [
			'archived' => false
		] );
		do_action( 'sp_payment_unarchive', $id );
	}

	public function sanitize_pci_dss($value) {
		$count = 0;
		foreach ($this->secrets as $key => $secret) {
			if (!$secret) continue;
			switch ($key) {
				case self::CARD_NUMBER:
					$first = substr($secret, 0, 4);
					$last = substr($secret, - 4);
					if ($cnt = preg_match_all('/('.$first.'.*)('.$last.')/', $value, $matches, PREG_SET_ORDER)) {
						foreach ($matches as $match) {
							$santized = preg_replace('/\d/', 'X', $match[1]).$match[2];
							$value = str_replace($match[0], $santized, $value);
						}
					}
					break;
				case self::CARD_CVV:
					$value = preg_replace('/([^\d])('.$secret.')([^\d.])/', '\1'.str_repeat('X', strlen($secret)).'\3', $value);
					break;
				default:
					$value = str_replace($secret, 'xxx', $value, $cnt);
			}
			$count += $cnt;
		}
		return($value);
	}

	public function save( $params, $tablename = null, $id = null ) {
		global $wpdb;
		$tablename = 'history';
		if ( isset( $params[ 'token' ] ) ) {
			if ( $params[ 'token' ] ) {
				self::update( $this->payment_id ? $this->payment_id  : $this->engine->transaction, [ 'token' => $params[ 'token' ] ], !$this->payment_id );
				do_action( 'sp_creditcard_token', $params[ 'token' ], ( $this->payment_id ? $this->payment_id  : $this->engine->transaction ), $params );
			}
			unset( $params[ 'token' ] );
		}

		foreach ( $params as $field => $value ) $params[ $field ] = $this->sanitize_pci_dss( $value );
		if ( !isset( $params[ 'payment_id' ] ) ) {
			$payment_id = $this->payment_id ? $this->payment_id : ( isset( $_REQUEST[ self::PAYMENT_ID ] ) ? $_REQUEST[ self::PAYMENT_ID ] : null );
			if ( $payment_id ) $params[ 'payment_id' ] = $payment_id;
		}
		if ( !isset( $params[ 'ip_address' ] ) ) $params[ 'ip_address' ] = $_SERVER[ 'REMOTE_ADDR' ];
		if ( !isset( $params[ 'user_agent' ] ) ) $params[ 'user_agent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
		if ( $id ) {
			$params[ 'modified' ] = current_time( 'mysql' );
			$result = $wpdb->update( $wpdb->prefix . 'sp_' . $tablename, $params, [ 'id' => $id ] );
		} else {
			$params[ 'modified' ] = current_time( 'mysql' );
			$params[ 'created' ] = current_time( 'mysql' );
			$result = $wpdb->insert( $wpdb->prefix . 'sp_' . $tablename, $params );
		}
		// TODO: use and keep token
		return( $result != null ? $wpdb->insert_id : false );
	}

	public static function sanitize_redirect( $url ) {
		$safe = self::param( 'safe_redirect', false );
		if ( $safe ) {
			$url = wp_validate_redirect( $url, home_url() );
		}
		return( $url );
	}

	public static function redirect( $url, $target = '', $return = false ) {
		$targets = explode( ':', $target ? $target : '' );
		$target = $targets[ 0 ];
		$redirect = '';
		$url = self::sanitize_redirect( $url );
		switch ( $target ) {
			case '_top':
				$redirect = '<html><head><script type="text/javascript"> top.location.replace("' . $url . '"); </script></head><body></body</html>'; 
				break;
			case '_parent':
				$redirect = '<html><head><script type="text/javascript"> parent.location.replace("' . $url . '"); </script></head><body></body</html>'; 
				break;
			case 'javascript':
				$script = $targets[ 1 ];
				$redirect = '<html><head><script type="text/javascript"> ' . $script . ' </script></head><body></body</html>'; 
				break;
			case '_blank':
				$redirect = '<html><head><script type="text/javascript"> var win = window.open("' . $url . '", "_blank"); win.focus(); </script></head><body></body</html>'; 
				break;
			case '_self':
			default:
				$redirect = '<html><head><script type="text/javascript"> location.replace("' . $url . '"); </script></head><body></body</html>'; 
				if ( !$return ) {
					// URL was previously santized so it is safe_redirect
					wp_redirect( $url );
					die;
				}
		}
		if ( !$return ) echo $redirect;
		else return( $redirect );
	}
	
}

require_once( SPWP_PLUGIN_DIR . '/db/simple-payment-database.php' );

global $SPWP;
$SPWP = SimplePaymentPlugin::instance();

require_once( 'addons/gutenberg/init.php' );
require_once( 'addons/zapier/init.php' );
require_once( 'addons/woocommerce/init.php' );
require_once( 'addons/woocommerce-subscriptions/init.php' );

require_once( 'addons/wpjobboard/init.php' );
require_once( 'addons/elementor/init.php' );
require_once( 'addons/gravityforms/init.php' );

//require_once('addons/recaptcha/init.php');

function sp_pro_get_license_defaults() {
	return apply_filters(
		'sp_license_defaults',
		[
			'key' => '',
			'status' => '',
			'beta' => false,
    ]
	);
}

/**
 * Check for and receive updates.
 *
 * @since 1.0.0
 */
/*
 add_action( 'init', 'sp_pro_updater' );

 function sp_pro_updater() {
	$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;

	if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
		return;
	}

	if ( ! class_exists( 'SimplePayment_Plugin_Updater' ) ) {
		include SPWP_PLUGIN_DIR . '/updater.php';
	}

	if ( ! function_exists( 'sp_pro_get_license_defaults' ) ) {
		return;
	}

	$license_settings = wp_parse_args(
		get_option( 'sp_license', array() ),
		sp_pro_get_license_defaults()
	);

	$license_key = trim( $license_settings['key'] );

TODO: finish it up
  $edd_updater = new SimplePayment_Plugin_Updater(
		'https://simple-payment.yalla-ya.com',
		__FILE__,
		[
			'version' => SimplePaymentPlugin::$version,
			'license' => esc_attr( $license_key ),
			'item_id' => 1393, // TODO: define item key
			'author'  => 'SimplePayment',
			'beta'    => isset( $license_settings[ 'beta' ] ) && $license_settings[ 'beta' ] ? true : false,
    ]
	);
}
 */