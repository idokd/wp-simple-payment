<?php

namespace ACA\SimplePayment\Column\Entry\Original;

use AC;
use ACA\SimplePayment\Search;
use ACP;

class Starred extends AC\Column implements ACP\Search\Searchable {

	public function __construct() {
		$this->set_original( true )
		     ->set_type( 'is_starred' )
		     ->set_label( '<span class="dashicons dashicons-star-filled"></span>' );
	}

	public function search() {
		return new Search\Comparison\Entry\Starred();
	}

}