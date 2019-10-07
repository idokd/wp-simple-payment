<?php
//var $product, $price, $id, $fixed;

// TODO: fill country selectbox (maybe also states)
// TODO: add validation for credit card number regexp
// TODO: validate credit card expiry
// TODO: valdate 3 digits (or 4 in american express) cvv
// TODO: show/hide depending if paypal or not.


?>
<div class="col-md-8 order-md-1">
  <form class="needs-validation" novalidate="" id="simple-payment" name="simple-payment" target="sp-payment-frame" method="post">
  <input type="hidden" name="op" value="purchase" />
  <input type="hidden" name="product" value="<?php echo $product; ?>" />
  <input type="hidden" name="amount" value="<?php echo $amount; ?>" />
  <input type="hidden" name="engine" value="<?php echo $engine; ?>" />

  <h4 class="mb-3">Purchase Information</h4>
  <div class="mb-3">
    <label for="amount"><?php echo $product; ?></label>
    <input type="text" class="form-control" id="amount" name="amount_show" readonly value="<?php echo $amount; ?>" />
  </div>
  <h4 class="mb-3">Billing Information</h4>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="firstName">First name</label>
        <input type="text" class="form-control" id="firstName" name="first_name" placeholder="" value="" required="">
        <div class="invalid-feedback">
          Valid first name is required.
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="lastName">Last name</label>
        <input type="text" class="form-control" id="lastName" name="last_name" placeholder="" value="" required="">
        <div class="invalid-feedback">
          Valid last name is required.
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="phone">Phone</label>
        <input type="text" class="form-control" id="phone" name="phone" placeholder="" value="" required="">
        <div class="invalid-feedback">
          Valid phone is required.
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="mobile">Mobile</label>
        <input type="text" class="form-control" id="mobile" name="mobile" placeholder="" value="" required="">
        <div class="invalid-feedback">
          Valid mobile is required.
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required="">
      <div class="invalid-feedback">
        Please enter a valid email address for shipping updates.
      </div>
    </div>

    <div class="mb-3">
      <label for="address">Address</label>
      <input type="text" class="form-control" id="address" name="address" placeholder="1234 Main St" required="">
      <div class="invalid-feedback">
        Please enter your shipping address.
      </div>
    </div>

    <div class="mb-3">
      <label for="address2">Address 2 <span class="text-muted">(Optional)</span></label>
      <input type="text" class="form-control" id="address2" name="address2" placeholder="Apartment or suite" >
    </div>

    <div class="mb-3">
      <label for="city">City</label>
      <input type="text" class="form-control" id="city" name="city" placeholder="City" required="">
      <div class="invalid-feedback">
        Please enter your city for shipping.
      </div>
    </div>

    <div class="row">
      <div class="col-md-5 mb-3">
        <label for="country">Country</label>
        <select class="custom-select d-block w-100" id="country" name="country" required="">
          <option value="">Choose...</option>
          <option>United States</option>
        </select>
        <div class="invalid-feedback">
          Please select a valid country.
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <label for="state">State</label>
        <input type="text" class="form-control" id="state" name="state" placeholder="" required="">
        <div class="invalid-feedback">
          State required.
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <label for="zipcode">Zip Code</label>
        <input type="text" class="form-control" id="zipcode" name="zipcode" placeholder="" required="" maxlength="10">
        <div class="invalid-feedback">
          Zip code required.
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label for="taxId">Tax ID</label>
      <input type="text" class="form-control" id="taxId" name="tax_id" placeholder="Tax ID">
      <div class="invalid-feedback">
        Valid tax id is required.
      </div>
    </div>

    <div class="mb-3">
      <label for="comment">Comment</label>
      <textarea type="text" class="form-control" id="comment" name="comment" placeholder=""></textarea>
      <div class="invalid-feedback">
        Please enter your comment for invoicing.
      </div>
    </div>
    <hr class="mb-4">
    <!--div class="custom-control custom-checkbox">
      <input type="checkbox" class="custom-control-input" id="same-address">
      <label class="custom-control-label" for="same-address">Shipping address is the same as my billing address</label>
    </div>
    <hr class="mb-4"-->

    <!--h4 class="mb-3">Payment</h4>

    <div class="d-block my-3">
      <div class="custom-control custom-radio">
        <input id="credit" name="method" type="radio" class="custom-control-input"  value="debit" checked="" required="">
        <label class="custom-control-label" for="credit">Credit card</label>
      </div>
      <div class="custom-control custom-radio">
        <input id="debit" name="method" type="radio" class="custom-control-input" value="debit" required="">
        <label class="custom-control-label" for="debit">Debit card</label>
      </div>
      <div class="custom-control custom-radio">
        <input id="paypal" name="method" type="radio" class="custom-control-input" value="paypal" required="">
        <label class="custom-control-label" for="paypal">PayPal</label>
      </div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="cc-name">Name on card</label>
        <input type="text" class="form-control" id="cc-name" name="card_holder_name" placeholder="" required="">
        <small class="text-muted">Full name as displayed on card</small>
        <div class="invalid-feedback">
          Name on card is required
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="cc-number">Credit card number</label>
        <input type="text" class="form-control" id="cc-number" name="card_number" placeholder="" required="">
        <div class="invalid-feedback">
          Credit card number is required
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-3 mb-3">
        <label for="cc-expiration">Expiration</label>
        <input type="text" class="form-control" id="cc-expiration" name="expiration" placeholder="" required="">
        <div class="invalid-feedback">
          Expiration date required
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <label for="cc-cvv">CVV</label>
        <input type="text" class="form-control" id="cc-cvv" name="ccv" placeholder="" required="">
        <div class="invalid-feedback">
          Security code required
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="payments">Installments</label>
        <select class="custom-select d-block w-100" id="payments" name="payments" required="">
          <option value="">Choose...</option>
          <option>Single Payment</option>
          <option>2</option>
          <option>3</option>

        </select>
        <div class="invalid-feedback">
          Security code required
        </div>
      </div>
    </div>
    <hr class="mb-4"-->
    <button class="btn btn-primary btn-lg btn-block" type="submit">Continue to checkout</button>
    <div class="modal fade" id="sp-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalScrollableTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-body">
            <div class=" embed-responsive embed-responsive-1by1">
            <iframe name="sp-payment-frame" src="about:blank" class="embed-responsive-item h100 w100"></iframe>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
<!-- Modal -->

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
        } else jQuery('#sp-payment').modal('show');
        form.classList.add('was-validated');

      }, false)
    })
  }, false)
}());
</script>
