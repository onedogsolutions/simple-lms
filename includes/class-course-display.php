<?php
/**
 * Course display and state presentation helpers for SimpleLMS.
 *
 * Houses UI representation logic, pricing, checkout URLs, CTA-states,
 * and continuing progress paths that support frontend rendering and
 * Beaver Builder layout modules.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CourseDisplay
 */
class CourseDisplay
{

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
     * Permalink for the "Continue" / "Start" target lesson.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return string URL of the first incomplete lesson, falling back to the
     *                first lesson, then the course permalink.
     */
    public static function get_continue_url($user_id, $course_id)
    {
        $lesson_id = Progress::first_incomplete($user_id, $course_id);

        if (!$lesson_id) {
            $lesson_ids = Access::get_lesson_ids($course_id);
            $lesson_id  = !empty($lesson_ids) ? $lesson_ids[0] : 0;
        }

        if ($lesson_id) {
            return (string) get_permalink($lesson_id);
        }

        return (string) get_permalink($course_id);
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

        $checkout_url  = Access::get_checkout_url($course_id);
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
            $stats     = Progress::stats($user_id, $course_id);

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

        if (!Access::is_enrolled($user_id, $course_id)) {
            return 'not_enrolled';
        }

        if (Progress::is_course_complete($user_id, $course_id)) {
            return 'completed';
        }

        $stats = Progress::stats($user_id, $course_id);

        return $stats['completed'] > 0 ? 'in_progress' : 'not_started';
    }
}
