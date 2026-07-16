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
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
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
        register_rest_route(self::NAMESPACE , '/progress/(?P<user_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_progress'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
            },
            'args' => array(
                'user_id' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // POST /progress
        register_rest_route(self::NAMESPACE , '/progress', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_progress'),
            'permission_callback' => function () {
                return is_user_logged_in();
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

        /* ── Forms ─────────────────────────────────────────────────── */

        register_rest_route(self::NAMESPACE , '/forms', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_forms'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        /* ── Presto Player Videos ───────────────────────────────────── */

        register_rest_route(self::NAMESPACE , '/videos', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_videos'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        /* ── Students List ──────────────────────────────────────────── */

        register_rest_route(self::NAMESPACE , '/students', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_students'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
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

        // POST /students/{id}/meta
        register_rest_route(self::NAMESPACE , '/students/(?P<id>\d+)/meta', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_student_meta'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
            },
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'billing_address_1' => array('sanitize_callback' => 'sanitize_text_field'),
                'billing_address_2' => array('sanitize_callback' => 'sanitize_text_field'),
                'billing_city' => array('sanitize_callback' => 'sanitize_text_field'),
                'billing_state' => array('sanitize_callback' => 'sanitize_text_field'),
                'billing_postcode' => array('sanitize_callback' => 'sanitize_text_field'),
                'billing_phone' => array('sanitize_callback' => 'sanitize_text_field'),
                'aalp_member' => array('sanitize_callback' => 'sanitize_text_field'),
                'registration_date' => array('sanitize_callback' => 'sanitize_text_field'),
                'license_number' => array('sanitize_callback' => 'sanitize_text_field'),
                'pro_exam_date' => array('sanitize_callback' => 'sanitize_text_field'),
                'pro_exam_status' => array('sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        /* ── Lessons List (lightweight) ─────────────────────────────── */

        register_rest_route(self::NAMESPACE , '/lessons', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_lessons_list'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        /* ── PMPro Membership Levels ─────────────────────────────────── */

        register_rest_route(self::NAMESPACE , '/pmpro-levels', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_pmpro_levels'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        /* ── Migration ──────────────────────────────────────────────── */

        // Status endpoint
        register_rest_route(self::NAMESPACE , '/migration/status', array(
            'methods' => 'GET',
            'callback' => function () {
                return rest_ensure_response(array(
                    'progress' => array('pending' => Migration::get_pending_migration_count()),
                    'content' => array('pending' => Migration::get_pending_content_count()),
                    'history' => array('pending' => Migration::get_pending_history_count()),
                    'pmpro' => array('pending' => Migration::get_pending_pmpro_count()),
                ));
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));

        // CPT Migration endpoint
        register_rest_route(self::NAMESPACE , '/migration/cpts', array(
            'methods' => 'POST',
            'callback' => function ($request) {
                $limit = $request->get_param('limit') ?? 5;
                return rest_ensure_response(Migration::migrate_cpt_batch($limit));
            },
            'args' => array(
                'limit' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 5,
                ),
            ),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));

        // Progress Migration endpoint
        register_rest_route(self::NAMESPACE , '/migration/progress', array(
            'methods' => 'POST',
            'callback' => function ($request) {
                $limit = $request->get_param('limit') ?? 10;
                return rest_ensure_response(Migration::migrate_progress_batch($limit));
            },
            'args' => array(
                'limit' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 10,
                ),
            ),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));

        // History Migration endpoint
        register_rest_route(self::NAMESPACE , '/migration/history', array(
            'methods' => 'POST',
            'callback' => function ($request) {
                $limit = $request->get_param('limit') ?? 10;
                $offset = $request->get_param('offset') ?? 0;
                return rest_ensure_response(Migration::migrate_history_batch($limit, $offset));
            },
            'args' => array(
                'limit' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 10,
                ),
                'offset' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 0,
                ),
            ),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));


        // PMPro Registration Sync endpoint (Phase 2)
        register_rest_route(self::NAMESPACE , '/migration/pmpro', array(
            'methods' => 'POST',
            'callback' => function ($request) {
                $limit = $request->get_param('limit') ?? 10;
                $offset = $request->get_param('offset') ?? 0;
                return rest_ensure_response(Migration::migrate_pmpro_batch($limit, $offset));
            },
            'args' => array(
                'limit' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 10,
                ),
                'offset' => array(
                    'sanitize_callback' => 'absint',
                    'default' => 0,
                ),
            ),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));

        // Reset Phase 2 migration meta so entries can be re-processed.
        register_rest_route(self::NAMESPACE , '/migration/pmpro/reset', array(
            'methods' => 'POST',
            'callback' => function () {
                return rest_ensure_response(Migration::reset_pmpro_migration());
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));

        /* ── Debug Log ──────────────────────────────────────────────── */

        register_rest_route(self::NAMESPACE , '/debug-log', array(
            'methods' => 'GET',
            'callback' => function () {
                return rest_ensure_response(array(
                    'log' => Migration::read_log(500),
                ));
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));

        register_rest_route(self::NAMESPACE , '/debug-log', array(
            'methods' => 'DELETE',
            'callback' => function () {
                Migration::clear_log();
                return rest_ensure_response(array('success' => true));
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));

        register_rest_route(self::NAMESPACE , '/course-history/repair-form-ids', array(
            'methods' => 'POST',
            'callback' => function () {
                $result = \SimpleLMS\CourseHistory::repair_form_ids();
                return rest_ensure_response($result);
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));

        /* ── Relationships ──────────────────────────────────────────── */

        // GET /relationships/course/{id}/lessons
        register_rest_route(self::NAMESPACE , '/relationships/course/(?P<id>\d+)/lessons', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_course_lessons'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        // POST /relationships/course/{id}/lessons
        register_rest_route(self::NAMESPACE , '/relationships/course/(?P<id>\d+)/lessons', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_course_lessons'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
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
        register_rest_route(self::NAMESPACE , '/relationships/lesson/(?P<id>\d+)/courses', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_lesson_courses'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        // GET /relationships/courses
        register_rest_route(self::NAMESPACE , '/relationships/courses', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_courses_list'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        /* ── Enrollments ────────────────────────────────────────────── */

        // GET /enrollments/user/{id}/courses
        register_rest_route(self::NAMESPACE , '/enrollments/user/(?P<id>\d+)/courses', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_user_courses'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
            },
        ));

        // POST /enrollments/user/{id}/courses
        register_rest_route(self::NAMESPACE , '/enrollments/user/(?P<id>\d+)/courses', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'enroll_user'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
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
        register_rest_route(self::NAMESPACE , '/enrollments/user/(?P<id>\d+)/courses/(?P<course_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'unenroll_user'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
            },
        ));

        // GET /enrollments/course/{id}/students
        register_rest_route(self::NAMESPACE , '/enrollments/course/(?P<id>\d+)/students', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_course_students'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
            },
        ));

        // GET /student/{id}/history
        register_rest_route(self::NAMESPACE , '/student/(?P<id>\d+)/history', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_student_history'),
            'permission_callback' => function () {
                return current_user_can('edit_users');
            },
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        /* ── Analytics (owner-facing, manage_options) ───────────────── */

        register_rest_route(self::NAMESPACE , '/analytics/overview', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_analytics_overview'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => array(
                'from' => array('required' => false, 'sanitize_callback' => 'sanitize_text_field'),
                'to'   => array('required' => false, 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/analytics/course/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_analytics_course'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => array(
                'id' => array('required' => true, 'sanitize_callback' => 'absint'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/analytics/at-risk', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_analytics_at_risk'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => array(
                'days' => array('required' => false, 'default' => 30, 'sanitize_callback' => 'absint'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/analytics/extend-access', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'extend_access'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => array(
                'user_id'   => array('required' => true, 'sanitize_callback' => 'absint'),
                'course_id' => array('required' => true, 'sanitize_callback' => 'absint'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/analytics/courses', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_analytics_courses'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
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
        $progress = get_user_meta($user_id, '_lms_progress', true);

        if (!is_array($progress)) {
            $progress = array();
        }

        return rest_ensure_response($progress);
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

        // Non-privileged users may only update their own progress. Ignore the
        // supplied user_id and force the acting user when they lack edit_users.
        if (!current_user_can('edit_users')) {
            $user_id = get_current_user_id();
        }

        // Validate the user exists.
        if (!get_userdata($user_id)) {
            return new \WP_Error('invalid_user', __('User not found.', 'simple-lms-bridge'), array('status' => 404));
        }

        // Validate the lesson belongs to the course.
        $lessons = Relationships::get_lessons_for_course($course_id);
        $course_lesson_ids = array_map('absint', wp_list_pluck($lessons, 'id'));
        if (!in_array((int) $lesson_id, $course_lesson_ids, true)) {
            return new \WP_Error('invalid_lesson', __('Lesson does not belong to this course.', 'simple-lms-bridge'), array('status' => 400));
        }

        // Validate the user is enrolled in the course before writing progress.
        if (!Relationships::is_user_enrolled($user_id, $course_id)) {
            return new \WP_Error('not_enrolled', __('User is not enrolled in this course.', 'simple-lms-bridge'), array('status' => 403));
        }

        // Route through the shared completion path (also fires certificate
        // automation + completion detection).
        $progress = Access::set_lesson_progress($user_id, $course_id, $lesson_id, $completed);

        $response = array(
            'success'  => true,
            'progress' => $progress,
        );

        // On completion of the final lesson, surface the configured redirect URL
        // so the frontend can send the student onward (e.g. to a certificate).
        //
        // NOTE: certificate automation (fired inside set_lesson_progress) may
        // de-enroll the student and wipe _lms_progress, so we cannot re-read
        // progress here. Instead we key off _lms_completed_at, which is set when
        // the course completes and is NOT cleared by de-enrollment.
        if ($completed) {
            $completed_map = get_user_meta($user_id, '_lms_completed_at', true);
            if (is_array($completed_map) && isset($completed_map[$course_id])) {
                $response['course_complete'] = true;

                $redirect = get_post_meta($course_id, '_lms_completion_redirect', true);
                if (!empty($redirect)) {
                    $response['redirect'] = esc_url_raw($redirect);
                }
            }
        }

        return rest_ensure_response($response);
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
        if (!class_exists('GFAPI')) {
            return rest_ensure_response(array());
        }

        $forms = \GFAPI::get_forms();
        $result = array();

        foreach ($forms as $form) {
            $result[] = array(
                'id' => (int) $form['id'],
                'title' => sanitize_text_field($form['title']),
            );
        }

        return rest_ensure_response($result);
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
        );

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $query = new \WP_User_Query($args);
        $users = $query->get_results();

        $result = array();

        foreach ($users as $user) {
            $progress = get_user_meta($user->ID, '_lms_progress', true);
            if (!is_array($progress)) {
                $progress = array();
            }

            $course_completion = get_user_meta($user->ID, '_lms_completed_at', true);
            if (!is_array($course_completion)) {
                $course_completion = array();
            }

            // Source courses from enrollment table, overlay progress data.
            $enrolled_courses = Relationships::get_courses_for_user($user->ID);
            $courses = array();

            foreach ($enrolled_courses as $enrollment) {
                $course_id = (int) $enrollment->id;
                $course_post = get_post($course_id);
                if (!$course_post) {
                    continue;
                }

                $course_progress = isset($progress[$course_id]) ? $progress[$course_id] : array();

                $total_lessons = get_post_meta($course_id, '_simple_lms_order', true);
                if (!is_array($total_lessons)) {
                    $total_lessons = array();
                }
                $total_count = count($total_lessons);
                $done_count = is_array($course_progress) ? count($course_progress) : 0;

                $enriched_lessons = array();
                foreach ($total_lessons as $lesson_id) {
                    $lesson_post = get_post($lesson_id);
                    $is_completed = isset($course_progress[$lesson_id]);
                    $completed_at = $is_completed ? $course_progress[$lesson_id] : null;
                    $enriched_lessons[$lesson_id] = array(
                        'title' => $lesson_post ? $lesson_post->post_title : 'Lesson #' . $lesson_id,
                        'completed' => $is_completed,
                        'completed_at' => $completed_at,
                    );
                }

                // Also include any lessons that are marked completed but might not be in the current _simple_lms_order array.
                if (is_array($course_progress)) {
                    foreach ($course_progress as $lesson_id => $lesson_data) {
                        if (!isset($enriched_lessons[$lesson_id])) {
                            $lesson_post = get_post($lesson_id);
                            $enriched_lessons[$lesson_id] = array(
                                'title' => $lesson_post ? $lesson_post->post_title : 'Lesson #' . $lesson_id,
                                'completed' => true,
                                'completed_at' => $lesson_data,
                            );
                        }
                    }
                }

                $courses[] = array(
                    'course_id' => $course_id,
                    'course_title' => $course_post->post_title,
                    'total' => $total_count,
                    'completed' => $done_count,
                    'completed_at' => isset($course_completion[$course_id]) ? $course_completion[$course_id] : null,
                    'lessons' => $enriched_lessons,
                );
            }

            // Get user meta for profile tab
            $user_meta = array(
                'billing_address_1' => get_user_meta($user->ID, 'billing_address_1', true) ?: '',
                'billing_address_2' => get_user_meta($user->ID, 'billing_address_2', true) ?: '',
                'billing_city' => get_user_meta($user->ID, 'billing_city', true) ?: '',
                'billing_state' => get_user_meta($user->ID, 'billing_state', true) ?: '',
                'billing_postcode' => get_user_meta($user->ID, 'billing_postcode', true) ?: '',
                'billing_phone' => get_user_meta($user->ID, 'billing_phone', true) ?: '',
                'aalp_member' => get_user_meta($user->ID, 'aalp_member', true) ?: '',
                'registration_date' => get_user_meta($user->ID, 'registration_date', true) ?: '',
                'license_number' => get_user_meta($user->ID, 'license_number', true) ?: '',
                'pro_exam_date' => get_user_meta($user->ID, 'pro_exam_date', true) ?: '',
                'pro_exam_status' => get_user_meta($user->ID, 'pro_exam_status', true) ?: '',
            );

            $result[] = array(
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'meta' => $user_meta,
                'courses' => $courses,
            );
        }

        return rest_ensure_response(array(
            'students' => $result,
            'total' => (int) $query->get_total(),
            'pages' => (int) ceil($query->get_total() / $per_page),
        ));
    }

    /**
     * POST /students/{id}/meta
     *
     * Update standard user meta.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public static function update_student_meta($request)
    {
        $id = $request->get_param('id');

        // Validate the user exists.
        if (!get_userdata($id)) {
            return new \WP_Error('invalid_user', __('User not found.', 'simple-lms-bridge'), array('status' => 404));
        }

        $meta_fields = array(
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_phone',
            'aalp_member',
            'registration_date',
            'license_number',
            'pro_exam_date',
            'pro_exam_status'
        );

        foreach ($meta_fields as $field) {
            if ($request->has_param($field)) {
                update_user_meta($id, $field, sanitize_text_field($request->get_param($field)));
            }
        }

        return rest_ensure_response(array('success' => true));
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
            'post_type' => 'slms_lesson',
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
        if (!function_exists('pmpro_getAllLevels')) {
            return rest_ensure_response(array());
        }

        $levels = pmpro_getAllLevels(false, true);
        $result = array();

        foreach ($levels as $level) {
            $expiration_days = 0;
            $exp_num = isset($level->expiration_number) ? (int) $level->expiration_number : 0;
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
                'id' => (int) $level->id,
                'name' => sanitize_text_field($level->name),
                'expiration_days' => $expiration_days,
            );
        }

        return rest_ensure_response($result);
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
        return rest_ensure_response(Relationships::get_lessons_for_course($id));
    }

    /**
     * POST /relationships/course/{id}/lessons
     */
    public static function update_course_lessons($request)
    {
        $id = $request->get_param('id');
        $lesson_ids = $request->get_param('lesson_ids');

        Relationships::set_lessons_for_course($id, $lesson_ids);

        return rest_ensure_response(array('success' => true));
    }

    /**
     * GET /relationships/lesson/{id}/courses
     */
    public static function get_lesson_courses($request)
    {
        $id = $request->get_param('id');
        return rest_ensure_response(Relationships::get_courses_for_lesson($id));
    }

    /**
     * GET /relationships/courses
     */
    public static function get_courses_list()
    {
        $query = new \WP_Query(array(
            'post_type' => 'slms_course',
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

    /* ─── Enrollment Callbacks ─────────────────────────────────────────── */

    /**
     * GET /enrollments/user/{id}/courses
     */
    public static function get_user_courses($request)
    {
        $id = $request->get_param('id');
        return rest_ensure_response(Relationships::get_courses_for_user($id));
    }

    /**
     * POST /enrollments/user/{id}/courses
     */
    public static function enroll_user($request)
    {
        $user_id = $request->get_param('id');
        $course_id = $request->get_param('course_id');
        $source = $request->get_param('source') ?? 'manual';

        $success = Relationships::enroll_user($user_id, $course_id, $source);

        return rest_ensure_response(array('success' => $success));
    }

    /**
     * DELETE /enrollments/user/{id}/courses/{course_id}
     */
    public static function unenroll_user($request)
    {
        $user_id = $request->get_param('id');
        $course_id = $request->get_param('course_id');

        $success = Relationships::unenroll_user($user_id, $course_id);

        return rest_ensure_response(array('success' => $success));
    }

    /**
     * GET /enrollments/course/{id}/students
     */
    public static function get_course_students($request)
    {
        $id = $request->get_param('id');
        return rest_ensure_response(Relationships::get_users_for_course($id));
    }

    /**
     * GET /student/{id}/history
     *
     * Returns compliance history from the custom table first.
     * Falls back to a live GFAPI query for users not yet migrated.
     */
    public static function get_student_history($request)
    {
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);

        if (!$user) {
            return new \WP_Error('invalid_user', __('User not found.', 'simple-lms-bridge'), array('status' => 404));
        }

        // 1. Query the permanent compliance table first.
        $records = CourseHistory::get_for_user($user_id);

        if (!empty($records)) {
            $history = array();
            foreach ($records as $row) {
                $history[] = array(
                    'id' => (int) $row->id,
                    'course_name' => self::resolve_course_name($row->course_name),
                    'date' => $row->completed_date,
                    'gf_entry_id' => (int) $row->gf_entry_id,
                    'pdf_url' => self::resolve_pdf_url(
                        (int) $row->gf_entry_id,
                        (int) $row->form_id,
                        (string) $row->course_name,
                        $user_id
                    ),
                );
            }
            return rest_ensure_response($history);
        }

        // 2. Fallback: live GFAPI query for users who haven't been migrated yet.
        if (!class_exists('GFAPI')) {
            return rest_ensure_response(array());
        }

        $forms = \GFAPI::get_forms();
        $cert_form_ids = array();
        foreach ($forms as $form) {
            $form_title = $form['title'] ?? '';
            if (stripos($form_title, 'Certificate') !== false) {
                $cert_form_ids[] = $form['id'];
            }
        }

        $form_ids = !empty($cert_form_ids) ? $cert_form_ids : 0;

        $search_criteria = array(
            'status' => 'active',
            'field_filters' => array(
                'mode' => 'any',
                array('key' => 'created_by', 'value' => $user_id),
            ),
        );
        $entries = \GFAPI::get_entries($form_ids, $search_criteria);

        $search_criteria_email = array(
            'status' => 'active',
            'field_filters' => array(
                'mode' => 'any',
                array('value' => $user->user_email),
            ),
        );
        $entries_by_email = \GFAPI::get_entries($form_ids, $search_criteria_email);

        $all_entries = array_merge((array) $entries, (array) $entries_by_email);
        $unique_entries = array();
        foreach ($all_entries as $entry) {
            if (isset($entry['id']) && !isset($unique_entries[$entry['id']])) {
                $unique_entries[$entry['id']] = $entry;
            }
        }

        $history = array();

        foreach ($unique_entries as $entry) {
            $course_name = __('Unknown Course', 'simple-lms-bridge');
            $form = \GFAPI::get_form($entry['form_id']);

            if ($form && isset($form['fields'])) {
                foreach ($form['fields'] as $field) {
                    $label = $field->label ?? '';
                    if (stripos($label, 'Course') !== false) {
                        $value = rgar($entry, (string) $field->id);
                        if (!empty($value)) {
                            $course_name = $value;
                            break;
                        }
                    }
                }
            }

            if ($course_name === __('Unknown Course', 'simple-lms-bridge') && $form) {
                $course_name = str_ireplace('Certificate', '', $form['title'] ?? '');
                $course_name = trim($course_name, ' -');
            }

            $history[] = array(
                'id' => $entry['id'],
                'course_name' => self::resolve_course_name($course_name),
                'date' => $entry['date_created'] ?? '',
                'pdf_url' => self::resolve_pdf_url(
                    (int) $entry['id'],
                    (int) $entry['form_id'],
                    (string) $course_name,
                    $user_id
                ),
            );
        }

        return rest_ensure_response($history);
    }

    /**
     * Resolve a course name that may be a URL to its post title.
     *
     * @param string $name The course name (may be a URL or post title).
     * @return string Resolved course title.
     */
    private static function resolve_course_name($name)
    {
        if (empty($name)) {
            return __('Unknown Class', 'simple-lms-bridge');
        }

        // If the name looks like a URL, try to resolve it to a post title.
        if (filter_var($name, FILTER_VALIDATE_URL) || strpos($name ?? '', 'http') === 0 || strpos($name ?? '', '/') === 0) {
            $post_id = \url_to_postid($name);
            if ($post_id) {
                $post = get_post($post_id);
                if ($post) {
                    return $post->post_title;
                }
            }

            // Fallback: extract last path segment and clean it up.
            $path = \wp_parse_url($name, PHP_URL_PATH);
            if ($path && is_string($path)) {
                $slug = basename(rtrim($path, '/'));
                if ($slug) {
                    // Try to find a post by slug.
                    $by_slug = get_page_by_path($slug, OBJECT, array('slms_course', 'slms_lesson', 'page', 'post'));
                    if ($by_slug) {
                        return $by_slug->post_title;
                    }
                    // Clean up the slug as a readable name.
                    return ucwords(str_replace(array('-', '_'), ' ', $slug));
                }
            }
        }

        return $name;
    }

    /**
     * Resolve a GravityPDF download URL for a history row.
     *
     * Two-stage: try GPDFAPI::get_entry_pdfs() first (evaluates conditional logic via
     * GF entry field values). Falls back to get_form_pdfs() + manual conditional logic
     * evaluation using billing_state user meta (field 6) and raw course URL slug (field 18).
     *
     * @param int    $gf_entry_id   GF entry ID stored in the history row.
     * @param int    $pdf_form_id   GF form ID stored in the history row.
     * @param string $raw_course    Raw course_name value (URL or plain string).
     * @param int    $user_id       Student user ID (for billing_state lookup).
     * @return string Download URL or empty string.
     */
    private static function resolve_pdf_url(int $gf_entry_id, int $pdf_form_id, string $raw_course, int $user_id): string
    {
        if (!$gf_entry_id || !$pdf_form_id || !class_exists('GPDFAPI')) {
            return '';
        }

        // Stage 1: get_entry_pdfs() evaluates conditional logic against the GF entry's fields.
        $entry_pdfs = \GPDFAPI::get_entry_pdfs($gf_entry_id);
        if (!is_wp_error($entry_pdfs) && !empty($entry_pdfs)) {
            $hash_id = array_key_first($entry_pdfs);
            return home_url('/pdf/' . $hash_id . '/' . $gf_entry_id . '/download/');
        }

        // Stage 2: Manual evaluation using billing_state + course slug from stored data.
        $all_pdfs = \GPDFAPI::get_form_pdfs($pdf_form_id);
        if (is_wp_error($all_pdfs) || empty($all_pdfs)) {
            return '';
        }

        $student_state = (string) get_user_meta($user_id, 'billing_state', true);

        foreach ($all_pdfs as $id => $pdf_config) {
            if (empty($pdf_config['active'])) {
                continue;
            }

            $logic = !empty($pdf_config['conditionalLogic']) ? $pdf_config['conditionalLogic'] : array();

            if (empty($logic['rules'])) {
                return home_url('/pdf/' . $id . '/' . $gf_entry_id . '/download/');
            }

            $logic_type   = isset($logic['logicType']) ? $logic['logicType'] : 'all';
            $rule_results = array();

            foreach ($logic['rules'] as $rule) {
                $fid = (string)(isset($rule['fieldId']) ? $rule['fieldId'] : '');
                $op  = isset($rule['operator']) ? $rule['operator'] : 'is';
                $val = isset($rule['value']) ? $rule['value'] : '';

                if ('6' === $fid) {
                    $match = ('is' === $op)
                        ? ($student_state === $val)
                        : ($student_state !== $val);
                } elseif ('18' === $fid) {
                    $cond_path  = (string) parse_url($val, PHP_URL_PATH);
                    $cond_parts = array_values(array_filter(explode('/', trim($cond_path, '/'))));
                    $cidx        = array_search('course', $cond_parts, true);
                    // Use segment after "course/" if present; fall back to last segment.
                    $course_slug = ($cidx !== false && isset($cond_parts[$cidx + 1]))
                        ? $cond_parts[$cidx + 1]
                        : (!empty($cond_parts) ? end($cond_parts) : '');

                    if ($course_slug !== '') {
                        // Case-insensitive match against the stored course value.
                        $match = stripos($raw_course, $course_slug) !== false;
                        // Also compare via a title-slug conversion (handles plain-text course_name).
                        if (!$match) {
                            $title_slug = sanitize_title($raw_course);
                            $match = $title_slug !== '' && (
                                stripos($title_slug, $course_slug) !== false ||
                                stripos($course_slug, $title_slug) !== false
                            );
                        }
                    } else {
                        $match = false;
                    }

                    if ('isnot' === $op) {
                        $match = !$match;
                    }
                } else {
                    continue;
                }

                $rule_results[] = $match;
            }

            if (empty($rule_results)) {
                continue;
            }

            $passes = ('any' === $logic_type)
                ? in_array(true, $rule_results, true)
                : !in_array(false, $rule_results, true);

            if ($passes) {
                return home_url('/pdf/' . $id . '/' . $gf_entry_id . '/download/');
            }
        }

        return '';
    }

    /* ───────────────────────────────────────────────────────────────────
     * Analytics callbacks
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * GET /analytics/overview
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public static function get_analytics_overview($request)
    {
        return rest_ensure_response(Analytics::overview(
            $request->get_param('from'),
            $request->get_param('to')
        ));
    }

    /**
     * GET /analytics/course/{id} — funnel + drop-off + time-to-complete.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public static function get_analytics_course($request)
    {
        $course_id = $request->get_param('id');

        return rest_ensure_response(array(
            'funnel'           => Analytics::course_funnel($course_id),
            'dropoff'          => Analytics::lesson_dropoff($course_id),
            'time_to_complete' => Analytics::time_to_complete($course_id),
        ));
    }

    /**
     * GET /analytics/at-risk
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public static function get_analytics_at_risk($request)
    {
        $days = $request->get_param('days');
        return rest_ensure_response(array(
            'days'     => (int) $days,
            'students' => Analytics::at_risk($days),
        ));
    }

    /**
     * GET /analytics/courses — published courses for the drill-down selector.
     *
     * @return \WP_REST_Response
     */
    public static function get_analytics_courses()
    {
        $query = new \WP_Query(array(
            'post_type'      => 'slms_course',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        $result = array();
        foreach ($query->posts as $id) {
            $result[] = array('id' => (int) $id, 'title' => get_the_title($id));
        }

        return rest_ensure_response($result);
    }

    /**
     * POST /analytics/extend-access
     *
     * Resets a user's enrollment clock for a course so access-expiry restarts.
     * Writes both the `_lms_enrolled_at` meta and the enrollment-table row.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public static function extend_access($request)
    {
        global $wpdb;

        $user_id   = $request->get_param('user_id');
        $course_id = $request->get_param('course_id');

        if (!get_userdata($user_id)) {
            return new \WP_Error('invalid_user', __('User not found.', 'simple-lms-bridge'), array('status' => 404));
        }

        $now = time();

        // Reset the enrollment-timestamp meta used by the expiration cron.
        $enrolled = get_user_meta($user_id, '_lms_enrolled_at', true);
        if (!is_array($enrolled)) {
            $enrolled = array();
        }
        $enrolled[$course_id] = $now;
        update_user_meta($user_id, '_lms_enrolled_at', $enrolled);

        // Keep the enrollment-table row in sync.
        $wpdb->update(
            $wpdb->prefix . 'slms_user_course',
            array('enrolled_at' => current_time('mysql')),
            array('user_id' => $user_id, 'course_id' => $course_id),
            array('%s'),
            array('%d', '%d')
        );

        return rest_ensure_response(array(
            'success'     => true,
            'enrolled_at' => gmdate('c', $now),
        ));
    }

    /**
     * Handle analytics CSV export via admin-post.php.
     *
     * Mirrors handle_log_download(): validates capability + nonce, streams a CSV.
     *
     * @return void
     */
    public static function handle_analytics_export()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }

        check_admin_referer('slms_analytics_export');

        $report = isset($_GET['report']) ? sanitize_key(wp_unslash($_GET['report'])) : 'overview';
        $args = array(
            'course_id' => isset($_GET['course_id']) ? absint($_GET['course_id']) : 0,
            'days'      => isset($_GET['days']) ? absint($_GET['days']) : 30,
            'from'      => isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : null,
            'to'        => isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : null,
        );

        $csv = Analytics::build_csv($report, $args);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . (string) $csv['filename'] . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        if (false === $out) {
            wp_die('Unable to open output stream.', 500);
        }

        if (!empty($csv['header'])) {
            fputcsv($out, (array) $csv['header']);
        }
        foreach ((array) $csv['rows'] as $row) {
            fputcsv($out, (array) $row);
        }
        fclose($out);
        exit;
    }

    /**
     * Handle log download via admin-post.php.
     *
     * @return void
     */
    public static function handle_log_download()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }

        check_admin_referer('slms_download_log');

        $log_file = \SimpleLMS\Migration::get_log_file_path();

        if (!file_exists($log_file) || filesize($log_file) === 0) {
            wp_die('No log file found.', 404);
        }

        $filename = 'slms-migration-' . gmdate('Y-m-d_H-i-s') . '.log';

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($log_file));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($log_file);
        exit;
    }
}