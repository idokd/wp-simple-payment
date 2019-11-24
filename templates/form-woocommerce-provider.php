<?php
global $wp_query;
require('preparation.php');
?>

<div sp-data="container"></div>
<script>
var sp_settings = <?php echo json_encode($wp_query->query_vars); ?>;
(function () {
  'use strict'
  window.addEventListener('load', function () {
    SimplePayment.submit(null, 'sp-frame');
  });
}());
</script>
