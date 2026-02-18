<?php
/**
 * REST API endpoints for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class REST
 *
 * Registers custom REST API routes under the simple-lms/v1 namespace.
 */
class REST
{

    const NAMESPACE = 'simple-lms/v1';

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        \add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /* ───────────────────────────────────────────────────────────────────
     * Route Registration
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Register all REST routes.
     *
     * @return void
     */
    public static function register_routes()
    {

        /* ── Student Progress ───────────────────────────────────────── */

        // GET /progress/{user_id}
        \register_rest_route(self::NAMESPACE , '/progress/(?P<user_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_progress'),
            'permission_callback' => function () {
            return \current_user_can('edit_users');
        },
            'args' => array(
                'user_id' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                return \is_numeric($param);
            },
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // POST /progress
        \register_rest_route(self::NAMESPACE , '/progress', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_progress'),
            'permission_callback' => function () {
            return \current_user_can('edit_users');
        },
            'args' => array(
                'user_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'course_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'lesson_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'completed' => array(
                    'required' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
            ),
        ));

        /* ── Gravity Forms ──────────────────────────────────────────── */

        \register_rest_route(self::NAMESPACE , '/forms', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_forms'),
            'permission_callback' => function () {
            return \current_user_can('edit_posts');
        },
        ));

        /* ── Presto Player Videos ───────────────────────────────────── */

        \register_rest_route(self::NAMESPACE , '/videos', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_videos'),
            'permission_callback' => function () {
            return \current_user_can('edit_posts');
        },
        ));

        /* ── Students List ──────────────────────────────────────────── */

        \register_rest_route(self::NAMESPACE , '/students', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_students'),
            'permission_callback' => function () {
            return \current_user_can('edit_users');
        },
            'args' => array(
                'search' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'page' => array(
                    'required' => false,
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ),
                'per_page' => array(
                    'required' => false,
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        /* ── Lessons List (lightweight) ─────────────────────────────── */

        \register_rest_route(self::NAMESPACE , '/lessons', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_lessons_list'),
            'permission_callback' => function () {
            return \current_user_can('edit_posts');
        },
        ));

        /* ── PMPro Membership Levels ─────────────────────────────────── */

        \register_rest_route(self::NAMESPACE , '/pmpro-levels', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_pmpro_levels'),
            'permission_callback' => function () {
            return \current_user_can('edit_posts');
        },
        ));

        /* ── Migration ──────────────────────────────────────────────── */

        \register_rest_route(self::NAMESPACE , '/migration/status', array(
            'methods' => 'GET',
            'callback' => function () {
                return \rest_ensure_response(array(
                    'pending' => Migration::get_pending_migration_count(),
                ));
            },
            'permission_callback' => function () {
                return \current_user_can('manage_options');
            },
        ));

        \register_rest_route(self::NAMESPACE , '/migration/migrate', array(
            'methods' => 'POST',
            'callback' => function ($request) {
                $limit = $request->get_param('limit') ? : 10;
                return \rest_ensure_response(Migration::migrate_batch($limit));
            },
            'permission_callback' => function () {
                return \current_user_can('manage_options');
            },
            'args' => array(
                'limit' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 10,
                ),
            ),
        ));

        /* ── Content Migration ───────────────────────────────────────── */

        \register_rest_route(self::NAMESPACE , '/migration/content/status', array(
            'methods' => 'GET',
            'callback' => function () {
                return \rest_ensure_response(array(
                    'pending' => Migration::get_pending_content_count(),
                ));
            },
            'permission_callback' => function () {
                return \current_user_can('manage_options');
            },
        ));

        \register_rest_route(self::NAMESPACE , '/migration/content/migrate', array(
            'methods' => 'POST',
            'callback' => function ($request) {
                $limit = $request->get_param('limit') ? : 5;
                return \rest_ensure_response(Migration::migrate_content_batch($limit));
            },
            'permission_callback' => function () {
                return \current_user_can('manage_options');
            },
            'args' => array(
                'limit' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 5,
                ),
            ),
        ));

        /* ── Relationships ──────────────────────────────────────────── */

        // GET /relationships/course/{id}/lessons
        \register_rest_route(self::NAMESPACE, '/relationships/course/(?P<id>\d+)/lessons', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_course_lessons'),
            'permission_callback' => function () {
                return \current_user_can('edit_posts');
            },
        ));

        // POST /relationships/course/{id}/lessons
        \register_rest_route(self::NAMESPACE, '/relationships/course/(?P<id>\d+)/lessons', array(
            'methods'  => 'POST',
            'callback' => array(__CLASS__, 'update_course_lessons'),
            'permission_callback' => function () {
                return \current_user_can('edit_posts');
            },
            'args' => array(
                'lesson_ids' => array(
                    'type' => 'array',
                    'items' => array('type' => 'integer'),
                    'required' => true,
                ),
            ),
        ));

        // GET /relationships/lesson/{id}/courses
        \register_rest_route(self::NAMESPACE, '/relationships/lesson/(?P<id>\d+)/courses', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_lesson_courses'),
            'permission_callback' => function () {
                return \current_user_can('edit_posts');
            },
        ));

        // GET /relationships/courses
        \register_rest_route(self::NAMESPACE, '/relationships/courses', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_courses_list'),
            'permission_callback' => function () {
                return \current_user_can('edit_posts');
            },
        ));

        /* ── Enrollments ────────────────────────────────────────────── */

        // GET /enrollments/user/{id}/courses
        \register_rest_route(self::NAMESPACE, '/enrollments/user/(?P<id>\d+)/courses', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_user_courses'),
            'permission_callback' => function () {
                return \current_user_can('edit_users');
            },
        ));

        // POST /enrollments/user/{id}/courses
        \register_rest_route(self::NAMESPACE, '/enrollments/user/(?P<id>\d+)/courses', array(
            'methods'  => 'POST',
            'callback' => array(__CLASS__, 'enroll_user'),
            'permission_callback' => function () {
                return \current_user_can('edit_users');
            },
            'args' => array(
                'course_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'source' => array(
                    'required' => false,
                    'default' => 'manual',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // DELETE /enrollments/user/{id}/courses/{course_id}
        \register_rest_route(self::NAMESPACE, '/enrollments/user/(?P<id>\d+)/courses/(?P<course_id>\d+)', array(
            'methods'  => 'DELETE',
            'callback' => array(__CLASS__, 'unenroll_user'),
            'permission_callback' => function () {
                return \current_user_can('edit_users');
            },
        ));

        // GET /enrollments/course/{id}/students
        \register_rest_route(self::NAMESPACE, '/enrollments/course/(?P<id>\d+)/students', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_course_students'),
            'permission_callback' => function () {
                return \current_user_can('edit_users');
            },
        ));
    }

    /* ───────────────────────────────────────────────────────────────────
     * Callbacks
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * GET /progress/{user_id}
     *
     * Returns the full _lms_progress array for a user.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public static function get_progress($request)
    {
        $user_id = $request->get_param('user_id');
        $progress = \get_user_meta($user_id, '_lms_progress', true);

        if (!\is_array($progress)) {
            $progress = array();
        }

        return \rest_ensure_response($progress);
    }

    /**
     * POST /progress
     *
     * Toggle lesson completion for a specific user/course/lesson.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public static function update_progress($request)
    {
        $user_id = $request->get_param('user_id');
        $course_id = $request->get_param('course_id');
        $lesson_id = $request->get_param('lesson_id');
        $completed = $request->get_param('completed');

        // Validate the user exists.
        if (!\get_userdata($user_id)) {
            return new \WP_Error('invalid_user', \__('User not found.', 'simple-lms-bridge'), array('status' => 404));
        }

        $progress = \get_user_meta($user_id, '_lms_progress', true);

        if (!\is_array($progress)) {
            $progress = array();
        }

        if ($completed) {
            if (!isset($progress[$course_id])) {
                $progress[$course_id] = array();
            }
            $progress[$course_id][$lesson_id] = \time();
        }
        else {
            unset($progress[$course_id][$lesson_id]);

            // Clean up empty course arrays.
            if (isset($progress[$course_id]) && empty($progress[$course_id])) {
                unset($progress[$course_id]);
            }
        }

        \update_user_meta($user_id, '_lms_progress', $progress);

        // Check for course completion.
        Certificates::check_course_completion($user_id, $course_id);


        return \rest_ensure_response(array(
            'success' => true,
            'progress' => $progress,
        ));
    }

    /**
     * GET /forms
     *
     * Return a list of Gravity Forms (id + title).
     *
     * @return \WP_REST_Response
     */
    public static function get_forms()
    {
        if (!\class_exists('GFAPI')) {
            return \rest_ensure_response(array());
        }

        $forms = \GFAPI::get_forms();
        $result = array();

        foreach ($forms as $form) {
            $result[] = array(
                'id' => (int)$form['id'],
                'title' => \sanitize_text_field($form['title']),
            );
        }

        return \rest_ensure_response($result);
    }

    /**
     * GET /videos
     *
     * Return a list of Presto Player videos (id + title).
     *
     * @return \WP_REST_Response
     */
    public static function get_videos()
    {
        $query = new \WP_Query(array(
            'post_type' => 'pp_video_block',
            'posts_per_page' => 200,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $result = array();

        foreach ($query->posts as $post) {
            $result[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
            );
        }

        wp_reset_postdata();

        return rest_ensure_response($result);
    }

    /**
     * GET /students
     *
     * Searchable list of users who have LMS progress data.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public static function get_students($request)
    {
        $search = $request->get_param('search');
        $page = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100);

        $args = array(
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'meta_key' => '_lms_progress',
            'meta_compare' => 'EXISTS',
        );

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $query = new \WP_User_Query($args);
        $users = $query->get_results();

        $result = array();

        foreach ($users as $user) {
            $progress = \get_user_meta($user->ID, '_lms_progress', true);
            if (!\is_array($progress)) {
                $progress = array();
            }

            $courses = array();
            foreach ($progress as $course_id => $lessons) {
                $course_post = \get_post($course_id);
                if (!$course_post) {
                    continue;
                }

                $total_lessons = \get_post_meta($course_id, '_simple_lms_order', true);
                $total_count = \is_array($total_lessons) ? \count($total_lessons) : 0;
                $done_count = \is_array($lessons) ? \count($lessons) : 0;

                $courses[] = array(
                    'course_id' => (int)$course_id,
                    'course_title' => $course_post->post_title,
                    'total' => $total_count,
                    'completed' => $done_count,
                    'lessons' => $lessons,
                );
            }

            $result[] = array(
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'courses' => $courses,
            );
        }

        return \rest_ensure_response(array(
            'students' => $result,
            'total' => (int)$query->get_total(),
            'pages' => (int)\ceil($query->get_total() / $per_page),
        ));
    }

    /**
     * GET /lessons
     *
     * Lightweight list of all published lessons (id + title) for the sorter.
     *
     * @return \WP_REST_Response
     */
    public static function get_lessons_list()
    {
        $query = new \WP_Query(array(
            'post_type' => 'lms_lesson',
            'posts_per_page' => 500,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $result = array();

        foreach ($query->posts as $post) {
            $result[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
            );
        }

        wp_reset_postdata();

        return rest_ensure_response($result);
    }

    /**
     * GET /pmpro-levels
     *
     * Return a list of PMPro membership levels (id + name).
     *
     * @return \WP_REST_Response
     */
    public static function get_pmpro_levels()
    {
        if (!\function_exists('pmpro_getAllLevels')) {
            return \rest_ensure_response(array());
        }

        $levels = pmpro_getAllLevels(false, true);
        $result = array();

        foreach ($levels as $level) {
            $expiration_days = 0;
            $exp_num = isset($level->expiration_number) ? (int)$level->expiration_number : 0;
            $exp_period = isset($level->expiration_period) ? $level->expiration_period : '';

            if ($exp_num > 0 && $exp_period) {
                switch ($exp_period) {
                    case 'Day':
                        $expiration_days = $exp_num;
                        break;
                    case 'Week':
                        $expiration_days = $exp_num * 7;
                        break;
                    case 'Month':
                        $expiration_days = $exp_num * 30;
                        break;
                    case 'Year':
                        $expiration_days = $exp_num * 365;
                        break;
                }
            }

            $result[] = array(
                'id' => (int)$level->id,
                'name' => \sanitize_text_field($level->name),
                'expiration_days' => $expiration_days,
            );
        }

        return \rest_ensure_response($result);
    }

    /* ───────────────────────────────────────────────────────────────────
     * Relationship Callbacks
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * GET /relationships/course/{id}/lessons
     */
    public static function get_course_lessons($request)
    {
        $id = $request->get_param('id');
        return \rest_ensure_response(Relationships::get_lessons_for_course($id));
    }

    /**
     * POST /relationships/course/{id}/lessons
     */
    public static function update_course_lessons($request)
    {
        $id = $request->get_param('id');
        $lesson_ids = $request->get_param('lesson_ids');

        Relationships::set_lessons_for_course($id, $lesson_ids);

        return \rest_ensure_response(array('success' => true));
    }

    /**
     * GET /relationships/lesson/{id}/courses
     */
    public static function get_lesson_courses($request)
    {
        $id = $request->get_param('id');
        return \rest_ensure_response(Relationships::get_courses_for_lesson($id));
    }

    /**
     * GET /relationships/courses
     */
    public static function get_courses_list()
    {
        $query = new \WP_Query(array(
            'post_type' => 'lms_course',
            'posts_per_page' => 500,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $result = array();
        foreach ($query->posts as $post) {
            $result[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
            );
        }
        \wp_reset_postdata();

        return \rest_ensure_response($result);
    }

    /* ─── Enrollment Callbacks ─────────────────────────────────────────── */

    /**
     * GET /enrollments/user/{id}/courses
     */
    public static function get_user_courses($request)
    {
        $id = $request->get_param('id');
        return \rest_ensure_response(Relationships::get_courses_for_user($id));
    }

    /**
     * POST /enrollments/user/{id}/courses
     */
    public static function enroll_user($request)
    {
        $user_id   = $request->get_param('id');
        $course_id = $request->get_param('course_id');
        $source    = $request->get_param('source') ? : 'manual';

        $success = Relationships::enroll_user($user_id, $course_id, $source);

        return \rest_ensure_response(array('success' => $success));
    }

    /**
     * DELETE /enrollments/user/{id}/courses/{course_id}
     */
    public static function unenroll_user($request)
    {
        $user_id   = $request->get_param('id');
        $course_id = $request->get_param('course_id');

        $success = Relationships::unenroll_user($user_id, $course_id);

        return \rest_ensure_response(array('success' => $success));
    }

    /**
     * GET /enrollments/course/{id}/students
     */
    public static function get_course_students($request)
    {
        $id = $request->get_param('id');
        return \rest_ensure_response(Relationships::get_users_for_course($id));
    }
}
