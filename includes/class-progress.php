<?php
/**
 * Course hierarchy and learner progress.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads course relationships and stores idempotent learner state in user meta.
 */
final class Progress {
	/**
	 * Get ordered lesson IDs for a course.
	 *
	 * @param int $course_id Course post ID.
	 * @return int[]
	 */
	public static function lesson_ids_for_course( int $course_id ): array {
		$course_id = absint( $course_id );
		if ( $course_id < 1 ) {
			return array();
		}

		$settings    = Settings::all();
		$course_type = (string) $settings['course_post_type'];
		$lesson_type = (string) $settings['lesson_post_type'];
		$lesson_ids  = array();

		if ( 'post_parent' === $settings['relationship_mode'] ) {
			$query = new \WP_Query(
				array(
					'post_type'      => $lesson_type,
					'post_status'    => 'publish',
					'post_parent'    => $course_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
				)
			);
			$lesson_ids = array_map( 'absint', $query->posts );

			if ( empty( $lesson_ids ) && self::is_standalone_course( $course_id ) ) {
				$lesson_ids = array( $course_id );
			}
		} else {
			$field     = (string) $settings['relationship_field'];
			$direction = (string) $settings['relationship_direction'];
			if ( '' === $field || ! post_type_exists( $course_type ) || ! post_type_exists( $lesson_type ) ) {
				return array();
			}

			if ( 'lesson_to_course' === $direction ) {
				if ( function_exists( 'pods' ) ) {
					$pods = pods( $lesson_type );
					$pods->find(
						array(
							'where' => $field . '.id = ' . $course_id,
							'limit' => -1,
						)
					);
					while ( $pods->fetch() ) {
						$lesson_ids[] = absint( $pods->id() );
					}
				} elseif ( 'meta' === $settings['relationship_storage'] ) {
					$query = new \WP_Query(
						array(
							'post_type'      => $lesson_type,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'meta_query'     => array(
								array(
									'key'     => $field,
									'value'   => $course_id,
									'compare' => '=',
								),
							),
						)
					);
					$lesson_ids = array_map( 'absint', $query->posts );
				}
			} elseif ( function_exists( 'pods' ) ) {
				$pod      = pods( $course_type, $course_id );
				$related  = $pod && $pod->exists() ? $pod->field( $field ) : array();
				$lesson_ids = self::normalize_relationship( $related );
			} elseif ( 'meta' === $settings['relationship_storage'] ) {
				$lesson_ids = self::normalize_relationship( get_post_meta( $course_id, $field, true ) );
			}
		}

		$lesson_ids = array_values(
			array_unique(
				array_filter(
					$lesson_ids,
					static fn( int $id ): bool => $id > 0
						&& $lesson_type === get_post_type( $id )
						&& 'publish' === get_post_status( $id )
				)
			)
		);

		if ( 'menu_order' !== $settings['lesson_order_field'] ) {
			usort( $lesson_ids, array( self::class, 'compare_lessons' ) );
		}

		/**
		 * Filters ordered lesson IDs for a course.
		 *
		 * @param int[] $lesson_ids Lesson post IDs.
		 * @param int   $course_id Course post ID.
		 */
		return array_values( apply_filters( 'simple_lms_course_lesson_ids', $lesson_ids, $course_id ) );
	}

	/**
	 * Get course IDs related to a lesson.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int[]
	 */
	public static function course_ids_for_lesson( int $lesson_id ): array {
		$lesson_id = absint( $lesson_id );
		if ( $lesson_id < 1 ) {
			return array();
		}

		$settings    = Settings::all();
		$course_type = (string) $settings['course_post_type'];
		$lesson_type = (string) $settings['lesson_post_type'];
		$course_ids  = array();

		if ( 'post_parent' === $settings['relationship_mode'] ) {
			$post = get_post( $lesson_id );
			if ( ! $post || $lesson_type !== $post->post_type || 'publish' !== $post->post_status ) {
				return array();
			}

			if ( $post->post_parent > 0 ) {
				$parent = get_post( $post->post_parent );
				if ( $parent && $course_type === $parent->post_type && 'publish' === $parent->post_status ) {
					$course_ids[] = $parent->ID;
				}
			} elseif ( self::is_standalone_course( $lesson_id ) ) {
				$course_ids[] = $lesson_id;
			}

			return array_values( array_unique( $course_ids ) );
		}

		$field = (string) $settings['relationship_field'];
		if ( '' === $field || ! post_type_exists( $course_type ) || ! post_type_exists( $lesson_type ) ) {
			return array();
		}

		if ( 'lesson_to_course' === $settings['relationship_direction'] && function_exists( 'pods' ) ) {
			$pod        = pods( $lesson_type, $lesson_id );
			$related    = $pod && $pod->exists() ? $pod->field( $field ) : array();
			$course_ids = self::normalize_relationship( $related );
		} else {
			$query = new \WP_Query(
				array(
					'post_type'      => $course_type,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $query->posts as $course_id ) {
				if ( in_array( absint( $course_id ), self::lesson_ids_for_course( absint( $course_id ) ), true ) ) {
					$course_ids[] = absint( $course_id );
				}
			}
		}

		return array_values( array_unique( array_filter( $course_ids ) ) );
	}

	/**
	 * Get the first related course for a lesson.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int
	 */
	public static function course_for_lesson( int $lesson_id ): int {
		$course_ids = self::course_ids_for_lesson( $lesson_id );

		return $course_ids[0] ?? 0;
	}

	/**
	 * Mark a lesson complete for a user.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public static function mark_lesson_complete( int $lesson_id, int $course_id = 0, int $user_id = 0 ): bool {
		$lesson_id = absint( $lesson_id );
		$course_id = $course_id > 0 ? absint( $course_id ) : self::course_for_lesson( $lesson_id );
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();
		$lesson_type = (string) Settings::get( 'lesson_post_type', 'course' );

		if ( $lesson_id < 1 || $course_id < 1 || $user_id < 1
			|| $lesson_type !== get_post_type( $lesson_id )
			|| 'publish' !== get_post_status( $lesson_id ) ) {
			return false;
		}

		if ( ! in_array( $lesson_id, self::lesson_ids_for_course( $course_id ), true ) ) {
			return false;
		}

		if ( ! Access::can_access( $course_id, $user_id ) ) {
			return false;
		}

		$meta_key = (string) Settings::get( 'completed_lessons_meta', '_slms_completed_lessons' );
		$progress = get_user_meta( $user_id, $meta_key, true );
		$progress = is_array( $progress ) ? $progress : array();

		$already_complete = isset( $progress[ $lesson_id ] );
		if ( ! $already_complete ) {
			$progress[ $lesson_id ] = array(
				'course_id'    => $course_id,
				'completed_at' => current_time( 'mysql' ),
			);
			update_user_meta( $user_id, $meta_key, $progress );
		}

		self::sync_course_completion( $course_id, $user_id );

		if ( ! $already_complete ) {
			/**
			 * Fires after a lesson completion has been recorded.
			 *
			 * @param int $lesson_id Lesson post ID.
			 * @param int $course_id Course post ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'simple_lms_lesson_completed', $lesson_id, $course_id, $user_id );
		}

		return true;
	}

	/**
	 * Get completed lesson IDs for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public static function completed_lesson_ids( int $user_id = 0 ): array {
		$user_id  = $user_id > 0 ? $user_id : get_current_user_id();
		$meta_key = (string) Settings::get( 'completed_lessons_meta', '_slms_completed_lessons' );
		$progress = get_user_meta( $user_id, $meta_key, true );

		return is_array( $progress ) ? array_map( 'absint', array_keys( $progress ) ) : array();
	}

	/**
	 * Get progress for a course.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return array{total:int,completed:int,percent:int,complete:bool}
	 */
	public static function course_progress( int $course_id, int $user_id = 0 ): array {
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();
		$lesson_ids = self::lesson_ids_for_course( $course_id );
		$completed  = array_intersect( $lesson_ids, self::completed_lesson_ids( $user_id ) );
		$total      = count( $lesson_ids );
		$count      = count( $completed );

		return array(
			'total'     => $total,
			'completed' => $count,
			'percent'   => $total > 0 ? (int) floor( ( $count / $total ) * 100 ) : 0,
			'complete'  => $total > 0 && $count === $total,
		);
	}

	/**
	 * Get the first incomplete lesson in a course.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return int
	 */
	public static function first_incomplete_lesson( int $course_id, int $user_id = 0 ): int {
		$completed = self::completed_lesson_ids( $user_id );
		foreach ( self::lesson_ids_for_course( $course_id ) as $lesson_id ) {
			if ( ! in_array( $lesson_id, $completed, true ) ) {
				return $lesson_id;
			}
		}

		return 0;
	}

	/**
	 * Store derived course completion when every lesson is complete.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return void
	 */
	public static function sync_course_completion( int $course_id, int $user_id = 0 ): void {
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();
		$meta_key  = (string) Settings::get( 'completed_courses_meta', '_slms_completed_courses' );
		$completed = get_user_meta( $user_id, $meta_key, true );
		$completed = is_array( $completed ) ? $completed : array();
		$progress  = self::course_progress( $course_id, $user_id );

		if ( $progress['complete'] && ! isset( $completed[ $course_id ] ) ) {
			$completed[ $course_id ] = current_time( 'mysql' );
			update_user_meta( $user_id, $meta_key, $completed );
			do_action( 'simple_lms_course_completed', $course_id, $user_id );
		} elseif ( ! $progress['complete'] && isset( $completed[ $course_id ] ) ) {
			unset( $completed[ $course_id ] );
			update_user_meta( $user_id, $meta_key, $completed );
		}
	}

	/**
	 * Mark a course verified after a valid Gravity Forms submission.
	 *
	 * @param int    $course_id       Course post ID.
	 * @param int    $entry_id        Gravity Forms entry ID.
	 * @param int    $user_id         User ID.
	 * @param string $pdf_config_id   Gravity PDF configuration ID.
	 * @return bool
	 */
	public static function mark_course_verified( int $course_id, int $entry_id = 0, int $user_id = 0, string $pdf_config_id = '' ): bool {
		$course_id = absint( $course_id );
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();

		if ( $course_id < 1 || $user_id < 1 || ! self::course_progress( $course_id, $user_id )['complete'] ) {
			return false;
		}

		if ( '' === $pdf_config_id && $entry_id > 0 && class_exists( Gravity_Forms::class ) ) {
			$pdf_config_id = Gravity_Forms::resolve_pdf_configuration_for_entry( $entry_id );
		}

		$meta_key = (string) Settings::get( 'verified_courses_meta', '_slms_verified_courses' );
		$verified = get_user_meta( $user_id, $meta_key, true );
		$verified = is_array( $verified ) ? $verified : array();

		$verified[ $course_id ] = array(
			'entry_id'        => absint( $entry_id ),
			'form_id'         => absint( Settings::get( 'verification_form_id', 0 ) ),
			'pdf_config_id'   => $pdf_config_id,
			'verified_at'     => current_time( 'mysql' ),
		);
		update_user_meta( $user_id, $meta_key, $verified );

		do_action( 'simple_lms_course_verified', $course_id, $entry_id, $user_id );

		return true;
	}

	/**
	 * Whether a course has been verified for a user.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public static function is_verified( int $course_id, int $user_id = 0 ): bool {
		$user_id  = $user_id > 0 ? $user_id : get_current_user_id();
		$verified = self::verified_courses( $user_id );

		return isset( $verified[ $course_id ] );
	}

	/**
	 * Get verified course records for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function verified_courses( int $user_id = 0 ): array {
		$user_id  = $user_id > 0 ? $user_id : get_current_user_id();
		$meta_key = (string) Settings::get( 'verified_courses_meta', '_slms_verified_courses' );
		$verified = get_user_meta( $user_id, $meta_key, true );

		return is_array( $verified ) ? $verified : array();
	}

	/**
	 * Build a Gravity PDF download URL for a verified course.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return string
	 */
	public static function certificate_url( int $course_id, int $user_id = 0 ): string {
		$user_id  = $user_id > 0 ? $user_id : get_current_user_id();
		$verified = self::verified_courses( $user_id );

		if ( ! isset( $verified[ $course_id ]['entry_id'] ) ) {
			return '';
		}

		$entry_id      = absint( $verified[ $course_id ]['entry_id'] );
		$form_id       = absint( $verified[ $course_id ]['form_id'] ?? 0 );
		$pdf_config_id = (string) ( $verified[ $course_id ]['pdf_config_id'] ?? '' );

		if ( '' === $pdf_config_id && $entry_id > 0 && class_exists( Gravity_Forms::class ) ) {
			$pdf_config_id = Gravity_Forms::resolve_pdf_configuration_for_entry( $entry_id );
		}

		if ( $entry_id < 1 || $form_id < 1 || '' === $pdf_config_id ) {
			return '';
		}

		if ( class_exists( 'GPDFAPI' ) && method_exists( 'GPDFAPI', 'get_pdf_url' ) ) {
			$url = \GPDFAPI::get_pdf_url( $entry_id, $pdf_config_id, array( 'download' => true ) );
			return is_wp_error( $url ) ? '' : (string) $url;
		}

		return add_query_arg(
			array(
				'gpdf'   => '1',
				'pid'    => $pdf_config_id,
				'lid'    => $entry_id,
				'action' => 'download',
			),
			home_url( '/' )
		);
	}

	/**
	 * Normalize Pods relationship output to post IDs.
	 *
	 * @param mixed $related Relationship value.
	 * @return int[]
	 */
	private static function normalize_relationship( mixed $related ): array {
		if ( ! is_array( $related ) ) {
			$related = array( $related );
		}

		$ids = array();
		foreach ( $related as $item ) {
			if ( is_array( $item ) ) {
				$item = $item['ID'] ?? $item['id'] ?? $item['post_id'] ?? 0;
			} elseif ( is_object( $item ) && method_exists( $item, 'ID' ) ) {
				$item = $item->ID();
			}

			$id = absint( $item );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Compare lessons using the configured ordering field.
	 *
	 * @param int $left  Lesson post ID.
	 * @param int $right Lesson post ID.
	 * @return int
	 */
	private static function compare_lessons( int $left, int $right ): int {
		$field = (string) Settings::get( 'lesson_order_field', 'menu_order' );
		if ( '' === $field || 'menu_order' === $field ) {
			$left_order  = (int) get_post_field( 'menu_order', $left );
			$right_order = (int) get_post_field( 'menu_order', $right );
		} else {
			$left_order  = (int) get_post_meta( $left, $field, true );
			$right_order = (int) get_post_meta( $right, $field, true );
		}

		if ( $left_order === $right_order ) {
			return strcmp( get_the_title( $left ), get_the_title( $right ) );
		}

		return $left_order <=> $right_order;
	}

	/**
	 * Whether a course is a standalone video lesson rather than a parent course.
	 *
	 * @param int $course_id Course post ID.
	 * @return bool
	 */
	private static function is_standalone_course( int $course_id ): bool {
		if ( ! Settings::get( 'standalone_courses_as_lessons', true ) ) {
			return false;
		}

		$course_type = (string) Settings::get( 'course_post_type', 'course' );
		$post        = get_post( $course_id );
		if ( ! $post || $course_type !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}

		$children = get_posts(
			array(
				'post_type'      => $course_type,
				'post_status'    => 'publish',
				'post_parent'    => $course_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $children ) ) {
			return false;
		}

		$video = get_post_meta( $course_id, 'lesson_video', true );
		if ( is_string( $video ) && '' !== trim( $video ) ) {
			return true;
		}

		return has_shortcode( (string) $post->post_content, 'presto_player' );
	}
}
