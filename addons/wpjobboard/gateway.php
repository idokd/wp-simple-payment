<?php

class Wpjb_Payment_SimplePayment extends Wpjb_Payment_Abstract {

    protected $SP;

    public function __construct(Wpjb_Model_Payment $data = null) {
        $this->SP = SimplePaymentPlugin::instance();
        $this->_default = array(
            'disabled' => '0'
        );
        $this->_data = $data;
    }
    
    public function getEngine() {
        return('SimplePayment');
    }
    
    public function getForm() { 
        return('Wpjb_Form_Admin_Config_SimplePayment'); 
    }
    
    public function getTitle() {
        return('Simple Payment');
    }

    // processTransaction(array $post, array $get) - return null if all is ok
    // otherwise throw an exception
    public function processTransaction() {        
        /*\Stripe\Stripe::setApiKey($this->conf("secret_key")); 
        
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
        );*/

    }
    
    public function bind(array $post, array $get) {
        $this->setObject(new Wpjb_Model_Payment($post["id"]));
        parent::bind($post, $get);
    }

    public function render() {        
        if ($this->_data->object_type == 1) {
            $complete = wpjb_link_to("home");
        } elseif($this->_data->object_type == 2 && wpjb_conf("urls_cpt")) {
            $complete = get_permalink(wpjb_conf("urls_link_emp_panel"));
        } elseif($this->_data->object_type == 2) {
            $complete = wpjr_link_to("resume", new Wpjb_Model_Resume($this->getObject()->object_id));
        } elseif($this->_data->object_type == 3) {
            $complete = wpjb_link_to("employer_panel");
        } elseif($this->_data->object_type == 3) {
            $complete = wpjr_link_to("myresume_home");
        } else {
            $complete = wpjb_link_to("home");
        }

        $amount = $this->_data->payment_sum-$this->_data->payment_paid;
        $product = sprintf(__('Order %1$s. (%2$s).', "wpjobboard"), $this->getObject()->id(), get_bloginfo("name"));
        $arr = array(
            "action" => "wpjb_payment_accept",
            "engine" => $this->getEngine(),
            "id" => $this->_data->id
        );
        $params = apply_filters("sp_wpjb_data", [
            'notify_url' => admin_url('admin-ajax.php')."?".http_build_query($arr),
            'redirect_url' => $complete,
            'product' => $product,
            'amount' => $amount,
            'type' => 'form',
            'form' => 'wpjobboard',
            'target' => '_top',
            'display' => 'modal',
            'callback' => '/', //$this->SP->payment_page(),
            'currency' => $this->getObject()->payment_currency,
            'product_code' => 'WPJB'.$this->getObject()->id
        ], $this);
        $html = $this->SP->checkout($params);
        $html.= "<script>SimplePayment.submit('".json_encode($params)."', '".($params['display'] ? 'sp-'.$params['display'] : '')."');</script>";
        return($html);
    }
    
    public function getIcon() {
        return('wpjb-icon-credit-card');
    }
    
    public function getIconFrontend() {
        $this->SP->scripts();
        return('wpjb-icon-credit-card'); 
    }
    
}
