<?php
/**
 * Meta box and admin page registration for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MetaBoxes
 *
 * Registers meta boxes on Course/Lesson edit screens and the Student Manager admin page.
 */
class MetaBoxes {


	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
	}

	/* ───────────────────────────────────────────────────────────────────
	 * Meta Boxes
	 * ─────────────────────────────────────────────────────────────────── */

	/**
	 * Register meta boxes for Course and Lesson CPTs.
	 *
	 * @return void
	 */
	public static function register_meta_boxes() {

		// Course Editor — Lesson Sorter & Settings.
		add_meta_box(
			'slms_course_editor',
			__( 'Course Settings', 'simple-lms-bridge' ),
			array( __CLASS__, 'render_react_root' ),
			'slms_course',
			'normal',
			'high'
		);

		// Lesson Settings — Type, Video, Quiz, Timer.
		add_meta_box(
			'slms_lesson_settings',
			__( 'Lesson Settings', 'simple-lms-bridge' ),
			array( __CLASS__, 'render_react_root' ),
			'slms_lesson',
			'normal',
			'high'
		);
	}

	/**
	 * Render the React mount point.
	 *
	 * Both meta boxes share the same root div; the React app decides
	 * what to render based on slmsAdmin.postType.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_react_root( $post ) {
		echo '<div id="slms-admin-root"></div>';
	}

	/* ───────────────────────────────────────────────────────────────────
	 * Admin Pages
	 * ─────────────────────────────────────────────────────────────────── */

	/**
	 * Render the Student Manager page shell.
	 *
	 * @return void
	 */
	public static function render_students_page() {
		echo '<div class="wrap slms-admin-wrap">';
		echo '<div id="slms-admin-root" class="bg-white rounded-xl shadow-md p-8 border border-gray-200"></div>';
		echo '</div>';
	}
}
