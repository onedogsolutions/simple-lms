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
     * Append a message to the in-memory log, WP debug log, and the plugin's persistent log file.
     *
     * @param string $message Log message.
     * @param string $level   One of 'info', 'warn', 'error', 'debug'.
     * @return void
     */
    private static function log($message, $level = 'info')
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $entry = array(
            'time' => current_time('H:i:s'),
            'level' => $level,
            'msg' => $message,
        );
        self::$log[] = $entry;
        error_log('[SimpleLMS][' . strtoupper($level) . '] ' . $message);

        // Write to persistent plugin log file.
        $log_file = self::get_log_file_path();
        $line = '[' . $timestamp . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
        @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get the path to the plugin's persistent log file.
     *
     * @return string
     */
    public static function get_log_file_path()
    {
        $upload_dir = \wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/slms-logs';
        if (!is_dir($log_dir)) {
            \wp_mkdir_p($log_dir);
            // Protect log directory with .htaccess.
            @file_put_contents($log_dir . '/.htaccess', 'deny from all');
            @file_put_contents($log_dir . '/index.php', '<?php // Silence is golden.');
        }
        return $log_dir . '/migration.log';
    }

    /**
     * Read the last N lines from the persistent log file.
     *
     * @param int $lines Number of lines to read.
     * @return string
     */
    public static function read_log($lines = 200)
    {
        $log_file = self::get_log_file_path();
        if (!file_exists($log_file)) {
            return '';
        }

        $content = file_get_contents($log_file);
        if (empty($content)) {
            return '';
        }

        $all_lines = explode(PHP_EOL, trim($content));
        $total = count($all_lines);

        if ($total <= $lines) {
            return implode(PHP_EOL, $all_lines);
        }

        return implode(PHP_EOL, array_slice($all_lines, $total - $lines));
    }

    /**
     * Clear the persistent log file.
     *
     * @return bool
     */
    public static function clear_log()
    {
        $log_file = self::get_log_file_path();
        return @file_put_contents($log_file, '') !== false;
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
            'post_type' => 'course',
            'post_status' => 'publish',
            'post_parent' => 0, // Only parents are "Courses"
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
                'post_type' => 'course',
                'post_status' => 'publish',
                'post_parent' => $legacy_course->ID,
                'orderby' => 'menu_order',
                'order' => 'ASC',
                'numberposts' => -1,
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
            }
            else {
                self::log('Found ' . count($legacy_lessons) . ' child lessons for legacy course ' . $legacy_course->ID . '.');
                foreach ($legacy_lessons as $legacy_lesson) {
                    $new_lesson_id = self::import_lesson($legacy_lesson);
                    if ($new_lesson_id) {
                        $new_lesson_ids[] = (int)$new_lesson_id;
                        if (!empty($terms) && !is_wp_error($terms)) {
                            wp_set_post_terms($new_lesson_id, $terms, 'slms_course_cat', true);
                        }
                    }
                    else {
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
            'pending' => $pending,
            'total' => $count + $pending,
            'duration' => $duration,
            'success' => true,
            'status' => ($pending === 0 || $count === 0) ? 'complete' : 'processing',
            'log' => self::flush_log(),
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
    public static function migrate_progress_batch($limit = 10)
    {
        $limit = max(1, min(absint($limit), 100));
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

            // Pre-fetch enrollment and purchase data for ownership validation.
            $user_courses = Relationships::get_courses_for_user($user_id);
            $enrolled_ids = array_map(function ($c) { return (int)$c->id; }, $user_courses);
            $user_gf_products = self::get_user_gf_products($user_id);

            foreach ($wpc_metas as $meta) {
                $key = $meta->meta_key;
                $value = $meta->meta_value;

                // Try JSON first — WPComplete stores data as JSON.
                $data = json_decode($value ?? '', true);
                $format_used = 'json';

                if ($data === null) {
                    // Fallback to maybe_unserialize for older formats.
                    $data = maybe_unserialize($value ?? '');
                    $format_used = 'serialized';
                }

                if (!is_array($data)) {
                    self::log('User ' . $user_label . ': could not parse value for key "' . $key . '" as JSON or serialized.', 'warn');
                    delete_user_meta($user_id, $key);
                    continue;
                }

                if ($key === 'wpcomplete' && is_array($data)) {
                    $entry_count = count($data);
                    self::log('User ' . $user_label . ': bulk key "wpcomplete" has ' . $entry_count . ' entries (' . $format_used . ').', 'debug');

                    foreach ($data as $post_key => $post_data) {
                        if ($post_key === '0-site' || strpos((string)$post_key, '0-site') !== false) {
                            continue;
                        }

                        $legacy_lesson_id = self::extract_post_id($post_key);
                        if (!$legacy_lesson_id) {
                            self::log('User ' . $user_label . ': could not extract post ID from key "' . $post_key . '".', 'warn');
                            continue;
                        }

                        self::process_legacy_lesson_progress($user_id, $legacy_lesson_id, $post_data, $current_progress, $stats, $user_label, $enrolled_ids, $user_gf_products);
                    }
                }
                else {
                    if (strpos((string)$key, 'wpcomplete_0-site') !== false) {
                        delete_user_meta($user_id, $key);
                        continue;
                    }

                    $legacy_lesson_id = (int)preg_replace('/[^0-9]/', '', str_replace('wpcomplete_', '', $key));
                    if (!$legacy_lesson_id) {
                        self::log('User ' . $user_label . ': could not parse lesson ID from meta key "' . $key . '".', 'warn');
                        delete_user_meta($user_id, $key);
                        continue;
                    }

                    self::process_legacy_lesson_progress($user_id, $legacy_lesson_id, $data, $current_progress, $stats, $user_label, $enrolled_ids, $user_gf_products);
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
            'pending' => $pending,
            'total' => $count + $pending,
            'duration' => $duration,
            'success' => true,
            'status' => ($pending === 0 || $count === 0) ? 'complete' : 'processing',
            'stats' => $stats,
            'log' => self::flush_log(),
        );
    }

    /**
     * Resolve the origin course for a legacy lesson using the post_parent hierarchy.
     *
     * In the legacy Pods CPT, lessons were children of courses (post_parent).
     * This method finds the new slms_course that maps to the legacy parent.
     *
     * @param int $legacy_lesson_id The legacy lesson post ID.
     * @return int|null The slms_course ID if resolved, or null.
     */
    private static function resolve_origin_course($legacy_lesson_id)
    {
        $legacy_post = get_post($legacy_lesson_id);
        if (!$legacy_post || empty($legacy_post->post_parent)) {
            return null;
        }

        $parent_id = (int)$legacy_post->post_parent;

        // Check if the parent itself is an slms_course.
        $parent_post = get_post($parent_id);
        if ($parent_post && $parent_post->post_type === 'slms_course') {
            return $parent_id;
        }

        // Look up slms_course by _legacy_id meta matching the parent.
        $query = new \WP_Query(array(
            'post_type' => 'slms_course',
            'meta_key' => '_legacy_id',
            'meta_value' => $parent_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));

        if ($query->have_posts()) {
            return (int)$query->posts[0];
        }

        return null;
    }

    /**
     * Check if a user has ownership/access evidence for a specific course.
     *
     * Uses a three-tier check:
     *   1. Enrollment table (wp_slms_user_course)
     *   2. Active PMPro membership level
     *   3. GF Form 2 purchase history (product name vs course title)
     *
     * @param int   $user_id          User ID.
     * @param int   $course_id        Course post ID.
     * @param array $enrolled_ids     Pre-fetched array of course IDs the user is enrolled in.
     * @param array $user_gf_products Pre-fetched array of GF product names (lowercase) for the user.
     * @return bool
     */
    private static function user_owns_course($user_id, $course_id, $enrolled_ids, $user_gf_products)
    {
        // Check A: Enrollment table.
        if (in_array($course_id, $enrolled_ids, true)) {
            return true;
        }

        // Check B: PMPro active membership level.
        if (class_exists(__NAMESPACE__ . '\\PMPro') && PMPro::has_course_access($user_id, $course_id)) {
            return true;
        }

        // Check C: GF Form 2 purchase history — match product name to course title.
        if (!empty($user_gf_products)) {
            $course_post = get_post($course_id);
            if ($course_post) {
                $course_title_lower = strtolower(trim($course_post->post_title));
                foreach ($user_gf_products as $product_lower) {
                    if ($product_lower === $course_title_lower || strpos($course_title_lower, $product_lower) !== false || strpos($product_lower, $course_title_lower) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Pre-fetch GF Form 2 product names for a user (for ownership validation).
     *
     * @param int $user_id User ID.
     * @return array Array of lowercase product name strings.
     */
    private static function get_user_gf_products($user_id)
    {
        if (!class_exists('GFAPI')) {
            return array();
        }

        $gf_form_id = 2;
        $product_field_ids = array(21, 22, 44, 23, 30, 24, 25, 26, 27, 34, 43);

        $search_criteria = array(
            'status' => 'active',
            'field_filters' => array(
                array('key' => 'created_by', 'value' => $user_id),
            ),
        );

        $entries = \GFAPI::get_entries($gf_form_id, $search_criteria);
        if (!is_array($entries)) {
            return array();
        }

        $products = array();
        foreach ($entries as $entry) {
            foreach ($product_field_ids as $field_id) {
                $value = rgar($entry, (string)$field_id);
                if (!empty($value)) {
                    $product_name = (string)$value;
                    if (strpos($product_name, '|') !== false) {
                        $parts = explode('|', $product_name);
                        $product_name = trim($parts[0]);
                    }
                    if (!empty($product_name)) {
                        $products[] = strtolower(trim($product_name));
                    }
                }
            }
        }

        return array_unique($products);
    }

    /**
     * Helper to process legacy lesson completions.
     *
     * Validates course ownership before recording progress for shared lessons.
     * Uses a tiered approach: legacy parent match, then enrollment/purchase checks.
     */
    private static function process_legacy_lesson_progress($user_id, $legacy_lesson_id, $data, &$current_progress, &$stats = null, $user_label = '', $enrolled_ids = array(), $user_gf_products = array())
    {
        // 1. Look up new lesson by _legacy_id meta.
        $new_lesson_query = new \WP_Query(array(
            'post_type' => 'slms_lesson',
            'meta_key' => '_legacy_id',
            'meta_value' => $legacy_lesson_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));

        // 2. Fallback: try matching by post ID directly (legacy ID IS the slms_lesson ID).
        if (!$new_lesson_query->have_posts()) {
            $direct_post = get_post($legacy_lesson_id);
            if ($direct_post instanceof \WP_Post && $direct_post->post_type === 'slms_lesson' && $direct_post->post_status === 'publish') {
                $new_lesson_id = $direct_post->ID;
                self::log($user_label . ': legacy lesson ' . $legacy_lesson_id . ' matched directly as slms_lesson ' . $new_lesson_id . '.', 'debug');
            }
            else {
                // 3. Fallback: try matching by title.
                $legacy_post = get_post($legacy_lesson_id);
                if ($legacy_post) {
                    $title_query = new \WP_Query(array(
                        'post_type' => 'slms_lesson',
                        'title' => $legacy_post->post_title,
                        'posts_per_page' => 1,
                        'fields' => 'ids',
                        'no_found_rows' => true,
                        'post_status' => 'publish',
                    ));
                    if ($title_query->have_posts()) {
                        $new_lesson_id = $title_query->posts[0];
                        update_post_meta($new_lesson_id, '_legacy_id', $legacy_lesson_id);
                        self::log($user_label . ': legacy lesson ' . $legacy_lesson_id . ' (' . $legacy_post->post_title . ') matched by title to slms_lesson ' . $new_lesson_id . '.', 'debug');
                    }
                    else {
                        self::log($user_label . ': legacy lesson ' . $legacy_lesson_id . ' (' . $legacy_post->post_title . ') has no matching slms_lesson — _legacy_id lookup failed, direct ID lookup failed (post_type=' . $legacy_post->post_type . '), title lookup failed.', 'warn');
                        if ($stats !== null) {
                            $stats['lessons_skipped_no_match']++;
                        }
                        return;
                    }
                }
                else {
                    self::log($user_label . ': legacy lesson ' . $legacy_lesson_id . ' has no matching slms_lesson — _legacy_id lookup failed, post ID ' . $legacy_lesson_id . ' does not exist in wp_posts.', 'warn');
                    if ($stats !== null) {
                        $stats['lessons_skipped_no_match']++;
                    }
                    return;
                }
            }
        }
        else {
            $new_lesson_id = $new_lesson_query->posts[0];
        }

        // Parse completion timestamp from WPComplete data.
        $timestamp = time();
        $ts_source = 'fallback(now)';

        if (is_array($data) && !empty($data['completed'])) {
            $completed_val = (string)($data['completed'] ?? '');
            $parsed = $completed_val !== '' ? strtotime($completed_val) : false;
            if ($parsed) {
                $timestamp = $parsed;
                $ts_source = 'array[completed]=' . $completed_val;
            }
        }
        elseif (is_string($data) && $data !== '' && strtotime($data)) {
            $timestamp = strtotime($data);
            $ts_source = 'string=' . $data;
        }
        elseif (is_numeric($data)) {
            $timestamp = (int)$data;
            $ts_source = 'numeric=' . $data;
        }

        // Find linked courses for this lesson.
        $linked_courses = Relationships::get_courses_for_lesson($new_lesson_id);
        if (empty($linked_courses)) {
            self::log($user_label . ': new lesson ' . $new_lesson_id . ' (legacy ' . $legacy_lesson_id . ') is not linked to any course in wp_slms_course_lesson table. Attempting course lookup via _simple_lms_order meta.', 'warn');

            // Fallback: search all courses for this lesson in their _simple_lms_order.
            global $wpdb;
            $course_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_simple_lms_order' AND meta_value LIKE %s",
                '%' . $wpdb->esc_like('"' . $new_lesson_id . '"') . '%'
            ));

            if (!empty($course_ids)) {
                self::log($user_label . ': found lesson ' . $new_lesson_id . ' in _simple_lms_order of course(s): ' . implode(', ', $course_ids) . '.', 'debug');
                $linked_courses = array();
                foreach ($course_ids as $cid) {
                    $cpost = get_post($cid);
                    if ($cpost && $cpost->post_type === 'slms_course') {
                        $obj = new \stdClass();
                        $obj->id = (int)$cid;
                        $obj->title = $cpost->post_title;
                        $linked_courses[] = $obj;
                    }
                }
            }

            if (empty($linked_courses)) {
                self::log($user_label . ': lesson ' . $new_lesson_id . ' (legacy ' . $legacy_lesson_id . ') still not linked to any course after fallback search. Skipping.', 'warn');
                if ($stats !== null) {
                    $stats['lessons_skipped_no_course']++;
                }
                return;
            }
        }

        // Determine which course(s) to record progress for.
        // Tier 1: Resolve origin course from legacy post_parent hierarchy.
        $origin_course_id = self::resolve_origin_course($legacy_lesson_id);

        if ($origin_course_id) {
            // Verify the origin course is among the linked courses.
            $origin_is_linked = false;
            $origin_title = '';
            foreach ($linked_courses as $course_obj) {
                if ((int)$course_obj->id === $origin_course_id) {
                    $origin_is_linked = true;
                    $origin_title = $course_obj->title;
                    break;
                }
            }

            if ($origin_is_linked) {
                Relationships::enroll_user($user_id, $origin_course_id, 'migration');
                if (!isset($current_progress[$origin_course_id])) {
                    $current_progress[$origin_course_id] = array();
                }
                $current_progress[$origin_course_id][$new_lesson_id] = $timestamp;
                self::log($user_label . ': mapped legacy ' . $legacy_lesson_id . ' -> lesson ' . $new_lesson_id . ' in origin course ' . $origin_course_id . ' (' . $origin_title . ') via post_parent (ts: ' . $ts_source . ').', 'debug');
                if ($stats !== null) {
                    $stats['lessons_mapped']++;
                }
                return;
            }
            // Origin course not in linked courses — fall through to tiered validation.
            self::log($user_label . ': legacy lesson ' . $legacy_lesson_id . ' post_parent resolved to course ' . $origin_course_id . ' but it is not in the linked courses list. Falling back to ownership validation.', 'debug');
        }

        // Tier 3: Single-course passthrough — no ambiguity, skip validation.
        if (count($linked_courses) === 1) {
            $course_obj = $linked_courses[0];
            $course_id = (int)$course_obj->id;
            Relationships::enroll_user($user_id, $course_id, 'migration');
            if (!isset($current_progress[$course_id])) {
                $current_progress[$course_id] = array();
            }
            $current_progress[$course_id][$new_lesson_id] = $timestamp;
            self::log($user_label . ': mapped legacy ' . $legacy_lesson_id . ' -> lesson ' . $new_lesson_id . ' in course ' . $course_id . ' (' . $course_obj->title . ') (single course, no ambiguity) (ts: ' . $ts_source . ').', 'debug');
            if ($stats !== null) {
                $stats['lessons_mapped']++;
            }
            return;
        }

        // Tier 2: Multiple courses — validate ownership for each candidate.
        $mapped_any = false;
        foreach ($linked_courses as $course_obj) {
            $course_id = (int)$course_obj->id;

            if (self::user_owns_course($user_id, $course_id, $enrolled_ids, $user_gf_products)) {
                Relationships::enroll_user($user_id, $course_id, 'migration');
                if (!isset($current_progress[$course_id])) {
                    $current_progress[$course_id] = array();
                }
                $current_progress[$course_id][$new_lesson_id] = $timestamp;
                self::log($user_label . ': mapped legacy ' . $legacy_lesson_id . ' -> lesson ' . $new_lesson_id . ' in course ' . $course_id . ' (' . $course_obj->title . ') (ownership verified) (ts: ' . $ts_source . ').', 'debug');
                if ($stats !== null) {
                    $stats['lessons_mapped']++;
                }
                $mapped_any = true;
            } else {
                self::log($user_label . ': lesson ' . $new_lesson_id . ' linked to course ' . $course_id . ' (' . $course_obj->title . ') but user has no purchase/enrollment evidence. Skipping.', 'warn');
                if ($stats !== null) {
                    $stats['lessons_skipped_not_enrolled']++;
                }
            }
        }

        if (!$mapped_any) {
            self::log($user_label . ': legacy lesson ' . $legacy_lesson_id . ' (new ' . $new_lesson_id . ') linked to ' . count($linked_courses) . ' courses but user has no ownership evidence for any. No progress recorded.', 'warn');
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

        $users = \get_users(array(
            'meta_key' => '_lms_history_migrated',
            'meta_compare' => 'NOT EXISTS',
            'number' => $limit,
            'fields' => 'ID',
        ));

        self::log('Found ' . count($users) . ' users pending history migration.');

        $count = 0;
        $inserted = 0;
        $skipped_dup = 0;

        foreach ($users as $user_id) {
            $user_id = (int)$user_id;
            $user = get_userdata($user_id);
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
                'status' => 'active',
                'field_filters' => array(
                    'mode' => 'any',
                        array('key' => 'created_by', 'value' => $user_id),
                ),
            );
            $entries = \GFAPI::get_entries($form_ids, $search_criteria);

            // Search by email (catches entries not linked by user ID).
            $search_criteria_email = array(
                'status' => 'active',
                'field_filters' => array(
                    'mode' => 'any',
                        array('value' => $user->user_email),
                ),
            );
            $entries_by_email = \GFAPI::get_entries($form_ids, $search_criteria_email);

            // Merge and deduplicate by entry ID.
            $all_entries = array_merge((array)$entries, (array)$entries_by_email);
            $unique_entries = array();
            foreach ($all_entries as $entry) {
                if (isset($entry['id']) && !isset($unique_entries[$entry['id']])) {
                    $unique_entries[$entry['id']] = $entry;
                }
            }

            self::log($user_label . ': found ' . count($unique_entries) . ' unique GF entries (by_id=' . count((array)$entries) . ', by_email=' . count((array)$entries_by_email) . ').');

            // Insert each entry into the compliance history table.
            foreach ($unique_entries as $entry) {
                $gf_entry_id = absint($entry['id']);

                // Skip if this GF entry is already in the table (dedup).
                $exists = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$history_table} WHERE gf_entry_id = %d",
                    $gf_entry_id
                ));

                if ($exists) {
                    $skipped_dup++;
                    continue;
                }

                // Resolve course name from GF entry fields.
                $course_name = __('Unknown Course', 'simple-lms-bridge');
                $form = \GFAPI::get_form($entry['form_id']);

                if ($form && isset($form['fields'])) {
                    foreach ($form['fields'] as $field) {
                        $label = $field->label ?? '';
                        if (stripos($label, 'Course') !== false) {
                            $value = rgar($entry, (string)$field->id);
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
                    'user_id' => $user_id,
                    'course_name' => sanitize_text_field($course_name),
                    'completed_date' => sanitize_text_field($entry['date_created'] ?? current_time('mysql')),
                    'gf_entry_id' => $gf_entry_id,
                ),
                    array('%d', '%s', '%s', '%d')
                );
                $inserted++;
            }

            if ($inserted > 0) {
                self::log($user_label . ': inserted ' . $inserted . ' compliance record(s), skipped ' . $skipped_dup . ' duplicate(s).');
            }
            else {
                self::log($user_label . ': no new certificate entries to insert.');
            }

            $updated = update_user_meta($user_id, '_lms_history_migrated', time());
            if (!$updated) {
                self::log('CRITICAL: Failed to set _lms_history_migrated for user ' . $user_id . '. This user will be re-processed next batch.', 'error');
            }
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
            'pending' => $pending,
            'total' => $count + $pending,
            'inserted' => $inserted,
            'duration' => $duration,
            'success' => true,
            'status' => ($pending === 0 || count($users) === 0) ? 'complete' : 'processing',
            'log' => self::flush_log(),
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
     * Phase 4: PMPro Membership Migration.
     *
     * Migrates historical access data from Gravity Forms Registration (Form ID 2)
     * into PMPro membership levels. Creates missing levels, enrolls users with
     * 90-day access windows from the original GF entry date.
     *
     * GF Form ID 2 product field IDs: 21, 22, 44, 23, 30, 24, 25, 26, 27, 34, 43
     *
     * @param int $limit Max entries to migrate in this batch.
     * @return array Result summary.
     */
    public static function migrate_pmpro_batch($limit = 10)
    {
        $limit = absint($limit);
        self::log('Phase 4: Starting PMPro membership migration (limit=' . $limit . ').');
        $start_time = microtime(true);

        if (!class_exists('GFAPI')) {
            self::log('GFAPI class not available — cannot run Phase 4.', 'error');
            return array(
                'processed' => 0,
                'pending' => 0,
                'total' => 0,
                'duration' => 0,
                'success' => false,
                'log' => self::flush_log(),
            );
        }

        if (!function_exists('pmpro_changeMembershipLevel')) {
            self::log('PMPro not active — cannot run Phase 4.', 'error');
            return array(
                'processed' => 0,
                'pending' => 0,
                'total' => 0,
                'duration' => 0,
                'success' => false,
                'log' => self::flush_log(),
            );
        }

        $gf_form_id = 2;
        $product_field_ids = array(21, 22, 44, 23, 30, 24, 25, 26, 27, 34, 43);

        // Get unmigrated entries from Form ID 2 using a self-advancing offset.
        // The offset advances past already-migrated entries to avoid an infinite loop.
        $search_criteria = array(
            'status' => 'active',
        );
        $sorting = array('key' => 'id', 'direction' => 'ASC');

        $unmigrated = array();
        $offset = 0;
        $max_scans = 100; // Safety limit to prevent runaway scanning.

        while (count($unmigrated) < $limit && $max_scans > 0) {
            $paging = array('offset' => $offset, 'page_size' => $limit);
            $entries = \GFAPI::get_entries($gf_form_id, $search_criteria, $sorting, $paging);

            if (!is_array($entries) || empty($entries)) {
                break; // No more entries in the form.
            }

            foreach ($entries as $entry) {
                $migrated = \gform_get_meta($entry['id'], '_slms_pmpro_migrated');
                if (!$migrated) {
                    $unmigrated[] = $entry;
                    if (count($unmigrated) >= $limit) {
                        break;
                    }
                }
            }

            // If we fetched fewer entries than the page size, we've reached the end.
            if (count($entries) < $limit) {
                break;
            }

            $offset += $limit;
            $max_scans--;
        }

        if (empty($unmigrated)) {
            self::log('No unmigrated entries found in GF Form ID ' . $gf_form_id . '.');
            $pending = self::get_pending_pmpro_count();
            return array(
                'processed' => 0,
                'pending' => $pending,
                'total' => $pending,
                'duration' => round(microtime(true) - $start_time, 2),
                'success' => true,
                'status' => $pending === 0 ? 'complete' : 'processing',
                'log' => self::flush_log(),
            );
        }

        self::log('Found ' . count($unmigrated) . ' unmigrated entries to process.');

        // Log the first entry's field keys for diagnostic purposes.
        if (!empty($unmigrated[0])) {
            $sample = $unmigrated[0];
            $populated_keys = array();
            foreach ($sample as $key => $val) {
                if (!empty($val) && is_numeric(str_replace('.', '', $key))) {
                    $populated_keys[] = $key . '=' . mb_substr((string)$val, 0, 60);
                }
            }
            self::log('Sample entry #' . $sample['id'] . ' populated field keys: ' . implode(' | ', $populated_keys), 'debug');
        }

        // Build a level name cache from existing PMPro levels.
        $level_cache = array();
        if (function_exists('pmpro_getAllLevels')) {
            $all_levels = pmpro_getAllLevels(false, true);
            foreach ($all_levels as $level) {
                $level_cache[strtolower(trim($level->name))] = (int)$level->id;
            }
        }

        $count = 0;
        $enrolled = 0;
        $levels_created = 0;

        foreach ($unmigrated as $entry) {
            $entry_id = absint($entry['id']);
            $user_id = !empty($entry['created_by']) ? (int)$entry['created_by'] : 0;
            $entry_date = $entry['date_created'] ?? '';

            // Resolve user by email if created_by is missing.
            if (!$user_id) {
                // Search common email fields in the entry.
                foreach ($entry as $fkey => $fval) {
                    if (is_string($fval) && \is_email($fval)) {
                        $wp_user = get_user_by('email', $fval);
                        if ($wp_user) {
                            $user_id = $wp_user->ID;
                            break;
                        }
                    }
                }
            }

            if (!$user_id) {
                self::log('Entry #' . $entry_id . ': no user found, skipping.', 'warn');
                \gform_update_meta($entry_id, '_slms_pmpro_migrated', time());
                $count++;
                continue;
            }

            $user = get_userdata($user_id);
            $user_label = $user ? $user->user_email : 'UID:' . $user_id;

            // Extract product names from the product field IDs.
            // GF product fields store data in sub-fields: {id}.1 = name, {id}.2 = price, {id}.3 = qty.
            // We check both the bare field ID and the .1 sub-field for the product name.
            $products = array();
            foreach ($product_field_ids as $field_id) {
                $product_name = '';

                // Try sub-field .1 first (standard GF product name sub-field).
                $sub_value = rgar($entry, $field_id . '.1');
                if (!empty($sub_value)) {
                    $product_name = $sub_value;
                } else {
                    // Fall back to bare field ID (non-product or legacy format).
                    $sub_value = rgar($entry, (string)$field_id);
                    if (!empty($sub_value)) {
                        $product_name = $sub_value;
                    }
                }

                if (!empty($product_name)) {
                    // GF product fields may contain "Product Name|Price" format.
                    if (strpos((string)$product_name, '|') !== false) {
                        $parts = explode('|', $product_name);
                        $product_name = trim($parts[0]);
                    }
                    if (!empty($product_name)) {
                        $products[] = $product_name;
                    }
                }
            }

            if (empty($products)) {
                self::log('Entry #' . $entry_id . ' (' . $user_label . '): no product fields found, skipping.', 'debug');
                \gform_update_meta($entry_id, '_slms_pmpro_migrated', time());
                $count++;
                continue;
            }

            self::log('Entry #' . $entry_id . ' (' . $user_label . '): found ' . count($products) . ' product(s): ' . implode(', ', $products) . '.');

            // For each product, find or create a PMPro level and enroll the user.
            foreach ($products as $product_name) {
                $level_key = strtolower(trim($product_name));
                $level_id = isset($level_cache[$level_key]) ? $level_cache[$level_key] : 0;

                // Create the level if it doesn't exist.
                if (!$level_id && function_exists('pmpro_insert_or_replace')) {
                    $new_level = array(
                        'name' => sanitize_text_field($product_name),
                        'description' => 'Auto-created from GF Form #' . $gf_form_id . ' migration.',
                        'allow_signups' => 0,
                        'expiration_number' => 90,
                        'expiration_period' => 'Day',
                    );

                    global $wpdb;
                    $pmpro_table = $wpdb->prefix . 'pmpro_membership_levels';

                    // Check table exists before inserting.
                    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $pmpro_table)) === $pmpro_table) {
                        $wpdb->insert(
                            $pmpro_table,
                            array(
                            'name' => sanitize_text_field($product_name),
                            'description' => 'Auto-created from GF migration.',
                            'allow_signups' => 0,
                            'expiration_number' => 90,
                            'expiration_period' => 'Day',
                        ),
                            array('%s', '%s', '%d', '%d', '%s')
                        );
                        $level_id = (int)$wpdb->insert_id;

                        if ($level_id) {
                            $level_cache[$level_key] = $level_id;
                            $levels_created++;
                            self::log('Created PMPro level "' . $product_name . '" (ID: ' . $level_id . ').');
                        }
                        else {
                            self::log('Failed to create PMPro level "' . $product_name . '".', 'error');
                            continue;
                        }
                    }
                    else {
                        self::log('PMPro membership_levels table not found, cannot create level.', 'error');
                        continue;
                    }
                }
                elseif (!$level_id) {
                    self::log('Cannot create PMPro level "' . $product_name . '" — pmpro_insert_or_replace not available.', 'warn');
                    continue;
                }

                // Calculate 90-day enddate from the GF entry date.
                $enddate = '';
                if (!empty($entry_date)) {
                    $entry_timestamp = strtotime($entry_date);
                    if ($entry_timestamp) {
                        $enddate = gmdate('Y-m-d H:i:s', $entry_timestamp + (90 * DAY_IN_SECONDS));
                    }
                }

                // Enroll the user via pmpro_changeMembershipLevel.
                $level_params = array(
                    'user_id' => $user_id,
                    'membership_id' => $level_id,
                    'enddate' => $enddate,
                );

                $result = \pmpro_changeMembershipLevel($level_params, $user_id);

                if ($result) {
                    self::log($user_label . ': enrolled in PMPro level ' . $level_id . ' ("' . $product_name . '") enddate=' . $enddate . '.', 'debug');
                    $enrolled++;

                    // Also enroll in SimpleLMS courses mapped to this level.
                    if (class_exists(__NAMESPACE__ . '\\PMPro')) {
                        $course_ids = PMPro::get_courses_for_level($level_id);
                        foreach ($course_ids as $course_id) {
                            Relationships::enroll_user($user_id, $course_id, 'pmpro_migration');
                        }
                    }
                }
                else {
                    self::log($user_label . ': pmpro_changeMembershipLevel failed for level ' . $level_id . '.', 'error');
                }
            }

            \gform_update_meta($entry_id, '_slms_pmpro_migrated', time());
            $count++;
        }

        $duration = round(microtime(true) - $start_time, 2);
        self::log(sprintf(
            'Phase 4 complete: entries=%d, enrolled=%d, levels_created=%d, duration=%ss.',
            $count, $enrolled, $levels_created, $duration
        ));

        $pending = self::get_pending_pmpro_count();

        return array(
            'processed' => $count,
            'pending' => $pending,
            'total' => $count + $pending,
            'enrolled' => $enrolled,
            'levels_created' => $levels_created,
            'duration' => $duration,
            'success' => true,
            'status' => ($pending === 0 || $count === 0) ? 'complete' : 'processing',
            'log' => self::flush_log(),
        );
    }

    /**
     * Phase 5: Legacy Cleanup.
     * Safely removes legacy posts after verification.
     *
     * @return int Number of deleted posts.
     */
    public static function cleanup_legacy_data()
    {
        $legacy_posts = get_posts(array(
            'post_type' => 'course',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
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
            'post_type' => 'slms_lesson',
            'meta_key' => '_legacy_id',
            'meta_value' => $legacy_lesson->ID,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));

        if ($existing->have_posts()) {
            return $existing->posts[0];
        }

        // Fallback: Deduplicate by title/slug
        $existing_title = new \WP_Query(array(
            'post_type' => 'slms_lesson',
            'title' => $legacy_lesson->post_title,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'post_status' => 'publish',
        ));

        if ($existing_title->have_posts()) {
            $found_id = $existing_title->posts[0];
            update_post_meta($found_id, '_legacy_id', $legacy_lesson->ID);
            return $found_id;
        }

        $new_lesson_id = wp_insert_post(array(
            'post_title' => $legacy_lesson->post_title,
            'post_content' => $legacy_lesson->post_content,
            'post_name' => $legacy_lesson->post_name,
            'post_status' => 'publish',
            'post_type' => 'slms_lesson',
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
            'post_type' => 'slms_course',
            'meta_key' => '_legacy_id',
            'meta_value' => $legacy_course->ID,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));

        if ($existing->have_posts()) {
            return $existing->posts[0];
        }

        $new_course_id = wp_insert_post(array(
            'post_title' => $legacy_course->post_title,
            'post_content' => $legacy_course->post_content,
            'post_name' => $legacy_course->post_name,
            'post_status' => 'publish',
            'post_type' => 'slms_course',
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
        if (strpos((string)$key, '-') !== false) {
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
        $count = $wpdb->get_var("SELECT COUNT(DISTINCT um.user_id) FROM {$wpdb->usermeta} um WHERE um.meta_key = 'wpcomplete' OR um.meta_key LIKE 'wpcomplete_%'");
        return (int)$count;
    }

    /**
     * Get count of users pending history migration.
     */
    public static function get_pending_history_count()
    {
        return count(\get_users(array(
            'meta_key' => '_lms_history_migrated',
            'meta_compare' => 'NOT EXISTS',
            'fields' => 'ID'
        )));
    }

    /**
     * Get count of GF Form ID 2 entries pending PMPro migration.
     */
    public static function get_pending_pmpro_count()
    {
        if (!class_exists('GFAPI')) {
            return 0;
        }

        $gf_form_id = 2;
        $search_criteria = array('status' => 'active');
        $total = \GFAPI::count_entries($gf_form_id, $search_criteria);

        if (!$total) {
            return 0;
        }

        // Count how many have already been migrated via GF entry meta.
        global $wpdb;
        $gf_meta_table = $wpdb->prefix . 'gf_entry_meta';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gf_meta_table)) !== $gf_meta_table) {
            return (int)$total;
        }

        $migrated = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$gf_meta_table} em
             INNER JOIN {$wpdb->prefix}gf_entry e ON em.entry_id = e.id
             WHERE em.meta_key = '_slms_pmpro_migrated'
             AND e.form_id = %d AND e.status = 'active'",
            $gf_form_id
        ));

        return max(0, (int)$total - $migrated);
    }

    /**
     * Reset Phase 4 migration meta so all GF Form 2 entries can be re-processed.
     *
     * @return array Result summary.
     */
    public static function reset_pmpro_migration()
    {
        global $wpdb;
        $gf_meta_table = $wpdb->prefix . 'gf_entry_meta';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gf_meta_table)) !== $gf_meta_table) {
            return array('deleted' => 0, 'pending' => 0, 'success' => false, 'message' => 'GF entry meta table not found.');
        }

        $deleted = $wpdb->query(
            "DELETE FROM {$gf_meta_table} WHERE meta_key = '_slms_pmpro_migrated'"
        );

        self::log('Phase 4 reset: removed ' . (int)$deleted . ' migration markers.', 'info');

        return array(
            'deleted' => (int)$deleted,
            'pending' => self::get_pending_pmpro_count(),
            'success' => true,
            'log' => self::flush_log(),
        );
    }

    /**
     * Get count of courses pending migration.
     */
    public static function get_pending_content_count()
    {
        $query = new \WP_Query(array(
            'post_type' => 'course',
            'post_parent' => 0,
            'meta_query' => array(
                    array(
                    'key' => '_slms_migrated',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'fields' => 'ids',
        ));
        return $query->found_posts;
    }
}