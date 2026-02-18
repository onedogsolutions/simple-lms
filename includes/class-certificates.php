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

    /**
     * Check if a course is completed and handle certificate automation.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return void
     */
    public static function check_course_completion($user_id, $course_id)
    {
        $lesson_ids = get_post_meta($course_id, '_simple_lms_order', true);
        if (!is_array($lesson_ids) || empty($lesson_ids)) {
            return;
        }

        $progress = get_user_meta($user_id, '_lms_progress', true);
        $course_progress = isset($progress[$course_id]) ? $progress[$course_id] : array();

        $all_done = true;
        foreach ($lesson_ids as $lesson_id) {
            if (!isset($course_progress[$lesson_id])) {
                $all_done = false;
                break;
            }
        }

        if ($all_done) {
            // Check if we've already handled completion for this course.
            $completion_recorded = get_user_meta($user_id, '_lms_completed_at', true);
            if (!is_array($completion_recorded)) {
                $completion_recorded = array();
            }

            if (!isset($completion_recorded[$course_id])) {
                $completion_recorded[$course_id] = time();
                update_user_meta($user_id, '_lms_completed_at', $completion_recorded);

                do_action('slms_course_completed', $user_id, $course_id);

                // Automate certificate generation.
                $form_id = (int)get_post_meta($course_id, '_lms_certificate_form', true);

                if ($form_id > 0 && class_exists('GFAPI')) {
                    // Check if an entry already exists for this user and form to avoid duplicates.
                    $entry_query = array(
                        'form_id' => $form_id,
                        'field_filters' => array(
                            array('key' => 'created_by', 'value' => $user_id)
                        )
                    );
                    $entries = \GFAPI::get_entries($form_id, $entry_query['field_filters']);

                    if (empty($entries)) {
                        $entry = array(
                            'form_id' => $form_id,
                            'created_by' => $user_id,
                            'status' => 'active',
                        );
                        \GFAPI::add_entry($entry);
                    }
                }

                // Revoke access automatically upon completion (if configured or standard behavior).
                self::remove_course_access($user_id, $course_id);
            }
        }
    }
}