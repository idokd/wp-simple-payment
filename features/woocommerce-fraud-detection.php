<?php
/**
 * Experimental feature: WooCommerce Fraud Detection.
 *
 * Detects repeated (consecutive) failed orders coming from the same email
 * address or IP address within a configurable timeframe and, once a threshold
 * is reached, blocks the checkout for that email / IP for a cooldown period:
 * no payment methods are shown and a custom message is displayed instead.
 *
 * A successful payment clears the streak for that email / IP.
 *
 * Loaded only when enabled in Simple Payment > Experimental.
 */

defined( 'ABSPATH' ) or exit;

// Requires WooCommerce.
if ( !function_exists( 'WC' ) ) return;

/* -------------------------------------------------------------------------
 * Settings (shown on the Experimental tab)
 * ---------------------------------------------------------------------- */

add_filter( 'sp_admin_sections', function( $sections ) {
	$sections[ 'wc_fraud_settings' ] = [
		'title' => __( 'WooCommerce Fraud Detection', 'simple-payment' ),
		'description' => __( 'Block the checkout for users with repeated failed orders (same email or IP) within the timeframe.', 'simple-payment' ),
		'section' => 'experimental'
	];
	return( $sections );
} );

add_filter( 'sp_admin_settings', function( $settings ) {
	$roles = function_exists( 'wp_roles' ) ? array_keys( wp_roles()->roles ) : [];
	$settings[ 'wc_fraud.threshold' ] = [
		'title' => __( 'Failed Orders Threshold', 'simple-payment' ),
		'section' => 'wc_fraud_settings',
		'description' => __( 'Number of failed orders from the same email or IP within the timeframe that triggers a block. Default 3.', 'simple-payment' )
	];
	$settings[ 'wc_fraud.period' ] = [
		'title' => __( 'Timeframe (seconds)', 'simple-payment' ),
		'section' => 'wc_fraud_settings',
		'description' => __( 'How far back to count failed orders from the same email / IP. Default 3600 (1 hour).', 'simple-payment' )
	];
	$settings[ 'wc_fraud.cooldown' ] = [
		'title' => __( 'Cooldown / Block Duration (seconds)', 'simple-payment' ),
		'section' => 'wc_fraud_settings',
		'description' => __( 'How long to block the same email / IP once the threshold is reached. Default 3600 (1 hour).', 'simple-payment' )
	];
	$settings[ 'wc_fraud.message' ] = [
		'title' => __( 'Blocked Message', 'simple-payment' ),
		'type' => 'textarea',
		'section' => 'wc_fraud_settings',
		'description' => __( 'Shown instead of the payment methods when blocked. HTML allowed.', 'simple-payment' )
	];
	$settings[ 'wc_fraud.excluded_roles' ] = [
		'title' => __( 'Excluded Roles', 'simple-payment' ),
		'section' => 'wc_fraud_settings',
		'description' => sprintf( __( 'Optional. Comma-separated role slugs to whitelist (never blocked). Available: %s', 'simple-payment' ), $roles ? implode( ', ', $roles ) : '—' )
	];
	$settings[ 'wc_fraud.exclude_registered' ] = [
		'title' => __( 'Exclude Registered Users', 'simple-payment' ),
		'type' => 'check',
		'section' => 'wc_fraud_settings',
		'description' => __( 'Never block logged-in (registered) users.', 'simple-payment' )
	];
	return( $settings );
} );

/* -------------------------------------------------------------------------
 * Configuration helpers
 * ---------------------------------------------------------------------- */

function sp_wc_fraud_param( $key, $default = false ) {
	return( SimplePaymentPlugin::param( 'wc_fraud.' . $key, $default ) );
}

function sp_wc_fraud_int( $key, $default ) {
	$value = sp_wc_fraud_param( $key );
	return( $value === false || $value === '' ? $default : max( 1, intval( $value ) ) );
}

function sp_wc_fraud_threshold() { return( sp_wc_fraud_int( 'threshold', 3 ) ); }
function sp_wc_fraud_period()    { return( sp_wc_fraud_int( 'period', HOUR_IN_SECONDS ) ); }
function sp_wc_fraud_cooldown()  { return( sp_wc_fraud_int( 'cooldown', HOUR_IN_SECONDS ) ); }

function sp_wc_fraud_excluded_roles() {
	$raw = sp_wc_fraud_param( 'excluded_roles' );
	if ( !$raw ) return( [] );
	return( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
}

function sp_wc_fraud_message() {
	$message = sp_wc_fraud_param( 'message' );
	if ( !$message ) $message = __( 'Please contact support.', 'simple-payment' );
	return( apply_filters( 'sp_wc_fraud_message', $message ) );
}

function sp_wc_fraud_ip() {
	if ( class_exists( 'WC_Geolocation' ) ) return( WC_Geolocation::get_ip_address() );
	return( isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ 'REMOTE_ADDR' ] ) ) : '' );
}

/* -------------------------------------------------------------------------
 * Transient keys
 * ---------------------------------------------------------------------- */

function sp_wc_fraud_bucket_key( $key ) { return( 'sp_fraud_f_' . md5( $key ) ); }
function sp_wc_fraud_block_key( $key )  { return( 'sp_fraud_b_' . md5( $key ) ); }

// The identity keys (email + ip) to track for a given order.
function sp_wc_fraud_order_keys( $order ) {
	$keys = [];
	$email = $order->get_billing_email();
	if ( $email ) $keys[] = 'email:' . strtolower( $email );
	$ip = $order->get_customer_ip_address();
	if ( !$ip ) $ip = sp_wc_fraud_ip();
	if ( $ip ) $keys[] = 'ip:' . $ip;
	return( $keys );
}

// The identity keys for the current visitor / posted checkout data.
function sp_wc_fraud_current_keys( $email = null ) {
	$keys = [];
	$ip = sp_wc_fraud_ip();
	if ( $ip ) $keys[] = 'ip:' . $ip;
	if ( !$email && is_user_logged_in() ) $email = wp_get_current_user()->user_email;
	if ( $email ) $keys[] = 'email:' . strtolower( $email );
	return( $keys );
}

/* -------------------------------------------------------------------------
 * Whitelisting
 * ---------------------------------------------------------------------- */

function sp_wc_fraud_is_whitelisted() {
	if ( is_user_logged_in() ) {
		if ( sp_wc_fraud_param( 'exclude_registered' ) ) return( true );
		$roles = sp_wc_fraud_excluded_roles();
		if ( $roles && array_intersect( $roles, (array) wp_get_current_user()->roles ) ) return( true );
	}
	return( (bool) apply_filters( 'sp_wc_fraud_whitelisted', false ) );
}

/* -------------------------------------------------------------------------
 * Detection: record failures, clear on success
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_order_status_failed', 'sp_wc_fraud_record_failure', 10, 2 );
function sp_wc_fraud_record_failure( $order_id, $order = null ) {
	$order = $order ? : wc_get_order( $order_id );
	if ( !$order ) return;
	$now = time();
	$period = sp_wc_fraud_period();
	$threshold = sp_wc_fraud_threshold();
	$cooldown = sp_wc_fraud_cooldown();
	foreach ( sp_wc_fraud_order_keys( $order ) as $key ) {
		$fails = get_transient( sp_wc_fraud_bucket_key( $key ) );
		$fails = is_array( $fails ) ? $fails : [];
		$fails[] = $now;
		// keep only failures inside the timeframe
		$fails = array_values( array_filter( $fails, function( $t ) use ( $now, $period ) { return( $t >= $now - $period ); } ) );
		set_transient( sp_wc_fraud_bucket_key( $key ), $fails, max( $period, $cooldown ) );
		if ( count( $fails ) >= $threshold ) {
			set_transient( sp_wc_fraud_block_key( $key ), $now, $cooldown );
			do_action( 'sp_wc_fraud_blocked', $key, $order, $fails );
		}
	}
}

// A successful payment breaks the streak: clear failures + block for that identity.
add_action( 'woocommerce_payment_complete', 'sp_wc_fraud_clear_for_order' );
add_action( 'woocommerce_order_status_completed', 'sp_wc_fraud_clear_for_order' );
add_action( 'woocommerce_order_status_processing', 'sp_wc_fraud_clear_for_order' );
function sp_wc_fraud_clear_for_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( !$order ) return;
	foreach ( sp_wc_fraud_order_keys( $order ) as $key ) {
		delete_transient( sp_wc_fraud_bucket_key( $key ) );
		delete_transient( sp_wc_fraud_block_key( $key ) );
	}
}

/* -------------------------------------------------------------------------
 * Enforcement: is the current visitor blocked?
 * ---------------------------------------------------------------------- */

function sp_wc_fraud_is_blocked( $email = null ) {
	if ( sp_wc_fraud_is_whitelisted() ) return( false );
	foreach ( sp_wc_fraud_current_keys( $email ) as $key ) {
		if ( get_transient( sp_wc_fraud_block_key( $key ) ) ) return( true );
	}
	return( false );
}

// Hide every payment method at checkout when blocked.
add_filter( 'woocommerce_available_payment_gateways', 'sp_wc_fraud_filter_gateways', 999 );
function sp_wc_fraud_filter_gateways( $gateways ) {
	if ( is_admin() && !wp_doing_ajax() ) return( $gateways );
	if ( sp_wc_fraud_is_blocked() ) return( [] );
	return( $gateways );
}

// Replace the "no payment methods" text with the custom message when blocked.
add_filter( 'woocommerce_no_available_payment_methods_message', 'sp_wc_fraud_no_methods_message' );
function sp_wc_fraud_no_methods_message( $message ) {
	if ( sp_wc_fraud_is_blocked() ) return( sp_wc_fraud_message() );
	return( $message );
}

// Server-side guard: refuse to place the order when blocked (defense in depth).
add_action( 'woocommerce_checkout_process', 'sp_wc_fraud_checkout_guard' );
function sp_wc_fraud_checkout_guard() {
	$email = isset( $_POST[ 'billing_email' ] ) ? sanitize_email( wp_unslash( $_POST[ 'billing_email' ] ) ) : null;
	if ( sp_wc_fraud_is_blocked( $email ) ) {
		wc_add_notice( wp_kses_post( sp_wc_fraud_message() ), 'error' );
	}
}
