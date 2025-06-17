<?php
require('preparation.php');
wp_enqueue_script( 'simple-payment-checkout-js', SPWP_PLUGIN_URL.'assets/js/form-checkout.js', [], $SPWP::$version, true );
?>
<script>
var sp_settings = <?php echo json_encode($SPWP->settings()); ?>;
</script>
<div class="col-md-8 order-md-1">
  <form class="needs-validation" novalidate="" id="simple-payment" name="simple-payment" action="<?php echo esc_url( $SPWP->payment_page() ); ?>" method="post"<?php echo $target; ?>>
  <input type="hidden" name="op" value="purchase" />
  <input type="hidden" name="product" value="<?php echo isset( $product ) ? esc_attr( $product ) : ''; ?>" />
  <input type="hidden" name="amount" value="<?php echo esc_attr( $amount ); ?>" />
<?php if (isset($engine)) { ?><input type="hidden" name="engine" value="<?php echo esc_attr( $engine ); ?>" /><?php } ?>
<?php if (isset($currency)) { ?><input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>" /><?php } ?>
<?php if (isset($redirect_url)) { ?><input type="hidden" name="redirect_url" value="<?php echo esc_attr( $redirect_url ); ?>" /><?php } ?>
<?php if ( isset( $_REQUEST[ 'message' ] ) && $message = $SPWP::get_message( $_REQUEST[ 'message' ] ) ) { ?><div class="alert alert-warning" role="alert"><?php echo esc_html( $message ); ?></div><?php } ?>

    <h4 class="mb-3"><?php _e('Payment', 'simple-payment'); ?></h4>
    <?php if (SimplePaymentPlugin::supports('method', isset($engine) ? $engine : null)) { ?>
    <div class="d-block my-3">
      <div class="custom-control custom-radio">
        <input id="credit" name="method" type="radio" class="custom-control-input" value="debit" checked="" required="">
        <label class="custom-control-label" for="credit"><?php _e('Credit card', 'simple-payment'); ?></label>
      </div>
      <div class="custom-control custom-radio">
        <input id="debit" name="method" type="radio" class="custom-control-input" value="debit" required="">
        <label class="custom-control-label" for="debit"><?php _e('Debit card', 'simple-payment'); ?></label>
      </div>
    </div>
    <?php } ?>
    <div class="row">
    <?php if ( SimplePaymentPlugin::supports( 'cvv', isset( $engine ) ? $engine : null)) { ?>
      <div class="col-md-6 mb-3">
        <label for="cc-name"><?php _e('Name on card', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-name" name="<?php echo esc_attr( $SPWP::CARD_OWNER ); ?>" placeholder="" required="">
        <small class="text-muted"><?php _e('Full name as displayed on card', 'simple-payment'); ?></small>
        <div class="invalid-feedback">
          <?php _e('Name on card is required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="cc-number"><?php _e('Credit card number', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-number" name="<?php echo esc_attr( $SPWP::CARD_NUMBER ); ?>" maxlength="16" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Credit card number is required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-3 mb-3">
        <label for="cc-expiry-month"><?php _e('Expiration', 'simple-payment'); ?></label>
        <select class="custom-select d-block w-100" id="cc-expiry-month" name="<?php echo esc_attr( $SPWP::CARD_EXPIRY_MONTH ); ?>" required=""><option></option>
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
        <select class="custom-select d-block w-100" id="cc-expiry-year" name="<?php echo esc_attr( $SPWP::CARD_EXPIRY_YEAR ); ?>" required=""><option></option>
          <?php for ( $y = $year_today; $y <= $year_max; $y++ ) echo '<option>' . $y . '</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <label for="cc-cvv"><?php _e('CVV', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-cvv" name="<?php echo esc_attr( $SPWP::CARD_CVV ); ?>" maxlength="4" placeholder="" required="">
        <div class="invalid-feedback">
          <?php _e('Required.', 'simple-payment'); ?>
        </div>
      </div>
    <?php } ?>
    <?php if (isset($installments) && $installments && isset($installments_min) && isset($installments_max) && $installments_max && $installments_max > 1) { 
      $installments = $installments === true ? $installments_default : $installments; 
      ?>
      <div class="col-md-4 mb-3">
        <label for="payments"><?php _e( 'Installments', 'simple-payment' ); ?></label>
        <select class="custom-select d-block w-100" id="payments" name="<?php echo esc_attr( $SPWP::PAYMENTS ); ?>" required="">
          <?php 
          for ( $installment = $installments_min; $installment <= $installments_max; $installment++ ) echo '<option' . selected( $installments, $installment, true ) . '>' . $installment . '</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Number of Installments is required.', 'simple-payment'); ?>
        </div>
      </div>
    <?php } ?>
    </div>
    <!--div class="row">
      <div class="col-md-6 mb-3">
        <label for="cc-name"><?php _e('Card Owner ID Number', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-owner-id" name="<?php echo esc_attr( $SPWP::CARD_OWNER_ID ); ?>" placeholder="" required="" maxlength="9">
        <small class="text-muted"><?php _e('Card owner ID (official ID)', 'simple-payment'); ?></small>
        <div class="invalid-feedback">
          <?php _e('Card owner ID number required.', 'simple-payment'); ?>
        </div>
      </div>
    </div-->
    <button class="btn btn-primary btn-lg btn-block" type="submit"><?php echo sprintf( __( 'Process Payment [%s]', 'simple-payment' ), esc_html( $amount ) ); ?></button>
  </form>
</div>