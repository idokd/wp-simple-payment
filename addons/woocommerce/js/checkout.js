// overide from  WC_Payment_Gateway tokenization_script()
//Enqueues our tokenization script to handle some of the new form options.
! function(e) {
    e(function() {
        var o = function() {
            return function(o) {
                var t = e(o),
                    n = t.closest(".payment_box"),
                    c = this;
                this.onTokenChange = function() {
                    "new" === e(this).val() ? (c.showForm(), c.showSaveNewCheckbox()) : (c.hideForm(), c.hideSaveNewCheckbox())
                },
                this.onCreateAccountChange = function() {
                    e(this).is(":checked") ? c.showSaveNewCheckbox() : c.hideSaveNewCheckbox()
                },
                this.onDisplay = function() {
                    0 === e(":input.woocommerce-SavedPaymentMethods-tokenInput:checked", t).length &&
                    e(":input.woocommerce-SavedPaymentMethods-tokenInput:last", t).prop("checked", !0),
                    0 === t.data("count") &&
                    e(".woocommerce-SavedPaymentMethods-new", t).hide(),
                    e(":input.woocommerce-SavedPaymentMethods-tokenInput:checked", t).trigger("change"),
                    e("input#createaccount").length &&
                    !e("input#createaccount").is(":checked") &&
                    c.hideSaveNewCheckbox()
                },
                this.hideForm = function() {
                    e(".wc-payment-form", n).hide();
                    //cardcom validation form
                    e(".payment_method_cardcom_validation",n).show();
                },
                this.showForm = function() {
                    e(".wc-payment-form", n).show();
                    ////cardcom validation form
                    e(".payment_method_cardcom_validation",n).hide();
                },
                this.showSaveNewCheckbox = function() {
                    e(".woocommerce-SavedPaymentMethods-saveNew", n).show()
                },
                this.hideSaveNewCheckbox = function() {
                    e(".woocommerce-SavedPaymentMethods-saveNew", n).hide()
                },
                e(":input.woocommerce-SavedPaymentMethods-tokenInput", t).change(this.onTokenChange),
                e("input#createaccount").change(this.onCreateAccountChange),
                this.onDisplay()
            }
        }();
        e(document.body).on("updated_checkout wc-credit-card-form-init",
            function() {
                e("ul.woocommerce-SavedPaymentMethods").each(
                    function() {
                        new o(this)
                    })
            })
    })
}(jQuery);