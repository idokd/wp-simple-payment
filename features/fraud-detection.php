<?php
/**
 * Experimental feature: Fraud Detection (integration-agnostic).
 *
 * Detects repeated (consecutive) failed payments coming from the same identity
 * and, once a threshold is reached within a timeframe, blocks further attempts
 * for a cooldown period.
 *
 * Each identity field (email, phone, IP, user agent) has a matching mode:
 *   - default : the built-in behaviour for that field (email/phone/ip cluster,
 *               user agent ignored)
 *   - cluster : linked together with the other clustered fields - failures that
 *               share ANY clustered value form one cluster counted as a whole
 *   - primary : a sole data point, counted on its own (not clustered)
 *
 * The core (sp_fraud_record / sp_fraud_is_blocked / sp_fraud_clear) is generic
 * so any integration can feed it identity values. A WooCommerce adapter is
 * included and gated by its own "Enable WooCommerce Fraud Detection" toggle, so
 * the same settings can later drive GravityForms or the built-in Simple Payment
 * flow.
 *
 * Loaded only when enabled in Simple Payment > Experimental.
 */

defined( 'ABSPATH' ) or exit;

/* -------------------------------------------------------------------------
 * Settings (shown on the Experimental tab)
 * ---------------------------------------------------------------------- */

add_filter( 'sp_admin_sections', function( $sections ) {
	$sections[ 'fraud_settings' ] = [
		'title' => __( 'Fraud Detection', 'simple-payment' ),
		'description' => __( 'Block repeated failed payments from the same identity. Works with WooCommerce and can be reused by other integrations.', 'simple-payment' ),
		'section' => 'experimental'
	];
	return( $sections );
} );

add_filter( 'sp_admin_settings', function( $settings ) {
	$roles = function_exists( 'wp_roles' ) ? array_keys( wp_roles()->roles ) : [];
	$modes = [
		'' => __( 'Default (our behaviour)', 'simple-payment' ),
		'cluster' => __( 'Cluster', 'simple-payment' ),
		'primary' => __( 'Primary (sole data point)', 'simple-payment' ),
	];
	$field_desc = __( 'How this field is used: Default (email/phone/IP cluster, user agent ignored), Cluster (linked with other clustered fields), or Primary (counted on its own).', 'simple-payment' );
	$settings[ 'fraud.field_email' ] = [ 'title' => __( 'Match by Email', 'simple-payment' ), 'type' => 'select', 'options' => $modes, 'section' => 'fraud_settings', 'description' => $field_desc ];
	$settings[ 'fraud.field_phone' ] = [ 'title' => __( 'Match by Billing Phone', 'simple-payment' ), 'type' => 'select', 'options' => $modes, 'section' => 'fraud_settings', 'description' => $field_desc ];
	$settings[ 'fraud.field_ip' ] = [ 'title' => __( 'Match by IP Address', 'simple-payment' ), 'type' => 'select', 'options' => $modes, 'section' => 'fraud_settings', 'description' => $field_desc ];
	$settings[ 'fraud.field_user_agent' ] = [ 'title' => __( 'Match by User Agent', 'simple-payment' ), 'type' => 'select', 'options' => $modes, 'section' => 'fraud_settings', 'description' => __( 'User agent (order attribution). Ignored by default - it is a broad signal that can over-block. Set to Cluster or Primary to use it.', 'simple-payment' ) ];

	$settings[ 'fraud.threshold' ] = [ 'title' => __( 'Failed Attempts Threshold', 'simple-payment' ), 'section' => 'fraud_settings', 'description' => __( 'Number of failed attempts (per cluster, or per value for Primary fields) within the timeframe that triggers a block. Default 3.', 'simple-payment' ) ];
	$settings[ 'fraud.period' ] = [ 'title' => __( 'Timeframe (seconds)', 'simple-payment' ), 'section' => 'fraud_settings', 'description' => __( 'How far back to count failed attempts. Default 86400 (24 hours).', 'simple-payment' ) ];
	$settings[ 'fraud.cooldown' ] = [ 'title' => __( 'Cooldown / Block Duration (seconds)', 'simple-payment' ), 'section' => 'fraud_settings', 'description' => __( 'How long to block once the threshold is reached. Default 86400 (24 hours).', 'simple-payment' ) ];
	$settings[ 'fraud.message' ] = [ 'title' => __( 'Blocked Message', 'simple-payment' ), 'type' => 'textarea', 'section' => 'fraud_settings', 'description' => __( 'Shown instead of the payment methods when blocked. HTML allowed.', 'simple-payment' ) ];
	$settings[ 'fraud.excluded_roles' ] = [ 'title' => __( 'Excluded Roles', 'simple-payment' ), 'section' => 'fraud_settings', 'description' => sprintf( __( 'Optional. Comma-separated role slugs to whitelist (never blocked). Available: %s', 'simple-payment' ), $roles ? implode( ', ', $roles ) : '-' ) ];
	$settings[ 'fraud.exclude_registered' ] = [ 'title' => __( 'Exclude Registered Users', 'simple-payment' ), 'type' => 'check', 'section' => 'fraud_settings', 'description' => __( 'Never block logged-in (registered) users.', 'simple-payment' ) ];

	$settings[ 'fraud.wc_enabled' ] = [ 'title' => __( 'Enable WooCommerce Fraud Detection', 'simple-payment' ), 'type' => 'check', 'default' => true, 'section' => 'fraud_settings', 'description' => __( 'Apply fraud detection to the WooCommerce checkout (failed orders, hide payment methods when blocked). On by default.', 'simple-payment' ) ];
	return( $settings );
} );

/* -------------------------------------------------------------------------
 * Configuration helpers
 * ---------------------------------------------------------------------- */

function sp_fraud_param( $key, $default = false ) {
	return( SimplePaymentPlugin::param( 'fraud.' . $key, $default ) );
}

function sp_fraud_int( $key, $default ) {
	$value = sp_fraud_param( $key );
	return( $value === false || $value === '' ? $default : max( 1, intval( $value ) ) );
}

function sp_fraud_threshold() { return( sp_fraud_int( 'threshold', 3 ) ); }
function sp_fraud_period()    { return( sp_fraud_int( 'period', DAY_IN_SECONDS ) ); }
function sp_fraud_cooldown()  { return( sp_fraud_int( 'cooldown', DAY_IN_SECONDS ) ); }

function sp_fraud_all_fields() { return( apply_filters( 'sp_fraud_all_fields', [ 'email', 'phone', 'ip', 'user_agent' ] ) ); }

// Matching mode for a field: 'off', 'cluster' or 'primary'.
function sp_fraud_field_mode( $field ) {
	$mode = sp_fraud_param( 'field_' . $field );
	if ( $mode === 'cluster' || $mode === 'primary' ) return( $mode );
	// default behaviour: cluster the strong identifiers, ignore the user agent.
	return( $field === 'user_agent' ? 'off' : 'cluster' );
}

function sp_fraud_fields()         { return( array_values( array_filter( sp_fraud_all_fields(), function( $f ) { return( sp_fraud_field_mode( $f ) !== 'off' ); } ) ) ); }
function sp_fraud_cluster_fields() { return( array_values( array_filter( sp_fraud_all_fields(), function( $f ) { return( sp_fraud_field_mode( $f ) === 'cluster' ); } ) ) ); }
function sp_fraud_primary_fields() { return( array_values( array_filter( sp_fraud_all_fields(), function( $f ) { return( sp_fraud_field_mode( $f ) === 'primary' ); } ) ) ); }

function sp_fraud_excluded_roles() {
	$raw = sp_fraud_param( 'excluded_roles' );
	if ( !$raw ) return( [] );
	return( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
}

function sp_fraud_message() {
	$message = sp_fraud_param( 'message' );
	if ( !$message ) $message = __( 'Please contact support.', 'simple-payment' );
	return( apply_filters( 'sp_fraud_message', $message ) );
}

function sp_fraud_ip() {
	if ( class_exists( 'WC_Geolocation' ) ) return( WC_Geolocation::get_ip_address() );
	return( isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ 'REMOTE_ADDR' ] ) ) : '' );
}

function sp_fraud_user_agent() {
	return( isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) : '' );
}

function sp_fraud_normalize( $field, $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) return( '' );
	if ( $field === 'phone' ) return( preg_replace( '/\D/', '', $value ) );
	return( strtolower( $value ) );
}

// Build 'field:value' keys from a raw values map, limited to $fields (or all enabled).
function sp_fraud_keys( $values, $fields = null ) {
	if ( $fields === null ) $fields = sp_fraud_fields();
	$keys = [];
	foreach ( $fields as $field ) {
		$value = sp_fraud_normalize( $field, isset( $values[ $field ] ) ? $values[ $field ] : '' );
		if ( $value !== '' ) $keys[] = $field . ':' . $value;
	}
	return( $keys );
}

function sp_fraud_bucket_key( $key ) { return( 'sp_fraud_f_' . md5( $key ) ); }
function sp_fraud_block_key( $key )  { return( 'sp_fraud_b_' . md5( $key ) ); }

/* -------------------------------------------------------------------------
 * Whitelisting
 * ---------------------------------------------------------------------- */

function sp_fraud_is_whitelisted() {
	if ( is_user_logged_in() ) {
		if ( sp_fraud_param( 'exclude_registered' ) ) return( true );
		$roles = sp_fraud_excluded_roles();
		if ( $roles && array_intersect( $roles, (array) wp_get_current_user()->roles ) ) return( true );
	}
	return( (bool) apply_filters( 'sp_fraud_whitelisted', false ) );
}

/* -------------------------------------------------------------------------
 * Core: record a failed attempt, test a visitor, clear on success.
 * These are integration-agnostic - pass a values map keyed by field name.
 * ---------------------------------------------------------------------- */

function sp_fraud_record( $values ) {
	$now = time();
	$period = sp_fraud_period();
	$threshold = sp_fraud_threshold();
	$cooldown = sp_fraud_cooldown();

	// Primary fields: each value has its own independent counter.
	foreach ( sp_fraud_keys( $values, sp_fraud_primary_fields() ) as $key ) {
		$fails = get_transient( sp_fraud_bucket_key( $key ) );
		$fails = is_array( $fails ) ? $fails : [];
		$fails[] = $now;
		$fails = array_values( array_filter( $fails, function( $t ) use ( $now, $period ) { return( $t >= $now - $period ); } ) );
		set_transient( sp_fraud_bucket_key( $key ), $fails, max( $period, $cooldown ) );
		if ( count( $fails ) >= $threshold ) {
			set_transient( sp_fraud_block_key( $key ), $now, $cooldown );
			do_action( 'sp_fraud_blocked', $key, $values, $fails );
		}
	}

	// Cluster fields: failures sharing ANY clustered value form one cluster.
	$cluster_keys = sp_fraud_keys( $values, sp_fraud_cluster_fields() );
	if ( $cluster_keys ) {
		$log = get_transient( 'sp_fraud_log' );
		$log = is_array( $log ) ? $log : [];
		$log = array_values( array_filter( $log, function( $e ) use ( $now, $period ) { return( isset( $e[ 't' ] ) && $e[ 't' ] >= $now - $period ); } ) );
		$log[] = [ 't' => $now, 'k' => $cluster_keys ];
		if ( count( $log ) > 500 ) $log = array_slice( $log, -500 );
		set_transient( 'sp_fraud_log', $log, max( $period, $cooldown ) );

		$component = sp_fraud_component( $log, count( $log ) - 1 );
		if ( count( $component ) >= $threshold ) {
			$block = [];
			foreach ( $component as $i ) foreach ( $log[ $i ][ 'k' ] as $k ) $block[ $k ] = 1;
			foreach ( array_keys( $block ) as $k ) set_transient( sp_fraud_block_key( $k ), $now, $cooldown );
			do_action( 'sp_fraud_blocked_cluster', array_keys( $block ), $values, $component );
		}
	}

	do_action( 'sp_fraud_recorded', $values );
}

// Connected component (transitive) of a log entry, linking entries that share
// any clustered value.
function sp_fraud_component( $log, $start ) {
	$seen = [ $start => true ];
	$stack = [ $start ];
	$component = [];
	while ( $stack ) {
		$i = array_pop( $stack );
		$component[] = $i;
		foreach ( $log as $j => $entry ) {
			if ( isset( $seen[ $j ] ) ) continue;
			if ( array_intersect( $log[ $i ][ 'k' ], $entry[ 'k' ] ) ) {
				$seen[ $j ] = true;
				$stack[] = $j;
			}
		}
	}
	return( $component );
}

function sp_fraud_is_blocked( $values ) {
	if ( sp_fraud_is_whitelisted() ) return( false );
	foreach ( sp_fraud_keys( $values ) as $key ) {
		if ( get_transient( sp_fraud_block_key( $key ) ) ) return( true );
	}
	return( false );
}

function sp_fraud_clear( $values ) {
	$keys = sp_fraud_keys( $values );
	foreach ( $keys as $key ) {
		delete_transient( sp_fraud_bucket_key( $key ) );
		delete_transient( sp_fraud_block_key( $key ) );
	}
	$log = get_transient( 'sp_fraud_log' );
	if ( is_array( $log ) && $keys ) {
		$log = array_values( array_filter( $log, function( $e ) use ( $keys ) { return( !array_intersect( $e[ 'k' ], $keys ) ); } ) );
		set_transient( 'sp_fraud_log', $log, max( sp_fraud_period(), sp_fraud_cooldown() ) );
	}
}

// Identity values for the current visitor. $overrides may carry posted email/phone.
function sp_fraud_current_values( $overrides = [] ) {
	$values = [
		'ip' => sp_fraud_ip(),
		'user_agent' => sp_fraud_user_agent(),
		'email' => isset( $overrides[ 'email' ] ) ? $overrides[ 'email' ] : ( is_user_logged_in() ? wp_get_current_user()->user_email : '' ),
		'phone' => isset( $overrides[ 'phone' ] ) ? $overrides[ 'phone' ] : ( is_user_logged_in() ? get_user_meta( get_current_user_id(), 'billing_phone', true ) : '' ),
	];
	return( apply_filters( 'sp_fraud_current_values', array_merge( $values, $overrides ) ) );
}

/* =========================================================================
 * WooCommerce adapter - only active when WooCommerce is present AND the
 * "Enable WooCommerce Fraud Detection" toggle is on.
 * ====================================================================== */

function sp_fraud_wc_enabled() {
	$value = sp_fraud_param( 'wc_enabled' );
	if ( $value === false ) return( true ); // default on
	return( $value !== '0' && $value !== '' && (bool) $value );
}

if ( function_exists( 'WC' ) ) {

	function sp_fraud_wc_order_values( $order ) {
		return( [
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
			'ip' => $order->get_customer_ip_address() ? : sp_fraud_ip(),
			'user_agent' => $order->get_meta( '_wc_order_attribution_user_agent' ) ? : sp_fraud_user_agent(),
		] );
	}

	// Record failed WooCommerce orders.
	add_action( 'woocommerce_order_status_failed', function( $order_id, $order = null ) {
		if ( !sp_fraud_wc_enabled() ) return;
		$order = $order ? : wc_get_order( $order_id );
		if ( $order ) sp_fraud_record( sp_fraud_wc_order_values( $order ) );
	}, 10, 2 );

	// A successful payment breaks the streak.
	foreach ( [ 'woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_processing' ] as $sp_fraud_hook ) {
		add_action( $sp_fraud_hook, function( $order_id ) {
			if ( !sp_fraud_wc_enabled() ) return;
			$order = wc_get_order( $order_id );
			if ( $order ) sp_fraud_clear( sp_fraud_wc_order_values( $order ) );
		} );
	}
	unset( $sp_fraud_hook );

	// Hide every payment method at checkout when blocked.
	add_filter( 'woocommerce_available_payment_gateways', function( $gateways ) {
		if ( ( is_admin() && !wp_doing_ajax() ) || !sp_fraud_wc_enabled() ) return( $gateways );
		return( sp_fraud_is_blocked( sp_fraud_current_values() ) ? [] : $gateways );
	}, 999 );

	// Replace the "no payment methods" text with the custom message when blocked.
	add_filter( 'woocommerce_no_available_payment_methods_message', function( $message ) {
		if ( !sp_fraud_wc_enabled() ) return( $message );
		return( sp_fraud_is_blocked( sp_fraud_current_values() ) ? sp_fraud_message() : $message );
	} );

	// Server-side guard: refuse to place the order when blocked.
	add_action( 'woocommerce_checkout_process', function() {
		if ( !sp_fraud_wc_enabled() ) return;
		$posted = [];
		if ( isset( $_POST[ 'billing_email' ] ) ) $posted[ 'email' ] = sanitize_email( wp_unslash( $_POST[ 'billing_email' ] ) );
		if ( isset( $_POST[ 'billing_phone' ] ) ) $posted[ 'phone' ] = sanitize_text_field( wp_unslash( $_POST[ 'billing_phone' ] ) );
		if ( sp_fraud_is_blocked( sp_fraud_current_values( $posted ) ) ) wc_add_notice( wp_kses_post( sp_fraud_message() ), 'error' );
	} );
}
