<?php
/**
 * Versioned REST API.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes completion and settings endpoints under /simple-lms/v1.
 */
final class Rest_API {
	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'simple-lms/v1';

	/**
	 * Register REST hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/complete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'complete' ),
				'permission_callback' => static fn(): bool => is_user_logged_in(),
				'args'                => array(
					'lesson_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'course_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_settings' ),
					'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'update_settings' ),
					'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				),
			)
		);
	}

	/**
	 * Record a lesson completion and return current course progress.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function complete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$lesson_id = absint( $request->get_param( 'lesson_id' ) );
		$course_id = absint( $request->get_param( 'course_id' ) );
		$user_id   = get_current_user_id();

		if ( ! Progress::mark_lesson_complete( $lesson_id, $course_id, $user_id ) ) {
			return new \WP_Error(
				'simple_lms_cannot_complete',
				__( 'The lesson could not be completed. Confirm enrollment and course access.', 'simple-lms' ),
				array( 'status' => 403 )
			);
		}

		$course_id = $course_id > 0 ? $course_id : Progress::course_for_lesson( $lesson_id );
		$progress  = Progress::course_progress( $course_id, $user_id );

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'lesson_id'        => $lesson_id,
				'course_id'        => $course_id,
				'progress'         => $progress,
				'verification_url' => $progress['complete'] ? Gravity_Forms::verification_url( $course_id ) : '',
			),
			200
		);
	}

	/**
	 * Get settings and system status for the admin shell.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_settings(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'settings' => Settings::all(),
				'system'   => self::system_status(),
			),
			200
		);
	}

	/**
	 * Update settings.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = Settings::update( (array) $request->get_params() );

		return new \WP_REST_Response(
			array(
				'settings' => $settings,
				'system'   => self::system_status(),
			),
			200
		);
	}

	/**
	 * Get a compact system status snapshot.
	 *
	 * @return array<string, mixed>
	 */
	private static function system_status(): array {
		return array(
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'plugin_version'    => defined( 'SIMPLE_LMS_VERSION' ) ? SIMPLE_LMS_VERSION : '',
			'configured'        => Settings::is_configured(),
			'pods'              => defined( 'PODS_VERSION' ) ? PODS_VERSION : false,
			'presto_player'     => defined( 'PRESTO_PLAYER_VERSION' ) ? PRESTO_PLAYER_VERSION : false,
			'gravity_forms'     => class_exists( 'GFCommon' ) ? (string) ( \GFCommon::$version ?? '' ) : false,
			'gravity_pdf'       => class_exists( 'GFPDF_Core_Model' ) || defined( 'GFPDF_VERSION' ),
			'namespace'         => self::NAMESPACE,
		);
	}
}
