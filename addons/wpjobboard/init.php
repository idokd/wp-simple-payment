<?php

// Make sure WPJobBoard is active
if (!in_array('wpjobboard/index.php', apply_filters('active_plugins', get_option('active_plugins')))) 
	return;

//add_action('init', 'sp_wpjb_init');
sp_wpjb_init();
function sp_wpjb_init() {

    add_filter('wpjb_payments_list', 'sp_wpjb_payment');
    add_filter('wpjb_list_currency', 'sp_wpjb_currency');

    function sp_wpjb_currency($list) {
        global $SPWP_CURRENCIES;
        $code = SimplePaymentPlugin::param('currency');
        $top = 0; $exists = false;
        foreach ($list as $key => $currency) {
            $top = $key > $top ? $key : $top;
            if ($currency['code'] == $code) {
                $exists = true;
                break;
            }
        }
        if (!$exists) $list[$top + 1] = [
            'code' => $code,
            'name' => $SPWP_CURRENCIES[$code],
            'symbol' => $code,
            'decimal' => 2
        ];
        return($list);
    }

    function sp_wpjb_payment($list) {
        global $wpjobboard;
    
        //include_once dirname(__FILE__)."/CashMod.php";
        //include_once dirname(__FILE__)."/CashModForm.php";
        require_once('gateway.php');
        require_once('config.php');

        $sp = new Wpjb_Payment_SimplePayment;
        $list[$sp->getEngine()] = get_class($sp);

        return($list);
    }

}