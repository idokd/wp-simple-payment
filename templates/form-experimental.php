<?php
require('preparation.php');
wp_enqueue_script( 'simple-payment-checkout-js', SPWP_PLUGIN_URL.'assets/js/form-checkout.js', [], $SPWP::$version, true );
?>
<script>
var sp_settings = <?php echo json_encode($SPWP->settings()); ?>;
</script>
<div class="col-md-8 order-md-1">
<form class="needs-validation" novalidate="" id="simple-payment" name="simple-payment" action="<?php echo $SPWP->payment_page(); ?>" method="post"<?php echo $target; ?>>
  <input type="hidden" name="op" value="purchase" />
  <input type="hidden" name="product" value="<?php echo $product; ?>" />
  <input type="hidden" name="amount" value="<?php echo $amount; ?>" />
  <input type="hidden" name="engine" value="<?php echo $engine; ?>" />
  <?php if ( isset( $_REQUEST[ 'message' ] ) && $message = $SPWP::get_message( $_REQUEST[ 'message' ] ) ) { ?><div class="alert alert-warning" role="alert"><?php echo esc_html( $message ); ?></div><?php } ?>

  <h4 class="mb-3"><?php _e('Billing Information', 'simple-payment'); ?></h4>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="firstName"><?php _e('First name', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="firstName" name="<?php echo $SPWP::FIRST_NAME; ?>" placeholder="" value="" required="">
        <div class="invalid-feedback">
          <?php _e('Valid first name is required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="lastName"><?php _e('Last name', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="lastName" name="last_name" placeholder="" value="" required="">
        <div class="invalid-feedback">
            <?php _e('Valid last name is required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="phone"><?php _e('Phone', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="phone" name="phone" placeholder="" value="" required="">
        <div class="invalid-feedback">
          <?php _e('Valid phone is required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="mobile"><?php _e('Mobile', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="mobile" name="mobile" placeholder="" value="" required="">
        <div class="invalid-feedback">
          <?php _e('Valid mobile is required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label for="email"><?php _e('Email', 'simple-payment'); ?></label>
      <input type="email" class="form-control" id="email" name="email" placeholder="<?php _e('you@example.com', 'simple-payment'); ?>" required="">
      <div class="invalid-feedback">
        <?php _e('Please enter a valid email address.', 'simple-payment'); ?>
      </div>
    </div>

    <div class="mb-3">
      <label for="address"><?php _e('Address', 'simple-payment'); ?></label>
      <input type="text" class="form-control" id="address" name="address" placeholder="<?php _e('1234 Main St.', 'simple-payment'); ?>" required="">
      <div class="invalid-feedback">
        <?php _e('Please enter your address.', 'simple-payment'); ?>
      </div>
    </div>

    <div class="mb-3">
      <label for="address2"><?php _e('Address 2', 'simple-payment'); ?><span class="text-muted"> <?php _e('(Optional)', 'simple-payment'); ?></span></label>
      <input type="text" class="form-control" id="address2" name="address2" placeholder="<?php _e('Apartment or suite', 'simple-payment'); ?>" >
    </div>

    <div class="mb-3">
      <label for="city"><?php _e('City', 'simple-payment'); ?></label>
      <input type="text" class="form-control" id="city" name="city" placeholder="City" required="">
      <div class="invalid-feedback">
        <?php _e('Please enter your city.', 'simple-payment'); ?>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5 mb-3">
        <label for="country"><?php _e('Country', 'simple-payment'); ?></label>
        <select class="custom-select d-block w-100" id="country" name="country" required="">
          <option value=""><?php _e('Choose', 'simple-payment'); ?></option>
          <?php foreach ($SPWP_COUNTRIES as $key => $value) echo '<option value="'.$key.'">'.__($value, 'simple-payment').'</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Please select a valid country.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <label for="state"><?php _e('State', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="state" name="state" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('State required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <label for="zipcode"><?php _e('Zip Code', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="zipcode" name="zipcode" placeholder="" required="" maxlength="10">
        <div class="invalid-feedback">
          <?php _e('Zip code required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label for="taxId"><?php _e('Tax ID', 'simple-payment'); ?></label>
      <input type="text" class="form-control" id="taxId" name="tax_id" placeholder="<?php _e('Tax ID', 'simple-payment'); ?>">
      <div class="invalid-feedback">
        <?php _e('Valid tax id is required.', 'simple-payment'); ?>
      </div>
    </div>

    <div class="mb-3">
      <label for="comment"><?php _e('Comment', 'simple-payment'); ?></label>
      <textarea type="text" class="form-control" id="comment" name="comment" placeholder=""></textarea>
      <div class="invalid-feedback">
        <?php _e('Please enter your comment for invoicing or shipping.', 'simple-payment'); ?>
      </div>
    </div>
    <hr class="mb-4">
    <!--div class="custom-control custom-checkbox">
      <input type="checkbox" class="custom-control-input" id="same-address">
      <label class="custom-control-label" for="same-address">Shipping address is the same as my billing address</label>
    </div>
    <hr class="mb-4"-->

    <h4 class="mb-3"><?php _e('Payment', 'simple-payment'); ?></h4>

    <div class="d-block my-3">
      <div class="custom-control custom-radio">
        <input id="credit" name="method" type="radio" class="custom-control-input"  value="debit" checked="" required="">
        <label class="custom-control-label" for="credit"><?php _e('Credit card', 'simple-payment'); ?></label>
      </div>
      <div class="custom-control custom-radio">
        <input id="debit" name="method" type="radio" class="custom-control-input" value="debit" required="">
        <label class="custom-control-label" for="debit"><?php _e('Debit card', 'simple-payment'); ?></label>
      </div>
      <!--div class="custom-control custom-radio">
        <input id="paypal" name="method" type="radio" class="custom-control-input" value="paypal" required="">
        <label class="custom-control-label" for="paypal"><?php _e('PayPal', 'simple-payment'); ?></label>
      </div-->
    </div>
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
          <option value="monthly"><?php _e('Monthly Recurring', 'simple-payment'); ?></option>
          <?php for ($installment = $installments_min; $installment <= $installments_max; $installment++) echo '<option'.selected( $installments, $installment, true).'>'.$installment.'</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Number of Installments is required.', 'simple-payment'); ?>
        </div>
        <?php } ?>
      </div>
    </div>
    <button class="btn btn-primary btn-lg btn-block" type="submit"><?php echo sprintf(__('Process Payment [%s]', 'simple-payment'), $amount); ?></button>
  </form>
</div>
<script>
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
</script>