<?php

class SimplePaymentAdmin {
	
	public static $instance;
  	protected static $params;
	protected $option_name = 'sp';

	protected $test_shortcodes = [
		'button' => [
			'title' => 'Standard Button Shortcode',
			'description' => 'Show standard button',
			'shortcode' => '[simple_payment product="Test Product" amount="99.00" type="button" target="_blank"]'
		],
		'paypal' => [
			'title' => 'Paypal Button Shortcode',
			'description' => 'Show standard button',
			'shortcode' => '[simple_payment product="Test Product" amount="99.00" title="Buy via Paypal" type="button" target="_blank" method="paypal"]'
		]
	];

	public function __construct( $params = [] ) {
		self::$params = $params;
		$this->add_hooks();
	}

	public static function instance( $params = [] ) {
		if ( !self::$instance ) self::$instance = new self( $params );
		return( self::$instance );
	}

	public function add_hooks() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

		add_action( 'upgrader_process_complete', [ $this, 'upgraded' ], 10, 2 );

		add_action( 'admin_notices', [ $this, 'notices' ] );

		//if (isset($_REQUEST['action'])) {
		//  do_action("admin_post_{$_REQUEST['action']}", [$this, 'archive']);
		//}

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ], 10, 2 );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

		add_filter( 'display_post_states', [$this, 'add_custom_post_states']);
		add_action( 'admin_menu', [ $this, 'add_plugin_options_page' ] );
		if ( !empty( $_REQUEST[ 'page' ] ) ) {
			switch ( $_REQUEST[ 'page' ] ) {
				case 'simple-payments':
					add_filter( 'set-screen-option', [ $this, 'screen_option' ], 10, 3 );
					break;
				default:
					break;
			}
		}
		//add_action( 'admin_menu', [ $this, 'add_plugin_options_page' ] );
		if ( !empty( $GLOBALS[ 'pagenow' ] ) ) {
			switch ( $GLOBALS[ 'pagenow' ] ) {
				case 'options-general.php':
				case 'options.php':
				case 'options-reading.php':
					add_action( 'admin_init', [ $this, 'add_plugin_settings' ] );
					break;
				default:
					break;
			}
		}

		$_active_plugins = array_merge( is_multisite() ? array_keys( get_site_option( 'active_sitewide_plugins', [] ) ) : [], get_option( 'active_plugins', [] ) );
		//if ( in_array( 'admin-columns-pro/admin-columns-pro.php', $_active_plugins ) ) {
		//	add_action( 'after_setup_theme', function () {
		//		require_once( SPWP_PLUGIN_DIR . '/addons/admin-columns-pro/init.php' );
		//	} );
		//}

		add_action( 'admin_enqueue_scripts', function () {
			wp_enqueue_script( 'simple-payment-admin-js', plugin_dir_url( __FILE__ ) . 'script.js', [], false, true );
			wp_enqueue_style( 'simple-payment-admin-css', plugin_dir_url( __FILE__ ) . 'style.css', [], false );
		} );

		add_action( 'admin_init', [ $this, 'register_license_settings' ] );
		//add_action( 'sp_admin_tabs', [ $this, 'add_plugin_settings' ] );
		//add_filter( 'sp_admin_sections', [ $this, 'add_plugin_settings' ] );
	}

	public static function param( $key = null, $default = false ) {
		global $SPWP;
		return( $SPWP::param( $key, $default ) );
	}

	function notices() {
		if ( get_transient( 'sp_message' ) ) {
			echo '<div class="notice notice-success"><p>' . get_transient( 'sp_message' ) . '</p></div>';
			delete_transient( 'sp_message' );
		}
		if ( get_transient( 'sp_license_issue' ) ) {
			echo '<div class="notice notice-error"><p>' . get_transient( 'sp_license_issue' ) . '</p></div>';
		}
		if( get_transient( 'sp_updated' ) ) {
			echo '<div class="notice notice-success"><p>' . __( 'Thanks for updating Simple Payment, you can checkout for new features and updates <a href="https://simple-payment.yalla-ya.com" target="_blank">here</a>.', 'simple-payment' ) . '</p></div>';
			delete_transient( 'sp_updated' );
		}
		if ( get_transient( 'sp_activated' ) ) {
			echo '<div class="notice notice-success"><p>' . __( 'Thanks for installing Simple Payment, after your test our plugin, dont forget to get your license to process real transactions, you can do it <a href="https://simple-payment.yalla-ya.com" target="_blank">here</a>.', 'simple-payment' ) . '</p></div>';
			delete_transient( 'sp_activated' );
		}
		
	}

	function upgraded( $upgrader_object, $options ) {
		$spwp = plugin_basename( __FILE__ );
		if ( $options[ 'action' ] == 'update' && $options[ 'type' ] == 'plugin' && isset( $options[ 'plugins' ] ) ) {
			foreach( $options[ 'plugins' ] as $plugin ) {
				if ( $plugin == $spwp ) set_transient( 'sp_updated', 1 );
			}
		}
		// TODO: consider rechecking license and deactivating live mode if necessary
	}


	function plugin_action_links( $plugin_actions, $plugin_file ) {
		$plugin_actions[] = '<a href="' . esc_url( admin_url( '/options-general.php?page=sp' ) ) . '">' . __( 'Settings', 'simple_payment' ) . '</a>';
		// TODO: Add buy support, Documentation
		return( $plugin_actions );
	}

	public static function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$row_meta = array(
				'docs'    => '<a href="https://simple-payment.yalla-ya.com/docs" aria-label="' . esc_attr__( 'View Simple Payment documentation', 'simple-payment' ) . '" target="_blank">' . esc_html__( 'Docs', 'simple-payment' ) . '</a>',
				'support' => '<a href="https://simple-payment.yalla-ya.com/contact-us/" aria-label="' . esc_attr__( 'Visit premium customer support', 'simple-payment' ) . '" target="_blank">' . esc_html__( 'Premium support', 'simple-payment' ) . '</a>',
			);

			return array_merge( $links, $row_meta );
		}
		return (array) $links;
	}
	
	function activate() {
		set_transient( 'sp_activated', 1 );
	}

	function deactivate() {
		global $wp_rewrite;
		$timestamp = wp_next_scheduled( 'sp_cron' ); 
		if ( $timestamp ) wp_unschedule_event( $timestamp, 'sp_cron' ); 
		$timestamp = wp_next_scheduled( 'sp_cron_purge' ); 
		if ( $timestamp ) wp_unschedule_event( $timestamp, 'sp_cron_purge' ); 
		$wp_rewrite->flush_rules();
	}
	
	function setting_callback_function( $args ) {
		$project_page_id = self::param( 'payment_page' );
		$args = [
			'posts_per_page'   => -1,
			'orderby'          => 'name',
			'order'            => 'ASC',
			'post_type'        => 'page',
		];
		$items = get_posts( $args );
		echo '<select id="sp_payment_page" name="sp[payment_page]">';
		echo '<option value="0">' . __( '— Select —', 'wordpress' ) . '</option>';
		foreach ( $items as $item ) {
			echo '<option value="' . esc_attr( $item->ID ) . '" ' . ( $project_page_id == $item->ID ? 'selected="selected"' : '' ) . '>' . esc_html( $item->post_title ) . '</option>';
		}
		echo '</select>';
	}

  function add_custom_post_states( $states ) {
      global $post;
      $payment_page_id = self::param( 'payment_page' );
      if ( is_object( $post ) && 'page' == get_post_type( $post->ID ) && $post->ID == $payment_page_id && $payment_page_id != '0' ) {
          $states[] = __( 'Payment Page', 'simple-payment' );
      }
      return( $states );
  }

  public function add_plugin_options_page() {
	  add_options_page(
		  __( 'Simple Payment', 'simple-payment' ),
		  __( 'Simple Payment', 'simple-payment' ),
		  'manage_options',
		  'sp',
		  [ $this, 'render_admin_page' ]
	  );

	  $hook = add_menu_page(
		  __( 'Payments', 'simple-payment' ),
		  __( 'Payments', 'simple-payment' ),
		  'manage_options',
		  'simple-payments',
		  [ $this, 'render_transactions' ],
		  SPWP_PLUGIN_URL . 'assets/simple-payment-icon.png',
		  30
	  );
	  add_action( "load-$hook", [ $this, 'transactions' ] );

	  $hook = add_submenu_page( null,
							   __( 'Transaction Details', 'simple-payment' ),
							   __( 'Transaction Details', 'simple-payment' ),
							   'manage_options',
							   'simple-payments-details',
							   [ $this, 'render_transaction_log' ]
							  );
	  add_action( "load-$hook", [ $this, 'info' ] );
  }

	public function screen_option($status, $option, $value) {
		return( 'sp_per_page' == $option ? $value : $status );
	}

	// Render our plugin's option page.
	public function render_admin_page() {
		if ( !current_user_can( 'manage_options' ) ) return;

		$tab = isset( $_GET[ 'tab' ] ) ? sanitize_text_field( $_GET[ 'tab' ] ) : 'sp';
		$section = $tab;
		$tabs = apply_filters( 'sp_admin_tabs', [ 'General', 'PayPal', 'Cardcom', 'iCount', 'PayMe', 'Meshulam',  'YaadPay', 'iCredit', 'CreditGuard', 'Credit2000', 'License', 'Extensions', 'Shortcode', 'Instructions' ] );
?>
<div class="wrap">
	<h1><?php _e( 'Simple Payment Settings', 'simple-payment' ); ?></h1>
	<h2 class="nav-tab-wrapper">
		<?php foreach ( $tabs as $stab => $label ) {
			$stab = is_numeric( $stab ) ? $label : $stab;
			$general = strtolower( $stab ) == 'general';
			echo '<a id="' . (  $general ? 'sp' : strtolower( $stab ) ) .'" href="options-general.php?page=sp' . ( $general ? '' : '&tab=' . strtolower( $stab ) ) . '" class="nav-tab ' . ( strtolower( $tab ) == $stab ? 'nav-tab-active' : '' ) . '">' . __( $label, 'simple-payment' ) . '</a>';
		} ?>
	</h2>
	<?php
		switch ($tab) {
			case 'instructions':
				require(SPWP_PLUGIN_DIR.'/admin/instructions.php');
				break;
			case 'shortcode':
				require( SPWP_PLUGIN_DIR.'/admin/shortcode.php' );
				foreach ( $this->test_shortcodes as $key => $shortcode ) {
					if ( isset( $shortcode[ 'title' ] ) ) echo '<div>' . $shortcode[ 'title' ].'</div>';
					if ( isset( $shortcode[ 'description' ] ) ) echo '<div>' . $shortcode[ 'description' ] . '</div>';
					echo '<pre>' . $shortcode[ 'shortcode' ] . '</pre>';
					echo do_shortcode( $shortcode[ 'shortcode' ] );
				}
				break;
			default:
				echo '<form method="post" action="options.php">';
				settings_fields( 'sp' );
				do_settings_sections( $section );
				submit_button();
				echo '</form>';
		}
		echo "</div>";
	}

	public function register_license_settings() {
		register_setting( 'sp', 'sp_license', [ 'type' => 'string', 'sanitize_callback' => [ $this, 'license_key_callback' ] ] );
		add_settings_field(
			'sp_license',
			__( 'License Key', 'simple-payment' ),
			[ $this, 'render_license_key_field' ],
			'license',
			'licensing',
			[ 'label_for' => 'sp_license' ]
		);
	}
	
	// Initialize our plugin's settings.
	public function add_plugin_settings() {
		$this->register_reading_setting();
		$this->register_license_settings();

		require( SPWP_PLUGIN_DIR . '/admin/settings.php' );
		$this->sections = apply_filters( 'sp_admin_sections', $sp_sections );
		$sp_settings = apply_filters( 'sp_admin_settings', $sp_settings );
		foreach ( $sp_sections as $key => $section ) {
			add_settings_section(
				$key,
				$section[ 'title' ],
				[ $this, isset( $section[ 'render_function' ]) ? $section[ 'render_function' ] : 'render_section' ],
				isset( $section[ 'section' ] ) ? $section[ 'section' ] : 'sp'
			);
		}
		register_setting( 'sp', 'sp', [ 'sanitize_callback' => [ $this, 'validate_options' ], 'default' => [] ] );

		foreach ( $sp_settings as $key => $value ) {
			add_settings_field(
				$key,
				$value[ 'title' ],
				[ $this, isset($value['render_function']) ? $value[ 'render_function' ] : 'render_setting_field' ],
				isset( $value[ 'section' ] ) && isset( $this->sections[ $value[ 'section' ] ] ) ? $this->sections[ $value[ 'section' ] ]['section'] : 'sp',
				isset( $value[ 'section' ] ) ? $value[ 'section' ] : 'settings',
				[ 'option' => $key, 'params' => $value, 'default' => NULL ],
				array( 'label_for' => $key )
			);
			if ( isset( $value[ 'sanitize_callback' ] ) ) register_setting( 'sp', $key, [ 'sanitize_callback' => [ $this, $value[ 'sanitize_callback' ] ], 'default' => [] ] );
		}
	}

	function param_name( $key ) {
		$keys = explode( '.', $key );
		$keys = join( '][', $keys );
		return( '[' . $keys . ']' );
	}

	protected function validate_single( $options ) {
		foreach( $options as $key => $value ) $options[ $key ] = is_array( $value ) ? $this->validate_single( $value ) :  sanitize_text_field( stripslashes( $value ) );
		return( $options );
	}

	public function validate_options( $options ) {
		if (!is_array($options)) $options = isset($_REQUEST['sp']) ? $_REQUEST['sp'] : [];
		if (is_array($options)) $options = $this->validate_single($options);
		else $options = sanitize_text_field($options);
		$options = array_merge(self::$params, $options);
		if (isset($options['api_key_reset']) && $options['api_key_reset']) {
			$options['api_key'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
										  // 32 bits for "time_low"
										  mt_rand(0, 0xffff), mt_rand(0, 0xffff),
										  // 16 bits for "time_mid"
										  mt_rand(0, 0xffff),
										  // 16 bits for "time_hi_and_version",
										  // four most significant bits holds version number 4
										  mt_rand(0, 0x0fff) | 0x4000,
										  // 16 bits, 8 bits for "clk_seq_hi_res",
										  // 8 bits for "clk_seq_low",
										  // two most significant bits holds zero and one for variant DCE1.1
										  mt_rand(0, 0x3fff) | 0x8000,
										  // 48 bits for "node"
										  mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
										 );
			unset($options['api_key_reset']);
		}
		return($options);
	}

	public function render_section( $params = null ) {
		$section_id = $params[ 'id' ];
		//if ( isset( $params[ 'title' ] ) ) print $params[ 'title' ] . '<br/ >';
		if ( isset( $this->sections[ $section_id ][ 'description' ] ) ) print $this->sections[ $section_id ][ 'description' ] . '<br/ >';
	}

	// Render the license key field.
	public function render_license_key_field() {
		global $SPWP;
		printf(
			'<input type="text" id="key" size="40" name="sp_license[key]" value="%s" />',
			isset( $SPWP::$license[ 'key' ] ) ? esc_attr( $SPWP::$license[ 'key' ] ) : ''
		);

		if ( isset( $SPWP::$license[ 'status' ] ) ) { 
			printf(
				'&nbsp;<span class="description">License %s</span>',
				isset( $SPWP::$license[ 'status' ] ) ? esc_attr( $SPWP::$license[ 'status' ] ) : 'is missing'
			);
		}
	}

	function sanitize_text_field( $value ) {
		return( sanitize_text_field( $value ) );
	}
	
	function register_reading_setting() {
		register_setting(
			'reading',
			'sp',
			[ 'type' => 'string',
			 'sanitize_callback' => 'sanitize_text_field',
			 'default' => NULL ]
		);
		add_settings_field(
			'sp_payment_page',
			__('Payment Page', 'simple-payment'),
			[ $this, 'setting_callback_function' ],
			'reading',
			'default',
			[ 'label_for' => 'sp_payment_page' ]
		);
	}

	function render_setting_field( $options ) {
		$type = isset( $options[ 'params' ][ 'type' ] ) ? $options[ 'params' ][ 'type' ] : 'string';
		switch ( $type ) {
			case 'select':
				if ( !isset( $options[ 'params' ][ 'options' ] ) ) {
					$items = [];
					for ( $i = $options[ 'params' ][ 'min' ]; $i <= $options[ 'params' ][ 'max' ]; $i++ ) {
						$items[ $i ] = $i;
					}
					$options[ 'params' ][ 'options' ] = $items;
				}
				$this->setting_select_fn($options['option'], $options['params']);
				break;
			case 'check':
				$this->setting_check_fn($options['option'], $options['params']);
				break;
			case 'radio':
				$this->setting_radio_fn( $options[ 'option' ], $options[ 'params' ] );
				break;
			case 'textarea':
				$this->setting_textarea_fn($options['option'], $options['params']);
				break;
			case 'password':
				$this->setting_password_fn($options['option'], $options['params']);
				break;
			case 'random':
				$this->setting_random_fn($options['option'], $options['params']);
				break;
			case 'string':
			default:
				$this->setting_text_fn($options['option'], $options['params']);
				break;
		}
	}


	function setting_select_fn( $key, $params = null ) {
		$option = isset( $params[ 'legacy' ] ) ? get_option( $key ) : self::param( $key );
		$items = $params[ 'options' ];
		$field = isset( $params[ 'legacy' ] ) ? $key : $this->option_name . $this->param_name( $key );
		echo "<select id='" . esc_attr( $key ) . "' name='" . esc_attr ( $field ) ."'>";
		$auto = isset( $params[ 'auto' ] ) && $params[ 'auto' ];
		if ( $auto ) echo "<option value=''>" . ( $auto ? __( 'Auto', 'simple-payment' ) : '' ) . "</option>";
		foreach ( $items as $value => $title ) {
			$selected = ( $option != '' && $option == $value ) ? ' selected="selected"' : '';
			if ( isset( $params[ 'display' ] ) && $params[ 'display' ] == 'both' ) $title = $value . ' - ' . $title;
			echo "<option value='" . esc_attr( $value ) . "'" . $selected . ">" . esc_html( $title ) . "</option>";
		}
		echo "</select>";
	}

	function setting_radio_fn( $key, $params = null ) {
		$option = self::param( $key );
		$items = $params[ 'options' ];
		$field = $this->option_name . $this->param_name( $key );
		foreach( $items as $value => $title ) {
			$checked = ( $option != '' && $option == $value ) ? ' checked="checked"' : '';
			echo "<label><input " . $checked . " value='" . esc_attr( $value ) . "' name='" . esc_attr( $field ) ."' type='radio' />" . esc_html( $title ) . "</label><br />";
		}
	}

	function setting_text_fn( $key, $params = null ) {
		$option = self::param( $key );
		$field = $this->option_name . $this->param_name( $key );
		echo "<input id='" . esc_attr( $key ) . "' name='" . esc_attr( $field ) . "' size='40' type='text' value='" . esc_attr( $option ) . "' />";
	}

	function setting_check_fn( $key, $params = null ) {
		$option = self::param( $key );
		$field = $this->option_name . $this->param_name( $key );

		echo "<input " . ( $option ? ' checked="checked" ' : '' ) . " id='" . esc_attr( $key ) . "' value='true' name='" . esc_attr( $field ) . "' type='checkbox' />";
	}

	function setting_textarea_fn( $key, $params = null ) {
		$option = self::param( $key );
		$field = $this->option_name . $this->param_name( $key );
		echo "<textarea id='" . esc_attr( $key ) . "' name='" . esc_attr( $field ) . "' rows='7' cols='50' type='textarea'>" . esc_html( $option ) . "</textarea>";
	}

	function setting_password_fn( $key, $params = null ) {
		$option = self::param( $key );
		$field = $this->option_name . $this->param_name( $key );
		echo "<input id='" . esc_attr( $key ) . "' name='" . esc_attr( $field ) . "' size='40' type='password' value='" . esc_attr( $option ) . "' />";
	}

	function setting_random_fn( $key, $params = null ) {
		$option = self::param( $key );
		$field = $this->option_name . $this->param_name( $key . '_reset' );
		echo "<input id='" . esc_attr( $key ) . "' size='40' type='text' readonly value='" . esc_attr( $option ) . "' />";
		echo "&nbsp;<input id='" . esc_attr( $key ) . "_reset' value='true' name='" . esc_attr( $field ) . "' type='checkbox' /> " . __( 'Reset API KEY', 'simple_payment' );
	}
	
	public function transactions() {
		global $wpdb, $list;
		$current_screen = get_current_screen();
		add_screen_option( 'per_page', [
			'default' => 20,
			'option' => 'sp_per_page'
		] );
		require( SPWP_PLUGIN_DIR.'/admin/transaction-list-table.php' );
		$list =  new Transaction_List(); // Consider using different List when using ACP - did_action( 'acp/ready' );
	}


	public function info() {
		global $wpdb, $list;
		add_screen_option( 'per_page', [
			'default' => 20,
			'option' => 'sp_per_page'
		] );
		if ( !isset( $_REQUEST[ 'id' ] ) && !( isset( $_REQUEST[ 'transaction_id' ] ) && isset( $_REQUEST[ 'engine' ] ) ) ) throw new Exception( __( 'Error fetching transaction' ), 500 );
		$id = isset( $_REQUEST[ 'transaction_id' ] ) && $_REQUEST[ 'transaction_id' ] ? sanitize_text_field( $_REQUEST[ 'transaction_id' ] ) : absint( $_REQUEST[ 'id' ] );
		//$engine = sanitize_text_field($_REQUEST['engine']);

		require( SPWP_PLUGIN_DIR . '/admin/transaction-list-table.php' );
		$list = new Transaction_List( true );
	}

	public static function get_transactions( $args = [], $per_page = 5, $page_number = 1, $instance = null, $count = false) {
		global $wpdb, $SPWP;
		if ( $instance && !self::$details ) {
			$orderby = $instance->get_pagination_arg( 'orderby' );
			$order = $instance->get_pagination_arg( 'order' );
		} else {
			$orderby = 'id';
			$order = 'DESC';
		}
		if ( $count ) $sql = "SELECT COUNT(*) FROM " . $wpdb->prefix . $SPWP::$table_name;
		else $sql = "SELECT * FROM " . $wpdb->prefix . $SPWP::$table_name;
		$where = [];
		if ( ! empty( $args[ 'id' ] ) && empty( $args['action'] ) ) $where[] = "`payment_id` = " .esc_sql(absint($args['id']));
		if ( ! empty( $args[ 'transaction_id' ] ) && isset($args['engine']) && $args['engine'] ) $where[] = "`transaction_id` =  '" .esc_sql($_REQUEST['transaction_id'])."'";

		if ( ! empty( $args[ 'status' ] ) ) $where[] = "`status` =  '" .esc_sql($args['status'])."'";
		if ( ! empty( $args[ 'user_id' ] ) ) $where[] = "`user_id` =  " .esc_sql($args['user_id']);

		//if (!self::$details) {
		//  $where[] = "`archived` = ".(!empty($args['archive']) ? '1' : 0);
		if ( ! empty( $args['engine'] ) ) $where[] = "`engine` =  '" .esc_sql($args['engine'])."'";
		//}

		if ( ! empty( $args['s'] ) ) {
			$where[] = "`transaction_id` LIKE '%" .esc_sql($args['s'])."%' OR `concept` LIKE '%" .esc_sql($args['s'])."%'";
		}

		if (count($where) > 0) $sql .=  ' WHERE '.implode(' AND ', $where);
		if ($count) {
			return($wpdb->get_var($sql));
		}
		if ( ! empty( $args['orderby'] ) || isset($orderby) ) {
			$sql .= ' ORDER BY ' . (isset($args['orderby']) && ! empty($args['orderby']) ? esc_sql ($args['orderby']) : $orderby) ;
			$sql .= isset($args['order']) && !empty($args['order']) ? ' '.esc_sql($args['order']) : ' '.$order;
		}
		if ( $per_page ) {
			$sql .= " LIMIT $per_page";
			$sql .= ' OFFSET ' . ( ($page_number ? : 1) - 1 ) * $per_page;
		}
		$result = $wpdb->get_results( $sql , 'ARRAY_A' );
		return( $result );
	}

	public function render_transactions() {
		global $list;
		require( SPWP_PLUGIN_DIR . '/admin/transactions.php' );
	}

	public function render_transaction_log() {
		global $list;
		require( SPWP_PLUGIN_DIR . '/admin/transaction-log.php' );
	}
	
	// Sanitize input from our plugin's option form and validate the provided key.
	public function license_key_callback( $options ) {
		global $SPWP;
		if ( !isset( $options[ 'key' ] ) ) return( $SPWP::$license );
		if ( isset( $options[ 'key' ] ) && !$options[ 'key' ] ) {
			add_settings_error( 'sp_license', esc_attr( 'settings_updated' ), __( 'License key is required', 'simple-payment' ), 'error' );
			return;
		}
		// Detect multiple sanitizing passes.
		// Workaround for: https://core.trac.wordpress.org/ticket/21989
		static $cache = null;
		//$cache = null;
		if ( $cache !== null ) return $cache;

		// Get the current domain. This example validates keys against a node-locked
		// license policy, allowing us to lock our plugin to a specific domain.
		$domain = parse_url( get_bloginfo( 'url' ), PHP_URL_HOST );

		// Validate the license key within the scope of the current domain.
		$key = sanitize_text_field( $options[ 'key' ] );
		try {
			$license = $SPWP->validate_plugin_license( $key );
			delete_transient( 'sp_license_issue' );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			$code = $e->getCode();
			switch ( $message ) {
					// When the license has been activated, but the current domain is not
					// associated with it, return an error.
				case 'FINGERPRINT_SCOPE_MISMATCH': {
					add_settings_error( 'sp_license', esc_attr( 'settings_updated' ), __( 'License is not valid on the current domain', 'simple-payment' ), 'error' );
					break;
				}
					// When the license has not been activated yet, return an error. This
					// shouldn't happen, since we should be activating the customer's domain
					// upon purchase - around the time we create their license.
				case 'NO_MACHINES':
				case 'NO_MACHINE': {
					add_settings_error( 'sp_license', esc_attr( 'settings_updated' ), __('License has not been activated', 'simple-payment' ), 'error' );
					break;
				}
					// When the license key does not exist, return an error.
				case 'NOT_FOUND': {
					add_settings_error( 'sp_license', esc_attr( 'settings_updated' ), __( 'License key was not found', 'simple-payment' ), 'error' );
					break;
				}
				default: {
					add_settings_error( 'sp_license', esc_attr( 'settings_updated' ), __( 'Unhandled error:', 'simple-payment' ) . " {$message} ({$code})", 'error' );
					break;
				}
					// Clear any options that were previously stored in the database.
			}
			return( [] );
		}

		// Save result to local cache.
		$cache = [
			'policy' => $license['data']['relationships']['policy']['data']['id'],
			'key' => $license['data']['attributes']['key'],
			'expiry' => $license['data']['attributes']['expiry'],
			'valid' => $license['meta']['valid'],
			'status' => $license['meta'][ 'detail' ],
			'domain' => $domain,
			'meta' => []
		];
		foreach ( $license[ 'data' ][ 'attributes' ][ 'metadata' ] as $key => $value ) {
			$cache[ 'meta' ][ $key ] = $value;
		}
		return( $cache );
	}
	
}