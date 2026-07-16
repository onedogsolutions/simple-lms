<?php
/**
 * Registration for the LMS Course CTA module.
 *
 * A single, state-aware call-to-action button for the resolved course.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LMSCourseCTAModule
 */
class LMSCourseCTAModule extends \FLBuilderModule
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array(
            'name'            => __('LMS Course CTA', 'simple-lms-bridge'),
            'description'     => __('A state-aware call-to-action button for a course.', 'simple-lms-bridge'),
            'category'        => __('SimpleLMS', 'simple-lms-bridge'),
            'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-course-cta/',
            'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-course-cta/',
            'icon'            => 'button.svg',
            'enabled'         => true,
            'partial_refresh' => true,
        ));
    }
}

\FLBuilder::register_module('SimpleLMS\LMSCourseCTAModule', array(
    'general' => array(
        'title'    => __('General', 'simple-lms-bridge'),
        'sections' => array(
            'general' => array(
                'title'  => '',
                'fields' => array(
                    'course_id' => array(
                        'type'    => 'text',
                        'label'   => __('Course ID', 'simple-lms-bridge'),
                        'default' => '',
                        'help'    => __('Leave blank to use the current course/lesson.', 'simple-lms-bridge'),
                    ),
                    'dashboard_url' => array(
                        'type'    => 'text',
                        'label'   => __('Certificate / Dashboard URL', 'simple-lms-bridge'),
                        'default' => '',
                        'help'    => __('Where the "View Certificate" state links. Defaults to /my-account/.', 'simple-lms-bridge'),
                    ),
                    'align' => array(
                        'type'    => 'select',
                        'label'   => __('Alignment', 'simple-lms-bridge'),
                        'default' => 'left',
                        'options' => array(
                            'left'   => __('Left', 'simple-lms-bridge'),
                            'center' => __('Center', 'simple-lms-bridge'),
                            'right'  => __('Right', 'simple-lms-bridge'),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'style' => array(
        'title'    => __('Style', 'simple-lms-bridge'),
        'sections' => array(
            'colors' => array(
                'title'  => __('Button Colors', 'simple-lms-bridge'),
                'fields' => array(
                    'bg_color' => array(
                        'type'        => 'color',
                        'label'       => __('Background', 'simple-lms-bridge'),
                        'default'     => '0073aa',
                        'show_reset'  => true,
                        'show_alpha'  => true,
                        'connections' => array('color'),
                    ),
                    'text_color' => array(
                        'type'        => 'color',
                        'label'       => __('Text', 'simple-lms-bridge'),
                        'default'     => 'ffffff',
                        'show_reset'  => true,
                        'connections' => array('color'),
                    ),
                    'hover_bg_color' => array(
                        'type'        => 'color',
                        'label'       => __('Hover Background', 'simple-lms-bridge'),
                        'default'     => '005177',
                        'show_reset'  => true,
                        'show_alpha'  => true,
                        'connections' => array('color'),
                    ),
                ),
            ),
        ),
    ),
));
