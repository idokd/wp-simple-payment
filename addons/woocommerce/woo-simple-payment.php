<?php

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) 
	return;

function sp_wc_add_to_gateways($gateways) {
	$gateways[] = 'WC_SimplePayment_Gateway';
	return($gateways);
}
add_filter( 'woocommerce_payment_gateways', 'sp_wc_add_to_gateways' );

function sp_wc_gateway_plugin_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'options-general.php?page=sp' ) . '">' . __( 'Configure', 'simple-payment' ) . '</a>'
	);
	return(array_merge($plugin_links, $links));
}
add_filter('plugin_action_links_'.plugin_basename( __FILE__ ), 'sp_wc_gateway_plugin_links');

add_action('plugins_loaded', 'sp_wc_gateway_init', 11);

function sp_wc_gateway_init() {
    
	class WC_SimplePayment_Gateway extends WC_Payment_Gateway_CC {

        protected $SPWP;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			$this->id = 'simple-payment';
			$this->icon = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields = false;//true;
			$this->method_title = __( 'Simple Payment', 'simple-payment' );
			$this->method_description = __( 'Allows integration of Simple Payment gateways into woocommerce', 'simple-payment' );
            $this->supports = [] ;// ['products', 'refunds', 'tokenization', 'default_credit_card_form'];
            // credit_card_form_cvc_on_saved_method
            //$this->new_method_label = __('new payment method', 'simple-payment');

			// Load the settings.
			$this->init_form_fields();
            $this->init_settings();
            
		  
			// Define user set variables
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_'.$this->id, array($this, 'thankyou_page'));
          
            if (!$this->has_fields) add_action('woocommerce_receipt_'.$this->id, array(&$this, 'provider_step'));

			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
            $this->SPWP = SimplePaymentPlugin::instance();
        }
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
            $engines = ['' => 'Default'];
            foreach (SimplePaymentPlugin::$engines as $engine) $engines[$engine] = $engine;
			$this->form_fields = apply_filters( 'wc_offline_form_fields', array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'simple-payment' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Simple Payment', 'simple-payment' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'simple-payment' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'simple-payment' ),
					'default'     => __( 'Simple Payment', 'simple-payment' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'simple-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'simple-payment' ),
					'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', 'simple-payment' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Instructions', 'simple-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'simple-payment' ),
					'default'     => '',
					'desc_tip'    => true,
                ),
                'engine' => array(
					'title'   => __( 'Engine', 'simple-payment' ),
					'type'    => 'select',
                    'label'   => __( 'Select Payment Gateway', 'simple-payment' ),
                    'description' => __( 'If none selected it will use Simple Payment default.', 'simple-payment' ),
					'desc_tip'    => true,
                    'options' => $engines,
                    'default' => ''
				),
                'installments' => array(
					'title'   => __( 'Installments', 'simple-payment' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Insallments', 'simple-payment' ),
                    'default' => 'yes',
                    'description' => __( 'Enable installments on checkout page', 'simple-payment' ),
					'desc_tip'    => true,
                ),
                'template' => array(
					'title'       => __( 'Template', 'simple-payment' ),
                    'type'        => 'text',
                    'label'   => __( 'Custom checkout template form', 'simple-payment' ),
					'description' => __( 'If you wish to use a custom form template.', 'simple-payment' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
    
        function provider_step($order_id) {
            $url = get_post_meta((int) $order_id, 'sp_provider_url', true);
            wc_delete_order_item_meta((int) $order_id, 'sp_provider_url');
            return('<iframe width="100%" height="1000" frameborder="0" src="'.$url.'" ></iframe>');
        }

		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
        public function payment_fields() {
            if (!$this->has_fields) return;
            $template = $this->get_option('template') ? : 'form-woocommerce';
            if ($override_template = locate_template($template.'.php')) load_template($override_template);
            else load_template(SPWP_PLUGIN_DIR.'/templates/'.$template.'.php');

            if ($this->supports('tokenization') && is_checkout()) {
                wp_enqueue_script('simple-payment-checkoutscript',
                plugins_url('/simple-payment/addons/woocommerce/checkout.js'),
                ['jquery'],
                WC()->version);
                $this->saved_payment_methods();
                // TODO: show CVV in case it is tokenixe
                $this->save_payment_method_checkbox();
            }            
        }

        public function validate_fields() {
            if ($this->has_fields) {
                $validations = $this->SPWP->validate($_REQUEST);
                    foreach ($validations as $key => $description)
                    wc_add_notice( sprintf(__('Payment error: %s', 'simple-payment'), $description, $key), 'error' );
            }
        }
        
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $data = $order->get_data();
            $params = $data['billing'];
            $params = array_merge($params, $_REQUEST);
            $engine = $this->get_option('engine') ? : null;
            try {
                // field_name
                // card-number, card-expiry, card-cvc
                $params[$this->SPWP::PRODUCT] = sprintf(__('WooCommerce Order %s', 'simple-payment'), $order_id);
                $params[$this->SPWP::AMOUNT] = $order->get_total();
                $params[$this->SPWP::ZIPCODE] = $params['postcode'];
                $params[$this->SPWP::ADDRESS] = $params['address_1'];
                $params[$this->SPWP::ADDRESS2] = $params['address_2'];
                if (!$this->has_fields) $params['redirect_url'] = $order->get_checkout_order_received_url();
                //get_checkout_order_received_url, get_cancel_order_url_raw
                $url = $this->SPWP->payment($params, $engine);
                $this->view_transaction_url = 'wp-admin/admin.php?page=simple-payments-details&transaction_id='.$this->SPWP->engine->transaction.'&engine='.$engine;
                if ($url && $url !== true) {
                    //return(true);
                    //wp_redirect($url);
                    // echo "<script>window.top.location.href = \"$url\";</script>";
                    //die;
                    //if (version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) $redirect = add_query_arg('order', $order_id, add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))));
                    //else $redirect = add_query_arg(['order-pay' => $order_id], add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)));
                    
                    return([
                        'result' => 'success',
                        'redirect' => $url
                    ]);
                }
            } catch (Exception $e) {
                $this->SPWP->error($params, $e->getCode(), $e->getMessage());
                wc_add_notice( __('Payment error: ', 'simple-payment') . $e->getMessage(), 'error' );
                return;
            }
            /*try {
                // TODO: check the need here? it should be somewhere elsewhere
                //if ($url !== false) $url = $this->SPWP->post_process($_REQUEST, $engine);
            } catch (Exception $e) {
                $this->SPWP->error($params, $e->getCode(), $e->getMessage());
                wc_add_notice( __('Payment error :', 'simple-payment') . $e->getMessage(), 'error' );
                return;
            }*/
			// Mark as on-hold (we're awaiting the payment)
            
            $order->payment_complete($this->SPWP->engine->transaction);
            wc_reduce_stock_levels($order_id);
			WC()->cart->empty_cart();
			return([
                'result' => 'success',
                'redirect'=> $this->get_return_url($order)
            ]);
		}
  } 

}