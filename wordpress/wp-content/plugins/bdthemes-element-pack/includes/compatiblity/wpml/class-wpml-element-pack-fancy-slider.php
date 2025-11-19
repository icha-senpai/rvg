<?php
namespace ElementPack\Includes;



if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Class WPML_ElementPack_Fancy_Slider
 */
class WPML_ElementPack_Fancy_Slider extends WPML_Module_With_Items {

	/**
	 * @return string
	 */
	public function get_items_field() {
		return 'slides';
	}

	/**
	 * @return array
	 */
	public function get_fields() {
		return array(
			'sub_title',
			'title',
			'description',
			'slide_button',
			'title_link' => ['url'],
			'button_link' => ['url'],
		);
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_title( $field ) {
		switch( $field ) {

			case 'sub_title':
				return esc_html__( 'Sub Title', 'bdthemes-element-pack' );

			case 'title':
				return esc_html__( 'Slide Title', 'bdthemes-element-pack' );

			case 'description':
				return esc_html__( 'Slide Description', 'bdthemes-element-pack' );

			case 'slide_button':
				return esc_html__( 'Button Text', 'bdthemes-element-pack' );

			case 'title_link':
				return esc_html__( 'Title Link', 'bdthemes-element-pack' );

			case 'button_link':
				return esc_html__( 'Button Link', 'bdthemes-element-pack' );

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
			case 'sub_title':
                return 'LINE';

			case 'title':
				return 'LINE';

			case 'description':
				return 'AREA';

			case 'slide_button':
				return 'LINE';

			case 'title_link':
				return 'LINK';

			case 'button_link':
				return 'LINK';

			default:
				return '';
		}
	}

}
