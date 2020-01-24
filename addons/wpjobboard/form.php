<?php
/**
 * Description of SimplePayment
 *
 *  */

 // TODO: implement a specific form
 class Wpjb_Form_Payment_SimplePayment extends Daq_Form_Abstract {

    public function __construct($options = array()) {
        $request = Daq_Request::getInstance();
        if (DOING_AJAX && $request->post("action") == 'wpjb_payment_render') {
            add_filter('wpjb_payment_render_response', array($this, 'script'));
        }
        parent::__construct($options);
    }
    
    public function script($response) {
        $response["load"] = array();
        $scripts = wp_scripts()->registered['wpjb-simple-payment'];
        $response["load"][] = $scripts->src."?time=".time();
        return($response);
    }
    
    public function init() {  
        $gateway = new Wpjb_Payment_SimplePayment;
             
        $e = $this->create("full_name");
        $e->setLabel(__("Full Name", 'simple-payment'));
        $e->setRequired(true);
        $this->addElement($e, "default");
        
        $e = $this->create("email");
        $e->setLabel(__("Email", 'simple-payment'));
        $e->setRequired(true);
        $this->addElement($e, "default");
        
        $this->addGroup('simple-payment', __("Credit Card", 'simple-payment'));
        
        $id = get_user_meta(get_current_user_id(), "_wpjb_stripe_customer_id", true);
        $cards = array();
        if ($id) {
            ///if(!class_exists('simple-payment')) {
                //include_once Wpjb_List_Path::getPath("vendor")."/Stripe/Stripe.php";
               // include_once Wpjb_List_Path::getPath("vendor")."/stripe/init.php";
            //}
            $gateway = new Wpjb_Payment_SimplePayment();
            //\Stripe\Stripe::setApiKey($gateway->conf("secret_key"));
            //$customer = \Stripe\Customer::retrieve($id);
            //foreach($customer->sources->data as $cc) {
            //    $cards[] = array(
            //        "id" => $cc->id,
            //        "desc" => sprintf("%s ****-****-****-%s (%s/%s)", $cc->brand, $cc->last4, $cc->exp_month, $cc->exp_year)
            //    );
            //}
        }
        
        $defaults = Daq_Request::getInstance()->post("defaults");
        if( !isset( $defaults['payment_hash'] ) ) {
            $pricing = new Wpjb_Model_Pricing( $defaults['pricing_id'] );
        }
        
        // TODO: support cvv engine types

        $e = $this->create("card_number");
        if( !isset( $pricing ) || $pricing->meta->is_recurring->value() != 1 ) {
            $e->setLabel(__("Card Number", 'simple-payment'));
        } else {
            $e->setLabel(__("Subscribe for plan", 'simple-payment'));
        }
        $e->addClass("wpjb-stripe-cc");
        $e->setAttr("sp-data", "number");
        $e->setRenderer(array($this, "inputStripe"));
        $this->addElement($e, 'simple-payment');
        
        apply_filters("wpjb_form_init_payment_stripe", $this);
    }
    
    public function inputStripe($input) 
    {
        
        $defaults = Daq_Request::getInstance()->post("defaults");
        if( isset( $defaults['payment_hash'] ) ) {
            $payment = Wpjb_Model_Payment::getFromHash( $defaults['payment_hash'] );
            $cArr = Wpjb_List_Currency::getByCode( $payment->payment_currency );
            $amount = ( $payment->payment_sum * pow( 10, $cArr["decimal"] ) - $payment->payment_paid * pow( 10, $cArr["decimal"] ) );
        } else {
            $pricing = new Wpjb_Model_Pricing( $defaults['pricing_id'] );
            $cArr = Wpjb_List_Currency::getByCode( $pricing->currency );
            $amount = ( $pricing->price * pow( 10, $cArr["decimal"] ) );
        }

        if( !isset( $pricing ) || $pricing->meta->is_recurring->value() != 1 ) {
        
            $gateway_customer = get_user_meta( get_current_user_id(), '_wpjb_stripe_customer_id', true ); 
            $gateway_default_card = "";
            $cards = array();
            
            //if (!class_exists('simple-payment')) {
            //    include_once Wpjb_List_Path::getPath("vendor")."/stripe/init.php";
            //}
            
            $gateway = new Wpjb_Payment_SimplePayment();
            \Stripe\Stripe::setApiKey($gateway->conf("secret_key"));
            
            if( get_current_user_id() && $gateway_customer ) {
                $cards = \Stripe\PaymentMethod::all(["customer" => $gateway_customer, "type" => "card"]);
                $customer = \Stripe\Customer::retrieve($gateway_customer);

                if( isset( $customer->invoice_settings->default_payment_method ) && ! empty( $customer->invoice_settings->default_payment_method ) ) {
                    $gateway_default_card = $customer->invoice_settings->default_payment_method;
                } else if( $customer->default_source ) {
                    $gateway_default_card = $customer->default_source;
                }
            }
            
            $intent = $this->getPaymentIntent();

            $this->creditCardField($cards, $intent, $gateway_default_card);
        } else {
            
            $slug = sanitize_title($pricing->title);
            $slug = preg_replace("([^A-z0-9\-]+)", "", $slug);
            
            printf( '<button id="checkout-button">%s</button>', __( "Subscribe", 'simple-payment' ) );
            //echo '<input type="hidden" id="stripe_plan_id" name="stripe_plan_id" value="'.$slug.'" />';
            //echo '<input type="hidden" id="payment_id" name="payment_id" value="0" />';
        }
        

    }
    
    public function getPaymentIntent( ) {
        
        $defaults = Daq_Request::getInstance()->post("defaults");
        if( isset( $defaults['payment_hash'] ) ) {
            $payment = Wpjb_Model_Payment::getFromHash( $defaults['payment_hash'] );
            $cArr = Wpjb_List_Currency::getByCode( $payment->payment_currency );
            
            $currency = strtolower( $cArr['code'] );
            $amount = ( $payment->payment_sum * pow( 10, $cArr["decimal"] ) - $payment->payment_paid * pow( 10, $cArr["decimal"] ) );
        } else {
            $payment = null;
            $pricing = new Wpjb_Model_Pricing( $defaults['pricing_id'] );
            $cArr = Wpjb_List_Currency::getByCode( $pricing->currency );
            
            $currency = strtolower( $cArr['code'] );
            $amount = ( $pricing->price * pow( 10, $cArr["decimal"] ) );
        }
        
        //if( $payment && $payment->meta->stripe_payment_intent_id && $payment->meta->stripe_payment_intent_id->value() ) {
        //    return \Stripe\PaymentIntent::retrieve( $payment->meta->stripe_payment_intent_id->value() );
       // }
        
        $gateway_customer = get_user_meta( get_current_user_id(), '_wpjb_stripe_customer_id', true ); 
        
        $intent_array = array(
            'amount' => $amount,
            'currency' => $currency,
            'setup_future_usage' => 'off_session'
        );
        
        if( $gateway_customer ) {
            $intent_array["customer"] = $gateway_customer;
        }   

        //$intent = \Stripe\PaymentIntent::create( $intent_array );

        //if( $payment ) {
        //    Wpjb_Model_MetaValue::import( "payment", "stripe_payment_intent_id", $intent->id, $payment->id );
        //}
   
        return $intent;
    }
    
    public function creditCardField($cards, $intent, $gateway_default_card ) {
        
        $icons = $this->icons();
        
        ?>
        <div class="wpjb-credit-card-wrap">
            <?php if( get_current_user_id() > 0 && ! empty( $cards->data ) ): ?>

                <div class="wpjb-credit-card-list">
                    <?php foreach( $cards->data as $card ): ?>
                    <div class="wpjb-credit-card-single <?php if($card->id==$gateway_default_card): ?>wpjb-card-is-default<?php endif; ?>">
                        <input type="radio" name="_stripe_card" value="<?php echo esc_attr($card->id) ?>" <?php checked( $card->id, $gateway_default_card ) ?> />

                        <span class="wpjb-glyphs <?php echo $icons[$card->card->brand] ?> wpjb-stripe-cc-icon"></span>

                        <span class="wpjb-stripe-cc-details">
                            <span class="wpjb-stripe-cc-brand"><?php echo $card->card->brand ?></span>
                            <span class="wpjb-stripe-cc-last4">(<?php echo str_repeat( "*", 4 ) . $card->card->last4 ?>)</span>
                            <span class="wpjb-stripe-cc-exp"><?php echo str_pad($card->card->exp_month, 2, "0", STR_PAD_LEFT ) . '/' . substr( $card->card->exp_year, 2) ?></span>
                        </span>

                        <span class="wpjb-stripe-cc-actions">
                            <a href="#" class="wpjb-stripe-cc-actions-default" title="<?php echo esc_attr_e( "Make Default", 'simple-payment' ) ?>"><span class="wpjb-glyphs wpjb-icon-check"></span></a>
                            <a href="#" class="wpjb-stripe-cc-actions-trash" title="<?php echo esc_attr_e( "Delete This Credit Card", 'simple-payment') ?>"><span class="wpjb-glyphs wpjb-icon-trash"></span></a>
                        </span>

                        <span class="wpjb-stripe-cc-actions-trash-confirm">
                            <?php _e( "Delete?", 'simple-payment') ?>
                            <a href="#" class="wpjb-stripe-cc-actions-trash-confirm-yes"><?php _e( "Yes", 'simple-payment' ) ?></a>
                            <a href="#" class="wpjb-stripe-cc-actions-trash-confirm-no"><?php _e( "No", 'simple-payment' ) ?></a>

                        </span>

                        <span class="wpjb-stripe-cc-actions-loader wpjb-glyphs wpjb-icon-spinner wpjb-animate-spin"></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <a href="#" class="wpjb-add-credit-card"><?php _e( "+ Use Different Credit Card", 'simple-payment') ?></a>

                <div class="wpjb-card-details" style="display: none">
                    <div id="card-element" data-secret="<?php echo esc_attr( $intent->client_secret ) ?>" data-newcard="0"></div>

                    <label for="_stripe_save_card" style="margin:0">
                        <input type="checkbox" name="_stripe_save_card" id="_stripe_save_card" value="1" /> 
                        <?php _e( "Save card for later use", 'simple-payment' ) ?>
                    </label>

                    <a href="#" class="wpjb-add-credit-card-cancel"><?php _e( "Cancel", 'simple-payment' ) ?></a>
                </div>

                <div id="card-errors" class="wpjb-card-errors">

                </div>

            <?php else: ?>

                <div class="wpjb-card-details">
                    <div id="card-element" data-secret="<?php echo esc_attr( $intent->client_secret ) ?>" data-newcard="1"></div>

                    <?php if(get_current_user_id() > 0): ?>
                    <label for="_stripe_save_card" style="margin:0">
                        <input type="checkbox" name="_stripe_save_card" id="_stripe_save_card" value="1" /> 
                        <?php _e( "Save card for later use", 'simple-payment' ) ?>
                    </label>
                    <?php endif; ?>

                    <div id="card-errors" class="wpjb-card-errors"></div>
                </div>

            <?php endif; ?>

        </div>
        <?php
    }
    
    public function icons() {
        return array(
            "American Express" => 'adverts-icon-cc-amex',
            "Diners Club" => 'adverts-icon-cc-diners-club',
            "Discover" => 'adverts-icon-cc-discover',
            "JCB" => 'adverts-icon-cc-jcb',
            "MasterCard" => 'adverts-icon-cc-mastercard',
            "UnionPay" => 'adverts-icon-credit-card',
            "Visa" => 'wpjb-icon-cc-visa',
            "Unknown" => 'adverts-icon-credit-card',

            "American Express" => 'adverts-icon-cc-amex',
            "Diners Club" => 'adverts-icon-cc-diners-club',
            "discover" => 'adverts-icon-cc-discover',
            "jcb" => 'adverts-icon-cc-jcb',
            "mastercard" => 'adverts-icon-cc-mastercard',
            "unionpay" => 'adverts-icon-credit-card',
            "visa" => 'wpjb-icon-cc-visa',
            "unknown" => 'adverts-icon-credit-card'
        );
    }
    
    public function inputExpiration()
    {
        $month = new Daq_Form_Element_Text("");
        $month->addClass("wpjb-stripe-cc");
        $month->setAttr("sp-data", "exp-month");
        
        
        $year = new Daq_Form_Element_Text("");
        $year->addClass("wpjb-stripe-cc");
        $year->setAttr("sp-data", "exp-year");
        
        echo '<div class="wpjb-stripe-expiration">'.$month->render() . "<strong>/</strong>" . $year->render().'</div>';
    }
}


?>
