<?php

namespace ACA\SimplePayment;


class TableFactory {

	public function create( $screen_id ) {

		return [
			'screen'  => $screen_id
		];

	}

}