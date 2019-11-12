(function () {
    'use strict'
    window.addEventListener('load', function () {
      // Fetch all the forms we want to apply custom Bootstrap validation styles to
      var forms = document.getElementsByClassName('needs-validation')
  
      Array.prototype.filter.call(forms, function (form) {
        SimplePayment.filter(form.elements.namedItem("card_number"), function(value) {
          return /^\d{1,16}$/.test(value);
        });
        SimplePayment.filter(form.elements.namedItem("cvv"), function(value) {
          return /^\d{1,4}$/.test(value);
        });
        SimplePayment.validation('card_number', form.elements.namedItem("card_number"), function(input) {
          return(SimplePayment.validCard(input.value))
        });
        SimplePayment.validation('expiry_month', [form.elements.namedItem("expiry_month"), form.elements.namedItem("expiry_year")], function(input) {
          var month = input[0]; var year = input[1];
          return(SimplePayment.validExpiry(month.value, year.value));
        });
      })
    }, false)
}());

