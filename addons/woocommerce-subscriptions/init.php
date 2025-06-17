<?php

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active

$_active_plugins = array_merge( is_multisite() ? array_keys( get_site_option( 'active_sitewide_plugins', [] ) ) : [], get_option( 'active_plugins', [] ) );

if ( !in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', $_active_plugins ) ) 
	return;

add_action( 'woocommerce_scheduled_subscription_payment_simple-payment', function( $amount_to_charge, $order ) {
    if ( 0 == $amount_to_charge ) {
        $order->payment_complete();
        return;
    }
    $status = ( new WC_SimplePayment_Gateway() )->process_payment( $order->get_id(), $amount_to_charge );

    if ( $status[ 'result' ] == 'success' ) {

        $order_id = $order->get_id();
        $transaction_id = get_post_meta( $order_id, '_sp_transaction_id'. true );
		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = [];
		}
		foreach ( $subscriptions as $subscription ) {
			$subscription_id = $subscription->get_id();
			$subscription->update_meta_data( '_sp_transaction_id', $transaction_id );
               // set last succeful  ;
        // and token id
			$subscription->save();
		}
        
        WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
        // calls: do_action( 'processed_subscription_payments_for_order', $order );
    } else {
        WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
        // calls: do_action( 'processed_subscription_payment_failure_for_order', $order );
    }

	return;


    $SPWP = SimplePaymentPlugin::instance();
    $params = WC_SimplePayment_Gateway::params( [], $order->get_data() );
    $params[ 'source' ] = 'woocommerce';
    $params[ 'source_id' ] = $order->get_id();
    $params[ $SPWP::AMOUNT ] = $amount_to_charge;

    // TODO: fetch token

    	//$payment_id = $SPWP->payment_recharge( apply_filters( 'sp_wc_payment_args', $params, $order->get_id() ), $transaction_id );

    //$subscriptions = wcs_get_subscriptions_for_order();

    //throw new Exception(print_r($order, true));
    // TODO: do we need the last renewal order??
    //$subscription_renewal = wcs_get_objects_property( $order, 'subscription_renewal', 'single' );
    //$order->add_order_note('subscription_renewal :' . $subscription_renewal);

    // TODO: do we have the transaction id on the renweal order? why do we need to fetch it, or otherwise just use
    // the token

    
   // if ( $subscription_renewal ) {
   // 	$subscription = wcs_get_subscription( $subscription_renewal );
	//}
    //$transaction_id = isset( $subscription ) && $subscription && $subscription->get_parent() ? $subscription->get_parent()->get_transaction_id() : null;
	$tokens = array_merge( $order->get_payment_tokens(), WC_Payment_Tokens::get_customer_default_token( $order->get_customer_id() ) );
    $transaction_id = get_post_meta( $order->get_id(), '_sp_transaction_id'. true );
    if ( count( $tokens ) ) {
        $wc_token = WC_Payment_Tokens::get( $tokens[ 0 ] );
        $params[ ] = 
        $transaction[ 'engine' ] = 'YaadPay';
		$transaction[ 'transaction_id' ] = $meta[ '_yaad_cardToken' ][ 'value' ];
		$transaction[ 'token' ] = $meta[ '_yaad_cardToken' ][ 'value' ];
		$transaction[ SimplePaymentPlugin::CARD_EXPIRY_MONTH ] = $meta[ '_yaad_cardMonth' ][ 'value' ];
		$transaction[ SimplePaymentPlugin::CARD_EXPIRY_YEAR ] = $meta[ '_yaad_cardYear' ][ 'value' ];
		$transaction[ SimplePaymentPlugin::CARD_OWNER_ID ] = $meta[ '_yaad_UserId' ][ 'value' ];
    }
    
}, 100, 2 );

add_action( 'woocommerce_subscription_failing_payment_method_updated_simple-payment', function ( $subscription, $renewal_order ) {
    $subscription->update_meta_data( '_sp_transaction_id', $renewal_order->get_meta( '_sp_transaction_id', true ) );
    // TODO: should we get the token from the renewal order?
    $subscription->save();
}, 10, 2 );

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
    return( array_merge( $supports, [ 
        'subscription_suspension', 
        'subscription_cancellation', 
        'subscription_reactivation', 
        'subscription_amount_changes', 
        'subscription_date_changes', 
        'subscription_payment_method_change', 
        'subscription_payment_method_change_admin',
        'multiple_subscriptions'
    ] ));
}, 10, 2 );

function sp_wcs_test( $order ) {
    $order = is_object( $order ) ? $order : wc_get_order( $order_id );
	if ( $subscription_renewal = wcs_get_objects_property( $order, 'subscription_renewal', 'single' ) ) {
        $subscription = wcs_get_subscription( $subscription_renewal );
        $transaction_id = wcs_get_objects_property( $subscription->get_parent(), 'sp_transaction_id', 'single' );
        print_r( $subscription->get_parent() );
        print_r( $subscription_renewal );
        print( $subscription->get_parent()->get_transaction_id() );
    }
    do_action( 'woocommerce_scheduled_subscription_payment_simple-payment', $order->get_total(), $order ); 
    //$subscription = array_pop( $subscription );

   // print($subscription->get_parent_id());

   // print get_post_meta( $order_id, 'sp_transaction_id', true);
    //print_r($subscription);
    //print_r($subscription->get_related_orders());
}


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