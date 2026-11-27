<?php
/**
 * Ordered many-to-many course relationships.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores course order and a synchronized reverse lesson lookup in post meta.
 */
final class Relationships {
	public const COURSE_LESSONS = '_slms_lesson_ids';
	public const LESSON_COURSES = '_slms_course_ids';

	/**
	 * Get ordered lesson IDs for a course.
	 *
	 * @param int  $course_id      Course post ID.
	 * @param bool $include_drafts Include unpublished lessons for editing.
	 * @return int[]
	 */
	public static function get_course_lesson_ids( int $course_id, bool $include_drafts = false ): array {
		$course_id = absint( $course_id );
		if ( $course_id < 1 || Post_Types::COURSE !== get_post_type( $course_id ) ) {
			return array();
		}

		$ids = self::normalize_ids( get_post_meta( $course_id, self::COURSE_LESSONS, true ) );
		$ids = array_values(
			array_filter(
				$ids,
				static function ( int $id ) use ( $include_drafts ): bool {
					if ( Post_Types::LESSON !== get_post_type( $id ) ) {
						return false;
					}
					$status = get_post_status( $id );
					return $include_drafts ? 'trash' !== $status : 'publish' === $status;
				}
			)
		);

		/**
		 * Filters the ordered lessons assigned to a course.
		 *
		 * @param int[] $ids       Lesson post IDs.
		 * @param int   $course_id Course post ID.
		 */
		return array_values( array_unique( array_filter( (array) apply_filters( 'simple_lms_course_lesson_ids', $ids, $course_id ), 'absint' ) ) );
	}

	/**
	 * Replace a course's ordered lessons and synchronize reverse lookups.
	 *
	 * @param int   $course_id Course post ID.
	 * @param int[] $lesson_ids Ordered lesson post IDs.
	 * @return bool
	 */
	public static function set_course_lesson_ids( int $course_id, array $lesson_ids ): bool {
		$course_id = absint( $course_id );
		if ( $course_id < 1 || Post_Types::COURSE !== get_post_type( $course_id ) ) {
			return false;
		}

		$previous = self::get_course_lesson_ids( $course_id, true );
		$next     = array();
		foreach ( $lesson_ids as $lesson_id ) {
			$lesson_id = absint( $lesson_id );
			if ( $lesson_id > 0 && $lesson_id !== $course_id
				&& Post_Types::LESSON === get_post_type( $lesson_id )
				&& 'trash' !== get_post_status( $lesson_id ) ) {
				$next[] = $lesson_id;
			}
		}
		$next = array_values( array_unique( $next ) );

		if ( $previous === $next ) {
			return true;
		}

		update_post_meta( $course_id, self::COURSE_LESSONS, $next );
		foreach ( array_diff( $previous, $next ) as $lesson_id ) {
			self::remove_course( $lesson_id, $course_id );
		}
		foreach ( array_diff( $next, $previous ) as $lesson_id ) {
			self::add_course( $lesson_id, $course_id );
		}
		clean_post_cache( $course_id );
		do_action( 'simple_lms_course_lessons_updated', $course_id, $next );

		return self::normalize_ids( get_post_meta( $course_id, self::COURSE_LESSONS, true ) ) === $next;
	}

	/**
	 * Get course IDs from a lesson's synchronized reverse lookup.
	 *
	 * @param int  $lesson_id      Lesson post ID.
	 * @param bool $include_drafts Include unpublished courses for editing.
	 * @return int[]
	 */
	public static function get_lesson_courses( int $lesson_id, bool $include_drafts = false ): array {
		$lesson_id = absint( $lesson_id );
		if ( $lesson_id < 1 || Post_Types::LESSON !== get_post_type( $lesson_id ) ) {
			return array();
		}

		$ids = self::normalize_ids( get_post_meta( $lesson_id, self::LESSON_COURSES, true ) );
		return array_values(
			array_filter(
				$ids,
				static function ( int $id ) use ( $include_drafts ): bool {
					if ( Post_Types::COURSE !== get_post_type( $id ) ) {
						return false;
					}
					$status = get_post_status( $id );
					return $include_drafts ? 'trash' !== $status : 'publish' === $status;
				}
			)
		);
	}

	/**
	 * Build a lesson URL in a specific course context.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $course_id Course post ID.
	 * @return string
	 */
	public static function lesson_url( int $lesson_id, int $course_id = 0 ): string {
		$url = (string) get_permalink( $lesson_id );
		if ( $course_id > 0 && in_array( absint( $course_id ), self::get_lesson_courses( $lesson_id, true ), true ) ) {
			$url = add_query_arg( 'course_id', absint( $course_id ), $url );
		}
		return $url;
	}

	/**
	 * Normalize scalar, serialized array, or relationship values to IDs.
	 *
	 * @param mixed $value Relationship value.
	 * @return int[]
	 */
	public static function normalize_ids( mixed $value ): array {
		if ( is_object( $value ) && method_exists( $value, 'ID' ) ) {
			$value = $value->ID();
		}
		$items = is_array( $value ) ? $value : array( $value );
		$ids   = array();
		foreach ( $items as $item ) {
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
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Add a course to a lesson's reverse lookup.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $course_id Course post ID.
	 * @return void
	 */
	private static function add_course( int $lesson_id, int $course_id ): void {
		$courses   = self::get_lesson_courses( $lesson_id, true );
		$courses[] = $course_id;
		update_post_meta( $lesson_id, self::LESSON_COURSES, array_values( array_unique( $courses ) ) );
		clean_post_cache( $lesson_id );
	}

	/**
	 * Remove a course from a lesson's reverse lookup.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $course_id Course post ID.
	 * @return void
	 */
	private static function remove_course( int $lesson_id, int $course_id ): void {
		$courses = array_values( array_diff( self::get_lesson_courses( $lesson_id, true ), array( $course_id ) ) );
		update_post_meta( $lesson_id, self::LESSON_COURSES, $courses );
		clean_post_cache( $lesson_id );
	}
}
