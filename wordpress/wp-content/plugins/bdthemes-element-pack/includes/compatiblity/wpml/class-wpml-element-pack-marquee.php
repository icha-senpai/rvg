<?php
namespace ElementPack\Includes;


if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Class WPML_Jet_Elements_Marquee
 */
class WPML_ElementPack_Marquee extends WPML_Module_With_Items {

	/**
	 * @return string
	 */
	public function get_items_field() {
		return 'marquee';
	}

	/**
	 * @return array
	 */
	public function get_fields() {
		return array( 
            'marquee_content',
            'marquee_link' => ['url'],
            'marquee_image_link' => ['url']
        );
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_title( $field ) {
		switch( $field ) {
			case 'marquee_content':
				return esc_html__( 'Marquee Content', 'bdthemes-element-pack' );

			case 'marquee_link':
				return esc_html__( 'Marquee Link', 'bdthemes-element-pack' );

			case 'marquee_image_link':
				return esc_html__( 'Marquee Image Link', 'bdthemes-element-pack' );

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
			case 'marquee_content':
				return 'LINE';

			case 'marquee_link':
            case 'marquee_image_link':
                // Both fields are links, so we return 'LINK' for both.
				return 'LINK';

			default:
				return '';
		}
	}

}
