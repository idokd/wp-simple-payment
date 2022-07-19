<?php
require( SPWP_PLUGIN_DIR.'/templates/preparation.php' );
?>
<div class="col-md-8 order-md-1" id="simple-payment">
    <div class="row form-row">
      <div class="col-md-6 mb-3">
        <label for="cc-name"><?php _e('Name on card', 'simple-payment'); ?></label>
        <input type="text" class="form-control input-text" id="cc-name" name="<?php echo $SPWP::CARD_OWNER; ?>" placeholder="" required="">
        <small class="text-muted"><?php _e('Full name as displayed on card', 'simple-payment'); ?></small>
        <div class="invalid-feedback">
          <?php _e('Name on card is required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label for="cc-number"><?php _e('Credit card number', 'simple-payment'); ?></label>
        <input type="text" class="form-control input-text wc-credit-card-form-card-number" id="cc-number" name="<?php echo $SPWP::CARD_NUMBER; ?>" maxlength="16" placeholder="" required="" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel">
        <div class="invalid-feedback">
          <?php _e('Credit card number is required.', 'simple-payment'); ?>
        </div>
      </div>
    </div>
    <div class="row form-row">
      <div class="col-md-3 mb-3">
        <label for="cc-expiry-month"><?php _e('Expiration', 'simple-payment'); ?></label>
        <select class="custom-select d-block w-100 form-control" id="cc-expiry-month" name="<?php echo $SPWP::CARD_EXPIRY_MONTH; ?>" required=""><option></option>
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
        <select class="custom-select d-block w-100 form-control" id="cc-expiry-year" name="<?php echo $SPWP::CARD_EXPIRY_YEAR; ?>" required=""><option></option>
          <?php for ($y = $year_today; $y <= $year_max; $y++) echo '<option>'.$y.'</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <label for="cc-cvv"><?php _e('CVV', 'simple-payment'); ?></label>
        <input type="text" class="form-control input-text wc-credit-card-form-card-cvc" id="cc-cvv" name="<?php echo $SPWP::CARD_CVV; ?>" maxlength="4" placeholder="" required="" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel">
        <div class="invalid-feedback">
          <?php _e('Required.', 'simple-payment'); ?>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <?php if (isset($installments) && $installments && isset($installments_min) && $installments_min && isset($installments_max) && $installments_max && $installments_max > 1) { ?>
        <label for="payments"><?php _e('Installments', 'simple-payment'); ?></label>
        <select class="custom-select d-block w-100 form-control" id="payments" name="<?php echo $SPWP::PAYMENTS; ?>" required="">
          <?php for ($installment = $installments_min; $installment <= $installments_max; $installment++) echo '<option'.selected( $installments, $installment, true).'>'.$installment.'</option>'; ?>
        </select>
        <div class="invalid-feedback">
          <?php _e('Number of Installments is required.', 'simple-payment'); ?>
        </div>
        <?php } ?>
      </div>
    </div>
    <?php if (isset($owner_id) && $owner_id) { ?>
    <div class="row form-row">
      <div class="col-md-6 mb-3">
        <label for="cc-card-owner-id"><?php _e('Card Owner ID', 'simple-payment'); ?></label>
        <input type="text" class="form-control" id="cc-card-owner-id" name="<?php echo $SPWP::CARD_OWNER_ID; ?>" placeholder="">
        <small class="text-muted"><?php _e('Document ID as registered with card company', 'simple-payment'); ?></small>
        <div class="invalid-feedback">
          <?php _e('Card owner Id is required or invalid.', 'simple-payment'); ?>
        </div>
      </div>
    </div>
    <?php } ?>
</div>