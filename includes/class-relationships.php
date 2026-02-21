<?php
/**
 * Relationship handling for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Relationships
 *
 * Manages the many-to-many relationship between Courses and Lessons.
 */
class Relationships
{

    /**
     * Join table name for courses and lessons.
     *
     * @var string
     */
    private static $course_lesson_table;

    /**
     * Join table name for users and courses (enrollments).
     *
     * @var string
     */
    private static $user_course_table;

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        global $wpdb;
        self::$course_lesson_table = $wpdb->prefix . 'slms_course_lesson';
        self::$user_course_table   = $wpdb->prefix . 'slms_user_course';
    }

    /**
     * Create the join tables using dbDelta.
     *
     * @return void
     */
    public static function create_table()
    {
        global $wpdb;
        self::init();

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Course-Lesson Join Table
        $sql_cl = "CREATE TABLE " . self::$course_lesson_table . " (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			course_id bigint(20) NOT NULL,
			lesson_id bigint(20) NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY course_lesson (course_id, lesson_id),
			KEY course_id (course_id),
			KEY lesson_id (lesson_id)
		) $charset_collate;";

        // 2. User-Course (Enrollment) Join Table
        $sql_uc = "CREATE TABLE " . self::$user_course_table . " (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			course_id bigint(20) NOT NULL,
			enrolled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			source varchar(50) NOT NULL DEFAULT 'manual',
			PRIMARY KEY (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY user_id (user_id),
			KEY course_id (course_id)
		) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_cl);
        dbDelta($sql_uc);
    }

    /* ─── Course-Lesson Relationships ──────────────────────────────────── */

    /**
     * Get all lessons for a specific course.
     *
     * @param int $course_id The course ID.
     * @return array Array of lesson objects (id, title).
     */
    public static function get_lessons_for_course($course_id)
    {
        global $wpdb;
        self::init();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT l.ID as id, l.post_title as title
			 FROM " . self::$course_lesson_table . " r
			 JOIN {$wpdb->posts} l ON r.lesson_id = l.ID
			 WHERE r.course_id = %d AND l.post_status = 'publish'
			 ORDER BY r.sort_order ASC",
            $course_id
        ));

        return $results ? $results : array();
    }

    /**
     * Get all courses for a specific lesson.
     *
     * @param int $lesson_id The lesson ID.
     * @return array Array of course objects (id, title).
     */
    public static function get_courses_for_lesson($lesson_id)
    {
        global $wpdb;
        self::init();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.ID as id, c.post_title as title
			 FROM " . self::$course_lesson_table . " r
			 JOIN {$wpdb->posts} c ON r.course_id = c.ID
			 WHERE r.lesson_id = %d AND c.post_status = 'publish'
			 ORDER BY c.post_title ASC",
            $lesson_id
        ));

        return $results ? $results : array();
    }

    /**
     * Replace all lessons for a course and sync the _simple_lms_order meta.
     *
     * @param int   $course_id  The course ID.
     * @param array $lesson_ids Array of lesson IDs in order.
     * @return void
     */
    public static function set_lessons_for_course($course_id, $lesson_ids)
    {
        global $wpdb;
        self::init();

        $lesson_ids = array_map('absint', $lesson_ids);
        $lesson_ids = array_filter($lesson_ids);

        // 1. Clear existing relationships for this course.
        $wpdb->delete(self::$course_lesson_table, array('course_id' => $course_id), array('%d'));

        // 2. Insert new relationships.
        if (!empty($lesson_ids)) {
            $sort_order = 0;
            foreach ($lesson_ids as $lesson_id) {
                $wpdb->insert(
                    self::$course_lesson_table,
                    array(
                    'course_id' => $course_id,
                    'lesson_id' => $lesson_id,
                    'sort_order' => $sort_order++,
                ),
                    array('%d', '%d', '%d')
                );
            }
        }

        // 3. Sync meta for the course (used for progress tracking and legacy compatibility).
        update_post_meta($course_id, '_simple_lms_order', $lesson_ids);
    }

    /* ─── User-Course Relationships (Enrollments) ───────────────────────── */

    /**
     * Enroll a user in a course.
     *
     * @param int    $user_id   User ID.
     * @param int    $course_id Course ID.
     * @param string $source    Source of enrollment (manual, pmpro, migration).
     * @return bool True on success.
     */
    public static function enroll_user($user_id, $course_id, $source = 'manual')
    {
        global $wpdb;
        self::init();

        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id) {
            return false;
        }

        $result = $wpdb->replace(
            self::$user_course_table,
            array(
                'user_id'     => $user_id,
                'course_id'   => $course_id,
                'enrolled_at' => current_time('mysql'),
                'source'      => sanitize_text_field($source),
            ),
            array('%d', '%d', '%s', '%s')
        );

        // Initialize progress if not already present.
        $progress = get_user_meta($user_id, '_lms_progress', true);
        if (!is_array($progress)) {
            $progress = array();
        }
        if (!isset($progress[$course_id])) {
            $progress[$course_id] = array();
            update_user_meta($user_id, '_lms_progress', $progress);
        }

        return $result !== false;
    }

    /**
     * Unenroll a user from a course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return bool True on success.
     */
    public static function unenroll_user($user_id, $course_id)
    {
        global $wpdb;
        self::init();

        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        $result = $wpdb->delete(
            self::$user_course_table,
            array('user_id' => $user_id, 'course_id' => $course_id),
            array('%d', '%d')
        );

        // Optional: clear progress? Usually keep for history, but if user wants full unenroll, we might clear it.
        // Existing logic in PMPro class clears progress, so let's be consistent if we want to follow it.
        // $progress = get_user_meta($user_id, '_lms_progress', true);
        // if (is_array($progress) && isset($progress[$course_id])) {
        //     unset($progress[$course_id]);
        //     update_user_meta($user_id, '_lms_progress', $progress);
        // }

        return $result !== false;
    }

    /**
     * Get all courses for a specific user.
     *
     * @param int $user_id User ID.
     * @return array Array of objects {id, title, enrolled_at, source}.
     */
    public static function get_courses_for_user($user_id)
    {
        global $wpdb;
        self::init();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.ID as id, c.post_title as title, uc.enrolled_at, uc.source
			 FROM " . self::$user_course_table . " uc
			 JOIN {$wpdb->posts} c ON uc.course_id = c.ID
			 WHERE uc.user_id = %d AND c.post_status = 'publish'
			 ORDER BY c.post_title ASC",
            $user_id
        ));

        return $results ? $results : array();
    }

    /**
     * Get all students for a specific course.
     *
     * @param int $course_id Course ID.
     * @return array Array of objects {id, display_name, email, enrolled_at, source}.
     */
    public static function get_users_for_course($course_id)
    {
        global $wpdb;
        self::init();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID as id, u.display_name, u.user_email as email, uc.enrolled_at, uc.source
			 FROM " . self::$user_course_table . " uc
			 JOIN {$wpdb->users} u ON uc.user_id = u.ID
			 WHERE uc.course_id = %d
			 ORDER BY u.display_name ASC",
            $course_id
        ));

        return $results ? $results : array();
    }

}