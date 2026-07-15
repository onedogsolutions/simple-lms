<?php
/**
 * Lesson progress storage for SimpleLMS.
 *
 * Owns the queryable `wp_slms_lesson_progress` table and is the single point
 * through which lesson completion is written and read. Every write is dual-written
 * to the legacy `_lms_progress` user meta (meta stays as a compatibility layer
 * for this release).
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Progress
 *
 * Read/write authority for lesson completion data.
 */
class Progress
{

    /**
     * Fully-qualified progress table name.
     *
     * @var string
     */
    private static $table;

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        global $wpdb;
        self::$table = $wpdb->prefix . 'slms_lesson_progress';
    }

    /**
     * Resolve the table name lazily (init() may not have run yet).
     *
     * @return string
     */
    public static function table()
    {
        if (!self::$table) {
            global $wpdb;
            self::$table = $wpdb->prefix . 'slms_lesson_progress';
        }
        return self::$table;
    }

    /**
     * Create the lesson progress table via dbDelta.
     *
     * @return void
     */
    public static function create_table()
    {
        global $wpdb;

        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            lesson_id bigint(20) NOT NULL,
            completed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_course_lesson (user_id, course_id, lesson_id),
            KEY user_id (user_id),
            KEY course_id (course_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ───────────────────────────────────────────────────────────────────
     * Writes (dual-write: table + legacy meta)
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Mark a lesson complete.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @param int $lesson_id Lesson ID.
     * @param int $timestamp Optional unix timestamp of completion.
     * @return bool
     */
    public static function complete($user_id, $course_id, $lesson_id, $timestamp = 0)
    {
        global $wpdb;

        $user_id   = absint($user_id);
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);

        if (!$user_id || !$course_id || !$lesson_id) {
            return false;
        }

        $timestamp = $timestamp ? (int) $timestamp : time();

        $wpdb->query($wpdb->prepare(
            "INSERT INTO " . self::table() . " (user_id, course_id, lesson_id, completed_at)
             VALUES (%d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE completed_at = VALUES(completed_at)",
            $user_id,
            $course_id,
            $lesson_id,
            gmdate('Y-m-d H:i:s', $timestamp)
        ));

        self::mirror_meta($user_id, $course_id, $lesson_id, true, $timestamp);

        // Trigger certificate automation / completion detection.
        if (class_exists(__NAMESPACE__ . '\Certificates')) {
            Certificates::check_course_completion($user_id, $course_id);
        }

        return true;
    }

    /**
     * Mark a lesson incomplete.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @param int $lesson_id Lesson ID.
     * @return bool
     */
    public static function uncomplete($user_id, $course_id, $lesson_id)
    {
        global $wpdb;

        $user_id   = absint($user_id);
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);

        if (!$user_id || !$course_id || !$lesson_id) {
            return false;
        }

        $wpdb->delete(
            self::table(),
            array('user_id' => $user_id, 'course_id' => $course_id, 'lesson_id' => $lesson_id),
            array('%d', '%d', '%d')
        );

        self::mirror_meta($user_id, $course_id, $lesson_id, false, 0);

        return true;
    }

    /**
     * Write lesson completion state for a user (dual-write wrapper).
     *
     * @param int  $user_id   User ID.
     * @param int  $course_id Course ID.
     * @param int  $lesson_id Lesson ID.
     * @param bool $completed Whether the lesson is completed.
     * @return array The updated full _lms_progress array.
     */
    public static function set_lesson_progress($user_id, $course_id, $lesson_id, $completed = true)
    {
        if ($completed) {
            self::complete($user_id, $course_id, $lesson_id);
        } else {
            self::uncomplete($user_id, $course_id, $lesson_id);
        }
        return get_user_meta($user_id, '_lms_progress', true);
    }

    /**
     * Mirror a single completion change into the legacy `_lms_progress` meta.
     *
     * @param int  $user_id   User ID.
     * @param int  $course_id Course post ID.
     * @param int  $lesson_id Lesson post ID.
     * @param bool $completed Whether the lesson is complete.
     * @param int  $timestamp Completion timestamp.
     * @return void
     */
    private static function mirror_meta($user_id, $course_id, $lesson_id, $completed, $timestamp)
    {
        $progress = get_user_meta($user_id, '_lms_progress', true);
        if (!is_array($progress)) {
            $progress = array();
        }

        if ($completed) {
            if (!isset($progress[$course_id]) || !is_array($progress[$course_id])) {
                $progress[$course_id] = array();
            }
            $progress[$course_id][$lesson_id] = $timestamp;
        } else {
            unset($progress[$course_id][$lesson_id]);
            if (isset($progress[$course_id]) && empty($progress[$course_id])) {
                unset($progress[$course_id]);
            }
        }

        update_user_meta($user_id, '_lms_progress', $progress);
    }

    /**
     * Clear all of a user's progress rows for a course (both table and meta).
     *
     * Called when access is revoked (de-enroll, expiration, certificate).
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return void
     */
    public static function clear_course($user_id, $course_id)
    {
        global $wpdb;

        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id) {
            return;
        }

        $wpdb->delete(
            self::table(),
            array('user_id' => $user_id, 'course_id' => $course_id),
            array('%d', '%d')
        );

        $progress = get_user_meta($user_id, '_lms_progress', true);
        if (is_array($progress) && isset($progress[$course_id])) {
            unset($progress[$course_id]);
            update_user_meta($user_id, '_lms_progress', $progress);
        }
    }

    /* ───────────────────────────────────────────────────────────────────
     * Reads (table-first, meta fallback during migration window)
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Get a user's progress for a single course as [ lesson_id => timestamp ].
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return array
     */
    public static function get_for_user_course($user_id, $course_id)
    {
        global $wpdb;

        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id) {
            return array();
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT lesson_id, completed_at FROM " . self::table() . "
             WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));

        if ($rows) {
            $out = array();
            foreach ($rows as $row) {
                $out[(int) $row->lesson_id] = strtotime($row->completed_at . ' UTC');
            }
            return $out;
        }

        // Migration-window fallback: table not yet backfilled for this scope.
        $meta = get_user_meta($user_id, '_lms_progress', true);
        if (is_array($meta) && isset($meta[$course_id]) && is_array($meta[$course_id])) {
            return $meta[$course_id];
        }

        return array();
    }

    /**
     * Stats for a user within a course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return array{total:int,completed:int,percent:int}
     */
    public static function stats($user_id, $course_id)
    {
        $lesson_ids = Access::get_lesson_ids($course_id);
        $total      = count($lesson_ids);

        $course_progress = self::get_for_user_course($user_id, $course_id);

        $completed = 0;
        foreach ($lesson_ids as $lesson_id) {
            if (isset($course_progress[$lesson_id])) {
                $completed++;
            }
        }

        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return array(
            'total'     => $total,
            'completed' => $completed,
            'percent'   => $percent,
        );
    }

    /**
     * Whether a course is completed by a user.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function is_course_complete($user_id, $course_id)
    {
        $lesson_ids = Access::get_lesson_ids($course_id);

        if (empty($lesson_ids)) {
            return false;
        }

        $course_progress = self::get_for_user_course($user_id, $course_id);

        foreach ($lesson_ids as $lesson_id) {
            if (!isset($course_progress[$lesson_id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get first incomplete lesson ID for a user in a course outline.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return int Lesson ID, or 0 if none.
     */
    public static function first_incomplete($user_id, $course_id)
    {
        $lesson_ids = Access::get_lesson_ids($course_id);

        if (empty($lesson_ids)) {
            return 0;
        }

        $course_progress = self::get_for_user_course($user_id, $course_id);

        foreach ($lesson_ids as $lesson_id) {
            if (!isset($course_progress[$lesson_id])) {
                return (int) $lesson_id;
            }
        }

        return 0;
    }

    /* ───────────────────────────────────────────────────────────────────
     * Backfill
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Backfill the progress table from legacy `_lms_progress` user meta.
     *
     * Idempotent: uses INSERT ... ON DUPLICATE KEY UPDATE so re-running never
     * creates duplicate rows.
     *
     * @param int $limit  Max users to process (0 = all).
     * @param int $offset User query offset.
     * @return array { users, rows, done }
     */
    public static function backfill($limit = 0, $offset = 0)
    {
        global $wpdb;

        $args = array(
            'meta_key'     => '_lms_progress',
            'meta_compare' => 'EXISTS',
            'fields'       => 'ID',
            'orderby'      => 'ID',
            'order'        => 'ASC',
            'offset'       => absint($offset),
        );

        if ($limit > 0) {
            $args['number'] = absint($limit);
        }

        $user_ids   = get_users($args);
        $rows       = 0;
        $table      = self::table();

        foreach ($user_ids as $user_id) {
            $user_id  = (int) $user_id;
            $progress = get_user_meta($user_id, '_lms_progress', true);

            if (!is_array($progress)) {
                continue;
            }

            foreach ($progress as $course_id => $lessons) {
                if (!is_array($lessons)) {
                    continue;
                }

                foreach ($lessons as $lesson_id => $ts) {
                    $course_id = absint($course_id);
                    $lesson_id = absint($lesson_id);
                    if (!$course_id || !$lesson_id) {
                        continue;
                    }

                    $timestamp = is_numeric($ts) ? (int) $ts : (strtotime((string) $ts) ?: time());

                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO {$table} (user_id, course_id, lesson_id, completed_at)
                         VALUES (%d, %d, %d, %s)
                         ON DUPLICATE KEY UPDATE completed_at = VALUES(completed_at)",
                        $user_id,
                        $course_id,
                        $lesson_id,
                        gmdate('Y-m-d H:i:s', $timestamp)
                    ));
                    $rows++;
                }
            }
        }

        $done = ($limit === 0) || (count($user_ids) < $limit);

        return array(
            'users' => count($user_ids),
            'rows'  => $rows,
            'done'  => $done,
        );
    }

    /**
     * Total number of rows in the progress table.
     *
     * @return int
     */
    public static function row_count()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table());
    }
}
