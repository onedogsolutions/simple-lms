<?php
/**
 * Registration for the SLMS Student Dashboard module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SLMSStudentDashboardModule
 */
class SLMSStudentDashboardModule extends \FLBuilderModule {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'name'            => __( 'Student Dashboard', 'simple-lms-bridge' ),
            'description'     => __( 'A custom student dashboard.', 'simple-lms-bridge' ),
            'category'        => __( 'LMS Modules', 'simple-lms-bridge' ),
            'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/slms-student-dashboard/',
            'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/slms-student-dashboard/',
            'editor_export'   => true,
            'partial_refresh' => true,
        ) );
    }

    /**
     * Get a list of Gravity Forms for selection.
     *
     * @return array
     */
    public static function get_gravity_forms() {
        $options = array( '' => __( 'Select a Form', 'simple-lms-bridge' ) );

        if ( class_exists( 'GFAPI' ) ) {
            $forms = \GFAPI::get_forms();
            foreach ( $forms as $form ) {
                $options[ $form['id'] ] = $form['title'];
            }
        }

        return $options;
    }

    /**
     * Get the default certificate form ID.
     * Searches for a form titled "Certificate".
     *
     * @return string
     */
    public static function get_default_cert_form() {
        if ( class_exists( 'GFAPI' ) ) {
            $forms = \GFAPI::get_forms();
            foreach ( $forms as $form ) {
                if ( stripos( $form['title'], 'Certificate' ) !== false ) {
                    return (string) $form['id'];
                }
            }
        }
        return '';
    }
}

/**
 * Register the module.
 */
\FLBuilder::register_module(
    'SimpleLMS\SLMSStudentDashboardModule',
    array(
        'general'    => array(
            'title'    => __( 'General', 'simple-lms-bridge' ),
            'sections' => array(
                'tab_labels'   => array(
                    'title'  => __( 'Tab Labels', 'simple-lms-bridge' ),
                    'fields' => array(
                        'tab_label_profile' => array(
                            'type'    => 'text',
                            'label'   => __( 'Profile Tab Label', 'simple-lms-bridge' ),
                            'default' => __( 'User Profile', 'simple-lms-bridge' ),
                        ),
                        'tab_label_history' => array(
                            'type'    => 'text',
                            'label'   => __( 'Purchase History Tab', 'simple-lms-bridge' ),
                            'default' => __( 'Purchase History', 'simple-lms-bridge' ),
                        ),
                        'tab_label_certs'   => array(
                            'type'    => 'text',
                            'label'   => __( 'Certificates Tab', 'simple-lms-bridge' ),
                            'default' => __( 'Certificates Earned', 'simple-lms-bridge' ),
                        ),
                    ),
                ),
                'certs_config' => array(
                    'title'  => __( 'Certificates Configuration', 'simple-lms-bridge' ),
                    'fields' => array(
                        'cert_data_source'  => array(
                            'type'    => 'select',
                            'label'   => __( 'Data Source', 'simple-lms-bridge' ),
                            'default' => 'history_table',
                            'options' => array(
                                'history_table' => __( 'Course History Table (Recommended)', 'simple-lms-bridge' ),
                                'gravity_forms' => __( 'Gravity Forms Entries (Legacy)', 'simple-lms-bridge' ),
                            ),
                            'toggle'  => array(
                                'gravity_forms' => array(
                                    'fields' => array( 'cert_field_name', 'cert_field_course', 'cert_field_date', 'cert_field_pdf' ),
                                ),
                            ),
                        ),
                        'cert_form_id'      => array(
                            'type'    => 'select',
                            'label'   => __( 'Certificate GF Form', 'simple-lms-bridge' ),
                            'options' => \SimpleLMS\SLMSStudentDashboardModule::get_gravity_forms(),
                            'default' => \SimpleLMS\SLMSStudentDashboardModule::get_default_cert_form(),
                        ),
                        'cert_field_name'   => array(
                            'type'  => 'text',
                            'label' => __( 'Student Name Field ID (GF)', 'simple-lms-bridge' ),
                            'size'  => '4',
                        ),
                        'cert_field_course' => array(
                            'type'  => 'text',
                            'label' => __( 'Course Name Field ID (GF)', 'simple-lms-bridge' ),
                            'size'  => '4',
                        ),
                        'cert_field_date'   => array(
                            'type'  => 'text',
                            'label' => __( 'Completion Date Field ID (GF)', 'simple-lms-bridge' ),
                            'size'  => '4',
                        ),
                        'cert_field_pdf'    => array(
                            'type'  => 'text',
                            'label' => __( 'Certificate PDF Field ID (GF)', 'simple-lms-bridge' ),
                            'size'  => '4',
                        ),
                    ),
                ),
            ),
        ),
        'style'      => array(
            'title'    => __( 'Style', 'simple-lms-bridge' ),
            'sections' => array(
                'tabs_colors'   => array(
                    'title'  => __( 'Tabs Colors', 'simple-lms-bridge' ),
                    'fields' => array(
                        'tab_bg_color'          => array(
                            'type'       => 'color',
                            'label'      => __( 'Tab Background', 'simple-lms-bridge' ),
                            'default'    => '001f3f',
                            'show_reset' => true,
                        ),
                        'tab_hover_bg_color'    => array(
                            'type'       => 'color',
                            'label'      => __( 'Tab Hover', 'simple-lms-bridge' ),
                            'default'    => '113355',
                            'show_reset' => true,
                        ),
                        'tab_text_color'        => array(
                            'type'       => 'color',
                            'label'      => __( 'Tab Text', 'simple-lms-bridge' ),
                            'default'    => 'ffffff',
                            'show_reset' => true,
                        ),
                        'tab_active_bg_color'   => array(
                            'type'       => 'color',
                            'label'      => __( 'Active Tab Background', 'simple-lms-bridge' ),
                            'default'    => 'ffffff',
                            'show_reset' => true,
                        ),
                        'tab_active_text_color' => array(
                            'type'       => 'color',
                            'label'      => __( 'Active Tab Text', 'simple-lms-bridge' ),
                            'default'    => 'e91e63',
                            'show_reset' => true,
                        ),
                        'tab_border_color'      => array(
                            'type'       => 'color',
                            'label'      => __( 'Tab Border', 'simple-lms-bridge' ),
                            'default'    => 'e0e0e0',
                            'show_reset' => true,
                        ),
                    ),
                ),
                'content_style' => array(
                    'title'  => __( 'Content Area', 'simple-lms-bridge' ),
                    'fields' => array(
                        'content_bg_color'      => array(
                            'type'       => 'color',
                            'label'      => __( 'Content Background', 'simple-lms-bridge' ),
                            'default'    => 'ffffff',
                            'show_reset' => true,
                        ),
                        'content_padding'       => array(
                            'type'    => 'unit',
                            'label'   => __( 'Content Padding', 'simple-lms-bridge' ),
                            'default' => '20',
                        ),
                        'content_border_radius' => array(
                            'type'    => 'unit',
                            'label'   => __( 'Content Radius', 'simple-lms-bridge' ),
                            'default' => '4',
                        ),
                    ),
                ),
                'typography'    => array(
                    'title'  => __( 'Typography', 'simple-lms-bridge' ),
                    'fields' => array(
                        'heading_font_size' => array(
                            'type'    => 'unit',
                            'label'   => __( 'Heading Font Size', 'simple-lms-bridge' ),
                            'default' => '18',
                        ),
                        'body_font_size'    => array(
                            'type'    => 'unit',
                            'label'   => __( 'Body Font Size', 'simple-lms-bridge' ),
                            'default' => '14',
                        ),
                    ),
                ),
            ),
        ),
        'form_style' => array(
            'title'    => __( 'Form & Table Style', 'simple-lms-bridge' ),
            'sections' => array(
                'form_fields'  => array(
                    'title'  => __( 'Profile Form', 'simple-lms-bridge' ),
                    'fields' => array(
                        'input_bg_color'           => array(
                            'type'       => 'color',
                            'label'      => __( 'Input Background', 'simple-lms-bridge' ),
                            'default'    => 'ffffff',
                            'show_reset' => true,
                        ),
                        'input_text_color'         => array(
                            'type'       => 'color',
                            'label'      => __( 'Input Text Color', 'simple-lms-bridge' ),
                            'default'    => '333333',
                            'show_reset' => true,
                        ),
                        'input_border_color'       => array(
                            'type'       => 'color',
                            'label'      => __( 'Input Border', 'simple-lms-bridge' ),
                            'default'    => 'cccccc',
                            'show_reset' => true,
                        ),
                        'input_focus_border_color' => array(
                            'type'       => 'color',
                            'label'      => __( 'Input Focus Border', 'simple-lms-bridge' ),
                            'default'    => '0073aa',
                            'show_reset' => true,
                        ),
                    ),
                ),
                'table_style'  => array(
                    'title'  => __( 'Table Style (History & Certs)', 'simple-lms-bridge' ),
                    'fields' => array(
                        'table_header_bg'   => array(
                            'type'       => 'color',
                            'label'      => __( 'Header Background', 'simple-lms-bridge' ),
                            'default'    => 'f7f7f7',
                            'show_reset' => true,
                        ),
                        'table_header_text' => array(
                            'type'       => 'color',
                            'label'      => __( 'Header Text Color', 'simple-lms-bridge' ),
                            'default'    => '333333',
                            'show_reset' => true,
                        ),
                        'table_row_alt_bg'  => array(
                            'type'       => 'color',
                            'label'      => __( 'Alternate Row Background', 'simple-lms-bridge' ),
                            'default'    => 'fafafa',
                            'show_reset' => true,
                        ),
                        'table_border_color' => array(
                            'type'       => 'color',
                            'label'      => __( 'Border Color', 'simple-lms-bridge' ),
                            'default'    => 'e0e0e0',
                            'show_reset' => true,
                        ),
                    ),
                ),
                'button_style' => array(
                    'title'  => __( 'Buttons', 'simple-lms-bridge' ),
                    'fields' => array(
                        'btn_bg_color'       => array(
                            'type'       => 'color',
                            'label'      => __( 'Button Background', 'simple-lms-bridge' ),
                            'default'    => 'e91e63',
                            'show_reset' => true,
                        ),
                        'btn_hover_bg_color' => array(
                            'type'       => 'color',
                            'label'      => __( 'Button Hover', 'simple-lms-bridge' ),
                            'default'    => 'c2185b',
                            'show_reset' => true,
                        ),
                        'btn_text_color'     => array(
                            'type'       => 'color',
                            'label'      => __( 'Button Text Color', 'simple-lms-bridge' ),
                            'default'    => 'ffffff',
                            'show_reset' => true,
                        ),
                    ),
                ),
            ),
        ),
    )
);