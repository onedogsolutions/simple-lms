<?php
/**
 * Certificate handling for SimpleLMS.
 *
 * Removes course access when a certificate form is submitted.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Certificates
 *
 * Hooks into Gravity Forms to handle certificate generation.
 */
class Certificates
{

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        // Hook into Gravity Forms after submission.
        add_action('gform_after_submission', array(__CLASS__, 'handle_certificate_submission'), 10, 2);
    }

    /**
     * Handle certificate form submission.
     *
     * @param array $entry The entry object.
     * @param array $form  The form object.
     * @return void
     */
    public static function handle_certificate_submission($entry, $form)
    {
        $user_id = isset($entry['created_by']) ? (int)$entry['created_by'] : get_current_user_id();

        if (!$user_id) {
            return;
        }

        $form_id = (int)$form['id'];

        // Find courses that use this form for certificates.
        $query = new \WP_Query(array(
            'post_type' => 'lms_course',
            'posts_per_page' => -1,
            'meta_query' => array(
                    array(
                    'key' => '_lms_certificate_form',
                    'value' => $form_id,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ));

        if (!$query->have_posts()) {
            return;
        }

        foreach ($query->posts as $course_id) {
            self::remove_course_access($user_id, $course_id);
        }

        wp_reset_postdata();
    }

    /**
     * Remove a user's access to a course and clear progress.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return void
     */
    private static function remove_course_access($user_id, $course_id)
    {
        // Use the PMPro class helpers if available since they already handle this logic cleanly.
        if (class_exists(__NAMESPACE__ . '\\PMPro')) {
            PMPro::de_enroll_user($user_id, $course_id);
        }
        else {
            // Fallback if PMPro class is missing (should not happen in this plugin).
            $progress = get_user_meta($user_id, '_lms_progress', true);
            if (is_array($progress) && isset($progress[$course_id])) {
                unset($progress[$course_id]);
                update_user_meta($user_id, '_lms_progress', $progress);
            }

            $enrolled = get_user_meta($user_id, '_lms_enrolled_at', true);
            if (is_array($enrolled) && isset($enrolled[$course_id])) {
                unset($enrolled[$course_id]);
                update_user_meta($user_id, '_lms_enrolled_at', $enrolled);
            }
        }

        // Trigger action for others to hook into.
        do_action('slms_certificate_generated', $user_id, $course_id);
    }
}