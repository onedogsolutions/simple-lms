<?php

/**
 * The REST API functionality of the plugin.
 *
 * @link       https://onedog.solutions
 * @since      3.0.0
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 */

/**
 * The REST API functionality of the plugin.
 *
 * Defines the plugin name, version, and registers the REST API routes.
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 * @author     Ryan Waterbury <ryan.waterbury@onedog.solutions>
 */
class SimpleLMS_REST_Controller extends WP_REST_Controller
{

    /**
     * The ID of this plugin.
     *
     * @since    3.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    3.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * The public class instance.
     *
     * @since    3.0.0
     * @access   private
     * @var      SimpleLMS_Public    $public    The public class instance.
     */
    private $public;

    /**
     * Initialize the class and set its properties.
     *
     * @since    3.0.0
     * @param      string    $plugin_name    The name of the plugin.
     * @param      string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->namespace = 'simplelms/v1';

        // We need an instance of SimpleLMS_Public to access helper methods
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-simple-lms-public.php';
        $this->public = new SimpleLMS_Public($plugin_name, $version);

    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/mark-completed', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'mark_completed'),
            'permission_callback' => array($this, 'permissions_check'),
        ));

        register_rest_route($this->namespace, '/mark-uncompleted', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'mark_uncompleted'),
            'permission_callback' => array($this, 'permissions_check'),
        ));

        register_rest_route($this->namespace, '/get-button', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'get_button'),
            'permission_callback' => array($this, 'permissions_check'),
        ));

        register_rest_route($this->namespace, '/get-graphs', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'get_graphs'),
            'permission_callback' => array($this, 'permissions_check'),
        ));

        register_rest_route($this->namespace, '/get-completable-list', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'get_completable_list'),
            'permission_callback' => array($this, 'permissions_check'),
        ));

        register_rest_route($this->namespace, '/get-content', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'get_content'),
            'permission_callback' => array($this, 'permissions_check'),
        ));

        register_rest_route($this->namespace, '/reset', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'reset'),
            'permission_callback' => array($this, 'permissions_check'),
        ));
    }

    /**
     * Check if a given request has access to create items.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function permissions_check($request)
    {
        return is_user_logged_in();
    }

    /**
     * Mark a button/lesson as completed
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function mark_completed($request)
    {
        $params = $request->get_json_params();
        $unique_button_id = isset($params['button']) ? $params['button'] : '';

        if (empty($unique_button_id)) {
            return new WP_Error('missing_param', 'Missing button parameter', array('status' => 400));
        }

        list($post_id, $button_id) = $this->extract_button_info($unique_button_id);

        // Save button if not exists (legacy logic)
        $posts = $this->public->get_completable_posts();
        if (isset($button_id) && (!isset($posts[$post_id]['buttons']) || !in_array($unique_button_id, $posts[$post_id]['buttons']))) {
            $post_meta = isset($posts[$post_id]) ? $posts[$post_id] : array();
            if (!isset($post_meta['buttons'])) {
                $post_meta['buttons'] = array();
            }
            $post_meta['buttons'][] = $unique_button_id;
            $posts[$post_id] = $post_meta;
            update_post_meta($post_id, 'simple-lms', json_encode($post_meta, JSON_UNESCAPED_UNICODE));
            wp_cache_set("posts", json_encode($posts, JSON_UNESCAPED_UNICODE), 'simple-lms');
        }

        // Mark as completed
        $user_activity = $this->public->get_user_activity();
        if (!isset($user_activity[$unique_button_id])) {
            $user_activity[$unique_button_id] = array();
        }

        $user_activity[$unique_button_id]['completed'] = date('Y-m-d H:i:s');
        if (!isset($user_activity[$unique_button_id]['first_seen'])) {
            $user_activity[$unique_button_id]['first_seen'] = date('Y-m-d H:i:s');
        }

        $this->public->set_user_activity($user_activity);

        // Prepare response
        $updates_to_sendback = array();

        // Add button update
        $button_atts = array('post_id' => $post_id, 'name' => $button_id);

        // Legacy: check for old/new text overrides
        if (isset($params['new_button_text'])) {
            $button_atts['text'] = $params['new_button_text'];
        }
        if (isset($params['old_button_text'])) {
            $button_atts['completed_text'] = $params['old_button_text'];
        }
        if (isset($params['class'])) {
            $button_atts['class'] = $params['class'];
        }
        if (isset($params['style'])) {
            $button_atts['style'] = $params['style'];
        }

        $updates_to_sendback['.wpc-button-' . $this->public->get_button_class($unique_button_id)] = $this->public->complete_button_cb($button_atts);

        // Helper fields for JS
        $updates_to_sendback['lesson-completed'] = $this->public->get_button_class($unique_button_id);

        return new WP_REST_Response($updates_to_sendback, 200);
    }

    /**
     * Mark a button/lesson as uncompleted
     */
    public function mark_uncompleted($request)
    {
        $params = $request->get_json_params();
        $unique_button_id = isset($params['button']) ? $params['button'] : '';

        if (empty($unique_button_id)) {
            return new WP_Error('missing_param', 'Missing button parameter', array('status' => 400));
        }

        list($post_id, $button_id) = $this->extract_button_info($unique_button_id);

        $user_activity = $this->public->get_user_activity();
        if (isset($user_activity[$unique_button_id])) {
            unset($user_activity[$unique_button_id]['completed']);
            $this->public->set_user_activity($user_activity);
        }

        $updates_to_sendback = array();

        $button_atts = array('post_id' => $post_id, 'name' => $button_id);
        if (isset($params['new_button_text'])) {
            $button_atts['text'] = $params['new_button_text'];
        }
        if (isset($params['old_button_text'])) {
            $button_atts['completed_text'] = $params['old_button_text'];
        }
        if (isset($params['class'])) {
            $button_atts['class'] = $params['class'];
        }
        if (isset($params['style'])) {
            $button_atts['style'] = $params['style'];
        }

        $updates_to_sendback['.wpc-button-' . $this->public->get_button_class($unique_button_id)] = $this->public->complete_button_cb($button_atts);
        $updates_to_sendback['lesson-incomplete'] = $this->public->get_button_class($unique_button_id);

        return new WP_REST_Response($updates_to_sendback, 200);
    }

    public function get_button($request)
    {
        $params = $request->get_json_params();
        $unique_button_id = isset($params['button_id']) ? $params['button_id'] : '';

        if (empty($unique_button_id)) {
            return new WP_REST_Response(array(), 200);
        }

        list($post_id, $button_id) = $this->extract_button_info($unique_button_id);

        $button_text = get_option($this->plugin_name . '_incomplete_text', 'Mark as complete');
        if (isset($params['new_button_text']) && !empty($params['new_button_text'])) {
            $button_text = sanitize_text_field($params['new_button_text']);
        }
        $completed_button_text = get_option($this->plugin_name . '_completed_text', 'COMPLETED');
        if (isset($params['old_button_text']) && !empty($params['old_button_text'])) {
            $completed_button_text = sanitize_text_field($params['old_button_text']);
        }

        $updates_to_sendback = array();
        $updates_to_sendback['.wpc-button-' . $this->public->get_button_class($unique_button_id)] = $this->public->complete_button_cb(array('post_id' => $post_id, 'name' => $button_id, 'text' => $button_text, 'completed_text' => $completed_button_text));

        // If redirect is set, send it back
        if (isset($params['redirect']) && !empty($params['redirect'])) {
            $updates_to_sendback['redirect'] = $params['redirect'];
        }

        return new WP_REST_Response($updates_to_sendback, 200);
    }

    public function get_graphs($request)
    {
        $updates_to_sendback = array();
        // Logic from get_graphs would go here, but it wasn't visible in the snippets I read.
        // Returning empty for now.
        return new WP_REST_Response($updates_to_sendback, 200);
    }

    public function get_completable_list($request)
    {
        $updates_to_sendback = array();
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $updates_to_sendback['timestamp'] = time();
            $updates_to_sendback['user'] = $user_id;

            $total_posts = $this->public->get_completable_posts();
            $user_completed = $this->public->get_user_completed();

            foreach ($total_posts as $post_id => $value) {
                $status = $this->public->post_completion_status($post_id, $user_id, $value, $user_completed);
                $updates_to_sendback[get_permalink($post_id)] = array(
                    'id' => $post_id,
                    'status' => $status,
                    'completed' => ($status == 'completed') ? true : false
                );
            }
        }

        return new WP_REST_Response($updates_to_sendback, 200);
    }

    public function get_content($request)
    {
        $params = $request->get_json_params();
        $type = isset($params['type']) ? $params['type'] : '';
        $unique_id = isset($params['unique_id']) ? $params['unique_id'] : '';

        $updates_to_sendback = array();

        if ($type == 'button') {
            $unique_button_id = $unique_id;
            $user_completed = $this->public->get_user_completed();
            $is_completed = isset($user_completed[$unique_button_id]);
            $updates_to_sendback[".wpc-content-button-" . $this->public->get_button_class($unique_button_id) . "-completed"] = ($is_completed) ? 'show' : 'hide';
            $updates_to_sendback[".wpc-content-button-" . $this->public->get_button_class($unique_button_id) . "-incomplete"] = (!$is_completed) ? 'show' : 'hide';
        }
        else if ($type == 'page') {
            $post_id = $unique_id;
            $is_completed = ($this->public->post_completion_status($post_id) == 'completed');
            $updates_to_sendback[".wpc-content-page-" . $post_id . "-completed"] = ($is_completed) ? 'show' : 'hide';
            $updates_to_sendback[".wpc-content-page-" . $post_id . "-incomplete"] = (!$is_completed) ? 'show' : 'hide';
        }
        else if ($type == 'course') {
            $course = $unique_id;
            $is_completed = ($this->public->course_completion_status($course) == 'completed');
            $course_class = $this->public->get_course_class(array('course' => $course));
            $updates_to_sendback[".wpc-content-course-" . $course_class . "-completed"] = ($is_completed) ? 'show' : 'hide';
            $updates_to_sendback[".wpc-content-course-" . $course_class . "-incomplete"] = (!$is_completed) ? 'show' : 'hide';
        }

        return new WP_REST_Response($updates_to_sendback, 200);
    }

    public function reset($request)
    {
        // Logic for reset
        return new WP_REST_Response(array('wpc-reset' => 'success'), 200);
    }

    // Helper from SimpleLMS_Common/Public
    private function extract_button_info($unique_button_id)
    {
        $parts = explode('-', $unique_button_id);
        $post_id = $parts[0];
        unset($parts[0]);
        $button_id = implode('-', $parts);
        return array($post_id, $button_id);
    }

}