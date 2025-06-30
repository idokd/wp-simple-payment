<?php

namespace ACA\SimplePayment\Editing\Strategy;

use ACA\SimplePayment;
use ACP;

class Entry implements ACP\Editing\Strategy {

	private $list_table;

	public function __construct( $list_table ) {
		$this->list_table = $list_table;
	}

	public function user_can_edit(): bool {
		return( current_user_can( SimplePayment\Capabilities::EDIT_ENTRIES ) ); // TODO: SPCommon::current_user_can_any(  );
	}

	public function user_can_edit_item( int $object_id ): bool {
		return $this->user_can_edit();
	}

	public function get_query_request_handler(): ACP\Editing\RequestHandler {
		return new SimplePayment\Editing\RequestHandler\Query\Entry( $this->list_table );
	}

}