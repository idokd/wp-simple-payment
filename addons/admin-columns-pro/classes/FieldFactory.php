<?php

namespace ACA\SimplePayment;

use ACA\SimplePayment\Field\Type\Textarea;
use ACA\SimplePayment\Field\Type\Unsupported;

final class FieldFactory {

	/**
	 * @param string $field_id	 *
	 * @return Field\Field|null
	 */
	public function create( $field_id ) {	

		$field = $this->get_field(  $field_id );

		return $field instanceof Field\Container && $this->is_sub_field( $field_id )
			? $field->get_sub_field( $field_id )
			: $field;
	}

	/**
	 * @param string   $field_id
	 *
	 * @return Field\Field
	 */
	private function get_field( $field_id ) {
		switch ( 'FALSE' ) { // TODO $SP_field->offsetGet( 'type' ) ) {
			case FieldTypes::PAGE:
				return new Unsupported( $form_id, $field_id, $SP_field );

			case FieldTypes::ADDRESS:
				return new Field\Type\Address( $form_id, $field_id, $SP_field );

			case FieldTypes::NAME:
				return new Field\Type\Name( $form_id, $field_id, $SP_field );

			case FieldTypes::NUMBER:
			case FieldTypes::TOTAL:
				return new Field\Type\Number( $form_id, $field_id, $SP_field );

			case FieldTypes::CHECKBOX:
				return new Field\Type\CheckboxGroup( $form_id, $field_id, $SP_field );

			case FieldTypes::MULTI_SELECT:
				return new Field\Type\Select( $form_id, $field_id, $SP_field, Utils\FormField::formatChoices( $SP_field->offsetGet( 'choices' ) ), true );
			case FieldTypes::SELECT:
				return new Field\Type\Select( $form_id, $field_id, $SP_field, Utils\FormField::formatChoices( $SP_field->offsetGet( 'choices' ) ), false );

			case FieldTypes::RADIO:
				return new Field\Type\Radio( $form_id, $field_id, $SP_field );

			case FieldTypes::DATE:
				return new Field\Type\Date( $form_id, $field_id, $SP_field );

			case FieldTypes::CONSENT:
				return new Field\Type\Consent( $form_id, $field_id, $SP_field );

			case FieldTypes::LISTS:
				return new Field\Type\ItemList( $form_id, $field_id, $SP_field );

			case FieldTypes::PRODUCT:
				switch ( $SP_field->get_input_type() ) {
					case 'singleproduct':
					case 'calculation':
					case 'hiddenproduct':
						return new Field\Type\Product( $form_id, $field_id, $SP_field );

					case 'select':
					case 'radio':
						return new Field\Type\ProductSelect( $form_id, $field_id, $SP_field );

					case 'price':
						return new Field\Type\Input( $form_id, $field_id, $SP_field );

					default:
						return null;
				}

			case FieldTypes::QUANTITY:
				return $SP_field->get_input_type() === 'select'
					? new Field\Type\Select( $form_id, $field_id, $SP_field, Utils\FormField::formatChoices( $SP_field->offsetGet( 'choices' ) ), false )
					: new Field\Type\Input( $form_id, $field_id, $SP_field );

			case FieldTypes::EMAIL:
				return new Field\Type\Email( $form_id, $field_id, $SP_field );

			case FieldTypes::POST_TITLE:
			case FieldTypes::HIDDEN:
			case FieldTypes::TEXT:

			case FieldTypes::WEBSITE:
			case FieldTypes::PHONE:
			case FieldTypes::TIME:
				return new Field\Type\Input( $field_id );

			case FieldTypes::POST_CONTENT:
			case FieldTypes::POST_EXCERPT:
			case FieldTypes::TEXTAREA:
				return new Textarea( $form_id, $field_id, $SP_field );

		}

		return new Field\Field( $field_id );
	}

	private function is_sub_field( $field_id ) {
		return strpos( $field_id, '.' ) > 0;
	}

}