<?php
/**
 * LMS Account Dashboard – Beaver Builder Module
 *
 * Original account dashboard module, upgraded in-place to the native tabbed
 * UI (profile, purchase history, certificates) so existing BB page layouts
 * continue working without a manual module swap.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

/**
 * @class LMSAccountDashboardModule
 */
class LMSAccountDashboardModule extends \FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'LMS Account Dashboard', 'simple-lms' ),
				'description'     => __( 'Displays the student profile, purchase history, and certificates.', 'simple-lms' ),
				'category'        => __( 'SimpleLMS', 'simple-lms' ),
				'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-account-dashboard/',
				'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-account-dashboard/',
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
			'title'    => __( 'Tabs Style', 'simple-lms' ),
			'sections' => array(

				'tab_colors' => array(
					'title'  => __( 'Tab Colors', 'simple-lms' ),
					'fields' => array(

						'tab_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Tab Background', 'simple-lms' ),
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
							'label'       => __( 'Active Tab Background', 'simple-lms' ),
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
							'label'       => __( 'Tab Text Color', 'simple-lms' ),
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
							'label'       => __( 'Active Tab Text Color', 'simple-lms' ),
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
							'label'       => __( 'Tab Hover Background', 'simple-lms' ),
							'default'     => '',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'tab_hover_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Tab Hover Text Color', 'simple-lms' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

					),
				),

				'tab_typography_section' => array(
					'title'     => __( 'Typography', 'simple-lms' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_typography' => array(
							'type'       => 'typography',
							'label'      => __( 'Tab Typography', 'simple-lms' ),
							'responsive' => true,
						),

					),
				),

				'tab_spacing_section' => array(
					'title'     => __( 'Padding & Margin', 'simple-lms' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_padding' => array(
							'type'       => 'dimension',
							'label'      => __( 'Tab Padding', 'simple-lms' ),
							'responsive' => true,
							'units'      => array( 'px', 'em', '%' ),
							'slider'     => true,
						),

						'tab_margin' => array(
							'type'       => 'dimension',
							'label'      => __( 'Tab Margin', 'simple-lms' ),
							'responsive' => true,
							'units'      => array( 'px', 'em', '%' ),
							'slider'     => true,
						),

					),
				),

				'tab_border_section' => array(
					'title'     => __( 'Border', 'simple-lms' ),
					'collapsed' => true,
					'fields'    => array(

						'tab_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Tab Border', 'simple-lms' ),
							'responsive' => true,
						),

						'tab_active_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Active Tab Border', 'simple-lms' ),
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
			'title'    => __( 'Form Style', 'simple-lms' ),
			'sections' => array(

				'input_general' => array(
					'title'  => __( 'Input Fields', 'simple-lms' ),
					'fields' => array(

						'input_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Input Background', 'simple-lms' ),
							'default'     => 'ffffff',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'input_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Input Text Color', 'simple-lms' ),
							'default'     => '333333',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'input_label_color' => array(
							'type'        => 'color',
							'label'       => __( 'Label Color', 'simple-lms' ),
							'default'     => '555555',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'input_padding' => array(
							'type'       => 'dimension',
							'label'      => __( 'Input Padding', 'simple-lms' ),
							'responsive' => true,
							'units'      => array( 'px', 'em' ),
							'slider'     => true,
						),

						'input_typography' => array(
							'type'       => 'typography',
							'label'      => __( 'Input Typography', 'simple-lms' ),
							'responsive' => true,
						),

					),
				),

				'input_focus' => array(
					'title'     => __( 'Input Focus State', 'simple-lms' ),
					'collapsed' => true,
					'fields'    => array(

						'input_focus_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Focus Background Color', 'simple-lms' ),
							'default'     => '',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'input_focus_border_color' => array(
							'type'        => 'color',
							'label'       => __( 'Focus Border Color', 'simple-lms' ),
							'default'     => '719ece',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'input_focus_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Focus Text Color', 'simple-lms' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

					),
				),

				'input_border' => array(
					'title'     => __( 'Input Border & Shadow', 'simple-lms' ),
					'collapsed' => true,
					'fields'    => array(

						'input_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Border', 'simple-lms' ),
							'responsive' => true,
						),

					),
				),

				'button_style' => array(
					'title'     => __( 'Button', 'simple-lms' ),
					'collapsed' => true,
					'fields'    => array(

						'button_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Background Color', 'simple-lms' ),
							'default'     => '0073aa',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'button_hover_bg_color' => array(
							'type'        => 'color',
							'label'       => __( 'Background Hover Color', 'simple-lms' ),
							'default'     => '005177',
							'show_reset'  => true,
							'show_alpha'  => true,
							'connections' => array( 'color' ),
						),

						'button_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Text Color', 'simple-lms' ),
							'default'     => 'ffffff',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'button_hover_text_color' => array(
							'type'        => 'color',
							'label'       => __( 'Text Hover Color', 'simple-lms' ),
							'default'     => '',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),

						'button_padding' => array(
							'type'       => 'dimension',
							'label'      => __( 'Button Padding', 'simple-lms' ),
							'responsive' => true,
							'units'      => array( 'px', 'em' ),
							'slider'     => true,
						),

						'button_border_group' => array(
							'type'       => 'border',
							'label'      => __( 'Button Border', 'simple-lms' ),
							'responsive' => true,
						),

						'button_typography' => array(
							'type'       => 'typography',
							'label'      => __( 'Button Typography', 'simple-lms' ),
							'responsive' => true,
						),

					),
				),

			),
		),

	)
);
