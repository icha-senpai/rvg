<?php
namespace ElementPack\Includes;


if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Class WPML_ElementPack_Scrollnav
 */
class WPML_ElementPack_Scrollnav extends WPML_Module_With_Items {

	/**
	 * @return string
	 */
	public function get_items_field() {
		return 'navs';
	}

	/**
	 * @return array
	 */
	public function get_fields() {
		return array('nav_title', 'nav_link' => ['url']);
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_title($field) {
		switch ($field) {

			case 'nav_title':
				return esc_html__('Nav Title', 'bdthemes-element-pack');

			case 'nav_link':
				return esc_html__( 'Nav Link', 'bdthemes-element-pack' );

			default:
				return '';
		}
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function get_editor_type($field) {
		switch ($field) {
			case 'nav_title':
				return 'LINE';

			case 'nav_link':
				return 'LINK';

			default:
				return '';
		}
	}
}
