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
     * In-memory log buffer for the current migration run.
     *
     * @var array
     */
    private static $log = array();

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
     * Append a message to both the in-memory log and the WP debug log.
     *
     * @param string $message Log message.
     * @param string $level   One of 'info', 'warn', 'error', 'debug'.
     * @return void
     */
    private static function log($message, $level = 'info')
    {
        $entry = array(
            'time'  => current_time('H:i:s'),
            'level' => $level,
            'msg'   => $message,
        );
        self::$log[] = $entry;
        error_log('[SimpleLMS][' . strtoupper($level) . '] ' . $message);
    }

    /**
     * Return and reset the in-memory log buffer.
     *
     * @return array
     */
    public static function flush_log()
    {
        $entries = self::$log;
        self::$log = array();
        return $entries;
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
        self::log('Phase 1: Starting content migration (limit=' . $limit . ').');
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

        self::log('Found ' . count($legacy_courses) . ' unmigrated legacy courses.');
        $count = 0;

        foreach ($legacy_courses as $legacy_course) {
            self::log('Processing legacy course ID ' . $legacy_course->ID . ' "' . $legacy_course->post_title . '".');

            // 1. Import or find current slms_course
            $new_course_id = self::import_course($legacy_course);

            if (!$new_course_id) {
                self::log('SKIP: Could not import/find course for legacy ID ' . $legacy_course->ID . '.', 'warn');
                continue;
            }

            self::log('Mapped legacy course ' . $legacy_course->ID . ' -> new course ' . $new_course_id . '.');

            // Retrieve the course group taxonomy from the legacy course
            $terms = wp_get_post_terms($legacy_course->ID, 'slms_course_cat', array('fields' => 'ids'));
            if (!empty($terms) && !is_wp_error($terms)) {
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
                self::log('No child lessons found for legacy course ' . $legacy_course->ID . '; importing course content as lesson.', 'debug');
                $new_lesson_id = self::import_lesson($legacy_course);
                if ($new_lesson_id) {
                    $new_lesson_ids[] = (int)$new_lesson_id;
                    if (!empty($terms) && !is_wp_error($terms)) {
                        wp_set_post_terms($new_lesson_id, $terms, 'slms_course_cat', true);
                    }
                }
            } else {
                self::log('Found ' . count($legacy_lessons) . ' child lessons for legacy course ' . $legacy_course->ID . '.');
                foreach ($legacy_lessons as $legacy_lesson) {
                    $new_lesson_id = self::import_lesson($legacy_lesson);
                    if ($new_lesson_id) {
                        $new_lesson_ids[] = (int)$new_lesson_id;
                        if (!empty($terms) && !is_wp_error($terms)) {
                            wp_set_post_terms($new_lesson_id, $terms, 'slms_course_cat', true);
                        }
                    } else {
                        self::log('SKIP: Could not import lesson for legacy ID ' . $legacy_lesson->ID . '.', 'warn');
                    }
                }
            }

            // 4. Link via Many-to-Many bridge
            if (!empty($new_lesson_ids)) {
                Relationships::set_lessons_for_course($new_course_id, $new_lesson_ids);
                self::log('Linked ' . count($new_lesson_ids) . ' lessons to course ' . $new_course_id . '.');
            }

            // Mark legacy course as migrated
            update_post_meta($legacy_course->ID, '_slms_migrated', time());
            $count++;
        }

        $duration = round(microtime(true) - $start_time, 2);
        self::log('Phase 1 complete: processed=' . $count . ', duration=' . $duration . 's.');

        $pending = self::get_pending_content_count();

        return array(
            'processed' => $count,
            'pending'   => $pending,
            'total'     => $count + $pending,
            'duration'  => $duration,
            'success'   => true,
            'log'       => self::flush_log(),
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
        self::log('Phase 2: Starting student progress migration (limit=' . $limit . ').');
        $start_time = microtime(true);

        global $wpdb;

        $sql = "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%'";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        $user_ids = $wpdb->get_col($sql);
        self::log('Found ' . count($user_ids) . ' users with WPComplete meta.');

        $count = 0;
        $stats = array('lessons_mapped' => 0, 'lessons_skipped_no_match' => 0, 'lessons_skipped_no_course' => 0, 'lessons_skipped_not_enrolled' => 0);

        foreach ($user_ids as $user_id) {
            $user_id = (int)$user_id;
            $user = get_userdata($user_id);
            $user_label = $user ? $user->user_email : 'UID:' . $user_id;

            $wpc_metas = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND (meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%%')",
                $user_id
            ));

            if (empty($wpc_metas)) {
                self::log('User ' . $user_label . ': no WPComplete meta rows found, skipping.', 'debug');
                continue;
            }

            self::log('User ' . $user_label . ': processing ' . count($wpc_metas) . ' meta row(s).');

            $current_progress = get_user_meta($user_id, '_lms_progress', true);
            if (!is_array($current_progress)) {
                $current_progress = array();
            }

            foreach ($wpc_metas as $meta) {
                $key = $meta->meta_key;
                $value = $meta->meta_value;

                $data = maybe_unserialize($value);
                $format_used = 'serialized';

                if (is_string($data) && strpos(trim($data), '{') === 0) {
                    $data = json_decode($data, true);
                    $format_used = 'json';
                    if ($data === null) {
                        self::log('User ' . $user_label . ': JSON decode failed for key "' . $key . '".', 'error');
                        continue;
                    }
                }

                if ($key === 'wpcomplete' && is_array($data)) {
                    $entry_count = count($data);
                    self::log('User ' . $user_label . ': bulk key "wpcomplete" has ' . $entry_count . ' entries (' . $format_used . ').', 'debug');

                    foreach ($data as $post_key => $post_data) {
                        if ($post_key === '0-site' || strpos($post_key, '0-site') !== false) {
                            continue;
                        }

                        $legacy_lesson_id = self::extract_post_id($post_key);
                        if (!$legacy_lesson_id) {
                            self::log('User ' . $user_label . ': could not extract post ID from key "' . $post_key . '".', 'warn');
                            continue;
                        }

                        self::process_legacy_lesson_progress($user_id, $legacy_lesson_id, $post_data, $current_progress, $stats, $user_label);
                    }
                } else {
                    if (strpos($key, 'wpcomplete_0-site') !== false) {
                        delete_user_meta($user_id, $key);
                        continue;
                    }

                    $legacy_lesson_id = (int) preg_replace('/[^0-9]/', '', str_replace('wpcomplete_', '', $key));
                    if (!$legacy_lesson_id) {
                        self::log('User ' . $user_label . ': could not parse lesson ID from meta key "' . $key . '".', 'warn');
                        delete_user_meta($user_id, $key);
                        continue;
                    }

                    self::process_legacy_lesson_progress($user_id, $legacy_lesson_id, $data, $current_progress, $stats, $user_label);
                }

                delete_user_meta($user_id, $key);
            }

            update_user_meta($user_id, '_lms_progress', $current_progress);

            $course_count = count($current_progress);
            $lesson_count = 0;
            foreach ($current_progress as $lessons) {
                $lesson_count += is_array($lessons) ? count($lessons) : 0;
            }
            self::log('User ' . $user_label . ': saved progress — ' . $course_count . ' course(s), ' . $lesson_count . ' lesson(s) total.');
            $count++;
        }

        $duration = round(microtime(true) - $start_time, 2);
        self::log(sprintf(
            'Phase 2 complete: users=%d, mapped=%d, skipped_no_match=%d, skipped_no_course=%d, skipped_not_enrolled=%d, duration=%ss.',
            $count, $stats['lessons_mapped'], $stats['lessons_skipped_no_match'],
            $stats['lessons_skipped_no_course'], $stats['lessons_skipped_not_enrolled'], $duration
        ));

        $pending = self::get_pending_migration_count();

        return array(
            'processed' => $count,
            'pending'   => $pending,
            'total'     => $count + $pending,
            'duration'  => $duration,
            'success'   => true,
            'stats'     => $stats,
            'log'       => self::flush_log(),
        );
    }

    /**
     * Helper to process legacy lesson completions.
     */
    private static function process_legacy_lesson_progress($user_id, $legacy_lesson_id, $data, &$current_progress, &$stats = null, $user_label = '') {
        $new_lesson_query = new \WP_Query(array(
            'post_type'      => 'slms_lesson',
            'meta_key'       => '_legacy_id',
            'meta_value'     => $legacy_lesson_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        if (!$new_lesson_query->have_posts()) {
            self::log($user_label . ': legacy lesson ' . $legacy_lesson_id . ' has no matching slms_lesson (_legacy_id lookup failed).', 'warn');
            if ($stats !== null) {
                $stats['lessons_skipped_no_match']++;
            }
            return;
        }

        $new_lesson_id = $new_lesson_query->posts[0];
        $timestamp = time();
        $ts_source = 'fallback(now)';

        if (is_array($data) && !empty($data['completed'])) {
            $parsed = strtotime($data['completed']);
            if ($parsed) {
                $timestamp = $parsed;
                $ts_source = 'array[completed]=' . $data['completed'];
            }
        } elseif (is_string($data) && strtotime($data)) {
            $timestamp = strtotime($data);
            $ts_source = 'string=' . $data;
        } elseif (is_numeric($data)) {
            $timestamp = (int)$data;
            $ts_source = 'numeric=' . $data;
        }

        $linked_courses = Relationships::get_courses_for_lesson($new_lesson_id);
        if (empty($linked_courses)) {
            self::log($user_label . ': new lesson ' . $new_lesson_id . ' (legacy ' . $legacy_lesson_id . ') is not linked to any course.', 'warn');
            if ($stats !== null) {
                $stats['lessons_skipped_no_course']++;
            }
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
                self::log($user_label . ': mapped legacy ' . $legacy_lesson_id . ' -> lesson ' . $new_lesson_id . ' in course ' . $course_id . ' (ts: ' . $ts_source . ').', 'debug');
                if ($stats !== null) {
                    $stats['lessons_mapped']++;
                }
            } else {
                self::log($user_label . ': not enrolled in course ' . $course_id . ' — skipping lesson ' . $new_lesson_id . ' (legacy ' . $legacy_lesson_id . ').', 'warn');
                if ($stats !== null) {
                    $stats['lessons_skipped_not_enrolled']++;
                }
            }
        }
    }

    /**
     * Phase 3: Historical Certificate Migration (GF → wp_slms_course_history).
     *
     * Queries Gravity Forms certificate entries and inserts permanent compliance
     * records into the custom history table for 9-year retention.
     *
     * @param int $limit Max users to migrate in this batch.
     * @return array Result summary.
     */
    public static function migrate_history_batch($limit = 10)
    {
        $limit = absint($limit);
        self::log('Phase 3: Starting historical certificate migration (limit=' . $limit . ').');
        $start_time = microtime(true);
        global $wpdb;

        $history_table = $wpdb->prefix . 'slms_course_history';

        $users = get_users(array(
            'meta_key'     => '_lms_history_migrated',
            'meta_compare' => 'NOT EXISTS',
            'number'       => $limit,
            'fields'       => 'ID',
        ));

        self::log('Found ' . count($users) . ' users pending history migration.');

        $count = 0;
        $inserted = 0;
        $skipped_dup = 0;

        foreach ($users as $user_id) {
            $user_id    = (int) $user_id;
            $user       = get_userdata($user_id);
            $user_label = $user ? $user->user_email : 'UID:' . $user_id;

            if (!class_exists('GFAPI')) {
                self::log('GFAPI class not available — cannot migrate history for ' . $user_label . '.', 'error');
                update_user_meta($user_id, '_lms_history_migrated', time());
                $count++;
                continue;
            }

            if (!$user) {
                self::log('User ' . $user_id . ' not found, marking as migrated.', 'warn');
                update_user_meta($user_id, '_lms_history_migrated', time());
                $count++;
                continue;
            }

            // Discover certificate forms.
            $forms = \GFAPI::get_forms();
            $cert_form_ids = array();
            foreach ($forms as $form) {
                $form_title = $form['title'] ?? '';
                if (stripos($form_title, 'Certificate') !== false) {
                    $cert_form_ids[] = $form['id'];
                }
            }

            $form_ids = !empty($cert_form_ids) ? $cert_form_ids : 0;
            self::log($user_label . ': searching ' . (is_array($form_ids) ? count($form_ids) : 'all') . ' certificate form(s).', 'debug');

            // Search by user ID.
            $search_criteria = array(
                'status'        => 'active',
                'field_filters' => array(
                    'mode' => 'any',
                    array('key' => 'created_by', 'value' => $user_id),
                ),
            );
            $entries = \GFAPI::get_entries($form_ids, $search_criteria);

            // Search by email (catches entries not linked by user ID).
            $search_criteria_email = array(
                'status'        => 'active',
                'field_filters' => array(
                    'mode' => 'any',
                    array('value' => $user->user_email),
                ),
            );
            $entries_by_email = \GFAPI::get_entries($form_ids, $search_criteria_email);

            // Merge and deduplicate by entry ID.
            $all_entries    = array_merge((array) $entries, (array) $entries_by_email);
            $unique_entries = array();
            foreach ($all_entries as $entry) {
                if (isset($entry['id']) && !isset($unique_entries[$entry['id']])) {
                    $unique_entries[$entry['id']] = $entry;
                }
            }

            self::log($user_label . ': found ' . count($unique_entries) . ' unique GF entries (by_id=' . count((array) $entries) . ', by_email=' . count((array) $entries_by_email) . ').');

            // Insert each entry into the compliance history table.
            foreach ($unique_entries as $entry) {
                $gf_entry_id = absint($entry['id']);

                // Skip if this GF entry is already in the table (dedup).
                $exists = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$history_table} WHERE gf_entry_id = %d",
                    $gf_entry_id
                ));

                if ($exists) {
                    $skipped_dup++;
                    continue;
                }

                // Resolve course name from GF entry fields.
                $course_name = __('Unknown Course', 'simple-lms-bridge');
                $form        = \GFAPI::get_form($entry['form_id']);

                if ($form && isset($form['fields'])) {
                    foreach ($form['fields'] as $field) {
                        $label = $field->label ?? '';
                        if (stripos($label, 'Course') !== false) {
                            $value = rgar($entry, (string) $field->id);
                            if (!empty($value)) {
                                $course_name = $value;
                                break;
                            }
                        }
                    }
                }

                // Fallback: derive course name from form title.
                if ($course_name === __('Unknown Course', 'simple-lms-bridge') && $form) {
                    $course_name = str_ireplace('Certificate', '', $form['title'] ?? '');
                    $course_name = trim($course_name, ' -');
                }

                $wpdb->insert(
                    $history_table,
                    array(
                        'user_id'        => $user_id,
                        'course_name'    => sanitize_text_field($course_name),
                        'completed_date' => sanitize_text_field($entry['date_created'] ?? current_time('mysql')),
                        'gf_entry_id'    => $gf_entry_id,
                    ),
                    array('%d', '%s', '%s', '%d')
                );
                $inserted++;
            }

            if ($inserted > 0) {
                self::log($user_label . ': inserted ' . $inserted . ' compliance record(s), skipped ' . $skipped_dup . ' duplicate(s).');
            } else {
                self::log($user_label . ': no new certificate entries to insert.');
            }

            update_user_meta($user_id, '_lms_history_migrated', time());
            $count++;
        }

        $duration = round(microtime(true) - $start_time, 2);
        self::log(sprintf(
            'Phase 3 complete: users=%d, inserted=%d, duplicates_skipped=%d, duration=%ss.',
            $count, $inserted, $skipped_dup, $duration
        ));

        $pending = self::get_pending_history_count();

        return array(
            'processed' => $count,
            'pending'   => $pending,
            'total'     => $count + $pending,
            'inserted'  => $inserted,
            'duration'  => $duration,
            'success'   => true,
            'log'       => self::flush_log(),
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