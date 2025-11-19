<?php

namespace ElementPack\Modules\ImageMasking;

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
		return 'bdt-image-masking';
	}

	public function register_controls($widget) {


		$args = self::widget_to_args_map( $widget->get_name() );

		$widget->start_injection( [
			'type' => 'control',
			'at' => $args['at'],
			'of' => $args['of'],
		] );

		$widget->add_control(
			'image_mask_popover',
			[ 
				'label'        => BDTEP_CP . esc_html__( 'Image Masking', 'bdthemes-element-pack' ) . BDTEP_NC,
				'type'         => Controls_Manager::POPOVER_TOGGLE,
				'render_type'  => 'template',
				'return_value' => 'yes',
				'prefix_class' => 'bdt-image-masking-',
			]
		);

		$widget->start_popover();

		$widget->add_control(
			'image_mask_shape',
			[
				'label'     => esc_html__('Masking Shape', 'bdthemes-element-pack'),
				'title'     => esc_html__('Masking Shape', 'bdthemes-element-pack'),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'default',
				'options'   => [
					'default' => [
						'title' => esc_html__('Default Shapes', 'bdthemes-element-pack'),
						'icon'  => 'eicon-star',
					],
					'custom'  => [
						'title' => esc_html__('Custom Shape', 'bdthemes-element-pack'),
						'icon'  => 'eicon-image-bold',
					],
				],
				'toggle'    => false,
				'condition' => [
					'image_mask_popover' => 'yes',
				],
			]
		);

		$widget->add_control(
			'image_mask_shape_default',
			[
				'label'          => _x('Default', 'Mask Image', 'bdthemes-element-pack'),
				'label_block'    => true,
				'show_label'     => false,
				'type'           => Controls_Manager::VISUAL_CHOICE,
				'columns'        => 4,
				'default'        => 'shape-1',
				'options'        => element_pack_mask_shapes_options(),
				'selectors'      => [
					'{{WRAPPER}} ' . $args['selector'] . ' img' => '-webkit-mask-image: url('.BDTEP_ASSETS_URL . 'images/mask/'.'{{VALUE}}.svg); mask-image: url('.BDTEP_ASSETS_URL . 'images/mask/'.'{{VALUE}}.svg);',
					'{{WRAPPER}} ' . $args['selector'] . ':before' => 'background-image: url('.BDTEP_ASSETS_URL . 'images/mask/color-'.'{{VALUE}}.svg);',
				],
				'condition'      => [
					'image_mask_popover' => 'yes',
					'image_mask_shape'   => 'default',
				],
				'style_transfer' => true,
			]
		);

		$widget->add_control(
			'image_mask_shape_custom',
			[
				'label'      => _x('Custom Shape', 'Mask Image', 'bdthemes-element-pack'),
				'type'       => Controls_Manager::MEDIA,
				'dynamic'    => ['active' => true],
				'show_label' => false,
				'selectors'  => [
					'{{WRAPPER}} ' . $args['selector'] . ' img' => '-webkit-mask-image: url({{URL}}); mask-image: url({{URL}});',
				],
				'condition'  => [
					'image_mask_popover' => 'yes',
					'image_mask_shape'   => 'custom',
				],
			]
		);

		$widget->add_control(
			'image_mask_shape_position',
			[
				'label'                => esc_html__('Position', 'bdthemes-element-pack'),
				'type'                 => Controls_Manager::SELECT,
				'default'              => 'center-center',
				'options'              => [
					'center-center' => esc_html__('Center Center', 'bdthemes-element-pack'),
					'center-left'   => esc_html__('Center Left', 'bdthemes-element-pack'),
					'center-right'  => esc_html__('Center Right', 'bdthemes-element-pack'),
					'top-center'    => esc_html__('Top Center', 'bdthemes-element-pack'),
					'top-left'      => esc_html__('Top Left', 'bdthemes-element-pack'),
					'top-right'     => esc_html__('Top Right', 'bdthemes-element-pack'),
					'bottom-center' => esc_html__('Bottom Center', 'bdthemes-element-pack'),
					'bottom-left'   => esc_html__('Bottom Left', 'bdthemes-element-pack'),
					'bottom-right'  => esc_html__('Bottom Right', 'bdthemes-element-pack'),
				],
				'selectors_dictionary' => [
					'center-center' => 'center center',
					'center-left'   => 'center left',
					'center-right'  => 'center right',
					'top-center'    => 'top center',
					'top-left'      => 'top left',
					'top-right'     => 'top right',
					'bottom-center' => 'bottom center',
					'bottom-left'   => 'bottom left',
					'bottom-right'  => 'bottom right',
				],
				'selectors'            => [
					'{{WRAPPER}} ' . $args['selector'] . ' img' => '-webkit-mask-position: {{VALUE}}; mask-position: {{VALUE}};',
				],
				'condition'            => [
					'image_mask_popover' => 'yes',
				],
			]
		);

		$widget->add_control(
			'image_mask_shape_size',
			[
				'label'     => esc_html__('Size', 'bdthemes-element-pack'),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'contain',
				'options'   => [
					'auto'    => esc_html__('Auto', 'bdthemes-element-pack'),
					'cover'   => esc_html__('Cover', 'bdthemes-element-pack'),
					'contain' => esc_html__('Contain', 'bdthemes-element-pack'),
					'initial' => esc_html__('Custom', 'bdthemes-element-pack'),
				],
				'selectors' => [
					'{{WRAPPER}} ' . $args['selector'] . ' img' => '-webkit-mask-size: {{VALUE}}; mask-size: {{VALUE}};',
				],
				'condition' => [
					'image_mask_popover' => 'yes',
				],
			]
		);

		$widget->add_responsive_control(
			'image_mask_shape_custom_size',
			[
				'label'      => _x('Custom Size', 'Mask Image', 'bdthemes-element-pack'),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => ['px', 'em', '%', 'vw'],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 1000,
					],
					'em' => [
						'min' => 0,
						'max' => 100,
					],
					'%'  => [
						'min' => 0,
						'max' => 100,
					],
					'vw' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default'    => [
					'size' => 100,
					'unit' => '%',
				],
				'required'   => true,
				'selectors'  => [
					'{{WRAPPER}} ' . $args['selector'] . ' img' => '-webkit-mask-size: {{SIZE}}{{UNIT}}; mask-size: {{SIZE}}{{UNIT}};',
				],
				'condition'  => [
					'image_mask_popover'    => 'yes',
					'image_mask_shape_size' => 'initial',
				],
			]
		);

		$widget->add_control(
			'image_mask_shape_repeat',
			[
				'label'                => esc_html__('Repeat', 'bdthemes-element-pack'),
				'type'                 => Controls_Manager::SELECT,
				'default'              => 'no-repeat',
				'options'              => [
					'repeat'          => esc_html__('Repeat', 'bdthemes-element-pack'),
					'repeat-x'        => esc_html__('Repeat-x', 'bdthemes-element-pack'),
					'repeat-y'        => esc_html__('Repeat-y', 'bdthemes-element-pack'),
					'space'           => esc_html__('Space', 'bdthemes-element-pack'),
					'round'           => esc_html__('Round', 'bdthemes-element-pack'),
					'no-repeat'       => esc_html__('No-repeat', 'bdthemes-element-pack'),
					'repeat-space'    => esc_html__('Repeat Space', 'bdthemes-element-pack'),
					'round-space'     => esc_html__('Round Space', 'bdthemes-element-pack'),
					'no-repeat-round' => esc_html__('No-repeat Round', 'bdthemes-element-pack'),
				],
				'selectors_dictionary' => [
					'repeat'          => 'repeat',
					'repeat-x'        => 'repeat-x',
					'repeat-y'        => 'repeat-y',
					'space'           => 'space',
					'round'           => 'round',
					'no-repeat'       => 'no-repeat',
					'repeat-space'    => 'repeat space',
					'round-space'     => 'round space',
					'no-repeat-round' => 'no-repeat round',
				],
				'selectors'            => [
					'{{WRAPPER}} ' . $args['selector'] . ' img' => '-webkit-mask-repeat: {{VALUE}}; mask-repeat: {{VALUE}};',
				],
				'condition'            => [
					'image_mask_popover' => 'yes',
				],
			]
		);

		$widget->end_popover();

		$widget->end_injection();
	}

	/**
     * @param string $widget_name
     * @return mixed
     */
	public static function widget_to_args_map( $widget_name = '' ) {
		$map = [
			'image' => [
				'at' => 'after',
				'of' => 'image',
				'selector' => '',
				'condition' => []
			],
			'image-box' => [
				'at' => 'after',
				'of' => 'image',
				'selector' => '.elementor-image-box-img',
				'condition' => []
			],
			'image-carousel' => [
				'at' => 'after',
				'of' => 'carousel',
				'selector' => '.swiper-slide-inner',
				'condition' => []
			],
		];

		return $map[ $widget_name ];
	}

	protected function add_actions() {
		add_action( 'elementor/element/image-box/section_image/before_section_end', [ $this, 'register_controls' ], 10, 2 );
		add_action( 'elementor/element/image-carousel/section_image_carousel/before_section_end', [ $this, 'register_controls' ], 10, 2 );

		// Register Element Pack mask shapes with Elementor's native masking system
		add_filter( 'elementor/mask_shapes/additional_shapes', [ $this, 'register_elementor_mask_shapes' ] );
		add_action( 'init', [ $this, 'copy_mask_shapes_to_elementor' ] );
	}

	/**
	 * Register Element Pack mask shapes with Elementor's native masking system
	 * 
	 * @param array $additional_mask_shapes Additional mask shapes array
	 * @return array Modified mask shapes array
	 */
	public function register_elementor_mask_shapes( $additional_mask_shapes ) {
		$ep_mask_shapes = [];
		
		// Get all available mask shapes from Element Pack
		$shape_list = element_pack_mask_shapes();
		
		foreach ( $shape_list as $shape_key => $shape_name ) {
			// Skip the first item if it's a placeholder
			if ( $shape_key === 0 ) {
				continue;
			}
			
			$ep_mask_shapes[ $shape_key ] = [
				'title' => esc_html__( 'EP ', 'bdthemes-element-pack' ) . $shape_name,
				'image' => BDTEP_ASSETS_URL . 'images/mask/' . $shape_key . '.svg',
			];
		}

		// Merge our shapes with the existing additional shapes
		return array_merge( $additional_mask_shapes, $ep_mask_shapes );
	}

	/**
	 * Copy Element Pack mask shapes to Elementor's assets directory
	 */
	public function copy_mask_shapes_to_elementor() {
		// Only run once per session
		static $copied = false;
		if ( $copied ) {
			return;
		}
		$copied = true;
		
		$elementor_mask_dir = ELEMENTOR_PATH . 'assets/mask-shapes/';
		
		// Create directory if it doesn't exist
		if ( ! file_exists( $elementor_mask_dir ) ) {
			wp_mkdir_p( $elementor_mask_dir );
		}
		
		$shape_list = element_pack_mask_shapes();
		
		foreach ( $shape_list as $shape_key => $shape_name ) {
			// Skip the first item if it's a placeholder
			if ( $shape_key === 0 ) {
				continue;
			}
			
			$source_file = BDTEP_PATH . 'assets/images/mask/' . $shape_key . '.svg';
			$dest_file = $elementor_mask_dir . $shape_key . '.svg';
			
			// Copy file if source exists and destination doesn't exist
			if ( file_exists( $source_file ) && ! file_exists( $dest_file ) ) {
				copy( $source_file, $dest_file );
			}
		}
		
	}
}

