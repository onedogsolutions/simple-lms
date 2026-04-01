<?php

/**
 * @class SLMSStudentDashboardModule
 */
class SLMSStudentDashboardModule extends FLBuilderModule {

    public function __construct() {
        parent::__construct( array(
            'name'            => __( 'Student Dashboard', 'simple-lms' ),
            'description'     => __( 'Consolidated student profile, purchase history, and certificates.', 'simple-lms' ),
            'group'           => __( 'Simple LMS', 'simple-lms' ),
            'category'        => __( 'LMS Components', 'simple-lms' ),
            'dir'             => plugin_dir_path( __FILE__ ),
            'url'             => plugin_dir_url( __FILE__ ),
            'editor_export'   => true,
            'enabled'         => true,
            'partial_refresh' => true,
        ) );
    }
}

FLBuilder::register_module( 'SLMSStudentDashboardModule', array(
    'tabs_style' => array(
        'title'    => __( 'Tabs Style', 'simple-lms' ),
        'sections' => array(
            'general' => array(
                'title'  => __( 'General', 'simple-lms' ),
                'fields' => array(
                    'tab_bg_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Tab Background', 'simple-lms' ),
                        'show_reset' => true,
                        'show_alpha' => true,
                    ),
                    'tab_active_bg_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Active Tab Background', 'simple-lms' ),
                        'show_reset' => true,
                        'show_alpha' => true,
                    ),
                    'tab_text_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Tab Text Color', 'simple-lms' ),
                        'show_reset' => true,
                    ),
                    'tab_active_text_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Active Tab Text Color', 'simple-lms' ),
                        'show_reset' => true,
                    ),
                )
            ),
            'typography' => array(
                'title'  => __( 'Typography', 'simple-lms' ),
                'fields' => array(
                    'tab_typography' => array(
                        'type'       => 'typography',
                        'label'      => __( 'Tab Typography', 'simple-lms' ),
                        'responsive' => true,
                    ),
                )
            ),
            'padding' => array(
                'title'  => __( 'Padding', 'simple-lms' ),
                'fields' => array(
                    'tab_padding' => array(
                        'type'       => 'dimension',
                        'label'      => __( 'Tab Padding', 'simple-lms' ),
                        'responsive' => true,
                        'units'      => array( 'px', 'em', '%' ),
                    ),
                )
            ),
        )
    ),
    'form_style' => array(
        'title'    => __( 'Form Style', 'simple-lms' ),
        'sections' => array(
            'input_style' => array(
                'title'  => __( 'Input Field', 'simple-lms' ),
                'fields' => array(
                    'input_bg_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Input Background', 'simple-lms' ),
                        'show_reset' => true,
                        'show_alpha' => true,
                    ),
                    'input_text_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Input Text Color', 'simple-lms' ),
                        'show_reset' => true,
                    ),
                    'input_border_group' => array(
                        'type'       => 'border',
                        'label'      => __( 'Input Border', 'simple-lms' ),
                        'responsive' => true,
                    ),
                    'input_typography' => array(
                        'type'       => 'typography',
                        'label'      => __( 'Input Typography', 'simple-lms' ),
                        'responsive' => true,
                    ),
                )
            ),
            'button_style' => array(
                'title'  => __( 'Button', 'simple-lms' ),
                'fields' => array(
                    'button_bg_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Background Color', 'simple-lms' ),
                        'show_reset' => true,
                        'show_alpha' => true,
                    ),
                    'button_hover_bg_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Background Hover Color', 'simple-lms' ),
                        'show_reset' => true,
                        'show_alpha' => true,
                    ),
                    'button_text_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Text Color', 'simple-lms' ),
                        'show_reset' => true,
                    ),
                    'button_hover_text_color' => array(
                        'type'       => 'color',
                        'label'      => __( 'Text Hover Color', 'simple-lms' ),
                        'show_reset' => true,
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
                )
            )
        )
    )
) );
