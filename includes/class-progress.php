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
		return Relationships::get_course_lesson_ids( $course_id );
	}

	/**
	 * Backward-compatible name for the ordered course curriculum.
	 *
	 * @param int $course_id Course post ID.
	 * @return int[]
	 */
	public static function get_course_lessons( int $course_id ): array {
		return self::lesson_ids_for_course( $course_id );
	}

	/**
	 * Get course IDs related to a lesson.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int[]
	 */
	public static function course_ids_for_lesson( int $lesson_id ): array {
		return Relationships::get_lesson_courses( $lesson_id );
	}

	/**
	 * Get the first related course for a lesson.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int
	 */
	public static function course_for_lesson( int $lesson_id ): int {
		$course_ids = self::course_ids_for_lesson( $lesson_id );
		foreach ( $course_ids as $course_id ) {
			if ( Access::can_access( $course_id ) ) {
				return $course_id;
			}
		}
		return $course_ids[0] ?? 0;
	}

	/**
	 * Resolve an explicit course context or the first accessible related course.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $course_id Requested course context.
	 * @return int
	 */
	public static function resolve_course_id( int $lesson_id, int $course_id = 0 ): int {
		$course_id = absint( $course_id );
		if ( $course_id > 0 ) {
			return in_array( $lesson_id, self::lesson_ids_for_course( $course_id ), true ) ? $course_id : 0;
		}
		return self::course_for_lesson( $lesson_id );
	}

	/**
	 * Resolve the next lesson or certificate URL in a specific course.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $lesson_id Current lesson post ID.
	 * @param int $user_id   User ID.
	 * @return string
	 */
	public static function get_next_step_url( int $course_id, int $lesson_id = 0, int $user_id = 0 ): string {
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();
		$lesson_id = absint( $lesson_id );
		$next_id   = self::first_incomplete_lesson( $course_id, $user_id );
		if ( $next_id > 0 ) {
			return Relationships::lesson_url( $next_id, $course_id );
		}
		if ( self::course_progress( $course_id, $user_id )['complete'] ) {
			return Gravity_Forms::verification_url( $course_id );
		}
		return $lesson_id > 0 ? Relationships::lesson_url( $lesson_id, $course_id ) : (string) get_permalink( $course_id );
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
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();
		$course_id = self::resolve_course_id( $lesson_id, $course_id );

		if ( $lesson_id < 1 || $course_id < 1 || $user_id < 1
			|| Post_Types::LESSON !== get_post_type( $lesson_id )
			|| Post_Types::COURSE !== get_post_type( $course_id )
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

		foreach ( self::course_ids_for_lesson( $lesson_id ) as $related_course_id ) {
			self::sync_course_completion( $related_course_id, $user_id );
		}

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

		return is_array( $progress )
			? array_values( array_filter( array_map( 'absint', array_keys( $progress ) ) ) )
			: array();
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
		$course_id = absint( $course_id );
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();
		if ( Post_Types::COURSE !== get_post_type( $course_id ) || $user_id < 1 ) {
			return;
		}
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
}
