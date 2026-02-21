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
     * Run the student progress migration from WP Complete.
     */
    public static function run_wpc_migration()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'simple-lms-bridge'));
        }

        $result = self::migrate_progress_batch(100); // Higher limit for manual trigger.

        wp_redirect(admin_url('admin.php?page=slms-students&migration_complete=' . $result['count']));
        exit;
    }

    /**
     * Phase 1: CPT Migration.
     * Imports legacy Course CPT and their child lessons into the new schema.
     *
     * @param int $limit Max courses to migrate in this batch.
     * @return array Result summary.
     */
    public static function migrate_cpt_batch($limit = 5)
    {
        $limit = absint($limit);
        error_log('[SimpleLMS] Phase 1: Starting content migration.');
        $start_time = microtime(true);

        $legacy_courses = get_posts(array(
            'post_type'      => 'course',
            'post_status'    => 'publish',
            'post_parent'    => 0, // Only parents are "Courses"
            'numberposts'    => $limit,
            'meta_query'     => array(
                array(
                    'key'     => '_slms_migrated',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ));

        $count = 0;

        foreach ($legacy_courses as $legacy_course) {
            // 1. Import or find current slms_course
            $new_course_id = self::import_course($legacy_course);

            if ($new_course_id) {
                // Retrieve the course group taxonomy from the legacy course
                $terms = wp_get_post_terms($legacy_course->ID, 'slms_course_cat', array('fields' => 'ids'));
                if (!empty($terms) && !is_wp_error($terms)) {
                    // Strictly associate the new course with its group
                    wp_set_post_terms($new_course_id, $terms, 'slms_course_cat');
                }

                // 2. Identify child posts (lessons)
                $legacy_lessons = get_posts(array(
                    'post_type'      => 'course',
                    'post_status'    => 'publish',
                    'post_parent'    => $legacy_course->ID,
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                    'numberposts'    => -1,
                ));

                $new_lesson_ids = array();

                if (empty($legacy_lessons)) {
                    // Course has no child lessons, generate a new slms_lesson from the course content
                    $new_lesson_id = self::import_lesson($legacy_course);
                    if ($new_lesson_id) {
                        $new_lesson_ids[] = (int)$new_lesson_id;

                        // Strictly associate the new lesson with its parent course via the taxonomy
                        if (!empty($terms) && !is_wp_error($terms)) {
                            // Pass true to APPEND terms, allowing a deduplicated lesson to have multiple parent groups
                            wp_set_post_terms($new_lesson_id, $terms, 'slms_course_cat', true);
                        }
                    }
                } else {
                    foreach ($legacy_lessons as $legacy_lesson) {
                        // 3. Import/Deduplicate lessons
                        $new_lesson_id = self::import_lesson($legacy_lesson);
                        if ($new_lesson_id) {
                            $new_lesson_ids[] = (int)$new_lesson_id;

                            // Strictly associate the new lesson with its parent course via the taxonomy
                            if (!empty($terms) && !is_wp_error($terms)) {
                                // Pass true to APPEND terms, allowing a deduplicated lesson to have multiple parent groups
                                wp_set_post_terms($new_lesson_id, $terms, 'slms_course_cat', true);
                            }
                        }
                    }
                }

                // 4. Link via Many-to-Many bridge
                if (!empty($new_lesson_ids)) {
                    Relationships::set_lessons_for_course($new_course_id, $new_lesson_ids);
                }

                // Mark legacy course as migrated
                update_post_meta($legacy_course->ID, '_slms_migrated', time());
                $count++;
            }
        }

        $duration = round(microtime(true) - $start_time, 2);
        return array(
            'processed' => $count,
            'pending'   => self::get_pending_content_count(),
            'duration'  => $duration,
            'success'   => true,
        );
    }

    /**
     * Alias for Phase 2 migration to maintain compatibility with legacy calls.
     */
    public static function migrate_batch($limit = 10)
    {
        return self::migrate_progress_batch($limit);
    }

    /**
     * Phase 2: Student Progress (WPComplete) Migration.
     *
     * @param int $limit Max users to migrate in this batch.
     * @return array Result summary.
     */
    public static function migrate_progress_batch($limit = -1)
    {
        $limit = (int) $limit;
        error_log('[SimpleLMS] Phase 2: Starting student progress migration.');
        $start_time = microtime(true);

        global $wpdb;

        $sql = "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%'";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        $user_ids = $wpdb->get_col($sql);
        $count = 0;

        foreach ($user_ids as $user_id) {
            $user_id = (int)$user_id;

            $wpc_metas = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND (meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%%')",
                $user_id
            ));

            if (empty($wpc_metas)) {
                continue;
            }

            $current_progress = get_user_meta($user_id, '_lms_progress', true);
            if (!is_array($current_progress)) {
                $current_progress = array();
            }

            foreach ($wpc_metas as $meta) {
                $key = $meta->meta_key;
                $value = $meta->meta_value;

                $data = maybe_unserialize($value);
                if (is_string($data) && strpos(trim($data), '{') === 0) {
                    $data = json_decode($data, true);
                }

                if ($key === 'wpcomplete' && is_array($data)) {
                    foreach ($data as $post_key => $post_data) {
                        if ($post_key === '0-site' || strpos($post_key, '0-site') !== false) continue;

                        $legacy_lesson_id = self::extract_post_id($post_key);
                        if (!$legacy_lesson_id) continue;

                        self::process_legacy_lesson_progress($user_id, $legacy_lesson_id, $post_data, $current_progress);
                    }
                } else {
                    if (strpos($key, 'wpcomplete_0-site') !== false) {
                        delete_user_meta($user_id, $key);
                        continue;
                    }

                    $legacy_lesson_id = (int) preg_replace('/[^0-9]/', '', str_replace('wpcomplete_', '', $key));
                    if (!$legacy_lesson_id) {
                        delete_user_meta($user_id, $key);
                        continue;
                    }

                    self::process_legacy_lesson_progress($user_id, $legacy_lesson_id, $data, $current_progress);
                }

                delete_user_meta($user_id, $key);
            }

            update_user_meta($user_id, '_lms_progress', $current_progress);
            $count++;
        }

        $duration = round(microtime(true) - $start_time, 2);
        return array(
            'processed' => $count,
            'pending'   => self::get_pending_migration_count(),
            'duration'  => $duration,
            'success'   => true,
        );
    }

    /**
     * Helper to process legacy lesson completions.
     */
    private static function process_legacy_lesson_progress($user_id, $legacy_lesson_id, $data, &$current_progress) {
        $new_lesson_query = new \WP_Query(array(
            'post_type'      => 'slms_lesson',
            'meta_key'       => '_legacy_id',
            'meta_value'     => $legacy_lesson_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        if (!$new_lesson_query->have_posts()) {
            return;
        }

        $new_lesson_id = $new_lesson_query->posts[0];
        $timestamp = time();
        if (is_array($data) && !empty($data['completed'])) {
            $timestamp = strtotime($data['completed']) ?: time();
        } else if (is_string($data) && strtotime($data)) {
            $timestamp = strtotime($data);
        } else if (is_numeric($data)) {
            $timestamp = (int)$data;
        }

        $linked_courses = Relationships::get_courses_for_lesson($new_lesson_id);
        if (empty($linked_courses)) {
            return;
        }

        foreach ($linked_courses as $course_obj) {
            $course_id = $course_obj->id;
            $is_enrolled = false;

            if (class_exists('SimpleLMS\PMPro') && PMPro::has_course_access($user_id, $course_id)) {
                $is_enrolled = true;
            }

            if (!$is_enrolled) {
                $enrolled_meta = get_user_meta($user_id, '_lms_enrolled_at', true);
                if (is_array($enrolled_meta) && isset($enrolled_meta[$course_id])) {
                    $is_enrolled = true;
                }
            }

            if ($is_enrolled) {
                Relationships::enroll_user($user_id, $course_id, 'migration');

                if (!isset($current_progress[$course_id])) {
                    $current_progress[$course_id] = array();
                }
                
                $current_progress[$course_id][$new_lesson_id] = $timestamp;
            }
        }
    }

    /**
     * Phase 3: Historical Certificate Migration (GF).
     *
     * @param int $limit Max users to migrate in this batch.
     * @return array Result summary.
     */
    public static function migrate_history_batch($limit = 10)
    {
        $limit = absint($limit);
        $start_time = microtime(true);
        global $wpdb;

        $users = get_users(array(
            'meta_key' => '_lms_history_migrated',
            'meta_compare' => 'NOT EXISTS',
            'number' => $limit,
            'fields' => 'ID'
        ));

        $count = 0;
        foreach ($users as $user_id) {
            if (class_exists('GFAPI')) {
                $user = get_userdata($user_id);
                if ($user) {
                    $forms = \GFAPI::get_forms();
                    $cert_form_ids = array();
                    foreach ($forms as $form) {
                        if (stripos($form['title'], 'Certificate') !== false) {
                            $cert_form_ids[] = $form['id'];
                        }
                    }
                    
                    $form_ids = !empty($cert_form_ids) ? $cert_form_ids : 0;
                    
                    $search_criteria = array(
                        'status' => 'active',
                        'field_filters' => array(
                            'mode' => 'any',
                            array('key' => 'created_by', 'value' => $user_id),
                        ),
                    );
                    $entries = \GFAPI::get_entries($form_ids, $search_criteria);
                    
                    $search_criteria_email = array(
                        'status' => 'active',
                        'field_filters' => array(
                            'mode' => 'any',
                            array('value' => $user->user_email),
                        ),
                    );
                    $entries_by_email = \GFAPI::get_entries($form_ids, $search_criteria_email);
                    
                    $all_entries = array_merge((array)$entries, (array)$entries_by_email);
                    
                    $unique_entries = array();
                    foreach ($all_entries as $entry) {
                        if (isset($entry['id']) && !isset($unique_entries[$entry['id']])) {
                            $unique_entries[$entry['id']] = $entry;
                        }
                    }

                    $history = array();
                    foreach ($unique_entries as $entry) {
                        $course_name = __('Unknown Course', 'simple-lms-bridge');
                        $form = \GFAPI::get_form($entry['form_id']);
                        
                        if ($form && isset($form['fields'])) {
                            foreach ($form['fields'] as $field) {
                                if (stripos($field->label, 'Course') !== false) {
                                    $value = rgar($entry, (string)$field->id);
                                    if (!empty($value)) {
                                        $course_name = $value;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if ($course_name === __('Unknown Course', 'simple-lms-bridge') && $form) {
                             $course_name = str_ireplace('Certificate', '', $form['title']);
                             $course_name = trim($course_name, ' -');
                        }

                        $history[] = array(
                            'id' => $entry['id'],
                            'course_name' => $course_name,
                            'date' => $entry['date_created'],
                            'form_title' => $form ? $form['title'] : '',
                        );
                    }

                    if (!empty($history)) {
                        update_user_meta($user_id, '_lms_historical_certificates', $history);
                    }
                }
            }

            update_user_meta($user_id, '_lms_history_migrated', time());
            $count++;
        }

        $duration = round(microtime(true) - $start_time, 2);
        return array(
            'processed' => $count,
            'pending'   => self::get_pending_history_count(),
            'duration'  => $duration,
            'success'   => true,
        );
    }

    /**
     * Renamed Phase 2 method for consistency.
     */
    public static function migrate_student_progress_batch($limit = 10)
    {
        return self::migrate_progress_batch($limit);
    }

    /**
     * Phase 4: Legacy Cleanup.
     * Safely removes legacy posts after verification.
     *
     * @return int Number of deleted posts.
     */
    public static function cleanup_legacy_data()
    {
        $legacy_posts = get_posts(array(
            'post_type'   => 'course',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
        ));

        $count = 0;
        foreach ($legacy_posts as $post_id) {
            // wp_delete_post(..., true) skips trash and goes straight to deletion
            if (wp_delete_post($post_id, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Helper: Import or deduplicate a lesson.
     */
    private static function import_lesson($legacy_lesson)
    {
        // Deduplicate by _legacy_id first (most accurate)
        $existing = new \WP_Query(array(
            'post_type'      => 'slms_lesson',
            'meta_key'       => '_legacy_id',
            'meta_value'     => $legacy_lesson->ID,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        if ($existing->have_posts()) {
            return $existing->posts[0];
        }

        // Fallback: Deduplicate by title/slug
        $existing_title = new \WP_Query(array(
            'post_type'      => 'slms_lesson',
            'title'          => $legacy_lesson->post_title,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post_status'    => 'publish',
        ));

        if ($existing_title->have_posts()) {
            $found_id = $existing_title->posts[0];
            update_post_meta($found_id, '_legacy_id', $legacy_lesson->ID);
            return $found_id;
        }

        $new_lesson_id = wp_insert_post(array(
            'post_title'   => $legacy_lesson->post_title,
            'post_content' => $legacy_lesson->post_content,
            'post_name'    => $legacy_lesson->post_name,
            'post_status'  => 'publish',
            'post_type'    => 'slms_lesson',
        ));

        if (!is_wp_error($new_lesson_id)) {
            update_post_meta($new_lesson_id, '_legacy_id', $legacy_lesson->ID);
            
            // Map Video Meta if exists (Legacy Pods)
            $video = get_post_meta($legacy_lesson->ID, 'lesson_video', true);
            if ($video) {
                update_post_meta($new_lesson_id, '_slms_lesson_type', 'video');
                update_post_meta($new_lesson_id, '_slms_presto_video', $video);
            }
            
            return $new_lesson_id;
        }

        return false;
    }

    /**
     * Helper: Import or deduplicate a course.
     */
    private static function import_course($legacy_course)
    {
        $existing = new \WP_Query(array(
            'post_type'      => 'slms_course',
            'meta_key'       => '_legacy_id',
            'meta_value'     => $legacy_course->ID,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        if ($existing->have_posts()) {
            return $existing->posts[0];
        }

        $new_course_id = wp_insert_post(array(
            'post_title'   => $legacy_course->post_title,
            'post_content' => $legacy_course->post_content,
            'post_name'    => $legacy_course->post_name,
            'post_status'  => 'publish',
            'post_type'    => 'slms_course',
        ));

        if (!is_wp_error($new_course_id)) {
            update_post_meta($new_course_id, '_legacy_id', $legacy_course->ID);
            
            // Map Price
            $price = get_post_meta($legacy_course->ID, 'course_price', true);
            if ($price) {
                update_post_meta($new_course_id, '_slms_course_price', $price);
            }

            return $new_course_id;
        }

        return false;
    }

    /**
     * Helper: Extract Post ID from WP Complete Key.
     */
    private static function extract_post_id($key)
    {
        if (strpos($key, '-') !== false) {
            $parts = explode('-', $key);
            return (int)$parts[0];
        }
        return (int)$key;
    }

    /**
     * Get count of users pending migration.
     */
    public static function get_pending_migration_count()
    {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%'");
        return (int) $count;
    }

    /**
     * Get count of users pending history migration.
     */
    public static function get_pending_history_count()
    {
        return count(get_users(array(
            'meta_key' => '_lms_history_migrated',
            'meta_compare' => 'NOT EXISTS',
            'fields' => 'ID'
        )));
    }

    /**
     * Get count of courses pending migration.
     */
    public static function get_pending_content_count()
    {
        $query = new \WP_Query(array(
            'post_type'   => 'course',
            'post_parent' => 0,
            'meta_query'  => array(
                array(
                    'key'     => '_slms_migrated',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'fields'      => 'ids',
        ));
        return $query->found_posts;
    }
}