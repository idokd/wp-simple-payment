<?php
require('preparation.php');
?>
<div sp-data="container"></div>
<script>
(function () {
  'use strict'
  <?php if (isset($settings) && $settings) echo 'var sp_settings = '.json_encode($settings, true).';'; ?>

  window.addEventListener('load', function () {
    <?php if (isset($settings) && isset($settings['method']) && $settings['method'] == 'direct_open') { ?>
      SimplePayment.init(sp_settings);
      SimplePayment.show(SimplePayment.params['url']);
    <?php } else { ?> 
      SimplePayment.submit(<?php echo json_encode($SPWP->settings()); ?>, 'sp-frame');
    <?php } ?>
  });
}());
</script>
