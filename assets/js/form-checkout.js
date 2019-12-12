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
        if (form.elements.namedItem("expiry_month")) SimplePayment.validation('expiry_month', [form.elements.namedItem("expiry_month"), form.elements.namedItem("expiry_year")], function(input) {
          var month = input[0]; var year = input[1];
          return(SimplePayment.validExpiry(month.value, year.value));
        });
        if (form.elements.namedItem("expiration"))  SimplePayment.validation('expiration', form.elements.namedItem("expiration"), function(input) {
          var expiration = input.value.split('/');
          var month = expiration[0]; 
          var year = expiration.length > 1 && expiration[1].length <= 2 ? (Math.floor(new Date().getFullYear() / 1000) * 1000) + (expiration[1] * 1) : (expiration.length > 1 ? expiration[1] : '');
          return(SimplePayment.validExpiry(month, year));
        });
        
      })
    }, false)
}());

