<?php
global $wp_query;
require('preparation.php');
$settings = $wp_query->query_vars;
?>
<script>
var sp_settings = <?php echo json_encode($settings); ?>;
</script>
<div class="modal fade" id="sp-payment" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <div class="embed-responsive embed-responsive-1by1">
        <iframe name="sp-frame" src="about:blank" class="embed-responsive-item h100 w100"></iframe>
        </div>
      </div>
    </div>
  </div>
</div>