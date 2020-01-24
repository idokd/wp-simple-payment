<?php
require('preparation.php');
?>
<div sp-data="container"></div>
<script>
(function () {
  'use strict'
  window.addEventListener('load', function () {
    SimplePayment.submit(<?php echo json_encode($SPWP->settings()); ?>, 'sp-frame');
  });
}());
</script>
