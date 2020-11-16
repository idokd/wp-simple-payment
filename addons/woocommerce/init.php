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

function sp_wc_maybe_failed_order() {
    if ($payment_id = $_REQUEST['payment_id']) {
        if ($url = $_REQUEST['redirect_url']) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            if (!isset($params['order-pay']) || !$params['order-pay']) return;
            $order = wc_get_order($params['order-pay']);
            $order->add_order_note( __('Important consult payment status before processing.', 'simple-payment') );
            $url = add_query_arg('payment_id', $payment_id, $url);
            wp_redirect($url);
            die;
        }
    }
}
add_action( 'wc_ajax_checkout', 'sp_wc_maybe_failed_order', 5 );

function sp_wc_gateway_init() {
    
	class WC_SimplePayment_Gateway extends WC_Payment_Gateway_CC {

        protected $SPWP;
        protected static $_instance = null;
        public $view_transaction_url = '/wp-admin/admin.php?page=simple-payments-details&id=%s';

        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }
        
        public function __toString() {
            return strtolower( get_class( $this ) );
        }
        
		public function __construct() {
            $this->SPWP = SimplePaymentPlugin::instance();
// 
			$this->id = 'simple-payment';
            $this->icon = apply_filters('woocommerce_offline_icon', '');
            
			$this->has_fields =  $this->SPWP->supports('cvv', $this->get_option('engine') ? : null);
			$this->method_title = __( 'Simple Payment', 'simple-payment' );
			$this->method_description = __( 'Allows integration of Simple Payment gateways into woocommerce', 'simple-payment' );
            $this->supports =  [ 'tokenization', 'subscriptions', 'products', 'refunds',  'default_credit_card_form']; // tokenization, subscriptions

            // TODO: credit_card_form_cvc_on_saved_method - add this to support when CVV is not required on tokenized cards - credit_card_form_cvc_on_saved_method
            // TODO: tokenization- in order to support tokinzation consider using the javascript
            $this->new_method_label = __('new payment method', 'simple-payment');

            // Load the settings.
			$this->init_form_fields();            
            $this->init_settings();
            
			// Define user set variables
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );
		  
            // Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_'.$this->id, array($this, 'thankyou_page'));
          
            if (!$this->has_fields || in_array($this->get_option('display'), ['iframe', 'modal'])) add_action('woocommerce_receipt_'.$this->id, array(&$this, 'provider_step'));
            add_action( "woocommerce_api_{$this}", array( $this, 'gateway_response' ) );
            
            // Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
       
            /** @since 1.2.0 */
            add_filter( 'woocommerce_available_payment_gateways', 				array( $this, 'selected_payment_gateways' ) );
            add_filter( 'woocommerce_get_customer_payment_tokens', 				array( $this, 'selected_customer_payment_token' ), 10, 3 );
            add_filter( 'wc_payment_gateway_form_saved_payment_methods_html',  	array( $this, 'payment_methods_html' ) );
            
            add_filter( version_compare( WC()->version, '3.0.9', '>=' ) ? 'woocommerce' : 'wocommerce' . '_credit_card_type_labels', array( $this, 'credit_card_type_labels' ) );
            
            if (!$this->has_fields && !$this->description && $this->get_option('in_checkout') == 'yes') {
                // Setting some value so it will go into the payment_fields() function
                $this->description = ' ';
            }
        }
    
        public function needs_setup() {
            return(false);
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
               /* 'cvv' => array(
					'title'   => __( 'Use CVV', 'simple-payment' ),
					'type'    => 'checkbox',
					'label'   => __( 'Use CVV method if available', 'simple-payment' )
				), */
                'display' => array(
					'title'   => __( 'Display Method', 'simple-payment' ),
					'type'    => 'select',
                    'label'   => __( 'Display Method', 'simple-payment' ),
                    'description' => __( 'If none selected it will use Simple Payment default.', 'simple-payment' ),
					'desc_tip'    => true,
                    'options' => ['' => 'Default', 'iframe' => 'IFRAME', 'modal' => 'Modal', 'redirect' => 'redirect'],
                    'default' => ''
                ),
                'in_checkout' => array(
					'title'   => __( 'Show in Checkout', 'simple-payment' ),
					'type'    => 'checkbox',
                    'label'   => __( 'Show in Modal or IFRAME in Checkout page, instead of Order Review', 'simple-payment' ),
                    'default' => 'yes',
                    'description' => __( 'For Modal & IFRAME Only, If none selected it will use Simple Payment default.', 'simple-payment' ),
					'desc_tip'    => true,
                ),
                'product' => array(
					'title'       => __( 'Product', 'simple-payment' ),
                    'type'        => 'text',
                    'label'   => __( 'Custom product name to use in Simple Payment', 'simple-payment' ),
					'description' => __( 'Simple Payment globalize the purchase to single product on the Payment Gateway.', 'simple-payment' ),
					'default'     => __('WooCommerce Order %s', 'simple-payment'),
					'desc_tip'    => true,
				),
                'single_item_use_name' => array(
					'title'   => __( 'Single Item Orders', 'simple-payment' ),
					'type'    => 'checkbox',
					'label'   => __( 'Single Item Order use as Product Name', 'simple-payment' ),
                    'default' => 'yes',
                    'description' => __( 'When order has a single item use item name as product name', 'simple-payment' ),
					'desc_tip'    => true,
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
                'settings' => array(
					'title'       => __( 'Settings', 'simple-payment' ),
                    'type'        => 'textarea',
                    'label'   => __( 'Custom & advanced checkout settings', 'simple-payment' ),
					'description' => __( 'Use if carefully', 'simple-payment' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
    /*
    { "type":"hidden", "display": "modal" } 
    */
        function provider_step($order_id) {
            $order = wc_get_order( $order_id );
            $params = $this->params($order->get_data());

            $url = get_post_meta((int) $order_id, 'sp_provider_url', true);
            wc_delete_order_item_meta((int) $order_id, 'sp_provider_url');

            $settings = $this->get_option('settings') ? json_decode($this->get_option('settings'), true, 512, JSON_OBJECT_AS_ARRAY) : [];
            if ($settings) $params = array_merge($settings, $params);
            $params['method'] = 'direct_open';

            $params['type'] = 'form';
            $params['form'] = 'plugin-addon';
            $params['url'] = $url;
            $params['display'] = $this->get_option('display');

            set_query_var('display', $this->get_option('display'));
            set_query_var('settings', $params);
            print $this->SPWP->checkout($params);
        }

        public function gateway_response() {
            $order_key = $_REQUEST['key'];
            $order_id = $_REQUEST['order-pay'];
            if (!$order_id) {
                return;
            }
            $order = wc_get_order($order_id);
            if (!$order = wc_get_order($order_id)) {
                return;
            }
            //if ( ! empty( $transaction->Token ) && $order->get_user_id() ) {
			//	$this->save_token( $transaction, $order->get_user_id() );
			//}
            $payment_id = $_REQUEST['payment_id']; // get payment id
            $order->payment_complete($payment_id);
            WC()->cart->empty_cart();
            $target = isset($_REQUEST['target']) ? $_REQUEST['target'] : '';
            $targets = explode(':', $target);
            $target = $targets[0];
            $url = $this->get_return_url($order);
            switch ($target) {
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
                    wp_redirect($url);
            }
            wp_die();
            return;
            //WC()->session->save_payment_method 	= null;
		    //WC()->session->selected_token_id 	= null;

            //$order->get_checkout_order_received_url(); //$this->get_return_url($order); //$order->get_checkout_order_received_url();
            //$params["SuccessRedirectUrl"] = untrailingslashit(home_url()).'?wc-api=WC_Gateway_Cardcom&'.('cardcomListener=cardcom_successful&order_id='.$order_id);
/*
            $order->payment_complete($this->SPWP->engine->transaction);
            wc_reduce_stock_levels($order_id);
			WC()->cart->empty_cart();*/
            /*$raw_data = json_decode( WC_Pelecard_API::get_raw_data(), true );
            $transaction = new WC_Pelecard_Transaction( null, $raw_data );
            $order_id = $transaction->get_order_id();
            if ( ! $order_id && isset( $raw_data['ResultData']['TransactionId'] ) ) {
                $transaction_id = wc_clean( $raw_data['ResultData']['TransactionId'] );
                $transaction = new WC_Pelecard_Transaction( $transaction_id );
                $order_id = $transaction->get_order_id();
            }
            
            // bail
            if ( ! $order = wc_get_order( $order_id ) ) {
                return;
            }
            
            $validated_ipn_response = $transaction->validate( is_callable( array( $order, 'get_order_key' ) ) ? $order->get_order_key() : $order->order_key );
            if ( apply_filters( 'wc_pelecard_gateway_validate_ipn_response', $validated_ipn_response ) ) {
                $this->do_payment( $transaction, $order );
            }*/
        }
                
    /*

        NO NEED TO; it takes it automatically via view_transaction_url, unless we wish to implement 
        other popup or similar
        public function get_transaction_url( $order ) {
            $return_url     = '';
            $transaction_id = $order->get_transaction_id();
            if ( ! empty( $this->view_transaction_url ) && ! empty( $transaction_id ) ) {
                $return_url = sprintf( $this->view_transaction_url, $transaction_id );
            }
            return apply_filters( 'woocommerce_get_transaction_url', $return_url, $order, $this );
        }
      */  

		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
    
        /**
         * Enqueues our tokenization script to handle some of the new form options.
         */
        public function tokenization_script() {
            wp_enqueue_script(
                'woocommerce-tokenization-form',
                SPWP_PLUGIN_URL.'addons/woocommerce/js/tokenization-form' . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
                array( 'jquery' ),
                $this->SPWP::$version
            );
            
            //wp_enqueue_script('simple-payment-checkoutscript',
            //    SPWP_PLUGIN_URL.'addons/woocommerce/js/checkout.js'),
            //    ['jquery'],
            //    $this->SPWP::$version
            //);

            //wp_enqueue_script( 'wc-credit-card-form' );
        }
        
        // TODO: it requires description to enter here... consider what to do.
        public function payment_fields() {
            $description = $this->get_description();
            if ( $description ) {
                echo wpautop( wptexturize( $description ) );
            }
            if (!$this->has_fields) {
                if (in_array($this->get_option('display'), ['iframe', 'modal']) && $this->get_option('in_checkout') == 'yes') {
                    $params = json_decode($this->get_option('settings'), true, 512, JSON_OBJECT_AS_ARRAY);
                    $params['woocommerce_show_checkout'] = true;
                    $params['display'] = $this->get_option('display');
                    
                    if ($this->get_option('display') == 'iframe') echo '<div sp-data="container"></div>';
                    echo '<script> var sp_settings = '.json_encode($params, true).'; </script>';
                    wp_enqueue_script('simple-payment-woocommerce-checkout-script',
                        SPWP_PLUGIN_URL.'addons/woocommerce/js/simple-payment-woocommerce-checkout.js',
                        ['jquery'],
                        $this->SPWP::$version
                    );
                    $this->SPWP->scripts();
                }
                return;
            }
            set_query_var('installments', $this->get_option('installments') == 'yes');
            $template = $this->get_option('template') ? : null;
            if ($template) {
                if ($override_template = locate_template($template.'.php')) load_template($override_template);
                else load_template(SPWP_PLUGIN_DIR.'/templates/'.$template.'.php');
            } else parent::payment_fields();         
        }

        public function validate_fields() {
            $ok = parent::validate_fields();
            if ($this->has_fields) {
                $params = $this->params($_REQUEST);
                $validations = $this->SPWP->validate($params);
                foreach ($validations as $key => $description) {
                    wc_add_notice( sprintf(__('Payment error: %s', 'simple-payment'), __($description, 'simple-payment'), $key), 'error' );
                    $ok = false;
                }
            }
            return($ok);
        }
        /*
        $token = new WC_Payment_Token_CC();
        $token->set_token( (string)($ipn_get_response->TransactionToken) );
        $token->set_gateway_id( 'icredit_payment' ); 
        $token->set_card_type('כרטיס אשראי' );
        $token->set_last4( (string)(substr($json_response->CardNumber,12)));
        $token->set_expiry_month( (string)$cardmm );
        $token->set_expiry_year( '20'.(string)$cardyy);
        $token->set_user_id($ipn_get_response->Custom4);
        $token->save();   
        */

        protected function params($params) {
            if (isset($params['billing'])) $params = array_merge($params, $params['billing']);
            if (!isset($params[$this->SPWP::PRODUCT])) $params[$this->SPWP::PRODUCT] = $this->product($params);
            if (isset($params['total'])) $params[$this->SPWP::AMOUNT] = $params['total'];
            if (isset($params['company'])) $params[$this->SPWP::TAX_ID] = $params['company'];
            if (isset($params['postcode'])) $params[$this->SPWP::ZIPCODE] = $params['postcode'];
            if (isset($params['address_1'])) $params[$this->SPWP::ADDRESS] = $params['address_1'];
            if (isset($params['address_2'])) $params[$this->SPWP::ADDRESS2] = $params['address_2'];

            // TODO: support product_code
            if ($this->has_fields) { 
                // TODO: when tokenized we do not have this value
                if (isset($params[$this->id.'-card-owner-id'])) $params[$this->SPWP::CARD_OWNER_ID] = $params[$this->id.'-card-owner-id'];
                if (!isset($params[$this->SPWP::CARD_OWNER])) $params[$this->SPWP::CARD_OWNER] = $params['first_name'].' '.$params['last_name'];
                if (!isset($params[$this->SPWP::CARD_OWNER]) || !$params[$this->SPWP::CARD_OWNER]) $params[$this->SPWP::CARD_OWNER] = $params['billing_first_name'].' '.$params['billing_last_name'];
                if (isset($params[$this->id.'-card-number'])) $params[$this->SPWP::CARD_NUMBER] = str_replace(' ', '', $params[$this->id.'-card-number']);
                if (isset($params[$this->id.'-card-cvc'])) {
                    $params[$this->SPWP::CARD_CVV] = $params[$this->id.'-card-cvc'];
                    $expiry = $params[$this->id.'-card-expiry'];
                    $expiry = explode('/', $expiry);
                    $century = floor(date('Y') / 1000) * 1000;
                    $params[$this->SPWP::CARD_EXPIRY_MONTH] = trim($expiry[0]);
                    $expiry[1] = trim($expiry[1]);
                    $params[$this->SPWP::CARD_EXPIRY_YEAR] = strlen($expiry[1]) < 4 ? ($century + $expiry[1]) : $expiry[1];
                }
            }
            return($params);
        }

        public function payment_methods_html( $html ) {
            global $wp;
            if ( ! is_checkout_pay_page() ) {
                return $html;
            }
            $order_id = absint( $wp->query_vars['order-pay'] );
            $order = wc_get_order( $order_id );
            $payments = array(
                'MaxPayments'			=> $this->SPWP->param('installments_min'),
                'MinPayments'			=> $this->SPWP->param('installments_max'),
            //    'MinPaymentsForCredit'	=> $this->mincredit
            );
            if ( $payments['MaxPayments'] !== $payments['MinPayments'] || 1 == $payments['MinPayments'] ) {
                //$html .= wc_get_template_html( 'checkout/number-of-payments.php', array( 'payments' => $payments ), null, WC_Pelecard()->plugin_path() . '/templates/' );
            }
            return($html);
        }

        protected function product($params) {
            $product = sprintf($this->get_option('product'), isset($params['id']) ? $params['id'] : ''); 
            if (isset($params['line_items']) && count($params['line_items']) == 1 && $this->get_option('single_item_use_name') == 'yes') {
                $product = array_shift($params['line_items']);
                $product = $product->get_name();
            } 
            return($product);
        }

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $engine = $this->get_option('engine') ? : null;

            $params = $this->params(array_merge($order->get_data(), $_REQUEST));
            $params['source'] = 'woocommerce';
            $params['source_id'] = $order_id;
            wc_reduce_stock_levels($order_id);
            try {
                //get_checkout_order_received_url, get_cancel_order_url_raw

                $params['redirect_url'] = WC()->api_request_url( "{$this}" );

                if (version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) $params['redirect_url'] = add_query_arg('order', $order_id, add_query_arg('key', $order->get_order_key(), $params['redirect_url']));   
                else $params['redirect_url'] = add_query_arg(['order-pay' => $order_id], add_query_arg('key', $order->get_order_key(), $params['redirect_url']));

                if (in_array($this->get_option('display'), ['iframe', 'modal'])) {
                    $params['redirect_url'] = add_query_arg(['target' => '_top'], $params['redirect_url']);
                }
                $url = $external = $this->SPWP->payment($params, $engine);
                if (!is_bool($url)) {
                    // && !add_post_meta((int) $order_id, 'sp_provider_url', $url, true) 
                    update_post_meta((int) $order_id, 'sp_provider_url', $url);
                 }

                if (!$this->has_fields && in_array($this->get_option('display'), ['iframe', 'modal'])) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) $url = add_query_arg('order', $order_id, add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))));
                    else $url = add_query_arg(['order-pay' => $order_id], add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)));
                }
                //    if (version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) $url = add_query_arg('order', $order_id, add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))));   
                //    else $url = add_query_arg(['order-pay' => $order_id], add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url()));
                //}
                if ($url && $url !== true) {
                    //return(true);
                    //wp_redirect($url);
                    // echo "<script>window.top.location.href = \"$url\";</script>";
                    //die;
                    
                    return([
                        'result' => 'success',
                        'redirect' => !$this->has_fields && in_array($this->get_option('display'), ['iframe', 'modal']) && $this->get_option('in_checkout') == 'yes' ? '#'.$external : $url,
                        'external' => $external,
                        'messages' => '<div></div>'
                    ]);
                }
            } catch (Exception $e) {
                $this->SPWP->error($params, $e->getCode(), $e->getMessage());
                wc_add_notice( __('Payment error: ', 'simple-payment') . __($e->getMessage(), 'simple-payment'), 'error' );
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
            
            $order->payment_complete($this->SPWP->payment_id);
			WC()->cart->empty_cart();
			return([
                'result' => 'success',
                'redirect'=> $this->get_return_url($order)
            ]);
        }
        
        public function save_token( $transaction, $user_id = 0 ) {
            //$token_number 		= $transaction->Token;
            //$token_card_type 	= $this->get_card_type( $transaction );
            //$token_last4 		= substr( $transaction->CreditCardNumber, -4 );
            //$token_expiry_month = substr( $transaction->CreditCardExpDate, 0, 2 );
            //$token_expiry_year 	= substr( date( 'Y' ), 0, 2 ) . substr( $transaction->CreditCardExpDate, -2 );
            
            $token = new WC_Payment_Token_CC();
            $token->set_token( $token_number );
            $token->set_gateway_id( $this->id );
            $token->set_card_type( $token_card_type );
            $token->set_last4( $token_last4 );
            $token->set_expiry_month( $token_expiry_month );
            $token->set_expiry_year( $token_expiry_year );
            $token->set_user_id( 0 < $user_id ? $user_id : get_current_user_id());
            
            if ($token->save()) return($token);
            return(null);
        }

        /**
         * Display selected payment token on checkout pay page.
         *
         * @param  array $tokens
         * @param  int $customer_id
         * @param  string $gateway_id
         * @return array
         */
        public function selected_customer_payment_token( $tokens, $customer_id, $gateway_id ) {
            if ( $gateway_id === $this->id && ! empty( WC()->session->selected_token_id ) && is_checkout_pay_page() ) {
                $selected_token_id = WC()->session->selected_token_id;
                // bail
                if ( ! array_key_exists( $selected_token_id, $tokens ) ) {
                    return $tokens;
                }
                $selected_token = $tokens[ $selected_token_id ];
                $selected_token->set_default( true );
                $tokens = array( $selected_token_id => $selected_token );
                
                // Remove new payment option
                add_filter( 'woocommerce_payment_gateway_get_new_payment_method_option_html', '__return_empty_string' );
            }
            return $tokens;
        }
    
        /**
         * Display selected payment gateway on checkout pay page.
         *
         * @param  array $available_gateways
         * @return array
         */
        public function selected_payment_gateways( $available_gateways ) {
            $is_checkout_pay_page = is_checkout_pay_page();
            if ( $is_checkout_pay_page ) {
                $gateway = $available_gateways[ $this->id ];
                $gateway->order_button_text = apply_filters( 'sp_wc_order_button_text', __( 'Pay for order', 'simple-payment' ), $is_checkout_pay_page );
                if ( ! empty( WC()->session->selected_token_id ) ) {
                    $available_gateways = array( $this->id => $gateway );
                }
            }
            return $available_gateways;
        }
    
        /**
         * Add credit card types and labels.
         *
         * @param  array $labels
         * @return array
         */
        public function credit_card_type_labels( $labels ) {
            return array_merge( $labels, apply_filters( 'sp_wc_credit_card_type_labels', array(
                'visa' 		=> __( 'Visa', 'simple-payment' ),
                'mastercard' 		=> __( 'Mastercard', 'simple-payment' ),
                'american express' 	=> __( 'American Express', 'simple-payment' )
            ) ) );
        }
  } 

}