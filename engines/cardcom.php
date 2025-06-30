<?php

// Due to Cardcom limitation for 500chars on the success, cancel, indicator etc urls we remove parameters that might be long
// a through solution must be found ( TODO )
function sp_callback_params_cardcom() {
    return( apply_filters( 'sp_payment_callback_params_removal', [ 'gf_page', 'gad_source', 'gclid', 'utm_source', 'gbraid' ] ) );
}

add_filter( 'sp_payment_pre_process_filter', function( $params, $engine ) {
    if ( strtolower( $engine::$name ) != 'cardcom' ) return( $params );
    $redirect_url = $params[ 'redirect_url' ];
    foreach( sp_callback_params_cardcom() as $key ) 
        $redirect_url = remove_query_arg( $key, $redirect_url );
    $params[ 'redirect_url' ] = $redirect_url;
    return( $params );
}, 1000, 2 );

/*
add_filter( 'sp_payment_callback', function( $url ) {
	global $SPWP;
	if ( !$SPWP::param( 'cardcom.short_urls' ) ) return( $url );
	$uid = wp_generate_uuid4();
	set_transient( 'sp_' . $uid, $url ); // TODO: should we define expiration/ 5 days?
	//$url = add_query_arg( [ 
	//	$SPWP::SPRD => $SPWP::OPERATION_REDIRECT,
	//	'_spr' => $uid
	//], site_url() );
	return( $url );
}, 5000  );
*/

add_filter( 'gform_simplepayment_return_url', function( $url, $form_id, $lead_id ) {
    global $SPWP;
	if ( !$SPWP::param( 'cardcom.short_urls' ) ) return( $url );
    $pageURL = site_url( '/_gf-sp' );
    $ids_query = "ids={$form_id}|{$lead_id}";
    $ids_query .= '&hash=' . wp_hash( $ids_query );
    $url = remove_query_arg( 'gf_simplepayment_retry', $pageURL );
    $url = add_query_arg( 'gf_simplepayment_return', base64_encode( $ids_query ), $url );
    return( $url );
}, 50, 3 );


add_filter( 'sp_payment_callback', function( $callback ) {
    foreach( sp_callback_params_cardcom() as $key ) 
        $callback = remove_query_arg( $key, $callback );
    return( $callback );
}, 1000 );