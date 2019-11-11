<?php

class Wpjb_Payment_SimplePayment extends Wpjb_Payment_Abstract {

    public function __construct(Wpjb_Model_Payment $data = null) {
        $this->_default = array(
            "disabled" => "0"
        );
        $this->_data = $data;
    }
    
    public function getEngine() {
        return "SimplePayment";
    }
    
    public function getForm() { // TODO: implements form
        return "Wpjb_Form_Payment_Stripe"; 
    }
    
    public function getFormFrontend() {
        return("Wpjb_Form_Payment_Stripe"); // TODO: 
    }
    
    public function getTitle() {
        return("Simple Payment");
    }

    //processTransaction(array $post, array $get) - return null if all is ok
    // otherwise throw an exception

    public function processTransaction() {        
        \Stripe\Stripe::setApiKey($this->conf("secret_key")); 
        
        $this->maybeSaveCC();
                
        $payment_intent_id = Daq_Request::getInstance()->getParam('payment_intent_id');
        //$customer_id = get_user_meta( $payment->post_author, '??', true );
        $intent = \Stripe\PaymentIntent::retrieve( $payment_intent_id );
        
        // Proceed only if status succeeded
        if( $intent->status !== "succeeded" ) {
        exit (-2);
        }
        
        $cArr = Wpjb_List_Currency::getByCode($intent->currency);
        $pricing = new Wpjb_Model_Pricing($this->_data->pricing_id);
        
        return array(
            "external_id"   => $payment_intent_id,
            'is_recurring'  => $pricing->meta->is_recurring->value(),
            "paid"          => ( $intent->amount_received / pow( 10, $cArr["decimal"] ) ),
        );

    }
    
    public function bind(array $post, array $get) {
        $this->setObject(new Wpjb_Model_Payment($post["id"]));
        parent::bind($post, $get);
    }
    
    public function render() {
        $request = Daq_Request::getInstance();
        $form = $request->post( "form" );
        if( $form["_stripe_save_card"] == 1 ) {
            Wpjb_Model_MetaValue::import( "payment", "stripe_save_cc", "pending", $this->getObject()->id );
        }
                
        $html = '';
        //$html.= '<input type="hidden" id="wpjb-stripe-id" value="'.$data["id"].'" />';
        //$html.= '<input type="hidden" id="wpjb-stripe-type" value="'.$data["type"].'" />';
        $html.= '<input type="hidden" id="wpjb-stripe-payment-id" value="'.$this->getObject()->id.'" />';
        
        $html.= '<div class="wpjb-stripe-result">';
        
        $html.= '<div class="wpjb-stripe-pending wpjb-flash-info">';
        $html.= '<div class="wpjb-flash-icon"><span class="wpjb-glyphs wpjb-icon-spinner wpjb-animate-spin"></span></div>';
        $html.= '<div class="wpjb-flash-body">';
        $html.= '<p><strong>'.__("Placing Order", "wpjobboard").'</strong></p>';
        $html.= '<p>'.__("Waiting for payment confirmation ...", "wpjobboard").'</p>';
        $html.= '</div>';
        $html.= '</div>';
        
        $html.= '<div class="wpjb-flash-info wpjb-none">';
        $html.= '<div class="wpjb-flash-icon"><span class="wpjb-glyphs wpjb-icon-ok"></span></div>';
        $html.= '<div class="wpjb-flash-body"></div>';
        $html.= '</div>';
        
        $html.= '<div class="wpjb-flash-error wpjb-none">';
        $html.= '<div class="wpjb-flash-icon"><span class="wpjb-glyphs wpjb-icon-cancel-circled"></span></div>';
        $html.= '<div class="wpjb-flash-body"></div>';
        $html.= '</div>';
        
        $html.= '</div>';
        return $html;
    }
    
    public function getIcon() {
        return('wpjb-icon-credit-card'); // TODO:
    }
    
    public function getIconFrontend() {
        return('wpjb-icon-credit-card'); 
    }
    
}

class Wpjb_Form_Admin_Config_SimplePayment extends Wpjb_Form_Abstract_Payment {
    public function init() {
        parent::init();
        $this->addGroup("my", __("My Group", "wpjobboard"));
        // adding custom textare field to the form
        $e = $this->create("message", "textarea");
        $e->setValue($this->conf("message"));
        $e->setLabel(__("Message", "wpjobboard"));
        $this->addElement($e, "my");
    }
}
