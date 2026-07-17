<?php
/**
 * Registration for the LMS Complete Button module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMSCompleteButtonModule
 */
class LMSCompleteButtonModule extends \FLBuilderModule {


	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'LMS Complete Button', 'simple-lms-bridge' ),
				'description'     => __( 'A button to toggle completion of the current lesson.', 'simple-lms-bridge' ),
				'category'        => __( 'SimpleLMS', 'simple-lms-bridge' ),
				'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-complete-button/',
				'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-complete-button/',
				'icon'            => 'yes.svg',
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);

		// The frontend script (assets/js/frontend.js) is enqueued globally by
		// the plugin bootstrap (handle: slms-frontend), so no per-module
		// enqueue is needed here.
	}
}

/**
 * Register the module.
 */
\FLBuilder::register_module(
	'SimpleLMS\LMSCompleteButtonModule',
	array(
		'general' => array(
			'title'    => __( 'General', 'simple-lms-bridge' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(),
				),
			),
		),
	)
);
