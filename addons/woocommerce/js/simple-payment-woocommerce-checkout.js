( function( $ ) {
    var SimplePaymentWooCommerce = {
        payment_method: 'simple-payment',
        hook_level: 'place_order_success', // Options: place_order, place_order_success // place_order - currently not supported
        $order_review: $( '#order_review' ),
		$checkout_form: $( 'form.checkout' ),
        $form: null,
		$processing: false,
		
        init: function() {            
            this.$form = $( document.body ).hasClass( 'woocommerce-order-pay' ) ? $( '#order_review' ) : $( 'form.checkout' );
            this.$checkout_form.on( 'checkout_place_order_' + this.payment_method, this.place_order );
            if ( 'place_order' != this.hook_level ) this.$checkout_form.on( 'checkout_place_order_success', this.place_order_success );
            this.$checkout_form.on( this.payment_method + '_frame_loaded', this.scroll_to );
            this.$checkout_form.on( this.payment_method + '_frame_loaded', this.form_unblock );
            // TODO: in order to support order-review, payments it is required to attach the submit from checkout.js
            // to the order_review form, or add checkout class to form, but check carefully the impact
        },

        scroll_to: function( o ) {
            $.scroll_to_notices( $ ('[sp-data="container"]' ) );
        },

        form_unblock: function() {
            $( 'form.checkout' ).removeClass( 'processing' ).unblock();
			$( 'form.checkout' ).find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
        },

        place_order: function() {
            console.log( 'SimplePayment place_order' );
            SimplePaymentWooCommerce.form_unblock();
            if ( !SimplePayment.params[ 'woocommerce_show_checkout' ] ) return;
            if ( !( SimplePayment.params[ 'display' ] == 'iframe' ||
                SimplePayment.params[ 'display' ] == 'modal' ) ) return;
			this.processing = true;
            $( document ).ajaxSuccess( SimplePaymentWooCommerce.place_order_success );
            if ( 'place_order' != this.hook_level ) return;
            // TODO: if wish to hook on place_order, process all information here.
            return( false );
        },
 
        place_order_success: function( event, xhr, options, result ) {
            //if ( 'place_order_success' !== this.hook_level ) return;
            if ( !this.processing 
				|| 'checkout_place_order_success' !== event.type
                || !SimplePayment.params[ 'woocommerce_show_checkout' ] 
                || !( SimplePayment.params[ 'display' ] == 'iframe' 
				|| SimplePayment.params[ 'display' ] == 'modal' ) ) return;
			this.processing = false;
            //if ( 'checkout_place_order_success' == event.type ) return( true ); // false for checkout error, true to process redirect
            console.log( 'SimplePayment place_order_success - processing' );
            if ( typeof( result ) == 'undefined' ) { // Adapttion for the WC 7.0 while using AbortContoller and not global ajax
                result = xhr;
            } else {
                if ( !options || !result || options[ 'url' ] != wc_checkout_params.checkout_url ) return;
            }
            if ( 'failure' === result.result ) return( true ); // probably should be false
            // We are using error on original checkout process, we might consider using success
            $( document.body ).on( 'checkout_error', this.scroll_to );

            SimplePayment.pre( null, result.external );
            $( 'form.checkout' ).trigger( SimplePaymentWooCommerce.payment_method + '_frame_loaded' );
            return( false );
        }

    };
    $( document ).on('simple_payment_init', function() {
        return( false ); // Do not continue with the rest of simple payment init
    } );
    this.SimplePaymentWooCommerce = SimplePaymentWooCommerce.init();
 })( jQuery );