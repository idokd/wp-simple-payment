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
  <input type="hidden" name="display" value="<?php echo $display; ?>" />

  <?php if (isset($_REQUEST['message']) && $message = $_REQUEST['message']) { ?><div class="alert alert-warning" role="alert"><?php echo $message; ?></div><?php } ?>

    <div class="mb-3">
      <label for="email"><?php _e('Email', 'simple-payment'); ?></label>
      <input type="email" class="form-control" id="email" name="email" placeholder="<?php _e('you@example.com', 'simple-payment'); ?>" required="">
      <div class="invalid-feedback">
        <?php _e('Please enter a valid email address.', 'simple-payment'); ?>
      </div>
    </div>

    <hr class="mb-4">
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
