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
        // Add migration action handler.
        add_action('admin_post_slms_migrate_wpc', array(__CLASS__, 'run_wpc_migration'));

        // Add admin notice if migration is needed.
        add_action('admin_notices', array(__CLASS__, 'migration_notice'));
    }

    /**
     * Display a notice if WP Complete data is found but not migrated.
     *
     * @return void
     */
    public static function migration_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if we have users with 'wpcomplete' meta.
        $count = self::get_pending_migration_count();

        if ($count > 0) {
            $migration_url = admin_url('admin.php?page=slms-students&migrate=1');
            echo '<div class="notice notice-info is-dismissible"><p>';
            printf(
                __('SimpleLMS detected WP Complete data for %d users. <a href="%s" class="button button-primary">Migrate Progress to SimpleLMS</a>', 'simple-lms-bridge'),
                $count,
                esc_url($migration_url)
            );
            echo '</p></div>';
        }
    }

    /**
     * Get count of users who have WP Complete data but haven't been migrated.
     *
     * @return int
     */
    public static function get_pending_migration_count()
    {
        $users = \get_users(array(
            'meta_key' => 'wpcomplete',
            'meta_compare' => 'EXISTS',
            'fields' => 'ID',
        ));

        return \count($users);
    }

    /**
     * Run the migration from WP Complete.
     *
     * WP Complete stores data in 'wpcomplete' user meta as a JSON string:
     * { "post_id": { "completed": "timestamp" }, "post_id-button": { "completed": "timestamp" } }
     *
     * We migrate this to '_lms_progress':
     * [ course_id => [ lesson_id => timestamp ] ]
     *
     * @return void
     */
    public static function run_wpc_migration()
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(\__('Unauthorized', 'simple-lms-bridge'));
        }

        $result = self::migrate_batch(999); // Process a large batch for legacy redirect.

        \wp_redirect(\admin_url('admin.php?page=slms-students&migration_complete=' . $result['count']));
        exit;
    }

    /**
     * Get count of legacy courses that haven't been migrated yet.
     *
     * @return int
     */
    public static function get_pending_content_count()
    {
        $query = new \WP_Query(array(
            'post_type' => 'course',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_slms_migrated',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'fields' => 'ids',
            'posts_per_page' => -1,
        ));

        return (int)$query->found_posts;
    }

    /**
     * Migrate a batch of legacy courses and their lessons.
     *
     * @param int $limit Max courses to migrate.
     * @return array Result summary.
     */
    public static function migrate_content_batch($limit = 5)
    {
        $limit = \absint($limit);
        \error_log( \sprintf('[SimpleLMS] Starting content migration batch. Limit: %d', $limit) );
        $start_time = \microtime(true);

        $legacy_courses = \get_posts(array(
            'post_type' => 'course',
            'post_status' => 'any',
            'numberposts' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_slms_migrated',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        $count = 0;

        foreach ($legacy_courses as $legacy_course) {
            // Import the course.
            $new_course_id = self::import_course($legacy_course);

            if ($new_course_id) {
                // Find lessons (children of this course).
                $legacy_lessons = \get_posts(array(
                    'post_type' => 'course',
                    'post_status' => 'any',
                    'post_parent' => $legacy_course->ID,
                    'orderby' => 'menu_order',
                    'order' => 'ASC',
                    'numberposts' => -1,
                ));

                $lesson_ids = array();

                foreach ($legacy_lessons as $legacy_lesson) {
                    $new_lesson_id = self::import_lesson($legacy_lesson);
                    if ($new_lesson_id) {
                        $lesson_ids[] = (int)$new_lesson_id;
                    }
                }

                // Update course with lesson order via Many-to-Many Join Table.
                Relationships::set_lessons_for_course($new_course_id, $lesson_ids);

                // Mark legacy course as migrated.
                \update_post_meta($legacy_course->ID, '_slms_migrated', \time());
                $count++;
            } else {
                \error_log( \sprintf('[SimpleLMS] Failed to import legacy course ID: %d', $legacy_course->ID) );
            }
        }

        $duration = \round(\microtime(true) - $start_time, 2);
        \error_log( \sprintf('[SimpleLMS] Content migration batch complete. Migrated: %d. Pending: %d. Duration: %s seconds.', $count, self::get_pending_content_count(), $duration) );

        return array(
            'count' => $count,
            'pending' => self::get_pending_content_count(),
        );
    }

    /**
     * Migrate a batch of users (Student Progress).
     *
     * @param int $limit Max users to migrate.
     * @return array Result summary.
     */
    public static function migrate_batch($limit = 10)
    {
        $limit = \absint($limit);
        \error_log( \sprintf('[SimpleLMS] Starting student progress migration batch. Limit: %d', $limit) );
        $start_time = \microtime(true);

        $users = \get_users(array(
            'meta_key' => 'wpcomplete',
            'meta_compare' => 'EXISTS',
            'number' => $limit,
        ));

        $count = 0;

        foreach ($users as $user) {
            $wpc_json = \get_user_meta($user->ID, 'wpcomplete', true);

            if (empty($wpc_json)) {
                continue;
            }

            $wpc_data = \json_decode($wpc_json, true);
            if (!\is_array($wpc_data)) {
                continue;
            }

            $current_progress = \get_user_meta($user->ID, '_lms_progress', true);
            if (!\is_array($current_progress)) {
                $current_progress = array();
            }

            foreach ($wpc_data as $key => $data) {
                $lesson_id = self::extract_post_id($key);

                if (!$lesson_id) {
                    \error_log( \sprintf('[SimpleLMS] Student Migration: Invalid lesson key "%s" for user ID: %d', $key, $user->ID) );
                    continue;
                }

                $post_status = \get_post_status($lesson_id);
                if (!$post_status || 'publish' !== $post_status) {
                    \error_log( \sprintf('[SimpleLMS] Student Migration: Lesson ID %d not found or not published for user ID: %d', $lesson_id, $user->ID) );
                    continue;
                }

                $course_id = self::get_course_id_for_lesson($lesson_id);

                    if ($course_id) {
                        // Enroll user in the course via join table.
                        Relationships::enroll_user($user->ID, $course_id, 'migration');

                        if (!isset($current_progress[$course_id])) {
                            $current_progress[$course_id] = array();
                        }

                        if (!isset($current_progress[$course_id][$lesson_id])) {
                            $timestamp = isset($data['completed']) ? \strtotime($data['completed']) : \time();
                            $current_progress[$course_id][$lesson_id] = $timestamp;
                        }
                    } else {
                    \error_log( \sprintf('[SimpleLMS] Student Migration: Could not determine course for lesson ID %d (User ID: %d)', $lesson_id, $user->ID) );
                }
            }

            \update_user_meta($user->ID, '_lms_progress', $current_progress);
            \error_log( \sprintf('[SimpleLMS] Successfully migrated progress for user: %s (ID: %d)', $user->display_name, $user->ID) );

            // Mark as migrated by removing the old meta.
            \delete_user_meta($user->ID, 'wpcomplete');

            $count++;
        }

        $duration = \round(\microtime(true) - $start_time, 2);
        \error_log( \sprintf('[SimpleLMS] Student progress migration batch complete. Migrated: %d. Pending: %d. Duration: %s seconds.', $count, self::get_pending_migration_count(), $duration) );

        return array(
            'count' => $count,
            'pending' => self::get_pending_migration_count(),
        );
    }

    /**
     * Import or deduplicate a lesson.
     *
     * @param \WP_Post $legacy_lesson Legacy lesson post object.
     * @return int|bool New lesson ID or false.
     */
    private static function import_lesson($legacy_lesson)
    {
        // Deduplicate by title using WP_Query for accuracy.
        $existing = new \WP_Query(array(
            'post_type'      => 'lms_lesson',
            'title'          => $legacy_lesson->post_title,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post_status'    => 'publish',
        ));

        if ($existing->have_posts()) {
            return $existing->posts[0];
        }

        $new_lesson_id = \wp_insert_post(array(
            'post_title' => $legacy_lesson->post_title,
            'post_content' => $legacy_lesson->post_content,
            'post_status' => 'publish',
            'post_type' => 'lms_lesson',
        ));

        if (\is_wp_error($new_lesson_id)) {
            \error_log( \sprintf('[SimpleLMS] Error inserting lesson: %s', $new_lesson_id->get_error_message()) );
            return false;
        }

        // Map Lesson Video (Pods field).
        $video_url = \get_post_meta($legacy_lesson->ID, 'lesson_video', true);
        if ($video_url) {
            // Check if it's a Presto Player video (our system uses Presto IDs, but Pods might store URL or ID).
            // For now, we'll store it as a generic video meta if it doesn't match Presto format.
            \update_post_meta($new_lesson_id, '_lms_lesson_type', 'video');
            \update_post_meta($new_lesson_id, '_lms_lesson_video_url', $video_url);
        }

        \update_post_meta($new_lesson_id, '_legacy_id', $legacy_lesson->ID);

        return $new_lesson_id;
    }

    /**
     * Import a course.
     *
     * @param \WP_Post $legacy_course Legacy course post object.
     * @return int|bool New course ID or false.
     */
    private static function import_course($legacy_course)
    {
        // Deduplicate by title.
        $existing = new \WP_Query(array(
            'post_type'      => 'lms_course',
            'title'          => $legacy_course->post_title,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post_status'    => 'publish',
        ));

        if ($existing->have_posts()) {
            return $existing->posts[0];
        }

        $new_course_id = \wp_insert_post(array(
            'post_title' => $legacy_course->post_title,
            'post_content' => $legacy_course->post_content,
            'post_status' => 'publish',
            'post_type' => 'lms_course',
        ));

        if (\is_wp_error($new_course_id)) {
            \error_log( \sprintf('[SimpleLMS] Error inserting course: %s', $new_course_id->get_error_message()) );
            return false;
        }

        // Map Course Price (Pods field).
        $price = \get_post_meta($legacy_course->ID, 'course_price', true);
        if ($price) {
            \update_post_meta($new_course_id, '_lms_course_price', (float)$price);
        }

        // Map Taxonomy: Course Group -> lms_course_cat.
        $terms = \wp_get_post_terms($legacy_course->ID, 'course_group');
        if (!\is_wp_error($terms) && !empty($terms)) {
            $new_term_ids = array();
            foreach ($terms as $term) {
                $new_term = \wp_insert_term($term->name, 'lms_course_cat', array(
                    'slug' => $term->slug,
                    'description' => $term->description,
                ));
                
                if (\is_wp_error($new_term) && $new_term->get_error_data('term_exists')) {
                    $new_term_ids[] = (int)$new_term->get_error_data('term_exists');
                } elseif (!\is_wp_error($new_term)) {
                    $new_term_ids[] = (int)$new_term['term_id'];
                } else {
                    \error_log( \sprintf('[SimpleLMS] Failed to insert term "%s": %s', $term->name, $new_term->get_error_message()) );
                }
            }
            \wp_set_post_terms($new_course_id, $new_term_ids, 'lms_course_cat');
        }

        \update_post_meta($new_course_id, '_legacy_id', $legacy_course->ID);

        return $new_course_id;
    }

    /**
     * Helper: Extract Post ID from WP Complete Key.
     *
     * @param string $key Key from wpcomplete meta (e.g. "123", "123-btn").
     * @return int Post ID.
     */
    private static function extract_post_id($key)
    {
        if (\strpos($key, '-') !== false) {
            $parts = \explode('-', $key);
            return (int)$parts[0];
        }
        return (int)$key;
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

        // Search the new Many-to-Many join table first.
        $table = $wpdb->prefix . 'slms_course_lesson';
        $course_id = $wpdb->get_var($wpdb->prepare(
            "SELECT course_id FROM $table WHERE lesson_id = %d LIMIT 1",
            $lesson_id
        ));

        if ($course_id) {
            return (int)$course_id;
        }

        // Fallback: search legacy metadata (if any records still exist in old format).
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_simple_lms_order' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like(\sprintf('i:%d;', $lesson_id)) . '%'
        ));

        return !empty($results) ? (int)$results[0] : false;
    }
}