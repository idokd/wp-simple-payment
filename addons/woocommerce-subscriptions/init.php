<?php

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active
if ( !in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
	return;

    
add_action( 'woocommerce_scheduled_subscription_payment_simple-payment', function( $amount_to_charge, $order ) {
    if ( 0 == $amount_to_charge ) {
        $order->payment_complete();
        return;
    }
    $SPWP = SimplePaymentPlugin::instance();
    $params = [];
    $params[ $SPWP::AMOUNT ] = $amount_to_charge;
    //throw new Exception(print_r($order, true));
    // TODO: do we need the last renewal order??
    $subscription_renewal = wcs_get_objects_property( $order, 'subscription_renewal', 'single' );
    //$order->add_order_note('subscription_renewal :' . $subscription_renewal);
    $subscription = wcs_get_subscription( $subscription_renewal );
    $transaction_id = $subscription->get_parent()->get_transaction_id();
    $payment_id = $SPWP->payment_recharge( $transaction_id, $params );
    if ( !$payment_id ) {
        WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order ); // , $product_id 
    } else {
        $order->payment_complete( $payment_id );
        WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
    }
}, 100, 2 );


add_filter( 'sp_wc_payment_args', function( $params, $order_id ) {
    if ( !class_exists( 'WC_Subscriptions_Order' ) ) return( $params );
    if ( WC_Subscriptions_Order::order_contains_subscription( $order_id )) {
        // $params['payments'] = 'monthly';
        // $order = wc_get_order( $order_id );
    }
    return( $params );
}, 100, 2 );

add_filter( 'sp_woocommerce_supports', function( $supports, $engine = null ) {
   // TODO: apply this support when it is gateway which handles the renewals: gateway_scheduled_payments
    return( array_merge( $supports, [ 'subscription_suspension', 'subscription_cancellation', 'subscription_reactivation' ] ));
}, 10, 2 );

function sp_wcs_test( $order_id ) {
    $order = wc_get_order( $order_id );
    $subscription_renewal = wcs_get_objects_property( $order, 'subscription_renewal', 'single' );
    $subscription = wcs_get_subscription( $subscription_renewal );
    $transaction_id = wcs_get_objects_property( $subscription->get_parent(), 'sp_transaction_id', 'single' );

    print_r( $subscription->get_parent() );
    //$subscription = array_pop( $subscription );
    print( $subscription_renewal );

    print( $subscription->get_parent()->get_transaction_id() );
   // print($subscription->get_parent_id());

   // print get_post_meta( $order_id, 'sp_transaction_id', true);
    //print_r($subscription);
    //print_r($subscription->get_related_orders());
}
/*
function yg_update_failing_payment_method( $original_order, $new_renewal_order ) {
    update_post_meta( $original_order->id, '_your_gateway_customer_token_id', get_post_meta( $new_renewal_order->id, '_your_gateway_customer_token_id', true ) );
}
add_action( 'woocommerce_subscriptions_changed_failing_payment_method_your_gateway', 'yg_failing_payment_method', 10, 2 );

*/

/*

TODO: Consider supports for the following flags for subscription managment to each engine
    'subscription_cancellation', 
    'subscription_suspension', 
    'subscription_reactivation',
    'subscription_amount_changes',
    'subscription_date_changes',
    'subscription_payment_method_change'
    'subscription_payment_method_change_customer',
    'subscription_payment_method_change_admin',
    'multiple_subscriptions',
*/