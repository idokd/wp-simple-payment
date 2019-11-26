<?php
global $wp_query;
require('preparation.php');
$settings = $wp_query->query_vars;
?>
<script>
var sp_settings = <?php echo json_encode($settings); ?>;
</script>