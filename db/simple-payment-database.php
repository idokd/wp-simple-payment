<?php

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

$sp_db_version = '3';

register_activation_hook( __FILE__, 'sp_install' );
register_activation_hook( __FILE__, 'sp_install_data' );

add_action( 'plugins_loaded', 'sp_update_db_check' );

function sp_update_db_check() {
    global $sp_db_version;
    if ( get_option( 'sp_db_version' ) != $sp_db_version ) {
        sp_install();
    }
}

function sp_install() {
  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

  global $wpdb, $sp_db_version;
  $charset_collate = $wpdb->get_charset_collate();
  $table_name = $wpdb->prefix . "sp_transactions";
  $sql = "CREATE TABLE $table_name (
    `id` MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
    `engine` VARCHAR(50) DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT NULL,
    `transaction_id` VARCHAR(50) DEFAULT NULL,
    `url` VARCHAR(255) DEFAULT NULL,
    `payments` VARCHAR(255) DEFAULT NULL,
    `parameters` TEXT DEFAULT NULL,
    `currency` VARCHAR(5) DEFAULT NULL,
    `amount` DECIMAL(10,2),
    `concept` VARCHAR(250) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `error_code` VARCHAR(255) DEFAULT NULL,
    `error_description` VARCHAR(255) DEFAULT NULL,
    `retries` TINYINT(1) NOT NULL DEFAULT 0,
    `archived` TINYINT(1) NOT NULL DEFAULT 0,
    `modified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_id` (`id`),
      KEY `idx_engine` (`engine`),
      KEY `idx_transaction` (`engine`,`transaction_id`),
      KEY `idx_user` (`user_id`),
      KEY `idx_archived` (`archived`)
  ) $charset_collate;

  ALTER TABLE $table_name  AUTO_INCREMENT = 1000;";

  dbDelta( $sql );

  $table_name = $wpdb->prefix . "sp_cardcom";
  $sql = "CREATE TABLE $table_name (
    `id` MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
    `transaction_id` VARCHAR(50),
    `code` TINYTEXT DEFAULT NULL,
    `terminal` MEDIUMINT(9) NOT NULL,
    `profile_code` VARCHAR(50) NOT NULL,
    `operation` INT NOT NULL,
    `response_code` INT DEFAULT NULL,
    `status_code` INT DEFAULT NULL,
    `deal_response` INT DEFAULT NULL,
    `token_response` INT DEFAULT NULL,
    `token` VARCHAR(50) DEFAULT NULL,
    `operation_response` INT DEFAULT NULL,
    `operation_description` TEXT DEFAULT NULL,
    `request` TEXT DEFAULT NULL,
    `response` TEXT DEFAULT NULL,
    `modified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_id` (`id`),
      KEY `idx_transaction` (`transaction_id`),

  ) $charset_collate;";

  dbDelta( $sql );

  update_option( "sp_db_version", $sp_db_version );
}

function sp_install_data() {
	global $wpdb;

/*	$welcome_name = 'Mr. WordPress';
	$welcome_text = 'Congratulations, you just completed the installation!';

	$table_name = $wpdb->prefix . 'liveshoutbox';

	$wpdb->insert(
		$table_name,
		array(
			'time' => current_time( 'mysql' ),
			'name' => $welcome_name,
			'text' => $welcome_text,
		)
	);*/
}


function sp_uninstall() {
  global $wpdb, $wp_rewrite;
  if (!defined('WP_UNINSTALL_PLUGIN')) exit();
  $uninstall = get_option('sp_uninstall', 'all');
  if ($uninstall == 'all' || $uninstall == 'tables') {
    $tables = ['transactions', 'cardcom'];
    foreach ($tables as $table) $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "sp_".$table);
  }
  if ($uninstall == 'all' || $uninstall == 'settings') {
    $options = ['sp_uninstall_drop_table', 'sp_db_version', 'sp'];
    foreach ($options as $option) {
      delete_option($option);
      delete_site_option($option);
    }
  }
  $wp_rewrite->flush_rules();
}
