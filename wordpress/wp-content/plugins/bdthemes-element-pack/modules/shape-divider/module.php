<?php

namespace ElementPack\Modules\ShapeDivider;

use Elementor\Controls_Manager;
use ElementPack\Base\Element_Pack_Module_Base;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class Module extends Element_Pack_Module_Base {

	public function __construct() {
		parent::__construct();
		$this->add_actions();
	}

	public function get_name() {
		return 'bdt-shape-divider';
	}


	protected function add_actions() {
		// add_action( 'elementor/element/shape-divider/section_shape_divider/before_section_end', [ $this, 'register_controls' ], 10, 2 );
		// Register Element Pack shapes with Elementor's shape divider system
		add_filter( 'elementor/shapes/additional_shapes', [ $this, 'register_elementor_shape_dividers' ] );
		add_action( 'init', [ $this, 'copy_shape_files_to_elementor' ] );
	}

	/**
	 * Register Element Pack shapes with Elementor's shape divider system
	 * 
	 * @param array $additional_shapes Additional shapes array
	 * @return array Modified shapes array
	 */
	public function register_elementor_shape_dividers( $additional_shapes ) {
		// Add modern liquid shapes from Element Pack
		$ep_shapes = [
			'ep-audio-waveform' => [
				'title' => esc_html__( 'Audio Waveform', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-audio-waveform.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-audio-waveform.svg',
				'has_flip' => true,
				'has_negative' => true,
			],
			'ep-water-splash' => [
				'title' => esc_html__( 'Water Splash', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-water-splash.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-water-splash.svg',
				'has_flip' => true,
				'has_negative' => true,
			],
			'ep-festival' => [
				'title' => esc_html__( 'Festival', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-festival.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-festival.svg',
				'has_flip' => true,
				'has_negative' => false,
			],
			'ep-mosque' => [
				'title' => esc_html__( 'Mosque', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-mosque.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-mosque.svg',
				'has_flip' => true,
				'has_negative' => true,
			],
			'ep-christmas' => [
				'title' => esc_html__( 'Christmas Tree', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-christmas.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-christmas.svg',
				'has_flip' => true,
				'has_negative' => true,
			],
			'ep-christmas-santa' => [
				'title' => esc_html__( 'Christmas Santa', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-christmas-santa.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-christmas-santa.svg',
				'has_flip' => true,
				'has_negative' => true,
			],
			'ep-helloween' => [
				'title' => esc_html__( 'Halloween', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-helloween.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-helloween.svg',
				'has_flip' => true,
				'has_negative' => true,
			],
			'ep-hill' => [
				'title' => esc_html__( 'Hill', 'bdthemes-element-pack' ),
				'path' => ELEMENTOR_PATH . 'assets/shapes/ep-hill.svg',
				'url' => ELEMENTOR_ASSETS_URL . 'shapes/ep-hill.svg',
				'has_flip' => true,
				'has_negative' => true,
			],
			
		];

		// Merge our shapes with the existing additional shapes
		return array_merge( $additional_shapes, $ep_shapes );
	}

	/**
	 * Copy Element Pack shape files to Elementor's assets directory
	 */
	public function copy_shape_files_to_elementor() {
		// Only run once per session
		static $copied = false;
		if ( $copied ) {
			return;
		}
		$copied = true;
		
		// Check if Elementor is active
		if ( ! defined( 'ELEMENTOR_PATH' ) ) {
			return;
		}
		
		$elementor_shapes_dir = ELEMENTOR_PATH . 'assets/shapes/';
		
		// Create directory if it doesn't exist
		if ( ! file_exists( $elementor_shapes_dir ) ) {
			wp_mkdir_p( $elementor_shapes_dir );
		}
		
		// Check if directory is writable
		if ( ! is_writable( $elementor_shapes_dir ) ) {
			return;
		}
		
		// Copy shape files
		$shape_files = [
			'christmas.svg' => 'ep-christmas.svg',
			'christmas-negative.svg' => 'ep-christmas-negative.svg',
			'audio-waveform.svg' => 'ep-audio-waveform.svg',
			'audio-waveform-negative.svg' => 'ep-audio-waveform-negative.svg',
			'water-splash.svg' => 'ep-water-splash.svg',
			'water-splash-negative.svg' => 'ep-water-splash-negative.svg',
			'festival.svg' => 'ep-festival.svg',
			'mosque.svg' => 'ep-mosque.svg',
			'mosque-negative.svg' => 'ep-mosque-negative.svg',
			'christmas-santa.svg' => 'ep-christmas-santa.svg',
			'christmas-santa-negative.svg' => 'ep-christmas-santa-negative.svg',
			'helloween.svg' => 'ep-helloween.svg',
			'helloween-negative.svg' => 'ep-helloween-negative.svg',
			'hill.svg' => 'ep-hill.svg',
			'hill-negative.svg' => 'ep-hill-negative.svg',

		];
		
		foreach ( $shape_files as $source_file => $dest_file ) {
			$source_path = BDTEP_PATH . 'assets/images/shape-divider/' . $source_file;
			$dest_path = $elementor_shapes_dir . $dest_file;
			
			// Copy file if source exists and destination doesn't exist
			if ( file_exists( $source_path ) && ! file_exists( $dest_path ) ) {
				@copy( $source_path, $dest_path );
			}
		}
	}
}

