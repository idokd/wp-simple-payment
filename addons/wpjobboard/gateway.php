<?php

class Wpjb_Payment_SimplePayment extends Wpjb_Payment_Abstract {

    protected $SPWP;
    
    public function __construct(Wpjb_Model_Payment $data = null) {
        $this->SPWP = SimplePaymentPlugin::instance();
        $this->_default = array(
            'disabled' => '0'
        );
        $this->_data = $data;
        add_action('sp_payment_success', [$this, 'accept']);
    }
    
    public function getEngine() {
        return('SimplePayment');
    }
    
    public function getForm() { 
        return('Wpjb_Form_Admin_Config_SimplePayment'); 
    }

    //public function getFormFrontend() {
    //    return('Wpjb_Form_Payment_SimplePayment'); 
    //}

    public function getTitle() {
        return('Simple Payment');
    }

    public function accept($params) {
        if (!isset($params['source']) || $params['source'] != 'wpjobboard') return(false);
        if (!isset($params['source_id']) || !$params['source_id']) return(false);

        $arr = [
            'action' => 'wpjb_payment_accept',
            'engine' => $this->getEngine(),
            'id' => $params['source_id'],
            'payment_id' => $params['id'],
            'amount' => $params['amount'],
            'redirect_url' => '',
        ];
        $response = wp_remote_post(admin_url('admin-ajax.php')."?".http_build_query($arr));

        /* TODO: This is how i wished it could be handled (with less workaourund ofcourse),
        but the wp_ajax_nopriv_wpjb_payment_accept - has an exit and die which doesnt allow to handle this
        piece of code
        
        $request = Daq_Request::getInstance();
        $request->addParam('GET', 'engine', $this->getEngine());
        $request->addParam('GET', 'id', $params['source_id']);
        $request->addParam('POST', 'engine', $this->getEngine());
        $request->addParam('POST', 'id', $params['source_id']);

        add_filter('wp_redirect', [$this, 'disableRedirect'], 10, 2);
        ob_start();
        $res = do_action('wp_ajax_nopriv_wpjb_payment_accept');
        $result = ob_get_clean();
        print_r($res);
        remove_filter('wp_redirect', [$this, 'disableRedirect'], 10);
        */


    }

    public function disableRedirect($location, $status) {
        return('');
    }

    public function processTransaction() {    
        // $payment = $this->SPWP->fetch($_REQUEST['payment_id']);
        // TODO: now assuming paid fully. maybe use _get['amount']
        return([
            'echo' => 1,
            'redirect_after' => $this->_get['redirect_url'],
            'external_id'   => $this->_get['payment_id'],
            'is_recurring'  => false, //$pricing->meta->is_recurring->value(),
            'paid'          => $this->_data->payment_sum
        ]);
    }
    
    public function bind(array $post, array $get) {
        $this->setObject(new Wpjb_Model_Payment(isset($post["id"]) ? $post["id"] : $get["id"]));
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
        $product = $this->conf('product');
        $product = sprintf($product, $this->getObject()->id(), get_bloginfo("name"));
        if (!$product) {
            $pricing = new Wpjb_Model_Pricing($this->_data->pricing_id);
            $product = $pricing->title;
        } 
        
        $params = [
            //  Originally expected a 'redirect_url' => admin_url('admin-ajax.php')."?".http_build_query($arr),
            'redirect_url' => $complete,
            'target' => '_top',
            'source' => 'wpjobboard',
            'source_id' => $this->getObject()->id,
            'product' => $product,
            'amount' => $amount,
            'currency' => $this->getObject()->payment_currency,
            'product_code' => 'WPJB'.$this->getObject()->id,
        ];
        $params[SimplePaymentPlugin::FULL_NAME] = $this->getObject()->fullname;
        $params[SimplePaymentPlugin::EMAIL] = $this->getObject()->email;

        $params = apply_filters('sp_wpjb_params', $params, $this);
        $engine = $this->conf('engine') && $this->conf('engine') != 'default' ? $this->conf('engine') : null;
        try {
            $this->SPWP->init(add_query_arg(['target' => '_top'], wp_get_referer()));
            $url = $this->SPWP->payment($params, $engine);
        } catch (Exception $e) {
            // TODO: handle direct errors
        }
        if ($engine) $params['engine'] = $engine;
        $display = SimplePaymentPlugin::supports($this->conf('display'), $engine) ? $this->conf('display') : null;
        if ($display && in_array($display, ['iframe', 'modal'])) {
            //$params['redirect_url'] = add_query_arg(['target' => '_top'], $params['redirect_url']);
            $settings = $this->conf('settings');
            if ($settings) $params = array_merge(json_decode($settings, true, 512, JSON_OBJECT_AS_ARRAY), $params);
            $params['type'] = 'form';
            $params['form'] = 'wpjobboard';
            $params['display'] = $display;
            $params['callback'] = $url;
            $html = $this->SPWP->checkout($params);
            $html.= "<script>
                var form = jQuery('#wpjb-checkout-success');
                form.find('.wpjb-flash-info').remove();
                SimplePayment.submit(".json_encode($params).", '".($params['display'] ? 'sp-'.$params['display'] : '')."');
            </script>";
        } else {
            $html = SimplePaymentPlugin::redirect($url, null, true);
        }
        return($html);
    }
    
    public function getIcon() {
        return('wpjb-icon-credit-card');
    }
    
    public function getIconFrontend() {
        $this->SPWP->scripts();
        return('wpjb-icon-credit-card'); 
    }
    
}
