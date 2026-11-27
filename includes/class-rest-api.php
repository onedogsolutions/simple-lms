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
			'/courses',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'courses' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'per_page' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 200 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/lessons',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'lessons' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'per_page' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 200 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/courses/(?P<id>\d+)/lessons',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'update_course_lessons' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'id'         => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'lesson_ids' => array( 'type' => 'array', 'required' => true ),
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

		$course_id = Progress::resolve_course_id( $lesson_id, $course_id );
		$progress  = Progress::course_progress( $course_id, $user_id );

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'lesson_id'        => $lesson_id,
				'course_id'        => $course_id,
				'progress'         => $progress,
				'next_step_url'    => Progress::get_next_step_url( $course_id, $lesson_id, $user_id ),
				'verification_url' => $progress['complete'] ? Gravity_Forms::verification_url( $course_id ) : '',
			),
			200
		);
	}

	/**
	 * List courses for the curriculum builder.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function courses( \WP_REST_Request $request ): \WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => Post_Types::COURSE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => (int) $request->get_param( 'per_page' ),
				's'              => (string) $request->get_param( 'search' ),
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);
		$items = array_map(
			static fn( \WP_Post $post ): array => array(
				'id'         => $post->ID,
				'title'      => get_the_title( $post ),
				'status'     => $post->post_status,
				'url'        => (string) get_permalink( $post ),
				'lesson_ids' => Relationships::get_course_lesson_ids( $post->ID, true ),
			),
			$posts
		);
		return new \WP_REST_Response( array( 'courses' => $items ), 200 );
	}

	/**
	 * List canonical lessons for the curriculum builder.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function lessons( \WP_REST_Request $request ): \WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => Post_Types::LESSON,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => (int) $request->get_param( 'per_page' ),
				's'              => (string) $request->get_param( 'search' ),
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);
		$items = array_map(
			static fn( \WP_Post $post ): array => array(
				'id'         => $post->ID,
				'title'      => get_the_title( $post ),
				'status'     => $post->post_status,
				'video_id'    => Post_Types::presto_video_id( $post->ID ),
				'course_ids' => Relationships::get_lesson_courses( $post->ID, true ),
			),
			$posts
		);
		return new \WP_REST_Response( array( 'lessons' => $items ), 200 );
	}

	/**
	 * Save a course's ordered canonical lesson list.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_course_lessons( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$course_id = absint( $request->get_param( 'id' ) );
		if ( Post_Types::COURSE !== get_post_type( $course_id ) || ! current_user_can( 'edit_post', $course_id ) ) {
			return new \WP_Error( 'simple_lms_cannot_edit_course', __( 'You cannot edit this course.', 'simple-lms' ), array( 'status' => 403 ) );
		}
		$lesson_ids = $request->get_param( 'lesson_ids' );
		if ( ! is_array( $lesson_ids ) || ! Relationships::set_course_lesson_ids( $course_id, $lesson_ids ) ) {
			return new \WP_Error( 'simple_lms_invalid_curriculum', __( 'The curriculum contains invalid lessons.', 'simple-lms' ), array( 'status' => 400 ) );
		}
		return new \WP_REST_Response(
			array(
				'success'    => true,
				'course_id'  => $course_id,
				'lesson_ids' => Relationships::get_course_lesson_ids( $course_id, true ),
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
			'course_post_type'  => Post_Types::COURSE,
			'lesson_post_type'  => Post_Types::LESSON,
			'lesson_meta'       => Relationships::COURSE_LESSONS,
			'enrollment_meta'   => Settings::ENROLLMENT_META,
			'presto_player'     => defined( 'PRESTO_PLAYER_VERSION' ) ? PRESTO_PLAYER_VERSION : false,
			'gravity_forms'     => class_exists( 'GFCommon' ) ? (string) ( \GFCommon::$version ?? '' ) : false,
			'gravity_pdf'       => class_exists( 'GFPDF_Core_Model' ) || defined( 'GFPDF_VERSION' ),
			'namespace'         => self::NAMESPACE,
		);
	}
}
