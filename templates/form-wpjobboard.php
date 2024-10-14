<?php
require('preparation.php');
?>
<script> var sp_settings = <?php echo json_encode($SPWP->settings()); ?>; </script>
<div class="wpjb-flash-info">
<div class="wpjb-flash-icon"><span class="wpjb-glyphs wpjb-icon-spinner wpjb-animate-spin"></span></div>
<div class="wpjb-flash-body">
<p><strong><?php _e('Your order has been placed.', 'simple-payment'); ?></strong></p>
<p><?php _e('Please wait. You are now being redirected to payment.', 'simple-payment'); ?></p>
</div>
</div>
<iframe name="sp-iframe" src="about:blank" allowpaymentrequest="true" allow="payment" sp-data="iframe" style="display:none"></iframe>