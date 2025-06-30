<?php

namespace ACA\SimplePayment\Utils;

class Hooks {

	public static function get_load_form_entries() {
		global $page_hook;
		return strpos( $page_hook, '_page_simple-payments' ) !== false
			? 'load-' . $page_hook
			: 'load-simple-payments';
	}

}