<?php

namespace ACA\SimplePayment\HideOnScreen;

use ACP\Settings\ListScreen\HideOnScreen;

class WordPressNotifications extends HideOnScreen {

	public function __construct() {
		parent::__construct( 'hide_sp_wordpress_notices', __( 'WordPress Notifications', 'codepress-admin-columns' ) );
	}

}