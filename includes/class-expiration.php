<?php
/**
 * Course access expiration logic for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Expiration
 *
 * Handles daily cron job to expire user course access.
 */
class Expiration
{

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        add_action('slms_daily_access_check', array(__CLASS__, 'check_expirations'));

        if (!wp_next_scheduled('slms_daily_access_check')) {
            wp_schedule_event(time(), 'daily', 'slms_daily_access_check');
        }
    }

    /**
     * Daily cron callback to check all enrollments for expired access.
     *
     * Iterates the canonical enrollment table (wp_slms_user_course) so that
     * every enrollment is considered, not only PMPro-sourced ones that happened
     * to leave behind `_lms_enrolled_at` user meta.
     *
     * @return void
     */
    public static function check_expirations()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'slms_user_course';

        $rows = $wpdb->get_results(
            "SELECT user_id, course_id, enrolled_at FROM {$table}"
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            self::maybe_expire_enrollment(
                (int) $row->user_id,
                (int) $row->course_id,
                $row->enrolled_at
            );
        }
    }

    /**
     * Expire a single enrollment if its access window has elapsed.
     *
     * @param int    $user_id     User ID.
     * @param int    $course_id   Course ID.
     * @param string $enrolled_at Enrollment timestamp (MySQL datetime or UNIX).
     * @return void
     */
    public static function maybe_expire_enrollment($user_id, $course_id, $enrolled_at)
    {
        $access_days = (int) get_post_meta($course_id, '_lms_access_days', true);

        // 0 = unlimited access.
        if ($access_days <= 0) {
            return;
        }

        $enrolled_time = is_numeric($enrolled_at)
            ? (int) $enrolled_at
            : strtotime((string) $enrolled_at);

        if (!$enrolled_time) {
            return;
        }

        $expiry_time = $enrolled_time + ($access_days * DAY_IN_SECONDS);

        if (time() <= $expiry_time) {
            return;
        }

        // Access expired — remove the enrollment.
        Relationships::unenroll_user($user_id, $course_id);

        // Clear the queryable progress table for this course.
        if (class_exists(__NAMESPACE__ . '\\Progress')) {
            Progress::clear_course($user_id, $course_id);
        }

        // Clear legacy progress meta for this course only.
        $progress = get_user_meta($user_id, '_lms_progress', true);
        if (is_array($progress) && isset($progress[$course_id])) {
            unset($progress[$course_id]);
            update_user_meta($user_id, '_lms_progress', $progress);
        }

        // Keep the legacy `_lms_enrolled_at` meta in sync for back-compat consumers.
        $enrolled_meta = get_user_meta($user_id, '_lms_enrolled_at', true);
        if (is_array($enrolled_meta) && isset($enrolled_meta[$course_id])) {
            unset($enrolled_meta[$course_id]);
            update_user_meta($user_id, '_lms_enrolled_at', $enrolled_meta);
        }

        do_action('slms_course_access_expired', $user_id, $course_id);
    }
}