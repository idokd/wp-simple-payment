<?php
wp_enqueue_script( 'simple-payment-admin-js', plugin_dir_url( __FILE__ ).'script.js', [], false, true );
wp_enqueue_style( 'simple-payment-admin-css', plugin_dir_url( __FILE__ ).'style.css', [], false);

$list->prepare_items();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php _e( 'Payments Transactions', 'simple-payment' ); ?></h1>
	<?php if ( user_can( get_current_user_id(), 'manage_options' ) ) { ?><a href="/wp-admin/options-general.php?page=sp" cclass="page-title-action">Configure</a><?php } ?>
	<?php
	if ( isset( $_REQUEST[ 's' ] ) && strlen( $_REQUEST[ 's' ] ) ) {
		/* translators: %s: search keywords */
		printf( ' <span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', get_search_query() );
	}
	?>
	<hr class="wp-header-end">
	<?php $list->views(); ?>
	<form id="transactions-filter" method="get">
		<?php
		$list->search_box( __( 'Search', 'simple-payment' ), 's' );
		$list->display(); ?>
	</form>
	<br class="clear">
</div>