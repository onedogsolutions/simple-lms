<?php
/**
 * Registration for the LMS Account Dashboard module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMSAccountDashboardModule
 */
class LMSAccountDashboardModule extends \FLBuilderModule {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'LMS Account Dashboard', 'simple-lms-bridge' ),
				'description'     => __( 'Displays the user account dashboard with profile, orders, and certificates.', 'simple-lms-bridge' ),
				'category'        => __( 'SimpleLMS', 'simple-lms-bridge' ),
				'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-account-dashboard/',
				'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-account-dashboard/',
				'icon'            => 'id.svg', // Ensure this icon exists or pick another.
				'editor_export'   => true,
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);
	}
}

/**
 * Register the module.
 */
\FLBuilder::register_module(
	'SimpleLMS\LMSAccountDashboardModule',
	array(
		'style' => array(
			'title'    => __( 'Style', 'simple-lms-bridge' ),
			'sections' => array(
				'tabs_style'     => array(
					'title'  => __( 'Tabs', 'simple-lms-bridge' ),
					'fields' => array(
						'tab_bg_color'        => array(
							'type'        => 'color',
							'label'       => __( 'Tab Background Color', 'simple-lms-bridge' ),
							'default'     => '#f1f1f1',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav li',
								'property' => 'background-color',
							),
						),
						'tab_active_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Active Tab Background', 'simple-lms-bridge' ),
							'default'     => '#ffffff',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav li.active',
								'property' => 'background-color',
							),
						),
						'tab_text_color'      => array(
							'type'        => 'color',
							'label'       => __( 'Tab Text Color', 'simple-lms-bridge' ),
							'default'     => '#333333',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav li a',
								'property' => 'color',
							),
						),
					),
				),
				'form_style'     => array(
					'title'  => __( 'Form Fields', 'simple-lms-bridge' ),
					'fields' => array(
						'input_bg_color'   => array(
							'type'        => 'color',
							'label'       => __( 'Input Background', 'simple-lms-bridge' ),
							'default'     => '#ffffff',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tab-content input[type="text"], .slms-tab-content select',
								'property' => 'background-color',
							),
						),
						'input_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Input Text Color', 'simple-lms-bridge' ),
							'default'     => '#333333',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tab-content input[type="text"], .slms-tab-content select',
								'property' => 'color',
							),
						),
					),
				),
				'readonly_style' => array(
					'title'  => __( 'Read Only Fields', 'simple-lms-bridge' ),
					'fields' => array(
						'ro_bg_color'   => array(
							'type'        => 'color',
							'label'       => __( 'Background Color', 'simple-lms-bridge' ),
							'default'     => '#e9e9e9',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-read-only-value',
								'property' => 'background-color',
							),
						),
						'ro_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Text Color', 'simple-lms-bridge' ),
							'default'     => '#666666',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-read-only-value',
								'property' => 'color',
							),
						),
					),
				),
				'button_style'   => array(
					'title'  => __( 'Buttons', 'simple-lms-bridge' ),
					'fields' => array(
						'btn_bg_color'   => array(
							'type'        => 'color',
							'label'       => __( 'Button Background', 'simple-lms-bridge' ),
							'default'     => '#0073aa',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tab-content input[type="submit"]',
								'property' => 'background-color',
							),
						),
						'btn_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Button Text Color', 'simple-lms-bridge' ),
							'default'     => '#ffffff',
							'show_reset'  => true,
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tab-content input[type="submit"]',
								'property' => 'color',
							),
						),
					),
				),
			),
		),
	)
);
