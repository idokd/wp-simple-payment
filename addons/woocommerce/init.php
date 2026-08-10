<?php

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active

$_active_plugins = array_merge( is_multisite() ? array_keys( get_site_option( 'active_sitewide_plugins', [] ) ) : [], get_option( 'active_plugins', [] ) );

if ( !in_array( 'woocommerce/woocommerce.php', $_active_plugins ) ) 
	return;

function sp_wc_add_to_gateways( $gateways) {
	$gateways[] = 'WC_SimplePayment_Gateway';
	return( $gateways );
}
add_filter( 'woocommerce_payment_gateways', 'sp_wc_add_to_gateways' );

function sp_wc_gateway_plugin_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'options-general.php?page=sp' ) . '">' . __( 'Configure', 'simple-payment' ) . '</a>'
	);
	return( array_merge( $plugin_links, $links ) );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'sp_wc_gateway_plugin_links' );

add_action( 'sp_pre_callback', function() {
    if ( function_exists( 'WC' ) ) {
        WC()->payment_gateways();
    }
} );

$_sp_woocommerce_save_token = null;
function sp_wc_save_token( $transaction, $transaction_id = null, $params = [], $user_id = 0, $default = false ) {
    global $_sp_woocommerce_save_token;
    if ( !is_array( $transaction ) && json_decode( $transaction ) && json_last_error() === JSON_ERROR_NONE ) $trasnaction = json_decode( $transaction );
    $transaction = is_array( $transaction ) ? $transaction : SimplePaymentPlugin::instance()->fetch( $transaction );
    $token = isset( $transaction[ 'token' ] ) && is_array( $transaction[ 'token' ] ) ? $transaction[ 'token' ] : $transaction;
    if ( $_sp_woocommerce_save_token == $token ) return( null );
    $_sp_woocommerce_save_token = $token;
    $engine = $token[ 'engine' ] ?? $transaction[ 'engine' ] ?? null;
    if ( !$engine || !SimplePaymentPlugin::supports( 'tokenization', $engine ) ) return( null );

    require_once( 'payment-token.php' );

    $sp_token = new WC_Payment_Token_SimplePayment();
    $sp_token->set_token( isset( $token[ 'token' ] ) ? $token[ 'token' ] : $transaction[ 'transaction_id' ] );
    $sp_token->set_gateway_id( 'simple-payment' );
    $sp_token->set_user_id( 0 < $user_id ? $user_id : get_current_user_id() );
    //if ( $default ) $sp_token->set_default( $default );
    if ( $default || !( $sp_token->get_user_id() && !WC_Payment_Tokens::get_customer_default_token( $sp_token->get_user_id() ) ) ) $sp_token->set_default( true );

    // TODO: determin
    // TODO: add support for card type
    // $sp_token->set_card_type( 'visa' ); // $transaction[ SimplePayment::CARD_TYPE] );
    if ( !empty( $engine ) ) $sp_token->set_engine( $engine );
    if ( !empty( $token[ SimplePaymentPlugin::CARD_NUMBER ] ) ) $sp_token->set_last4( substr( $token[ SimplePaymentPlugin::CARD_NUMBER ], -4, 4 ) );
    if ( !empty( $token[ SimplePaymentPlugin::CARD_EXPIRY_MONTH ] ) ) $sp_token->set_expiry_month( $token[ SimplePaymentPlugin::CARD_EXPIRY_MONTH ] );
    if ( !empty( $token[ SimplePaymentPlugin::CARD_EXPIRY_YEAR ] ) ) $sp_token->set_expiry_year( $token[ SimplePaymentPlugin::CARD_EXPIRY_YEAR ] );
    if ( !empty( $token[ SimplePaymentPlugin::CARD_OWNER ] ) ) $sp_token->set_owner_name( $token[ SimplePaymentPlugin::CARD_OWNER ] );
    if ( !empty( $token[ SimplePaymentPlugin::CARD_OWNER_ID ] ) ) $sp_token->set_owner_id( $token[ SimplePaymentPlugin::CARD_OWNER_ID ] );
    if ( !empty( $token[ SimplePaymentPlugin::CARD_CVV ] ) ) $sp_token->set_cvv( $token[ SimplePaymentPlugin::CARD_CVV ] );
    if ( $token_id = $sp_token->save() ) do_action( 'sp_woocommerce_added_payment_method', $sp_token, $token );
    return( $token_id );
}
add_action( 'sp_creditcard_token', 'sp_wc_save_token' );


add_action( 'plugins_loaded', 'sp_wc_gateway_init', 11 );

function sp_wc_maybe_failed_order( ) {
	global $SPWP;
    if ( $payment_id = ( isset( $_REQUEST[ SimplePaymentPlugin::PAYMENT_ID ] ) ? $_REQUEST[ SimplePaymentPlugin::PAYMENT_ID ] : null ) ) {
        if ( $url = $_REQUEST[ 'redirect_url' ] ) {
            parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
            if ( !isset( $params[ 'order-pay' ] ) || !$params[ 'order-pay' ] ) return;
            $order = wc_get_order( $params[ 'order-pay' ] );
            SimplePaymentPlugin::instance();
            // TODO: should validate the payment id status instead of the url param
            $ops = $_REQUEST[ $SPWP::SPOP ] ?? ( $_REQUEST[ $SPWP::SPOP ] ?? false );
            if ( in_array( $ops, [ SimplePaymentPlugin::OPERATION_SUCCESS ] ) ) {
                $order->add_order_note( __( 'Important consult payment status before processing.', 'simple-payment' ) );
                $url = add_query_arg( 'payment_id', $payment_id, $url );
            } else {
                $order->add_order_note( __( 'Payment failed, redirecting user to checkout.', 'simple-payment' ) );
                $url = $order->get_checkout_payment_url( true );
				$target = '_top';
                SimplePaymentPlugin::redirect( $url, $target );
                die;
            }
            // TODO: consider using SimplePaymentPlugin::redirect()
            wp_redirect( $url );
            die;
        } // 
    }
}
add_action( 'wc_ajax_checkout', 'sp_wc_maybe_failed_order', 5 );

add_action( 'woocommerce_admin_order_data_header_right', function( $order ) {
    $payment_id = $order->get_meta( '_sp_transaction_code' );
    $confirmation_code = $order->get_meta( '_sp_confirmation_code' );
    if ( $payment_id ) {
        echo '<p><strong>' . __( 'Simple Payment ID:', 'simple-payment' ) . '</strong> ' . esc_html( $payment_id ) . '</p>';
    }
    if ( $confirmation_code ) {
        echo '<p><strong>' . __( 'Confirmation Code:', 'simple-payment' ) . '</strong> ' . esc_html( $confirmation_code ) . '</p>';
    }
} );

function sp_wc_gateway_init() {
    
    require_once( 'payment-token.php' );

	class WC_SimplePayment_Gateway extends WC_Payment_Gateway_CC {

        protected $SPWP;
        protected static $_instance = null;

        protected $instructions = null;
        
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
            $supports = [ 'products', 'subscriptions', 'refunds',  'default_credit_card_form' ]; // add_payment_method
 
            $this->SPWP = SimplePaymentPlugin::instance();
			$this->id = 'simple-payment';
            $this->icon = apply_filters( 'woocommerce_offline_icon', '' );
            $engine = $this->get_option( 'engine' ) ? $this->get_option( 'engine' ) : null;
			if ( $this->SPWP->supports( 'cvv', $engine ) ) {
                $this->has_fields = true;
                //$supports[] = 'credit_card_form_cvc_on_saved_method';
            }
			$this->method_title = __( 'Simple Payment', 'simple-payment' );
			$this->method_description = __( 'Allows integration of Simple Payment gateways into woocommerce', 'simple-payment' );
            if ( $this->SPWP->supports( 'cvv', $engine ) ) {
                $supports[] = 'tokenization';
            }

            $this->supports = apply_filters( 'sp_woocommerce_supports', $supports, $engine ); 
            
            // TODO: consider taking these values from the specific engine; tokenization, subscriptions

            // TODO: credit_card_form_cvc_on_saved_method - add this to support when CVV is not required on tokenized cards - credit_card_form_cvc_on_saved_method
            // TODO: tokenization- in order to support tokinzation consider using the javascript
            $this->new_method_label = __( 'new payment method', 'simple-payment' );

            // Load the settings.
			$this->init_form_fields();            
            $this->init_settings();
            
			// Define user set variables
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );
		  
			// Simple Payment actions:
			
            // Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
			add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
          
            if ( !$this->has_fields || in_array( $this->get_option( 'display' ), [ 'iframe', 'modal' ] ) ) add_action( 'woocommerce_receipt_'.$this->id, [ &$this, 'provider_step' ] );
            add_action( "woocommerce_api_{$this}", [ $this, 'gateway_response' ] );
            
            // Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

            /** @since 1.2.0 */
            add_filter( 'woocommerce_available_payment_gateways', 				array( $this, 'selected_payment_gateways' ) );
            add_filter( 'woocommerce_get_customer_payment_tokens', 				array( $this, 'selected_customer_payment_token' ), 10, 3 );
            add_filter( 'wc_payment_gateway_form_saved_payment_methods_html',  	array( $this, 'payment_methods_html' ) );
         
            add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'wc_get_account_saved_payment_methods_list_item' ] , 10, 2 );

            add_filter( 'woocommerce_payment_token_class', function( $classname, $type ) {
                return( in_array( $type, [ 'simple-payment', 'SimplePayment' ] ) ? WC_Payment_Token_SimplePayment::class : $classname );
            }, 50, 2 );
            add_filter( version_compare( WC()->version, '3.0.9', '>=' ) ? 'woocommerce' : 'wocommerce' . '_credit_card_type_labels', array( $this, 'credit_card_type_labels' ) );
            
            if ( !$this->has_fields && !$this->description && $this->get_option( 'in_checkout' ) == 'yes' ) {
                // Setting some value so it will go into the payment_fields() function
                $this->description = ' ';
            }

            add_action( 'sp_payment_status', function( $params ) {
                $params = SimplePaymentPlugin::instance()->fetch( $params[ 'payment_id' ] );
                if ( $params[ 'source' ] == 'woocommerce' && $params[ 'confirmation_code' ] && $url = $_REQUEST[ 'redirect_url' ] ) {
                    $this->gateway_response( $params[ 'source_id' ], false );
                }
            } );

            add_filter( 'woocommerce_credit_card_form_fields', [ $this, 'fields' ], 50, 2 );
        }
    
        public function needs_setup() {
            return( false );
        }

        public function is_available() {
            // Do not offer the gateway at checkout unless it is enabled in WooCommerce.
            if ( 'yes' !== $this->enabled ) return( false );
            return( parent::is_available() );
        }

        function wc_get_account_saved_payment_methods_list_item( $item, $payment_token ) {
			if ( 'simplepayment' !== strtolower( $payment_token->get_type() ) ) {
				return( $item );
			}
			$card_type = $payment_token->get_card_type();
			$item[ 'method' ][ 'last4' ] = $payment_token->get_last4();
			$item[ 'method' ][ 'brand' ] = ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Credit card', 'woocommerce' ) );
			$item[ 'expires' ] = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), -2 );
			return( $item );
		}

        public function fields( $default_fields, $id ) {
            if ( $id != $this->id ) return( $fields );
            $fields = [
                'card-name-field' => '<p class="form-row form-row-first">
                    <label for="' . esc_attr( $this->id ) . '-card-name">' . esc_html__( 'Name on card', 'simple-payment' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->id ) . '-card-name" class="input-text wc-credit-card-form-card-name" inputmode="text" autocomplete="cc-name" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="" ' . $this->field_name
                    ( 'card-name' ) . ' />
                </p>',
                'card-owner-id-field' => '<p class="form-row form-row-last">
                    <label for="' . esc_attr( $this->id ) . '-card-owner-id">' . esc_html__( 'Card Owner ID', 'simple-payment' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->id ) . '-card-owner-id" class="input-text wc-credit-card-form-card-owner-id" inputmode="numeric" autocomplete="cc-owner-id" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="" ' . $this->field_name( 'card-owner-id' ) . ' />
                </p>',
            ];

            $default_fields = array_merge( $fields, $default_fields );
            $installments = $this->get_option( 'installments' ) == 'yes' ? $this->SPWP->param( 'installments_default' ) : false;
            $installments_min = $this->SPWP->param( 'installments_min' );
            $installments_max = $this->SPWP->param( 'installments_max' );
            if ( isset( $installments ) && $installments
                 && isset( $installments_min ) && $installments_min 
                && isset( $installments_max ) && $installments_max && $installments_max > 1 ) {
                    $options = '';
                    for ( $installment = $installments_min; $installment <= $installments_max; $installment++ ) $options .= '<option' . selected( $installments, $installment, false ) . '>' . $installment . '</option>';
                    $fields = [
                        'card-insallments-field' => '<p class="form-row form-row-first">
                            <label for="' . esc_attr( $this->id ) . '-card-payments">' . esc_html__( 'Installments', 'simple-payment' ) . '</label>
                            <select id="' . esc_attr( $this->id ) . '-card-payments" class="input-text wc-credit-card-form-card-name" inputmode="text" autocomplete="cc-payments" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="" ' . $this->field_name
                            ( 'payments' ) . ' />' . $options . '</select>
                        </p>' ];
                $default_fields = array_merge( $default_fields, $fields );
            }
            return( $default_fields );
        }

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
            $engines = [ '' => 'Default' ];
            foreach (SimplePaymentPlugin::$engines as $engine) $engines[ $engine] = $engine;
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
                    'options' => [ '' => 'Default', 'iframe' => 'IFRAME', 'modal' => 'Modal', 'redirect' => 'redirect' ],
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
					'default'     => __('WooCommerce Order %s', 'simple-payment' ),
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
        function provider_step( $order_id ) {
            $order = wc_get_order( $order_id );
            $params = self::params( $order->get_data() );

            $url = get_post_meta( (int) $order_id, 'sp_provider_url', true );
            wc_delete_order_item_meta( (int) $order_id, 'sp_provider_url' );

            $settings = $this->get_option( 'settings' ) ? json_decode( $this->get_option( 'settings' ), true, 512, JSON_OBJECT_AS_ARRAY ) : [];
            if ( $settings ) $params = array_merge( $settings, $params );
            $params[ 'method' ] = 'direct_open';

            $params[ 'type' ] = 'form';
            $params[ 'form' ] = 'plugin-addon';
            $params[ 'url' ] = $url;
            $params[ 'display' ] = $this->get_option('display' );

            set_query_var('display', $this->get_option('display' ) );
            set_query_var('settings', $params);
            print $this->SPWP->checkout( $params);
        }

        public function gateway_response( $order_id = null, $redirect = true ) {
            if ( !$order_id ) {
                $order_key = $_REQUEST[ 'key' ];
                $order_id = $_REQUEST[ 'order-pay' ];
                if ( !$order_id ) {
                    return;
                }
            }
            if ( !$order_id ) return;
            $order = wc_get_order( $order_id );
            // TODO: Maybe here check if the payment was approved
            if ( !$order = wc_get_order( $order_id ) ) return;

            $payment_id = $_REQUEST[ SimplePaymentPlugin::PAYMENT_ID ] ?? false; // get payment id
            if ( !$payment_id ) {
                $last_payment = get_transient( 'sp-payment-last' );
                $payment_id = $last_payment[ SimplePaymentPlugin::PAYMENT_ID ] ?? false;
            }
            if ( !empty( $payment_id ) && $order->get_customer_id() ) {
				sp_wc_save_token( $payment_id, null, null, $order->get_customer_id() );
            }

            $payment = SimplePaymentPlugin::instance()->fetch( $payment_id );
            $order->update_meta_data( '_sp_transaction_id', $payment_id );
            $order->update_meta_data( '_sp_confirmation_code', $payment[ 'confirmation_code' ] );
            $order->update_meta_data( '_sp_transaction_code', $payment[ 'transaction_id' ] );

            // TODO: validate if it was success??
            if ( $payment[ 'confirmation_code' ] ) {
                $order->payment_complete( $payment_id );
            } else {
                $order->add_order_note( sprintf( __( 'Payment failed.', 'simple-payment' ), $payment_id ) );
            }
            if ( !$redirect ) {
                return;
            }    //
            // $order->payment_failed();

           // Remove cart.
            if ( function_exists( 'wc_empty_cart' ) ) {
                wc_empty_cart();
            }
            
            // TODO: consider using SPWP::redirect()
            $target = isset( $_REQUEST[ 'target' ] ) ? $_REQUEST[ 'target' ] : '';
            $targets = explode( ':', $target );
            $target = $targets[ 0 ];
            $url = $this->get_return_url( $order );
            switch ( $target ) {
                case '_top':
                  echo '<html><head><script type="text/javascript"> top.location.replace("' . $url . '"); </script></head><body></body</html>'; 
                  break;
                case '_parent':
                  echo '<html><head><script type="text/javascript"> parent.location.replace("' . $url . '"); </script></head><body></body</html>'; 
                  break;
                case 'javascript':
                  $script = $targets[ 1 ];
                  echo '<html><head><script type="text/javascript"> ' . $script . ' </script></head><body></body</html>'; 
                  break;
                case '_blank':
                  break;
                case '_self':
                default:
                    if ( true || headers_sent() )  // It cannot use redirect when it is IFRAME as it seems since it loses cookies
                        echo '<html><head><script type="text/javascript"> location.replace("' . $url . '"); </script></head><body></body</html>'; 
                    else wp_redirect( $url, 303 );
            }
            die();
            return;
            //WC()->session->save_payment_method 	= null;
		    //WC()->session->selected_token_id 	= null;

            //$order->get_checkout_order_received_url(); //$this->get_return_url( $order); //$order->get_checkout_order_received_url();
            //$params["SuccessRedirectUrl"] = untrailingslashit(home_url() ).'?wc-api=WC_Gateway_Cardcom&'.('cardcomListener=cardcom_successful&order_id='.$order_id);
/*
            $order->payment_complete( $this->SPWP->engine->transaction);
            wc_reduce_stock_levels( $order_id);
			WC()->cart->empty_cart();*/
            /*$raw_data = json_decode( WC_Pelecard_API::get_raw_data(), true );
            $transaction = new WC_Pelecard_Transaction( null, $raw_data );
            $order_id = $transaction->get_order_id();
            if ( ! $order_id && isset( $raw_data[ 'ResultData' ][ 'TransactionId' ] ) ) {
                $transaction_id = wc_clean( $raw_data[ 'ResultData' ][ 'TransactionId' ] );
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
			if ( $this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
    
        /**
         * Enqueues our tokenization script to handle some of the new form options.
         */

         // TODO: support tokenization for credit cards inline.

        public function tokenization_script() {
            return;
            wp_enqueue_script(
                'woocommerce-tokenization-form',
                SPWP_PLUGIN_URL.'addons/woocommerce/js/tokenization-form' . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
                array( 'jquery' ),
                SimplePaymentPlugin::$version
            );
            
            //wp_enqueue_script('simple-payment-checkoutscript',
            //    SPWP_PLUGIN_URL.'addons/woocommerce/js/checkout.js' ),
            //    [ 'jquery' ],
            //    SimplePaymentPlugin::$version
            //);

            //wp_enqueue_script( 'wc-credit-card-form' );
        }
        
        // TODO: it requires description to enter here... consider what to do.
        public function payment_fields() {
            $description = $this->get_description();
            if ( $description ) {
                echo wpautop( wptexturize( $description ) );
            }
            if ( !$this->has_fields ) {
                if ( !isset( $_POST['woocommerce_pay'], $_GET['key'] ) && in_array( $this->get_option( 'display' ), [ 'iframe', 'modal' ] ) && $this->get_option( 'in_checkout' ) == 'yes' ) {
                    $params = json_decode( $this->get_option( 'settings' ), true, 512, JSON_OBJECT_AS_ARRAY );
                    $params[ 'woocommerce_show_checkout' ] = true;
                    $params[ 'display' ] = $this->get_option( 'display' );
                    if ( $this->get_option( 'display' ) == 'iframe' ) echo '<div sp-data="container"></div>';
                    echo '<script> var sp_settings = ' . json_encode( $params, true ) . '; </script>';
                    wp_enqueue_script( 'simple-payment-woocommerce-checkout-script',
                        SPWP_PLUGIN_URL . 'addons/woocommerce/js/simple-payment-woocommerce-checkout.js',
                        [ 'jquery' ],
                        SimplePaymentPlugin::$version
                    );
                    $this->SPWP->scripts();
                }
                return;
            }
            set_query_var( 'installments', $this->get_option( 'installments' ) == 'yes' );
            $template = $this->get_option( 'template' ) ? : null;
            if ( $template ) {
                if ( $override_template = locate_template( $template.'.php' ) ) load_template( $override_template );
                else load_template( SPWP_PLUGIN_DIR . '/templates/' . $template.'.php' );
            } else parent::payment_fields();         
        }

        public function validate_fields() {
            $ok = parent::validate_fields();
            $is_token = $_REQUEST[ 'wc-simple-payment-payment-token' ] ?? false;
            if ( !$is_token && $this->has_fields  ) {
                $params = self::params( [], $_REQUEST );
                $validations = $this->SPWP->validate( $params );
                foreach ( $validations as $key => $description ) {
                    wc_add_notice( sprintf( __( 'Payment error: %s', 'simple-payment' ), __( $description, 'simple-payment' ), $key ), 'error' );
                    $ok = false;
                }
            }
            return( $ok );
        }
		

		public function add_payment_method( $params = [] ) {
			try {
				$params = self::params( $params, $_REQUEST );
				if ( $user_id = get_current_user_id() ) {
					$user = get_userdata( get_current_user_id() ); // CARD_OWNER
					if ( !isset( $params[ SimplePaymentPlugin::EMAIL ] ) ) $params[ SimplePaymentPlugin::EMAIL ] = $user->user_email;
				
					if ( !isset( $params[ SimplePaymentPlugin::FIRST_NAME ] ) ) $params[ SimplePaymentPlugin::FIRST_NAME ] = $user->first_name;
					if ( !isset( $params[ SimplePaymentPlugin::LAST_NAME ] ) ) $params[ SimplePaymentPlugin::LAST_NAME ] = $user->last_name;
					if ( !isset( $params[ SimplePaymentPlugin::FULL_NAME ] ) ) $params[ SimplePaymentPlugin::FULL_NAME ] = $user->user_nicename;
				}
				if ( isset( $params[ SimplePaymentPlugin::FULL_NAME ] ) && trim( $params[ SimplePaymentPlugin::FULL_NAME ] ) ) {
					$names = explode( ' ', $params[ SimplePaymentPlugin::FULL_NAME ] );
					$first_name = $names[ 0 ];
					$last_name = substr( $params[ SimplePaymentPlugin::FULL_NAME ], strlen( $first_name ) );
					if ( !isset($params[ SimplePaymentPlugin::FIRST_NAME ] ) || !trim( $params[ SimplePaymentPlugin::FIRST_NAME ] ) ) $params[ SimplePaymentPlugin::FIRST_NAME ] = $first_name;
					if ( !isset( $params[ SimplePaymentPlugin::LAST_NAME ] ) || !trim( $params[ SimplePaymentPlugin::LAST_NAME ] ) ) $params[ SimplePaymentPlugin::LAST_NAME ] = $last_name;
				}
				if ( !isset( $params[ SimplePaymentPlugin::FULL_NAME ] ) && ( isset($params[ SimplePaymentPlugin::FIRST_NAME ] ) || isset( $params[ SimplePaymentPlugin::LAST_NAME ] ) ) ) $params[ self::FULL_NAME ] = trim( ( isset( $params[ SimplePaymentPlugin::FIRST_NAME ] ) ? $params[ SimplePaymentPlugin::FIRST_NAME ] : '' ) . ' ' . ( isset($params[ SimplePaymentPlugin::LAST_NAME ] ) ? $params[ SimplePaymentPlugin::LAST_NAME ] : '' ) );
				if ( !isset( $params[ SimplePaymentPlugin::CARD_OWNER ] ) && isset( $params[ SimplePaymentPlugin::FULL_NAME ] ) ) $params[ SimplePaymentPlugin::CARD_OWNER ] = $params[ SimplePaymentPlugin::FULL_NAME ];
				

				$transaction = $this->SPWP->store( $params );
				//$this->save_token( $transaction );
				return( [
					'success' => true,
					'result' => 'success', // success, redirect
					'redirect' => wc_get_endpoint_url( 'payment-methods' ),
				] );
			} catch ( Exception $e ) {
				wc_add_notice( sprintf( __( 'Add payment method error: %s', 'simple-payment' ), __( $e->getMessage(), 'simple-payment' ) ), 'error' );
				return( [
					'success' => false,
					'result'   => 'failure', // success, redirect
					'error' => $e->getCode(),
					'message' => $e->getMessage(),
				] );
			}
		}
        /*
        $token = new WC_Payment_Token_CC();
        $token->set_token( (string)( $ipn_get_response->TransactionToken) );
        $token->set_gateway_id( 'icredit_payment' ); 
        $token->set_card_type('כרטיס אשראי' );
        $token->set_last4( (string)(substr( $json_response->CardNumber,12) ));
        $token->set_expiry_month( (string)$cardmm );
        $token->set_expiry_year( '20'.(string)$cardyy);
        $token->set_user_id( $ipn_get_response->Custom4);
        $token->save();   
        */

        public static function params( $params, $data = [] ) {
            $gateway = self::instance();

            if ( isset( $data[ 'billing' ] ) ) $params = array_merge( $params, $data[ 'billing' ] );
            if ( !isset( $data[ SimplePaymentPlugin::PRODUCT ] ) ) $params[ SimplePaymentPlugin::PRODUCT ] = $gateway->product( $data );
            if ( isset( $data[ 'total' ] ) ) $params[ SimplePaymentPlugin::AMOUNT ] = $data[ 'total' ];
            if ( isset( $data[ 'company' ] ) ) $params[ SimplePaymentPlugin::TAX_ID ] = $data[ 'company' ];
            if ( isset( $data[ 'postcode' ] ) ) $params[ SimplePaymentPlugin::ZIPCODE ] = $data[ 'postcode' ];
            if ( isset( $data[ 'address_1' ] ) ) $params[ SimplePaymentPlugin::ADDRESS ] = $data[ 'address_1' ];
            if ( isset( $data[ 'address_2' ] ) ) $params[ SimplePaymentPlugin::ADDRESS2 ] = $data[ 'address_2' ];
            if ( isset( $data[ 'first_name' ] ) ) $params[ SimplePaymentPlugin::FIRST_NAME ] = $data[ 'first_name' ];
            if ( isset( $data[ 'last_name' ] ) ) $params[ SimplePaymentPlugin::LAST_NAME ] = $data[ 'last_name' ];
           // if ( isset( $data[ 'xcid' ] ) ) $params[ SimplePaymentPlugin::LAST_NAME ] = $data[ 'last_name' ];

            // TODO: support product_code
            if ( $gateway->has_fields ) { 
                // TODO: when tokenized we do not have this value
                if ( !isset( $params[ SimplePaymentPlugin::CARD_OWNER ] ) || !$params[ SimplePaymentPlugin::CARD_OWNER ] ) $params[ SimplePaymentPlugin::CARD_OWNER ] = $data[ $gateway->id . '-card-owner' ];

                if ( isset( $data[ $gateway->id.'-card-owner-id' ] ) ) $params[ SimplePaymentPlugin::CARD_OWNER_ID ] = $data[ $gateway->id . '-card-owner-id' ];
                if ( !isset( $params[ SimplePaymentPlugin::CARD_OWNER ] ) ) $params[ SimplePaymentPlugin::CARD_OWNER ] = $data[ 'first_name' ] . ' ' . $data[ 'last_name' ];
                if ( !isset( $params[ SimplePaymentPlugin::CARD_OWNER ] ) || !$params[ SimplePaymentPlugin::CARD_OWNER ] ) $params[ SimplePaymentPlugin::CARD_OWNER ] = $data[ 'billing_first_name' ] . ' ' . $data[ 'billing_last_name' ];
                if ( isset( $data[ $gateway->id.'-card-number' ] ) ) $params[ SimplePaymentPlugin::CARD_NUMBER ] = str_replace( ' ', '', $data[ $gateway->id . '-card-number' ] );
                if ( isset( $data[ $gateway->id.'-card-cvc' ] ) ) {
                    $params[ SimplePaymentPlugin::CARD_CVV ] = $data[ $gateway->id . '-card-cvc' ];
                    $expiry = $data[ $gateway->id . '-card-expiry' ];
                    $expiry = explode( '/', $expiry );
					$expiry = array_map( 'intval', $expiry );
                    $century = intval( floor( date( 'Y' ) / 1000 ) * 1000 );
                    $params[ SimplePaymentPlugin::CARD_EXPIRY_MONTH ] = $expiry[ 0 ];
                    $expiry[ 1 ] = $expiry[ 1 ];
                    $params[ SimplePaymentPlugin::CARD_EXPIRY_YEAR ] = strlen( $expiry[ 1 ] ) < 4 ? ( $century + $expiry[ 1 ] ) : $expiry[ 1 ];
                }
            }
            return( $params );
        }

        public function payment_methods_html( $html ) {
            global $wp;
            if ( ! is_checkout_pay_page() ) {
                return $html;
            }
            $order_id = absint( $wp->query_vars[ 'order-pay' ] );
            $order = wc_get_order( $order_id );
            $payments = array(
                'MaxPayments'			=> $this->SPWP->param('installments_min' ),
                'MinPayments'			=> $this->SPWP->param('installments_max' ),
            //    'MinPaymentsForCredit'	=> $this->mincredit
            );
            if ( $payments[ 'MaxPayments' ] !== $payments[ 'MinPayments' ] || 1 == $payments[ 'MinPayments' ] ) {
                //$html .= wc_get_template_html( 'checkout/number-of-payments.php', array( 'payments' => $payments ), null, WC_Pelecard()->plugin_path() . '/templates/' );
            }
            return( $html );
        }

        protected function product( $params ) {
            $product = sprintf( $this->get_option( 'product' ), isset( $params[ 'number' ] ) ? $params[ 'number' ] : $params[ 'id' ] ); 
            if ( isset( $params[ 'line_items' ] ) && count( $params[ 'line_items' ] ) == 1 && $this->get_option( 'single_item_use_name' ) == 'yes' ) {
                $product = array_shift( $params[ 'line_items' ] );
                $product = $product->get_name();
            } 
            return( $product ? : $params[ 'id' ] );
        }

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id, $amount = 0, $use_tokens = false ) {
            $order = wc_get_order( $order_id );
            $engine = $this->get_option( 'engine' ) ? : null;

            $params = self::params( [], array_merge( $order->get_data(), isset( $_REQUEST ) ? $_REQUEST : [] ) );
            $params[ 'source' ] = 'woocommerce';
            $params[ 'source_id' ] = $order_id;
            if ( $amount ) $params[ SimplePaymentPlugin::AMOUNT ] = $amount;
            if ( isset( $_REQUEST[ 'wc-simple-payment-payment-token' ] ) && $_REQUEST[ 'wc-simple-payment-payment-token' ] ) {
				$tokens = [ $_REQUEST[ 'wc-simple-payment-payment-token' ] ];
			} elseif ( $use_tokens ) {
                // TODO: verify if token payment or cc is sent.
                $tokens = array_merge( WC_Payment_Tokens::get_order_tokens( $order_id ), ( $order->get_customer_id() ? [ WC_Payment_Tokens::get_customer_default_token( $order->get_customer_id() ) ] : [] ) );
			}
            if ( $transaction_id = $order->get_meta_data( '_sp_transaction_id' ) ) $params[ 'transaction_id' ] = $transaction_id; 
            if ( isset( $tokens ) && count( $tokens ) ) {
                $wc_token = is_object( $tokens[ 0 ] ) ? $tokens[ 0 ] : WC_Payment_Tokens::get( $tokens[ 0 ] );
                if ( $wc_token ) {
                    $token = [];
                    $token[ 'token' ] = $wc_token->get_token();
                    
                    // TODO: add expiration of token to remove unecessary tokens
                    $token[ SimplePaymentPlugin::CARD_OWNER ] = $wc_token->get_owner_name();
                    $token[ SimplePaymentPlugin::CARD_EXPIRY_MONTH ] = $wc_token->get_expiry_month();
                    $token[ SimplePaymentPlugin::CARD_EXPIRY_YEAR ] = $wc_token->get_expiry_year();
                    $token[ SimplePaymentPlugin::CARD_OWNER_ID ] = $wc_token->get_owner_id();
                    $token[ SimplePaymentPlugin::CARD_NUMBER ] = $wc_token->get_last4();
                    $token[ SimplePaymentPlugin::CARD_CVV ] = $wc_token->get_cvv();
                    $token[ 'engine' ] = $wc_token->get_engine();
                    $params[ 'token' ] = $token;
                }
            }
            try {
                //get_checkout_order_received_url, get_cancel_order_url_raw
                $settings = $this->get_option( 'settings' ) ? json_decode( $this->get_option( 'settings' ), true, 512, JSON_OBJECT_AS_ARRAY ) : [];
                if ( $settings ) $params = array_merge( $settings, $params );
                
                $params[ 'redirect_url' ] = WC()->api_request_url( "{$this}" );

                if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) $params[ 'redirect_url' ] = add_query_arg( 'order', $order_id, add_query_arg( 'key', $order->get_order_key(), $params[ 'redirect_url' ] ) );   
                else $params[ 'redirect_url' ] = add_query_arg([ 'order-pay' => $order_id], add_query_arg( 'key', $order->get_order_key(), $params[ 'redirect_url' ] ) );

                if ( in_array( $this->get_option( 'display' ), [ 'iframe', 'modal' ] ) ) {
                    $params[ 'redirect_url' ] = add_query_arg( [ 'target' => '_top' ], $params[ 'redirect_url' ] );
                }
                $params = apply_filters( 'sp_wc_payment_args', $params, $order_id );
                if ( isset( $params[ 'engine' ] ) && $params[ 'engine' ] ) $engine  = $params[ 'engine' ];
                $url = $external = $this->SPWP->payment( $params, $engine );
                if ( !is_bool( $url ) ) {
                    // && !add_post_meta((int) $order_id, 'sp_provider_url', $url, true) 
                    update_post_meta( (int) $order_id, 'sp_provider_url', $url );
                 }

                if ( !$this->has_fields && in_array( $this->get_option( 'display' ), [ 'iframe', 'modal' ] ) ) {
                    if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) $url = add_query_arg( 'order', $order_id, add_query_arg( 'key', $order->get_order_key(), get_permalink( woocommerce_get_page_id('pay' ) ) ) );
                    else $url = add_query_arg( [ 'order-pay' => $order_id ], add_query_arg( 'key', $order->get_order_key(), $order->get_checkout_payment_url( true ) ));
                }
                //    if (version_compare(WOOCOMMERCE_VERSION, '2.2', '<' ) ) $url = add_query_arg('order', $order_id, add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay' ) )) );   
                //    else $url = add_query_arg([ 'order-pay' => $order_id], add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url() ));
                //}
                if ( !is_bool( $url ) ) {
                    //return(true);
                    //wp_redirect( $url);
                    // echo "<script>window.top.location.href = \"$url\";</script>";
                    //die;
                    
                    return( [
                        'result' => 'success',
                        'redirect' => !$this->has_fields && !isset( $_POST['woocommerce_pay'], $_GET['key'] ) && in_array( $this->get_option( 'display' ), [ 'iframe', 'modal' ] ) && $this->get_option( 'in_checkout' ) == 'yes' ? '#' . $external : $url,
                        'external' => $external,
                        'messages' => '<div></div>'
                    ] );
                }
            } catch ( Exception $e ) {
                $this->SPWP->error( $params, $e->getCode(), $e->getMessage() );
                $transaction_link = '<a href="' . esc_url( sprintf( $this->view_transaction_url, $this->SPWP->payment_id ) ) . '" target="_blank">' . esc_html( $this->SPWP->payment_id ) . '</a>';
                $order->add_order_note( sprintf( __( 'Payment error [ SP ID: %s ]: %s', 'simple-payment' ), $transaction_link, __( $e->getMessage(), 'simple-payment' ) ) );
                return( [
                    'result' => 'failure',
                    'messages' => '<div>' . $e->getMessage() . '</div>'
                ] );
            }
            /*try {
                // TODO: check the need here? it should be somewhere elsewhere
                //if ( $url !== false) $url = $this->SPWP->post_process( $_REQUEST, $engine);
            } catch (Exception $e) {
                $this->SPWP->error( $params, $e->getCode(), $e->getMessage() );
                wc_add_notice( __('Payment error :', 'simple-payment' ) . $e->getMessage(), 'error' );
                return;
            }*/
			// Mark as on-hold (we're awaiting the payment)
        
            $order->update_meta_data( '_sp_transaction_id', $this->SPWP->payment_id );

            if ( $url === true ) {
                $order->payment_complete( $this->SPWP->payment_id );
                $payment = SimplePaymentPlugin::instance()->fetch( $this->SPWP->payment_id );
                $order->update_meta_data( '_sp_confirmation_code', $payment[ 'confirmation_code' ] );
                $order->update_meta_data( '_sp_transaction_code', $payment[ 'transaction_id' ] );
                // Remove cart.
                if ( isset( WC()->cart ) ) {
                    WC()->cart->empty_cart();
                }
            } else {
               // $order->payment_failed();
                $order->add_order_note( sprintf( __( 'Payment error[ SP ID: %s ]: unkown.', 'simple-payment' ), $this->SPWP->payment_id ) );
            }
            if ( is_callable( [ $order, 'save' ] ) ) {
                $order->save();
            }
			return( [
                'result' => $url === true  ? 'success' : 'failure', // failure
                'redirect'=> $url === true ? $this->get_return_url( $order ) : ( $checkout_page_id = wc_get_page_id( 'checkout' ) ? get_permalink( $checkout_page_id ) : home_url() )
            ] );
        }
        
		// TODO: support tokenization for credit cards inline, like icount
		public function field_name( $name ) {
			// $this->supports( 'tokenization' ) ? '' :
			return( ' name="' . esc_attr( $this->id . '-' . $name ) . '" ' );
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
			return( $tokens );
		}
    
		/**
         * Display selected payment gateway on checkout pay page.
         *
         * @param  array $available_gateways
         * @return array
         */
		public function selected_payment_gateways( $available_gateways ) {
			$is_checkout_pay_page = is_checkout_pay_page();
			if ( $is_checkout_pay_page && isset( $available_gateways[ $this->id ] ) ) {
				$gateway = $available_gateways[ $this->id ];
				$gateway->order_button_text = apply_filters( 'sp_wc_order_button_text', __( 'Pay for order', 'simple-payment' ), $is_checkout_pay_page );
				if ( ! empty( WC()->session->selected_token_id ) ) {
					$available_gateways = array( $this->id => $gateway );
				}
			}
			return( $available_gateways );
		}

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );
            $params = [];
            $params[ SimplePaymentPlugin::PRODUCT ] = get_the_title( $order->get_id() );
            $params[ SimplePaymentPlugin::AMOUNT ] = $amount;
            // TODO: need to raise exception when fails
            return( SimplePaymentPlugin::instance()->payment_refund( $order->get_transaction_id(), $params ) );
        }

		/**
         * Add credit card types and labels.
         *
         * @param  array $labels
         * @return array
         */
		public function credit_card_type_labels( $labels ) {
			return( array_merge( $labels, apply_filters( 'sp_wc_credit_card_type_labels', [
				'visa' 		=> __( 'Visa', 'simple-payment' ),
				'mastercard' 		=> __( 'Mastercard', 'simple-payment' ),
				'american express' 	=> __( 'American Express', 'simple-payment' )
			] ) ) );
		}

        /*
        <div class="col-md-4 mb-3">
        <?php if (isset( $installments) && $installments && isset( $installments_min) && $installments_min && isset( $installments_max) && $installments_max && $installments_max > 1) { ?>
        <label for="payments"><?php _e('Installments', 'simple-payment' ); ?></label>
        <select class="custom-select d-block w-100 form-control" id="payments" name="<?php echo esc_attr( $SPWP::PAYMENTS ); ?>" required="">
          <?php for ( $installment = $installments_min; $installment <= $installments_max; $installment++ ) echo '<option' . selected( $installments, $installment, true ) . '>' . $installment . '</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Number of Installments is required.', 'simple-payment' ); ?>
        </div>
        <?php } ?>
      </div>
    </div>
    <?php if (isset( $owner_id) && $owner_id) { ?>
    <div class="row form-row">
      <div class="col-md-6 mb-3">
        <label for="cc-card-owner-id"><?php _e('Card Owner ID', 'simple-payment' ); ?></label>
        <input type="text" class="form-control" id="cc-card-owner-id" name="<?php echo esc_attr( $SPWP::CARD_OWNER_ID ); ?>" placeholder="">
        <small class="text-muted"><?php _e('Document ID as registered with card company', 'simple-payment' ); ?></small>
        <div class="invalid-feedback">
          <?php _e('Card owner Id is required or invalid.', 'simple-payment' ); ?>
        </div>
      </div>
    </div>
    <?php } ?>
    */
  }

}

/* ============================================================================
 * Companion (incoming orders) handling.
 *
 * When THIS WooCommerce store is the third party that RECEIVES purchases as
 * orders, created via the WooCommerce REST API by a Simple Payment "WooCommerce"
 * engine running on another (source) website, this companion:
 *   1. exposes / validates / keeps the sp_* meta data sent with the order,
 *   2. on a successful payment, optionally skips "processing" and sets the order
 *      directly to "completed" (when the setting is enabled),
 *   3. redirects the customer back to the originating site once the order is
 *      paid correctly (breaking out of the iframe / popup).
 * ==========================================================================*/

// The meta keys the engine sends along with the remote order.
function sp_wc_incoming_meta_keys() {
    return( apply_filters( 'sp_wc_incoming_meta_keys', [
        'sp_return_url', 'sp_success_url', 'sp_cancel_url', 'sp_error_url',
        'sp_status_url', 'sp_payment_id', 'sp_source', 'sp_product', 'sp_product_code',
    ] ) );
}

function sp_wc_incoming_enabled() {
    return( (bool) SimplePaymentPlugin::param( 'wc_incoming.enabled' ) );
}

function sp_wc_incoming_autocomplete_enabled() {
    return( (bool) SimplePaymentPlugin::param( 'wc_incoming.autocomplete' ) );
}

function sp_wc_incoming_redirect_enabled() {
    return( (bool) SimplePaymentPlugin::param( 'wc_incoming.redirect' ) );
}

// Whether a given order originated from a Simple Payment "WooCommerce" engine.
function sp_wc_incoming_is( $order ) {
    if ( !$order || !is_a( $order, 'WC_Order' ) ) return( false );
    if ( 'yes' === $order->get_meta( '_sp_incoming' ) ) return( true );
    return( (bool) ( $order->get_meta( 'sp_source' ) || $order->get_meta( 'sp_payment_id' )
        || $order->get_meta( 'sp_return_url' ) || $order->get_meta( 'sp_success_url' ) ) );
}

function sp_wc_incoming_is_url_key( $key ) {
    return( '_url' === substr( $key, -4 ) );
}

// Resolve a meta value from an incoming REST request (sp_ key, plain key or meta_data).
function sp_wc_incoming_request_value( $request, $key ) {
    if ( isset( $request[ $key ] ) && '' !== $request[ $key ] ) return( $request[ $key ] );
    $plain = preg_replace( '/^sp_/', '', $key );
    if ( $plain !== $key && isset( $request[ $plain ] ) && '' !== $request[ $plain ] ) return( $request[ $plain ] );
    $meta = isset( $request[ 'meta_data' ] ) ? $request[ 'meta_data' ] : null;
    if ( is_array( $meta ) ) {
        foreach ( $meta as $m ) {
            $mk = is_array( $m ) ? ( $m[ 'key' ] ?? null ) : ( is_object( $m ) ? ( $m->key ?? null ) : null );
            $mv = is_array( $m ) ? ( $m[ 'value' ] ?? null ) : ( is_object( $m ) ? ( $m->value ?? null ) : null );
            if ( $mk === $key && null !== $mv && '' !== $mv ) return( $mv );
        }
    }
    return( null );
}

function sp_wc_incoming_sanitize( $key, $value ) {
    return( sp_wc_incoming_is_url_key( $key ) ? esc_url_raw( $value ) : sanitize_text_field( $value ) );
}

// 1a. Register the sp_* order meta so it is exposed and sanitized on the REST API.
add_action( 'init', function() {
    if ( !sp_wc_incoming_enabled() || !post_type_exists( 'shop_order' ) || !function_exists( 'register_post_meta' ) ) return;
    foreach ( sp_wc_incoming_meta_keys() as $key ) {
        register_post_meta( 'shop_order', $key, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => sp_wc_incoming_is_url_key( $key ) ? 'esc_url_raw' : 'sanitize_text_field',
            'auth_callback' => function() { return( current_user_can( 'edit_shop_orders' ) ); },
        ] );
    }
}, 20 );

// 1b. Receive, validate and keep the sp_* values when an order is created via REST.
add_action( 'woocommerce_rest_insert_shop_order_object', 'sp_wc_incoming_rest_insert', 10, 3 );
function sp_wc_incoming_rest_insert( $order, $request, $creating ) {
    if ( !sp_wc_incoming_enabled() || !is_a( $order, 'WC_Order' ) ) return;
    $found = false;
    foreach ( sp_wc_incoming_meta_keys() as $key ) {
        // Prefer a value already stored by WooCommerce (sent as meta_data), then the request.
        $value = $order->get_meta( $key );
        if ( '' === $value || null === $value ) $value = sp_wc_incoming_request_value( $request, $key );
        if ( null === $value || '' === $value ) continue;
        $value = sp_wc_incoming_sanitize( $key, $value );
        if ( '' === $value ) continue;
        $order->update_meta_data( $key, $value );
        $found = true;
    }
    if ( $found ) {
        $order->update_meta_data( '_sp_incoming', 'yes' );
        // The source site cannot know which gateways exist here, so assign a
        // payment method the companion actually has (or let the customer choose).
        sp_wc_incoming_assign_payment_method( $order );
        $order->save();
        do_action( 'sp_wc_incoming_order_created', $order, $request );
    }
}

// Registered payment gateways on this (companion) site.
function sp_wc_incoming_gateways() {
    $list = [];
    if ( !function_exists( 'WC' ) || !WC()->payment_gateways() ) return( $list );
    foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
        $list[ $gateway->id ] = $gateway;
    }
    return( $list );
}

// Assign the gateway chosen for incoming orders (or normalise an unknown one).
function sp_wc_incoming_assign_payment_method( $order ) {
    $gateways = sp_wc_incoming_gateways();
    $chosen = SimplePaymentPlugin::param( 'wc_incoming.payment_method' );
    $chosen = apply_filters( 'sp_wc_incoming_payment_method', $chosen, $order, $gateways );
    if ( $chosen && isset( $gateways[ $chosen ] ) ) {
        // set_payment_method() with a gateway object sets both the id and title.
        $order->set_payment_method( $gateways[ $chosen ] );
        return;
    }
    // No explicit choice: if the source sent a method this site does not have,
    // clear it so the remote payment page cleanly lets the customer choose.
    $current = $order->get_payment_method();
    if ( $current && !isset( $gateways[ $current ] ) ) {
        $order->set_payment_method( '' );
        $order->set_payment_method_title( '' );
    }
}

// 2. On a successful payment, skip "processing" and go directly to "completed".
add_filter( 'woocommerce_payment_complete_order_status', function( $status, $order_id, $order = null ) {
    if ( !sp_wc_incoming_enabled() || !sp_wc_incoming_autocomplete_enabled() ) return( $status );
    $order = $order ? : wc_get_order( $order_id );
    if ( sp_wc_incoming_is( $order ) ) return( 'completed' );
    return( $status );
}, 20, 3 );

// 2b. Fallback: complete orders that reached "processing" without the filter above.
add_action( 'woocommerce_order_status_processing', function( $order_id, $order = null ) {
    if ( !sp_wc_incoming_enabled() || !sp_wc_incoming_autocomplete_enabled() ) return;
    $order = $order ? : wc_get_order( $order_id );
    if ( sp_wc_incoming_is( $order ) ) $order->update_status( 'completed', __( 'Auto completed by Simple Payment companion.', 'simple-payment' ) );
}, 20, 2 );

// Log a companion diagnostic line (WooCommerce > Status > Logs, source sp-companion).
// Enabled when WP_DEBUG is on, or via the sp_wc_incoming_debug filter.
function sp_wc_incoming_log( $message ) {
    if ( !apply_filters( 'sp_wc_incoming_debug', defined( 'WP_DEBUG' ) && WP_DEBUG ) ) return;
    if ( function_exists( 'wc_get_logger' ) ) wc_get_logger()->info( $message, [ 'source' => 'sp-companion' ] );
    else error_log( '[sp-companion] ' . $message );
}

// The url to send the customer back to, based on the order outcome. Reaching the
// order-received page means the order was placed, so we always return a url:
// success/return when paid or pending, cancel/error only for those statuses.
function sp_wc_incoming_return_url( $order ) {
    if ( $order->has_status( 'cancelled' ) ) {
        $url = $order->get_meta( 'sp_cancel_url' ) ? : $order->get_meta( 'sp_return_url' );
    } elseif ( $order->has_status( 'failed' ) ) {
        $url = $order->get_meta( 'sp_error_url' ) ? : $order->get_meta( 'sp_return_url' );
    } elseif ( $order->is_paid() ) {
        $url = $order->get_meta( 'sp_success_url' ) ? : $order->get_meta( 'sp_return_url' );
    } else {
        // pending / on-hold: return the customer neutrally; the source verifies later.
        $url = $order->get_meta( 'sp_return_url' ) ? : $order->get_meta( 'sp_success_url' );
    }
    return( apply_filters( 'sp_wc_incoming_redirect_url', $url, $order ) );
}

// Resolve the order shown on the current order-received page (query var or key).
function sp_wc_incoming_received_order() {
    if ( !function_exists( 'is_order_received_page' ) || !is_order_received_page() ) return( false );
    global $wp;
    $order_id = absint( isset( $wp->query_vars[ 'order-received' ] ) ? $wp->query_vars[ 'order-received' ] : 0 );
    $key = isset( $_GET[ 'key' ] ) ? wc_clean( wp_unslash( $_GET[ 'key' ] ) ) : '';
    if ( !$order_id && $key && function_exists( 'wc_get_order_id_by_order_key' ) ) $order_id = wc_get_order_id_by_order_key( $key );
    if ( !$order_id ) return( false );
    $order = wc_get_order( $order_id );
    if ( !$order ) return( false );
    // Validate the order key when present, so we only redirect the genuine buyer.
    if ( $key && !hash_equals( $order->get_order_key(), $key ) ) return( false );
    return( $order );
}

// 3. Redirect the customer back to the originating site once the order is paid.
// Primary: template_redirect (clean server redirect before output).
add_action( 'template_redirect', 'sp_wc_incoming_maybe_redirect', 20 );
function sp_wc_incoming_maybe_redirect() {
    if ( is_admin() ) return;
    if ( !sp_wc_incoming_enabled() ) return;
    if ( !sp_wc_incoming_redirect_enabled() ) return;
    $order = sp_wc_incoming_received_order();
    if ( !$order ) return; // not an order-received page
    sp_wc_incoming_maybe_redirect_order( $order, 'template_redirect' );
}

// Fallback: some themes / gateways reach the thank-you page without matching the
// order-received query var at template_redirect. woocommerce_thankyou always
// fires there with the order id (redirect happens via JS since output started).
add_action( 'woocommerce_thankyou', 'sp_wc_incoming_thankyou_redirect', 1 );
function sp_wc_incoming_thankyou_redirect( $order_id ) {
    if ( is_admin() ) return;
    if ( !sp_wc_incoming_enabled() || !sp_wc_incoming_redirect_enabled() ) return;
    $order = wc_get_order( $order_id );
    if ( !$order ) return;
    sp_wc_incoming_maybe_redirect_order( $order, 'thankyou' );
}

// Shared decision + logging for the return redirect.
function sp_wc_incoming_maybe_redirect_order( $order, $where ) {
    $id = $order->get_id();
    if ( !sp_wc_incoming_is( $order ) ) {
        sp_wc_incoming_log( "$where: order $id is not an incoming Simple Payment order (no sp_* meta) - not redirecting" );
        return;
    }
    $url = sp_wc_incoming_return_url( $order );
    if ( !$url ) {
        sp_wc_incoming_log( "$where: order $id (status={$order->get_status()}) has no sp_success_url / sp_return_url meta - not redirecting" );
        return;
    }
    sp_wc_incoming_log( "$where: order $id (status={$order->get_status()}) redirecting to $url" );
    sp_wc_incoming_break_out( $url );
}

// Redirect the customer back to the source site. Navigates the current window
// only (does not force window.top), so an iframe / popup stays in control of
// its own frame and the source callback decides any further target.
function sp_wc_incoming_break_out( $url ) {
    $url = esc_url_raw( $url );
    if ( !$url ) return;
    $url = apply_filters( 'sp_wc_incoming_break_out_url', $url );
    // Use a client-side redirect (meta refresh + JS) rather than wp_redirect: it
    // works cross-domain regardless of security plugins that filter wp_redirect,
    // and navigates the current window only.
    if ( !headers_sent() ) nocache_headers();
    $json = wp_json_encode( $url );
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . esc_attr( $url ) . '">';
    echo '<title>' . esc_html__( 'Redirecting…', 'simple-payment' ) . '</title>';
    echo '<script type="text/javascript">window.location.replace(' . $json . ');</script>';
    echo '</head><body><a href="' . esc_url( $url ) . '">' . esc_html__( 'Continue', 'simple-payment' ) . '</a></body></html>';
    exit;
}

// 4. Suppress WooCommerce emails for incoming (outsourced) orders when configured.
// WooCommerce order emails, split by audience.
function sp_wc_incoming_customer_email_ids() {
    return( apply_filters( 'sp_wc_incoming_customer_email_ids', [
        'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order',
        'customer_refunded_order', 'customer_invoice', 'customer_note',
    ] ) );
}
function sp_wc_incoming_admin_email_ids() {
    return( apply_filters( 'sp_wc_incoming_admin_email_ids', [
        'new_order', 'cancelled_order', 'failed_order',
    ] ) );
}

// Gate each email via woocommerce_email_enabled_{id}; the order is passed in, so
// only orders that originated from the source site are affected.
foreach ( sp_wc_incoming_customer_email_ids() as $sp_wc_email_id ) {
    add_filter( 'woocommerce_email_enabled_' . $sp_wc_email_id, function( $enabled, $object ) {
        return( sp_wc_incoming_filter_email( $enabled, $object, 'customer' ) );
    }, 100, 2 );
}
foreach ( sp_wc_incoming_admin_email_ids() as $sp_wc_email_id ) {
    add_filter( 'woocommerce_email_enabled_' . $sp_wc_email_id, function( $enabled, $object ) {
        return( sp_wc_incoming_filter_email( $enabled, $object, 'admin' ) );
    }, 100, 2 );
}
unset( $sp_wc_email_id );

function sp_wc_incoming_filter_email( $enabled, $object, $audience ) {
    if ( !$enabled || !sp_wc_incoming_enabled() ) return( $enabled );
    if ( !is_a( $object, 'WC_Order' ) || !sp_wc_incoming_is( $object ) ) return( $enabled );
    $flag = 'admin' === $audience ? 'wc_incoming.disable_admin_emails' : 'wc_incoming.disable_customer_emails';
    return( SimplePaymentPlugin::param( $flag ) ? false : $enabled );
}

// Companion settings, shown on the WooCommerce tab of the Simple Payment settings.
add_filter( 'sp_admin_sections', function( $sections ) {
    $sections[ 'wc_incoming_settings' ] = [
        'title' => __( 'WooCommerce Incoming Orders (Companion)', 'simple-payment' ),
        'description' => __( 'When this store receives purchases as orders from a remote Simple Payment "WooCommerce" engine, configure how paid orders are completed and how the customer is returned to the originating site.', 'simple-payment' ),
        'section' => 'woocommerce'
    ];
    return( $sections );
} );

add_filter( 'sp_admin_settings', function( $settings ) {
    $settings[ 'wc_incoming.enabled' ] = [
        'title' => __( 'Enable Companion Handling', 'simple-payment' ),
        'type' => 'check',
        'section' => 'wc_incoming_settings',
        'description' => __( 'Receive and keep the sp_* parameters sent with REST orders and act on them.', 'simple-payment' )
    ];
    $settings[ 'wc_incoming.autocomplete' ] = [
        'title' => __( 'Auto Complete Paid Orders', 'simple-payment' ),
        'type' => 'check',
        'section' => 'wc_incoming_settings',
        'description' => __( 'On successful payment, skip Processing and set the order directly to Completed.', 'simple-payment' )
    ];
    $settings[ 'wc_incoming.redirect' ] = [
        'title' => __( 'Redirect Back After Payment', 'simple-payment' ),
        'type' => 'check',
        'section' => 'wc_incoming_settings',
        'description' => __( 'Once the order is paid, return the customer to the originating site (success / return url).', 'simple-payment' )
    ];
    $options = [ '' => __( 'Let the customer choose on the payment page', 'simple-payment' ) ];
    foreach ( sp_wc_incoming_gateways() as $id => $gateway ) {
        $title = $gateway->get_method_title() ? : $gateway->get_title();
        $options[ $id ] = ( $title ? $title : $id ) . ' (' . $id . ')';
    }
    $settings[ 'wc_incoming.payment_method' ] = [
        'title' => __( 'Incoming Orders Payment Method', 'simple-payment' ),
        'type' => 'select',
        'options' => $options,
        'section' => 'wc_incoming_settings',
        'description' => __( 'Gateway to assign to orders received from the source site (which cannot know this store\'s gateways). Leave on "Let the customer choose" to present the available gateways on the payment page.', 'simple-payment' )
    ];
    $settings[ 'wc_incoming.disable_customer_emails' ] = [
        'title' => __( 'Disable Customer Emails', 'simple-payment' ),
        'type' => 'check',
        'section' => 'wc_incoming_settings',
        'description' => __( 'Do not send WooCommerce customer emails for orders received from the source site (outsourced payment requests).', 'simple-payment' )
    ];
    $settings[ 'wc_incoming.disable_admin_emails' ] = [
        'title' => __( 'Disable Admin Emails', 'simple-payment' ),
        'type' => 'check',
        'section' => 'wc_incoming_settings',
        'description' => __( 'Do not send WooCommerce admin / store emails (new order, cancelled, failed) for orders received from the source site.', 'simple-payment' )
    ];
    return( $settings );
} );