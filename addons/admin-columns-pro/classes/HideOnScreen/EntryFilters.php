<?php

namespace ACA\SimplePayment\HideOnScreen;

use ACP\Settings\ListScreen\HideOnScreen;

class EntryFilters extends HideOnScreen {

	public function __construct() {
		parent::__construct( 'hide_entry_filters', __( 'Entry Search', 'codepress-admin-columns' ) );
	}

}