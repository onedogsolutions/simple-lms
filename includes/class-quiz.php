<?php
/**
 * Quiz completion intelligence for SimpleLMS.
 *
 * Auto-completes quiz lessons when their linked Gravity Form is submitted,
 * with an optional passing-score gate. Runs through the same completion path
 * as the REST /progress endpoint (Access::set_lesson_progress) so certificate
 * automation stays consistent.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Quiz
 */
class Quiz {


	/**
	 * Hook into WordPress / Gravity Forms.
	 *
	 * @return void
	 */
	public static function init() {
		// Priority 5 so this runs before the certificate submission handler.
		add_action( 'gform_after_submission', array( __CLASS__, 'handle_quiz_submission' ), 5, 2 );
	}

	/**
	 * Handle a Gravity Forms submission and auto-complete matching quiz lessons.
	 *
	 * @param array $entry The entry object.
	 * @param array $form  The form object.
	 * @return void
	 */
	public static function handle_quiz_submission( $entry, $form ) {
		if ( empty( $form['id'] ) ) {
			return;
		}

		$form_id = (int) $form['id'];

		$user_id = isset( $entry['created_by'] ) ? (int) $entry['created_by'] : get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$lesson_ids = self::get_quiz_lessons_for_form( $form_id );
		if ( empty( $lesson_ids ) ) {
			return;
		}

		foreach ( $lesson_ids as $lesson_id ) {
			// Enforce optional passing-score gate.
			if ( ! self::passes_score_gate( $lesson_id, $entry ) ) {
				continue;
			}

			$courses = Relationships::get_courses_for_lesson( $lesson_id );
			if ( empty( $courses ) ) {
				continue;
			}

			foreach ( $courses as $course ) {
				$course_id = (int) $course->id;

				// Only mark complete for courses the user actually belongs to.
				if ( ! Access::is_enrolled( $user_id, $course_id ) ) {
					continue;
				}

				Access::set_lesson_progress( $user_id, $course_id, $lesson_id, true );
			}

			/**
			 * Fires when a quiz lesson is auto-completed via form submission.
			 *
			 * @param int   $user_id   User ID.
			 * @param int   $lesson_id Lesson ID.
			 * @param array $entry     Gravity Forms entry.
			 */
			do_action( 'slms_quiz_completed', $user_id, $lesson_id, $entry );
		}
	}

	/**
	 * Find quiz-type lessons whose _lms_gravity_form matches the given form.
	 *
	 * @param int $form_id Gravity Form ID.
	 * @return int[] Lesson IDs.
	 */
	private static function get_quiz_lessons_for_form( $form_id ) {
		$query = new \WP_Query(
			array(
				'post_type'      => 'slms_lesson',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_lms_gravity_form',
						'value'   => $form_id,
						'compare' => '=',
					),
					array(
						'key'     => '_slms_lesson_type',
						'value'   => 'quiz',
						'compare' => '=',
					),
				),
			)
		);

		$ids = array_map( 'intval', $query->posts );
		wp_reset_postdata();

		return $ids;
	}

	/**
	 * Whether a submission satisfies the lesson's optional passing-score gate.
	 *
	 * When no gate is configured (_lms_quiz_pass_field empty) the submission
	 * always passes.
	 *
	 * @param int   $lesson_id Lesson ID.
	 * @param array $entry     Gravity Forms entry.
	 * @return bool
	 */
	private static function passes_score_gate( $lesson_id, $entry ) {
		$pass_field = trim( (string) get_post_meta( $lesson_id, '_lms_quiz_pass_field', true ) );

		if ( '' === $pass_field ) {
			return true;
		}

		$min_score = (float) get_post_meta( $lesson_id, '_lms_quiz_pass_min', true );

		// Read the score value from the entry (supports GF quiz score fields).
		$raw_score = self::get_entry_value( $entry, $pass_field );

		// Strip any non-numeric decoration (e.g. "8/10", "80%").
		$score = is_numeric( $raw_score ) ? (float) $raw_score : (float) preg_replace( '/[^0-9.\-]/', '', (string) $raw_score );

		return $score >= $min_score;
	}

	/**
	 * Read a field value from a Gravity Forms entry.
	 *
	 * GF entries are plain arrays keyed by field ID string (including sub-inputs
	 * like "3.1"), so direct access is equivalent to rgar() here without taking
	 * a dependency on the Gravity Forms helper being loaded.
	 *
	 * @param array  $entry    Entry array.
	 * @param string $field_id Field ID (may include sub-inputs like "3.1").
	 * @return mixed
	 */
	private static function get_entry_value( $entry, $field_id ) {
		return isset( $entry[ $field_id ] ) ? $entry[ $field_id ] : '';
	}
}
