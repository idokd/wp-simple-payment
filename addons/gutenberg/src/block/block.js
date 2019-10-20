/**
 * BLOCK: sp-block
 *
 * Registering a basic block with Gutenberg.
 * Simple block, renders and saves the same content without any interactivity.
 */

//  Import CSS.
import './editor.scss';
import './style.scss';

const { __ } = wp.i18n; // Import __() from wp.i18n

const { registerBlockType } = wp.blocks; // Import registerBlockType() from wp.blocks

/**
 * Register: aa Gutenberg Block.
 *
 * Registers a new block provided a unique name and an object defining its
 * behavior. Once registered, the block is made editor as an option to any
 * editor interface where blocks are implemented.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType( 'simple-payment/simple-payment', {
	title: __( 'Simple Payment' ), 
	description: __( 'Simple Payment to integrate an easy form and credit card processing.' ),
	icon: 'money', // Block icon from Dashicons → https://developer.wordpress.org/resource/dashicons/.
	category: 'common',
	keywords: [
		__( 'sSimple Paymnet Block' ),
		__( 'Simple Paymnet' ),
		__( 'simple-payment' ),
		__( 'checkout' ),
		__( 'Payment' ),
	],
	attributes: {
		id: {
			type: 'integer'
		},
		amount: {
			type: 'string'
		},
		product: {
			type: 'string'
		},
		title: {
			type: 'string'
		},
		fixed: {
			type: 'boolean'
		},
		type: { // form/temlpate e...
			type: 'string'
		},
		form: { // form/temlpate e...
			type: 'string'
		},
		template: { // form/temlpate e...
			type: 'string'
		},
		target: {
			type: 'string'
		},
		engine: {
			type: 'string'
		},
		method: {
			type: 'string'
		},
		redirect_url: {
			type: 'string'
		},
		amount_field: {
			type: 'string'
		},
		product_field: {
			type: 'string'
		},
		enable_query: {
			type: 'boolean'
		}
	},
	supports: {
		anchor: true,
	//	html: false,
	},

	/**
	 * The edit function describes the structure of your block in the context of the editor.
	 * This represents what the editor will render when the block is used.
	 *
	 * The "edit" property must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @returns {Mixed} JSX Component.
	 */
	edit: ( props ) => {
		return [ wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Product ID (post/page)'),
				value: props.attributes.id,
				onChange: ( val ) => { props.setAttributes( { id: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Product'),
				value: props.attributes.product,
				onChange: ( val ) => { props.setAttributes( { product: val } ); },
			}
		),wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Amount'),
				value: props.attributes.amount,
				onChange: ( val ) => { props.setAttributes( { amount: val } ); },
			}
		),  wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Type'),
				value: props.attributes.type,
				onChange: ( val ) => { props.setAttributes( { type: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Form'),
				value: props.attributes.form,
				onChange: ( val ) => { props.setAttributes( { form: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Template'),
				value: props.attributes.template,
				onChange: ( val ) => { props.setAttributes( { template: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Target'),
				value: props.attributes.target,
				onChange: ( val ) => { props.setAttributes( { target: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Engine'),
				value: props.attributes.engine,
				onChange: ( val ) => { props.setAttributes( { engine: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Method'),
				value: props.attributes.method,
				onChange: ( val ) => { props.setAttributes( { method: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Redirect URL'),
				value: props.attributes.redirect_url,
				onChange: ( val ) => { props.setAttributes( { redirect_url: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Product Field'),
				value: props.attributes.product_field,
				onChange: ( val ) => { props.setAttributes( { product_field: val } ); },
			}
		), wp.element.createElement(
			wp.components.TextControl,
			{
				label: __('Amount Field'),
				value: props.attributes.amount_field,
				onChange: ( val ) => { props.setAttributes( { amount_field: val } ); },
			}
		)] ;
	},

	/**
	 * The save function defines the way in which the different attributes should be combined
	 * into the final markup, which is then serialized by Gutenberg into post_content.
	 *
	 * The "save" property must be specified and must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @returns {Mixed} JSX Frontend HTML.
	 */
	

	save: ( props ) => {
		var options = {
			tag: 'simple_payment',
			type: 'single',
			attrs: props.attributes
		};
		return wp.shortcode.string(options); 
	},
} );
