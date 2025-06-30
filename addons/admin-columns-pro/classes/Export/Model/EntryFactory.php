<?php

namespace ACA\SimplePayment\Export\Model;

use ACA\SimplePayment\Column;
use ACA\SimplePayment\Export;
use ACA\SimplePayment\Field;
use ACP;

class EntryFactory {

	public function create( Column\Entry $column, Field\Field $field ): ACP\Export\Service {

		switch ( true ) {
			case $field instanceof Field\Type\Address:
				return new Export\Model\Entry\Address( $column );

			case $field instanceof Field\Type\Checkbox:
			case $field instanceof Field\Type\Consent:
				return new Export\Model\Entry\Check( $column );

			case $field instanceof Field\Type\Product:
				return new ACP\Export\Model\StrippedValue( $column );

			case $field instanceof Field\Type\ItemList:
				return new Export\Model\Entry\ItemList( $column );

			default:
				return new ACP\Export\Model\Value( $column );
		}

	}

}