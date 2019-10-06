<?php
//var $product, $price, $id, $fixed;

// TODO: fill country selectbox (maybe also states)
// TODO: add validation for credit card number regexp
// TODO: validate credit card expiry
// TODO: valdate 3 digits (or 4 in american express) cvv
// TODO: show/hide depending if paypal or not.

wp_enqueue_script( 'bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js', ['jquery'], '4.3.1', true );
wp_enqueue_style( 'bootstrap-4', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css', array(), '4.3.1', 'all' );

$phone_pattern= '^\d{4}-\d{3}-\d{4}$';
$text = __('For', 'simple-payment');
$text = __('General Donation', 'simple-payment');

?>
<style>
.container {
  max-width: 960px;
}
.lh-condensed { line-height: 1.25; }
</style>

<div class="col-md-8 order-md-1">
  <form class="needs-validation" novalidate="" id="simple-payment" name="simple-payment">
  <input type="hidden" name="op" value="purchase" />
  <input type="hidden" name="product" value="<?php echo $product; ?>" />
  <input type="hidden" name="amount" value="<?php echo $amount; ?>" />

  <h4 class="mb-3"><?php _e('Donation Information', 'simple-payment'); ?></h4>
  <div class="mb-3">
    <label for="amount"><?php echo $product; ?></label>
    <input type="text" class="form-control" id="amount" name="amount" readonly value="<?php echo $amount; ?>" />
  </div>
  <h4 class="mb-3"><?php _e('Billing Information', 'simple-payment'); ?></h4>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="firstName"><?php _e('First name', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="firstName" name="first_name" placeholder="" value="" required="">
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
          <option><?php _e('United States', 'simple-payment'); ?></option>
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

    <h4 class="mb-3"><?php _e('Donation', 'simple-payment'); ?></h4>

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
      <div class="col-md-3 mb-3">
        <label for="cc-expiration"><?php _e('Expiration', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-expiration" name="expiration" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Expiration date required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <label for="cc-cvv"><?php _e('CVV', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-cvv" name="ccv" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Security code required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="payments"><?php _e('Installments', 'simple-payment'); ?></label>
        <select class="custom-select d-block w-100" id="payments" name="payments" required="">
          <option value=""><?php _e('Choose', 'simple-payment'); ?></option>
          <option><?php _e('Single Payment', 'simple-payment'); ?></option>
          <option>2</option>
          <option>3</option>
        </select>
        <div class="invalid-feedback">
          <?php _e('Number of Installments is required.', 'simple-payment'); ?>
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
