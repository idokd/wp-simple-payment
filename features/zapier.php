<?php
/**
 * Experimental feature: Zapier integration.
 *
 * Exposes a JSON endpoint (the Simple Payment callback URL with
 * op=zapier&api_key=...) that Zapier polls for transactions and updates.
 *
 * Moved from addons/zapier. Loaded only when enabled in Simple Payment >
 * Experimental, and gated by its own "Enable Zapier" toggle.
 */

defined( 'ABSPATH' ) or exit;

/* -------------------------------------------------------------------------
 * Settings (shown on the Experimental tab)
 * ---------------------------------------------------------------------- */

add_filter( 'sp_admin_sections', function( $sections ) {
	$sections[ 'zapier_settings' ] = [
		'title' => __( 'Zapier', 'simple-payment' ),
		'description' => __( 'Connect Simple Payment to Zapier to trigger workflows on payments (new/updated/archived transactions).', 'simple-payment' ),
		'section' => 'experimental'
	];
	return( $sections );
} );

add_filter( 'sp_admin_settings', function( $settings ) {
	$api_key = SimplePaymentPlugin::param( 'api_key' );
	$note = $api_key
		? sprintf( __( 'Use this URL as the Zapier endpoint (it includes your API key):<br><code>%s</code>', 'simple-payment' ), esc_url( sp_zapier_url() ) )
		: __( 'Generate an API KEY first on the Extensions tab, then the Zapier endpoint URL will appear here.', 'simple-payment' );
	$settings[ 'zapier.enabled' ] = [
		'title' => __( 'Enable Zapier', 'simple-payment' ),
		'type' => 'check',
		'default' => true,
		'section' => 'zapier_settings',
		'description' => __( 'Expose the Zapier endpoint (on by default).', 'simple-payment' ) . '<br>' . $note
	];
	return( $settings );
} );

function sp_zapier_enabled() {
	$value = SimplePaymentPlugin::param( 'zapier.enabled' );
	if ( $value === false ) return( true ); // default on
	return( $value !== '0' && $value !== '' && (bool) $value );
}

// The Zapier endpoint URL (callback URL + op=zapier + api_key).
function sp_zapier_url() {
	$sp = SimplePaymentPlugin::instance();
	$callback = method_exists( $sp, 'callback_url' ) ? $sp->callback_url() : '';
	if ( !$callback ) $callback = home_url( '/' );
	if ( strpos( $callback, '://' ) === false ) $callback = site_url( $callback );
	return( add_query_arg( [ SimplePaymentPlugin::OP => 'zapier', 'api_key' => SimplePaymentPlugin::param( 'api_key' ) ], $callback ) );
}

/* -------------------------------------------------------------------------
 * Endpoint (op=zapier)
 * ---------------------------------------------------------------------- */

add_action( 'sp_extension_zapier', 'sp_zapier' );

function sp_zapier( $params = [] ) {
    global $wpdb, $SPWP;
    header('Content-Type: application/json');
    if ( !sp_zapier_enabled() ) {
      http_response_code( 403 );
      print json_encode( [ 'error' => 403, 'description' => __( 'Zapier integration is disabled', 'simple-payment' ) ] );
      die;
    }
    $method = isset($_REQUEST['method']) ? $_REQUEST['method'] : 'default';
    $api_key = isset($_REQUEST['api_key']) ? $_REQUEST['api_key'] : null;
    if (!$api_key || $api_key != SimplePaymentPlugin::param('api_key')) {
      http_response_code(401);
      print json_encode(['error' => 401, 'description' => __('API KEY Invalid', 'simple-payment')]);
      die;
    }
    $sp = SimplePaymentPlugin::instance();
    switch ($method) {
        case 'archive':
          SimplePaymentPlugin::archive($_REQUEST['id']);
          $zapier = $SPWP->fetch($_REQUEST['id']);
          break;
        case 'transaction':
          $zapier = $SPWP->fetch($_REQUEST['id']);
          break;
        case 'transactions':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 0';
          if (isset($_REQUEST['status']) && $_REQUEST['status'])
              $sql .= sprintf(" AND `status` LIKE '%s'", esc_sql($_REQUEST['status']));
          if (isset($_REQUEST['engine']) && $_REQUEST['engine'])
              $sql .= sprintf(" AND `engine` LIKE '%s'", esc_sql($_REQUEST['engine']));
          if (isset($_REQUEST['transaction_id']) && $_REQUEST['transaction_id'])
            $sql .= sprintf(" AND `transaction_id` LIKE '%s'", esc_sql($_REQUEST['transaction_id']));
          if (isset($_REQUEST['confirmation_code']) && $_REQUEST['confirmation_code'])
            $sql .= sprintf(" AND `confirmation_code` LIKE '%s'", esc_sql($_REQUEST['confirmation_code']));
          if (isset($_REQUEST['sandbox']) && $_REQUEST['sandbox'])
            $sql .= " AND `sandbox` = 1";
          if (isset($_REQUEST['id']) && $_REQUEST['id'])
            $sql .= " AND `id` = ".absint($_REQUEST['id']);
          $sql .= ' ORDER BY `created` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transactions_updated':
        case 'updates':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 0 ORDER BY `modified` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transactions_archived':
        case 'archives':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 1 ORDER BY `created` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transactions_pending':
        case 'pendings':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 0 AND `status` = '.SimplePaymentPlugin::TRANSACTION_PENDING.' ORDER BY `created` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'subscribe':
        case 'auth':
        default:
          $zapier = [
            'site' => get_bloginfo('url'),
            'version' => SimplePaymentPlugin::VERSION,
            'name' => get_bloginfo('name'),
            'platform' => 'Wordpress',
            'license' => SimplePaymentPlugin::$license,
            'initiator' => get_class($sp),
            'platform_version' => get_bloginfo('version'),
            'plugin_versoin' => SimplePaymentPlugin::$version
          ];
          break;
    }
    print json_encode($zapier);
    die;
}
