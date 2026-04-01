<?php
/**
 * LMS Account Dashboard – Beaver Builder Module
 *
 * This module was the original account dashboard. It has been upgraded
 * in-place to render the new slms-student-dashboard UI (tabbed profile,
 * purchase history, and certificates) so existing Beaver Builder page
 * layouts continue working without a manual module swap.
 *
 * All rendering is delegated to:
 *   includes/bb-modules/slms-student-dashboard/includes/frontend.php
 *   includes/bb-modules/slms-student-dashboard/includes/frontend.css.php
 *   includes/bb-modules/slms-student-dashboard/includes/frontend.js
 *
 * @package SimpleLMS
 */

/**
 * @class LMSAccountDashboardModule
 */
class LMSAccountDashboardModule extends FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'LMS Account Dashboard', 'simple-lms' ),
				'description'     => __( 'Displays the student profile, purchase history, and certificates.', 'simple-lms' ),
				'group'           => __( 'Simple LMS', 'simple-lms' ),
				'category'        => __( 'LMS Components', 'simple-lms' ),
				'dir'             => plugin_dir_path( __FILE__ ),
				'url'             => plugin_dir_url( __FILE__ ),
				'editor_export'   => true,
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);
	}
}

\FLBuilder::register_module(
	'SimpleLMS\LMSAccountDashboardModule',
	array(

		// ─────────────────────────────────────────────────────────────
		// TAB: Tabs Style
		// ─────────────────────────────────────────────────────────────
		'tabs_style' => array(
			'title'    => __( 'Tabs Style', 'simple-lms-bridge' ),
			'sections' => array(

				'tab_colors' => array(
					'title'  => __( 'Tab Colors', 'simple-lms-bridge' ),
					'fields' => array(

						'tab_bg_color' => array(
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

						'tab_active_bg_color' => array(
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

						'tab_text_color' => array(
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

						'tab_hover_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Tab Hover Background', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'tab_hover_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Tab Hover Text Color', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

					),
				),

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

				'tab_spacing_section' => array(
					'title'     => __( 'Padding & Margin', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_padding' => array(
							'type'       => 'dimension',
							'label'      => __( 'Tab Padding', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em', '%' ),
							'slider'     => true,
						),

						'tab_margin' => array(
							'type'       => 'dimension',
							'label'      => __( 'Tab Margin', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em', '%' ),
							'slider'     => true,
						),

					),
				),

				'tab_border_section' => array(
					'title'     => __( 'Border', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Tab Border', 'simple-lms-bridge' ),
							'responsive' => true,
						),

						'tab_active_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Active Tab Border', 'simple-lms-bridge' ),
							'responsive' => true,
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

				'input_general' => array(
					'title'  => __( 'Input Fields', 'simple-lms-bridge' ),
					'fields' => array(

						'input_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Input Background', 'simple-lms-bridge' ),
							'default'     => 'ffffff',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'input_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Input Text Color', 'simple-lms-bridge' ),
							'default'     => '333333',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'input_label_color' => array(
							'type'        => 'color',
							'label'       => __( 'Label Color', 'simple-lms-bridge' ),
							'default'     => '555555',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'input_padding' => array(
							'type'       => 'dimension',
							'label'      => __( 'Input Padding', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em' ),
							'slider'     => true,
						),

						'input_typography' => array(
							'type'       => 'typography',
							'label'      => __( 'Input Typography', 'simple-lms-bridge' ),
							'responsive' => true,
						),

					),
				),

				'input_focus' => array(
					'title'     => __( 'Input Focus State', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'input_focus_bg_color' => array(
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

						'input_focus_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Focus Text Color', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

					),
				),

				'input_border' => array(
					'title'     => __( 'Input Border & Shadow', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'input_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Border', 'simple-lms-bridge' ),
							'responsive' => true,
						),

					),
				),

				'button_style' => array(
					'title'     => __( 'Button', 'simple-lms-bridge' ),
					'collapsed' => true,
					'fields'    => array(

						'button_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Background Color', 'simple-lms-bridge' ),
							'default'     => '0073aa',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'button_hover_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Background Hover Color', 'simple-lms-bridge' ),
							'default'     => '005177',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'button_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Text Color', 'simple-lms-bridge' ),
							'default'     => 'ffffff',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'button_hover_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Text Hover Color', 'simple-lms-bridge' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'button_padding' => array(
							'type'       => 'dimension',
							'label'      => __( 'Button Padding', 'simple-lms-bridge' ),
							'responsive' => true,
							'units'      => array( 'px', 'em' ),
							'slider'     => true,
						),

						'button_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Button Border', 'simple-lms-bridge' ),
							'responsive' => true,
						),

						'button_typography' => array(
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
