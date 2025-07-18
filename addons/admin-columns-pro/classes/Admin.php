<?php

namespace ACA\SimplePayment;

use AC;
use ACA\SimplePayment\HideOnScreen\EntryFilters;
use ACA\SimplePayment\HideOnScreen\WordPressNotifications;
use ACA\SimplePayment\ListScreen\Entry;
use ACP;
use ACP\Type\HideOnScreen\Group;

final class Admin implements AC\Registerable {

	public function register(): void {
		add_action( 'acp/admin/settings/hide_on_screen', [ $this, 'add_hide_on_screen' ], 10, 2 );
	}

	public function add_hide_on_screen(
		ACP\Settings\ListScreen\HideOnScreenCollection $collection,
		AC\ListScreen $list_screen
	) {
		if ( $list_screen instanceof Entry ) {
			$collection->remove( new ACP\Settings\ListScreen\HideOnScreen\Search() )
			           ->add( new EntryFilters(), new Group( Group::ELEMENT ) )
			           ->add( new WordPressNotifications(), new Group( Group::ELEMENT ) );
		}

	}

}