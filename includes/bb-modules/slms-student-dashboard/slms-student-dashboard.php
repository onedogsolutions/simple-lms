<?php
/**
 * Registration for the SLMS Student Dashboard module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SLMSStudentDashboardModule
 */
class SLMSStudentDashboardModule extends \FLBuilderModule
{

    /**
     * Constructor.
     */
    /**
     * Get a list of Gravity Forms for selection.
     *
     * @return array
     */
    public static function get_gravity_forms()
    {
        $options = array('' => __('Select a Form', 'simple-lms-bridge'));
        
        if (class_exists('GFAPI')) {
            $forms = \GFAPI::get_forms();
            foreach ($forms as $form) {
                $options[$form['id']] = $form['title'];
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
    public static function get_default_cert_form()
    {
        if (class_exists('GFAPI')) {
            $forms = \GFAPI::get_forms();
            foreach ($forms as $form) {
                if (stripos($form['title'], 'Certificate') !== false) {
                    return (string)$form['id'];
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
    'general' => array(
        'title' => __('General', 'simple-lms-bridge'),
        'sections' => array(
            'certs_info' => array(
                'title' => __('Certificates Earned', 'simple-lms-bridge'),
                'fields' => array(
                    'cert_form_id' => array(
                        'type' => 'select',
                        'label' => __('Certificate Form', 'simple-lms-bridge'),
                        'options' => \SimpleLMS\SLMSStudentDashboardModule::get_gravity_forms(),
                        'default' => \SimpleLMS\SLMSStudentDashboardModule::get_default_cert_form(),
                    ),
                    'cert_field_name' => array(
                        'type' => 'text',
                        'label' => __('Student Name Field ID', 'simple-lms-bridge'),
                        'size' => '4',
                    ),
                    'cert_field_course' => array(
                        'type' => 'text',
                        'label' => __('Course Name Field ID', 'simple-lms-bridge'),
                        'size' => '4',
                    ),
                    'cert_field_date' => array(
                        'type' => 'text',
                        'label' => __('Completion Date Field ID', 'simple-lms-bridge'),
                        'size' => '4',
                    ),
                    'cert_field_pdf' => array(
                        'type' => 'text',
                        'label' => __('Certificate PDF Field ID', 'simple-lms-bridge'),
                        'size' => '4',
                    ),
                ),
            ),
            'tab_labels' => array(
                'title' => __('Tab Labels', 'simple-lms-bridge'),
                'fields' => array(
                    'tab_label_profile' => array(
                        'type' => 'text',
                        'label' => __('Profile Tab Label', 'simple-lms-bridge'),
                        'default' => __('User Profile', 'simple-lms-bridge'),
                    ),
                    'tab_label_history' => array(
                        'type' => 'text',
                        'label' => __('History Tab Label', 'simple-lms-bridge'),
                        'default' => __('Purchase History', 'simple-lms-bridge'),
                    ),
                    'tab_label_certs' => array(
                        'type' => 'text',
                        'label' => __('Certificates Tab Label', 'simple-lms-bridge'),
                        'default' => __('Certificates Earned', 'simple-lms-bridge'),
                    ),
                ),
            ),
        ),
    ),
    'style' => array(
        'title' => __('Style', 'simple-lms-bridge'),
        'sections' => array(
            'tabs_colors' => array(
                'title' => __('Tabs Colors', 'simple-lms-bridge'),
                'fields' => array(
                    'tab_bg_color' => array(
                        'type' => 'color',
                        'label' => __('Tab Background', 'simple-lms-bridge'),
                        'default' => '001f3f',
                        'show_reset' => true,
                    ),
                    'tab_text_color' => array(
                        'type' => 'color',
                        'label' => __('Tab Text', 'simple-lms-bridge'),
                        'default' => 'ffffff',
                        'show_reset' => true,
                    ),
                    'tab_active_bg_color' => array(
                        'type' => 'color',
                        'label' => __('Active Tab Background', 'simple-lms-bridge'),
                        'default' => 'ffffff',
                        'show_reset' => true,
                    ),
                    'tab_active_text_color' => array(
                        'type' => 'color',
                        'label' => __('Active Tab Text', 'simple-lms-bridge'),
                        'default' => 'e91e63',
                        'show_reset' => true,
                    ),
                ),
            ),
            'table_style' => array(
                'title' => __('Table Style', 'simple-lms-bridge'),
                'fields' => array(
                    'table_header_bg' => array(
                        'type' => 'color',
                        'label' => __('Header Background', 'simple-lms-bridge'),
                        'default' => 'f7f7f7',
                        'show_reset' => true,
                    ),
                    'table_header_text' => array(
                        'type' => 'color',
                        'label' => __('Header Text Color', 'simple-lms-bridge'),
                        'default' => '333333',
                        'show_reset' => true,
                    ),
                    'table_row_alt_bg' => array(
                        'type' => 'color',
                        'label' => __('Alternate Row Background', 'simple-lms-bridge'),
                        'default' => 'fafafa',
                        'show_reset' => true,
                    ),
                    'table_border_color' => array(
                        'type' => 'color',
                        'label' => __('Border Color', 'simple-lms-bridge'),
                        'default' => 'e0e0e0',
                        'show_reset' => true,
                    ),
                ),
            ),
            'typography' => array(
                'title' => __('Typography', 'simple-lms-bridge'),
                'fields' => array(
                    'heading_font_size' => array(
                        'type' => 'unit',
                        'label' => __('Heading Font Size', 'simple-lms-bridge'),
                        'default' => '18',
                    ),
                    'body_font_size' => array(
                        'type' => 'unit',
                        'label' => __('Body Font Size', 'simple-lms-bridge'),
                        'default' => '14',
                    ),
                ),
            ),
            'content_style' => array(
                'title' => __('Content Area', 'simple-lms-bridge'),
                'fields' => array(
                    'content_bg_color' => array(
                        'type' => 'color',
                        'label' => __('Content Background', 'simple-lms-bridge'),
                        'default' => 'ffffff',
                        'show_reset' => true,
                    ),
                    'content_padding' => array(
                        'type' => 'unit',
                        'label' => __('Content Padding', 'simple-lms-bridge'),
                        'default' => '20',
                    ),
                ),
            ),
        ),
    ),
)
);