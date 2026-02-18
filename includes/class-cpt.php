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
 * Registers the lms_course and lms_lesson post types and their meta fields.
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
        add_action('init', array(__CLASS__, 'register_post_types'), 5);
        add_action('init', array(__CLASS__, 'register_taxonomies'), 5);
        add_action('init', array(__CLASS__, 'register_meta'), 6);
    }

    /* ───────────────────────────────────────────────────────────────────
     * Post Types
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Register lms_course and lms_lesson CPTs.
     *
     * @return void
     */
    public static function register_post_types()
    {

        /* ── Course ──────────────────────────────────────────────────── */
        register_post_type('lms_course', array(
            'labels' => self::labels('Course', 'Courses'),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'courses', 'with_front' => false),
            'show_in_rest' => true,
            'rest_base' => 'lms-courses',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'menu_icon' => 'dashicons-welcome-learn-more',
            'menu_position' => 25,
        ));

        /* ── Lesson ─────────────────────────────────────────────────── */
        register_post_type('lms_lesson', array(
            'labels' => self::labels('Lesson', 'Lessons'),
            'public' => true,
            'has_archive' => false,
            'rewrite' => array('slug' => 'lessons', 'with_front' => false),
            'show_in_rest' => true,
            'rest_base' => 'lms-lessons',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon' => 'dashicons-media-text',
            'menu_position' => 26,
        ));
    }

    /**
     * Register Taxonomies.
     *
     * @return void
     */
    public static function register_taxonomies()
    {
        \register_taxonomy('lms_course_cat', 'lms_course', array(
            'labels' => self::labels('Course Category', 'Course Categories'),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
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

        // Ordered array of lesson IDs.
        register_post_meta('lms_course', '_simple_lms_order', array(
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
        register_post_meta('lms_course', '_lms_access_days', array(
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
        register_post_meta('lms_course', '_lms_certificate_form', array(
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
        register_post_meta('lms_course', '_lms_course_price', array(
            'type' => 'number',
            'description' => 'The price of the course.',
            'single' => true,
            'show_in_rest' => true,
            'default' => 0,
            'sanitize_callback' => 'floatval',
            'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
        ));

        // PMPro membership level IDs that grant access.
        register_post_meta('lms_course', '_lms_pmpro_levels', array(
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

        /* ── Lesson Meta ────────────────────────────────────────────── */

        // Lesson type: video | quiz.
        register_post_meta('lms_lesson', '_lms_lesson_type', array(
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
        register_post_meta('lms_lesson', '_lms_presto_video', array(
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
        register_post_meta('lms_lesson', '_lms_gravity_form', array(
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
        register_post_meta('lms_lesson', '_lms_quiz_timer', array(
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