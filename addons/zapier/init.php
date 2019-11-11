<?php

add_action('sp_extension_zapier', 'sp_zapier');

function sp_zapier($params = []) {
    global $wpdb;
    header('Content-Type: application/json');
    $method = isset($_REQUEST['method']) ? $_REQUEST['method'] : 'default';
    $api_key = isset($_REQUEST['api_key']) ? $_REQUEST['api_key'] : null;
    if (!$api_key || $api_key != SimplePaymentPlugin::param('api_key')) {
      print json_encode(['error' => 401, 'description' => __('API KEY Invalid', 'simple-payment')]);
      die;
    }
    $sp = SimplePaymentPlugin::instance();
    switch ($method) {
        case 'archive':
          SimplePaymentPlugin::archive($_REQUEST['id']);
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `id` = '.$_REQUEST['id'];
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transaction':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `id` = '.$_REQUEST['id'];
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transactions':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 0 ORDER BY `created` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transactions_updated':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 0 ORDER BY `modified` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transactions_archived':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 1 ORDER BY `created` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;
        case 'transactions_pending':
          $sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `archived` = 0 AND `status` = '.SimplePaymentPlugin::TRANSACTION_PENDING.' ORDER BY `created` DESC';
          $zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          break;  
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