<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$_active_plugins = array_merge( is_multisite() ? array_keys( get_site_option( 'active_sitewide_plugins', [] ) ) : [], get_option( 'active_plugins', [] ) );

if ( !in_array( 'gravityforms/gravityforms.php', $_active_plugins ) ) return; 


// If Gravity Forms is loaded, bootstrap the Simple Payment Add-On.
add_action( 'gform_loaded', [ 'GF_SimplePayment_Bootstrap', 'load' ], 5 );

add_action( 'wp', [ 'GFSimplePayment', 'maybe_repayment_page' ], 20 );
add_action( 'wp', [ 'GFSimplePayment', 'maybe_thankyou_page' ], 50 );


class GF_SimplePayment_Bootstrap {

	/**
	 * If the Payment Add-On Framework exists, Simple Payment Add-On is loaded.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses GFAddOn::register()
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}
		GF_Fields::register( new GF_Field_Card_Owner_ID() );
		GFAddOn::register( 'GFSimplePayment' );
	}

}

GFForms::include_payment_addon_framework();

class GFSimplePayment extends GFPaymentAddOn {
	protected $_version;
	protected $_min_gravityforms_version = '1.9.16';
	protected $_slug = 'simple-payment';
	protected $_path = 'simple-payment/addons/gravityforms/init.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://simple-payment.yalla-ya.com';
	protected $_title = 'Gravity Forms Simple Payment Add-On';
	protected $_short_title = 'Simple Payment';
	protected $_supports_callbacks = true;
    protected $_requires_credit_card = true;
	protected $_enable_rg_autoupgrade = true;
    protected $redirect_url;
    
    protected $SPWP;

	public static $params;
	/**
	 * Members plugin integration
	 *
	 * @access protected
	 * @var    array
	 */
	protected $_capabilities = array(
		'gravityforms_simplepayment',
		'gravityforms_simplepayment_uninstall',
		'gravityforms_simplepayment_plugin_page',
	);

	/**
	 * Permissions
	 */
	protected $_capabilities_settings_page = 'gravityforms_simplepayment';
	protected $_capabilities_form_settings = 'gravityforms_simplepayment';
    protected $_capabilities_uninstall = 'gravityforms_simplepayment_uninstall';
	protected $_capabilities_plugin_page = 'gravityforms_simplepayment_plugin_page';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFSimplePayment
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFSimplePayment();
        }
        return self::$_instance;
	}

    function pre_init() {
		parent::pre_init();
		add_action( 'sp_payment_success', [ $this, 'payment_success' ] );
		add_action( 'sp_payment_status', function( $params, $engine ) {
			if ( $params[ 'confirmation_code' ] ) $this->payment_success( $params );
		}, 50, 2 );

		add_action( 'sp_payment_verify', function( $params, $engine ) {
			if ( $params[ 'confirmation_code' ] ) $this->payment_success( $params );
		}, 50, 2 );

		//add_action( 'sp_payment_post_process', [ $this, 'payment_success' ] );
		add_action( 'gform_enqueue_scripts', [ $this, 'load_scripts'], 10, 2 );
		add_filter( 'sp_payment_pre_process_filter', [ $this, 'sp_payment_pre_process_filter' ] );
        $this->SPWP = SimplePaymentPlugin::instance();
		$this->_version = $this->SPWP::$version;
    }
	
	function get_post_payment_actions_config( $feed_slug ) {
		return( [
			'position' => 'before',
			'setting'  => 'conditionalLogic',
		] );
	}

	function load_scripts( $form, $is_ajax ) {
		if ( $is_ajax ) {
			$this->SPWP->scripts();
		}
	}
	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	// ------- Plugin settings -------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		$description = '<p style="text-align: left;">' . sprintf( esc_html__( 'Simple Payment is a merchant account and gateway in one. Use Gravity Forms to collect payment information and automatically integrate to your Simple Payment account. If you don\'t have a Simple Payment account, you can %ssign up for one here.%s', 'simple-payment' ), '<a href="https://registration.paypal.com/welcomePage.do?bundleCode=C3&country=US&partner=PayPal" target="_blank">', '</a>' ) . '</p>';

		$engines = [];
		$engines[] = ['label' => 'Default', 'value' => ''];
		foreach (SimplePaymentPlugin::$engines as $engine) $engines[] = ['label' => $engine, 'value' => $engine];

		return array(
			array(
				'description' => $description,
				'fields' => array(
				array(
				'name'    		=> 'engine',
				'label'   		=> esc_html__( 'Engine', 'simple-payment' ),
				'type'    		=> 'select',
				'tooltip' 		=> '<h6>' . esc_html__( 'Select Payment Gateway', 'simple-payment' ) . '</h6>' . esc_html__( 'If none selected it will use Simple Payment default', 'simple-payment' ),
				'choices'       => $engines,
				'horizontal'    => true,
			),
			array(
				'name'    		=> 'display',
				'label'   		=> esc_html__( 'Display Method', 'simple-payment' ),
				'type'    		=> 'select',
				'tooltip' 		=> '<h6>' . esc_html__( 'Display Method', 'simple-payment' ) . '</h6>' . esc_html__( 'If none selected it will use Simple Payment default.', 'simple-payment' ),
				'choices'       => array(
					array(
						'label' 	=> esc_html__( 'Default', 'simple-payment' ),
						'value' 	=> '',
					),
					array(
						'label'    	=> esc_html__( 'IFRAME', 'simple-payment' ),
						'value'    	=> 'iframe',
					),
					array(
						'label'    	=> esc_html__( 'Modal', 'simple-payment' ),
						'value'    	=> 'modal',
					),
					array(
						'label'    	=> esc_html__( 'redirect', 'simple-payment' ),
						'value'    	=> 'redirect',
					),
				),
				'horizontal'    => true,
			),
			array(
				'name'     => 'installments',
				'label'    => esc_html__( 'Installments', 'simple-payment' ),
				'type'     => 'checkbox',
				'tooltip' 		=> '<h6>' . esc_html__( 'Enable Insallments', 'simple-payment' ) . '</h6>' . esc_html__( 'Enable installments on checkout page.', 'simple-payment' ),
				'choices' 	=> array(
					array(
						'label' => 'Enable',
						'name'	=> 'installments',
					),
				)
			),
			array(
				'name'     => 'template',
				'label'    => esc_html__( 'Template', 'simple-payment' ),
				'type'     => 'text',
				'class'    => 'medium',
				'tooltip' 		=> '<h6>' . esc_html__( 'Custom checkout template form', 'simple-payment' ) . '</h6>' . esc_html__( 'If you wish to use a custom form template.', 'simple-payment' ),
			),
			array(
				'name'     => 'settings',
				'label'    => esc_html__( 'Settings', 'simple-payment' ),
				'type'     => 'json',
				'class'    => 'medium',
				'tooltip' 		=> '<h6>' . esc_html__( 'Custom & advanced checkout settings', 'simple-payment' ) . '</h6>' . esc_html__( 'Use if carefully', 'simple-payment' ),
			)
			)
		)
		);
	}

	//-------- Form Settings ---------

	/**
	 * Prevent feeds being listed or created if the api keys aren't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {
        return true; 
	}
	
	public function can_duplicate_feed( $feed ) {
		return( true );
	}
	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @return array The feed settings.
	 */
	public function feed_settings_fields() {
		require_once( 'class-json.php' );
		$default_settings = parent::feed_settings_fields();

        // Remove default options before adding custom.
		$default_settings = parent::remove_field( 'options', $default_settings );
		$default_settings = parent::remove_field( 'billingCycle', $default_settings );
		$default_settings = parent::remove_field( 'trial', $default_settings );

		// Add pay period if subscription.
		if ( $this->get_setting( 'transactionType' ) == 'subscription' ) {
			$pay_period_field = array(
				'name'     => 'payPeriod',
				'label'    => esc_html__( 'Pay Period', 'simple-payment' ),
				'type'     => 'select',
				'choices' => array(
								array( 'label' => esc_html__( 'Weekly', 'simple-payment' ), 'value' => 'weekly' ),
								array( 'label' => esc_html__( 'Every Two Weeks', 'simple-payment' ), 'value' => 'biweekly' ),
								array( 'label' => esc_html__( 'Twice Every Month', 'simple-payment' ), 'value' => 'twicemonth' ),
								array( 'label' => esc_html__( 'Every Four Weeks', 'simple-payment' ), 'value' => '4weeks' ),
								array( 'label' => esc_html__( 'Monthly', 'simple-payment' ), 'value' => 'monthly' ),
								array( 'label' => esc_html__( 'Quarterly', 'simple-payment' ), 'value' => 'quarterly' ),
								array( 'label' => esc_html__( 'Twice Every Year', 'simple-payment' ), 'value' => 'semesterly' ),
								array( 'label' => esc_html__( 'Yearly', 'simple-payment' ), 'value' => 'yearly' ),
							),
				'tooltip'  => '<h6>' . esc_html__( 'Pay Period', 'simple-payment' ) . '</h6>' . esc_html__( 'Select pay period.  This determines how often the recurring payment should occur.', 'simple-payment' ),
			);
			$default_settings = $this->add_field_after( 'recurringAmount', $pay_period_field, $default_settings );

			// Add post fields if form has a post.
			$form = $this->get_current_form();

			if ( GFCommon::has_post_field( $form['fields'] ) ) {
				$post_settings = array(
						'name'    => 'post_checkboxes',
						'label'   => esc_html__( 'Posts', 'simple-payment' ),
						'type'    => 'checkbox',
						'tooltip' => '<h6>' . esc_html__( 'Posts', 'simple-payment' ) . '</h6>' . esc_html__( 'Enable this option if you would like to change the post status when a subscription is cancelled.', 'simple-payment' ),
						'choices' => array(
								array(
										'label'    => esc_html__( 'Update Post when subscription is cancelled.', 'simple-payment' ),
										'name'     => 'change_post_status',
										'onChange' => 'var action = this.checked ? "draft" : ""; jQuery("#update_post_action").val(action);',
								),
						),
				);
				$default_settings = $this->add_field_after( 'billingInformation', $post_settings, $default_settings );
			}
		}
		$engines = [];
		$engines[] = [ 'label' => 'Default', 'value' => '' ];
		foreach ( SimplePaymentPlugin::$engines as $engine ) $engines[] = [ 'label' => $engine, 'value' => $engine ];
		$fields = array(
			array(
				'name'      => 'customSettingsEnabled',
				'label'     => esc_html__( 'Custom Settings', 'simple-payment' ),
				'type'      => 'checkbox',
				'tooltip' 	=> '<h6>' . esc_html__( 'Custom Settings', 'simple-payment' ) . '</h6>' . esc_html__( 'Override the settings provided on the Simple Payment Settings page and use these instead for this feed.', 'simple-payment' ),
				'onchange' => " if ( jQuery( this ).prop( 'checked' ) ){
										jQuery('#gform_setting_engine').show();
										jQuery('#gform_setting_display').show();
										jQuery('#gform_setting_installments').show();
										jQuery('#gform_setting_multiline').show();
										jQuery('#gform_setting_template').show();
										jQuery('#gform_setting_settings').show();
										jQuery('#gaddon-setting-row-engine').show();
										jQuery('#gaddon-setting-row-display').show();
										jQuery('#gaddon-setting-row-installments').show();
										jQuery('#gaddon-setting-row-template').show();
										jQuery('#gaddon-setting-row-settings').show();
									} else {
										jQuery('#gform_setting_engine').hide();
										jQuery('#gform_setting_display').hide();
										jQuery('#gform_setting_installments').hide();
										jQuery('#gform_setting_multiline').hide();
										jQuery('#gform_setting_template').hide();
										jQuery('#gform_setting_settings').hide();
										jQuery('#gaddon-setting-row-engine').hide();
										jQuery('#gaddon-setting-row-display').hide();
										jQuery('#gaddon-setting-row-installments').hide();
										jQuery('#gaddon-setting-row-template').hide();
										jQuery('#gaddon-setting-row-settings').hide();
										jQuery('#engine').val('');
										jQuery('#display').val('');
										jQuery('#installments').val('');
										jQuery('#template').val('');
										jQuery('#settings').val('');
										jQuery('i').removeClass('icon-check fa-check gf_valid');
									}",
				'choices' 	=> array(
					array(
						'label' => 'Override Default Settings',
						'name'	=> 'customSettingsEnabled',
					),
				)
			),
			array(
				'name'    		=> 'engine',
				'label'   		=> esc_html__( 'Engine', 'simple-payment' ),
				'type'    		=> 'select',
				'hidden'  		=> ! $this->get_setting( 'customSettingsEnabled' ),
				'tooltip' 		=> '<h6>' . esc_html__( 'Select Payment Gateway', 'simple-payment' ) . '</h6>' . esc_html__( 'If none selected it will use Simple Payment default', 'simple-payment' ),
				'choices'       => $engines,
				'horizontal'    => true,
			),
			array(
				'name'    		=> 'display',
				'label'   		=> esc_html__( 'Display Method', 'simple-payment' ),
				'type'    		=> 'select',
				'hidden'  		=> ! $this->get_setting( 'customSettingsEnabled' ),
				'tooltip' 		=> '<h6>' . esc_html__( 'Display Method', 'simple-payment' ) . '</h6>' . esc_html__( 'If none selected it will use Simple Payment default.', 'simple-payment' ),
				'choices'       => array(
					array(
						'label' 	=> esc_html__( 'Default', 'simple-payment' ),
						'value' 	=> '',
					),
					array(
						'label'    	=> esc_html__( 'IFRAME', 'simple-payment' ),
						'value'    	=> 'iframe',
					),
					array(
						'label'    	=> esc_html__( 'Modal', 'simple-payment' ),
						'value'    	=> 'modal',
					),
					array(
						'label'    	=> esc_html__( 'redirect', 'simple-payment' ),
						'value'    	=> 'redirect',
					),
				),
				'horizontal'    => true,
			),
			array(
				'name'     => 'installments',
				'label'    => esc_html__( 'Installments', 'simple-payment' ),
				'type'     => 'checkbox',
				'hidden'  		=> ! $this->get_setting( 'customSettingsEnabled' ),
				'tooltip' 		=> '<h6>' . esc_html__( 'Enable Insallments', 'simple-payment' ) . '</h6>' . esc_html__( 'Enable installments on checkout page.', 'simple-payment' ),
				'choices' 	=> array(
					array(
						'label' => 'Enable',
						'name'	=> 'installments',
					),
				)
			),
			array(
				'name'     => 'multiline',
				'label'    => esc_html__( 'Multi-line Documents', 'simple-payment' ),
				'type'     => 'checkbox',
				'class'    => 'medium',
				'hidden'  		=> ! $this->get_setting( 'customSettingsEnabled' ),
				'tooltip' 		=> '<h6>' . esc_html__( 'Where possible issue receipt with products details', 'simple-payment' ) . '</h6>' . esc_html__( 'When receipt details is requried.', 'simple-payment' ),
				'choices' 	=> array(
					array(
						'label' => 'Enable',
						'name'	=> 'multiline',
					),
				)
			),
			array(
				'name'     => 'template',
				'label'    => esc_html__( 'Template', 'simple-payment' ),
				'type'     => 'text',
				'class'    => 'medium',
				'hidden'  		=> ! $this->get_setting( 'customSettingsEnabled' ),
				'tooltip' 		=> '<h6>' . esc_html__( 'Custom checkout template form', 'simple-payment' ) . '</h6>' . esc_html__( 'If you wish to use a custom form template.', 'simple-payment' ),
			),
			array(
				'name'     => 'settings',
				'label'    => esc_html__( 'Settings', 'simple-payment' ),
				'type'     => 'json',
				'class'    => 'medium',
				'hidden'  		=> ! $this->get_setting( 'customSettingsEnabled' ),
				'tooltip' 		=> '<h6>' . esc_html__( 'Custom & advanced checkout settings', 'simple-payment' ) . '</h6>' . esc_html__( 'Use if carefully', 'simple-payment' ),
			),
		);
		$default_settings = $this->add_field_after( 'conditionalLogic', $fields, $default_settings );
		return $default_settings;
	}

	/**
	 * Returns the markup for the change post status checkbox item.
	 *
	 * @param array  $choice     The choice properties.
	 * @param string $attributes The attributes for the input tag.
	 * @param string $value      Currently selection (1 if field has been checked. 0 or null otherwise).
	 * @param string $tooltip    The tooltip for this checkbox item.
	 *
	 * @return string
	 */
	public function checkbox_input_change_post_status( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );
		$dropdown_field = array(
			'name'     => 'update_post_action',
			'choices'  => array(
				array( 'label' => '' ),
				array( 'label' => esc_html__( 'Mark Post as Draft', 'simple-payment' ), 'value' => 'draft' ),
				array( 'label' => esc_html__( 'Delete Post', 'simple-payment' ), 'value' => 'delete' ),

			),
			'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
		);
		$markup .= '&nbsp;&nbsp;' . $this->settings_select( $dropdown_field, false );

		return $markup;
	}

	/**
	 * Prepend the name fields to the default billing_info_fields added by the framework.
	 *
	 * @return array
	 */
	public function billing_info_fields() {
		$fields = array(
				array(
						'name'     => 'lastName',
						'label'    => esc_html__( 'Last Name', 'simple-payment' ),
						'required' => false,
				),
				array(
						'name'     => 'firstName',
						'label'    => esc_html__( 'First Name', 'simple-payment' ),
						'required' => false,
				),
				array(
					'name'     => 'phone',
					'label'    => esc_html__( 'Phone', 'simple-payment' ),
					'required' => false,
				),
				array(
					'name'     => 'company',
					'label'    => esc_html__( 'Company', 'simple-payment' ),
					'required' => false,
				),
				array(
					'name'     => 'product_code',
					'label'    => esc_html__( 'Product Code', 'simple-payment' ),
					'required' => false,
				),
		);
		return array_merge( $fields, parent::billing_info_fields() );
	}

	public static function maybe_repayment_page() {
		$instance = self::get_instance();
		if ( ! $instance->is_gravityforms_supported() ) {
			return;
		}
		$retry = $str = rgget( 'gf_simplepayment_retry' );
		if (!$retry) return;
		
		// TODO: validate payment status before continuing
		// GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );
		if ( $str = rgget( 'gf_simplepayment_return' ) ) {
			$str = base64_decode( $str );
			parse_str( $str, $query );
			if ( wp_hash( 'ids=' . $query['ids'] ) == $query[ 'hash' ] ) {
				list( $form_id, $entry_id ) = explode( '|', $query[ 'ids' ] );
				$form = GFAPI::get_form( $form_id );
				$entry = GFAPI::get_entry( $entry_id );
				$feed = $instance->get_feed( $retry );
				$submission_data = $instance->get_submission_data( $feed, $form, $entry );

				$is_subscription = $feed[ 'meta' ][ 'transactionType' ] == 'subscription';
				//if ( ! $is_subscription ) {
					//Running an authorization only transaction if function is implemented and this is a single payment
					//$authorization = $instance->authorize( $feed, $submission_data, $form, $entry );
				//}
				// TODO: handle subscriptions
				//if ( $authorization ) {
					//$instance->log_debug( __METHOD__ . "(): Authorization result for form #{$form['id']} submission => " . print_r( $this->authorization, true ) );
				//} else {
					$instance->redirect_url( $feed, $submission_data, $form, $entry );
				//}
				
				// TODO: check if feed id , is not paid? or let it charge for it anyway
				// 
				//$instance->entry_post_save( $lead, $form, $feed);
				//
				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once( GFCommon::get_base_path() . '/form_display.php' );
				}
			}
		}
	}
	
	public static function maybe_thankyou_page() {
		global $SPWP;
		$instance = self::get_instance();
		if ( ! $instance->is_gravityforms_supported() ) {
			return;
		}
		if ( $str = rgget( 'gf_simplepayment_return' ) ) {
			$str = base64_decode( $str );
			parse_str( $str, $query );
			if ( wp_hash( 'ids=' . $query['ids'] ) == $query['hash'] ) {
				list( $form_id, $lead_id ) = explode( '|', $query['ids'] );
				$form = GFAPI::get_form( $form_id );
				$lead = GFAPI::get_entry( $lead_id );
				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once( GFCommon::get_base_path() . '/form_display.php' );
				}
				$confirmation = GFFormDisplay::handle_confirmation( $form, $lead, false );
				if ( is_array( $confirmation ) && isset( $confirmation[ 'redirect' ] ) ) {
					$url = $confirmation[ 'redirect' ];
					$target = parse_url( $url, PHP_URL_QUERY );
					parse_str( $target, $target );
					$target = isset( $target[ 'target' ] ) ? $target[ 'target' ] : '';
					$SPWP::redirect( $url, $target );
					/*

					$targets = explode(':', $target);
					$target = $targets[0];
					switch ( $target ) {
						case '_top':
						  echo '<html><head><script type="text/javascript"> top.location.replace("'.$url.'"); </script></head><body></body</html>'; 
						  break;
						case '_parent':
						  echo '<html><head><script type="text/javascript"> parent.location.replace("'.$url.'"); </script></head><body></body</html>'; 
						  break;
						case 'javascript':
						  $script = $targets[1];
						  echo '<html><head><script type="text/javascript"> '.$script.' </script></head><body></body</html>'; 
						  break;
						case '_blank':
						  break;
						case '_self':
						default:
							echo '<html><head><script type="text/javascript"> location.replace("'.$url.'"); </script></head><body></body</html>'; 
							wp_redirect( $url );
					}*/
					die();
				}
				GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead );
			}
		}
    }
    
	// -------- Entry Detail ---------

	public function return_url( $form_id, $lead_id ) {
		// Lets use wordpress built-in function site_url
		/*$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';
		$server_port = apply_filters( 'gform_simplepayment_return_url_port', $_SERVER['SERVER_PORT'] );
		if ( $server_port != '80' && $server_port != 443) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}*/
		$pageURL = site_url( $_SERVER[ 'REQUEST_URI' ] );
		$ids_query = "ids={$form_id}|{$lead_id}";
		$ids_query .= '&hash=' . wp_hash( $ids_query );
		$url = remove_query_arg( 'gf_simplepayment_retry', $pageURL );
		$url = add_query_arg( 'gf_simplepayment_return', base64_encode( $ids_query ), $url );
		/**
		 * Filters SimplePayment's return URL, which is the URL that users will be sent to after completing the payment on SimplePayment's site.
		 * Useful when URL isn't created correctly (could happen on some server configurations using PROXY servers).
		 *
		 * @since 2.4.5
		 *
		 * @param string  $url 	The URL to be filtered.
		 * @param int $form_id	The ID of the form being submitted.
		 * @param int $entry_id	The ID of the entry that was just created.
		 * @param string $query	The query string portion of the URL.
		 */
		return( apply_filters( 'gform_simplepayment_return_url', $url, $form_id, $lead_id ) );
	}

	/**
	 * Handle cancelling the subscription from the entry detail page.
	 *
	 * @param array $entry The entry object currently being processed.
	 * @param array $feed  The feed object currently being processed.
	 *
	 * @return bool
	 */
	public function cancel( $entry, $feed ) {
		// $entry['transaction_id']
		//$settings = $this->get_settings( $feed );
		//$response = $this->post_to_payflow( $args, $settings, $entry['form_id'] );
		//if ( ! empty( $response ) && $response['RESULT'] == '0' ) {
		//	return true;
		//}
		return false;
	}

	/**
	 * Check if the current entry was processed by this add-on.
	 *
	 * @param int $entry_id The ID of the current Entry.
	 *
	 * @return bool
	 */
	public function is_payment_gateway( $entry_id ) {
		if ( $this->is_payment_gateway ) {
			return( true );
		}
		$gateway = gform_get_meta( $entry_id, 'payment_gateway' );
		return( in_array( $gateway, array( 'simplepayment', $this->_slug ) ) );
	}

	public function is_callback_valid() {
		return( rgget( 'page' ) == 'gf_simplepayment_ipn' );
	}

	public function sp_payment_pre_process_filter( $params ) {
		global $gf_sp_payment;
		if ( !( $gf_sp_payment
			|| ( isset( $params[ 'source' ]) 
			&& $params[ 'source' ] == 'gravityforms'
			&& isset( $params[ 'source_id' ] )
			&& $params[ 'source_id' ] ) ) ) return( $params );

		if ( isset( $gf_sp_payment ) && $gf_sp_payment ) {
			$submission_data = $gf_sp_payment[ 'submission_data' ];
			$feed = $gf_sp_payment[ 'feed' ];
		} else {
			$entry_id = $params[ 'source_id' ];
			$entry = GFAPI::get_entry( $entry_id );
			$form_id = $entry[ 'form_id' ];
			$form = GFAPI::get_form( $form_id );			
			$feed = $this->get_payment_feed( $entry, $form );
			$submission_data = $this->get_order_data( $feed, $form, $entry );
		}
		if ( rgar( $feed[ 'meta' ], 'multiline' ) ) {
			unset( $params[ 'products' ] );
			foreach ( $submission_data[ 'line_items' ] as $item ) {
				$product = [
					'id' => $item[ 'id' ],
					'name' => $item[ 'name' ],
					'description' => $item[ 'description' ],
					'amount' => $item[ 'unit_price' ],
					'qty' => $item[ 'quantity' ]
				];
				if ( !isset( $params[ 'products' ] ) ) $params[ 'products' ] = [];
				$params[ 'products' ][] = $product;
			}
		}
		if ( $feed[ 'meta' ][ 'installments' ] ) {
			$params[ 'payments' ] = true;
		}
		return( $params );
	}

	public function add_sp_pre_process( $feed, $submission_data, $form, $entry ) {
		global $gf_sp_payment;
		$gf_sp_payment = [
			'feed' => $feed,
			'submission_data' => $submission_data,
			'form' => $form,
			'entry' => $entry
		];
	}
	// # SUBMISSION ----------------------------------------------------------------------------------------------------
	
	public function redirect_url( $feed, $submission_data, $form, $entry ) {
		//Don't process redirect url if request is a SimplePayment return
		if ( !rgempty( 'gf_simplepayment_return', $_GET ) && rgempty( 'gf_simplepayment_retry', $_GET ) ) {
			return false;
		}
		$settings = $this->get_settings( $feed );
		$params = $settings ? $settings : [];
		if (isset($params['settings']) && $params['settings']) $params = array_merge($params, $params['settings']);
		$params = array_merge($params, $this->prepare_credit_card_transaction( $feed, $submission_data, $form, $entry ));
		$engine = isset( $params[ 'engine' ] ) ? $params[ 'engine' ] : null; 
		if ( !rgempty( 'gf_simplepayment_retry', $_GET ) ) {
			$params[ 'callback' ] = $entry[ 'source_url' ];
		}
		$params[ 'redirect_url' ] = $this->return_url( $form[ 'id' ], $entry[ 'id' ] ).( isset( $params[ 'target' ] ) && $params[ 'target' ] ? '&target=' . $params[ 'target' ] : '' );

		$this->add_sp_pre_process( $feed, $submission_data, $form, $entry );
		$params = apply_filters( 'gform_simplepayment_args_before_payment', $params, $form['id'], $submission_data, $feed, $entry );
		GFAPI::update_entry_property( $entry[ 'id' ], 'payment_method', 'SimplePayment' );
		GFAPI::update_entry_property( $entry[ 'id' ], 'payment_status', 'Processing' );
		if ( !isset( $params[ 'display' ] ) || !in_array( $params[ 'display' ], [ 'iframe', 'modal' ] ) || !SimplePaymentPlugin::supports( $params[ 'display' ], $engine ) ) {
			try {
				$this->redirect_url = $this->SPWP->payment( $params, $engine );
				GFAPI::update_entry_property( $entry[ 'id' ], 'transaction_id', $this->SPWP->engine->transaction );
			} catch ( Exception $e ) {
				$action = array(
					'transaction_id'   => $this->SPWP->engine->transaction,
					'amount'         => $params[ $this->SPWP::AMOUNT ],
					'error_message'  => $e->getMessage(),
				);
				$this->fail_payment( $entry, $action );
			}
		}
		if ( in_array( $params[ 'display' ], [ 'iframe', 'modal' ] ) ) {
			if ( isset( $params[ 'template' ] ) && $params[ 'template' ] ) {
				$params[ 'type' ] = 'template';
			} else if ( !isset( $params[ 'form' ] ) || !$params[ 'form' ] ) {
				$params[ 'type' ] = 'form';
				$params[ 'form' ] = isset( $_REQUEST[ 'gform_ajax' ] ) && $_REQUEST[ 'gform_ajax' ] ? 'plugin-addon-ajax' : 'plugin-addon';
			}
			//$params['redirect_url'] = add_query_arg('target', '_parent', $params['redirect_url']);
			GFSimplePayment::$params = $params;
			$this->redirect_url = null;
			add_filter( 'gform_confirmation', function ( $confirmation, $form, $entry, $ajax ) {
				if ( isset( $confirmation[ 'redirect' ] ) ) {
					$url = esc_url_raw( $confirmation[ 'redirect' ] );
					GFCommon::log_debug( __METHOD__ . '(): Redirect to URL: ' . $url );
					//$confirmation = "<script type=\"text/javascript\">window.open('$url', '_top');</script>";
					//return($confirmation);
				} 
				// TODO: check if the previous was redirect from javascript to also disable the previous;
				// otherwise preset html/text from confirmation
				$confirmation = $this->SPWP->checkout( GFSimplePayment::$params );
				remove_all_filters( 'gform_confirmation' );
				return( $confirmation );
			}, 10, 4 );
		}
		return( SimplePaymentPlugin::sanitize_redirect( $this->redirect_url ) );
	}

	/**
	 * Authorize and capture the transaction for the product & services type feed.
	 *
	 * @param array $feed            The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form            The form object currently being processed.
	 * @param array $entry           The entry object currently being processed.
	 *
 	 * @return array {
	 *     Return an $authorization array.
	 *
	 *     @type bool   $is_authorized  True if the payment is authorized. Otherwise, false.
	 *     @type string $error_message  The error message, if present.
	 *     @type string $transaction_id The transaction ID.
	 *     @type array  $captured_payment {
	 *         If payment is captured, an additional array is created.
	 *
	 *         @type bool   $is_success     If the payment capture is successful.
	 *         @type string $error_message  The error message, if any.
	 *         @type string $transaction_id The transaction ID of the captured payment.
	 *         @type int    $amount         The amount of the captured payment, if successful.
	 *     }
	 */
	public function authorize( $feed, $submission_data, $form, $entry ) {
		$settings = $this->get_settings( $feed );
		
		$params = $settings ? $settings : [];
		if ( isset( $params[ 'settings' ] ) && $params[ 'settings' ] ) $params = array_merge( $params, $params[ 'settings' ] );
		$params = array_merge( $params, $this->prepare_credit_card_transaction( $feed, $submission_data, $form, $entry ) );
		
		$engine = isset( $params[ 'engine' ] ) ? $params[ 'engine' ] : null; 

		// get engine from params
		if ( !SimplePaymentPlugin::supports( 'cvv', $engine ) ) return( false );

		/**
		 * Filter the transaction properties for the product and service feed.
		 *
		 * @since 1.0.0
		 * @since 2.0.0 Added the $submission_data, $feed, and $entry parameters.
		 *
		 * @param array $args            The transaction properties.
		 * @param int   $form_id         The ID of the form currently being processed.
		 * @param array $submission_data The customer and transaction data.
		 * @param array $feed            The feed object currently being processed.
		 * @param array $entry           The entry object currently being processed.
		 */
		$params[ 'redirect_url' ] = get_bloginfo( 'url' ) . '/?page=gf_simplepayment_ipn&entry_id=' . $entry[ 'id' ] . '&redirect_url=' . urlencode($this->return_url( $form[ 'id' ], $entry[ 'id' ] ) ) . ( isset( $params[ 'target' ] ) && $params[ 'target' ] ? '&target=' . $params[ 'target' ] : '' );
		$this->add_sp_pre_process( $feed, $submission_data, $form, $entry );
		$params = apply_filters( 'gform_simplepayment_args_before_payment', $params, $form[ 'id' ], $submission_data, $feed, $entry );
		$is_subscription = $feed[ 'meta' ][ 'transactionType' ] == 'subscription';
		try {
			$this->redirect_url = $this->SPWP->payment( $params, $engine );
		} catch ( Exception $e ) {
			$captured_payment = array(
				'is_success'     => false,
				'error_message'  => $e->getMessage(),
				'transaction_id' => $this->SPWP->engine->transaction,
				'amount'         => $params[$this->SPWP::AMOUNT],
			);
			$auth = array(
				'is_authorized'    => false,
				'transaction_id'   => $this->SPWP->engine->transaction,
				'error_message'  => $e->getMessage(),
				'captured_payment' => $captured_payment,
			);
			return( $auth );
		}

		if ( $this->redirect_url === true || !$this->redirect_url ) {
			$captured_payment = array(
				// Possible name for setup fee
				'is_success'     => true,
				'error_message'  => '',
				'transaction_id' => $this->SPWP->engine->transaction,
				'amount'         => $params[ $this->SPWP::AMOUNT ],
			);
			$auth = array(
				'is_authorized'    => true,
				'transaction_id'   => $this->SPWP->engine->transaction,
				'captured_payment' => $captured_payment,
			);
			if ( $is_subscription ) $auth[ 'subscription_id' ] = $this->SPWP->payment_id;

			$this->redirect_url = null;
		} else {
			$captured_payment = array(
				'is_success'     => false,
				'transaction_id' => $this->SPWP->engine->transaction,
				'amount'         => $params[ $this->SPWP::AMOUNT ],
			);
			$auth = array(
				'is_authorized'    => false,
				'transaction_id'   => $this->SPWP->engine->transaction,
				'captured_payment' => $captured_payment,
			);
		}
		return( $auth );
	}

	public function validation( $validation_result ) {
		if ( ! $validation_result['is_valid'] ) {
			return $validation_result;
		}
		$form  = $validation_result['form'];
		$entry = GFFormsModel::create_lead( $form );
		$feed  = $this->get_payment_feed( $entry, $form );
		if ( ! $feed ) {
			return $validation_result;
		}

		global $gf_payment_gateway;

		if ( $gf_payment_gateway && $gf_payment_gateway !== $this->get_slug() ) {
			$this->log_debug( __METHOD__ . '() Aborting. Submission already processed by ' . $gf_payment_gateway );

			return $validation_result;
		}

		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		//Do not process payment if payment amount is 0
		if ( floatval( $submission_data[ 'payment_amount' ] ) <= 0 ) {
			$this->log_debug( __METHOD__ . '(): Payment amount is zero or less. Not sending to payment gateway.' );
			return $validation_result;
		}

		$gf_payment_gateway = $this->get_slug();

		$this->is_payment_gateway  = true;
		$this->current_feed = $this->_single_submission_feed = $feed;
		$this->current_submission_data = $submission_data;

		$performed_authorization = false;
		$is_subscription = $feed[ 'meta' ][ 'transactionType' ] == 'subscription';
		if ( ! $is_subscription ) {
			//Running an authorization only transaction if function is implemented and this is a single payment
			$this->authorization = $this->authorize( $feed, $submission_data, $form, $entry );
			$performed_authorization = $this->authorization;
		} else {
			$subscription = $this->subscribe( $feed, $submission_data, $form, $entry );
			if ( $subscription !== false ) {
				$this->authorization[ 'is_authorized' ] = rgar( $subscription,'is_success' );
				$this->authorization[ 'error_message' ] = rgar( $subscription, 'error_message' );
				$this->authorization[ 'subscription' ]  = $subscription;
				$performed_authorization = true;
			}
		}

		if ( $performed_authorization ) {
			$this->log_debug( __METHOD__ . "(): Authorization result for form #{$form['id']} submission => " . print_r( $this->authorization, true ) );
		}
		if ( $performed_authorization && !rgar( $this->authorization, 'is_authorized' ) ) {
			$validation_result = $this->get_validation_result( $validation_result, $this->authorization );
			//Setting up current page to point to the credit card page since that will be the highlighted field
			GFFormDisplay::set_current_page( $validation_result[ 'form' ][ 'id' ], isset( $validation_result[ 'credit_card_page' ] ) ? $validation_result[ 'credit_card_page' ] : 1 );
		}
		return $validation_result;
	}

	/**
	 * Authorize and capture the transaction for the product & services type feed.
	 *
	 * @param array $feed            The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form            The form object currently being processed.
	 * @param array $entry           The entry object currently being processed.
	 *
	* @return array {
	 *     Return an $subscription array in the following format:
	 *
	 *     @type bool   $is_success      If the subscription is successful.
	 *     @type string $error_message   The error message, if applicable.
	 *     @type string $subscription_id The subscription ID.
	 *     @type int    $amount          The subscription amount.
	 *     @type array  $captured_payment {
	 *         If payment is captured, an additional array is created.
	 *
	 *         @type bool   $is_success     If the payment capture is successful.
	 *         @type string $error_message  The error message, if any.
	 *         @type string $transaction_id The transaction ID of the captured payment.
	 *         @type int    $amount         The amount of the captured payment, if successful.
	 *     }
	 *
	 * 
	 * */

	public function subscribe( $feed, $submission_data, $form, $entry ) {
		$subscription = $this->authorize( $feed, $submission_data, $form, $entry );
		if ( $subscription === false ) return( false );
		$subscription[ 'is_success' ] = $subscription[ 'captured_payment' ][ 'is_success' ];
		$subscription[ 'subscription_id' ] = $subscription[ 'subscription_id' ];
		$subscription[ 'amount' ] = $subscription[ 'captured_payment' ][ 'amount' ];
		return( $subscription );
	}

	//------- PROCESSING CALLBACK -----------//

	public function payment_success( $params ) {
		global $gf_sp_payment;
		if ( !isset( $params[ 'source' ] ) || $params[ 'source' ] != 'gravityforms' ) return;
		if ( !isset( $params[ 'source_id' ] ) && !$gf_sp_payment ) return;

		$entry = isset( $params[ 'source_id' ] ) ? GFAPI::get_entry( $params[ 'source_id' ] ) : $gf_sp_payment[ 'entry' ];

		if ( isset( $entry[ 'status' ] ) && $entry[ 'status' ] == 'spam' ) {
			$this->log_error( __METHOD__ . '(): Entry is marked as spam. Aborting.' );
			return false;
		}

		if ( isset( $entry[ 'payment_status' ] ) && strtolower( $entry[ 'payment_status' ] ) == 'paid' ) {
			$this->log_error( __METHOD__ . '(): Entry is marked as already paid. Aborting.' );
			return false;
		}

		$feed = isset( $gf_sp_payment[ 'feed' ] ) ? $gf_sp_payment[ 'feed' ] : $this->get_payment_feed( $entry );
		// Ignore IPN messages from forms that are no longer configured with the add-on
		if ( ! $feed || ! rgar( $feed, 'is_active' ) ) {
			$this->log_error( __METHOD__ . "(): Form no longer is configured with Simple Payment Addon. Form ID: {$entry['form_id']}. Aborting." );
			return false;
		}

		$action = [];
		$action[ 'transaction_id' ]   = $params[ 'transaction_id' ];
		$action[ 'amount' ]           = $params[ 'amount' ];
		$action[ 'payment_method' ]	= 'SimplePayment';
		$action[ 'type' ]	= 'complete_payment';
		$result = $this->complete_payment( $entry, $action );
		if ( method_exists( $this, 'trigger_payment_delayed_feeds' ) ) {
			$this->trigger_payment_delayed_feeds( $transaction_id, $feed, $entry, GFFormsModel::get_form_meta( $entry['form_id'] ) );
		}
	}

	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		return array(
				'complete_payment'          => esc_html__( 'Payment Completed', 'simple-payment' ),
				'fail_payment'              => esc_html__( 'Payment Failed', 'simple-payment' ),
				'create_subscription'       => esc_html__( 'Subscription Created', 'simple-payment' ),
				/*
				'add_pending_payment'       => esc_html__( 'Payment Pending', 'simple-payment' ),
				'void_authorization'        => esc_html__( 'Authorization Voided', 'simple-payment' ),
				'cancel_subscription'       => esc_html__( 'Subscription Canceled', 'simple-payment' ),
				'expire_subscription'       => esc_html__( 'Subscription Expired', 'simple-payment' ),
				'add_subscription_payment'  => esc_html__( 'Subscription Payment Added', 'simple-payment' ),
				'fail_subscription_payment' => esc_html__( 'Subscription Payment Failed', 'simple-payment' ),*/
		);
	}

	public function maybe_process_callback() {
		// Ignoring requests that are not this addon's callbacks.
		if ( ! $this->is_callback_valid() ) {
			return;
		}

		if ( ! $this->is_gravityforms_supported() ) {
			return false;
		}

		/*
		if ( rgar( $action, 'id' ) && $this->is_duplicate_callback( $action['id'] ) ) {
			return new WP_Error( 'duplicate', sprintf( esc_html__( 'This webhook has already been processed (Event Id: %s)', 'gravityforms' ), $action['id'] ) );
		}
		*/

		$this->log_debug( __METHOD__ . '(): IPN request received. Starting to process => ' . print_r( $_POST, true ) );
		// Valid IPN requests must have a custom field
		$custom_field = rgget( 'entry_id' );
		if ( empty( $custom_field ) ) {
			$this->log_error( __METHOD__ . '(): IPN request does not have a custom field, so it was not created by Gravity Forms. Aborting.' );

			return false;
		}

		//------ Getting entry related to this IPN ----------------------------------------------//
		$entry = GFAPI::get_entry( $custom_field );
		//Ignore orphan IPN messages (ones without an entry)
		if ( ! $entry ) {
			$this->log_error( __METHOD__ . '(): Entry could not be found. Aborting.' );
			return false;
		}
		$this->log_debug( __METHOD__ . '(): Entry has been found => ' . print_r( $entry, true ) );
		if ( $entry['status'] == 'spam' ) {
			$this->log_error( __METHOD__ . '(): Entry is marked as spam. Aborting.' );
			return false;
		}

		//------ Getting feed related to this IPN ------------------------------------------//
		$feed = $this->get_payment_feed( $entry );

		//Ignore IPN messages from forms that are no longer configured with the add-on
		if ( ! $feed || ! rgar( $feed, 'is_active' ) ) {
			$this->log_error( __METHOD__ . "(): Form no longer is configured with Simple Payment Addon. Form ID: {$entry['form_id']}. Aborting." );
			return false;
		}
		$this->log_debug( __METHOD__ . "(): Form {$entry['form_id']} is properly configured." );

		// TODO: should we check if callback is completed, or ok,
		// and rather determine the correct action to call

		//----- Processing IPN ------------------------------------------------------------//
		$this->log_debug( __METHOD__ . '(): Processing IPN...' );
		$action = [];
		$action[ 'transaction_id' ]   = $entry[ 'transaction_id' ];
		$action[ 'amount']           = $entry[ 'payment_amount' ];
		$action[ 'payment_method' ]	= 'SimplePayment';
		$action[ 'type' ] = 'complete_payment';
		$result = $this->complete_payment( $entry, $action );
		$this->log_debug( __METHOD__ . '(): SimplePayment processing complete.' );
		
		return( $action );
	}

	// # CRON JOB ------------------------------------------------------------------------------------------------------

	/**
	 * Check subscription status; Active subscriptions will be checked to see if their status needs to be updated.
	 */
    /*
	public function check_status() {

		// getting all Simple Payment subscription feeds
		$recurring_feeds = $this->get_feeds_by_slug( $this->_slug );

		foreach ( $recurring_feeds as $feed ) {

			// process renewal's if authorize.net feed is subscription feed
			if ( $feed['meta']['transactionType'] == 'subscription' ) {

				$this->log_debug( __METHOD__ . "(): Checking subscription statuses for feed (#{$feed['id']} - {$feed['meta']['feedName']})." );

				$form_id   = $feed['form_id'];
				$querytime = strtotime( gmdate( 'Y-m-d' ) );
				$querydate = gmdate( 'mdY', $querytime );

				// finding leads with a late payment date
				global $wpdb;

				// Get entry table names and entry ID column.
				$entry_table      = self::get_entry_table_name();
				$entry_meta_table = self::get_entry_meta_table_name();
				$entry_id_column  = version_compare( self::get_gravityforms_db_version(), '2.3-dev-1', '<' ) ? 'lead_id' : 'entry_id';

				$results = $wpdb->get_results( "SELECT l.id, l.transaction_id, m.meta_value as payment_date
                                                FROM {$entry_table} l
                                                INNER JOIN {$entry_meta_table} m ON l.id = m.{$entry_id_column}
                                                WHERE l.form_id={$form_id}
                                                AND payment_status = 'Active'
                                                AND meta_key = 'subscription_payment_date'
                                                AND meta_value < '{$querydate}'" );

				if ( empty( $results ) ) {
					$this->log_debug( __METHOD__ . '(): No entries with late payment.' );
					continue;
				}

				$this->log_debug( __METHOD__ . '(): Entries with late payment: ' .  count( $results ) );

				foreach ( $results as $result ) {

					$this->log_debug( __METHOD__ . '(): Processing entry => ' . print_r( $result, true ) );

					//Getting entry
					$entry_id = $result->id;
					$entry    = GFAPI::get_entry( $entry_id );

					$subscription_id = $result->transaction_id;
					// Get the subscription profile status
					$profile_status_request                  = array();
					$profile_status_request['TRXTYPE']       = 'R';
					$profile_status_request['TENDER']        = 'C';
					$profile_status_request['ACTION']        = 'I';
					$profile_status_request['ORIGPROFILEID'] = $subscription_id;
					//$profile_status_request['PAYMENTHISTORY'] = 'Y';

					$settings       = $this->get_settings( $feed );
					$profile_status = $this->post_to_payflow( $profile_status_request, $settings, $form_id );

					$status          = $profile_status['STATUS'];
					$subscription_id = $profile_status['PROFILEID'];

					switch ( strtolower( $status ) ) {
						case 'active' :

							// getting new payment date and count
							$new_payment_date   = $profile_status['NEXTPAYMENT'];
							$new_payment_count  = $profile_status['NEXTPAYMENTNUM'] - 1;
							$new_payment_amount = $profile_status['AMT'];

							if ( $new_payment_date > $querydate ) {

								// update subscription payment and lead information
								gform_update_meta( $entry_id, 'subscription_payment_count', $new_payment_count );
								gform_update_meta( $entry_id, 'subscription_payment_date', $new_payment_date );

								$action = array(
									'amount'          => $new_payment_amount,
									'subscription_id' => $subscription_id,
									'type'            => 'add_subscription_payment'
								);
								$this->add_subscription_payment( $entry, $action );

								//deprecated
								do_action( 'gform_simplepayment_after_subscription_payment', $entry, $subscription_id, $profile_status['AMT'] );
							}

							break;

						case 'expired' :

							$action = array(
								'subscription_id' => $subscription_id,
								'type'            => 'expire_subscription'
							);
							$this->expire_subscription( $entry, $action );

							//deprecated
							do_action( 'gform_simplepayment_subscription_expired', $entry, $subscription_id );

							break;

						case 'too many failures':
						case 'deactivated by merchant':
							$this->cancel_subscription( $entry, $feed );
							do_action( 'gform_simplepayment_subscription_canceled', $entry, $subscription_id );
							break;

						default:
							$this->cancel_subscription( $entry, $feed );
							do_action( 'gform_simplepayment_subscription_canceled', $entry, $subscription_id );
							break;
					}

				}

			}

		}
	}

    */
	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * Retrieve the settings to use when making the request to PayPal API.
	 *
	 * @param bool|array $feed False or the feed currently being processed.
	 *
	 * @return array
	 */
	public function get_settings( $feed = false ) {
		if ( ! $feed ) {
			$feed = $this->current_feed;
		}
		if ( $feed && rgars( $feed, 'meta/customSettingsEnabled' ) ) {
			$meta     = $feed['meta'];
			$settings = array(
				'engine'     => rgar( $meta, 'engine' ),
				'display' => rgar( $meta, 'display' ),
				'template' => rgar( $meta, 'template' ),
				'installments' => rgar( $meta, 'installments' ),
				'multiline' => rgar( $meta, 'multiline' ),
				'settings'   => rgar( $meta, 'settings' ),
			);
		} else {
			$settings = $this->get_plugin_settings();
		}
		return $settings;
	}

	/**
	 * Prepare the transaction arguments.
	 *
	 * @param array $feed            The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form            The form object currently being processed.
	 * @param array $entry           The entry object currently being processed.
	 *
	 * @return array
	 */
	public function prepare_credit_card_transaction( $feed, $submission_data, $form, $entry ) {
		$feed_name = rgar( $feed['meta'], 'feedName' );
		$this->log_debug( __METHOD__ . "(): Preparing transaction arguments based on feed #{$feed['id']} - {$feed_name}." );
		$this->log_debug( __METHOD__ . '(): $submission_data line_items => ' . print_r( $submission_data['line_items'], true ) );

		// Billing Information
		$args = array();
		if ( $feed[ 'meta' ][ 'transactionType' ] == 'subscription' ) { // other options: donation, product
			$period = 'monthly';
			switch( $feed[ 'meta' ][ 'payPeriod' ] ) {
				case 'yearly':
				case 'YEAR':
					$period = 'yearly';
					break;
				case 'quarterly':
				case 'QTER':
					$period = 'quarterly';
					break;
				case 'semesterly':
				case 'SMYR':
					$period = 'semesterly';
					break;	
				case 'monthly':
				case 'MONT':
					$period = 'monthly';
				default:
					$period = $feed[ 'meta' ][ 'payPeriod' ];
			}
			$args[ 'payments' ] = $period; 
			$args[ 'times' ] = $feed[ 'meta' ][ 'recurringTimes' ];
		}
		
		$engine_field = $this->get_fields_by_name( $form, 'engine' );
		if ( $engine_field ) {
			$engine = rgpost( "input_{$engine_field[0]->id}" );
			if ($engine) $args[ 'engine' ] = $engine;
		}
		
		$card_owner_field = $this->get_card_owner_id_field( $form );
		if ( $card_owner_field ) {
			$card_ownder_id = rgpost( "input_{$card_owner_field->id}" );
			if ($card_ownder_id) $args[$this->SPWP::CARD_OWNER_ID] = $card_ownder_id;
		}
		if (isset($submission_data['card_name'])) $args[$this->SPWP::CARD_OWNER] = $submission_data['card_name'];

		// TODO: Add installments / subscription?
		if (isset($submission_data['card_number'])) $args[$this->SPWP::CARD_NUMBER] = $submission_data['card_number'];
		if (isset($submission_data['card_expiration_date']) && $submission_data['card_expiration_date']) {
			$args[$this->SPWP::CARD_EXPIRY_MONTH] = $submission_data['card_expiration_date'][0];
			$args[$this->SPWP::CARD_EXPIRY_YEAR] = $submission_data['card_expiration_date'][1];
		}
		if (isset($submission_data['card_security_code'])) $args[$this->SPWP::CARD_CVV] = $submission_data['card_security_code'];
		if (isset($submission_data['address'])) $args[$this->SPWP::ADDRESS] = $submission_data['address'];
		if (isset($submission_data['address2'])) $args[$this->SPWP::ADDRESS2] = $submission_data['address2'];
		if (isset($submission_data['city'])) $args[$this->SPWP::CITY] = $submission_data['city'];
		if (isset($submission_data['state'])) $args[$this->SPWP::STATE] = $submission_data['state'];
        if (isset($submission_data['zip'])) $args[$this->SPWP::ZIPCODE] = $submission_data['zip'];
		if (isset($submission_data['country'])) $args[$this->SPWP::COUNTRY] = GFCommon::get_country_code( $submission_data['country'] );
        
		// Customer Information
		if (isset($submission_data['firstName'])) $args[$this->SPWP::FIRST_NAME] = $submission_data['firstName'];
		if (isset($submission_data['lastName'])) $args[$this->SPWP::LAST_NAME] = $submission_data['lastName'];
		if (isset($submission_data['email'])) $args[ $this->SPWP::EMAIL ] = $submission_data['email'];
		if (isset($submission_data['phone'])) $args[ $this->SPWP::PHONE ] = $submission_data['phone'];
		if (isset($submission_data['company'])) $args[ $this->SPWP::COMPANY ] = $submission_data['company'];

		// Product Information
		if ( isset( $submission_data[ 'product_code' ] ) ) $args[ $this->SPWP::PRODUCT_CODE ] = $submission_data[ 'product_code' ];

		$i = 0;
		$args[ $this->SPWP::CURRENCY ] = GFCommon::get_currency();
		$args[ $this->SPWP::PRODUCT ] = '';
		foreach ( $submission_data['line_items'] as $item ) {
			if ( rgar( $feed[ 'meta' ], 'multiline' ) ) {
				$product = [
					'id' => $item[ 'id' ],
					'name' => $item[ 'name' ],
					'description' => $item[ 'description' ],
					'amount' => $item[ 'unit_price' ],
					'qty' => $item[ 'quantity' ]
				];
				$product[ $this->SPWP::PRODUCTS ][] = $product;
			} // else {
				$args[ $this->SPWP::PRODUCT ] .= ( $i >= 1 ? ', ' : '' ) . $item[ 'name' ]; // ?? TO DO figure out why there is warning that desc is undefined
			//}
			$i++;
		}
		if ( !$args[ $this->SPWP::PRODUCT ] ) $args[ $this->SPWP::PRODUCT ] = $form[ 'title' ];
		$args[ $this->SPWP::AMOUNT ] = $submission_data[ 'payment_amount' ];

		$args[ 'source' ] = 'gravityforms';
		$args[ 'source_id' ] = $entry[ 'id' ];
		return( $args );
	}

	public function has_credit_card_field( $form ) {
		return( true );
	}

	public function get_card_owner_id_field( $form ) {
		$fields = GFAPI::get_fields_by_type( $form, array( 'card_owner_id' ) );
		return empty( $fields ) ? false : $fields[0];
	}

	public function get_fields_by_name( $form, $names, $use_field_label = false ) {
		$fields = array();
		if ( ! is_array( rgar( $form, 'fields' ) ) ) {
			return $fields;
		}

		if ( ! is_array( $names ) ) {
			$names = array( $names );
		}

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */
			$name = $use_field_label ? $field->label : $field->inputName;
			if ( in_array( $name, $names ) ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/*public function settings_json( $field, $echo = true ) {
		$field[ 'type' ] = 'textarea'; //making sure type is set to textarea
		$attributes    = $this->get_field_attributes( $field );
		$default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
		$value = $this->get_setting( $field[ 'name' ], $default_value );

		$name = '' . esc_attr( $field['name'] );
		$html = '';
		$html .= print_r( $value, true );
		$html .= '<textarea
				name="_gaddon_setting_' . $name . '" ' .
				implode( ' ', $attributes ) .
				'>' . 
				( $value ? json_encode( $value ) : '' ).
				'</textarea>';

		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}*/
}

class GF_Field_Card_Owner_ID extends GF_Field_Text {

	public $type = 'card_owner_id';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Card Owner ID', 'simple-payment' );
	}
}

