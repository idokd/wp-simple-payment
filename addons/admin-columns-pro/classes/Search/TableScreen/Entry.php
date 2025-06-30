<?php

namespace ACA\SimplePayment\Search\TableScreen;

use ACP\Search;

class Entry extends Search\TableScreen {

	public function register(): void {
		parent::register();

		add_action( 'sp_pre_entry_list', [ $this, 'filters_markup' ] );
	}

}