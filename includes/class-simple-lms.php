<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://onedog.solutions
 * @since      1.0.0
 * @last       2.0.0
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 * @author     Ryan Waterbury <ryan.waterbury@onedog.solutions>
 */
class SimpleLMS
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SimpleLMS_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{

		$this->plugin_name = SIMPLE_LMS_PREFIX;
		$this->version = SIMPLE_LMS_VERSION;
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - SimpleLMS_Loader. Orchestrates the hooks of the plugin.
	 * - SimpleLMS_i18n. Defines internationalization functionality.
	 * - SimpleLMS_Admin. Defines all hooks for the admin area.
	 * - SimpleLMS_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-lms-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-lms-i18n.php';

		/**
		 * The class responsible for defining actions that are used in both admin and public sections
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-lms-common.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-simple-lms-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-simple-lms-public.php';

		$this->loader = new SimpleLMS_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the SimpleLMS_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new SimpleLMS_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

		$this->define_api_hooks();

	}

	/**
	 * Register all of the hooks related to the API functionality
	 * of the plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private function define_api_hooks()
	{
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-lms-rest-controller.php';
		$plugin_api = new SimpleLMS_REST_Controller($this->get_plugin_name(), $this->get_version());
		$this->loader->add_action('rest_api_init', $plugin_api, 'register_routes');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new SimpleLMS_Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		// adding settings page and metaboxes
		$this->loader->add_action('admin_menu', $plugin_admin, 'add_options_page');
		$this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
		$this->loader->add_action('add_meta_boxes', $plugin_admin, 'add_completable_metabox');
		$this->loader->add_action('save_post', $plugin_admin, 'save_completable', 10, 2);
		// adding bulk actions
		$this->loader->add_action('admin_footer-edit.php', $plugin_admin, 'add_bulk_actions');
		$this->loader->add_action('load-edit.php', $plugin_admin, 'save_bulk_completable');
		$this->loader->add_action('admin_notices', $plugin_admin, 'show_bulk_action_notice');
		// adding custom edit column + quick edit
		$this->loader->add_action('manage_pages_columns', $plugin_admin, 'add_custom_column_header');
		$this->loader->add_action('manage_posts_columns', $plugin_admin, 'add_custom_column_header');
		$this->loader->add_action('manage_pages_custom_column', $plugin_admin, 'add_custom_column_value', 10, 2);
		$this->loader->add_action('manage_posts_custom_column', $plugin_admin, 'add_custom_column_value', 10, 2);
		$this->loader->add_action('quick_edit_custom_box', $plugin_admin, 'add_custom_quick_edit', 10, 2);
		// adding custom completion column for users
		$this->loader->add_action('manage_users_columns', $plugin_admin, 'add_user_column_header');
		$this->loader->add_action('manage_users_custom_column', $plugin_admin, 'add_user_column_value', 11, 3);
		$this->loader->add_action('admin_post_delete_user_data', $plugin_admin, 'delete_user_data');
		$this->loader->add_action('admin_post_user_completion', $plugin_admin, 'admin_user_completion');

		// Validate license:
		$this->loader->add_action('admin_init', $plugin_admin, 'activate_license');

		// Show alert about being in development mode and having all of the features.
		if (!simple_lms_is_production()) {
			//update_option( 'dismissed-devmode', FALSE );
			$this->loader->add_action('admin_notices', $plugin_admin, 'show_development_mode_nag');
			$this->loader->add_action('wp_ajax_dismissed_devmode_notice_handler', $plugin_admin, 'dismiss_devmode_notice_handler');
		}
		else {
			// Check for license:
			//update_option( 'dismissed-license', FALSE );
			$this->loader->add_action('admin_notices', $plugin_admin, 'show_license_notice_nag');
			$this->loader->add_action('wp_ajax_dismissed_license_notice_handler', $plugin_admin, 'dismiss_license_notice_handler');
		}

		// PREMIUM:
		if (SIMPLE_LMS_IS_ACTIVATED) {
			// PREMIUM: add specific pages to show completion
			$this->loader->add_action('admin_menu', $plugin_admin, 'add_course_completion_page');
			$this->loader->add_action('admin_menu', $plugin_admin, 'add_post_completion_page');
			$this->loader->add_action('admin_menu', $plugin_admin, 'add_button_completion_page');
			$this->loader->add_action('admin_menu', $plugin_admin, 'add_user_completion_page');

			// PREMIUM: Add dashboard widget
			$this->loader->add_action('wp_dashboard_setup', $plugin_admin, 'add_dashboard_widget');

			// auto complete/suggest page/post title lookup
			$this->loader->add_action('wp_ajax_wpc_post_lookup', $plugin_admin, 'post_lookup');

			// report exporting...
			$this->loader->add_action('admin_init', $plugin_admin, 'export_button_completion_csv');
			$this->loader->add_action('admin_init', $plugin_admin, 'export_course_completion_csv');
			$this->loader->add_action('admin_init', $plugin_admin, 'export_user_completion_csv');
		}
		// allow admins to delete buttons:
		$this->loader->add_action('wp_ajax_wpc_delete_button', $plugin_admin, 'delete_button');
		// allow admins to reset users' button data:
		$this->loader->add_action('wp_ajax_wpc_reset_button', $plugin_admin, 'reset_button');

		// FIX OTHER PLUGINS:
		$this->loader->add_filter('manage_knowledgebase_posts_columns', $plugin_admin, 'add_custom_column_header');
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{
		//header('X-LiteSpeed-Cache-Control: no-cache');

		$plugin_public = new SimpleLMS_Public($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
		$this->loader->add_filter('script_loader_tag', $plugin_public, 'append_script_defer', 10, 2);

		$this->loader->add_filter('the_content', $plugin_public, 'append_completion_code', 1);

		// Custom ajax functions:
		$this->loader->add_action('admin_post_mark_completed', $plugin_public, 'mark_completed');
		$this->loader->add_action('wp_ajax_mark_completed', $plugin_public, 'mark_completed');
		$this->loader->add_action('wp_ajax_nopriv_mark_completed', $plugin_public, 'nopriv_mark_completed');
		$this->loader->add_action('admin_post_mark_uncompleted', $plugin_public, 'mark_uncompleted');
		$this->loader->add_action('wp_ajax_mark_uncompleted', $plugin_public, 'mark_uncompleted');
		$this->loader->add_action('wp_ajax_nopriv_mark_uncompleted', $plugin_public, 'nopriv_mark_uncompleted');
		$this->loader->add_action('wp_ajax_get_button', $plugin_public, 'get_button');
		$this->loader->add_action('wp_ajax_nopriv_get_button', $plugin_public, 'get_button');
		$this->loader->add_action('wp_ajax_get_graphs', $plugin_public, 'get_graphs');
		$this->loader->add_action('wp_ajax_nopriv_get_graphs', $plugin_public, 'get_graphs');
		$this->loader->add_action('wp_ajax_get_content', $plugin_public, 'get_content');
		$this->loader->add_action('wp_ajax_nopriv_get_content', $plugin_public, 'get_content');

		// Add shortcodes:
		$this->loader->add_shortcode('complete_button', $plugin_public, 'complete_button_cb');
		$this->loader->add_shortcode('wpc_complete_button', $plugin_public, 'complete_button_cb');
		$this->loader->add_shortcode('wpc_button', $plugin_public, 'complete_button_cb');
		$this->loader->add_shortcode('simple_lms_button', $plugin_public, 'complete_button_cb');

		// PREMIUM:
		if (SIMPLE_LMS_IS_ACTIVATED) {
			$this->loader->add_shortcode('progress_percentage', $plugin_public, 'progress_percentage_cb');
			$this->loader->add_shortcode('progress_in_percentage', $plugin_public, 'progress_percentage_cb');
			$this->loader->add_shortcode('progress_ratio', $plugin_public, 'progress_ratio_cb');
			$this->loader->add_shortcode('progress_in_ratio', $plugin_public, 'progress_ratio_cb');
			$this->loader->add_shortcode('progress_graph', $plugin_public, 'progress_radial_graph_cb');
			$this->loader->add_shortcode('progress_bar', $plugin_public, 'progress_bar_graph_cb');
			$this->loader->add_shortcode('wpc_progress_percentage', $plugin_public, 'progress_percentage_cb');
			$this->loader->add_shortcode('wpc_progress_in_percentage', $plugin_public, 'progress_percentage_cb');
			$this->loader->add_shortcode('wpc_progress_ratio', $plugin_public, 'progress_ratio_cb');
			$this->loader->add_shortcode('wpc_progress_in_ratio', $plugin_public, 'progress_ratio_cb');
			$this->loader->add_shortcode('wpc_progress_graph', $plugin_public, 'progress_radial_graph_cb');
			$this->loader->add_shortcode('wpc_progress_bar', $plugin_public, 'progress_bar_graph_cb');
			$this->loader->add_shortcode('simple_lms_progress_percentage', $plugin_public, 'progress_percentage_cb');
			$this->loader->add_shortcode('simple_lms_progress_in_percentage', $plugin_public, 'progress_percentage_cb');
			$this->loader->add_shortcode('simple_lms_progress_ratio', $plugin_public, 'progress_ratio_cb');
			$this->loader->add_shortcode('simple_lms_progress_in_ratio', $plugin_public, 'progress_ratio_cb');
			$this->loader->add_shortcode('simple_lms_progress_graph', $plugin_public, 'progress_radial_graph_cb');
			$this->loader->add_shortcode('simple_lms_progress_bar', $plugin_public, 'progress_bar_graph_cb');

			add_filter('widget_text', 'do_shortcode'); // allow text widgets to render shortcodes

			// conditional shortcodes
			$this->loader->add_shortcode('wpc_completed_content', $plugin_public, 'completed_content_cb');
			$this->loader->add_shortcode('wpc_incomplete_content', $plugin_public, 'incomplete_content_cb');
			$this->loader->add_shortcode('simple_lms_completed_content', $plugin_public, 'completed_content_cb');
			$this->loader->add_shortcode('simple_lms_incomplete_content', $plugin_public, 'incomplete_content_cb');
			$this->loader->add_shortcode('wpc_if_completed', $plugin_public, 'completed_content_cb');
			$this->loader->add_shortcode('wpc_if_incomplete', $plugin_public, 'incomplete_content_cb');
			$this->loader->add_shortcode('wpc_if_button_completed', $plugin_public, 'if_button_completed_cb');
			$this->loader->add_shortcode('wpc_if_button_incomplete', $plugin_public, 'if_button_incomplete_cb');
			$this->loader->add_shortcode('wpc_if_post_completed', $plugin_public, 'if_page_completed_cb');
			$this->loader->add_shortcode('wpc_if_post_incomplete', $plugin_public, 'if_page_incomplete_cb');
			$this->loader->add_shortcode('wpc_if_page_completed', $plugin_public, 'if_page_completed_cb');
			$this->loader->add_shortcode('wpc_if_page_incomplete', $plugin_public, 'if_page_incomplete_cb');
			$this->loader->add_shortcode('wpc_if_course_completed', $plugin_public, 'if_course_completed_cb');
			$this->loader->add_shortcode('wpc_if_course_incomplete', $plugin_public, 'if_course_incomplete_cb');

			// Mark links as completable and with their status if logged in:
			$this->loader->add_action('wp_ajax_get_completable_list', $plugin_public, 'get_completable_list');
			$this->loader->add_action('wp_ajax_nopriv_get_completable_list', $plugin_public, 'get_completable_list');

			// Peer Pressure shortcode:
			$this->loader->add_shortcode('wpc_peer_pressure', $plugin_public, 'peer_pressure_shortcode');

			// Reset shortcode:
			$this->loader->add_shortcode('wpc_reset', $plugin_public, 'reset_shortcode');
			$this->loader->add_action('admin_post_reset', $plugin_public, 'reset_account');
			$this->loader->add_action('admin_post_nopriv_reset', $plugin_public, 'reset_account');
			$this->loader->add_action('wp_ajax_reset', $plugin_public, 'reset_account');
			$this->loader->add_action('wp_ajax_nopriv_reset', $plugin_public, 'reset_account');

			// Add custom helper filters:
			$this->loader->add_filter('simple_lms_button_is_completed', $plugin_public, 'button_is_completed', 10, 2);
			$this->loader->add_filter('simple_lms_page_is_completed', $plugin_public, 'page_is_completed', 10, 1);
			$this->loader->add_filter('simple_lms_course_is_completed', $plugin_public, 'course_is_completed', 10, 1);

			// Shortode to build a list of course pages:
			$this->loader->add_shortcode('wpc_list_completable', $plugin_public, 'list_completable_shortcode');
			$this->loader->add_shortcode('wpc_list_pages', $plugin_public, 'list_completable_shortcode');
			//$this->loader->add_shortcode('wpc_list_buttons', $plugin_public, 'list_buttons_shortcode');
			$this->loader->add_filter('simple_lms_list_pages', $plugin_public, 'list_completable_filter', 10, 1);
			// Navigation shortcodes:
			$this->loader->add_shortcode('wpc_next_to_complete', $plugin_public, 'next_to_complete_shortcode');
			$this->loader->add_shortcode('wpc_has_next_to_complete', $plugin_public, 'next_to_complete_shortcode');
			$this->loader->add_shortcode('wpc_has_no_next_to_complete', $plugin_public, 'next_to_complete_shortcode');

			$this->loader->add_shortcode('wpc_last_completed', $plugin_public, 'last_completed_shortcode');
			$this->loader->add_shortcode('wpc_has_last_completed', $plugin_public, 'last_completed_shortcode');
			$this->loader->add_shortcode('wpc_has_no_last_completed', $plugin_public, 'last_completed_shortcode');

			$this->loader->add_shortcode('wpc_next_page', $plugin_public, 'next_page_shortcode');
			$this->loader->add_shortcode('wpc_has_next_page', $plugin_public, 'next_page_shortcode');
			$this->loader->add_shortcode('wpc_has_no_next_page', $plugin_public, 'next_page_shortcode');

			$this->loader->add_shortcode('wpc_previous_page', $plugin_public, 'previous_page_shortcode');
			$this->loader->add_shortcode('wpc_has_previous_page', $plugin_public, 'previous_page_shortcode');
			$this->loader->add_shortcode('wpc_has_no_previous_page', $plugin_public, 'previous_page_shortcode');
		}
		$this->loader->add_action('wp_head', $plugin_public, 'append_custom_styles');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    SimpleLMS_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}

	/** PUBLIC / STATIC METHODS */

	/**
	 * Retrieve test info...
	 *
	 * @since     2.9.0
	 * @return    array    An array of posts and their completion status.
	 */
	/*public static function test() {
	 $plugin_public = new SimpleLMS_Public( SIMPLE_LMS_PREFIX, SIMPLE_LMS_VERSION );
	 $pages_raw = $plugin_public->get_completable_list( false );
	 $pages_to_display = array();
	 foreach ( $pages_raw as $post_url => $post_data ) {
	 $post_data['url'] = $post_url;
	 $post_data['title'] = get_the_title($post_data['id']);
	 $pages_to_display[] = $post_data;
	 }
	 return $pages_to_display;
	 }*/

	/**
	 * Retrieve available SimpleLMS courses.
	 * Accepts: "posts" => false|true, "stats" => false|true, "user_stats" => false|true
	 *
	 * @since     2.9.0
	 * @last 			2.9.0
	 * @return    array    An array of courses and information about them in the following format:
	 *  [
	 *    {
	 *	    name: "My Course Name",
	 *			slug: "my-course-name",
	 *      posts: [{ title, url, id, buttons }],
	 *	    stats: { posts, buttons, users, started, completed }
	 *	  }
	 *	]
	 */
	public static function courses($atts = array())
	{
		$args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase
		$wpc = new SimpleLMS_Public(SIMPLE_LMS_PREFIX, SIMPLE_LMS_VERSION);

		$post_data = $wpc->get_completable_posts();
		$courses = array();
		$course_names = array_merge($wpc->get_course_names($post_data), array(get_bloginfo('name')));
		if (isset($args['user_stats']) && ($args['user_stats'] === true)) {
			$user_id = get_current_user_id();
			$user_activity = $wpc->get_user_activity($user_id);
		}

		foreach ($course_names as $course) {
			$course_data = array(
				'name' => $course,
				'slug' => $wpc->get_course_class(array('course' => $course)),
				'user_status' => $wpc->course_completion_status($course)
			);

			if (isset($args['user_stats']) && ($args['user_stats'] === true)) {
				// get user start and finish times...
				$user_started = null;
				$user_finished = true;
				$buttons = $wpc->get_course_buttons($course, $post_data);
				foreach ($buttons as $button_id) {
					if (isset($user_activity[$button_id])) {
						if (isset($user_activity[$button_id]['first_seen']) && (($user_started === null) || ($user_activity[$button_id]['first_seen'] < $user_started))) {
							$user_started = $user_activity[$button_id]['first_seen'];
						}
						if ($user_finished !== false && isset($user_activity[$button_id]['completed']) && $user_finished < $user_activity[$button_id]['completed']) {
							$user_finished = $user_activity[$button_id]['completed'];
						}
					}
					else {
						$user_finished = false;
					}
				}
				$course_data['user_started_at'] = $user_started;
				$course_data['user_finished_at'] = (in_array($user_finished, array(true, false))) ? null : $user_finished;
			}

			$course_post_ids = $wpc->get_course_post_ids($course, $post_data);
			$course_data['post_ids'] = $course_post_ids;

			// if dev wants posts...
			if (isset($args['posts']) && ($args['posts'] === true)) {
				$posts = array();
				foreach ($course_post_ids as $post_id) {
					$post = get_post($post_id);
					$posts[] = array(
						"id" => $post_id,
						"title" => $post->post_title,
						"url" => esc_url(get_permalink($post)),
						'type' => get_post_type($post),
						'status' => get_post_status($post),
						'buttons' => (isset($post_data[$post_id]['buttons'])) ? $post_data[$post_id]['buttons'] : array('' . $post_id)
					);
				}
				$course_data['posts'] = $posts;
			}

			// if dev wants stats...
			if (isset($args['stats']) && ($args['stats'] === true)) {
				$course_stats = $wpc->get_course_stats($post_data);
				$course_data['stats'] = $course_stats[$course];
			}
			$courses[] = $course_data;
		}

		return $courses;
	}

	/**
	 * Retrieve a user's SimpleLMS activity.
	 *
	 * @since     2.9.0
	 * @last 			2.9.0
	 * @return    The current user's SimpleLMS activity
	 */
	public static function user()
	{
		$wpc = new SimpleLMS_Public(SIMPLE_LMS_PREFIX, SIMPLE_LMS_VERSION);
		return $wpc->get_user_activity();
	}

	/**
	 * Retrieve all user's SimpleLMS activity.
	 *
	 * @since     2.9.0
	 * @last 			2.9.0
	 * @return    array    An array of all users and their SimpleLMS activity. User ID is the array key.
	 */
	public static function users()
	{
		global $wpdb;
		$wpc = new SimpleLMS_Public(SIMPLE_LMS_PREFIX, SIMPLE_LMS_VERSION);
		if ($users_json = wp_cache_get('users-all', 'simple-lms')) {
			$users_raw = json_decode($users_json, true);
		}
		else {
			// if not, fetch ALL users' SimpleLMS metadata from the database...
			$users_raw = $wpdb->get_results($wpdb->prepare("SELECT um.user_id, um.meta_value FROM {$wpdb->usermeta} um WHERE um.meta_key = %s", 'simple-lms'), ARRAY_A);
			// store it in cache for easy access...
			wp_cache_set("users-all", json_encode($users_raw, JSON_UNESCAPED_UNICODE), 'simple-lms');
		}

		$users = array();
		foreach ($users_raw as $key => $user) {
			$users[$user['user_id']] = json_decode($user['meta_value'], true);
		}

		return $users;
	}

	/**
	 * Retrieve the equivalent of [wpc_list_pages] shortcode
	 *
	 * @since     2.9.0
	 * @last 			2.9.0
	 * @return    array    An array of all users and their SimpleLMS activity. User ID is the array key.
	 */
	public static function pages($atts = array())
	{
		$args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase
		$wpc = new SimpleLMS_Public(SIMPLE_LMS_PREFIX, SIMPLE_LMS_VERSION);

		// need to send in a user's completion activity if we want to filter by this:
		if (isset($args['completed'])) {
			$args['user_activity'] = $wpc->get_user_activity();
		}

		$r = $wpc->build_post_criteria($args);

		$pages = (empty($r['include'])) ? array() : get_posts($r);

		if (isset($args['buttons']) && ($args['buttons'] === true)) {
			$post_data = $wpc->get_completable_posts();
			foreach ($pages as $key => $page) {
				$pages[$key]->buttons = (isset($post_data[$page->ID]['buttons'])) ? $post_data[$page->ID]['buttons'] : array('' . $page->ID);
			}
		}

		return $pages;
	}

}