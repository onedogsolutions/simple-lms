<?php
/**
 * Access service for SimpleLMS.
 *
 * Central authority for "can this user view this lesson?" decisions and for
 * the state-machine helpers (course state, first-incomplete lesson, drip
 * unlock dates, progress) consumed by the Beaver Builder frontend modules.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Access
 */
class Access
{

    /* ───────────────────────────────────────────────────────────────────
     * Course resolution helpers
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Resolve the course ID for an arbitrary post (course or lesson).
     *
     * @param \WP_Post|int|null $post Post object or ID. Defaults to current post.
     * @return int Course ID, or 0 when it cannot be resolved.
     */
    public static function resolve_course_id($post = null)
    {
        $post = get_post($post);

        if (!$post) {
            return 0;
        }

        if ('slms_course' === $post->post_type) {
            return (int) $post->ID;
        }

        if ('slms_lesson' === $post->post_type) {
            $courses = Relationships::get_courses_for_lesson($post->ID);
            return !empty($courses) ? (int) $courses[0]->id : 0;
        }

        return 0;
    }

    /**
     * Ordered array of published lesson IDs for a course.
     *
     * @param int $course_id Course ID.
     * @return int[]
     */
    public static function get_lesson_ids($course_id)
    {
        $lesson_ids = get_post_meta($course_id, '_simple_lms_order', true);

        if (!is_array($lesson_ids)) {
            return array();
        }

        return array_values(array_filter(array_map('absint', $lesson_ids)));
    }

    /* ───────────────────────────────────────────────────────────────────
     * Enrollment
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Whether a user is enrolled in (or has PMPro access to) a course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function is_enrolled($user_id, $course_id)
    {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id) {
            return false;
        }

        if (Relationships::is_enrolled($user_id, $course_id)) {
            return true;
        }

        // Fall back to PMPro membership access.
        if (class_exists(__NAMESPACE__ . '\PMPro') && PMPro::has_course_access($user_id, $course_id)) {
            return true;
        }

        return false;
    }

    /**
     * Enrollment timestamp (unix) for a user in a course.
     *
     * Prefers the join table; falls back to the legacy _lms_enrolled_at meta.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return int Unix timestamp, or 0 when unknown.
     */
    public static function get_enrolled_timestamp($user_id, $course_id)
    {
        $enrolled_at = Relationships::get_enrolled_at($user_id, $course_id);
        if ($enrolled_at) {
            $ts = strtotime($enrolled_at);
            if ($ts) {
                return (int) $ts;
            }
        }

        $legacy = get_user_meta($user_id, '_lms_enrolled_at', true);
        if (is_array($legacy) && isset($legacy[$course_id]) && is_numeric($legacy[$course_id])) {
            return (int) $legacy[$course_id];
        }

        return 0;
    }

    /* ───────────────────────────────────────────────────────────────────
     * Drip scheduling
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * The unix timestamp at which a lesson unlocks for a user via drip.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @param int $course_id Course ID (resolved automatically when 0).
     * @return int Unlock timestamp, or 0 when the lesson is immediately available.
     */
    public static function get_unlock_timestamp($user_id, $lesson_id, $course_id = 0)
    {
        $drip_days = (int) get_post_meta($lesson_id, '_lms_drip_days', true);

        if ($drip_days <= 0) {
            return 0;
        }

        if (!$course_id) {
            $course_id = self::resolve_course_id($lesson_id);
        }

        $enrolled_ts = self::get_enrolled_timestamp($user_id, $course_id);
        if (!$enrolled_ts) {
            return 0;
        }

        return $enrolled_ts + ($drip_days * DAY_IN_SECONDS);
    }

    /**
     * Whether a lesson is currently drip-locked for a user.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @param int $course_id Course ID (resolved automatically when 0).
     * @return bool
     */
    public static function is_dripped($user_id, $lesson_id, $course_id = 0)
    {
        $unlock_ts = self::get_unlock_timestamp($user_id, $lesson_id, $course_id);

        return $unlock_ts > 0 && (int) current_time('timestamp') < $unlock_ts;
    }

    /* ───────────────────────────────────────────────────────────────────
     * Core access decision
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Whether a user may view a course.
     *
     * @param int $user_id   User ID (0 = current user).
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function can_view_course($user_id, $course_id)
    {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();

        if ($user_id && user_can($user_id, 'edit_posts')) {
            return true;
        }

        $guard_mode = get_post_meta($course_id, '_lms_guard_mode', true);
        if (!$guard_mode) {
            $guard_mode = class_exists(__NAMESPACE__ . '\\Settings') ? Settings::get('default_guard_mode', 'enrolled') : 'enrolled';
        }

        if ('public' === $guard_mode) {
            return true;
        }

        if (!$user_id) {
            return false;
        }

        if ('level' === $guard_mode) {
            return class_exists(__NAMESPACE__ . '\PMPro') && PMPro::has_course_access($user_id, $course_id);
        }

        // 'enrolled' or fallback.
        return Relationships::is_enrolled($user_id, $course_id);
    }

    /**
     * Whether a user may view a lesson.
     *
     * Combines enrollment/PMPro access with drip scheduling. Editors always
     * pass so the builder and previews remain usable.
     *
     * @param int $user_id   User ID (0 = current user).
     * @param int $lesson_id Lesson ID.
     * @param int $course_id Course ID (resolved automatically when 0).
     * @return bool
     */
    public static function can_view($user_id, $lesson_id, $course_id = 0)
    {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();

        if (!$course_id) {
            $course_id = self::resolve_course_id($lesson_id);
        }

        // Editors/administrators may always view (builder + preview).
        if ($user_id && user_can($user_id, 'edit_posts')) {
            return true;
        }

        if (!self::can_view_course($user_id, $course_id)) {
            return false;
        }

        if (!$user_id) {
            return false;
        }

        if (self::is_dripped($user_id, $lesson_id, $course_id)) {
            return false;
        }

        /**
         * Filter the final access decision for a lesson.
         *
         * @param bool $can_view  Whether the user can view.
         * @param int  $user_id   User ID.
         * @param int  $lesson_id Lesson ID.
         * @param int  $course_id Course ID.
         */
        return (bool) apply_filters('slms_can_view_lesson', true, $user_id, $lesson_id, $course_id);
    }

    /**
     * Get the reason a user is denied access to a lesson.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @param int $course_id Course ID (resolved automatically when 0).
     * @return string Reason: not_logged_in, not_enrolled, expired, or dripped.
     */
    public static function denial_reason($user_id, $lesson_id, $course_id = 0)
    {
        $user_id = absint($user_id);
        
        if (!$course_id) {
            $course_id = self::resolve_course_id($lesson_id);
        }

        if (!$user_id) {
            return 'not_logged_in';
        }

        if (!self::can_view_course($user_id, $course_id)) {
            if (class_exists(__NAMESPACE__ . '\Expiration') && Expiration::is_expired($user_id, $course_id)) {
                return 'expired';
            }
            return 'not_enrolled';
        }

        if (self::is_dripped($user_id, $lesson_id, $course_id)) {
            return 'dripped';
        }

        return '';
    }

    /* ───────────────────────────────────────────────────────────────────
     * Progress + state machine
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Write lesson completion state for a user.
     *
     * Single source of truth for marking a lesson complete/incomplete. Both the
     * REST /progress endpoint and the quiz auto-completion path funnel through
     * here so certificate automation and completion detection stay consistent.
     *
     * @param int  $user_id   User ID.
     * @param int  $course_id Course ID.
     * @param int  $lesson_id Lesson ID.
     * @param bool $completed Whether the lesson is completed.
     * @return array The updated full _lms_progress array.
     */
    public static function set_lesson_progress($user_id, $course_id, $lesson_id, $completed = true)
    {
        $progress = get_user_meta($user_id, '_lms_progress', true);

        if (!is_array($progress)) {
            $progress = array();
        }

        if ($completed) {
            if (!isset($progress[$course_id]) || !is_array($progress[$course_id])) {
                $progress[$course_id] = array();
            }
            // Preserve an existing completion timestamp if already recorded.
            if (!isset($progress[$course_id][$lesson_id])) {
                $progress[$course_id][$lesson_id] = time();
            }
        } else {
            unset($progress[$course_id][$lesson_id]);

            if (isset($progress[$course_id]) && empty($progress[$course_id])) {
                unset($progress[$course_id]);
            }
        }

        update_user_meta($user_id, '_lms_progress', $progress);

        // Trigger certificate automation / completion detection.
        if (class_exists(__NAMESPACE__ . '\Certificates')) {
            Certificates::check_course_completion($user_id, $course_id);
        }

        return $progress;
    }

    /**
     * Completed-lesson map for a user within a course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return array Map of lesson_id => completion timestamp.
     */
    public static function get_course_progress($user_id, $course_id)
    {
        $progress = get_user_meta($user_id, '_lms_progress', true);

        if (is_array($progress) && isset($progress[$course_id]) && is_array($progress[$course_id])) {
            return $progress[$course_id];
        }

        return array();
    }

    /**
     * Progress statistics for a user within a course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return array{total:int,completed:int,percent:int}
     */
    public static function get_progress_stats($user_id, $course_id)
    {
        $lesson_ids = self::get_lesson_ids($course_id);
        $total      = count($lesson_ids);

        $course_progress = self::get_course_progress($user_id, $course_id);

        // Only count lessons that are actually part of the current outline.
        $completed = 0;
        foreach ($lesson_ids as $lesson_id) {
            if (isset($course_progress[$lesson_id])) {
                $completed++;
            }
        }

        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return array(
            'total'     => $total,
            'completed' => $completed,
            'percent'   => $percent,
        );
    }

    /**
     * Whether a user has completed every lesson in a course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function is_course_complete($user_id, $course_id)
    {
        $lesson_ids = self::get_lesson_ids($course_id);

        if (empty($lesson_ids)) {
            return false;
        }

        $course_progress = self::get_course_progress($user_id, $course_id);

        foreach ($lesson_ids as $lesson_id) {
            if (!isset($course_progress[$lesson_id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The first lesson the user has not yet completed (respecting outline order).
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return int Lesson ID, or 0 when all lessons are complete / none exist.
     */
    public static function get_first_incomplete_lesson($user_id, $course_id)
    {
        $lesson_ids = self::get_lesson_ids($course_id);

        if (empty($lesson_ids)) {
            return 0;
        }

        $course_progress = self::get_course_progress($user_id, $course_id);

        foreach ($lesson_ids as $lesson_id) {
            if (!isset($course_progress[$lesson_id])) {
                return (int) $lesson_id;
            }
        }

        return 0;
    }

    /**
     * Permalink for the "Continue" / "Start" target lesson.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return string URL of the first incomplete lesson, falling back to the
     *                first lesson, then the course permalink.
     */
    public static function get_continue_url($user_id, $course_id)
    {
        $lesson_id = self::get_first_incomplete_lesson($user_id, $course_id);

        if (!$lesson_id) {
            $lesson_ids = self::get_lesson_ids($course_id);
            $lesson_id  = !empty($lesson_ids) ? $lesson_ids[0] : 0;
        }

        if ($lesson_id) {
            return (string) get_permalink($lesson_id);
        }

        return (string) get_permalink($course_id);
    }

    /**
     * Format a course price for display.
     *
     * @param float $price Price value.
     * @return string
     */
    public static function format_price($price)
    {
        $price = (float) $price;

        if (function_exists('pmpro_formatPrice')) {
            return \pmpro_formatPrice($price);
        }

        return '$' . number_format_i18n($price, 2);
    }

    /**
     * PMPro checkout URL for the level mapped to a course.
     *
     * @param int $course_id Course ID.
     * @param string $return_url Optional return URL.
     * @return string Checkout URL, or '' when no level is mapped / PMPro absent.
     */
    public static function get_checkout_url($course_id, $return_url = '')
    {
        $levels = get_post_meta($course_id, '_lms_pmpro_levels', true);

        if (empty($levels) || !is_array($levels)) {
            return '';
        }

        $level_id = (int) reset($levels);
        if (!$level_id) {
            return '';
        }

        if (function_exists('pmpro_url')) {
            $url = \pmpro_url('checkout', '?level=' . $level_id);
            if ($return_url) {
                $url = add_query_arg('return', urlencode($return_url), $url);
            }
            return $url;
        }

        return '';
    }

    /**
     * Build the state-aware CTA descriptor for a course.
     *
     * States → labels/targets:
     *   guest        → Login / Buy   (checkout if mapped, else login)
     *   not_enrolled → Buy           (PMPro checkout, else course page)
     *   not_started  → Start         (first lesson)
     *   in_progress  → Continue      (first incomplete lesson)
     *   completed    → View Certificate (dashboard URL)
     *
     * @param int   $course_id Course ID.
     * @param int   $user_id   User ID (0 = current user).
     * @param array $args      Optional: 'dashboard_url' for the completed state.
     * @return array{state:string,label:string,url:string,classes:string}
     */
    public static function get_cta($course_id, $user_id = 0, $args = array())
    {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        $state   = self::get_course_state($user_id, $course_id);

        $checkout_url  = self::get_checkout_url($course_id);
        $course_url    = (string) get_permalink($course_id);
        $dashboard_url = !empty($args['dashboard_url']) ? $args['dashboard_url'] : home_url('/my-account/');

        switch ($state) {
            case 'completed':
                $cta = array(
                    'label'   => __('View Certificate', 'simple-lms-bridge'),
                    'url'     => $dashboard_url,
                    'classes' => 'slms-cta-completed',
                );
                break;

            case 'in_progress':
                $cta = array(
                    'label'   => __('Continue', 'simple-lms-bridge'),
                    'url'     => self::get_continue_url($user_id, $course_id),
                    'classes' => 'slms-cta-continue',
                );
                break;

            case 'not_started':
                $cta = array(
                    'label'   => __('Start', 'simple-lms-bridge'),
                    'url'     => self::get_continue_url($user_id, $course_id),
                    'classes' => 'slms-cta-start',
                );
                break;

            case 'not_enrolled':
                $cta = array(
                    'label'   => __('Buy', 'simple-lms-bridge'),
                    'url'     => $checkout_url ? $checkout_url : $course_url,
                    'classes' => 'slms-cta-buy',
                );
                break;

            case 'guest':
            default:
                $cta = array(
                    'label'   => $checkout_url ? __('Login / Buy', 'simple-lms-bridge') : __('Log In', 'simple-lms-bridge'),
                    'url'     => $checkout_url ? $checkout_url : wp_login_url($course_url),
                    'classes' => 'slms-cta-guest',
                );
                break;
        }

        $cta['state'] = $state;

        return $cta;
    }

    /**
     * The current user's enrolled courses enriched with progress + continue links.
     *
     * Shared by the lms-my-courses module and the Student Dashboard "My Courses"
     * tab so both present identical data.
     *
     * @param int $user_id User ID.
     * @return array[] List of {id, title, permalink, thumbnail, percent, total,
     *                 completed, state, continue_url}.
     */
    public static function get_enrolled_courses_with_progress($user_id)
    {
        $user_id = absint($user_id);
        if (!$user_id) {
            return array();
        }

        $enrollments = Relationships::get_courses_for_user($user_id);
        $courses     = array();

        foreach ($enrollments as $enrollment) {
            $course_id = (int) $enrollment->id;
            $stats     = self::get_progress_stats($user_id, $course_id);

            $courses[] = array(
                'id'           => $course_id,
                'title'        => $enrollment->title,
                'permalink'    => (string) get_permalink($course_id),
                'thumbnail'    => get_the_post_thumbnail_url($course_id, 'medium'),
                'percent'      => $stats['percent'],
                'total'        => $stats['total'],
                'completed'    => $stats['completed'],
                'state'        => self::get_course_state($user_id, $course_id),
                'continue_url' => self::get_continue_url($user_id, $course_id),
            );
        }

        return $courses;
    }

    /**
     * Resolve the state-machine state for a user + course.
     *
     * @param int $user_id   User ID (0 = current user).
     * @param int $course_id Course ID.
     * @return string One of: guest, not_enrolled, not_started, in_progress, completed.
     */
    public static function get_course_state($user_id, $course_id)
    {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();

        if (!$user_id) {
            return 'guest';
        }

        if (!self::is_enrolled($user_id, $course_id)) {
            return 'not_enrolled';
        }

        if (self::is_course_complete($user_id, $course_id)) {
            return 'completed';
        }

        $stats = self::get_progress_stats($user_id, $course_id);

        return $stats['completed'] > 0 ? 'in_progress' : 'not_started';
    }
}
