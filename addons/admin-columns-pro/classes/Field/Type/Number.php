<?php

namespace ACA\SimplePayment\Field\Type;

use ACA\SimplePayment;

class Number extends SimplePayment\Field\Field implements SimplePayment\Field\Number {

	private function get_range( $key ) {
		return $this->SP_field->offsetExists( $key ) && $this->SP_field->offsetGet( $key )
			? $this->SP_field->offsetGet( $key )
			: '';
	}

	public function has_range_min(){
		return $this->SP_field->offsetExists( 'rangeMin' ) && $this->SP_field->offsetGet( 'rangeMin' );
	}

	public function has_range_max(){
		return $this->SP_field->offsetExists( 'rangeMax' ) && $this->SP_field->offsetGet( 'rangeMax' );
	}

	public function get_range_min() {
		return $this->get_range( 'rangeMin' );
	}

	public function get_range_max() {
		return $this->get_range( 'rangeMax' );
	}

	public function get_step() {
		return 'any';
	}

}