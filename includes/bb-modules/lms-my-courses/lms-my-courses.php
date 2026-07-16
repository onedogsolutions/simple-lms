<?php
/**
 * Registration for the LMS My Courses module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LMSMyCoursesModule
 */
class LMSMyCoursesModule extends \FLBuilderModule
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array(
            'name'            => __('LMS My Courses', 'simple-lms-bridge'),
            'description'     => __('The current student\'s enrolled courses with progress and continue links.', 'simple-lms-bridge'),
            'category'        => __('SimpleLMS', 'simple-lms-bridge'),
            'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-my-courses/',
            'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-my-courses/',
            'icon'            => 'list-view.svg',
            'enabled'         => true,
            'partial_refresh' => true,
        ));
    }
}

\FLBuilder::register_module('SimpleLMS\LMSMyCoursesModule', array(
    'general' => array(
        'title'    => __('General', 'simple-lms-bridge'),
        'sections' => array(
            'general' => array(
                'title'  => '',
                'fields' => array(
                    'show_thumbnail' => array(
                        'type'    => 'select',
                        'label'   => __('Show Thumbnail', 'simple-lms-bridge'),
                        'default' => 'yes',
                        'options' => array('yes' => __('Yes', 'simple-lms-bridge'), 'no' => __('No', 'simple-lms-bridge')),
                    ),
                    'empty_text' => array(
                        'type'    => 'text',
                        'label'   => __('Empty State Text', 'simple-lms-bridge'),
                        'default' => __('You are not enrolled in any courses yet.', 'simple-lms-bridge'),
                    ),
                ),
            ),
            'colors' => array(
                'title'  => __('Colors', 'simple-lms-bridge'),
                'fields' => array(
                    'accent_color' => array(
                        'type'        => 'color',
                        'label'       => __('Accent (progress / button)', 'simple-lms-bridge'),
                        'default'     => '0073aa',
                        'show_reset'  => true,
                        'connections' => array('color'),
                    ),
                    'button_text_color' => array(
                        'type'        => 'color',
                        'label'       => __('Button Text', 'simple-lms-bridge'),
                        'default'     => 'ffffff',
                        'show_reset'  => true,
                        'connections' => array('color'),
                    ),
                ),
            ),
        ),
    ),
));
