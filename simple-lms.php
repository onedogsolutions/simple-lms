<?php
/**
 * Plugin Name: SimpleLMS
 * Description: Lean Pods, Presto Player, Gravity Forms, and Gravity PDF connector.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: SimpleLMS
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-lms
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_LMS_VERSION', '0.1.0' );
define( 'SIMPLE_LMS_FILE', __FILE__ );
define( 'SIMPLE_LMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_LMS_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLE_LMS_DIR . 'includes/class-settings.php';
require_once SIMPLE_LMS_DIR . 'includes/class-access.php';
require_once SIMPLE_LMS_DIR . 'includes/class-progress.php';
require_once SIMPLE_LMS_DIR . 'includes/class-gravity-forms.php';
require_once SIMPLE_LMS_DIR . 'includes/class-rest-api.php';
require_once SIMPLE_LMS_DIR . 'includes/class-shortcodes.php';
require_once SIMPLE_LMS_DIR . 'includes/class-admin.php';

/**
 * Whether a user can access a course.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course post ID.
 * @return bool
 */
function simple_lms_user_has_course_access( int $user_id, int $course_id ): bool {
	return \SimpleLMS\Access::can_access( $course_id, $user_id );
}

/**
 * Initialize plugin modules.
 *
 * @return void
 */
function simple_lms_init(): void {
	\SimpleLMS\Gravity_Forms::init();
	\SimpleLMS\Rest_API::init();
	\SimpleLMS\Shortcodes::init();
	\SimpleLMS\Admin::init();

	add_action( 'update_option_simple_lms_settings', array( \SimpleLMS\Access::class, 'flush_cache' ) );
	add_action( 'wp_enqueue_scripts', 'simple_lms_enqueue_frontend_assets' );
}

add_action( 'plugins_loaded', 'simple_lms_init' );

/**
 * Enqueue vanilla frontend assets for the current request.
 *
 * @return void
 */
function simple_lms_enqueue_frontend_assets(): void {
	$course_type = (string) \SimpleLMS\Settings::get( 'course_post_type', 'course' );
	if ( \SimpleLMS\Settings::get( 'tracking_enabled', true ) && is_singular( $course_type ) ) {
		$lesson_id = absint( get_queried_object_id() );
		$course_id = \SimpleLMS\Progress::course_for_lesson( $lesson_id );
		$asset     = SIMPLE_LMS_DIR . 'build/frontend/player-tracker.js';

		if ( $course_id > 0 && file_exists( $asset ) ) {
			wp_enqueue_script(
				'simple-lms-player-tracker',
				SIMPLE_LMS_URL . 'build/frontend/player-tracker.js',
				array(),
				(string) filemtime( $asset ),
				true
			);
			wp_localize_script(
				'simple-lms-player-tracker',
				'simpleLMSPlayer',
				array(
					'restUrl'   => esc_url_raw( rest_url( \SimpleLMS\Rest_API::NAMESPACE . '/complete' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'lessonId'  => $lesson_id,
					'courseId'  => $course_id,
					'eventName' => (string) \SimpleLMS\Settings::get( 'presto_event', 'endedMedia' ),
					'loggedIn'  => is_user_logged_in(),
					'messages'  => array(
						'saving'   => __( 'Saving progress…', 'simple-lms' ),
						'saved'    => __( 'Progress saved.', 'simple-lms' ),
						'complete' => __( 'Course complete. Your certificate form is ready.', 'simple-lms' ),
						'error'    => __( 'Progress could not be saved. Please try again.', 'simple-lms' ),
					),
				)
			);
		}
	}

	$account_page_id = absint( \SimpleLMS\Settings::get( 'account_page_id', 0 ) );
	$account_asset   = SIMPLE_LMS_DIR . 'build/frontend/account.js';
	if ( $account_page_id > 0 && is_page( $account_page_id ) && file_exists( $account_asset ) ) {
		wp_enqueue_script(
			'simple-lms-account',
			SIMPLE_LMS_URL . 'build/frontend/account.js',
			array(),
			(string) filemtime( $account_asset ),
			true
		);

		$account_style = SIMPLE_LMS_DIR . 'build/frontend/account.css';
		if ( file_exists( $account_style ) ) {
			wp_enqueue_style(
				'simple-lms-account',
				SIMPLE_LMS_URL . 'build/frontend/account.css',
				array(),
				(string) filemtime( $account_style )
			);
		}
	}
}
