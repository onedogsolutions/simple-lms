<?php
/**
 * Registration for the LMS Content module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LMSContentModule
 */
class LMSContentModule extends \FLBuilderModule
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array(
            'name' => __('LMS Content', 'simple-lms-bridge'),
            'description' => __('Displays the content of the current LMS lesson.', 'simple-lms-bridge'),
            'category' => __('SimpleLMS', 'simple-lms-bridge'),
            'dir' => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-content/',
            'url' => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-content/',
            'icon' => 'media-text.svg',
            'enabled' => true,
            'partial_refresh' => true,
        ));
    }
}

/**
 * Register the module.
 */
\FLBuilder::register_module('SimpleLMS\LMSContentModule', array(
    'general' => array(
        'title' => __('General', 'simple-lms-bridge'),
        'sections' => array(
            'general' => array(
                'title' => '',
                'fields' => array(
                    // No complex fields needed for a simple content display.
                ),
            ),
        ),
    ),
));