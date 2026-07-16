<?php
/**
 * Registration for the LMS Course Grid module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LMSCourseGridModule
 */
class LMSCourseGridModule extends \FLBuilderModule
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array(
            'name'            => __('LMS Course Grid', 'simple-lms-bridge'),
            'description'     => __('A responsive card grid of courses with state-aware CTAs.', 'simple-lms-bridge'),
            'category'        => __('SimpleLMS', 'simple-lms-bridge'),
            'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-course-grid/',
            'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-course-grid/',
            'icon'            => 'schedule.svg',
            'enabled'         => true,
            'partial_refresh' => true,
        ));
    }
}

\FLBuilder::register_module('SimpleLMS\LMSCourseGridModule', array(
    'general' => array(
        'title'    => __('General', 'simple-lms-bridge'),
        'sections' => array(
            'query' => array(
                'title'  => __('Courses', 'simple-lms-bridge'),
                'fields' => array(
                    'category' => array(
                        'type'    => 'text',
                        'label'   => __('Category Slug', 'simple-lms-bridge'),
                        'default' => '',
                        'help'    => __('Filter by a slms_course_cat slug. Leave blank for all courses.', 'simple-lms-bridge'),
                    ),
                    'columns' => array(
                        'type'    => 'select',
                        'label'   => __('Columns', 'simple-lms-bridge'),
                        'default' => '3',
                        'options' => array(
                            '1' => '1',
                            '2' => '2',
                            '3' => '3',
                            '4' => '4',
                        ),
                    ),
                    'number' => array(
                        'type'    => 'text',
                        'label'   => __('Number of Courses', 'simple-lms-bridge'),
                        'default' => '12',
                        'help'    => __('-1 for all.', 'simple-lms-bridge'),
                    ),
                ),
            ),
            'display' => array(
                'title'  => __('Card Display', 'simple-lms-bridge'),
                'fields' => array(
                    'show_thumbnail' => array(
                        'type'    => 'select',
                        'label'   => __('Show Thumbnail', 'simple-lms-bridge'),
                        'default' => 'yes',
                        'options' => array('yes' => __('Yes', 'simple-lms-bridge'), 'no' => __('No', 'simple-lms-bridge')),
                    ),
                    'show_excerpt' => array(
                        'type'    => 'select',
                        'label'   => __('Show Excerpt', 'simple-lms-bridge'),
                        'default' => 'yes',
                        'options' => array('yes' => __('Yes', 'simple-lms-bridge'), 'no' => __('No', 'simple-lms-bridge')),
                    ),
                    'show_price' => array(
                        'type'    => 'select',
                        'label'   => __('Show Price', 'simple-lms-bridge'),
                        'default' => 'yes',
                        'options' => array('yes' => __('Yes', 'simple-lms-bridge'), 'no' => __('No', 'simple-lms-bridge')),
                    ),
                    'show_enrolled_badge' => array(
                        'type'    => 'select',
                        'label'   => __('Show Enrolled Badge', 'simple-lms-bridge'),
                        'default' => 'yes',
                        'options' => array('yes' => __('Yes', 'simple-lms-bridge'), 'no' => __('No', 'simple-lms-bridge')),
                    ),
                    'show_progress' => array(
                        'type'    => 'select',
                        'label'   => __('Show Progress Bar (enrolled)', 'simple-lms-bridge'),
                        'default' => 'yes',
                        'options' => array('yes' => __('Yes', 'simple-lms-bridge'), 'no' => __('No', 'simple-lms-bridge')),
                    ),
                    'dashboard_url' => array(
                        'type'    => 'text',
                        'label'   => __('Certificate / Dashboard URL', 'simple-lms-bridge'),
                        'default' => '',
                        'help'    => __('Where a completed course CTA links. Defaults to /my-account/.', 'simple-lms-bridge'),
                    ),
                ),
            ),
        ),
    ),
    'style' => array(
        'title'    => __('Style', 'simple-lms-bridge'),
        'sections' => array(
            'card' => array(
                'title'  => __('Card', 'simple-lms-bridge'),
                'fields' => array(
                    'card_bg_color' => array(
                        'type'        => 'color',
                        'label'       => __('Card Background', 'simple-lms-bridge'),
                        'default'     => 'ffffff',
                        'show_reset'  => true,
                        'show_alpha'  => true,
                        'connections' => array('color'),
                    ),
                    'accent_color' => array(
                        'type'        => 'color',
                        'label'       => __('Accent (CTA / progress)', 'simple-lms-bridge'),
                        'default'     => '0073aa',
                        'show_reset'  => true,
                        'connections' => array('color'),
                    ),
                    'cta_text_color' => array(
                        'type'        => 'color',
                        'label'       => __('CTA Text', 'simple-lms-bridge'),
                        'default'     => 'ffffff',
                        'show_reset'  => true,
                        'connections' => array('color'),
                    ),
                ),
            ),
        ),
    ),
));
