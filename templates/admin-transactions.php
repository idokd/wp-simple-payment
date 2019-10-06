<?php
require(SP_PLUGIN_DIR.'/admin/transaction-list-table.php');

$list = new Transaction_List();
?>
<style>
.tablenav .alignleft:nth-of-type(2n) { clear: both; }
</style>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php _e('Payments Transactions', 'simple-payment'); ?></h1>
	<?php
	if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
		/* translators: %s: search keywords */
		printf( ' <span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', get_search_query() );
	}
	?>

	<hr class="wp-header-end">
	<div id="post-body" class="metabox-holder columns-2">
		<div id="post-body-content">
			<div class="meta-box-sortables ui-sortable">
				<form method="post">
					<?php
					$list->prepare_items();
					$list->display(); ?>
				</form>
			</div>
		</div>
	</div>
	<br class="clear">

</div>
