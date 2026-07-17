<?php
/**
 * Access resolution authority for SimpleLMS.
 *
 * All "can this user see this content?" decisions flow through this class so
 * the rules live in exactly one place. Enforcement layers (template_redirect,
 * the_content, REST) call Access::can_view(); messaging / redirect targeting
 * calls Access::denial_reason().
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

    /**
     * Guard mode meta key on the course post.
     *
     * @var string
     */
    const GUARD_META = '_lms_guard_mode';

    /**
     * Can the given user view the given post (course or lesson)?
     *
     * Resolution order:
     *   1. Resolve lesson → course(s). Course posts check themselves.
     *   2. Read guard mode meta (public | enrolled | level, default enrolled).
     *   3. public → true. enrolled → enrollment row exists. level → PMPro level.
     *   4. Expiration check against enrolled_at + _lms_access_days.
     *   5. Filtered through `simple_lms_check_access`.
     *
     * @param int $user_id User ID (0 = logged out).
     * @param int $post_id Post ID (course or lesson).
     * @return bool
     */
    public static function can_view($user_id, $post_id)
    {
        $user_id = absint($user_id);
        $post_id = absint($post_id);

        // Editors/admins who can edit the post always see it (block editor,
        // previews, authenticated REST edit context).
        if ($user_id && user_can($user_id, 'edit_post', $post_id)) {
            return true;
        }

        $courses = self::resolve_courses($post_id);

        // Nothing to guard (not an LMS post, or an orphan lesson).
        if (empty($courses)) {
            $result = true;
        } else {
            $result = false;
            foreach ($courses as $course_id) {
                if (self::course_grants($user_id, $course_id)) {
                    $result = true;
                    break;
                }
            }
        }

        /**
         * Filter the final access decision.
         *
         * Wires the PMPro runtime override registered in class-pmpro.php so a
         * membership-level holder can be granted access without an enrollment
         * row.
         *
         * @param bool $result  Computed access decision.
         * @param int  $user_id User ID.
         * @param int  $post_id Post being checked.
         */
        return (bool) apply_filters('simple_lms_check_access', $result, $user_id, $post_id);
    }

    /**
     * Reason the user is denied — for redirect targeting and messaging.
     *
     * Only meaningful when can_view() is false. Returns one of:
     * not_logged_in | not_enrolled | expired.
     *
     * @param int $user_id User ID (0 = logged out).
     * @param int $post_id Post ID.
     * @return string
     */
    public static function denial_reason($user_id, $post_id)
    {
        $user_id = absint($user_id);
        $post_id = absint($post_id);

        if (!$user_id) {
            return 'not_logged_in';
        }

        $courses = self::resolve_courses($post_id);

        // If any course records an enrollment row, the denial is expiry.
        foreach ($courses as $course_id) {
            if (Relationships::is_user_enrolled($user_id, $course_id) && self::is_expired($user_id, $course_id)) {
                return 'expired';
            }
        }

        return 'not_enrolled';
    }

    /* ───────────────────────────────────────────────────────────────────
     * Internals
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Resolve a post to the course ID(s) that govern its access.
     *
     * @param int $post_id Post ID.
     * @return int[] Course post IDs (empty if not an LMS post).
     */
    public static function resolve_courses($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        if ('slms_course' === $post->post_type) {
            return array((int) $post->ID);
        }

        if ('slms_lesson' === $post->post_type) {
            $courses = Relationships::get_courses_for_lesson($post->ID);
            return array_map(function ($c) {
                return (int) $c->id;
            }, $courses);
        }

        return array();
    }

    /**
     * Whether a single course grants access to a user.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return bool
     */
    private static function course_grants($user_id, $course_id)
    {
        $mode = self::guard_mode($course_id);

        if ('public' === $mode) {
            return true;
        }

        // Any non-public mode requires an authenticated user.
        if (!$user_id) {
            return false;
        }

        if ('level' === $mode) {
            if (class_exists(__NAMESPACE__ . '\\PMPro') && PMPro::has_course_access($user_id, $course_id)) {
                return true;
            }
            return false;
        }

        // Default: 'enrolled'.
        if (!Relationships::is_user_enrolled($user_id, $course_id)) {
            return false;
        }

        if (self::is_expired($user_id, $course_id)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the guard mode for a course, honouring the site default.
     *
     * @param int $course_id Course post ID.
     * @return string public | enrolled | level
     */
    public static function guard_mode($course_id)
    {
        $mode = get_post_meta($course_id, self::GUARD_META, true);

        if (!in_array($mode, array('public', 'enrolled', 'level'), true)) {
            $mode = self::default_guard_mode();
        }

        return $mode;
    }

    /**
     * Site-wide default guard mode (Settings-overridable).
     *
     * @return string
     */
    public static function default_guard_mode()
    {
        if (class_exists(__NAMESPACE__ . '\\Settings')) {
            $default = Settings::get('default_guard_mode', 'enrolled');
            if (in_array($default, array('public', 'enrolled', 'level'), true)) {
                return $default;
            }
        }
        return 'enrolled';
    }

    /**
     * Whether the user's access to a course has expired.
     *
     * Uses the course's _lms_access_days against the enrollment timestamp.
     * Prefers the legacy `_lms_enrolled_at` user meta (unix) used by the daily
     * cron, falling back to the enrollment table's enrolled_at column.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return bool
     */
    public static function is_expired($user_id, $course_id)
    {
        $access_days = (int) get_post_meta($course_id, '_lms_access_days', true);

        // 0 = unlimited access.
        if ($access_days <= 0) {
            return false;
        }

        $enrolled_at = self::enrolled_at($user_id, $course_id);
        if (!$enrolled_at) {
            return false;
        }

        $expiry = $enrolled_at + ($access_days * DAY_IN_SECONDS);

        return time() > $expiry;
    }

    /**
     * Resolve an enrollment timestamp for a user/course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return int|null Unix timestamp or null.
     */
    private static function enrolled_at($user_id, $course_id)
    {
        $meta = get_user_meta($user_id, '_lms_enrolled_at', true);
        if (is_array($meta) && !empty($meta[$course_id]) && is_numeric($meta[$course_id])) {
            return (int) $meta[$course_id];
        }

        return Relationships::get_enrolled_at($user_id, $course_id);
    }

    /**
     * Build the redirect target for a denied logged-in user: PMPro checkout
     * for the course's first mapped level, else the levels page.
     *
     * @param int    $course_id  Course post ID.
     * @param string $return_url URL to return to after checkout.
     * @return string
     */
    public static function get_checkout_url($course_id, $return_url = '')
    {
        $levels = get_post_meta($course_id, '_lms_pmpro_levels', true);
        $level_id = (is_array($levels) && !empty($levels)) ? (int) reset($levels) : 0;

        // Per-course override wins; otherwise fall back to the Settings default.
        $override = (int) get_post_meta($course_id, '_lms_checkout_override', true);
        if (!$override && class_exists(__NAMESPACE__ . '\\Settings')) {
            $override = (int) Settings::get('checkout_page_id', 0);
        }

        if ($override) {
            $url = get_permalink($override);
        } elseif ($level_id && function_exists('pmpro_url')) {
            $url = \pmpro_url('checkout', '?level=' . $level_id);
        } elseif (function_exists('pmpro_url')) {
            $url = \pmpro_url('levels');
        } else {
            $url = home_url('/membership-levels/');
        }

        if ($return_url) {
            $url = add_query_arg('slms_return', rawurlencode($return_url), $url);
        }

        return $url;
    }
}
