<?php
/**
 * Migration utility for SimpleLMS.
 *
 * Migrates lesson completion data from WP Complete to SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Migration
 *
 * Handles data conversion from legacy formats.
 */
class Migration
{

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        // Add a simple admin action for now. In a real scenario, this might have a dedicated UI.
        add_action('admin_post_slms_migrate_wpc', array(__CLASS__, 'run_wpc_migration'));
    }

    /**
     * Run the migration from WP Complete.
     *
     * WP Complete stores completed lesson IDs in an array under the '_wpc_completed_lessons' user meta.
     * We need to map these to the new structure: _lms_progress[course_id][lesson_id] = timestamp.
     *
     * @return void
     */
    public static function run_wpc_migration()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'simple-lms-bridge'));
        }

        $users = get_users(array(
            'meta_key' => '_wpc_completed_lessons',
            'meta_compare' => 'EXISTS',
        ));

        $count = 0;

        foreach ($users as $user) {
            $wpc_lessons = get_user_meta($user->ID, '_wpc_completed_lessons', true);
            if (!is_array($wpc_lessons)) {
                continue;
            }

            $current_progress = get_user_meta($user->ID, '_lms_progress', true);
            if (!is_array($current_progress)) {
                $current_progress = array();
            }

            foreach ($wpc_lessons as $lesson_id) {
                $lesson_id = (int)$lesson_id;

                // We need to find which course this lesson belongs to.
                // In our model, lessons are linked to courses via the '_simple_lms_order' meta on courses.
                $course_id = self::get_course_id_for_lesson($lesson_id);

                if ($course_id) {
                    if (!isset($current_progress[$course_id])) {
                        $current_progress[$course_id] = array();
                    }
                    if (!isset($current_progress[$course_id][$lesson_id])) {
                        $current_progress[$course_id][$lesson_id] = time(); // Use current time as timestamp.
                    }
                }
            }

            update_user_meta($user->ID, '_lms_progress', $current_progress);
            $count++;
        }

        wp_redirect(admin_url('admin.php?page=slms-students&migration_complete=' . $count));
        exit;
    }

    /**
     * Helper to find the course ID for a given lesson ID.
     *
     * @param int $lesson_id Lesson post ID.
     * @return int|bool Course ID or false if not found.
     */
    private static function get_course_id_for_lesson($lesson_id)
    {
        global $wpdb;

        // Search courses that have this lesson in their order array.
        // Serialized search is slow but acceptable for a one-time migration.
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_simple_lms_order' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like(sprintf('i:%d;', $lesson_id)) . '%'
        ));

        return !empty($results) ? (int)$results[0] : false;
    }
}