<?php
/**
 * Content enforcement for SimpleLMS.
 *
 * Turns the Access decision into real protection across three layers so
 * guarded content cannot leak through any surface:
 *   1. template_redirect — full-page guard on course/lesson singles.
 *   2. the_content       — excerpt + CTA fallback for archives, search, builders.
 *   3. REST              — strips rendered content from the public API.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Guard
 */
class Guard
{

    /**
     * LMS post types that are guarded.
     *
     * @var string[]
     */
    private static $post_types = array('slms_course', 'slms_lesson');

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'guard_singular'));
        add_filter('the_content', array(__CLASS__, 'guard_content'), 99);
        add_filter('rest_prepare_slms_course', array(__CLASS__, 'guard_rest'), 10, 3);
        add_filter('rest_prepare_slms_lesson', array(__CLASS__, 'guard_rest'), 10, 3);
    }

    /* ───────────────────────────────────────────────────────────────────
     * Layer 1: template_redirect
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Full-page guard for single course / lesson views.
     *
     * @return void
     */
    public static function guard_singular()
    {
        if (!is_singular(self::$post_types)) {
            return;
        }

        // Never redirect inside the Beaver Builder editor.
        if (self::is_builder_active()) {
            return;
        }

        $post_id = get_queried_object_id();
        $user_id = get_current_user_id();

        if (Access::can_view($user_id, $post_id)) {
            return;
        }

        // Respect the course's denial behavior: 'message' leaves the request on
        // the page so the the_content CTA renders instead of redirecting.
        $course_id = Access::resolve_course_id($post_id);
        if (!$course_id) {
            $course_id = $post_id;
        }
        if ('message' === get_post_meta($course_id, '_lms_denial_behavior', true)) {
            return;
        }

        $current_url = self::current_url();

        // Logged out → login (default) or checkout, per Settings.
        if (!$user_id) {
            $behavior = class_exists(__NAMESPACE__ . '\\Settings')
                ? Settings::get('login_redirect', 'login')
                : 'login';

            if ('checkout' === $behavior) {
                wp_safe_redirect(Access::get_checkout_url($course_id, $current_url));
            } else {
                wp_safe_redirect(wp_login_url($current_url));
            }
            exit;
        }

        // Logged in but not entitled → PMPro checkout for the course's level.
        $target = Access::get_checkout_url($course_id, $current_url);

        // get_checkout_url() may return an internal URL; wp_safe_redirect is fine.
        wp_safe_redirect($target);
        exit;
    }

    /* ───────────────────────────────────────────────────────────────────
     * Layer 2: the_content
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Replace guarded content with an excerpt + CTA when access is denied.
     *
     * Covers archives, search results and builder-rendered contexts where the
     * template_redirect guard does not fire.
     *
     * @param string $content Post content.
     * @return string
     */
    public static function guard_content($content)
    {
        // Do not interfere with the Beaver Builder editing experience.
        if (self::is_builder_active()) {
            return $content;
        }

        $post = get_post();
        if (!$post || !in_array($post->post_type, self::$post_types, true)) {
            return $content;
        }

        if (Access::can_view(get_current_user_id(), $post->ID)) {
            return $content;
        }

        return self::denied_markup($post);
    }

    /**
     * Build the excerpt + call-to-action markup shown in place of content.
     *
     * @param \WP_Post $post The guarded post.
     * @return string
     */
    private static function denied_markup($post)
    {
        $reason = Access::denial_reason(get_current_user_id(), $post->ID);

        $excerpt = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags($post->post_content), 40);

        $course_id = Access::resolve_course_id($post->ID);
        if (!$course_id) {
            $course_id = $post->ID;
        }

        if ('not_logged_in' === $reason) {
            $cta_url  = wp_login_url(self::current_url());
            $cta_text = __('Log in to continue', 'simple-lms-bridge');
            $message  = __('This content is available to enrolled students. Please log in to continue.', 'simple-lms-bridge');
        } elseif ('expired' === $reason) {
            $cta_url  = Access::get_checkout_url($course_id, self::current_url());
            $cta_text = __('Renew access', 'simple-lms-bridge');
            $message  = __('Your access to this course has expired.', 'simple-lms-bridge');
        } else {
            $cta_url  = Access::get_checkout_url($course_id, self::current_url());
            $cta_text = __('Enroll now', 'simple-lms-bridge');
            $message  = __('This content is available to enrolled students.', 'simple-lms-bridge');
        }

        $html  = '<div class="slms-access-denied">';
        if ($excerpt) {
            $html .= '<div class="slms-access-excerpt">' . wpautop(esc_html($excerpt)) . '</div>';
        }
        $html .= '<div class="slms-access-cta">';
        $html .= '<p class="slms-access-message">' . esc_html($message) . '</p>';
        $html .= '<a class="slms-access-button button button-primary" href="' . esc_url($cta_url) . '">' . esc_html($cta_text) . '</a>';
        $html .= '</div>';
        $html .= '</div>';

        /**
         * Filter the access-denied markup.
         *
         * @param string   $html   Rendered CTA markup.
         * @param \WP_Post $post   The guarded post.
         * @param string   $reason Denial reason.
         */
        return apply_filters('simple_lms_access_denied_markup', $html, $post, $reason);
    }

    /* ───────────────────────────────────────────────────────────────────
     * Layer 3: REST
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Strip rendered content from the REST response when access is denied.
     *
     * @param \WP_REST_Response $response The response object.
     * @param \WP_Post          $post     The post.
     * @param \WP_REST_Request  $request  The request.
     * @return \WP_REST_Response
     */
    public static function guard_rest($response, $post, $request)
    {
        if (Access::can_view(get_current_user_id(), $post->ID)) {
            return $response;
        }

        $data = $response->get_data();

        if (isset($data['content']) && is_array($data['content'])) {
            $data['content']['rendered']  = '';
            $data['content']['protected'] = true;

            // Also drop the raw block/content field if present (edit context).
            if (isset($data['content']['raw'])) {
                $data['content']['raw'] = '';
            }
        } elseif (isset($data['content'])) {
            $data['content'] = '';
        }

        $response->set_data($data);

        return $response;
    }

    /* ───────────────────────────────────────────────────────────────────
     * Helpers
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Whether the Beaver Builder editor/preview is active.
     *
     * @return bool
     */
    private static function is_builder_active()
    {
        return class_exists('FLBuilderModel') && \FLBuilderModel::is_builder_active();
    }

    /**
     * Best-effort current front-end URL.
     *
     * @return string
     */
    private static function current_url()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri  = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if ($host && $uri) {
            $scheme = is_ssl() ? 'https' : 'http';
            return $scheme . '://' . $host . $uri;
        }

        return home_url('/');
    }
}
