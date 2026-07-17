<?php
/**
 * Custom Post Type registration for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CPT
 *
 * Registers the slms_course and slms_lesson post types and their meta fields.
 */
class CPT
{

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        self::register_post_types();
        self::register_taxonomies();
        self::register_meta();
    }

    /* ───────────────────────────────────────────────────────────────────
     * Post Types
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Register slms_course and slms_lesson CPTs.
     *
     * @return void
     */
    public static function register_post_types()
    {

        /* ── Course ──────────────────────────────────────────────────── */
        register_post_type('slms_course', array(
            'labels' => self::labels('Course', 'Courses'),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'courses', 'with_front' => false),
            'show_in_rest' => true,
            'rest_base' => 'lms-courses',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'menu_icon' => 'dashicons-welcome-learn-more',
            'show_in_menu' => 'simple-lms',
        ));

        /* ── Lesson ─────────────────────────────────────────────────── */
        register_post_type('slms_lesson', array(
            'labels' => self::labels('Lesson', 'Lessons'),
            'public' => true,
            'has_archive' => false,
            'rewrite' => array('slug' => 'lessons', 'with_front' => false),
            'show_in_rest' => true,
            'rest_base' => 'lms-lessons',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon' => 'dashicons-media-text',
            'show_in_menu' => 'simple-lms',
        ));
    }

    /**
     * Register Taxonomies.
     *
     * @return void
     */
    public static function register_taxonomies()
    {
        register_taxonomy('slms_course_cat', array('slms_course', 'slms_lesson'), array(
            'labels' => self::labels('Course Category', 'Course Categories'),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'simple-lms',
            'rest_base' => 'lms-course-categories',
            'rewrite' => array('slug' => 'course-category'),
        ));
    }

    /* ───────────────────────────────────────────────────────────────────
     * Meta Fields
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Register post meta for both CPTs so they are available via REST API.
     *
     * @return void
     */
    public static function register_meta()
    {

        /* ── Course Meta ────────────────────────────────────────────── */

        // Guard mode (public, level, enrolled).
        register_post_meta('slms_course', '_lms_guard_mode', array(
            'type'              => 'string',
            'description'       => 'Course access guard mode (public, level, enrolled).',
            'single'            => true,
            'show_in_rest'      => true,
            'default'           => 'enrolled',
            'sanitize_callback' => function ($value) {
                return in_array($value, array('public', 'level', 'enrolled'), true) ? $value : 'enrolled';
            },
            'auth_callback'     => function () {
                return current_user_can('edit_posts');
            },
        ));

        // Ordered array of lesson IDs.
        register_post_meta('slms_course', '_simple_lms_order', array(
            'type' => 'array',
            'description' => 'Ordered list of lesson post IDs.',
            'single' => true,
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'array',
                    'items' => array('type' => 'integer'),
                ),
            ),
            'default' => array(),
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Access expiration in days (0 = unlimited).
        register_post_meta('slms_course', '_lms_access_days', array(
            'type' => 'integer',
            'description' => 'Number of days a student retains access (0 = unlimited).',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Certificate Gravity Form ID.
        register_post_meta('slms_course', '_lms_certificate_form', array(
            'type' => 'integer',
            'description' => 'Gravity Form ID used for certificate generation.',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Course Price.
        register_post_meta('slms_course', '_slms_course_price', array(
            'type' => 'number',
            'description' => 'The price of the course.',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => function ($value) {
                return floatval($value);
            },
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Completion redirect URL (fired when the final lesson is completed).
        register_post_meta('slms_course', '_lms_completion_redirect', array(
            'type' => 'string',
            'description' => 'URL to redirect the student to when the course is completed.',
            'single' => true,
            'show_in_rest' => true,
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // PMPro membership level IDs that grant access.
        register_post_meta('slms_course', '_lms_pmpro_levels', array(
            'type' => 'array',
            'description' => 'PMPro membership level IDs granting course access.',
            'single' => true,
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'array',
                    'items' => array('type' => 'integer'),
                ),
            ),
            'default' => array(),
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Per-course native certificate template (background, preset, placeholders).
        register_post_meta('slms_course', \SimpleLMS\Certificates\Template::META_KEY, array(
            'type' => 'object',
            'description' => 'Native certificate template configuration.',
            'single' => true,
            'show_in_rest' => array(
                'schema' => \SimpleLMS\Certificates\Template::rest_schema(),
            ),
            'default' => \SimpleLMS\Certificates\Template::defaults(),
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        /* ── Lesson Meta ────────────────────────────────────────────── */

        // Lesson type: video | quiz.
        register_post_meta('slms_lesson', '_slms_lesson_type', array(
            'type' => 'string',
            'description' => 'Lesson content type: video or quiz.',
            'single' => true,
            'show_in_rest' => true,
            'default' => '',
            'sanitize_callback' => function ($value) {
            return in_array($value, array('video', 'quiz', ''), true) ? $value : '';
        },
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Presto Player video ID.
        register_post_meta('slms_lesson', '_lms_presto_video', array(
            'type' => 'integer',
            'description' => 'Presto Player video post ID.',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Gravity Form ID (for quiz lessons).
        register_post_meta('slms_lesson', '_lms_gravity_form', array(
            'type' => 'integer',
            'description' => 'Gravity Form ID used for quiz content.',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Quiz timer in minutes (0 = no timer).
        register_post_meta('slms_lesson', '_lms_quiz_timer', array(
            'type' => 'integer',
            'description' => 'Quiz time limit in minutes (0 = unlimited).',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Quiz passing-score gate: GF field ID holding the score.
        register_post_meta('slms_lesson', '_lms_quiz_pass_field', array(
            'type' => 'string',
            'description' => 'Gravity Forms field ID containing the quiz score (empty = no score gate).',
            'single' => true,
            'show_in_rest' => true,
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Quiz passing-score gate: minimum score required to auto-complete.
        register_post_meta('slms_lesson', '_lms_quiz_pass_min', array(
            'type' => 'number',
            'description' => 'Minimum quiz score required to auto-complete the lesson.',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => function ($value) {
                return floatval($value);
            },
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Drip delay in days after enrollment (0 = immediate).
        register_post_meta('slms_lesson', '_lms_drip_days', array(
            'type' => 'integer',
            'description' => 'Days after enrollment before this lesson unlocks (0 = immediate).',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // Video gating: minimum percent watched before completion is allowed.
        register_post_meta('slms_lesson', '_lms_video_gate_pct', array(
            'type' => 'integer',
            'description' => 'Percent of the video that must be watched before the lesson can be completed (0 = disabled).',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));
    }

    /* ───────────────────────────────────────────────────────────────────
     * Helpers
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Generate a standard set of CPT labels.
     *
     * @param string $singular Singular label.
     * @param string $plural   Plural label.
     * @return array
     */
    private static function labels($singular, $plural)
    {
        return array(
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => 'Add New',
            'add_new_item' => "Add New {$singular}",
            'edit_item' => "Edit {$singular}",
            'new_item' => "New {$singular}",
            'view_item' => "View {$singular}",
            'view_items' => "View {$plural}",
            'search_items' => "Search {$plural}",
            'not_found' => "No {$plural} found",
            'not_found_in_trash' => "No {$plural} found in Trash",
            'all_items' => "All {$plural}",
            'archives' => "{$singular} Archives",
            'attributes' => "{$singular} Attributes",
            'insert_into_item' => "Insert into {$singular}",
            'uploaded_to_this_item' => "Uploaded to this {$singular}",
            'menu_name' => $plural,
        );
    }
}