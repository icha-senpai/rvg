<?php
namespace ElementPack\Includes;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Interactive Tabs integration
 */
class WPML_ElementPack_Interactive_Tabs extends WPML_Module_With_Items {

	/**
	 * @return string
	 */
	public function get_items_field() {
		return 'tabs';
	}

	/**
	 * @return array
	 */
	public function get_fields() {
		return array(
			'tab_title',
			'tab_sub_title',
			'tab_text',
			'video_link',
			'youtube_link',
			'image_link' => ['url'],
		);
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_title( $field ) {
		switch ( $field ) {
			case 'tab_title':
				return esc_html__( 'Tab Title', 'bdthemes-element-pack' );

			case 'tab_sub_title':
				return esc_html__( 'Sub Title', 'bdthemes-element-pack' );

			case 'tab_text':
				return esc_html__( 'Text Content', 'bdthemes-element-pack' );

			case 'video_link':
				return esc_html__( 'Video Link', 'bdthemes-element-pack' );

			case 'youtube_link':
				return esc_html__( 'YouTube Link', 'bdthemes-element-pack' );

			case 'image_link':
				return esc_html__( 'Image Link', 'bdthemes-element-pack' );

			default:
				return '';
		}
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_editor_type( $field ) {
		switch ( $field ) {
			case 'tab_title':
			case 'tab_sub_title':
			case 'video_link':
			case 'youtube_link':
				return 'LINE';

			case 'tab_text':
				return 'AREA';

			case 'image_link':
				return 'LINK';

			default:
				return '';
		}
	}
} 