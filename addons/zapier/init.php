<?php

add_action( 'sp_extension_zapier', 'sp_zapier' );

function sp_zapier($params = []) {
    global $wpdb, $SPWP;
    header('Content-Type: application/json');
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
          //$sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `id` = '.$_REQUEST['id'];
          //$zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
          $zapier = $SPWP->fetch($_REQUEST['id']);
          break;
        case 'transaction':
          //$sql = 'SELECT * FROM '.$wpdb->prefix.SimplePaymentPlugin::$table_name.' WHERE `id` = '.$_REQUEST['id'];
          //$zapier = $wpdb->get_results( $sql , 'ARRAY_A' );
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
            if (isset($_REQUEST['sandbox']) && $_REQUEST['sandbox']) 
            $sql .= " AND `sandbox` = 1";
          if (isset($_REQUEST['id']) && $_REQUEST['id']) 
            $sql .= " AND `id` = ".absint($_REQUEST['id']);  
          //if (isset($_REQUEST['archived']) && $_REQUEST['archived']) 
          //  $sql .= " AND `archived` = 1";
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