(function () {
    'use strict'
    window.addEventListener('load', function () {
      // Fetch all the forms we want to apply custom Bootstrap validation styles to
      var forms = document.getElementsByClassName('needs-validation')
  
      
      // Loop over them and prevent submission
      Array.prototype.filter.call(forms, function (form) {
        setInputFilter(form.elements.namedItem("<?php echo $SPWP::CARD_NUMBER; ?>"), function(value) {
          return /^\d{1,16}$/.test(value);
        });
        setInputFilter(form.elements.namedItem("<?php echo $SPWP::CARD_CVV; ?>"), function(value) {
          return /^\d{1,4}$/.test(value);
        });
        form.addEventListener('submit', function (event) {
          var creditcard = form.elements.namedItem("<?php echo $SPWP::CARD_NUMBER; ?>");
          creditcard.setCustomValidity(!validateCardNumber(creditcard.value) ? 'Invalid Credit Number' : '');
          var month = form.elements.namedItem("<?php echo $SPWP::CARD_EXPIRY_MONTH; ?>");
          var year = form.elements.namedItem("<?php echo $SPWP::CARD_EXPIRY_YEAR; ?>");
          month.setCustomValidity(!validateCardExpires(month.value, year.value) ? 'Invalid Expiry Date' : '');
          if (form.checkValidity() === false) {
            event.preventDefault()
            event.stopPropagation()
          }
          form.classList.add('was-validated')
        }, false)
      })
    }, false)
  }());
  
  function validateCardExpires(month, year) {
    var now = new Date();
    now.setHours(0,0,0,0);
    var expiry = new Date(year, month, 0);
    expiry.setHours(0,0,0,0);
    return(now <= expiry);
  }
  
  function validateCardNumber(number) {
      var regex = new RegExp("^[0-9]{16}$");
      return(regex.test(number) && luhnCheck(number));
  }
  function luhnCheck(val) {
      var sum = 0;
      for (var i = 0; i < val.length; i++) {
          var intVal = parseInt(val.substr(i, 1));
          if (i % 2 == 0) {
              intVal *= 2;
              if (intVal > 9) {
                  intVal = 1 + (intVal % 10);
              }
          }
          sum += intVal;
      }
      return((sum % 10) == 0);
  }
  
  function setInputFilter(textbox, inputFilter) {
    ["input", "keydown", "keyup", "mousedown", "mouseup", "select", "contextmenu", "drop"].forEach(function(event) {
      textbox.oldValue = "";
      textbox.addEventListener(event, function() {
        if (inputFilter(this.value)) {
          this.oldValue = this.value;
          this.oldSelectionStart = this.selectionStart;
          this.oldSelectionEnd = this.selectionEnd;
        } else if (this.hasOwnProperty("oldValue")) {
          this.value = this.oldValue;
          this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
        }
      });
    });
  }