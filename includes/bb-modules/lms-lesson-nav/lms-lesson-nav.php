<?php
/**
 * Registration for the LMS Lesson Nav module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMSLessonNavModule
 */
class LMSLessonNavModule extends \FLBuilderModule {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'LMS Lesson Nav', 'simple-lms-bridge' ),
				'description'     => __( 'Previous / next lesson navigation with a back-to-course link.', 'simple-lms-bridge' ),
				'category'        => __( 'SimpleLMS', 'simple-lms-bridge' ),
				'dir'             => SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-lesson-nav/',
				'url'             => SLMS_PLUGIN_URL . 'includes/bb-modules/lms-lesson-nav/',
				'icon'            => 'align-center.svg',
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);
	}
}

\FLBuilder::register_module(
	'SimpleLMS\LMSLessonNavModule',
	array(
		'general' => array(
			'title'    => __( 'General', 'simple-lms-bridge' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'show_back'  => array(
							'type'    => 'select',
							'label'   => __( 'Show "Back to Course"', 'simple-lms-bridge' ),
							'default' => 'yes',
							'options' => array(
								'yes' => __( 'Yes', 'simple-lms-bridge' ),
								'no'  => __( 'No', 'simple-lms-bridge' ),
							),
						),
						'back_label' => array(
							'type'    => 'text',
							'label'   => __( 'Back Label', 'simple-lms-bridge' ),
							'default' => __( 'Back to Course', 'simple-lms-bridge' ),
						),
					),
				),
				'colors'  => array(
					'title'  => __( 'Colors', 'simple-lms-bridge' ),
					'fields' => array(
						'link_color' => array(
							'type'        => 'color',
							'label'       => __( 'Link Color', 'simple-lms-bridge' ),
							'default'     => '0073aa',
							'show_reset'  => true,
							'connections' => array( 'color' ),
						),
					),
				),
			),
		),
	)
);
