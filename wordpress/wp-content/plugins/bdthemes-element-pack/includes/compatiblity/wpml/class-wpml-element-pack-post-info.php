<?php
namespace ElementPack\Includes;

/**
 * Class WPML_ElementPack_Accordion
 */
if (!defined('ABSPATH')) exit; // Exit if accessed directly
class WPML_ElementPack_Post_Info extends WPML_Module_With_Items {

	/**
	 * @return string
	 */
	public function get_items_field() {
		return 'icon_list';
	}

	/**
	 * @return array
	 */
	public function get_fields() {
		return array( 'custom_text', 'custom_url' => ['url'] );
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_title( $field ) {
		switch( $field ) {

			case 'custom_text':
				return esc_html__( 'Custom Text', 'bdthemes-element-pack' );

			case 'custom_url':
				return esc_html__( 'Custom URL', 'bdthemes-element-pack' );

			default:
				return '';
		}
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_editor_type( $field ) {
		switch( $field ) {
			case 'custom_text':
				return 'LINE';

			case 'custom_url':
				return 'LINK';

			default:
				return '';
		}
	}

}
