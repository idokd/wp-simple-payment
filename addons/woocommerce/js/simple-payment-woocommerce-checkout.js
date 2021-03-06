( function( $ ) {
    var SimplePaymentWooCommerce = {
        payment_method: 'simple-payment',
        hook_level: 'place_order_success', // Options: place_order, place_order_success // place_order - currently not supported
        $checkout_form: $( 'form.checkout' ),

        init: function() {
            this.$checkout_form.on('checkout_place_order_' + this.payment_method, this.place_order);
            if ('place_order' != this.hook_level) this.$checkout_form.on('checkout_place_order_success', this.place_order_success);
            this.$checkout_form.on(this.payment_method + '_frame_loaded', this.scroll_to);
            this.$checkout_form.on(this.payment_method + '_frame_loaded', this.form_unblock);
            return(this);
        },

        scroll_to: function(o) {
            $.scroll_to_notices($('[sp-data="container"]'));
        },

        form_unblock: function() {
            $( 'form.checkout' ).removeClass( 'processing' ).unblock();
			$( 'form.checkout' ).find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
        },

        place_order: function() {
            SimplePaymentWooCommerce.form_unblock();
            if (!SimplePayment.params['woocommerce_show_checkout']) return;
            if (!(SimplePayment.params['display'] == 'iframe' ||
                SimplePayment.params['display'] == 'modal')) return;

            $(document).ajaxSuccess(SimplePaymentWooCommerce.place_order_success);
            if ('place_order' != this.hook_level) return;
            // TODO: if wish to hook on place_order, process all information here.
            return(false);
        },

        place_order_success: function(event, xhr, options, result) {
            if (!SimplePayment.params['woocommerce_show_checkout']) return;
            if (!(SimplePayment.params['display'] == 'iframe' ||
                SimplePayment.params['display'] == 'modal')) return;
            if ('checkout_place_order_success' == event.type) return(true); // false for checkout error, true to process redirect
            if (!options || !result || options['url'] != wc_checkout_params.checkout_url) return;
            if ('failure' === result.result) return(true);
            // We are using error on original checkout process, we might consider using success
            $(document.body).on('checkout_error', this.scroll_to );

            SimplePayment.pre(null, result.external);
            $( 'form.checkout' ).trigger(SimplePaymentWooCommerce.payment_method + '_frame_loaded');
            return(false);
        }

    };
    $(document).on('simple_payment_init', function() {
        return(false); // Do not continue with the rest of simple payment init
    });
    this.SimplePaymentWooCommerce = SimplePaymentWooCommerce.init();
})(jQuery);