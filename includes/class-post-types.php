<?php
/**
 * Native course and lesson post types.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the native content schema and its admin columns.
 */
final class Post_Types {
	public const COURSE = 'slms_course';
	public const LESSON = 'slms_lesson';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'manage_edit-' . self::COURSE . '_columns', array( self::class, 'course_columns' ) );
		add_action( 'manage_' . self::COURSE . '_posts_custom_column', array( self::class, 'course_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::LESSON . '_columns', array( self::class, 'lesson_columns' ) );
		add_action( 'manage_' . self::LESSON . '_posts_custom_column', array( self::class, 'lesson_column' ), 10, 2 );
	}

	/**
	 * Register the native schema and persist its rewrite rules on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::register();
		flush_rewrite_rules();
	}

	/**
	 * Register post types, taxonomy, and native meta.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::COURSE,
			array(
				'labels'       => array(
					'name'          => __( 'Courses', 'simple-lms' ),
					'singular_name' => __( 'Course', 'simple-lms' ),
					'menu_name'     => __( 'Courses', 'simple-lms' ),
					'add_new_item'  => __( 'Add New Course', 'simple-lms' ),
					'edit_item'     => __( 'Edit Course', 'simple-lms' ),
				),
				'public'       => true,
				'has_archive'  => 'courses',
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-welcome-learn-more',
				'rewrite'      => array( 'slug' => 'courses', 'with_front' => false ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			)
		);

		register_post_type(
			self::LESSON,
			array(
				'labels'       => array(
					'name'          => __( 'Lessons', 'simple-lms' ),
					'singular_name' => __( 'Lesson', 'simple-lms' ),
					'menu_name'     => __( 'Lessons', 'simple-lms' ),
					'add_new_item'  => __( 'Add New Lesson', 'simple-lms' ),
					'edit_item'     => __( 'Edit Lesson', 'simple-lms' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-play-alt-video',
				'rewrite'      => array( 'slug' => 'lessons', 'with_front' => false ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			)
		);

		register_taxonomy(
			'slms_course_cat',
			array( self::COURSE, self::LESSON ),
			array(
				'labels'            => array(
					'name'          => __( 'Course Categories', 'simple-lms' ),
					'singular_name' => __( 'Course Category', 'simple-lms' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'rewrite'           => array( 'slug' => 'course-category' ),
			)
		);

		self::register_meta( self::COURSE, '_slms_lesson_ids', 'array' );
		self::register_meta( self::COURSE, '_lms_guard_mode', 'string' );
		self::register_meta( self::COURSE, '_lms_access_days', 'integer' );
		self::register_meta( self::COURSE, '_lms_certificate_form', 'integer' );
		self::register_meta( self::COURSE, '_slms_course_price', 'number' );
		self::register_meta( self::COURSE, '_lms_completion_redirect', 'string' );
		self::register_meta( self::COURSE, '_lms_pmpro_levels', 'array' );
		self::register_meta( self::LESSON, '_slms_course_ids', 'array' );
		self::register_meta( self::LESSON, '_lms_lesson_type', 'string' );
		self::register_meta( self::LESSON, '_lms_presto_video_id', 'integer' );
		self::register_meta( self::LESSON, '_lms_gravity_form_id', 'integer' );
		self::register_meta( self::LESSON, '_lms_quiz_timer', 'integer' );
		self::register_meta( self::LESSON, '_lms_quiz_pass_field', 'string' );
		self::register_meta( self::LESSON, '_lms_quiz_pass_score', 'number' );
		self::register_meta( self::LESSON, '_lms_drip_days', 'integer' );
		self::register_meta( self::LESSON, '_lms_video_gate_pct', 'integer' );
	}

	/**
	 * Add course overview columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public static function course_columns( array $columns ): array {
		$columns['slms_lessons']  = __( 'Lessons', 'simple-lms' );
		$columns['slms_products'] = __( 'Assigned Product / Level', 'simple-lms' );
		return $columns;
	}

	/**
	 * Render course overview values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Course post ID.
	 * @return void
	 */
	public static function course_column( string $column, int $post_id ): void {
		if ( 'slms_lessons' === $column ) {
			$count = count( Relationships::get_course_lesson_ids( $post_id, true ) );
			echo esc_html( number_format_i18n( $count ) );
			return;
		}
		if ( 'slms_products' !== $column ) {
			return;
		}

		$links = array();
		foreach ( self::course_assignments( $post_id ) as $assignment ) {
			$links[] = sprintf(
				'<a href="%s">%s <span class="slms-admin-badge">#%d</span></a>',
				esc_url( (string) get_edit_post_link( $assignment['id'], 'raw' ) ),
				esc_html( $assignment['label'] ),
				absint( $assignment['id'] )
			);
		}
		echo wp_kses_post( empty( $links ) ? '<span class="slms-admin-muted">—</span>' : implode( '<br>', $links ) );
	}

	/**
	 * Add lesson overview columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public static function lesson_columns( array $columns ): array {
		$columns['slms_courses'] = __( 'Assigned Courses', 'simple-lms' );
		$columns['slms_video']   = __( 'Presto Player Video', 'simple-lms' );
		return $columns;
	}

	/**
	 * Render lesson overview values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Lesson post ID.
	 * @return void
	 */
	public static function lesson_column( string $column, int $post_id ): void {
		if ( 'slms_courses' === $column ) {
			$links = array();
			foreach ( Relationships::get_lesson_courses( $post_id, true ) as $course_id ) {
				$links[] = sprintf(
					'<a href="%s">%s <span class="slms-admin-badge">#%d</span></a>',
					esc_url( (string) get_edit_post_link( $course_id, 'raw' ) ),
					esc_html( get_the_title( $course_id ) ),
					absint( $course_id )
				);
			}
			echo wp_kses_post( empty( $links ) ? '<span class="slms-admin-muted">Unassigned</span>' : implode( '<br>', $links ) );
			return;
		}
		if ( 'slms_video' !== $column ) {
			return;
		}

		$video_id = self::presto_video_id( $post_id );
		if ( $video_id > 0 ) {
			printf( '<span class="slms-admin-badge">ID %d</span><br><span class="slms-admin-muted">Configured</span>', absint( $video_id ) );
		} else {
			echo '<span class="slms-admin-muted">Not set</span>';
		}
	}

	/**
	 * Resolve a Presto Player ID from native meta or a legacy embed.
	 *
	 * @param int $post_id Lesson post ID.
	 * @return int
	 */
	public static function presto_video_id( int $post_id ): int {
		$video_id = absint( get_post_meta( $post_id, '_lms_presto_video_id', true ) );
		if ( $video_id > 0 ) {
			return $video_id;
		}

		$content = (string) get_post_meta( $post_id, 'lesson_video', true ) . ' ' . (string) get_post_field( 'post_content', $post_id );
		if ( preg_match( '/\[presto_player\b[^\]]*\bid\s*=\s*["\']?(\d+)/i', $content, $matches ) ) {
			return absint( $matches[1] );
		}
		if ( preg_match( '/presto[-_]player[^\s"\'<>]*\b(?:id|video_id)=["\']?(\d+)/i', $content, $matches ) ) {
			return absint( $matches[1] );
		}

		return 0;
	}

	/**
	 * Register one protected post meta field.
	 *
	 * @param string $post_type Post type.
	 * @param string $key       Meta key.
	 * @param string $type      REST schema type.
	 * @return void
	 */
	private static function register_meta( string $post_type, string $key, string $type ): void {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'array' === $type ? array( __CLASS__, 'sanitize_ids' ) : null,
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * Sanitize an ID array for registered REST meta.
	 *
	 * @param mixed $value Raw IDs.
	 * @return int[]
	 */
	public static function sanitize_ids( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * Find products and membership levels mapped to a course.
	 *
	 * @param int $course_id Course post ID.
	 * @return array<int,array{id:int,label:string}>
	 */
	private static function course_assignments( int $course_id ): array {
		$assignments = array();
		$queries     = array(
			array( 'product', '_slms_course_id', __( 'Product', 'simple-lms' ) ),
			array( 'product_variation', '_slms_course_id', __( 'Variation', 'simple-lms' ) ),
			array( 'pmpro_membership_level', '_slms_course_ids', __( 'Membership level', 'simple-lms' ) ),
		);
		foreach ( $queries as list( $post_type, $meta_key, $label ) ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => 20,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => $meta_key,
							'value'   => 'array' === str_ends_with( $meta_key, 'ids' ) ? '"' . $course_id . '"' : $course_id,
							'compare' => 'array' === str_ends_with( $meta_key, 'ids' ) ? 'LIKE' : '=',
						),
					),
				)
			);
			foreach ( $posts as $id ) {
				$assignments[ (int) $id ] = array(
					'id'    => (int) $id,
					'label' => $label,
				);
			}
		}
		return array_values( $assignments );
	}
}
