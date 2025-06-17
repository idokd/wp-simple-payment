<?php

class WC_Payment_Token_SimplePayment extends WC_Payment_Token_CC {

    protected $type = 'SimplePayment';

    /**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'last4' => '',
		'expiry_year' => '',
		'expiry_month' => '',
		'card_type' => '',
        'engine' => '',
		'owner_name' => '',
		'cvv' => '',
        'owner_id' => ''
    ];

	public function get_display_name( $deprecated = '' ) {
		return( trim( parent::get_display_name( $deprecated ) ) );
	}

    public function validate() {
        // TODO: do we require to validate any info
		if ( false === parent::validate() ) {
		//	return false;
		}
        return( true );
    }

    /**
	 * Returns the engine.
	 *
	 * @since  2.6.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Engine
	 */
	public function get_engine( $context = 'view' ) {
		return $this->get_prop( 'engine', $context );
	}

	/**
	 * Set the engine.
	 *
	 * @since 2.6.0
	 * @param string $engine .
	 */
	public function set_engine( $engine ) {
		$this->set_prop( 'engine', $engine );
	}

    /**
	 * Returns the Owner ID.
	 *
	 * @since  2.6.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Owner ID
	 */
	public function get_owner_id( $context = 'view' ) {
		return $this->get_prop( 'owner_id', $context );
	}

	/**
	 * Set the Owner ID.
	 *
	 * @since 2.6.0
	 * @param string $owner_id .
	 */
	public function set_owner_id( $owner_id ) {
		$this->set_prop( 'owner_id', $owner_id );
	}

	/**
	 * Set the Owner Name.
	 *
	 * @since 2.6.0
	 * @param string $owner_name.
	 */
	public function set_owner_name( $owner_name ) {
		$this->set_prop( 'owner_name', $owner_name );
	}

	/**
	 * Set the Owner ID.
	 *
	 * @since 2.6.0
	 * @param string $owner_id .
	 */
	public function get_owner_name( $context = 'view' ) {
		return $this->get_prop( 'owner_name', $context );
	}

	/**
	 * Set the CVV.
	 *
	 * @since 2.6.0
	 * @param string $cvv.
	 */
	public function set_cvv( $cvv ) {
		$this->set_prop( 'cvv', $cvv );
	}

	/**
	 * Set the Owner ID.
	 *
	 * @since 2.6.0
	 * @param string $cvv .
	 */
	public function get_cvv( $context = 'view' ) {
		return $this->get_prop( 'cvv', $context );
	}
	
		/**
	 * Hook prefix
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_simplepayment_get_';
	}
	
}