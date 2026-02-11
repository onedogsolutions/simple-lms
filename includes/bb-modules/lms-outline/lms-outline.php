<?php
/**
 * Registration for the LMS Outline module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LMSOutlineModule
 */
class LMSOutlineModule extends \FLBuilderModule
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array(
            'name' => __('LMS Outline', 'simple-lms-bridge'),
            'description' => __('Displays a list of lessons for the current course.', 'simple-lms-bridge'),
            'category' => __('SimpleLMS', 'simple-lms-bridge'),
            'dir' => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-outline/',
            'url' => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-outline/',
            'icon' => 'list-view.svg',
            'enabled' => true,
            'partial_refresh' => true,
        ));
    }
}

/**
 * Register the module.
 */
\FLBuilder::register_module('SimpleLMS\LMSOutlineModule', array(
    'general' => array(
        'title' => __('General', 'simple-lms-bridge'),
        'sections' => array(
            'general' => array(
                'title' => '',
                'fields' => array(
                ),
            ),
        ),
    ),
));