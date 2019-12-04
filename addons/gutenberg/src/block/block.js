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

import { ToggleControl } from '@wordpress/components';
import { TextControl } from '@wordpress/components';
import { SelectControl } from '@wordpress/components';

import { Panel, PanelBody, PanelRow } from '@wordpress/components';
import { useState } from '@wordpress/element';


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
		__( 'Simple Paymnet Block' ),
		__( 'Simple Paymnet' ),
		__( 'simple-payment' ),
		__( 'checkout' ),
		__( 'Payment' ),
	],
	attributes: {
		id: { type: 'integer' },
		amount: { type: 'string' },
		product: { type: 'string' },
		title: { type: 'string' },
		fixed: { type: 'boolean' },
		type: { type: 'string' },
		form: { type: 'string' },
		template: { type: 'string' },
		target: { type: 'string' },
		engine: { type: 'string' },
		method: { type: 'string' },
		redirect_url: { type: 'string' },
		amount_field: { type: 'string' },
		product_field: { type: 'string' },
		enable_query: { type: 'boolean' }
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
		function onChange( value, name ) {
			var attr = new Object();
			attr[name] = value;
			props.setAttributes(attr);
			jQuery('[data-block="' + props.clientId + '"] > .simple-payment-block').attr('data-block-' + name, value);
		};
		var engines = [{ value: '', label: '' }];
		spGlobal['Engines'].forEach(function(engine) {
			engines.push({value: engine, label: engine}); 
		});
		var currencies = [{value: '', label: ''}];
		for (var prop in spGlobal['Currencies']) {
			currencies.push({value: prop, label: prop }); 
		};
		var attrs = new Object();
		for (var prop in props.attributes) {
			attrs['data-block-' + prop] = props.attributes[prop];
		}
		return(
			<div class="simple-payment-block" {...attrs}>
				<TextControl
					label={ __('Product') }
					value={ props.attributes.product }
					onChange={ ( val ) => { onChange(val, 'product'); } }
				/>

				<PanelRow>
					<TextControl
						className="simple-payment-block-amount"
						label={  __('Amount') }
						value={ props.attributes.amount }
						onChange={ ( val ) => { onChange(val, 'amount'); } }
					/>

					<SelectControl
						label={ __('Currency') }
						value={ props.attributes.currency }
						options={ currencies }
						onChange={ ( val ) => { onChange(val, 'currency'); } }
					/>

					<TextControl
						className="simple-payment-block-id"
						label={  __('Product ID') }
						value={ props.attributes.id }
						onChange={ ( val ) => { onChange(val, 'id'); } }
					/>
				</PanelRow>

				<PanelRow>
					<SelectControl
						label={ __('Display') }
						value={ props.attributes.display }
						options={ [
							{ value: '', label: ''},
							{ value: 'redirect', label: 'Redirect'},
							{ value: 'iframe', label: 'IFRAME'},
							{ value: 'modal', label: 'Modal'},
						] }
						onChange={ ( val ) => { onChange(val, 'display'); } }
					/>	

					<SelectControl
						label={ __('Type') }
						value={ props.attributes.type }
						options={ [
							{ value: '', label: ''},
							{ value: 'form', label: 'Form'},
							{ value: 'button', label: 'Button'},
							{ value: 'template', label: 'Template'},
							{ value: 'hidden', label: 'Hidden'},
						] }
						onChange={ ( val ) => { onChange(val, 'type'); } }
					/>

					<SelectControl
						label={ __('Target') }
						value={ props.attributes.target }
						options={ [
							{ value: '', label: ''},
							{ value: '_top', label: '_top'},
							{ value: '_parent', label: '_parent'},
							{ value: '_self', label: '_self'},
							{ value: '_blank', label: '_blank'},
						] }
						onChange={ ( val ) => { onChange(val, 'target'); } }
					/>	
					
				</PanelRow>

				<TextControl
					className="simple-payment-block-template"
					label={  __('Temlpate') }
					value={ props.attributes.template }
					onChange={ ( val ) => { onChange(val, 'template'); } }
				/>

				<SelectControl
					className="simple-payment-block-form"
					label={ __('Form') }
					value={ props.attributes.form }
					options={ [
						{ value: '', label: ''},
						{ value: 'legacy', label: 'Legacy'},
						{ value: 'bootstrap-basic', label: 'Bootstrap Basic'},
						{ value: 'bootstrap', label: 'Bootstrap'},
						{ value: 'donation', label: 'Donation'},
					] }
					onChange={ ( val ) => { onChange(val, 'form'); } }
				/>

				<TextControl
					label={  __('Redirect URL') }
					value={ props.attributes.redirect_url }
					onChange={ ( val ) => { onChange(val, 'redirect_url'); } }
				/>

				<Panel>
					<PanelBody
						title={ __('Advanced Options') }
						initialOpen={ false }
					>
						<PanelRow>
							<SelectControl
								label={ __('Engine') }
								value={ props.attributes.engine }
								options={ engines }
								onChange={ ( val ) => { onChange(val, 'engine'); } }
							/>

							<TextControl
								label={ __('Payment Gateway Method') }
								value={ props.attributes.method }
								onChange={ ( val ) => { onChange(val, 'method'); } }
							/>
						</PanelRow>

						<PanelRow>
							<ToggleControl
								label={ __('Installments') }
								checked={ props.attributes.installments }
								onChange={ ( val ) => { onChange(val, 'installments'); } }
							/>

							<ToggleControl
								label={ __('Enable Query Parameters') }
								checked={ props.attributes.enable_query }
								onChange={ ( val ) => { onChange(val, 'enable_query'); } }
							/>
						</PanelRow>

						<PanelRow>
							<TextControl
								label={ __('Product Field') }
								value={ props.attributes.product_field }
								onChange={ ( val ) => { onChange(val, 'product_field'); } }
							/>
							
							<TextControl
								label={ __('Amount Field') }
								value={ props.attributes.amount_field }
								onChange={ ( val ) => { onChange(val, 'amount_field'); } }
							/>
						</PanelRow>
						
					</PanelBody>
				</Panel>
			</div>
		);
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

	transforms: {
		from: [
			{
				type: 'shortcode',
				tag: 'simple_payment',
				attributes: {
					amount: {
						type: 'string',
						source: 'attribute',
						attribute: 'amount'
					},
					/*// An attribute can be source from the shortcode attributes
					align: {
						type: 'string',
						shortcode: function( attributes ) {
							var align = attributes.named.align ? attributes.named.align : 'alignnone';
							return align.replace( 'align', '' );
						},
					},*/
				},
			},
		],
		to: [
			{
				type: 'bloack',
				blocks: [ 'core/shortcode' ],
				transform: function( attributes ) {
					return createBlock( 'core/shortcode', {
						attributes: attributes,
					} );
				},
			},
		],
	},
} );
