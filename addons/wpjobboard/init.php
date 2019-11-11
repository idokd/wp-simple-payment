<?php

// Make sure WPJobBoard is active
if (!in_array('wpjobboard/index.php', apply_filters('active_plugins', get_option('active_plugins')))) 
	return;

//add_action('init', 'sp_wpjb_init');
sp_wpjb_init();
function sp_wpjb_init() {

    add_filter('wpjb_payments_list', 'sp_wpjb_payment');

    function sp_wpjb_payment($list) {
        global $wpjobboard;
    
        //include_once dirname(__FILE__)."/CashMod.php";
        //include_once dirname(__FILE__)."/CashModForm.php";
        require_once('payment-gateway.php');

        $sp = new Wpjb_Payment_SimplePayment;
        $list[$sp->getEngine()] = get_class($sp);
        return($list);
    }

}