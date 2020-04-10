( function( $ ) {
    var SimplePaymentWooCommerce = {
        payment_method: 'simple-payment',
        hook_level: 'place_order_success', // Options: place_order, place_order_success // place_order - currently not supported
        $checkout_form: $( 'form.checkout' ),

        init: function() {
            this.$checkout_form.on('checkout_place_order_' + this.payment_method, this.place_order);
            if ('place_order' != this.hook_level) this.$checkout_form.on('checkout_place_order_success', this.place_order_success);
        },

        place_order: function() {
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
            if ('checkout_place_order_success' == event.type) return(false);
            if (!options || !result || options['url'] != wc_checkout_params.checkout_url) return;
            if ('failure' === result.result) return(true);
            SimplePayment.pre(null, result.external);
            return(false);
        }

    };
    $(document).on('simple_payment_init', function() {
        this.SimplePaymentWooCommerce = SimplePaymentWooCommerce.init();
        return(false);
    });
})(jQuery);