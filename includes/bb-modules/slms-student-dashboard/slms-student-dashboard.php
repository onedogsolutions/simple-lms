<?php

namespace SimpleLMS;

/**
 * @class SLMSStudentDashboardModule
 *
 * Native Beaver Builder module replacing:
 *   - PowerPack Advanced Tabs
 *   - PowerPack Gravity Form
 *   - Gravity Perks Entry Blocks
 */
class SLMSStudentDashboardModule extends \FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'Student Dashboard', 'simple-lms-bridge' ),
				'description'     => __( 'Consolidated student profile, purchase history, and certificates.', 'simple-lms-bridge' ),
				'category'        => __( 'SimpleLMS', 'simple-lms-bridge' ),
				'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/slms-student-dashboard/',
				'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/slms-student-dashboard/',
				'editor_export'   => true,
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);
	}
}

\FLBuilder::register_module(
	'SimpleLMS\SLMSStudentDashboardModule',
	array(

		// ─────────────────────────────────────────────────────────────
		// TAB: Tabs Style
		// ─────────────────────────────────────────────────────────────
		'tabs_style' => array(
			'title'    => __( 'Tabs Style', 'simple-lms-bridge' ),
			'sections' => array(

				// Section: Tab Colors
				'tab_colors'             => array(
					'title'  => __( 'Tab Colors', 'simple-lms-bridge' ),
					'fields' => array(

						'tab_bg_color'          => array(
							'type'        => 'color',
							'label'       => __( 'Tab Background', 'simple-lms-bridge' ),
							'default'     => 'f5f5f5',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link',
								'property' => 'background-color',
							),
						),

						'tab_active_bg_color'   => array(
							'type'        => 'color',
							'label'       => __( 'Active Tab Background', 'simple-lms-bridge' ),
							'default'     => 'ffffff',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link.active',
								'property' => 'background-color',
							),
						),

						'tab_text_color'        => array(
							'type'        => 'color',
							'label'       => __( 'Tab Text Color', 'simple-lms-bridge' ),
							'default'     => '333333',
							'show_reset'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link',
								'property' => 'color',
							),
						),

						'tab_active_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Active Tab Text Color', 'simple-lms-bridge' ),
							'default'     => '000000',
							'show_reset'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link.active',
								'property' => 'color',
							),
						),

						'tab_hover_bg_color'    => array(
							'type'        => 'color',
							'label'       => __( 'Tab Hover Background', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'tab_hover_text_color'  => array(
							'type'        => 'color',
							'label'       => __( 'Tab Hover Text Color', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

					),
				),

				// Section: Tab Typography
				'tab_typography_section' => array(
					'title'     => __( 'Typography', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_typography' => array(
							'type'       => 'typography',
							'label'      => __( 'Tab Typography', 'simple-lms-bridge' ),
							'responsive' => true,
						),

					),
				),

				// Section: Tab Padding & Margin
				'tab_spacing_section'    => array(
					'title'     => __( 'Padding & Margin', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_padding' => array(
							'type'       => 'dimension',
							'label'      => __( 'Tab Padding', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em', '%' ),
							'slider'     => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link',
								'property' => 'padding',
								'unit'     => 'px',
							),
						),

						'tab_margin'  => array(
							'type'       => 'dimension',
							'label'      => __( 'Tab Margin', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em', '%' ),
							'slider'     => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link',
								'property' => 'margin',
								'unit'     => 'px',
							),
						),

					),
				),

				// Section: Tab Border
				'tab_border_section'     => array(
					'title'     => __( 'Border', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_border_group'        => array(
							'type'       => 'border',
							'label'      => __( 'Tab Border', 'simple-lms-bridge' ),
							'responsive' => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link',
							),
						),

						'tab_active_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Active Tab Border', 'simple-lms-bridge' ),
							'responsive' => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-tabs-nav .slms-tab-link.active',
							),
						),

					),
				),

			),
		),

		// ─────────────────────────────────────────────────────────────
		// TAB: Form Style
		// ─────────────────────────────────────────────────────────────
		'form_style' => array(
			'title'    => __( 'Form Style', 'simple-lms-bridge' ),
			'sections' => array(

				// Section: Input Fields
				'input_general' => array(
					'title'  => __( 'Input Fields', 'simple-lms-bridge' ),
					'fields' => array(

						'input_bg_color'    => array(
							'type'        => 'color',
							'label'       => __( 'Input Background', 'simple-lms-bridge' ),
							'default'     => 'ffffff',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-input',
								'property' => 'background-color',
							),
						),

						'input_text_color'  => array(
							'type'        => 'color',
							'label'       => __( 'Input Text Color', 'simple-lms-bridge' ),
							'default'     => '333333',
							'show_reset'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-input',
								'property' => 'color',
							),
						),

						'input_label_color' => array(
							'type'        => 'color',
							'label'       => __( 'Label Color', 'simple-lms-bridge' ),
							'default'     => '555555',
							'show_reset'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-field-label',
								'property' => 'color',
							),
						),

						'input_padding'     => array(
							'type'       => 'dimension',
							'label'      => __( 'Input Padding', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em' ),
							'slider'     => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-input',
								'property' => 'padding',
								'unit'     => 'px',
							),
						),

						'input_typography'  => array(
							'type'       => 'typography',
							'label'      => __( 'Input Typography', 'simple-lms-bridge' ),
							'responsive' => true,
						),

					),
				),

				// Section: Input Focus State
				'input_focus'   => array(
					'title'     => __( 'Input Focus State', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'input_focus_bg_color'     => array(
							'type'        => 'color',
							'label'       => __( 'Focus Background Color', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'input_focus_border_color' => array(
							'type'        => 'color',
							'label'       => __( 'Focus Border Color', 'simple-lms-bridge' ),
							'default'     => '719ece',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'input_focus_text_color'   => array(
							'type'        => 'color',
							'label'       => __( 'Focus Text Color', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

					),
				),

				// Section: Input Border
				'input_border'  => array(
					'title'     => __( 'Input Border & Shadow', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'input_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Border', 'simple-lms-bridge' ),
							'responsive' => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-input',
							),
						),

					),
				),

				// Section: Button
				'button_style'  => array(
					'title'     => __( 'Button', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'button_bg_color'         => array(
							'type'        => 'color',
							'label'       => __( 'Background Color', 'simple-lms-bridge' ),
							'default'     => '0073aa',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-submit-btn',
								'property' => 'background-color',
							),
						),

						'button_hover_bg_color'   => array(
							'type'        => 'color',
							'label'       => __( 'Background Hover Color', 'simple-lms-bridge' ),
							'default'     => '005177',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'button_text_color'       => array(
							'type'        => 'color',
							'label'       => __( 'Text Color', 'simple-lms-bridge' ),
							'default'     => 'ffffff',
							'show_reset'  => true,
							'connections' => array( 'color' ),
							'preview'     => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-submit-btn',
								'property' => 'color',
							),
						),

						'button_hover_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Text Hover Color', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'button_padding'          => array(
							'type'       => 'dimension',
							'label'      => __( 'Button Padding', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em' ),
							'slider'     => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-submit-btn',
								'property' => 'padding',
								'unit'     => 'px',
							),
						),

						'button_border_group'     => array(
							'type'       => 'border',
							'label'      => __( 'Button Border', 'simple-lms-bridge' ),
							'responsive' => true,
							'preview'    => array(
								'type'     => 'css',
								'selector' => '.slms-profile-form .slms-submit-btn',
							),
						),

						'button_typography'       => array(
							'type'       => 'typography',
							'label'      => __( 'Button Typography', 'simple-lms-bridge' ),
							'responsive' => true,
						),

					),
				),

			),
		),

	)
);
