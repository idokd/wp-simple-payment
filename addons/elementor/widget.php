<?php


class Elementor_SimplePayment_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 *
	 * Retrieve Simple Payment widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'simple-payment';
	}

	/**
	 * Get widget title.
	 *
	 * Retrieve Simple Payment widget title.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return __( 'Simple Payment', 'simple-payment' );
	}

	/**
	 * Get widget icon.
	 *
	 * Retrieve Simple Payment widget icon.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Get widget categories.
	 *
	 * Retrieve the list of categories the Simple Payment widget belongs to.
	 *
	 * Used to determine where to display the widget in the editor.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return [ 'general-elements' ];
	}

	/**
	 * Whether the reload preview is required or not.
	 *
	 * Used to determine whether the reload preview is required.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return bool Whether the reload preview is required.
	 */
	public function is_reload_preview_required() {
		return true;
	}

	/**
	 * Register Simple Payment widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function _register_controls() {
		$this->start_controls_section(
			'basic',
			[
				'label' => __( 'Basic', 'simple-payment' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$this->add_control(
			'product',
			[
				'label' => __( 'Product', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::TEXT			]
		);
		$this->add_control(
			'amount',
			[
				'label' => __( 'Amount', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'step' => 5,
			]
		);
		$this->add_control(
			'id',
			[
				'label' => __( 'Product ID', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::TEXT	
			]
		);
		$this->add_control(
			'type',
			[
				'label' => __( 'Type', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					''  => '',
					'button'  => __( 'Button', 'simple-payment' ),
					'form' => __( 'Bootstrap Basic', 'simple-payment' ),
					'template' => __( 'Template', 'simple-payment' ),
					'hidden' => __( 'Hidden', 'simple-payment' ),
				],
			]
		);
		$this->add_control(
			'form',
			[
				'label' => __( 'Form', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					''  => '',
					'legacy'  => __( 'Legacy', 'simple-payment' ),
					'bootstrap-basic' => __( 'Bootstrap Basic', 'simple-payment' ),
					'bootstrap' => __( 'Bootstrap', 'simple-payment' ),
					'donation' => __( 'Donation', 'simple-payment' ),
				],
			]
		);

		$this->add_control(
			'template',
			[
				'label' => __( 'Template', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::TEXT		
			]
		);

		$this->add_control(
			'display',
			[
				'label' => __( 'Display', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					''  => '',
					'redirect'  => __( 'Redirct', 'simple-payment' ),
					'iframe' => __( 'IFRAME', 'simple-payment' ),
					'modal' => __( 'Modal', 'simple-payment' ),
				],
			]
		);

		$this->add_control(
			'target',
			[
				'label' => __( 'Target', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					''  => '',
					'_top'  => __( '_top', 'simple-payment' ),
					'_parent' => __( '_parent', 'simple-payment' ),
					'_self' => __( '_self', 'simple-payment' ),
					'_blank' => __( '_blank', 'simple-payment' ),
				],
			]
		);

		$this->add_control(
			'redirect_url',
			[
				'label' => __( 'Redirect URL', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::TEXT		
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'advanced',
			[
				'label' => __( 'Advanced', 'simple-payment' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$engines = SimplePaymentPlugin::$engines;
		$options = ['' => ''];
		foreach ($engines as $engine) { $options[$engine] = $engine; }
		$this->add_control(
			'engine',
			[
				'label' => __( 'Engine', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
			]
		);

		$this->add_control(
			'method',
			[
				'label' => __( 'Payment Gateway Method', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::TEXT		
			]
		);

		$this->add_control(
			'installments',
			[
				'label' => __( 'Installments', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'true'
			]
		);

		$this->add_control(
			'enable_query',
			[
				'label' => __( 'Enable Query Params', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'true'		
			]
		);

		$this->add_control(
			'product_field',
			[
				'label' => __( 'Product Field', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::TEXT		
			]
		);

		$this->add_control(
			'amount_field',
			[
				'label' => __( 'Amount Field', 'simple-payment' ),
				'type' => \Elementor\Controls_Manager::TEXT		
			]
		);
		/*
		id: { type: 'integer' },
		fixed: { type: 'boolean' },
		*/

		$this->end_controls_section();
	}

	/**
	 * Render Simple Payment widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {
		$SP = SimplePaymentPlugin::instance();
		$params = [];
		foreach ( $this->get_controls() as $control ) {
			if ( isset( $control[ 'name' ] ) && $this->get_settings( $control[ 'name' ] ) ) $params[ $control[ 'name' ] ] = $this->get_settings( $control[ 'name' ] );
		}
		echo $SP->checkout($params);
	}

	/**
	 * Render Simple Payment widget as plain content.
	 *
	 * Override the default behavior by printing the shortcode insted of rendering it.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function render_plain_content() {
		$params = [];
		foreach ( $this->get_controls() as $control ) {
			if ( isset( $control[ 'name' ] ) && $this->get_settings( $control[ 'name' ] ) ) $params[ $control[ 'name' ] ] = $this->get_settings( $control[ 'name' ] );
		}
		$shortcode = '[simple_payment';
		foreach ( $params as $param => $value ) {
			$shortcode .= ' ' . $param . '="' . $value . '"';
		}
		$shortcode .= ']';
		echo $shortcode;
	}

	/**
	 * Render Simple Payment widget output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function _content_template() {}
	
}