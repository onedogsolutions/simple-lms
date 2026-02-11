<?php
/**
 * Meta box and admin page registration for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MetaBoxes
 *
 * Registers meta boxes on Course/Lesson edit screens and the Student Manager admin page.
 */
class MetaBoxes
{

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        add_action('add_meta_boxes', array(__CLASS__, 'register_meta_boxes'));
        add_action('admin_menu', array(__CLASS__, 'register_admin_pages'));
    }

    /* ───────────────────────────────────────────────────────────────────
     * Meta Boxes
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Register meta boxes for Course and Lesson CPTs.
     *
     * @return void
     */
    public static function register_meta_boxes()
    {

        // Course Editor — Lesson Sorter & Settings.
        add_meta_box(
            'slms_course_editor',
            __('Course Settings', 'simple-lms-bridge'),
            array(__CLASS__, 'render_react_root'),
            'lms_course',
            'normal',
            'high'
        );

        // Lesson Settings — Type, Video, Quiz, Timer.
        add_meta_box(
            'slms_lesson_settings',
            __('Lesson Settings', 'simple-lms-bridge'),
            array(__CLASS__, 'render_react_root'),
            'lms_lesson',
            'normal',
            'high'
        );
    }

    /**
     * Render the React mount point.
     *
     * Both meta boxes share the same root div; the React app decides
     * what to render based on slmsAdmin.postType.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public static function render_react_root($post)
    {
        echo '<div id="slms-admin-root"></div>';
    }

    /* ───────────────────────────────────────────────────────────────────
     * Admin Pages
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Register the Student Manager admin page.
     *
     * @return void
     */
    public static function register_admin_pages()
    {
        add_menu_page(
            __('Student Manager', 'simple-lms-bridge'),
            __('Students', 'simple-lms-bridge'),
            'edit_users',
            'slms-students',
            array(__CLASS__, 'render_students_page'),
            'dashicons-groups',
            27
        );
    }

    /**
     * Render the Student Manager page shell.
     *
     * @return void
     */
    public static function render_students_page()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Student Manager', 'simple-lms-bridge') . '</h1>';
        echo '<div id="slms-admin-root"></div>';
        echo '</div>';
    }
}