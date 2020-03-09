<?php
require('preparation.php');
?>
<div sp-data="container"></div>
<script>
  if (SimplePayment) SimplePayment.submit(<?php echo json_encode($SPWP->settings()); ?>, 'sp-frame');
</script>
