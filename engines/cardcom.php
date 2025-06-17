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


add_filter( 'sp_payment_callback', function( $callback, $params = null ) {
    foreach( sp_callback_params_cardcom() as $key ) 
        $callback = remove_query_arg( $key, $callback );
    return( $callback );
}, 1000, 2 );