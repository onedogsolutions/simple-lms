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
     * Daily cron callback to check all users for expired access.
     *
     * @return void
     */
    public static function check_expirations()
    {
        // Get all users who have enrollment data.
        $query = new \WP_User_Query(array(
            'meta_key' => '_lms_enrolled_at',
            'meta_compare' => 'EXISTS',
            'fields' => 'ID',
        ));

        $user_ids = $query->get_results();

        foreach ($user_ids as $user_id) {
            self::check_user_expirations($user_id);
        }
    }

    /**
     * Check a specific user's courses for expiration.
     *
     * @param int $user_id User ID.
     * @return void
     */
    public static function check_user_expirations($user_id)
    {
        $enrolled_at = get_user_meta($user_id, '_lms_enrolled_at', true);
        if (!is_array($enrolled_at) || empty($enrolled_at)) {
            return;
        }

        $progress = get_user_meta($user_id, '_lms_progress', true);
        if (!is_array($progress)) {
            $progress = array();
        }

        $changed = false;

        foreach ($enrolled_at as $course_id => $timestamp) {
            $access_days = (int)get_post_meta($course_id, '_lms_access_days', true);

            // 0 = unlimited.
            if ($access_days <= 0) {
                continue;
            }

            $expiry_time = $timestamp + ($access_days * DAY_IN_SECONDS);

            if (time() > $expiry_time) {
                // Access expired.
                unset($enrolled_at[$course_id]);
                unset($progress[$course_id]);
                $changed = true;

                // Clear the queryable progress table too.
                if (class_exists(__NAMESPACE__ . '\\Progress')) {
                    Progress::clear_course($user_id, $course_id);
                }

                // Optional: log or trigger hook for expiration.
                do_action('slms_course_access_expired', $user_id, $course_id);
            }
        }

        if ($changed) {
            update_user_meta($user_id, '_lms_enrolled_at', $enrolled_at);
            update_user_meta($user_id, '_lms_progress', $progress);
        }
    }
}