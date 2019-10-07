<?php
//var $product, $price, $id, $fixed;

// TODO: fill country selectbox (maybe also states)
// TODO: add validation for credit card number regexp
// TODO: validate credit card expiry
// TODO: valdate 3 digits (or 4 in american express) cvv
// TODO: show/hide depending if paypal or not.

$phone_pattern= '^\d{4}-\d{3}-\d{4}$';
?>
<div class="col-md-8 order-md-1">
  <form class="needs-validation" novalidate="" id="simple-payment" name="simple-payment">
  <input type="hidden" name="op" value="purchase" />
  <input type="hidden" name="product" value="<?php echo $product; ?>" />
  <input type="hidden" name="amount" value="<?php echo $amount; ?>" />
  <input type="hidden" name="engine" value="<?php echo $engine; ?>" />

    <div class="mb-3">
      <label for="email"><?php _e('Email', 'simple-payment'); ?></label>
      <input type="email" class="form-control" id="email" name="email" placeholder="<?php _e('you@example.com', 'simple-payment'); ?>" required="">
      <div class="invalid-feedback">
        <?php _e('Please enter a valid email address.', 'simple-payment'); ?>
      </div>
    </div>

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
      <div class="custom-control custom-radio">
        <input id="paypal" name="method" type="radio" class="custom-control-input" value="paypal" required="">
        <label class="custom-control-label" for="paypal"><?php _e('PayPal', 'simple-payment'); ?></label>
      </div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="cc-name"><?php _e('Name on card', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-name" name="card_holder_name" placeholder="" required="">
        <small class="text-muted"><?php _e('Full name as displayed on card', 'simple-payment'); ?></small>
        <div class="invalid-feedback">
          <?php _e('Name on card is required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="cc-number"><?php _e('Credit card number', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-number" name="card_number" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Credit card number is required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="cc-expiration"><?php _e('Expiration', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-expiration" name="expiration" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Expiration date required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="cc-cvv"><?php _e('CVV', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-cvv" name="ccv" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Security code required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>
    <hr class="mb-4">
    <button class="btn btn-primary btn-lg btn-block" type="submit"><?php _e('Process Payment', 'simple-payment'); ?></button>
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
      form.addEventListener('submit', function (event) {
        if (form.checkValidity() === false) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
  }, false)
}());
</script>
