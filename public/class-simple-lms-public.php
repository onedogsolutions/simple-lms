<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://simplelms.co
 * @since      1.0.0
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/public
 * @author     Zack Gilbert <zack@zackgilbert.com>
 */
class SimpleLMS_Public extends SimpleLMS_Common {

  /**
   * The ID of this plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string    $plugin_name    The ID of this plugin.
   */
  protected $plugin_name;

  /**
   * The version of this plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string    $version    The current version of this plugin.
   */
  protected $version;

  /**
   * Register the stylesheets for the public-facing side of the site.
   *
   * @since    1.0.0
   * @last     2.9.0
   */
  public function enqueue_styles() {
    if (is_user_logged_in()) {
      if ( !function_exists('is_plugin_active') || is_plugin_active( 'optimizePressPlugin/optimizepress.php' ) ) {
        wp_register_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/simple-lms-full.css', array(), $this->version, 'all' );
        wp_enqueue_style( $this->plugin_name );
      } else {
        wp_register_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/simple-lms-public.css', array(), $this->version, 'all' );

        $bar_theme = get_option( $this->plugin_name . '_theme_bar_graph', 'classic' );
        $radial_theme = get_option( $this->plugin_name . '_theme_radial_graph', 'classic' );

        if ( $bar_theme !== 'false') {
          wp_register_style( $this->plugin_name . '-bar-graph', plugin_dir_url( __FILE__ ) . 'partials/themes/' . $bar_theme . '/bar-graph.css', array(), $this->version, 'all' );
        }
        if ( $radial_theme !== 'false') {
          wp_register_style( $this->plugin_name . '-radial-graph', plugin_dir_url( __FILE__ ) . 'partials/themes/' . $radial_theme . '/radial-graph.css', array(), $this->version, 'all' );
        }

        wp_enqueue_style( $this->plugin_name );
        if ( $bar_theme !== 'false')     wp_enqueue_style( $this->plugin_name . '-bar-graph' );
        if ( $radial_theme !== 'false')  wp_enqueue_style( $this->plugin_name . '-radial-graph' );
      }
    }
  }

  /**
   * Register the JavaScript for the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function enqueue_scripts() {
    if (is_user_logged_in()) {
      wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/simple-lms-public.js', array( 'jquery' ), $this->version, true );

      $completion_nonce = wp_create_nonce( 'wpc-ajax-nonce' );
      $updated_at = get_option( $this->plugin_name . '_last_updated' );
      $last_activity_at = $this->get_last_activity();

      $params = array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => $completion_nonce,
        'user_id' => get_current_user_id(),
        'updated_at' => $updated_at,
        'last_activity_at' => $last_activity_at
      );
      if ($post_id = get_the_ID()) {
        $params['post_id'] = $post_id;
      }

      wp_localize_script( $this->plugin_name, 'wpcompletable', $params );
    }
  }

  /**
   * Allow for async (ajax) loading of buttons...
   *
   * @since    2.2.0
   * @last     2.4.2
   */
  public function get_button() {
    $this->set_nocache();

    $nonce = $_POST['_ajax_nonce'];
    if ( empty( $_POST ) || !wp_verify_nonce( $nonce, 'wpc-ajax-nonce' ) ) {
      wp_send_json( array() );
      exit;
    }

    $unique_button_id = $_REQUEST['button_id'];
    list($post_id, $button_id) = $this->extract_button_info($unique_button_id);

    // Feature: Allow for custom button texts if it exists for this button:
    $button_text = get_option($this->plugin_name . '_incomplete_text', 'Mark as complete');
    if ( isset( $_POST['old_button_text'] ) && !empty( $_POST['old_button_text'] ) ) {
      $button_text = sanitize_text_field($_POST['old_button_text']);
    }
    $completed_button_text = get_option($this->plugin_name . '_completed_text', 'COMPLETED');
    if ( isset( $_POST['new_button_text'] ) && !empty( $_POST['new_button_text'] ) ) {
      $completed_button_text = sanitize_text_field($_POST['new_button_text']);
    }

    $updates_to_sendback = array(
      ('.wpc-button-' . $this->get_button_class( $unique_button_id )) => $this->complete_button_cb( array( 'post_id' => $post_id, 'name' => $button_id, 'text' => $completed_button_text, 'completed_text' => $button_text ) )
    );

    // Send back array of posts:
    wp_send_json( $updates_to_sendback );
  }

  /**
   * Register the shortcode for [complete_button] for the public-facing side of the site.
   *
   * @since    1.0.0
   * @last     2.9.1
   */
  public function complete_button_cb($atts, $content = null, $tag = '') {
    $this->set_nocache();
    if ( ! is_user_logged_in() ) {
      /**
       * Action that is fired when a complete-button should be displayed for a
       * logged-out user.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_button_failed', 'guest', $atts, $content, $tag );

      return; // should replace with button redirect to signup
    }

    // cleanup shortcode attributes:
    if ( is_array( $atts ) ) {
      foreach ($atts as $key => $value) {
        // remove any ticks or magicquotes at the beginning of the attribute value:
        $value = preg_replace("/^”(.*)”$/", "$1", $value);
        $value = preg_replace("/^`(.*)`$/", "$1", $value);

        $atts[$key] = $value;
      }
    }

    /**
     * Filter the shortcode attributes so other plugins can overwrite values or
     * dynamically add attributes.
     *
     * @pst
     *
     * @since 2.9.1
     * @param string|array $atts    The attributes to filter of the shortcode.
     * @param string       $content Content of the shortcode. Usually empty.
     * @param string       $tag     The shortcode tag name.
     */
    $atts = apply_filters( 'simplelms_button_attrs', $atts, $content, $tag );

    $post_id = get_the_ID();
    $button_id = '';
    if ( isset( $atts['id'] ) && !empty( $atts['id'] ) ) {
      $button_id = sanitize_text_field($atts['id']);
    } else if ( isset( $atts['name'] ) && !empty( $atts['name'] ) ) {
      $button_id = sanitize_text_field($atts['name']);
    }
    if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
      $post_id = intval($atts['post_id']);
    } else if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
      $post_id = intval($atts['post']);
    }

    if ( ! in_array( get_post_type( $post_id ), $this->get_enabled_post_types() ) ) {
      /**
       * Action that is fired when a complete-button should be displayed for a
       * post type that does not support completion.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_button_failed', 'unsupported', $atts, $content, $tag );

      return;
    }

    if ( ! $this->post_can_complete( $post_id ) ) {
      /**
       * Action that is fired when a complete-button should be displayed for a
       * post that cannot be marked as complete.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_button_failed', 'not_completable', $atts, $content, $tag );

      return;
    }

    $unique_button_id = $this->get_button_id($post_id, $button_id);

    $user_activity = $this->get_user_activity();
    if ( !isset( $user_activity[$unique_button_id] ) ) $user_activity[$unique_button_id] = array();
    if ( !isset( $user_activity[$unique_button_id]['first_seen'] ) && !isset( $user_activity[$unique_button_id]['completed'] )  ) {
      $user_activity[$unique_button_id]['first_seen'] = date('Y-m-d H:i:s');
      $this->set_user_activity($user_activity);
    }

    // NOTE: if you have graphs that were loaded before this, they will be out of date...
    // We should recommend that if someone uses autocomplete, they should also use async.
    if ( isset( $atts['autocomplete'] ) && !isset( $user_activity[$unique_button_id]['completed'] ) ) {
      $posts = $this->get_completable_posts();
      // Check to see if button isn't registed yet:
      if ( isset( $button_id ) && ( !isset( $posts[$post_id]['buttons'] ) || !in_array( $unique_button_id, $posts[$post_id]['buttons'] ) ) ) {
        $post_meta = $posts[$post_id];
        if ( !isset( $post_meta['buttons'] ) ) $post_meta['buttons'] = array();
        $post_meta['buttons'][] = $unique_button_id;
        $posts[ $post_id ] = $post_meta;
        // Save changes:
        update_post_meta( $post_id, 'simple-lms', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
        wp_cache_set( "posts", json_encode( $posts, JSON_UNESCAPED_UNICODE ), 'simple-lms' );
      }

      // Mark this button as completed:
      if ( ! isset( $user_activity[ $unique_button_id ] ) ) $user_activity[ $unique_button_id ] = array();
      $user_activity[ $unique_button_id ]['completed'] = date('Y-m-d H:i:s');

      // Save to database/cache:
      $this->set_user_activity($user_activity);
    }

    if ( isset( $atts['hidden'] ) ) {
      /**
       * Action that is fired when a complete-button should be displayed for a
       * post type that does not support completion.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_button_failed', 'hidden', $atts, $content, $tag );

      return "<!-- hidden button for: $unique_button_id -->";
    }

    // Feature: Allow for custom button texts and styles if it exists for this button:
    $button_text = get_option($this->plugin_name . '_incomplete_text', 'Mark as complete');
    if ( isset( $atts['text'] ) && !empty( $atts['text'] ) ) {
      $button_text = sanitize_text_field(stripslashes($atts['text']));
    }
    $completed_button_text = get_option($this->plugin_name . '_completed_text', 'COMPLETED');
    if ( isset( $atts['completed_text'] ) && !empty( $atts['completed_text'] ) ) {
      $completed_button_text = sanitize_text_field(stripslashes($atts['completed_text']));
    }
    $redirect_url = false;
    if ( isset( $atts['redirect'] ) && !empty( $atts['redirect'] ) ) {
      $redirect_url = esc_url_raw($atts['redirect']);
    }
    $custom_classes = false;
    if ( isset( $atts['class'] ) && !empty( $atts['class'] ) ) {
      $custom_classes = sanitize_html_class($atts['class']);
    }
    $custom_styles = false;
    if ( isset( $atts['style'] ) && !empty( $atts['style'] ) ) {
      $custom_styles = sanitize_text_field($atts['style']);
    }

    ob_start();
    if (isset($atts['async'])) {
      include 'partials/simple-lms-public-loading-button.php';
    } else {
      // Start displaying button:
      if ( $this->button_is_completed( $post_id, $button_id ) ) {
        include 'partials/simple-lms-public-completed-button.php';
      } else {
        include 'partials/simple-lms-public-incomplete-button.php';
      }
    }

    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Register the shortcode for [progress_in_percentage] for the public-facing side of the site.
   *
   * @since    1.0.0
   * @last     2.9.1
   */
  public function progress_percentage_cb($atts, $content = null, $tag = '') {
    $this->set_nocache();
    if ( ! is_user_logged_in() ) {
      /**
       * Action that is fired when a course progress bar should be displayed for a
       * logged-out user.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_progress_failed', 'guest', $atts, $content, $tag );

      return;
    }

    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    /**
     * Filter the shortcode attributes so other plugins can overwrite values or
     * dynamically add attributes.
     *
     * @pst
     *
     * @since 2.9.1
     * @param string|array $atts    The attributes to filter of the shortcode.
     * @param string       $content Content of the shortcode. Usually empty.
     * @param string       $tag     The shortcode tag name.
     */
    $atts = apply_filters( 'simplelms_progress_percentage_attrs', $atts, $content, $tag );

    // find the current course's
    if ( ( !isset( $atts['course'] ) || empty( $atts['course'] ) ) && is_numeric( get_the_ID() ) ) {
      if ( $post_course = $this->post_course( get_the_ID() ) ) $atts['course'] = $post_course;
    }
    $percentage = $this->get_percentage( $atts );

    return '<span class="simple-lms-progress-percentage ' . $this->get_course_class( $atts ) . '">' . $percentage . "%" . '</span>';
  }

  /**
   * PREMIUM:
   * Register the shortcode for [progress_ratio] for the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function progress_ratio_cb($atts, $content = null, $tag = '') {
    $this->set_nocache();
    if ( ! is_user_logged_in() ) {
      /**
       * Action that is fired when a course progress ratio should be displayed for a
       * logged-out user.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_progress_ratio_failed', 'guest', $atts, $content, $tag );

      return;
    }
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    /**
     * Filter the shortcode attributes so other plugins can overwrite values or
     * dynamically add attributes.
     *
     * @pst
     *
     * @since 2.9.1
     * @param string|array $atts    The attributes to filter of the shortcode.
     * @param string       $content Content of the shortcode. Usually empty.
     * @param string       $tag     The shortcode tag name.
     */
    $atts = apply_filters( 'simplelms_progress_ratio_attrs', $atts, $content, $tag );

    // find the current course's
    if ( ( !isset( $atts['course'] ) || empty( $atts['course'] ) ) && is_numeric( get_the_ID() ) ) {
      if ( $post_course = $this->post_course( get_the_ID() ) ) $atts['course'] = $post_course;
    }
    if ( !isset( $atts['scheduled'] ) ) {
      $atts['scheduled'] = 'false';
    }
    $total_buttons = $this->get_buttons( $atts );
    // Don't show chart if there's no data to populate it:
    if ( count( $total_buttons ) <= 0 ) {
      /**
       * Action that is fired when a course progress ratio should be displayed for a
       * logged-out user.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_progress_ratio_failed', 'no_button', $atts, $content, $tag );

      return;
    }

    $user_completed = $this->get_user_completed();

    $completed_posts = array_intersect( $total_buttons, array_keys( $user_completed ) );

    return '<span class="simple-lms-progress-ratio ' . $this->get_course_class($atts) . '">' . count($completed_posts) . "/" . count($total_buttons) . '</span>';
  }

  /**
   * Allow for async (ajax) loading of progress graphs...
   *
   * @since    2.2.0
   * @last     2.9.0
   */
  public function get_graphs() {
    $this->set_nocache();
    $nonce = $_POST['_ajax_nonce'];
    if ( empty( $_POST ) || !wp_verify_nonce( $nonce, 'wpc-ajax-nonce' ) ) {
      wp_send_json( array() );
      exit;
    }

    $bar_theme = get_option( $this->plugin_name . '_theme_bar_graph', 'classic' );
    $radial_theme = get_option( $this->plugin_name . '_theme_radial_graph', 'classic' );
    $loading = false;

    $courses = $this->get_course_names();
    $courses[] = get_bloginfo( 'name' );
    $updates_to_sendback = array();
    foreach ($courses as $course) {
      $atts = array('course' => $course);

      $updates_to_sendback['.simple-lms-progress-ratio.' . $this->get_course_class($atts)] = $this->progress_ratio_cb( $atts );
      $updates_to_sendback['.simple-lms-progress-percentage.' . $this->get_course_class($atts)] = $this->progress_percentage_cb( $atts );

      $percentage = $this->get_percentage( $atts );
      if ($radial_theme !== 'false') {
        ob_start();
        include 'partials/themes/' . $radial_theme . '/radial-graph.php';
        $updates_to_sendback['.wpc-radial-loading.' . $this->get_course_class($atts)] = ob_get_clean();
      }
      if ($bar_theme !== 'false') {
        ob_start();
        include 'partials/themes/' . $bar_theme . '/bar-graph.php';
        $updates_to_sendback['.wpc-bar-loading.' . $this->get_course_class($atts)] = ob_get_clean();
      }
    }

    $updates_to_sendback['.simple-lms-progress-ratio.all-courses'] = $this->progress_ratio_cb( array('course' => 'all') );
    $updates_to_sendback['.simple-lms-progress-percentage.all-courses'] = $this->progress_percentage_cb( array('course' => 'all') );

    $percentage = $this->get_percentage( array('course' => 'all') );

    if ( $radial_theme !== 'false' ) {
      ob_start();
      include 'partials/themes/' . $radial_theme . '/radial-graph.php';
      $updates_to_sendback['.wpc-radial-loading.all-courses'] = ob_get_clean();
    }
    if ( $bar_theme !== 'false' ) {
      ob_start();
      include 'partials/themes/' . $bar_theme . '/bar-graph.php';
      $updates_to_sendback['.wpc-bar-loading.all-courses'] = ob_get_clean();
    }
    // Send back array of posts:
    wp_send_json( $updates_to_sendback );
  }

  /**
   * PREMIUM:
   * Register the shortcode for [progress_graph] for the public-facing side of the site.
   *
   * @since    1.0.0
   * @last     2.9.1
   */
  public function progress_radial_graph_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) {
      /**
       * Action that is fired when a radial progress graph should be displayed
       * for a logged-out user.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_progress_radial_graph_failed', 'guest', $atts, $content, $tag );

      return;
    }
    $radial_theme = get_option( $this->plugin_name . '_theme_radial_graph', 'classic' );
    if ( $radial_theme === 'false' ) {
      /**
       * Action that is fired when a radial progress graph should be displayed
       * but that feature was disabled in the plugin settings.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_progress_radial_graph_failed', 'disabled', $atts, $content, $tag );
      return;
    }
    $loading = false;

    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);
    // find the current course's
    if ( ( !isset( $atts['course'] ) || empty( $atts['course'] ) ) && is_numeric( get_the_ID() ) ) {
      if ( $post_course = $this->post_course( get_the_ID() ) ) $atts['course'] = $post_course;
    }

    ob_start();
    if (isset($atts['async'])) {
      $loading = true;
      include 'partials/themes/' . $radial_theme . '/radial-graph.php';
    } else {
      $percentage = $this->get_percentage( $atts );
      include 'partials/themes/' . $radial_theme . '/radial-graph.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Register the shortcode for [progress_graph] for the public-facing side of the site.
   *
   * @since    1.0.0
   * @last     2.9.1
   */
  public function progress_bar_graph_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) {
      /**
       * Action that is fired when a course progress graph should be displayed
       * for a logged-out user.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_progress_graph_failed', 'guest', $atts, $content, $tag );

      return;
    }
    $bar_theme = get_option( $this->plugin_name . '_theme_bar_graph', 'classic' );
    if ( $bar_theme === 'false' ) {
      /**
       * Action that is fired when a course progress graph should be displayed
       * but that feature was disabled in the plugin settings.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       * @param string|array $atts    The unprocessed attrs of the shortcode.
       * @param string       $content Content of the shortcode. Usually empty.
       * @param string       $tag     The shortcode tag name.
       */
      do_action( 'simplelms_show_progress_graph_failed', 'disabled', $atts, $content, $tag );

      return;
    }
    $loading = false;

    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);
    // find the current course's
    if ( ( !isset( $atts['course'] ) || empty( $atts['course'] ) ) && is_numeric( get_the_ID() ) ) {
      if ( $post_course = $this->post_course(get_the_ID()) ) $atts['course'] = $post_course;
    }

    ob_start();
    if (isset($atts['async'])) {
      $loading = true;
      include 'partials/themes/' . $bar_theme . '/bar-graph.php';
    } else {
      $percentage = $this->get_percentage( $atts );
      include 'partials/themes/' . $bar_theme . '/bar-graph.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Add custom completion code to the end of post and page content
   *
   * @since    1.0.0
   * @last     2.9.1
   */
  public function append_custom_styles() {
    if ( ! is_user_logged_in() ) {
      /**
       * Action that is fired when custom button CSS is generated while user is
       * logged out. By default, no CSS is output for guests, but this action
       * allows plugins to generate custom CSS or handle the case in a different
       * way.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       */
      do_action( 'simplelms_custom_styles_failed', 'guest' );

      return;
    }
    if ( ! SIMPLELMS_IS_ACTIVATED ) {
      /**
       * Action that is fired when attempting to output custom button CSS while
       * SimpleLMS is unlicensed.
       *
       * @pst
       *
       * @since 2.9.1
       * @param string       $reason  The reason, why the function failed.
       */
      do_action( 'simplelms_custom_styles_failed', 'disabled' );

      return;
    }

    $style_default = '
li .wpc-lesson-completed { opacity: .5; }
li .wpc-lesson-completed:after { content: "✔"; margin-left: 5px; }
';

    $complete_background = get_option( $this->plugin_name . '_incomplete_background', '#ff0000' );
    $complete_color = get_option( $this->plugin_name . '_incomplete_color', '#ffffff' );
    $completed_background = get_option( $this->plugin_name . '_completed_background', '#666666' );
    $completed_color = get_option( $this->plugin_name . '_completed_color', '#ffffff' );
    $graph_primary_color = get_option( $this->plugin_name . '_graph_primary', '#97a71d' );
    $graph_secondary_color = get_option( $this->plugin_name . '_graph_secondary', '#ebebeb' );

    $radial_styles = " .wpc-radial-progress { background-color: $graph_secondary_color; } .wpc-radial-progress .wpc-fill { background-color: $graph_primary_color; } .wpc-radial-progress .wpc-numbers { color: $graph_primary_color; } ";
    $bar_styles = " .wpc-bar-progress .wpc-progress-track { background-color: $graph_secondary_color; } .wpc-bar-progress .wpc-progress-fill { background-color: $graph_primary_color; } .wpc-bar-progress .wpc-numbers { color: $graph_primary_color; } .wpc-bar-progress[data-progress=\"75\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"76\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"77\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"78\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"79\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"80\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"81\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"82\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"83\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"84\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"85\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"86\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"87\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"88\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"89\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"90\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"91\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"92\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"93\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"94\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"95\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"96\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"97\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"98\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"99\"] .wpc-numbers, .wpc-bar-progress[data-progress=\"100\"] .wpc-numbers { color: $graph_secondary_color; } ";

    $graph_styles = '';
    $bar_theme = get_option( $this->plugin_name . '_theme_bar_graph', 'classic' );
    $radial_theme = get_option( $this->plugin_name . '_theme_radial_graph', 'classic' );

    if ( $bar_theme === 'classic' )     $graph_styles .= $bar_styles;
    if ( $radial_theme === 'classic' )  $graph_styles .= $radial_styles;

    $custom_styles = get_option( $this->plugin_name . '_custom_styles', $style_default );

    echo "<style type=\"text/css\"> a.wpc-complete { background: $complete_background; color: $complete_color; } a.wpc-completed { background: $completed_background; color: $completed_color; } $graph_styles .wpc-reset-link { color: $graph_primary_color; background-color: $graph_secondary_color; } $custom_styles </style>";
  }

  /**
   * Add defer to scripts.
   *
   * @since    2.4.3.1
   */
  public function append_script_defer( $tag, $handle ) {
    if ( 'simple-lms' !== $handle )
      return $tag;
    return str_replace( ' src', ' defer="defer" src', $tag );
  }

  /**
   * Add custom completion code to the end of post and page content
   *
   * @since    1.0.0
   * @last     2.5.3
   */
  public function append_completion_code($content) {
    $post_type = get_post_type();
    $post_id = get_the_ID();

    // Don't append if it's been disabled:
    if ( get_option( $this->plugin_name . '_auto_append', 'true' ) == 'false' ) {
      return $content;
    }

    // Don't append if we aren't suppose to complete this type of post:
    if ( ! in_array( $post_type, $this->get_enabled_post_types() ) ) {
      return $content;
    }

    // See if this post is actually completable:
    if ( ! $this->post_can_complete( $post_id ) ) {
      return $content;
    }

    $post = get_post($post_id);
    $post_content = $post->post_content;
    $all_post_meta_data = get_post_meta( $post_id );
    foreach ($all_post_meta_data as $key => $value) {
      $post_content .= var_export( $value, true ) . "\n\n";
    }

    // Only append to body if we can't find any record of the button anywhere on the content:
    // NOTE: This doesn't fix the issue with OptimizePress... but it should help. Check current saved content:
    if ( ( strpos( $post_content, '[complete_button' ) === false ) && ( strpos( $post_content, '[wpc_complete_button' ) === false ) && ( strpos( $post_content, '[wpc_button' ) === false ) && is_main_query() ) {
      if ( ( strpos( $content, '[complete_button' ) === false ) && ( strpos( $content, '[wpc_complete_button' ) === false ) && ( strpos( $content, '[wpc_button' ) === false ) && ( strpos( $content, 'class="wpc-button' ) === false ) ) {
        $content .= "\n\n[wpc_complete_button]";
      }
    }

    return $content;
  }

  /**
   * Handle trying to mark a lesson as completed as a logged out user... should just redirect to login.
   *
   * @since    1.0.0
   */
  public function nopriv_mark_completed() {
    $redirect = 'http' . ((isset($_SERVER['HTTPS']) && ('on' === $_SERVER['HTTPS'])) ? 's' : '') . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
      // return something indicating that the page should redirect to login?
      echo json_encode( array( 'redirect' => wp_login_url( $redirect ) ) );
      die();
    } else {
      wp_redirect( wp_login_url( $redirect ) );
      exit();
    }
  }

  /**
   * Handle marking a lesson as completed.
   *
   * @since    1.0.0
   * @last     2.9.0.7
   */
  public function mark_completed() {
    check_ajax_referer( 'wpc-ajax-nonce', '_ajax_nonce' );

    $this->set_nocache();

    // Get any existing lessons this user has completed:
    $user_completed = $this->get_user_activity();

    $unique_button_id = $_REQUEST['button'];
    list($post_id, $button_id) = $this->extract_button_info($unique_button_id);

    $course = $this->post_course($post_id);

    $posts = $this->get_completable_posts();
    if ( isset( $button_id ) && ( !isset( $posts[$post_id]['buttons'] ) || !in_array( $unique_button_id, $posts[$post_id]['buttons'] ) ) ) {
      $post_meta = $posts[$post_id];
      if ( !isset( $post_meta['buttons'] ) ) $post_meta['buttons'] = array();
      $post_meta['buttons'][] = $unique_button_id;
      $posts[ $post_id ] = $post_meta;
      // Save changes:
      update_post_meta( $post_id, 'simple-lms', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
      wp_cache_set( "posts", json_encode( $posts, JSON_UNESCAPED_UNICODE ), 'simple-lms' );
    }

    // Mark this button as completed:
    if ( ! isset( $user_completed[ $unique_button_id ] ) ) $user_completed[ $unique_button_id ] = array();
    $user_completed[ $unique_button_id ]['completed'] = date('Y-m-d H:i:s');

    // Save to database/cache:
    $this->set_user_activity($user_completed);

    // Feature: Allow for custom button texts if it exists for this button:
    $button_text = get_option($this->plugin_name . '_incomplete_text', 'Mark as complete');
    if ( isset( $_POST['old_button_text'] ) && !empty( $_POST['old_button_text'] ) ) {
      $button_text = sanitize_text_field($_POST['old_button_text']);
    }
    $completed_button_text = get_option($this->plugin_name . '_completed_text', 'COMPLETED');
    if ( isset( $_POST['new_button_text'] ) && !empty( $_POST['new_button_text'] ) ) {
      $completed_button_text = sanitize_text_field($_POST['new_button_text']);
    }
    $button_style = '';
    if ( isset( $_POST['style'] ) && !empty( $_POST['style'] ) ) {
      $button_style = sanitize_text_field($_POST['style']);
    }
    $button_class = '';
    if ( isset( $_POST['class'] ) && !empty( $_POST['class'] ) ) {
      $button_class = sanitize_html_class($_POST['class']);
    }

    // update the button
    $updates_to_sendback = array(
      ('.wpc-button-' . $this->get_button_class( $unique_button_id )) => $this->complete_button_cb( array( 'post_id' => $post_id, 'name' => $button_id, 'text' => $button_text, 'completed_text' => $completed_button_text, 'style' => $button_style, 'class' => $button_class ) )
    );
    $updates_to_sendback['fetch'] = 'true';

    $post_status = $this->post_completion_status( $post_id );

    //sleep ( rand ( 0, 2 ) );

    // PREMIUM: redirect student if teacher has added redirect url:
    if (SIMPLELMS_IS_ACTIVATED) {
      // PREMIUM: get info for progress percentage:
      $atts = array();
      if ( $course ) {
        $atts['course'] = $course;
      }
      // Add lesson indicators
      $updates_to_sendback['lesson-' . $post_status] = $post_id;

      // Toggle content blocks:
      $updates_to_sendback['.wpc-content-button-' . $this->get_button_class( $unique_button_id ) . '-completed'] = 'show';
      $updates_to_sendback['.wpc-content-button-' . $this->get_button_class( $unique_button_id ) . '-incomplete'] = 'hide';
      $updates_to_sendback['wpc-button-completed'] = 'trigger';
      $updates_to_sendback['wpc-button-completed::' . $unique_button_id ] = 'trigger';
      if ( $this->post_completion_status($post_id) == 'completed' ) {
        $updates_to_sendback['.wpc-content-page-' . $post_id . '-completed'] = 'show';
        $updates_to_sendback['.wpc-content-page-' . $post_id . '-incomplete'] = 'hide';
        $updates_to_sendback['wpc-page-completed'] = 'trigger';
        $updates_to_sendback['wpc-page-completed::' . $post_id ] = 'trigger';
      }
      if ( $this->course_completion_status($course) == 'completed' ) {
        $updates_to_sendback['.wpc-content-course-' . $this->get_course_class( array( 'course' => $course ) ) . '-completed'] = 'show';
        $updates_to_sendback['.wpc-content-course-' . $this->get_course_class( array( 'course' => $course ) ) . '-incomplete'] = 'hide';
        $updates_to_sendback['wpc-course-completed'] = 'trigger';
        $updates_to_sendback['wpc-course-completed::' . $course ] = 'trigger';
        $updates_to_sendback['wpc-course-completed::' . $this->get_course_class( array( 'course' => $course ) ) ] = 'trigger';
      }

      // Update premium feature widgets:
      $updates_to_sendback['.simple-lms-progress-ratio.' . $this->get_course_class($atts)] = $this->progress_ratio_cb( $atts );
      $updates_to_sendback['.simple-lms-progress-percentage.' . $this->get_course_class($atts)] = $this->progress_percentage_cb( $atts );
      $updates_to_sendback['.simple-lms-progress-ratio.all-courses'] = $this->progress_ratio_cb( array('course' => 'all') );
      $updates_to_sendback['.simple-lms-progress-percentage.all-courses'] = $this->progress_percentage_cb( array('course' => 'all') );
      $updates_to_sendback['.' . $this->get_course_class($atts) . '[data-progress]'] = $this->get_percentage($atts);
      $updates_to_sendback['.all-courses[data-progress]'] = $this->get_percentage(array('course' => 'all') );
      $updates_to_sendback['peer-pressure'] = $this->get_peer_pressure_stats($post_id);

      // Add redirect if needed:
      $posts = $this->get_completable_posts();
      if ( ( $post_status == 'completed' ) && isset( $posts[ $post_id ] ) && isset( $posts[ $post_id ]['redirect'] ) ) {
        $redirect = $posts[ $post_id ]['redirect'];

        if ($redirect['url'] && !empty($redirect['url'])) {
          $updates_to_sendback['redirect'] = $redirect['url'];
        } else if (strpos($redirect['title'], 'http') === 0) {
          $updates_to_sendback['redirect'] = $redirect['title'];
        }
      }
    }

    // Add action for other plugins to hook in:
    do_action( 'simplelms_mark_completed', array( 'user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    do_action( 'simplelms_button_completed', array( 'user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    if ( $this->post_completion_status($post_id) == 'completed' ) {
      do_action( 'simplelms_page_completed', array('user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    }
    if ( $this->course_completion_status($course) == 'completed' ) {
      do_action( 'simplelms_course_completed', array('user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    }

    $zapier_url = get_option( $this->plugin_name . '_zapier', '' );
    if ( !empty($zapier_url) && ( strpos($zapier_url, 'zapier.com') !== false ) ) {
      // get user specific info:
      $user_id = get_current_user_id();
      $user_info = get_userdata( $user_id );
      $user_name = $user_info->display_name;
      $user_email = $user_info->user_email;

      $button_zapier = array( 'simple-lms' => array(
        'user_id' => $user_id,
        'user_email' => $user_email,
        'user_name' => $user_name,
        'type' => 'button',
        'action' => 'completed',
        'button' => $unique_button_id,
        'post_id' => $post_id,
        'course' => ( ( !empty( $course ) ) ? $course : get_bloginfo( 'name' ) ),
        'course_slug' => $this->get_course_class( array( 'course' => $course ) ),
        'course_ratio' => $this->progress_ratio_cb( array( 'course' => $course ) ),
        'course_percentage' => $this->progress_percentage_cb( array( 'course' => $course ) ),
        'total_ratio' => $this->progress_ratio_cb( array( 'course' => 'all' ) ),
        'total_percentage' => $this->progress_percentage_cb( array( 'course' => 'all' ) ),
        'completed_at' => current_time( 'mysql' ),
        'site_url' => get_site_url(),
        'version' => SIMPLELMS_VERSION,
        'referral' => wp_get_referer()
      ) );

      $button_response = wp_remote_post( $zapier_url, array('blocking' => false, 'body' => $button_zapier));

      if ( is_wp_error( $button_response ) ) {
        update_option( $this->plugin_name . '_zapier_last_error', json_encode( array("message" => $button_response->get_error_message(), "error_at" => current_time( 'mysql' ) ), JSON_UNESCAPED_UNICODE ) );
        error_log($this->plugin_name . " line " . __LINE__ . ": Zapier error -- " . var_export( $button_response, true ) );
      } else {
        //error_log($this->plugin_name . " line " . __LINE__ . ": Zapier SUCCESS -- " . var_export( $button_zapier, true ) );
      }

      if ( $this->post_completion_status($post_id) == 'completed' ) {
        // build zapier payload:
        $post_zapier = array( 'simple-lms' => array(
          'user_id' => $user_id,
          'user_email' => $user_email,
          'user_name' => $user_name,
          'type' => 'post',
          'action' => 'completed',
          'post_id' => $post_id,
          'course' => ( ( !empty( $course ) ) ? $course : get_bloginfo( 'name' ) ),
          'course_slug' => $this->get_course_class( array( 'course' => $course ) ),
          'course_ratio' => $this->progress_ratio_cb( array( 'course' => $course ) ),
          'course_percentage' => $this->progress_percentage_cb( array( 'course' => $course ) ),
          'total_ratio' => $this->progress_ratio_cb( array( 'course' => 'all' ) ),
          'total_percentage' => $this->progress_percentage_cb( array( 'course' => 'all' ) ),
          'completed_at' => current_time( 'mysql' ),
          'site_url' => get_site_url(),
          'version' => SIMPLELMS_VERSION,
          'referral' => wp_get_referer()
        ) );

        $post_response = wp_remote_post( $zapier_url, array('blocking' => false, 'body' => $post_zapier));

        if ( is_wp_error( $post_response ) ) {
          update_option( $this->plugin_name . '_zapier_last_error', json_encode( array("message" => $post_response->get_error_message(), "error_at" => current_time( 'mysql' ) ), JSON_UNESCAPED_UNICODE ) );
          error_log($this->plugin_name . " line " . __LINE__ . ": Zapier error -- " . var_export( $post_response, true ) );
        }
      }
      if ( $this->course_completion_status($course) == 'completed' ) {
        $course_zapier = array( 'simple-lms' => array(
          'user_id' => $user_id,
          'user_email' => $user_email,
          'user_name' => $user_name,
          'type' => 'course',
          'action' => 'completed',
          'course' => ( ( !empty( $course ) ) ? $course : get_bloginfo( 'name' ) ),
          'course_slug' => $this->get_course_class( array( 'course' => $course ) ),
          'course_ratio' => $this->progress_ratio_cb( array( 'course' => $course ) ),
          'course_percentage' => $this->progress_percentage_cb( array( 'course' => $course ) ),
          'total_ratio' => $this->progress_ratio_cb( array( 'course' => 'all' ) ),
          'total_percentage' => $this->progress_percentage_cb( array( 'course' => 'all' ) ),
          'completed_at' => current_time( 'mysql' ),
          'site_url' => get_site_url(),
          'version' => SIMPLELMS_VERSION,
          'referral' => wp_get_referer()
        ) );

        $course_response = wp_remote_post( $zapier_url, array('blocking' => false, 'body' => $course_zapier));

        if ( is_wp_error( $course_response ) ) {
          update_option( $this->plugin_name . '_zapier_last_error', json_encode( array("message" => $course_response->get_error_message(), "error_at" => current_time( 'mysql' ) ), JSON_UNESCAPED_UNICODE ) );
          error_log($this->plugin_name . " line " . __LINE__ . ": Zapier error -- " . var_export( $course_response, true ) );
        }
      }
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {
      echo json_encode( $updates_to_sendback, JSON_UNESCAPED_UNICODE );
      die();
    } else {
      if ( isset( $_REQUEST['redirect'] ) ) {
        wp_safe_redirect( $_REQUEST['redirect'] );
      } else if ( wp_get_referer() ) {
        wp_safe_redirect( wp_get_referer() );
      } else {
        wp_safe_redirect( get_home_url() );
      }
    }
  }

  /**
   * Handle trying to mark a lesson as incomplete as a logged out user... should just redirect to login.
   *
   * @since    1.0.0
   */
  public function nopriv_mark_uncompleted() {
    $redirect = 'http' . ((isset($_SERVER['HTTPS']) && ('on' === $_SERVER['HTTPS'])) ? 's' : '') . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
      // return something indicating that the page should redirect to login?
      echo json_encode( array( 'redirect' => wp_login_url( $redirect ) ) );
      die();
    } else {
      wp_redirect( wp_login_url( $redirect ) );
      exit();
    }
  }

  /**
   * Handle mark a lesson as incomplete.
   *
   * @since    1.0.0
   * @last     2.1.0
   */
  public function mark_uncompleted() {
    check_ajax_referer( 'wpc-ajax-nonce', '_ajax_nonce' );

    $this->set_nocache();

    // Get any existing lessons this user has completed:
    $user_completed = $this->get_user_activity();

    // Get any existing lessons this user has completed:
    $unique_button_id = $_REQUEST['button'];
    list($post_id, $button_id) = $this->extract_button_info($unique_button_id);

    $course = $this->post_course($post_id);
    $previous_post_status = $this->post_completion_status($post_id);
    $previous_course_status = $this->course_completion_status($course);

    // Remove this post id:
    if ( ! isset( $user_completed[ $unique_button_id ] ) ) $user_completed[ $unique_button_id ] = array();
    unset($user_completed[ $unique_button_id ]['completed']);

    // and update the meta storage values:
    $this->set_user_activity($user_completed);

    // Feature: Allow for custom button texts if it exists for this button:
    $button_text = get_option($this->plugin_name . '_incomplete_text', 'Mark as complete');
    if ( isset( $_POST['new_button_text'] ) && !empty( $_POST['new_button_text'] ) ) {
      $button_text = sanitize_text_field($_POST['new_button_text']);
    }
    $completed_button_text = get_option($this->plugin_name . '_completed_text', 'COMPLETED');
    if ( isset( $_POST['old_button_text'] ) && !empty( $_POST['old_button_text'] ) ) {
      $completed_button_text = sanitize_text_field($_POST['old_button_text']);
    }
    $button_style = '';
    if ( isset( $_POST['style'] ) && !empty( $_POST['style'] ) ) {
      $button_style = sanitize_text_field($_POST['style']);
    }
    $button_class = '';
    if ( isset( $_POST['class'] ) && !empty( $_POST['class'] ) ) {
      $button_class = sanitize_html_class($_POST['class']);
    }

    $updates_to_sendback = array(
      ('.wpc-button-' . $this->get_button_class( $unique_button_id )) => $this->complete_button_cb( array( 'post_id' => $post_id, 'name' => $button_id, 'text' => $button_text, 'completed_text' => $completed_button_text, 'style' => $button_style, 'class' => $button_class ) )
    );
    $updates_to_sendback['fetch'] = 'true';

    //sleep ( rand ( 0, 2 ) );

    // PREMIUM:
    if (SIMPLELMS_IS_ACTIVATED) {
      // get info for progress percentage:
      $atts = array();
      if ( $course ) {
        $atts['course'] = $course;
      }
      // Add lesson indicators:
      $updates_to_sendback['lesson-' . $this->post_completion_status( $post_id )] = $post_id;

      // Toggle content blocks:
      $updates_to_sendback['.wpc-content-button-' . $this->get_button_class( $unique_button_id ) . '-incomplete'] = 'show';
      $updates_to_sendback['.wpc-content-button-' . $this->get_button_class( $unique_button_id ) . '-completed'] = 'hide';
      $updates_to_sendback['.wpc-content-page-' . $post_id . '-incomplete'] = 'show';
      $updates_to_sendback['.wpc-content-page-' . $post_id . '-completed'] = 'hide';
      $updates_to_sendback['.wpc-content-course-' . $this->get_course_class( array( 'course' => $course ) ) . '-incomplete'] = 'show';
      $updates_to_sendback['.wpc-content-course-' . $this->get_course_class( array( 'course' => $course ) ) . '-completed'] = 'hide';

      $updates_to_sendback['wpc-button-uncompleted'] = 'trigger';
      $updates_to_sendback['wpc-button-uncompleted::' . $unique_button_id ] = 'trigger';
      if ( $previous_post_status == 'completed' ) {
        $updates_to_sendback['wpc-page-uncompleted'] = 'trigger';
        $updates_to_sendback['wpc-page-uncompleted::' . $post_id ] = 'trigger';
      }
      if ( $previous_course_status == 'completed' ) {
        $updates_to_sendback['wpc-course-uncompleted'] = 'trigger';
        $updates_to_sendback['wpc-course-uncompleted::' . $course ] = 'trigger';
        $updates_to_sendback['wpc-course-uncompleted::' . $this->get_course_class( array( 'course' => $course ) ) ] = 'trigger';
      }

      $updates_to_sendback['.simple-lms-progress-ratio.' . $this->get_course_class($atts)] = $this->progress_ratio_cb( $atts );
      $updates_to_sendback['.simple-lms-progress-percentage.' . $this->get_course_class($atts)] = $this->progress_percentage_cb( $atts );
      $updates_to_sendback['.simple-lms-progress-ratio.all-courses'] = $this->progress_ratio_cb( array('course' => 'all') );
      $updates_to_sendback['.simple-lms-progress-percentage.all-courses'] = $this->progress_percentage_cb( array('course' => 'all') );
      $updates_to_sendback['.' . $this->get_course_class($atts) . '[data-progress]'] = $this->get_percentage($atts);
      $updates_to_sendback['.all-courses[data-progress]'] = $this->get_percentage(array('course' => 'all') );
      $updates_to_sendback['peer-pressure'] = $this->get_peer_pressure_stats($post_id);
    }

    // Add action for other plugins to hook in:
    do_action( 'simplelms_mark_incomplete', array('user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    do_action( 'simplelms_button_uncompleted', array( 'user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    if ( $previous_post_status == 'completed' ) {
      do_action( 'simplelms_page_uncompleted', array('user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    }
    if ( $previous_course_status == 'completed' ) {
      do_action( 'simplelms_course_uncompleted', array('user_id' => get_current_user_id(), 'post_id' => $post_id, 'button_id' => $unique_button_id, 'course' => $course ) );
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {
      // Send back new button:
      echo json_encode( $updates_to_sendback, JSON_UNESCAPED_UNICODE );
      die();
    } else {
      if ( isset( $_REQUEST['redirect'] ) ) {
        wp_safe_redirect( $_REQUEST['redirect'] );
      } else if ( wp_get_referer() ) {
        wp_safe_redirect( wp_get_referer() );
      } else {
        wp_safe_redirect( get_home_url() );
      }
    }
  }

  /**
   * Returns an array of all wordpress posts that are "completable".
   *
   * @since  1.2.0
   * @last   2.9.0
   */
  public function get_completable_list() {
    $this->set_nocache();

    $updates_to_sendback = array();
    $asJson = ($_POST && isset($_POST['_ajax_nonce']));

    if ( get_current_user_id() > 0 ) {
      $user_id = get_current_user_id();
      if ( $asJson ) {
        $updates_to_sendback['timestamp'] = time();
        $updates_to_sendback['user'] = $user_id;
      }
      $total_posts = $this->get_completable_posts();
      $user_completed = $this->get_user_completed();
      foreach ( $total_posts as $post_id => $value ) {
        $status = $this->post_completion_status( $post_id, $user_id, $value, $user_completed );
        $updates_to_sendback[ get_permalink( $post_id ) ] = array(
          'id' => $post_id,
          'status' => $status,
          'completed' => ( $status == 'completed' ) ? true : false
        );
      }
    }
    // Send back array of posts:
    if ( $asJson ) {
      wp_send_json( $updates_to_sendback );
    } else {
      return $updates_to_sendback;
    }
  }

  /**
   * PREMIUM:
   * Handles the [wpc_completed_content] or [wpc_if_completed] shortcodes
   *
   * @since    2.0.0
   * @last     2.1.0
   */
  public function completed_content_cb($atts, $content = null, $tag = '') {
    $this->set_nocache();
    if ( isset( $atts['course'] ) && !empty( $atts['course'] ) ) {
      return $this->if_course_completed_cb($atts, $content, $tag);
    } else if ( isset( $atts['page'] ) && !empty( $atts['page'] ) ) {
      return $this->if_page_completed_cb($atts, $content, $tag);
    } else {
      return $this->if_button_completed_cb($atts, $content, $tag);
    }
  }

  /**
   * PREMIUM:
   * Handles the [wpc_incomplete_content] or [wpc_if_incomplete] shortcodes
   *
   * @since    2.0.0
   * @last     2.1.0
   */
  public function incomplete_content_cb($atts, $content = null, $tag = '') {
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase
    if ( isset( $atts['course'] ) ) {
      return $this->if_course_incomplete_cb($atts, $content, $tag);
    } else if ( isset( $atts['page'] ) ) {
      return $this->if_page_incomplete_cb($atts, $content, $tag);
    } else {
      return $this->if_button_incomplete_cb($atts, $content, $tag);
    }
  }

  /**
   * PREMIUM:
   * Handles the [wpc_if_button_completed] or [wpc_if_completed] shortcodes
   *
   * @since    2.1.0
   * @last     2.9.0
   */
  public function if_button_completed_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // dont show conditional content for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $post_id = get_the_ID();
    $button_id = '';
    if ( isset( $atts['id'] ) && !empty( $atts['id'] ) ) {
      if ( is_numeric($atts['id']) ) {
        $post_id = $atts['id'];
      } else {
        $button_id = sanitize_text_field($atts['id']);
      }
    } else if ( isset( $atts['name'] ) && !empty( $atts['name'] ) ) {
      $button_id = sanitize_text_field($atts['name']);
    } else if ( isset( $atts['button'] ) && !empty( $atts['button'] ) ) {
      $button_id = sanitize_text_field($atts['button']);
    }
    if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
      $post_id = $atts['post'];
    } else if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
      $post_id = $atts['post_id'];
    }

    // dont show conditional content if post isn't completable
    if ( ! in_array( get_post_type( $post_id ), $this->get_enabled_post_types() ) ) return;
    if ( ! $this->post_can_complete( $post_id ) ) return;

    $completion_status = 'completed';
    $should_hide = isset($atts['async']) || !$this->button_is_completed($post_id, $button_id);
    // not pumped on this duplicate call:
    $unique_button_id = $this->get_button_id($post_id, $button_id);

    ob_start();
    if ( !$should_hide || !isset($atts['hide']) || ( $atts['hide'] === 'false' ) ) {
      include 'partials/simple-lms-public-content-button.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Returns boolean of whether a button is completed. Requires post_id and button_id.
   *
   * @since    2.5.0
   * @last     2.9.0
   */
  public function button_is_completed( $post_id = false, $button_id = '' ) {
    if ( ! $post_id ) $post_id = get_the_ID();

    $unique_button_id = $this->get_button_id($post_id, $button_id);

    $user_completed = $this->get_user_completed();
    return isset( $user_completed[ $unique_button_id ] ) && isset( $user_completed[ $unique_button_id ]["completed"] );
  }

  /**
   * PREMIUM:
   * Handles the [wpc_if_page_completed] or [wpc_if_completed page=""] shortcodes
   *
   * @since    2.1.0
   * @last     2.9.0
   */
  public function if_page_completed_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // dont show conditional content for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $post_id = get_the_ID();
    if ( isset( $atts['page'] ) && !empty( $atts['page'] ) ) {
      $post_id = $atts['page'];
    } else if ( isset( $atts['page_id'] ) && !empty( $atts['page_id'] ) ) {
      $post_id = $atts['page_id'];
    } else if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
      $post_id = $atts['post'];
    } else if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
      $post_id = $atts['post_id'];
    }

    // dont show conditional content if post isn't completable
    if ( ! in_array( get_post_type( $post_id ), $this->get_enabled_post_types() ) ) return;
    if ( ! $this->post_can_complete( $post_id ) ) return;

    $completion_status = 'completed';
    $should_hide = isset($atts['async']) || !$this->page_is_completed( $post_id );

    ob_start();
    if ( !$should_hide || !isset($atts['hide']) || ( $atts['hide'] === 'false' ) ) {
      include 'partials/simple-lms-public-content-page.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Returns boolean of whether a page is completed. Accepts a post_id
   *
   * @since    2.5.0
   * @last     2.5.0
   */
  public function page_is_completed( $post_id = false ) {
    if ( ! $post_id ) $post_id = get_the_ID();

    return ( $this->post_completion_status($post_id) == 'completed' );
  }

  /**
   * PREMIUM:
   * Handles the [wpc_if_course_completed] or [wpc_if_completed course=""] shortcodes
   *
   * @since    2.1.0
   * @last     2.9.0
   */
  public function if_course_completed_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // dont show conditional content for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    if ( isset( $atts['course'] ) && !empty( $atts['course'] ) ) {
      $course = $atts['course'];
    } else {
      $course = $this->post_course( get_the_ID() );
    }

    $completion_status = 'completed';
    $should_hide = isset($atts['async']) || $this->course_is_completed( $course );

    ob_start();
    if ( !$should_hide || !isset($atts['hide']) || ( $atts['hide'] === 'false' ) ) {
      include 'partials/simple-lms-public-content-course.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Returns boolean of whether a course is completed. Accepts a course name
   *
   * @since    2.5.0
   * @last     2.5.0
   */
  public function course_is_completed( $course = false ) {
    if ( ! $course ) $course = $this->post_course( get_the_ID() );
    return ( $this->course_completion_status($course) != 'completed' );
  }

  /**
   * PREMIUM:
   * Handles the [wpc_if_incomplete] or [wpc_if_button_incomplete] shortcodes
   *
   * @since    2.1.0
   * @last     2.9.0
   */
  public function if_button_incomplete_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // dont show conditional content for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $post_id = get_the_ID();
    $button_id = '';
    if ( isset( $atts['id'] ) && !empty( $atts['id'] ) ) {
      if ( is_numeric($atts['id']) ) {
        $post_id = $atts['id'];
      } else {
        $button_id = sanitize_text_field($atts['id']);
      }
    } else if ( isset( $atts['name'] ) && !empty( $atts['name'] ) ) {
      $button_id = sanitize_text_field($atts['name']);
    }
    if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
      $post_id = $atts['post'];
    } else if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
      $post_id = $atts['post_id'];
    }

    // dont show conditional content if post isn't completable
    if ( ! in_array( get_post_type( $post_id ), $this->get_enabled_post_types() ) ) return;
    if ( ! $this->post_can_complete( $post_id ) ) return;

    $unique_button_id = $this->get_button_id($post_id, $button_id);
    $completion_status = 'incomplete';

    $user_completed = $this->get_user_completed();
    $should_hide = isset($atts['async']) || isset( $user_completed[ $unique_button_id ] );

    ob_start();
    if ( !$should_hide || !isset($atts['hide']) || ( $atts['hide'] === 'false' ) ) {
      include 'partials/simple-lms-public-content-button.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Handles the [wpc_if_page_incomplete] or [wpc_if_incomplete page=""] shortcodes
   *
   * @since    2.1.0
   * @last     2.9.0
   */
  public function if_page_incomplete_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // dont show conditional content for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $post_id = get_the_ID();
    if ( isset( $atts['page'] ) && !empty( $atts['page'] ) ) {
      $post_id = $atts['page'];
    } else if ( isset( $atts['page_id'] ) && !empty( $atts['page_id'] ) ) {
      $post_id = $atts['page_id'];
    } else if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
      $post_id = $atts['post'];
    } else if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
      $post_id = $atts['post_id'];
    }

    // dont show conditional content if post isn't completable
    if ( ! in_array( get_post_type( $post_id ), $this->get_enabled_post_types() ) ) return;
    if ( ! $this->post_can_complete( $post_id ) ) return;

    $completion_status = 'incomplete';
    $should_hide = isset($atts['async']) || ( $this->post_completion_status($post_id) == 'completed' );

    ob_start();
    if ( !$should_hide || !isset($atts['hide']) || ( $atts['hide'] === 'false' ) ) {
      include 'partials/simple-lms-public-content-page.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Handles the [wpc_if_course_incomplete] or [wpc_if_incomplete course=""] shortcodes
   *
   * @since    2.1.0
   * @last     2.9.0
   */
  public function if_course_incomplete_cb($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // dont show conditional content for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    if ( isset( $atts['course'] ) && !empty( $atts['course'] ) ) {
      $course = $atts['course'];
    } else {
      $course = $this->post_course( get_the_ID() );
    }

    $completion_status = 'incomplete';
    $should_hide = isset($atts['async']) || ( $this->course_completion_status($course) == 'completed' );

    ob_start();
    if ( !$should_hide || !isset($atts['hide']) || ( $atts['hide'] === 'false' ) ) {
      include 'partials/simple-lms-public-content-course.php';
    }
    return ob_get_clean();
  }

  /**
   * Allow for async (ajax) loading of conditional content blocks...
   *
   * @since    2.3.0
   * @last     2.3.0
   */
  public function get_content() {
    $this->set_nocache();
    $nonce = $_POST['_ajax_nonce'];
    if ( empty( $_POST ) || !wp_verify_nonce( $nonce, 'completion' ) ) {
      wp_send_json( array() );
      exit;
    }

    $updates_to_sendback = array();

    if ( $_POST['type'] == 'button' ) {
      $unique_button_id = $_POST['unique_id'];
      $user_completed = $this->get_user_completed();
      $is_completed = isset( $user_completed[ $unique_button_id ] );
      $updates_to_sendback[".wpc-content-button-" . $this->get_button_class( $unique_button_id ) . "-completed"] = ($is_completed) ? 'show' : 'hide';
      $updates_to_sendback[".wpc-content-button-" . $this->get_button_class( $unique_button_id ) . "-incomplete"] = (!$is_completed) ? 'show' : 'hide';
    } else if ( $_POST['type'] == 'page' ) {
      $post_id = $_POST['unique_id'];
      $is_completed = ( $this->post_completion_status($post_id) == 'completed' );
      $updates_to_sendback[".wpc-content-page-" . $post_id . "-completed"] = ($is_completed) ? 'show' : 'hide';
      $updates_to_sendback[".wpc-content-page-" . $post_id . "-incomplete"] = (!$is_completed) ? 'show' : 'hide';
    } else if ( $_POST['type'] == 'course' ) {
      $course = $_POST['unique_id'];
      $is_completed = ( $this->course_completion_status($course) == 'completed' );
      $updates_to_sendback[".wpc-content-course-" . $this->get_course_class( array( 'course' => $course ) ) . "-completed"] = ($is_completed) ? 'show' : 'hide';
      $updates_to_sendback[".wpc-content-course-" . $this->get_course_class( array( 'course' => $course ) ) . "-incomplete"] = (!$is_completed) ? 'show' : 'hide';
    }

    wp_send_json( $updates_to_sendback );
  }

  /**
   * PREMIUM:
   * Helper function. Accepts supplied arguments, returns criteria needed to properly query posts.
   *
   * @since    2.5.0
   * @last     2.9.0.9
   */
  public function build_post_criteria( $args = array() ) {
    $course = 'all';
    if (isset($args['course']) && !empty($args['course'])) {
      $course = html_entity_decode( $args['course'], ENT_QUOTES | ENT_HTML401 );
    }
    $courses = array( stripslashes( $course ) );
    // if there isn't an exact match for a course name, handle the potential for multiple courses:
    if ( ! in_array( stripslashes( $course ), $this->get_course_names() ) ) {
      $courses = array();
      // but still handle if there's an escaped \, in the course name:
      $tmp_str = str_replace('\,', "**simplelms**", $course);
      $tmp_array = explode( ",", strtolower( str_replace( ", ", ",", $tmp_str ) ) );
      foreach ($tmp_array as $tmp) {
        $courses[] = str_replace( "**simplelms**", ",", $tmp );
      }

    }

    // if we have more than 1 course, then we need to run the criteria builder for each:
    if ( count($courses) > 1 ) {
      $rs = [];
      foreach ($courses as $c ) {
        $args['course'] = $c;
        $rs[] = $this->build_post_criteria($args);
      }
      return $rs;
    } else {
      $defaults = array(
        'depth'        => 0,
        'show_date'    => '',
        'date_format'  => get_option( 'date_format' ),
        'child_of'     => 0,
        'exclude'      => '',
        'include'      => '',
        'title_li'     => '',
        'echo'         => 1,
        'authors'      => '',
        'sort_column'  => 'menu_order,post_title',
        'sort_order'   => 'DESC',
        'orderby'      => 'menu_order,post_title',
        'order'        => 'DESC',
        'link_before'  => '',
        'link_after'   => '',
        'item_spacing' => 'preserve',
        'walker'       => '',
      );

      // make sure $course is properly set to the escaped course name:
      $course = html_entity_decode( strtolower( current($courses) ), ENT_QUOTES | ENT_HTML401 );

      $completable_posts = $this->get_completable_posts('false');
      $post_ids = array();
      foreach ($completable_posts as $post_id => $post) {
        if ( !isset( $post['course'] ) || ( $post['course'] === 'true' ) ) $post['course'] = get_bloginfo('name');
        // clean up the post's course name for comparisons:
        $post_course = html_entity_decode( strtolower( $post['course'] ), ENT_QUOTES | ENT_HTML401 );
        if ( $course == 'all' ) { // All posts on entire site
          $post_ids[] = ''.$post_id;
        // Specific course:
        } else if (
          ( $post_course === $course ) ||
          ( $this->get_course_class( array( 'course' => $post_course ) ) === $this->get_course_class( array( 'course' => $course ) ) )
        ) {
          $post_ids[] = ''.$post_id;
        }
      }

      if ( isset($args['completed']) ) {
        foreach ( $post_ids as $index => $post_id ) {
          if ( isset( $args['user_activity'][$post_id] ) && isset( $args['user_activity'][$post_id]['completed'] ) ) {
            if ( $args['completed'] === 'false' ) {
              unset( $post_ids[$index] );
            }
          } else {
            if ( $args['completed'] === 'true' ) {
              unset( $post_ids[$index] );
            }
          }
        }
      }

      if ( count( $post_ids ) <= 0 ) return false;

      if (isset($args['child_of'])) {
        $child_pages_ids = array();
        // look through all content types (really only handles pages + posts)
        foreach ($this->get_enabled_post_types() as $key => $type) {
          // if we have a requested child_of, grab all the possible ids of the children...
          if ( $child_pages = get_pages( array( 'child_of' => $args['child_of'], 'post_type' => $type ) ) ) {
            // map just IDs...
            foreach ($child_pages as $p) { $child_pages_ids[] = "".$p->ID; }
          }
        }
        // then get rid of any that aren't completable...
        $post_ids = array_intersect( $child_pages_ids, $post_ids );
        //var_dump($post_ids);
        unset($args['child_of']);
      }

      if ( !empty($args['include']) ) {
        $args['include'] = array_intersect( $post_ids, explode( ',', $args['include'] ) );
      } else {
        $args['include'] = $post_ids;
      }

      // Restrict posts to show to only completed or imcomplete entries:
      if ( isset( $args['show'] ) ) {
        $include_ids = array();
        foreach ($args['include'] as $post_id) {
          if ( in_array( $args['show'], array('completed', 'complete') ) ) {
            if ( $this->page_is_completed( $post_id ) ) {
              $include_ids[] = $post_id;
            }
          } else {
            if ( ! $this->page_is_completed( $post_id ) ) {
              $include_ids[] = $post_id;
            }
          }
        }
        $args['include'] = $include_ids;
      }

      $args['include'] = join(',', $args['include']);

      $args['post_type'] = array_values( $this->get_enabled_post_types() );

      if (isset($args['order_by']) && !isset($args['orderby'])) {
        $args['orderby'] = $args['order_by'];
      }
      if (isset($args['sort_column']) && !isset($args['orderby'])) {
        $args['orderby'] = $args['sort_column'];
      }

      $r = wp_parse_args( $args, $defaults );

      if ( ! in_array( $r['item_spacing'], array( 'preserve', 'discard' ), true ) ) {
        // invalid value, fall back to default.
        $r['item_spacing'] = $defaults['item_spacing'];
      }

      // sanitize, mostly to keep spaces out
      $r['exclude'] = preg_replace( '/[^0-9,]/', '', $r['exclude'] );

      // Allow plugins to filter an array of excluded pages (but don't put a nullstring into the array)
      $exclude_array = ( $r['exclude'] ) ? explode( ',', $r['exclude'] ) : array();

      /**
       * Filters the array of pages to exclude from the pages list.
       *
       * @since 2.1.0
       *
       * @param array $exclude_array An array of page IDs to exclude.
       */
      $r['exclude'] = implode( ',', apply_filters( 'wp_list_pages_excludes', $exclude_array ) );

      if (count( explode( ',', $r['exclude'] ) ) > 0) {
        $r['include'] = join( ',', array_diff( explode( ',', $r['include'] ), explode( ',', $r['exclude'] ) ) );
      }

      $r['hierarchical'] = 0;

      return $r;
    }
  }

  /**
   * PREMIUM:
   * Helper to make getting the list's desired page ids in order based on criteria.
   *
   * @since    2.9.0.5
   * @last     2.9.0.5
   */
  public function get_course_posts( $rs ) {
    $pages = [];
    if ( isset($rs['hierarchical']) && ( $rs['hierarchical'] === 0 ) ) {
      if ( empty( $rs['include'] ) ) return array();
      $pages = get_posts( $rs );
    } elseif ($rs !== false) {
      foreach ( $rs as $r ) {
        $pages = array_merge( $pages, get_posts( $r ) );
      }
    }
    return $pages;
  }

  /**
   * PREMIUM:
   * Helper to make getting the list's desired page ids in order based on criteria.
   *
   * @since    2.5.0
   * @last     2.9.0.5
   */
  public function get_list_page_ids($args = array()) {
    $r = $this->build_post_criteria($args);
    // Query pages.
    $pages = $this->get_course_posts( $r );

    if ( isset($r['hierarchical']) && ( $r['hierarchical'] === 0 ) ) {
      // Put through additional list formating similar to wpc_list_pages:
      $output = '';
      if ( ! empty( $pages ) ) {
        if ( $r['walker'] === 'false' ) {
          foreach ($pages as $page) {
            $output .= '<li class="page_item page-item-' . $page->ID . '"><a href="' . esc_url( get_permalink( $page ) ) . '">' . $page->post_title . '</a></li>';
          }
        } else {
          $output .= walk_page_tree( $pages, $r['depth'], 0, $r );
        }
      }
      $html = apply_filters( 'wp_list_pages', $output, $r, $pages );

      // Convert to a more digestable / searchable format:
      $html_items = explode('page_item page-item-', $html);
      // get rid of the first, we don't want it.
      array_shift($html_items);

      $page_ids_in_order = array();
      foreach ( $html_items as $html_item ) {
        $page_id = substr( $html_item, 0, strpos( $html_item, '"><a' ) );
        if ( strpos( $page_id, ' ' ) > 0 )
          $page_id = substr( $page_id, 0, strpos( $page_id, ' ' ) );

        $page_ids_in_order[] = $page_id;
      }
    } else {
      $page_ids_in_order = [];
      foreach ( $pages as $page ) {
        $page_ids_in_order[] = $page->ID;
      }
    }

    return $page_ids_in_order;
  }

  /**
   * PREMIUM:
   * Handles the [wpc_list_completable] or [wpc_list_pages] shortcodes
   *
   * @since    2.2.0
   * @last     2.9.0.8
   */
  public function list_completable_shortcode($atts, $content = null, $tag = '') {
    $this->set_nocache();
    $args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    // need to send in a user's completion activity if we want to filter by this:
    if ( isset( $args['completed'] ) ) {
      $args['user_activity'] = $this->get_user_activity();
    }

    $r = $this->build_post_criteria($args);
    $pages = $this->get_course_posts( $r );
    // Fix if multi-level criteria
    if ( !isset($r['hierarchical']) && ( $r !== false ) ) $r = current($r);

    ob_start();

    if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
      $html = array();
      foreach ($pages as $page) {
        $html[] = array(
          'id' => $page->ID,
          'title' => $page->post_title,
          'url' => esc_url( get_permalink( $page ) )
        );
      }
      //var_dump($args['user_activity']);
      $html = json_encode( $html, JSON_UNESCAPED_UNICODE );
    } else {
      $output = '';
      $current_page = 0;
      if ( ! empty( $pages ) ) {
        // I don't think this is needed, so let's hide it for now and see if anyone complains...
        global $wp_query;
        if ( is_page() || is_attachment() || $wp_query->is_posts_page ) {
          $current_page = get_queried_object_id();
        } elseif ( is_singular() ) {
          $queried_object = get_queried_object();
          if ( is_post_type_hierarchical( $queried_object->post_type ) ) {
            $current_page = $queried_object->ID;
          }
        }
        $output .= '<ul class="wpc-list';
        if ( isset($args['class']) ) {
          $output .= ' ' . esc_attr($args['class']);
        }
        $output .= '"';
        if (isset($args['style'])) {
          $output .= ' style="' . esc_attr($args['style']) . '"';
        }
        $output .= '>';
        if ( $r['walker'] === 'false' ) {
          foreach ($pages as $page) {
            $output .= '<li class="page_item page-item-' . $page->ID . '"><a href="' . esc_url( get_permalink( $page ) ) . '">' . $page->post_title . '</a></li>';
          }
        } else {
          $output .= walk_page_tree( $pages, $r['depth'], $current_page, $r );
        }
        $output .= '</ul>';
      } else if ( isset( $r['empty'] ) ) {
        $output .= '<div class="wpc-list wpc-list-empty">' . html_entity_decode( $r['empty'], ENT_QUOTES | ENT_HTML401 ) . '</div>';
      } else {
        $output .= "<!-- No pages found for " . var_export($atts, true) . " -->";
      }

      $html = apply_filters( 'wp_list_pages', $output, $r, $pages );
    }

    echo $html;
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Filter to get completable pages returned as an array.
   *
   * @since    2.8.0
   * @last     2.8.0
   */
  public function list_completable_filter($atts) {
    $args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    // need to send in a user's completion activity if we want to filter by this:
    if ( isset( $args['completed'] ) ) {
      $args['user_activity'] = $this->get_user_activity();
    }

    $r = $this->build_post_criteria($args);
    $pages = $this->get_course_posts( $r );

    return $pages;
  }

  /**
   * PREMIUM:
   * Handles the [wpc_next_to_complete] shortcode. Output a link to the first/next available post that is completable.
   *
   * @since    2.5.0
   * @last     2.9.0
   */
  public function next_to_complete_shortcode($atts, $content = null, $tag = '') {
    $args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $page_ids_in_order = $this->get_list_page_ids($args);

    ob_start();

    if ( count( $page_ids_in_order ) < 1 ) {
      echo "<p><b>SimpleLMS warning:</b> No pages have been set as completable. You can enable them in your Wordpress admin dashboard.</p>";
      return ob_get_clean();
    }

    $class = 'wpc-nav-next-to-complete';

    // Loop through pages and find the first post that hasn't been completed.
    foreach ( $page_ids_in_order as $page_id ) {
      // is page incomplete?
      if ( ! $this->page_is_completed( $page_id ) ) {
        $page = get_post( $page_id );
        $prepend = '';
        if ( isset( $args['prepend'] ) && !empty( $args['prepend'] ) ) $prepend = $args['prepend'];
        $append = '';
        if ( isset( $args['append'] ) && !empty( $args['append'] ) ) $append = $args['append'];

        $post_url = esc_url( get_permalink( $page ) );

        $post_title = '';
        if ( isset( $args['button_text'] ) && !empty( $args['button_text'] ) ) {
          $post_title = sanitize_text_field($args['button_text']);
        } else {
          $post_title = $page->post_title;
        }

        // if content block has
        if ( $tag == 'wpc_has_next_to_complete' ) {
          echo do_shortcode($content);
        // if content block has no (display nonthing)
        } else if ( $tag == 'wpc_has_no_next_to_complete' ) {
          // nothing
        // if has attribute "link", just return the link
        } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
          echo $post_url;
        // if has attribute "json", return a json string
        } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
          echo json_encode( array(
            "url" => $post_url,
            'id' => $page_id,
            'title' => $page->post_title,
            'display' => html_entity_decode($prepend, ENT_QUOTES | ENT_HTML401) . $post_title . html_entity_decode($append, ENT_QUOTES | ENT_HTML401)
          ), JSON_UNESCAPED_UNICODE );
        } else {
          include 'partials/simple-lms-public-nav-link.php';
        }

        return ob_get_clean();
      }
    }

    // Everything's already completed:
    // if content block has
    if ( $tag == 'wpc_has_next_to_complete' ) {
      // nothing
    // if content block has no
    } else if ( $tag == 'wpc_has_no_next_to_complete' ) {
      echo do_shortcode($content);
    // if has attribute "link", just return the link
    } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
      echo "false";
    // if has attribute "json", return a json string
    } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
      echo json_encode( array( ), JSON_UNESCAPED_UNICODE );
    } else {
      $not_found = '';
      if ( isset( $args['not_found'] ) && !empty( $args['not_found'] ) ) $not_found = sanitize_text_field($args['not_found']);
      $class .= ' wpc-nav-next-to-complete-not-found';
      include 'partials/simple-lms-public-nav-link-not-found.php';
    }
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Handles the [wpc_last_completed] shortcode. Output a link to the last post that was completabled.
   *
   * @since    2.5.0
   * @last     2.9.0
   */
  public function last_completed_shortcode($atts, $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // should replace with button redirect to signup
    $args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $course = 'all';
    if (isset($args['course']) && !empty($args['course'])) {
      $course = $args['course'];
    }

    $user_id = get_current_user_id();
    $total_posts = $this->get_completable_posts();
    $user_completed = $this->get_user_completed();
    $completed_posts = array();

    ob_start();

    foreach ( $total_posts as $post_id => $post ) {
      if ( !isset( $post['course'] ) || ( $post['course'] === 'true' ) ) $post['course'] = get_bloginfo('name');
      // Only include the posts in the courses that we want:
      if ( ( strtolower($course) == 'all' ) || ( strtolower( $post['course'] ) == strtolower( $course ) ) ) {
        // We only want completed pages...
        if ( $this->page_is_completed( $post_id ) ) {
          // If there are multiple buttons, figure out which is the last one clicked:
          if ( isset( $post['buttons'] ) ) {
            foreach ( $post['buttons'] as $button ) {
              // In theory all buttons should be marked completed... but just to be safe...
              if ( isset( $user_completed[$button] ) && isset( $user_completed[$button]['completed'] ) && !empty( $user_completed[$button]['completed'] ) ) {
                if ( ! isset($completed_posts[$post_id]) || ( $user_completed[$button]['completed'] > $completed_posts[$post_id] ) ) {
                  $completed_posts[$post_id] = $user_completed[$button]['completed'];
                }
              }
            }
          // Otherwise, we probably just have the default button:
          } else {
            // In theory the button should be marked completed... but just to be safe...
            if ( isset( $user_completed[$post_id] ) && isset( $user_completed[$post_id]['completed'] ) && !empty( $user_completed[$post_id]['completed'] ) ) {
              $completed_posts[$post_id] = $user_completed[$post_id]['completed'];
            }
          }
        }
      }
    }

    $class = 'wpc-nav-last-completed';
    if ( count( $completed_posts ) > 0 ) {
      // sort by reversed completion date (value) (newest to oldest)
      arsort( $completed_posts );
      // get the id of the most recently completed post:
      $last_page_id = current( array_keys( $completed_posts ) );
      $last_page = get_post( $last_page_id );

      $prepend = '';
      if ( isset( $args['prepend'] ) && !empty( $args['prepend'] ) ) $prepend = sanitize_text_field($args['prepend']);
      $append = '';
      if ( isset( $args['append'] ) && !empty( $args['append'] ) ) $append = sanitize_text_field($args['append']);

      $post_url = esc_url( get_permalink( $last_page ) );

      $post_title = '';
      if ( isset( $args['button_text'] ) && !empty( $args['button_text'] ) ) {
        $post_title = sanitize_text_field($args['button_text']);
      } else {
        $post_title = $last_page->post_title;
      }

      // if content block has
      if ( $tag == 'wpc_has_last_completed' ) {
        echo do_shortcode($content);
      // if content block has not (display nonthing)
      } else if ( $tag == 'wpc_has_no_last_completed' ) {
        // nothing
      // if has attribute "link", just return the link
      } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
        echo $post_url;
      // if has attribute "json", return a json string
      } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
        echo json_encode( array(
          "url" => $post_url,
          'id' => $last_page_id,
          'title' => $last_page->post_title,
          'display' => html_entity_decode($prepend, ENT_QUOTES | ENT_HTML401) . $post_title . html_entity_decode($append, ENT_QUOTES | ENT_HTML401)
        ), JSON_UNESCAPED_UNICODE );
      } else {
        include 'partials/simple-lms-public-nav-link.php';
      }
    } else {
      if ( $tag == 'wpc_has_last_completed' ) {
        // nothing
      // if content block has no
      } else if ( $tag == 'wpc_has_no_last_completed' ) {
        echo do_shortcode($content);
      // if has attribute "link", just return the link
      } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
        echo "false";
      // if has attribute "json", return a json string
      } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
        echo json_encode( array( ), JSON_UNESCAPED_UNICODE );
      } else {
        $not_found = '';
        if ( isset( $args['not_found'] ) && !empty( $args['not_found'] ) ) $not_found = sanitize_text_field($args['not_found']);
        $class .= ' wpc-nav-last-completed-not-found';
        include 'partials/simple-lms-public-nav-link-not-found.php';
      }
    }

    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Handles the [wpc_next_page] shortcode. Output a link to the next completable post.
   *
   * @since    2.5.0
   * @last     2.9.0
   */
  public function next_page_shortcode($atts, $content = null, $tag = '') {
    $args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $page_ids_in_order = $this->get_list_page_ids($args);

    $post_id = get_the_ID();
    if ( isset( $atts['page'] ) && !empty( $atts['page'] ) ) {
      $post_id = $atts['page'];
    } else if ( isset( $atts['page_id'] ) && !empty( $atts['page_id'] ) ) {
      $post_id = $atts['page_id'];
    } else if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
      $post_id = $atts['post'];
    } else if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
      $post_id = $atts['post_id'];
    }

    ob_start();

    $next_page = false;
    // Loop through all the page ids based on menu criteria:
    foreach ( $page_ids_in_order as $key => $p_id ) {
      // If we find our current page...
      if ( $p_id == $post_id ) {
        // grab the next page (if it exists):
        if ( isset( $page_ids_in_order[$key+1] ) ) {
          $next_page = get_post($page_ids_in_order[$key+1]);
          break;
        }
      }
    }

    $class = 'wpc-nav-next-page';
    if ( $next_page ) {
      $prepend = '';
      if ( isset( $args['prepend'] ) && !empty( $args['prepend'] ) ) $prepend = sanitize_text_field($args['prepend']);
      $append = '';
      if ( isset( $args['append'] ) && !empty( $args['append'] ) ) $append = sanitize_text_field($args['append']);

      $post_url = esc_url( get_permalink( $next_page ) );

      $post_title = '';
      if ( isset( $args['button_text'] ) && !empty( $args['button_text'] ) ) {
        $post_title = sanitize_text_field($args['button_text']);
      } else {
        $post_title = $next_page->post_title;
      }

      if ( $tag == 'wpc_has_next_page' ) {
        echo do_shortcode($content);
      // if content block has not (display nonthing)
      } else if ( $tag == 'wpc_has_no_next_page' ) {
        // nothing
      // if has attribute "link", just return the link
      } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
        echo $post_url;
      // if has attribute "json", return a json string
      } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
        echo json_encode( array(
          "url" => $post_url,
          'id' => $next_page->ID,
          'title' => $next_page->post_title,
          'display' => html_entity_decode($prepend, ENT_QUOTES | ENT_HTML401) . $post_title . html_entity_decode($append, ENT_QUOTES | ENT_HTML401)
        ), JSON_UNESCAPED_UNICODE );
      } else {
        include 'partials/simple-lms-public-nav-link.php';
      }
    } else {
      if ( $tag == 'wpc_has_next_page' ) {
        // nothing
      // if content block has no
      } else if ( $tag == 'wpc_has_no_next_page' ) {
        echo do_shortcode($content);
      // if has attribute "link", just return the link
      } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
        echo "false";
      // if has attribute "json", return a json string
      } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
        echo json_encode( array( ), JSON_UNESCAPED_UNICODE );
      } else {
        $not_found = '';
        if ( isset( $args['not_found'] ) && !empty( $args['not_found'] ) ) $not_found = sanitize_text_field($args['not_found']);
        $class .= ' wpc-nav-next-page-not-found';
        include 'partials/simple-lms-public-nav-link-not-found.php';
      }
    }

    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Handles the [wpc_previous_page] shortcode. Output a link to the previous completable post.
   *
   * @since    2.5.0
   * @last     2.9.0
   */
  public function previous_page_shortcode($atts, $content = null, $tag = '') {
    $args = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $page_ids_in_order = $this->get_list_page_ids($args);

    $post_id = get_the_ID();
    if ( isset( $atts['page'] ) && !empty( $atts['page'] ) ) {
      $post_id = $atts['page'];
    } else if ( isset( $atts['page_id'] ) && !empty( $atts['page_id'] ) ) {
      $post_id = $atts['page_id'];
    } else if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
      $post_id = $atts['post'];
    } else if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
      $post_id = $atts['post_id'];
    }

    ob_start();

    $prev_page = false;
    // Loop through all the page ids based on menu criteria:
    foreach ( $page_ids_in_order as $key => $p_id ) {
      // If we find our current page...
      if ( $p_id == $post_id ) {
        // grab the previous page (if it exists):
        if ( isset( $page_ids_in_order[$key-1] ) ) {
          $prev_page = get_post($page_ids_in_order[$key-1]);
          break;
        }
      }
    }

    $class = 'wpc-nav-previous-page';
    if ( $prev_page ) {
      $prepend = '';
      if ( isset( $args['prepend'] ) && !empty( $args['prepend'] ) ) $prepend = sanitize_text_field($args['prepend']);
      $append = '';
      if ( isset( $args['append'] ) && !empty( $args['append'] ) ) $append = sanitize_text_field($args['append']);

      $post_url = esc_url( get_permalink( $prev_page ) );

      $post_title = '';
      if ( isset( $args['button_text'] ) && !empty( $args['button_text'] ) ) {
        $post_title = sanitize_text_field($args['button_text']);
      } else {
        $post_title = $prev_page->post_title;
      }

      if ( $tag == 'wpc_has_previous_page' ) {
        echo do_shortcode($content);
      // if content block has not (display nonthing)
      } else if ( $tag == 'wpc_has_no_previous_page' ) {
        // nothing
      // if has attribute "link", just return the link
      } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
        echo $post_url;
      // if has attribute "json", return a json string
      } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
        echo json_encode( array(
          "url" => $post_url,
          'id' => $prev_page->ID,
          'title' => $prev_page->post_title,
          'display' => html_entity_decode($prepend, ENT_QUOTES | ENT_HTML401) . $post_title . html_entity_decode($append, ENT_QUOTES | ENT_HTML401)
        ), JSON_UNESCAPED_UNICODE );
      } else {
        include 'partials/simple-lms-public-nav-link.php';
      }
    } else {
      if ( $tag == 'wpc_has_previous_page' ) {
        // nothing
      // if content block has no
      } else if ( $tag == 'wpc_has_no_previous_page' ) {
        echo do_shortcode($content);
      // if has attribute "link", just return the link
      } else if ( isset( $args['link'] ) && ( $args['link'] === 'true' ) ) {
        echo "false";
      // if has attribute "json", return a json string
      } else if ( isset( $args['json'] ) && ( $args['json'] === 'true' ) ) {
        echo json_encode( array( ), JSON_UNESCAPED_UNICODE );
      } else {
        $not_found = '';
        if ( isset( $args['not_found'] ) && !empty( $args['not_found'] ) ) $not_found = sanitize_text_field($args['not_found']);
        $class .= ' wpc-nav-previous-page-not-found';
        include 'partials/simple-lms-public-nav-link-not-found.php';
      }
    }

    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Handles the [wpc_peer_pressure] shortcode
   * Can supply: course, zero, single, plural, completed attributes.
   *
   * @since    2.2.0
   * @last     2.7.1
   */
  public function peer_pressure_shortcode($atts = array(), $content = null, $tag = '') {
    #if ( ! is_user_logged_in() ) return; // dont show for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    if ( isset( $atts['course'] ) && !empty( $atts['course'] ) ) {
      if ( count( $this->get_course_buttons($atts['course']) ) < 1 ) {
        return "<!-- No buttons found for course: " . $atts['course'] . " -->";
      }

      $zero = 'Be the first to complete this course!';
      if ( isset( $atts['zero'] ) ) $zero = $atts['zero'];
      if ($zero == 'false') $zero = '';
      $single = "1 person has already completed this course!";
      if ( isset( $atts['single'] ) && !empty( $atts['single'] ) ) $single = $atts['single'];
      $plural = "{number} people have already completed this!";
      if ( isset( $atts['plural'] ) && !empty( $atts['plural'] ) ) $plural = $atts['plural'];
      $completed = "Yay, you've completed this course!";
      if ( isset( $atts['completed'] ) ) $completed = $atts['completed'];
      if ($completed == 'false') $completed = '';

      $uid = $this->get_course_class($atts['course']);

      $stats = $this->get_peer_pressure_course_stats($atts['course']);
    } else {
      $post_id = get_the_ID();
      if ( isset( $atts['page'] ) && !empty( $atts['page'] ) ) {
        $post_id = $atts['page'];
      } else if ( isset( $atts['page_id'] ) && !empty( $atts['page_id'] ) ) {
        $post_id = $atts['page_id'];
      } else if ( isset( $atts['post'] ) && !empty( $atts['post'] ) ) {
        $post_id = $atts['post'];
      } else if ( isset( $atts['post_id'] ) && !empty( $atts['post_id'] ) ) {
        $post_id = $atts['post_id'];
      }

      // dont show if post isn't completable
      if ( ! in_array( get_post_type( $post_id ), $this->get_enabled_post_types() ) ) return;
      if ( ! $this->post_can_complete( $post_id ) ) return;

      $uid = $post_id;

      $zero = 'Be the first to complete this!';
      if ( isset( $atts['zero'] ) ) $zero = $atts['zero'];
      if ($zero == 'false') $zero = '';
      $single = "1 person has already completed this!";
      if ( isset( $atts['single'] ) && !empty( $atts['single'] ) ) $single = $atts['single'];
      $plural = "{number} people have already completed this!";
      if ( isset( $atts['plural'] ) && !empty( $atts['plural'] ) ) $plural = $atts['plural'];
      $completed = "Yay, you've completed this!";
      if ( isset( $atts['completed'] ) ) $completed = $atts['completed'];
      if ($completed == 'false') $completed = '';

      $stats = $this->get_peer_pressure_stats($post_id);
    }

    ob_start();

    if ($stats === false) {
      $output = '<!-- Tracking user count is 0 -->';
    } else {
      $output = '';
      $output .= '<div class="wpc-peer-pressure wpc-peer-pressure-' . $uid . '" data-zero="' . $zero . '" data-single="' . $single . '" data-plural="' . $plural . '" data-completed="' . $completed . '">';

      // 2. if any, run $copy through a replace and display that.
      if ( $stats['user_completed'] ) {
        $copy = $completed;
      } else if ( $stats['{number}'] > 0 ) {
        $copy = ($stats['{number}'] == 1) ? $single : $plural;
      } else {
        // 3. if there are zero, just display empty text.
        $copy = $zero;
      }

      $copy = str_ireplace(
        array( '{number}', '{percentage}', '{next_with_ordinal}' ),
        array(
          $stats['{number}'],
          $stats['{percentage}'],
          $stats['{next_with_ordinal}']
        ),
        $copy
      );
      $output .= $copy;

      $output .= '</div>';
    }

    echo $output;
    return ob_get_clean();
  }

  /**
   * PREMIUM:
   * Helper for peer pressure short code.
   * Accepts the post_id and the user_id (defaults to current logged in user)
   *
   * @since    2.2.0
   * @last     2.3.4
   */
  public function get_peer_pressure_stats($post_id, $user_id = false) {
    global $wpdb;

    if ( !$user_id ) $user_id = get_current_user_id();

    // 1. grab the total number of users completed for this post
    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    // Get all users that are able to complete the post:
    $args = array('fields' => 'ID');
    if ($selected_role != 'all') $args['role'] = $selected_role;
    $args['meta_key'] = 'simple-lms';
    $total_users = get_users($args);

    // Bail if we don't have any users to track.
    if (count($total_users) < 1) return false;
    // Bail if we have TOO many users to track.
    if (count($total_users) > 10000) return false;

    $user_ids = join( ",", $total_users );
    $SQL = "SELECT b.user_id, b.meta_value FROM $wpdb->usermeta b WHERE ( b.meta_key = 'simple-lms' ) AND ( b.user_id IN ($user_ids) )";

    $total_users_meta = $wpdb->get_results( $SQL, ARRAY_A );

    $is_completed_by_user = false;

    $user_completed = array();
    foreach ($total_users_meta as $user) {
      $user_completed_json = $user['meta_value'];
      $user_completed_raw = ( $user_completed_json ) ? json_decode( $user_completed_json, true ) : array();

      //$user_completed_raw = $this->get_user_completed( $user->ID );
      if ( isset( $user_completed_raw[$post_id] ) && isset( $user_completed_raw[$post_id]['completed'] ) ) {
        if ($user_completed_raw[$post_id]['completed'] === true) {
          $user_completed_raw[$post_id]['completed'] = 'Yes';
        }
        $user_completed[$user['user_id']] = $user_completed_raw[$post_id]['completed'];
        if ($user['user_id'] == $user_id) $is_completed_by_user = true;
      }
    }
    // just double check to see if user has confirmed but isn't the right user type...
    if ($user_id && !$is_completed_by_user) {
      $user_completed_raw = $this->get_user_completed();
      if ( isset( $user_completed_raw[$post_id] ) && isset( $user_completed_raw[$post_id]['completed'] ) ) {
        $is_completed_by_user = true;
      }
    }

    $count = count($user_completed);
    $ord = array('st', 'nd', 'rd');

    $stats = array(
      'post_id' => $post_id,
      'user_completed' => $is_completed_by_user,
      '{number}' => $count,
      '{percentage}' => (round(100 * ( $count / count($total_users) ), 0) . '%'),
      '{next_with_ordinal}' => (($count+1).((($j=abs($count+1)%100)>10&&$j<14) ? 'th' : ((($j%=10)>0&&$j<4) ? $ord[$j-1] : 'th')))
    );

    return $stats;
  }

  /**
   * PREMIUM:
   * Helper for peer pressure short code.
   * Accepts the course name and the user_id (defaults to current logged in user)
   *
   * @since    2.7.1
   * @last     2.7.1
   */
  public function get_peer_pressure_course_stats($course, $user_id = false) {
    global $wpdb;

    $this->set_nocache();

    if ( !$user_id ) $user_id = get_current_user_id();

    // 1. grab the total number of users completed for this post
    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    // Get all users that are able to complete the post:
    $args = array('fields' => 'ID');
    if ($selected_role != 'all') $args['role'] = $selected_role;
    $args['meta_key'] = 'simple-lms';
    $total_users = get_users($args);

    // Bail if we don't have any users to track.
    if (count($total_users) < 1) return false;
    // Bail if we have TOO many users to track.
    if (count($total_users) > 10000) return false;

    $user_ids = join( ",", $total_users );
    $SQL = "SELECT b.user_id, b.meta_value FROM $wpdb->usermeta b WHERE ( b.meta_key = 'simple-lms' ) AND ( b.user_id IN ($user_ids) )";

    $total_users_meta = $wpdb->get_results( $SQL, ARRAY_A );

    $is_completed_by_user = false;

    $course_buttons = $this->get_course_buttons($course);

    $user_completed = array();
    foreach ($total_users_meta as $user) {
      $user_completed_json = $user['meta_value'];
      $user_completed_raw = ( $user_completed_json ) ? json_decode( $user_completed_json, true ) : array();

      $count = 0;
      foreach ($course_buttons as $button) {
        if ( isset( $user_completed_raw[$button] ) && isset( $user_completed_raw[$button]['completed'] ) ) {
          $count++;
        }
      }

      if ( $count >= count($course_buttons) ) {
        $user_completed[$user['user_id']] = true;

        if ($user['user_id'] == $user_id) $is_completed_by_user = true;
      }

    }
    // just double check to see if user has confirmed but isn't the right user type...
    if ($user_id && !$is_completed_by_user) {
      if ( $this->course_completion_status($course) == 'completed' ) {
        $is_completed_by_user = true;
      }
    }

    $count = count($user_completed);
    $ord = array('st', 'nd', 'rd');

    $stats = array(
      'course' => $course,
      'user_completed' => $is_completed_by_user,
      '{number}' => $count,
      '{percentage}' => (round(100 * ( $count / count($total_users) ), 0) . '%'),
      '{next_with_ordinal}' => (($count+1).((($j=abs($count+1)%100)>10&&$j<14) ? 'th' : ((($j%=10)>0&&$j<4) ? $ord[$j-1] : 'th')))
    );

    return $stats;
  }

  /**
   * PREMIUM:
   * Handles the [wpc_reset] shortcode
   * Can supply: text, course, class, confirm, success_text, failure_text
   *
   * @since    2.4.0
   * @last     2.9.0.7
   */
  public function reset_shortcode($atts = array(), $content = null, $tag = '') {
    if ( ! is_user_logged_in() ) return; // dont show for logged out users
    $atts = array_change_key_case((array)$atts, CASE_LOWER); // normalize attribute keys, lowercase

    $reset_url = admin_url( 'admin-post.php?action=reset');

    $text = 'Reset Your Data';
    if ( isset( $atts['text'] ) && !empty( $atts['text'] ) ) {
      $text = sanitize_text_field($atts['text']);
    }
    $course = false;
    if ( isset( $atts['course'] ) && !empty( $atts['course'] ) ) {
      $course = sanitize_text_field($atts['course']);
      $reset_url = admin_url( 'admin-post.php?action=reset&course=' . urlencode($course));
    }
    $classes = '';
    if ( isset( $atts['class'] ) && !empty( $atts['class'] ) ) {
      $classes = sanitize_html_class($atts['class']);
    }
    $confirm_message = "Are you sure you want to reset your data? This can not be undone.";
    if ( isset( $atts['confirm'] ) ) {
      $confirm_message = sanitize_text_field($atts['confirm']);
    }
    $success_text = "Your account data was successfully removed.";
    if ( isset( $atts['success_text'] ) ) {
      $success_text = sanitize_text_field($atts['success_text']);
    }
    $failure_text = "Unfortunately, there was an error while trying to delete your completion data. Please try again or notify the site owner.";
    if ( isset( $atts['failure_text'] ) ) {
      $failure_text = sanitize_text_field($atts['failure_text']);
    }
    $no_change_text = "Completion data has already been deleted.";
    if ( isset( $atts['no_change_text'] ) ) {
      $no_change_text = sanitize_text_field($atts['no_change_text']);
    }

    // add a nonce
    $reset_url = wp_nonce_url( $reset_url, 'reset-account_' . get_current_user_id() );
    $reset_nonce = wp_create_nonce( 'reset-account_' . get_current_user_id() );

    ob_start();
    // Start displaying reset link:
    include 'partials/simple-lms-public-reset-link.php';

    return ob_get_clean();

  }

  /**
   * PREMIUM:
   * Handles the request from a [wpc_reset] shortcode
   * Can supply: course
   *
   * @since    2.4.0
   * @last     2.9.0.7
   */
  public function reset_account() {
    check_ajax_referer( 'reset-account_' . get_current_user_id(), '_ajax_nonce' );

    $user_id = get_current_user_id();
    $wp_ref = wp_get_referer();
    $updates_to_sendback = array();
    $atts = array();

    if ( isset( $_REQUEST['course'] ) && !empty( $_REQUEST['course'] ) ) {
      // if course is supplied, get all of user's completion data...
      $data = $this->get_user_activity($user_id);

      $original_keys = array_keys( $data );
      // remove data for supplied course...
      // get all the buttons that belong to a course...
      $buttons = $this->get_course_buttons($_REQUEST['course']);
      // loop through user data and unset those button data
      foreach ( $buttons as $button ) {
        if ( isset( $data[$button]['completed'] ) ) {
          unset( $data[$button] );
        }
      }

      if ( $original_keys == array_keys( $data ) ) {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
          $updates_to_sendback['wpc-reset'] = 'no-change';
          echo json_encode( $updates_to_sendback, JSON_UNESCAPED_UNICODE );
          die();
        } else {
          // TODO: how do we handle multiple buttons?
          // redirect back to referral page
          wp_safe_redirect( add_query_arg('wpc_reset', 'no-change', $wp_ref ) );
          return;
        }
      }

      // save back to database
      $saved = $this->set_user_activity($data, $user_id);
      if ( $saved ) {
        // Reset graphs for just this course...
        $course = $_REQUEST['course'];
        $updates_to_sendback['.wpc-content-course-' . $this->get_course_class( array( 'course' => $course ) ) . '-incomplete'] = 'show';
        $updates_to_sendback['.wpc-content-course-' . $this->get_course_class( array( 'course' => $course ) ) . '-completed'] = 'hide';
        $updates_to_sendback['.simple-lms-progress-ratio.' . $this->get_course_class( array('course' => $course) )] = $this->progress_ratio_cb( array('course' => $course) );
        $updates_to_sendback['.simple-lms-progress-percentage.' . $this->get_course_class( array('course' => $course) )] = $this->progress_percentage_cb( array('course' => $course) );
        $updates_to_sendback['.simple-lms-progress-ratio.all-courses'] = $this->progress_ratio_cb( array('course' => 'all') );
        $updates_to_sendback['.simple-lms-progress-percentage.all-courses'] = $this->progress_percentage_cb( array('course' => 'all') );
        $updates_to_sendback['.' . $this->get_course_class( array('course' => $course) ) . '[data-progress]'] = $this->get_percentage( array('course' => $course) );
        $updates_to_sendback['.all-courses[data-progress]'] = $this->get_percentage(array('course' => 'all') );
        // Add action for other plugins to hook in:
        do_action( 'simplelms_reset_course', array('user_id' => $user_id, 'course' => $course ) );
      }
    } else {
      // otherwise just delete all user's completion data...
      if ( $this->get_user_activity($user_id) == array() ) {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
          $updates_to_sendback['wpc-reset'] = 'no-change';
          echo json_encode( $updates_to_sendback, JSON_UNESCAPED_UNICODE );
          die();
        } else {
          // TODO: how do we handle multiple buttons?
          // redirect back to referral page
          wp_safe_redirect( add_query_arg('wpc_reset', 'no-change', $wp_ref ) );
          return;
        }
      } else {

        $saved = $this->set_user_activity(array(), $user_id);
        if ( $saved ) {
          // Update graphs to not be completed:
          $updates_to_sendback['.simple-lms-progress-ratio.all-courses'] = $this->progress_ratio_cb( array('course' => 'all') );
          $updates_to_sendback['.simple-lms-progress-percentage.all-courses'] = $this->progress_percentage_cb( array('course' => 'all') );
          $updates_to_sendback['.all-courses[data-progress]'] = $this->get_percentage(array('course' => 'all') );
          // Loop through all courses to update all graphs...
          foreach ( array_merge( $courseNames = $this->get_course_names(), array( get_bloginfo( 'name' ) ) ) as $course ) {
            $courseName = $this->get_course_class( array( 'course' => $course ) );
            $updates_to_sendback['.wpc-content-course-' . $courseName . '-incomplete'] = 'show';
            $updates_to_sendback['.wpc-content-course-' . $courseName . '-completed'] = 'hide';
            $updates_to_sendback['.simple-lms-progress-ratio.' . $courseName] = $this->progress_ratio_cb( array('course' => $course) );
            $updates_to_sendback['.simple-lms-progress-percentage.' . $courseName] = $this->progress_percentage_cb( array('course' => $course) );
            $updates_to_sendback['.' . $courseName . '[data-progress]'] = $this->get_percentage( array('course' => $course) );
          }
          // Add action for other plugins to hook in:
          do_action( 'simplelms_reset', array('user_id' => $user_id ) );
        }

      }

    }

    if (defined('DOING_AJAX') && DOING_AJAX) {
      $updates_to_sendback['wpc-reset'] = ($saved) ? 'success' : 'failed';
      echo json_encode( $updates_to_sendback, JSON_UNESCAPED_UNICODE );
      die();
    } else {
      // TODO: how do we handle multiple buttons?
      // redirect back to referral page
      wp_safe_redirect( add_query_arg('wpc_reset', ( ($saved) ? 'success' : 'failed' ), $wp_ref ) );
    }
  }

}
