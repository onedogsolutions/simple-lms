<?php
/**
 * Registration for the LMS Complete Button module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LMSCompleteButtonModule
 */
class LMSCompleteButtonModule extends \FLBuilderModule
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array(
            'name' => __('LMS Complete Button', 'simple-lms-bridge'),
            'description' => __('A button to toggle completion of the current lesson.', 'simple-lms-bridge'),
            'category' => __('SimpleLMS', 'simple-lms-bridge'),
            'dir' => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-complete-button/',
            'url' => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-complete-button/',
            'icon' => 'yes.svg',
            'enabled' => true,
            'partial_refresh' => true,
        ));

        // Enqueue frontend scripts.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    /**
     * Enqueue frontend scripts.
     */
    public function enqueue_frontend_scripts()
    {
        if (!\FLBuilderModel::is_builder_active()) {
            wp_enqueue_script(
                'slms-complete-button',
                SLMS_PLUGIN_URL . 'assets/js/frontend.js',
                array(),
                SLMS_VERSION,
                true
            );
        }
    }
}

/**
 * Register the module.
 */
\FLBuilder::register_module('SimpleLMS\LMSCompleteButtonModule', array(
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