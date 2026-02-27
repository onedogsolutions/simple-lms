<?php
/**
 * Paid Memberships Pro integration for SimpleLMS.
 *
 * Handles automatic course enrollment and de-enrollment when
 * PMPro membership levels change (single and group memberships).
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

use function add_action;
use function add_filter;
use function get_post_meta;
use function esc_html;
use function pmpro_getLevel;
use function pmpro_hasMembershipLevel;
use function update_user_meta;
use function get_user_meta;
use function time;

/**
 * Class PMPro
 *
 * Hooks into PMPro membership level changes to manage course enrollment.
 */
class PMPro
{

    /**
     * Hook into WordPress / PMPro.
     *
     * @return void
     */
    public static function init()
    {
        // Fires after a user's membership level is changed.
        add_action('pmpro_after_change_membership_level', array(__CLASS__, 'handle_level_change'), 10, 3);

        // Optional: filter for runtime access checks.
        add_filter('simple_lms_check_access', array(__CLASS__, 'filter_access_check'), 10, 3);

        // Admin Columns.
        add_filter('manage_slms_course_posts_columns', array(__CLASS__, 'add_admin_columns'));
        add_action('manage_slms_course_posts_custom_column', array(__CLASS__, 'render_admin_columns'), 10, 2);
    }

    /* ───────────────────────────────────────────────────────────────────
     * Level Change Handler
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Handle membership level change — enroll or de-enroll from courses.
     *
     * @param int $level_id        New membership level ID (0 = cancelled).
     * @param int $user_id         User ID.
     * @param int $cancel_level_id Old membership level ID being cancelled.
     * @return void
     */
    public static function handle_level_change($level_id, $user_id, $cancel_level_id)
    {
        // Process the individual user.
        self::process_user_enrollment($user_id, $level_id, $cancel_level_id);

        // If Group Accounts addon is active, also process group child members.
        self::process_group_members($user_id, $level_id, $cancel_level_id);
    }

    /**
     * Enroll or de-enroll a single user based on level change.
     *
     * @param int $user_id         User ID.
     * @param int $new_level_id    New membership level ID.
     * @param int $cancel_level_id Old level being cancelled.
     * @return void
     */
    public static function process_user_enrollment($user_id, $new_level_id, $cancel_level_id)
    {
        // Enroll in courses mapped to the new level.
        if ($new_level_id > 0) {
            $courses = self::get_courses_for_level($new_level_id);
            foreach ($courses as $course_id) {
                self::enroll_user($user_id, $course_id);
            }
        }

        // De-enroll from courses that were exclusive to the cancelled level.
        if ($cancel_level_id > 0) {
            $old_courses = self::get_courses_for_level($cancel_level_id);
            $new_courses = $new_level_id > 0 ?self::get_courses_for_level($new_level_id) : array();

            foreach ($old_courses as $course_id) {
                // Only de-enroll if the course is NOT also mapped to the new level.
                if (!in_array($course_id, $new_courses, true)) {
                    self::de_enroll_user($user_id, $course_id);
                }
            }
        }
    }

    /* ───────────────────────────────────────────────────────────────────
     * Group Membership Support
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * If the Group Accounts addon is active, process all child members
     * of the group owned by this user.
     *
     * @param int $user_id         Group owner user ID.
     * @param int $new_level_id    New membership level ID.
     * @param int $cancel_level_id Old level being cancelled.
     * @return void
     */
    public static function process_group_members($user_id, $new_level_id, $cancel_level_id)
    {
        // Check if Group Accounts addon functions exist.
        if (!function_exists('pmprogroupacct_get_group_for_user')) {
            return;
        }

        // Get groups owned by this user.
        $groups = self::get_groups_for_owner($user_id);

        foreach ($groups as $group) {
            $members = self::get_group_members($group);

            foreach ($members as $member_user_id) {
                if ((int)$member_user_id !== (int)$user_id) {
                    self::process_user_enrollment($member_user_id, $new_level_id, $cancel_level_id);
                }
            }
        }
    }

    /**
     * Get groups owned by a user.
     *
     * @param int $user_id Owner user ID.
     * @return array Array of group objects or IDs.
     */
    private static function get_groups_for_owner($user_id)
    {
        if (!class_exists('PMPro_Group_Account')) {
            return array();
        }

        if (method_exists('\PMPro_Group_Account', 'get_groups_by_owner')) {
            return \PMPro_Group_Account::get_groups_by_owner($user_id);
        }

        // Fallback: direct DB query for pmpro_groups table.
        global $wpdb;
        $table = $wpdb->prefix . 'pmprogroupacct_groups';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_results(
            $wpdb->prepare(
            "SELECT * FROM {$table} WHERE group_parent_user_id = %d",
            $user_id
        )
        );
    }

    /**
     * Get member user IDs for a group.
     *
     * @param object|int $group Group object or ID.
     * @return array Array of user IDs.
     */
    private static function get_group_members($group)
    {
        $group_id = is_object($group) ? $group->id : (int)$group;

        if (function_exists('pmprogroupacct_get_members_for_group')) {
            $members = pmprogroupacct_get_members_for_group($group_id);
            return is_array($members) ? wp_list_pluck($members, 'group_child_user_id') : array();
        }

        // Fallback: direct DB query.
        global $wpdb;
        $table = $wpdb->prefix . 'pmprogroupacct_group_members';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_col(
            $wpdb->prepare(
            "SELECT group_child_user_id FROM {$table} WHERE group_id = %d",
            $group_id
        )
        );
    }

    /**
     * Add PMPro Levels column.
     *
     * @param array $columns Columns.
     * @return array
     */
    public static function add_admin_columns($columns)
    {
        $columns['pmpro_levels'] = __('PMPro Levels', 'simple-lms-bridge');
        return $columns;
    }

    /**
     * Render PMPro Levels column.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     * @return void
     */
    public static function render_admin_columns($column, $post_id)
    {
        if ('pmpro_levels' === $column) {
            $levels = get_post_meta($post_id, '_lms_pmpro_levels', true);
            if (empty($levels) || !is_array($levels)) {
                echo '—';
                return;
            }

            $level_names = array();
            foreach ($levels as $level_id) {
                if (function_exists('pmpro_getLevel')) {
                    $level = pmpro_getLevel($level_id);
                    $level_names[] = $level ? esc_html($level->name) : '#' . esc_html($level_id);
                }
                else {
                    $level_names[] = '#' . esc_html($level_id);
                }
            }
            echo implode(', ', $level_names);
        }
    }

    /**
     * Runtime access check filter.
     *
     * @param bool $has_access Existing access state.
     * @param int  $user_id    User ID.
     * @param int  $course_id  Course ID.
     * @return bool
     */
    public static function filter_access_check($has_access, $user_id, $course_id)
    {
        if ($has_access) {
            return true;
        }

        return self::has_course_access($user_id, $course_id);
    }

    /**
     * Check if a user has access to a course based on PMPro levels.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function has_course_access($user_id, $course_id)
    {
        if (!function_exists('pmpro_hasMembershipLevel')) {
            return false;
        }

        $required_levels = get_post_meta($course_id, '_lms_pmpro_levels', true);
        if (empty($required_levels) || !is_array($required_levels)) {
            return false;
        }

        return pmpro_hasMembershipLevel($required_levels, $user_id);
    }

    /**
     * Get all course IDs mapped to a PMPro level.
     *
     * @param int $level_id PMPro membership level ID.
     * @return array Array of course post IDs.
     */
    public static function get_courses_for_level($level_id)
    {
        $query = new \WP_Query(array(
            'post_type' => 'slms_course',
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'no_found_rows' => true,
            'fields' => 'ids',
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
                    array(
                    'key' => '_lms_pmpro_levels',
                    'value' => sprintf('"%d"', $level_id),
                    'compare' => 'LIKE',
                ),
            ),
        ));

        // Double-check with actual meta since LIKE on serialized is imprecise.
        $confirmed = array();
        foreach ($query->posts as $course_id) {
            $levels = get_post_meta($course_id, '_lms_pmpro_levels', true);
            if (is_array($levels) && in_array((int)$level_id, array_map('intval', $levels), true)) {
                $confirmed[] = (int)$course_id;
            }
        }

        wp_reset_postdata();

        return $confirmed;
    }

    /**
     * Enroll a user in a course (initialize progress if not already enrolled).
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return void
     */
    public static function enroll_user($user_id, $course_id)
    {
        // 1. Sync to Join Table.
        Relationships::enroll_user($user_id, $course_id, 'pmpro');

        // 2. Record enrollment timestamp in user meta (legacy/backup).
        $enrolled = get_user_meta($user_id, '_lms_enrolled_at', true);
        if (!is_array($enrolled)) {
            $enrolled = array();
        }
        if (!isset($enrolled[$course_id])) {
            $enrolled[$course_id] = time();
            update_user_meta($user_id, '_lms_enrolled_at', $enrolled);
        }
    }

    /**
     * De-enroll a user from a course (remove progress and PMPro level).
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return void
     */
    public static function de_enroll_user($user_id, $course_id)
    {
        // 1. Sync to Join Table.
        Relationships::unenroll_user($user_id, $course_id);

        // 2. Clear progress meta.
        $progress = get_user_meta($user_id, '_lms_progress', true);
        if (is_array($progress) && isset($progress[$course_id])) {
            unset($progress[$course_id]);
            update_user_meta($user_id, '_lms_progress', $progress);
        }

        // 3. Clear enrollment timestamp.
        $enrolled = get_user_meta($user_id, '_lms_enrolled_at', true);
        if (is_array($enrolled) && isset($enrolled[$course_id])) {
            unset($enrolled[$course_id]);
            update_user_meta($user_id, '_lms_enrolled_at', $enrolled);
        }

        // 4. Remove PMPro membership level(s) mapped to this course.
        if (function_exists('pmpro_changeMembershipLevel') && function_exists('pmpro_getMembershipLevelForUser')) {
            $required_levels = get_post_meta($course_id, '_lms_pmpro_levels', true);
            if (is_array($required_levels)) {
                $current_level = \pmpro_getMembershipLevelForUser($user_id);
                $current_level_id = $current_level ? (int)$current_level->id : 0;

                if ($current_level_id && in_array($current_level_id, array_map('intval', $required_levels), true)) {
                    \pmpro_changeMembershipLevel(0, $user_id);
                }
            }
        }
    }
}