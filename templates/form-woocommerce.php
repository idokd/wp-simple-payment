<?php
//var $product, $price, $id, $fixed;
$SPWP = SimplePaymentPlugin::instance();
require_once(SPWP_PLUGIN_DIR.'/settings.php');
$year_today = date('Y'); $year_max = $year_today + 10;
$installments_min = $SPWP->param('installments_min');
$installments_max = $SPWP->param('installments_max');
$installments = $SPWP->param('installments_default');

// TODO: valdate 3 digits (or 4 in american express) cvv and further credit card format
// TODO: Consider adding credit card type
$amount = number_format((float) $amount, 2);

$target = isset($target) ? $target : $SPWP->param('target');
$target = $target ? ' target="'.$target.'"' : '';
?>
<div class="col-md-8 order-md-1" id="simple-payment">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="cc-name"><?php _e('Name on card', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-name" name="<?php echo $SPWP::CARD_OWNER; ?>" placeholder="" required="">
        <small class="text-muted"><?php _e('Full name as displayed on card', 'simple-payment'); ?></small>
        <div class="invalid-feedback">
          <?php _e('Name on card is required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="cc-number"><?php _e('Credit card number', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-number" name="<?php echo $SPWP::CARD_NUMBER; ?>" maxlength="16" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Credit card number is required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-3 mb-3">
        <label for="cc-expiry-month"><?php _e('Expiration', 'simple-payment'); ?></label>
        <select class="custom-select d-block w-100" id="cc-expiry-month" name="<?php echo $SPWP::CARD_EXPIRY_MONTH; ?>" required=""><option></option>
          <option>01</option><option>02</option><option>03</option><option>04</option>
          <option>05</option><option>06</option><option>07</option><option>08</option>
          <option>09</option><option>10</option><option>11</option><option>12</option>
        </select>
        <div class="invalid-feedback">
          <?php _e('Required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-3 mb-3">
      <label for="cc-expiry-year">&nbsp;</label>
        <select class="custom-select d-block w-100" id="cc-expiry-year" name="<?php echo $SPWP::CARD_EXPIRY_YEAR; ?>" required=""><option></option>
          <?php for ($y = $year_today; $y <= $year_max; $y++) echo '<option>'.$y.'</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <label for="cc-cvv"><?php _e('CVV', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-cvv" name="<?php echo $SPWP::CARD_CVV; ?>" maxlength="4" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <?php if (isset($installments_min) && $installments_min && isset($installments_max) && $installments_max && $installments_max > 1) { ?>
        <label for="payments"><?php _e('Installments', 'simple-payment'); ?></label>
        <select class="custom-select d-block w-100" id="payments" name="<?php echo $SPWP::PAYMENTS; ?>" required="">
          <?php for ($installment = $installments_min; $installment <= $installments_max; $installment++) echo '<option'.(isset($installments) && $installment == $installments ? ' selected' : '').'>'.$installment.'</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Number of Installments is required.', 'simple-payment'); ?>
        </div>
        <?php } ?>
      </div>
    </div>
</div>
<script>
(function () {
  'use strict'
  window.addEventListener('load', function () {
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.getElementsByClassName('checkout')

    
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
</script>