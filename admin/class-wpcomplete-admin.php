<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wpcomplete.co
 * @since      1.0.0
 * @last       2.0.0
 *
 * @package    WPComplete
 * @subpackage wpcomplete/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WPComplete
 * @subpackage wpcomplete/admin
 * @author     Zack Gilbert <zack@zackgilbert.com>
 */
class WPComplete_Admin extends WPComplete_Common {

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
   * Register the stylesheets for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_styles() {

    /**
     * This function is provided for demonstration purposes only.
     *
     * An instance of this class should be passed to the run() function
     * defined in Plugin_Name_Loader as all of the hooks are defined
     * in that particular class.
     *
     * The WPComplete_Loader will then create the relationship
     * between the defined hooks and the functions defined in this
     * class.
     */

    wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wpcomplete-admin.css', array('wp-color-picker'), $this->version, 'all' );

  }

  /**
   * Register the JavaScript for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_scripts() {
    global $pagenow;

    if ( !in_array( $pagenow, array( 'edit-tags.php' ) ) ) {
      $deps = array( 'jquery', 'jquery-ui-autocomplete', 'wp-color-picker', 'inline-edit-post' );

      wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wpcomplete-admin.js', $deps, $this->version, true );

      $admin_nonce = wp_create_nonce( 'wpc_admin_nonce' );

      $params = array(
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => $admin_nonce,
      );

      wp_localize_script( $this->plugin_name, WPCOMPLETE_PRODUCT_NAME, $params );
    }
  }

  /**
   * Add WPComplete specific dashboard widget
   *
   * @since  2.0.0
   * @last   2.3.2
   */
  public function add_dashboard_widget() {
    if ( current_user_can( 'administrator' ) && ( get_option( $this->plugin_name . '_show_widget', 'true' ) === 'true' ) ) {
      wp_add_dashboard_widget( $this->plugin_name . '-course-statistics', 'WPComplete Course Statistics', array( $this, 'add_dashboard_widget_cb' ) );
    }
  }

  /**
   * Callback for WPComplete dashboard widget. Adds actual content.
   *
   * @since  2.0.0
   * @last   2.9.0
   */
  public function add_dashboard_widget_cb() {
    $posts = $this->get_completable_posts();
    if ( count( $posts ) <= 0 ) {
      include_once 'partials/wpcomplete-admin-widget-empty.php';
      return;
    }

    $courses = $this->get_course_stats( $posts );

    include_once 'partials/wpcomplete-admin-widget.php';
  }

  /**
   * Add WPComplete specific page under the Settings submenu.
   *
   * @since  1.0.0
   */
  public function add_options_page() {

    $this->plugin_screen_hook_suffix = add_options_page(
      __( 'WPComplete Settings', $this->plugin_name ),
      __( WPCOMPLETE_PRODUCT_NAME, $this->plugin_name ),
      'manage_options',
      $this->plugin_name,
      array( $this, 'display_settings_page' )
    );

  }

  /**
   * Render the WPComplete specific settings page for plugin.
   *
   * @since  1.0.0
   */
  public function display_settings_page() {
    include_once 'partials/wpcomplete-admin-display.php';
  }

  /**
   * Build all the settings for plugin on the WPComplete settings page.
   *
   * @since  1.0.0
   */
  public function register_settings() {
    // PREMIUM:
    register_setting( $this->plugin_name, $this->plugin_name . '_license_key', array( $this, 'sanitize_license' ) );

    // Section related to students:
    add_settings_section(
      $this->plugin_name . '_general',
      __( 'General Settings', $this->plugin_name ),
      array( $this, 'settings_section_cb' ),
      $this->plugin_name . '_general'
    );

    add_settings_field(
      $this->plugin_name . '_role',
      __( 'Completable User Types', $this->plugin_name ),
      array( $this, 'settings_role_cb' ),
      $this->plugin_name . '_general',
      $this->plugin_name . '_general',
      array( 'label_for' => $this->plugin_name . '_role' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_general', $this->plugin_name . '_role', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_post_type',
      __( 'Completable Content Types', $this->plugin_name ),
      array( $this, 'settings_post_type_cb' ),
      $this->plugin_name . '_general',
      $this->plugin_name . '_general',
      array( 'label_for' => $this->plugin_name . '_post_type' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_general', $this->plugin_name . '_post_type', array('sanitize_callback' => array( $this, 'sanitize_post_types_cb' ) ) );

    add_settings_field(
      $this->plugin_name . '_auto_enable',
      '',
      array( $this, 'settings_auto_enable_cb' ),
      $this->plugin_name . '_general',
      $this->plugin_name . '_general',
      array()
    );
    register_setting( $this->plugin_name . '_general', $this->plugin_name . '_auto_enable', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_auto_append',
      '',
      array( $this, 'settings_auto_append_cb' ),
      $this->plugin_name . '_general',
      $this->plugin_name . '_general',
      array()
    );
    register_setting( $this->plugin_name . '_general', $this->plugin_name . '_auto_append', 'sanitize_text_field' );

    // Section related to the Mark as Complete button:
    add_settings_section(
      $this->plugin_name . '_incomplete_button',
      __( 'Mark Complete Button', $this->plugin_name ),
      array( $this, 'settings_section_cb' ),
      $this->plugin_name . '_buttons'
    );

    add_settings_field(
      $this->plugin_name . '_incomplete_text',
      __( 'Button Text', $this->plugin_name ),
      array( $this, 'settings_incomplete_text_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_incomplete_button',
      array( 'label_for' => $this->plugin_name . '_incomplete_text' )
    );
    register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_incomplete_text', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_incomplete_active_text',
      __( 'Saving Text', $this->plugin_name ),
      array( $this, 'settings_incomplete_active_text_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_incomplete_button',
      array( 'label_for' => $this->plugin_name . '_incomplete_active_text' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_incomplete_active_text', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_incomplete_background',
      __( 'Button Color', $this->plugin_name ),
      array( $this, 'settings_incomplete_background_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_incomplete_button',
      array( 'label_for' => $this->plugin_name . '_incomplete_background' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_incomplete_background', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_incomplete_color',
      __( 'Button Text Color', $this->plugin_name ),
      array( $this, 'settings_incomplete_color_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_incomplete_button',
      array( 'label_for' => $this->plugin_name . '_incomplete_color' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_incomplete_color', 'sanitize_text_field' );

    // Section related to the Completed! button:
    add_settings_section(
      $this->plugin_name . '_completed_button',
      __( 'Completed Button', $this->plugin_name ),
      array( $this, 'settings_section_cb' ),
      $this->plugin_name . '_buttons'
    );

    add_settings_field(
      $this->plugin_name . '_completed_text',
      __( 'Button Text', $this->plugin_name ),
      array( $this, 'settings_completed_text_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_completed_button',
      array( 'label_for' => $this->plugin_name . '_completed_text' )
    );
    register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_completed_text', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_completed_active_text',
      __( 'Saving Text', $this->plugin_name ),
      array( $this, 'settings_completed_active_text_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_completed_button',
      array( 'label_for' => $this->plugin_name . '_completed_active_text' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_completed_active_text', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_completed_background',
      __( 'Button Color', $this->plugin_name ),
      array( $this, 'settings_completed_background_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_completed_button',
      array( 'label_for' => $this->plugin_name . '_completed_background' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_completed_background', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_completed_color',
      __( 'Button Text Color', $this->plugin_name ),
      array( $this, 'settings_completed_color_cb' ),
      $this->plugin_name . '_buttons',
      $this->plugin_name . '_completed_button',
      array( 'label_for' => $this->plugin_name . '_completed_color' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_buttons', $this->plugin_name . '_completed_color', 'sanitize_text_field' );

    // PREMIUM: Section related to the graphs:
    add_settings_section(
      $this->plugin_name . '_graphs',
      __( 'Graph Settings', $this->plugin_name ),
      array( $this, 'settings_section_cb' ),
      $this->plugin_name . '_graphs'
    );

    // Bar Graph Theme: classic | none
    add_settings_field(
      $this->plugin_name . '_bar_theme',
      __( 'Bar Graph Theme', $this->plugin_name ),
      array( $this, 'settings_bar_theme_cb' ),
      $this->plugin_name . '_graphs',
      $this->plugin_name . '_graphs',
      array( 'label_for' => $this->plugin_name . '_bar_theme' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_graphs', $this->plugin_name . '_bar_theme', 'sanitize_text_field' );

    // Radial Graph Theme: classic | none
    add_settings_field(
      $this->plugin_name . '_radial_theme',
      __( 'Radial Graph Theme', $this->plugin_name ),
      array( $this, 'settings_radial_theme_cb' ),
      $this->plugin_name . '_graphs',
      $this->plugin_name . '_graphs',
      array( 'label_for' => $this->plugin_name . '_radial_theme' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_graphs', $this->plugin_name . '_radial_theme', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_graph_primary',
      __( 'Primary Color', $this->plugin_name ),
      array( $this, 'settings_graph_primary_cb' ),
      $this->plugin_name . '_graphs',
      $this->plugin_name . '_graphs',
      array( 'label_for' => $this->plugin_name . '_graph_primary' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_graphs', $this->plugin_name . '_graph_primary', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_graph_secondary',
      __( 'Secondary Color', $this->plugin_name ),
      array( $this, 'settings_graph_secondary_cb' ),
      $this->plugin_name . '_graphs',
      $this->plugin_name . '_graphs',
      array( 'label_for' => $this->plugin_name . '_graph_secondary' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_graphs', $this->plugin_name . '_graph_secondary', 'sanitize_text_field' );

    // PREMIUM: Section related to advanced features:
    add_settings_section(
      $this->plugin_name . '_advanced',
      __( 'Advanced Settings', $this->plugin_name ),
      array( $this, 'settings_section_cb' ),
      $this->plugin_name . '_advanced'
    );

    add_settings_field(
      $this->plugin_name . '_zapier',
      __( 'Zapier Webhook (beta)', $this->plugin_name ),
      array( $this, 'settings_zapier_cb' ),
      $this->plugin_name . '_advanced',
      $this->plugin_name . '_advanced',
      array( 'label_for' => $this->plugin_name . '_zapier' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_advanced', $this->plugin_name . '_zapier', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_custom_styles',
      __( 'Custom Styles (CSS)', $this->plugin_name ),
      array( $this, 'settings_custom_styles_cb' ),
      $this->plugin_name . '_advanced',
      $this->plugin_name . '_advanced',
      array( 'label_for' => $this->plugin_name . '_custom_styles' )
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_advanced', $this->plugin_name . '_custom_styles', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_show_widget',
      '',
      array( $this, 'settings_show_widget_cb' ),
      $this->plugin_name . '_advanced',
      $this->plugin_name . '_advanced',
      array()
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_advanced', $this->plugin_name . '_show_widget', 'sanitize_text_field' );

    add_settings_field(
      $this->plugin_name . '_show_user_column',
      '',
      array( $this, 'settings_show_user_column_cb' ),
      $this->plugin_name . '_advanced',
      $this->plugin_name . '_advanced',
      array()
    );
    if (WPCOMPLETE_IS_ACTIVATED)
      register_setting( $this->plugin_name . '_advanced', $this->plugin_name . '_show_user_column', 'sanitize_text_field' );

  }

  /**
   *
   *
   * @since  2.7.0
   */
  public function sanitize_post_types_cb( $input ) {
    return (is_array($input)) ? join(',', $input) : $input;
  }

  /**
   * Render extra text for sections.
   *
   * @since  1.0.0
   */
  public function settings_section_cb() {
  }

  /**
   * Sanitation helper for license field.
   *
   * @since  1.0.0
   */
  public function sanitize_license( $new ) {
    $old = get_option( $this->plugin_name . '_license_key' );
    if ( $old && $old != $new ) {
      delete_option( $this->plugin_name . '_license_status' ); // new license has been entered, so must reactivate
      wp_cache_delete( $this->plugin_name . '_license_status' );
    }
    return $new;
  }

  /**
   * Render select menu for assigning which type of user roles should be tracked as students.
   *
   * @since  1.0.0
   */
  public function settings_role_cb() {
    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-role.php';
  }

  /**
   * Render select menu for assigning which type of user roles should be tracked as students.
   *
   * @since  1.0.3
   */
  public function settings_post_type_cb() {
    $selected_type = get_option( $this->plugin_name . '_post_type', 'page,post' );
    if ($selected_type == 'all') {
      $selected_types = get_post_types();
    } else if ( $selected_type == 'page_post' ) {
      $selected_types = array('page', 'post');
    } else {
      $selected_types = explode( ',', $selected_type );
    }

    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-post-type.php';
  }

  /**
   * Render checkbox for if should attempt to append [complete_button] shortcode if not found
   *
   * @since  2.6.0
   * @last   2.6.1
   */
  public function settings_auto_enable_cb() {
    $name = $this->plugin_name . '_auto_enable';
    $text = "Automatically enable completion for newly created content types.";
    $is_enabled = get_option( $this->plugin_name . '_auto_enable', 'false' );
    $disabled = false;
    include 'partials/wpcomplete-admin-settings-checkbox.php';
  }

  /**
   * Render checkbox for if should attempt to append [complete_button] shortcode if not found
   *
   * @since  1.3.0
   * @last   2.6.1
   */
  public function settings_auto_append_cb() {
    $name = $this->plugin_name . '_auto_append';
    $text = "Automatically add completion button to enabled content types.";
    $is_enabled = get_option( $this->plugin_name . '_auto_append', 'true' );
    $disabled = false;
    include 'partials/wpcomplete-admin-settings-checkbox.php';
  }

  /**
   * Render the Mark as Complete button text setting field.
   *
   * @since  1.0.0
   */
  public function settings_incomplete_text_cb() {
    $name = $this->plugin_name . '_incomplete_text';
    $text = get_option( $name, 'Mark as complete' );
    $class = '';
    $disabled = false;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * Render the Mark as Complete button active text setting field.
   *
   * @since  1.4.7
   */
  public function settings_incomplete_active_text_cb() {
    $name = $this->plugin_name . '_incomplete_active_text';
    $text = get_option( $name, 'Saving...' );
    $class = '';
    $disabled = false;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * Render the Mark as Complete button background color setting field.
   *
   * @since  1.0.0
   */
  public function settings_incomplete_background_cb() {
    $name = $this->plugin_name . '_incomplete_background';
    $text = get_option( $name, '#ff0000' );
    $class = 'wpc-color-picker';
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * Render the Mark as Complete button text color setting field.
   *
   * @since  1.0.0
   */
  public function settings_incomplete_color_cb() {
    $name = $this->plugin_name . '_incomplete_color';
    $text = get_option( $name, '#ffffff' );
    $class = 'wpc-color-picker';
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * Render the Completed! button text setting field.
   *
   * @since  1.0.0
   */
  public function settings_completed_text_cb() {
    $name = $this->plugin_name . '_completed_text';
    $text = get_option( $name, 'COMPLETED!' );
    $class = '';
    $disabled = false;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * Render the Completed! button active text setting field.
   *
   * @since  1.4.7
   */
  public function settings_completed_active_text_cb() {
    $name = $this->plugin_name . '_completed_active_text';
    $text = get_option( $name, 'Saving...' );
    $class = '';
    $disabled = false;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * Render the Completed! button background color setting field.
   *
   * @since  1.0.0
   */
  public function settings_completed_background_cb() {
    $name = $this->plugin_name . '_completed_background';
    $text = get_option( $name, '#666666' );
    $class = 'wpc-color-picker';
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * Render the Completed! button text color setting field.
   *
   * @since  1.0.0
   */
  public function settings_completed_color_cb() {
    $name = $this->plugin_name . '_completed_color';
    $text = get_option( $name, '#ffffff' );
    $class = 'wpc-color-picker';
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * PREMIUM:
   * Render bar graph theme options.
   *
   * @since  1.0.0
   */
  public function settings_bar_theme_cb() {
    $name = $this->plugin_name . '_bar_theme';
    $themes = array('classic');
    $selected_theme = get_option( $name, 'classic' );
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-theme.php';
  }

    /**
   * PREMIUM:
   * Render radial graph theme options.
   *
   * @since  1.0.0
   */
  public function settings_radial_theme_cb() {
    $name = $this->plugin_name . '_radial_theme';
    $themes = array('classic');
    $selected_theme = get_option( $name, 'classic' );
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-theme.php';
  }


  /**
   * PREMIUM:
   * Render graph primary color setting field.
   *
   * @since  1.0.0
   */
  public function settings_graph_primary_cb() {
    $name = $this->plugin_name . '_graph_primary';
    $text = get_option( $name, '#97a71d' );
    $class = 'wpc-color-picker';
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * PREMIUM:
   * Render graph secondary color setting field.
   *
   * @since  1.0.0
   */
  public function settings_graph_secondary_cb() {
    $name = $this->plugin_name . '_graph_secondary';
    $text = get_option( $name, '#ebebeb' );
    $class = 'wpc-color-picker';
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-input.php';
  }

  /**
   * PREMIUM:
   * Render zapier webhook field.
   *
   * @since  2.9.0
   */
  public function settings_zapier_cb() {
    $name = $this->plugin_name . '_zapier';
    $text = get_option( $name, '' );
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    $last_error = json_decode( stripslashes( get_option( $name . '_last_error', '{}' ) ), true );

    include 'partials/wpcomplete-admin-settings-zapier.php';
  }

  /**
   * PREMIUM:
   * Render textarea for custom styles.
   *
   * @since  1.2.0
   */
  public function settings_custom_styles_cb() {
    $name = $this->plugin_name . '_custom_styles';
    $default = '
li .wpc-lesson {} li .wpc-lesson-complete {} li .wpc-lesson-completed { opacity: .5; } li .wpc-lesson-completed:after { content: "✔"; margin-left: 5px; }
';
    $text = get_option( $name, $default );
    if ( empty( $text ) ) {
      $text = '
.wpc-lesson {} li .wpc-lesson-complete {} li .wpc-lesson-completed {}
';
    }
    $text = str_replace("} ", "}\n", $text);
    $disabled = !WPCOMPLETE_IS_ACTIVATED;

    include 'partials/wpcomplete-admin-settings-textarea.php';
  }

  /**
   * Render checkbox for if should show dashboard widget
   *
   * @since  2.1.0
   */
  public function settings_show_widget_cb() {
    $name = $this->plugin_name . '_show_widget';
    $text = "Show statistics widget on dashboard.";
    $is_enabled = get_option( $this->plugin_name . '_show_widget', true );
    $disabled = !WPCOMPLETE_IS_ACTIVATED;
    include 'partials/wpcomplete-admin-settings-checkbox.php';
  }

  /**
   * Render checkbox for if should show user completion column (defaults to yes)
   *
   * @since  2.5.0
   */
  public function settings_show_user_column_cb() {
    $name = $this->plugin_name . '_show_user_column';
    $text = "Show user completion statistics.";
    $is_enabled = get_option( $this->plugin_name . '_show_user_column', 'true' );
    $disabled = !WPCOMPLETE_IS_ACTIVATED;
    include 'partials/wpcomplete-admin-settings-checkbox.php';
  }

  /**
   * PREMIUM:
   * Script used to activate license keys.
   *
   * @since  1.0.0
   * @last   2.9.0.8
   */
  public function activate_license() {
    // Clear cache...
    delete_transient( WPCOMPLETE_PREFIX . '_license_status' );
    // listen for our activate button to be clicked
    if ( isset( $_POST[$this->plugin_name . '_license_activate'] ) ) {
      // run a quick security check
      check_admin_referer( $this->plugin_name . '_license_nonce', '_wpcnonce' );

      // make sure only users with proper permission can activate this plugin...
      if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_die( __( 'You need permission to activate plugins. Please contact your sites administrator.' ) );
      }

      $item_name = WPCOMPLETE_PRODUCT_NAME;

      // retrieve the license from the database
      $license = trim( $_POST[ $this->plugin_name . '_license_key'] );

      // If posted license isn't the same as what's stored, store it.
      $current = get_option( $this->plugin_name . '_license_key');
      if ( $current != $license ) {
        update_option( $this->plugin_name . '_license_key', $license);
      }

      // first, lets check the license to see if it's for the right item (WPComplete vs WPComplete Unlimited vs WPComplete Lifetime)
      $api_params = array(
        'edd_action' => 'check_license',
        'license' => $license,
        'item_name'  => urlencode( $item_name )
      );

      $check_response = wp_remote_get( add_query_arg( $api_params, WPCOMPLETE_STORE_URL ), array( 'timeout' => 15, 'sslverify' => false ) );

      $license_check = json_decode( wp_remote_retrieve_body( $check_response ) );

      if ( ( $license_check->license == 'item_name_mismatch' ) ) {
        if ( $license_check->activations_left == 'unlimited' ) {
          $item_name .= " Unlimited";
        } else {
          $item_name .= " Lifetime";
        }
      }

      // data to send in our API request
      $api_params = array(
        'edd_action' => 'activate_license',
        'license'    => $license,
        'item_name'  => urlencode( $item_name ),
        'url'        => home_url()
      );

      // Call the custom API.
      $response = wp_remote_post( WPCOMPLETE_STORE_URL, array(
        'timeout'   => 15,
        'sslverify' => false,
        'body'      => $api_params
      ) );

      $message = '';
      // make sure the response came back okay
      if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        $message =  ( is_wp_error( $response ) && $response->get_error_message() ) ? $response->get_error_message() : __( 'An error occurred, please try again.' );
      } else {
        $license_data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( ( false === $license_data->success ) && ( !isset($license_data->expires) || ( $license_data->expires !== 'lifetime' ) ) ) {

          error_log($this->plugin_name . " line " . __LINE__ . ": Unknown activation error -- " . var_export( $license_data, true ) );

          switch( $license_data->error ) {
            case 'expired' :
              $message = sprintf(
                __( 'Your license key expired on %s.' ),
                date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
              );
              break;
            case 'revoked' :
              $message = __( 'Your license key has been disabled.' );
              break;
            case 'missing' :
              $message = __( 'Invalid license.' );
              break;
            case 'invalid' :
            case 'site_inactive' :
              $message = __( 'Your license is not active for this URL.' );
              break;
            case 'item_name_mismatch' :
              $message = sprintf( __( 'This appears to be an invalid license key for %s.' ), WPCOMPLETE_PRODUCT_NAME );
              break;
            case 'no_activations_left':
              $message = __( 'Your license key has reached its activation limit.' );
              break;
            default :
              $message = __( 'An error occurred, please try again.' );
              break;
          }
        }
      }
      // Check if anything passed on a message constituting a failure
      if ( ! empty( $message ) ) {
        $base_url = admin_url( 'options-general.php?page=' . $this->plugin_name );
        $redirect = add_query_arg( array( 'sl_activation' => 'false', 'message' => urlencode( $message ) ), $base_url );
        wp_redirect( $redirect );
        exit();
      }

      update_option( $this->plugin_name . '_license_status', $license_data->expires );
      wp_redirect( admin_url( 'options-general.php?page=' . $this->plugin_name ) );
      exit();

    }
  }

  /* END SETTINGS PAGE HELPERS */

  /**
   * Render the meta box for this plugin enabling completion functionality
   *
   * @since  1.0.0
   * @last   2.6.3
   */
  public function add_completable_metabox() {
    $screens = $this->get_enabled_post_types();

    add_meta_box(
      'completable',                                 // Unique ID
      __( WPCOMPLETE_PRODUCT_NAME, $this->plugin_name ),        // Box title
      array( $this, 'add_completable_metabox_cb' ),  // Content callback
      $screens,                                        // post type
      'normal', 'high',
      array(
        //'__block_editor_compatible_meta_box' => false,
      )
    );
  }

  /**
   * Callback which renders the actual html for completable metabox. Includes enabling completability and redirect url.
   *
   * @since  1.0.0
   * @last   2.9.0.7
   */
  public function add_completable_metabox_cb( $post ) {
    // get the variables we need to build the form:
    $completable = false;
    $redirect = array('title' => '', 'url' => '');
    $post_meta = get_post_meta( $post->ID, 'wpcomplete', true);
    $post_course = false;

    if ($post_meta) {
      $completable = true;
      $post_meta = json_decode( stripslashes( $post_meta ), true);
      $post_course = ( isset( $post_meta['course'] ) ) ? html_entity_decode( $post_meta['course'], ENT_QUOTES | ENT_HTML401 ) : false;
      $post_meta['buttons'] = ( isset( $post_meta['buttons'] ) && is_array( $post_meta['buttons'] ) ) ? $post_meta['buttons'] : array();
      $redirect = ( isset( $post_meta['redirect'] ) ) ? $post_meta['redirect'] : array('title' => '', 'url' => '');

      // try to clean up buttons
      $buttons = array_unique($post_meta['buttons']);
      foreach ($buttons as $key => $value) {
        if (substr($value, 0, strlen(''.$post->ID)) !== ''.$post->ID) {
          unset($buttons[$key]);
        }
      }
      if ( $post_meta['buttons'] != $buttons ) {
        $post_meta['buttons'] = $buttons;
        update_post_meta( ''.$post->ID, 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
      }

    } else if ($post->post_status == 'auto-draft') {
      $completable = (get_option( $this->plugin_name . '_auto_enable', 'false' )) === 'true';
    }
    // include a nonce to ensure we can save:
    wp_nonce_field( $this->plugin_name . '_completable', 'completable_nonce' );

    include 'partials/wpcomplete-admin-metabox.php';
  }

  /**
   * Add options to the bulk menu for posts and pages.
   *
   * @since  1.0.0
   */
  public function add_bulk_actions() {
    global $post_type;

    if ( in_array( $post_type, $this->get_enabled_post_types() ) ) {
      ?>
      <script defer type="text/javascript">
        jQuery(document).ready(function() {
          jQuery('<option>').val('completable').text("<?php _e('Can Complete', $this->plugin_name)?>").appendTo("select[name='action'],select[name='action2']");
<?php
          $courses = $this->get_course_names();
          if ( count( $courses ) > 0 ) { ?>
          jQuery('<option>').val('course::true').text("<?php echo __('Assign to: ', $this->plugin_name) . html_entity_decode(get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML401); ?>").appendTo("select[name='action'],select[name='action2']");
<?php       foreach ( $courses as $course_name ) { ?>
          jQuery('<option>').val('course::<?php echo esc_attr($course_name); ?>').text("<?php echo __('Assign to: ', $this->plugin_name) . $course_name; ?>").appendTo("select[name='action'],select[name='action2']");
<?php       }
          } ?>

        });
      </script>
      <?php
    }
  }

  /**
   * Save script for saving an individual post/page, enabling it as completable
   * PREMIUM: and custom redirect url.
   *
   * @since  1.0.0
   * @last   2.3.4
   */
  public function save_completable( $post_id, $post ) {
    global $wpdb;

    if ( isset( $_POST['completable_nonce'] ) && isset( $_POST['post_type'] ) && isset( $_POST['wpcomplete'] ) && isset( $_POST['wpcomplete']['completable'] ) ) {

      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        //echo '<!-- Autosave -->';
        return;
      } // end if

      // Verify that the input is coming from the proper form(s):
      if (
        // metabox edits:
        ! wp_verify_nonce( $_POST['completable_nonce'], $this->plugin_name . '_completable' ) &&
        // inline edits:
        !check_ajax_referer( 'inlineeditnonce', '_inline_edit' )
      ) {
        //echo '<!-- NONCE FAILED -->';
        echo "Sorry, your nonce did not verify.";
        return;
      } // end if

      // Make sure the user has permissions to posts and pages
      if ( ! in_array( $_POST['post_type'], $this->get_enabled_post_types() ) ) {
        // echo '<!-- Post type isn\'t allowed to be marked as completable -->';
        return;
      }

      $rename_course = false;
      $is_completable = $_POST['wpcomplete']['completable'];
      // PREMIUM:
      $course_name = 'true';
      if ( isset( $_POST['wpcomplete']['course-rename'] ) && !empty( $_POST['wpcomplete']['course-rename'] ) ) {
        $rename_course = $_POST['wpcomplete']['course-original'];
        $course_name = esc_attr($_POST['wpcomplete']['course-rename']);
      } else if ( isset( $_POST['wpcomplete']['course-custom'] ) && !empty( $_POST['wpcomplete']['course-custom'] ) ) {
        $course_name = esc_attr($_POST['wpcomplete']['course-custom']);
      } else if ( isset( $_POST['wpcomplete']['course'] ) && !empty( $_POST['wpcomplete']['course'] ) ) {
        $course_name = esc_attr($_POST['wpcomplete']['course']);
      }

      $redirect_to = ( isset( $_POST['wpcomplete']['completion-redirect-to'] ) ) ? esc_attr($_POST['wpcomplete']['completion-redirect-to']) : '';
      $redirect_url = ( isset( $_POST['wpcomplete']['completion-redirect-url'] ) ) ? esc_url($_POST['wpcomplete']['completion-redirect-url']) : '';
      $redirect = array('title' => $redirect_to, 'url' => $redirect_url);

      if ($is_completable == 'true') {
        // Fix when the $post_id isn't the same as the post_ID sent in:
        if ( isset($_POST['post_ID']) && ( $post_id != $_POST['post_ID'] ) ) {
          $post_id = absint($_POST['post_ID']);
        }

        $post_meta = array();

        $orig_post_meta_json = get_post_meta( $post_id, 'wpcomplete', true);
        if ( $orig_post_meta_json ) {
          $orig_post_meta = json_decode( stripslashes( $orig_post_meta_json ), true );
          if ( isset( $orig_post_meta['buttons'] ) && !is_null( $orig_post_meta['buttons'] ) ) {
            $post_meta['buttons'] = $orig_post_meta['buttons'];
          }
        }

        if ( $course_name !== 'true' ) {
          $post_meta['course'] = $course_name;
        }

        $content = $post->post_content;

        // Loop through all fields being submitted and build a master "content" variable that we can use to check what buttons exist for the post...
        foreach ($_POST as $key => $value) {
          if (is_array($value)) {
            foreach ($value as $key2 => $value2) {
              $content .= $value2;
            }
          } else {
            $content .= $value;
          }
        }
        // Loop through all saved meta data fields to check for buttons also:
        $all_post_meta_data = get_post_meta( $post_id );
        foreach ($all_post_meta_data as $key => $value) {
          if (is_array($value)) {
            //$content .= var_dump($value) . "\n\n";
            foreach ($value as $key2 => $value2) {
              $content .= $value2 . "\n\n";
            }
          } else {
            $content .= $value . "\n\n";
          }
        }

        $post_meta = $this->add_multiple_buttons_to_meta($post_id, $post_meta, $content);

        if ( !empty( $redirect_to ) ) {
          $post_meta['redirect'] = $redirect;
        }

        update_post_meta( $post_id, 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );

      } else {

        // If the value exists, delete it.
        delete_post_meta( $post_id, 'wpcomplete' );

      }
      update_option( $this->plugin_name . '_last_updated', time());

      // If user is renaming the course...
      if ($course_name) {
        // find all posts with a course name of: $rename_course
        $r = $wpdb->get_results( $wpdb->prepare( "
        SELECT pm.post_id,pm.meta_value FROM {$wpdb->postmeta} pm
        WHERE pm.meta_key = '%s'
        AND (pm.post_id != " . $post_id . ")
        AND (pm.meta_value LIKE '%\"course\":\"" . $rename_course . "\"%')", 'wpcomplete'), ARRAY_A );

        // loop through all found posts
        foreach ($r as $post_meta_fields) {
          // and decode the post's original meta
          $post_meta = json_decode( stripslashes( $post_meta_fields['meta_value'] ), JSON_UNESCAPED_UNICODE );
          // IF the current course is the correct name,
          if ($post_meta['course'] == $rename_course) {
            // so we can rename the course to $course_name
            $post_meta['course'] = $course_name;
            // and save back to the database:
            update_post_meta( $post_meta_fields['post_id'], 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
          }
        }
      }

      wp_cache_flush();

    }
  }

  /**
   * Save script for the bulk action that marks multiple pages/posts as completable.
   *
   * @since  1.0.0
   * @last   2.0.3
   */
  public function save_bulk_completable() {
    global $typenow;
    $post_type = $typenow;

    if ( in_array( $post_type, $this->get_enabled_post_types() ) && isset($_REQUEST['post']) ) {
      update_option( $this->plugin_name . '_last_updated', time());
      if ( (($_REQUEST['action'] == 'completable') || ($_REQUEST['action2'] == 'completable')) ) {
        // security check
        check_admin_referer( 'bulk-posts' );

        $action = ($_REQUEST['action'] == '-1') ? $_REQUEST['action2'] : $_REQUEST['action'];

        // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
        if ( isset($_REQUEST['post'] ) ) {
          $post_ids = array_map( 'intval', $_REQUEST['post'] );
        }

        if ( empty( $post_ids ) ) return;

        // this is based on wp-admin/edit.php
        $sendback = remove_query_arg( array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
        if ( ! $sendback )
          $sendback = admin_url( "edit.php?post_type=$post_type" );

        // do the marking as complete!
        $marked = 0;
        foreach ( $post_ids as $post_id ) {
          $post_meta = get_post_meta( $post_id, 'wpcomplete', true );

          if ( ! $post_meta ) {
            // Enable the post because it wasn't previously.
            $post_meta = array();
            // Check to see if we need to add multiple buttons to database meta info:
            $post_content = get_post_field('post_content', $post_id);
            $post_meta = $this->add_multiple_buttons_to_meta($post_id, $post_meta, $post_content);

            // TODO: double check this still works:
            update_post_meta( $post_id, 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
            $marked++;
          } else {
            // Already enabled... no need to do anything...
          }
        }

        $sendback = add_query_arg( array('completable' => $marked, 'ids' => join(',', $post_ids) ), $sendback );
        $sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );

        wp_cache_flush();

        wp_redirect( $sendback );
        exit();
      } else if ( ( (substr($_REQUEST['action'], 0, strlen('course::')) == 'course::') || (substr($_REQUEST['action2'], 0, strlen('course::')) == 'course::') ) ) {
        // security check
        check_admin_referer( 'bulk-posts' );

        $action = ($_REQUEST['action'] == '-1') ? $_REQUEST['action2'] : $_REQUEST['action'];
        list($action, $course_name) = explode("::", $action);
        $course_name = html_entity_decode( $course_name, ENT_QUOTES | ENT_HTML401 );

        // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
        if ( isset($_REQUEST['post'] ) ) {
          $post_ids = array_map( 'intval', $_REQUEST['post'] );
        }

        if ( empty( $post_ids ) ) return;

        // this is based on wp-admin/edit.php
        $sendback = remove_query_arg( array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
        if ( ! $sendback )
          $sendback = admin_url( "edit.php?post_type=$post_type" );

        // do the marking as complete!
        $marked = 0;
        foreach ( $post_ids as $post_id ) {
          $post_meta = get_post_meta( $post_id, 'wpcomplete', true );

          if ( ! $post_meta ) {
            // Enable the post because it wasn't previously.
            $post_meta = array( 'course' => $course_name );
            // Check to see if we need to add multiple buttons to database meta info:
            $post_content = get_post_field('post_content', $post_id);
            $post_meta = $this->add_multiple_buttons_to_meta($post_id, $post_meta, $post_content);

            update_post_meta( $post_id, 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
            $marked++;
          } else {
            $post_meta = json_decode( stripslashes( $post_meta ), true );
            if ( isset($post_meta['course']) ) $post_meta['course'] = html_entity_decode( $post_meta['course'], ENT_QUOTES | ENT_HTML401 );
            if ( !isset($post_meta['course']) || ( $post_meta['course'] != $course_name ) ) {
              $post_meta['course'] = htmlentities( $course_name, ENT_QUOTES | ENT_HTML401 );
              // Check to see if we need to add multiple buttons to database meta info:
              $post_content = get_post_field('post_content', $post_id);
              $post_meta = $this->add_multiple_buttons_to_meta($post_id, $post_meta, $post_content);

              update_post_meta( $post_id, 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
              $marked++;
            } else {
              // Already in this course... no need to do anything...
            }
          }
        }

        $sendback = add_query_arg( array('course' => $marked, 'ids' => join(',', $post_ids) ), $sendback );
        $sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );

        wp_cache_flush();

        wp_redirect( $sendback );
        exit();
      }
    }
  }

  /**
   * Add a notice message for completed bulk actions.
   *
   * @since  1.0.0
   */
  public function show_bulk_action_notice() {
    global $post_type, $pagenow;

    if ( $pagenow == 'edit.php' && in_array( $post_type, $this->get_enabled_post_types() ) && isset($_REQUEST['completable']) && (int) $_REQUEST['completable']) {
      $message = sprintf( _n( 'Post marked completable by students.', '%s posts marked as completable by students.', $_REQUEST['completable'] ), number_format_i18n( $_REQUEST['completable'] ) );
      echo "<div class=\"updated\"><p>{$message}</p></div>";
    } else if ( $pagenow == 'edit.php' && in_array( $post_type, $this->get_enabled_post_types() ) && isset($_REQUEST['course']) && (int) $_REQUEST['course']) {
      $message = sprintf( _n( 'Post assigned to course.', '%s posts assigned to course.', $_REQUEST['course'] ), number_format_i18n( $_REQUEST['course'] ) );
      echo "<div class=\"updated\"><p>{$message}</p></div>";
    }
  }

  /**
   * PREMIUM:
   * If the license has not been configured properly, display an admin notice.
   *
   * @since  1.0.0
   */
  public function show_license_notice_nag() {
    global $pagenow;

    if ( !WPCOMPLETE_IS_ACTIVATED &&
         !in_array( $pagenow , array('post.php', 'post_new.php') ) && // don't show on certain pages
         !get_option('dismissed-license', FALSE ) // don't show when already dismissed
       ) {
      $msg = __( 'Please activate your license key to enable all WPComplete PRO features.', $this->plugin_name );

      include 'partials/wpcomplete-admin-license-notice.php';
    }
  }

  /**
   * Show devmode nag if not in production and hasn't been dismissed already.
   *
   * @since  2.8.8
   */
  public function show_development_mode_nag() {
    global $pagenow;

    if ( !wpcomplete_is_production() && // don't show in production
         !in_array( $pagenow , array('post.php', 'post_new.php') ) && // don't show on certain pages
         !get_option('dismissed-devmode', FALSE ) // don't show when already dismissed
       ) {
      $msg = __( 'WPComplete is currently in development mode! While you build your site, please enjoy all <strong>pro features enabled</strong> for free. :)', $this->plugin_name );

      include 'partials/wpcomplete-admin-devmode-notice.php';
    }
  }

  /**
   * Update setting to not show devmode nag.
   *
   * @since  2.8.8
   */
  public function dismiss_devmode_notice_handler() {
    update_option( 'dismissed-devmode', TRUE );
  }

    /**
   * Update setting to not show devmode nag.
   *
   * @since  2.8.10
   */
  public function dismiss_license_notice_handler() {
    update_option( 'dismissed-license', TRUE );
  }

  /**
   * Add the new custom column header, "User Completion" to pages and posts edit.php page.
   *
   * @since  1.0.0
   */
  public function add_custom_column_header( $columns ) {
    global $post_type;

    if (!$post_type) $post_type = $_POST['post_type'];

    if ( in_array( $post_type, $this->get_enabled_post_types() ) ) {

      if ( count( $this->get_course_names() ) > 0 ) {
        $columns = array_merge( $columns, array( 'completable-course' => __( 'Course Name', $this->plugin_name ) ) );
      }

      $columns = array_merge( $columns, array( 'completable' => __( 'Completion', $this->plugin_name ) ) );
    }

    return $columns;
  }

  /**
   * Add the values for each post/page of the new custom "Completion %" column.
   * If post/page isn't enabled to be completed, it shows — in column.
   * If wordpress install doesn't have any subscribers (students), it shows "0 Students".
   * Otherwise, it'll show the ratio and percentage of how many students have completed it.
   *
   * @since  1.0.0
   * @last   2.9.0.4
   */
  public function add_custom_column_value( $column_name, $post_id ) {
    global $wpdb;

    if ( $column_name == 'completable-course' ) {
      $posts = $this->get_completable_posts();

      if ( isset( $posts[$post_id] ) ) {
        $course_name = (!isset($posts[$post_id]['course'])) ? get_bloginfo( 'name' ) : $posts[$post_id]['course'];
      } else {
        $course_name = '—';
      }

      echo '<div id="completable-course-' . $post_id . '">' . $course_name . '</div>';

    } else if ( $column_name == 'completable' ) {
      if ( get_post_status($post_id) === 'future' ) {
        echo '<div id="completable-' . $post_id . '">Scheduled</div>';
      } elseif ( get_post_status($post_id) === 'pending' ) {
        echo '<div id="completable-' . $post_id . '">Pending Approval</div>';
      } else {
        $posts = $this->get_completable_posts();

        if ( isset( $posts[$post_id] ) ) {

          $completion = '';
          $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );

          if ( $users_json = wp_cache_get( 'users-' . $selected_role, 'wpcomplete' ) ) {
            $users = json_decode( stripslashes( $users_json ), true );
          } else {
            $args = array('fields' => 'id');
            if ($selected_role != 'all') $args['role'] = $selected_role;
            $users = get_users($args);
            wp_cache_set( 'users-' . $selected_role, json_encode( $users, JSON_UNESCAPED_UNICODE ), 'wpcomplete' );
          }
          $avail_users = count($users);

          if ($avail_users > 0) {
            if ( $users_meta_json = wp_cache_get( 'users-meta-' . $selected_role, 'wpcomplete' ) ) {
              $user_activities = json_decode( $users_meta_json, true );
            } else {
              // if we need a specific role, only grab those userse first...
              $from_sql = "{$wpdb->users}";
              if ( $selected_role != 'all' ) {
                $from_sql = "(SELECT u.ID FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} AS mt1 ON ( u.ID = mt1.user_id ) WHERE ( (mt1.meta_key = '{$wpdb->prefix}capabilities') AND (mt1.meta_value LIKE '%\"$selected_role\"%') ))";
              }
              $sql = "SELECT um.user_id, um.meta_value FROM {$from_sql} u INNER JOIN {$wpdb->usermeta} AS um ON ( u.ID = um.user_id ) WHERE ( um.meta_key = 'wpcomplete' )";
              $user_activities = $wpdb->get_results( $sql, ARRAY_A );
              // fix key/value association:
              $user_activities = array_column($user_activities, 'meta_value', 'user_id');
              wp_cache_set( 'users-meta-' . $selected_role, json_encode( $user_activities, JSON_UNESCAPED_UNICODE), 'wpcomplete');
            }

            if ( isset( $posts[$post_id]['buttons'] ) ) {
              foreach ( $posts[$post_id]['buttons'] as $button) {
                // calculate how many of the users are completed...
                $completed_users = 0;
                foreach ( $user_activities as $user_id => $user_activity ) {
                  $user_activity = json_decode( stripslashes( $user_activity ), true );
                  foreach ($user_activity as $button_id => $value) {
                    if ( ( ''.$button_id === ''.$button ) && isset( $value['completed'] ) && !empty( $value['completed'] ) ) {
                      $completed_users++;
                    }
                  }
                }

                list($button_post_id, $button_id) = $this->extract_button_info($button);
                $button_name = ($button === ''.$post_id) ? 'Default Button' : "Button '$button_id'";
                $completion .= ('<a href="edit.php?page=wpcomplete-buttons&post_id=' . $post_id . '&button=' . $button . '">' . "$button_name: $completed_users/$avail_users Users (" . round(100 * ($completed_users / $avail_users), 1) . '%)</a><br>');
              }
            } else {
              // calculate how many of these users are completed...
              $completed_users = 0;
              foreach ( $user_activities as $user_id => $user_activity ) {
                $user_activity = json_decode( stripslashes( $user_activity ), true );
                foreach ($user_activity as $button_id => $value) {
                  if ( ( ''.$button_id == ''.$post_id ) && isset( $value['completed'] ) && !empty( $value['completed'] ) ) {
                    $completed_users++;
                    continue;
                  }
                }
              }

              $completion = '<a href="edit.php?page=wpcomplete-posts&post_id=' . $post_id . '">' . ("$completed_users/$avail_users Users (" . round(100 * ($completed_users / $avail_users), 1) . '%)')  . '</a>';
            }
          } else {
            $completion = "0 Users";
          }
          echo '<div id="completable-' . $post_id . '">' . $completion . '</div>';
        } else {
          echo '<div id="completable-' . $post_id . '">—</div>';
        }
      }
    }
  }

  /**
   *
   *
   * @since  2.3.0
   * @last   2.3.0
   */
  public function add_course_completion_page() {
    add_menu_page(
      WPCOMPLETE_PRODUCT_NAME,
      __( WPCOMPLETE_PRODUCT_NAME, $this->plugin_name ),
      'manage_options',
      'wpcomplete-courses',
      array( $this, 'render_courses_page' ),
      'dashicons-yes',
      50
    );

    add_submenu_page(
      'wpcomplete-courses',
      __( 'Courses', $this->plugin_name ),
      __( 'Courses', $this->plugin_name ),
      'manage_options',
      'wpcomplete-courses',
      array( $this, 'render_courses_page' )
    );

    add_submenu_page(
      'wpcomplete-courses',
      __( 'General Settings', $this->plugin_name ),
      __( 'General Settings', $this->plugin_name ),
      'manage_options',
      'wpcomplete-settings',
      array( $this, 'render_course_settings_page' )
    );
  }

  /**
   *
   *
   * @since  2.3.0
   * @last   2.3.2
   */
  public function render_courses_page() {
    if ( ! current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! WPCOMPLETE_IS_ACTIVATED ) {
      wp_die( __( 'You only get access to this data once you activate your license.' ) );
    }

    $total_posts = $this->get_buttons( array( 'course' => 'all' ) );
    $post_data = $this->get_completable_posts();
    $course_stats = $this->get_course_stats( $post_data );
    $courses = array();

    foreach ($total_posts as $button) {
      list($post_id, $button_id) = $this->extract_button_info($button);

      if ( isset( $post_data[$post_id]['course'] ) ) {
        $course_name = html_entity_decode( $post_data[$post_id]['course'], ENT_QUOTES | ENT_HTML401 );
      } else {
        $course_name = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML401 );
      }

      if (!isset($courses[$course_name])) {
        $courses[$course_name] = array();
        $courses[$course_name]['buttons'] = array();
        $courses[$course_name]['stats'] = ( isset( $course_stats[$course_name] ) ) ? $course_stats[$course_name] : array();
      }

      if ($this->post_has_multiple_buttons($post_id)) {
        if ($button == $post_id) {
          $button_name = get_the_title($post_id) . " (" . ucwords( str_replace( "_", " ", get_post_type( $post_id ) ) ) . " #" . $post_id . ") - Button: Default";
          $courses[$course_name]['buttons'][$button_name] = array('id' => $button, 'link' => "edit.php?page=wpcomplete-buttons&amp;post_id=" . $post_id . "&amp;button=" . $button, 'started' => 0, 'completed' => 0, 'status' => get_post_status($post_id) );
        } else {
          $button_name = get_the_title($post_id) . " (" . ucwords( str_replace( "_", " ", get_post_type( $post_id ) ) ) . " #" . $post_id . ") - Button: " . $button_id;
          $courses[$course_name]['buttons'][$button_name] = array('id' => $button, 'link' => "edit.php?page=wpcomplete-buttons&amp;post_id=" . $post_id . "&amp;button=" . $button, 'started' => 0, 'completed' => 0, 'status' => get_post_status($post_id));
        }
      } else {
        $button_name = get_the_title($post_id) . " (" . ucwords( str_replace( "_", " ", get_post_type( $post_id ) ) ) . " #" . $post_id . ")";
        $courses[$course_name]['buttons'][$button_name] = array('id' => $button, 'link' => "edit.php?page=wpcomplete-posts&amp;post_id=" . $button, 'started' => 0, 'completed' => 0, 'status' => get_post_status($post_id));
      }
    }

    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    // Get all users that are able to complete the post:
    $args = array('fields' => 'id');
    if ($selected_role != 'all') $args['role'] = $selected_role;
    $total_users = get_users($args);

    foreach ($total_users as $user_id) {
      $user_completed_raw = $this->get_user_activity( $user_id );
      $user_completed = array();
      foreach ($user_completed_raw as $button_id => $value) {
        if ( in_array( $button_id, $total_posts ) ) {
          $user_completed[$button_id] = array();
          if ( isset( $value['first_seen'] ) ) {
            $user_completed[$button_id]['first_seen'] = $value['first_seen'];
          }
          if ( isset( $value['completed'] ) ) {
            if ($value['completed'] === true) {
              $value['completed'] = 'Yes';
            }
            $user_completed[$button_id]['completed'] = $value['completed'];
          }
        }
      }

      // array('course' => array('stats' => array('ratio', 'percentage'), 'buttons' => array('button' => array('link', 'completion' => completion_datetime/false))))
      foreach ( $courses as $course_name => $course_info) {
        foreach ( $course_info['buttons'] as $button_name => $button ) {
          if ( isset( $user_completed[$button['id']] ) ) {
            if ( isset( $user_completed[$button['id']]['completed'] ) ) {
              $courses[$course_name]['buttons'][$button_name]['started']++;
              $courses[$course_name]['buttons'][$button_name]['completed']++;
            } else if ( isset( $user_completed[$button['id']]['first_seen'] ) ) {
              $courses[$course_name]['buttons'][$button_name]['started']++;
            }
          }
        }
      }
    }

    ksort($courses);

    if ( isset( $_GET['course'] ) && isset( $courses[$_GET['course']] ) ) {
      $courses = array($_GET['course'] => $courses[$_GET['course']]);
    }

    include_once 'partials/wpcomplete-admin-courses.php';
  }

  /**
   * Export entire completion data to a csv. Called from admin_init so loaded before
   * headers are sent.
   *
   * @since  2.9.1
   * @last   2.9.1
   */
  public function export_course_completion_csv() {
    global $wpdb;

    // for entire posts or specific buttons...
    if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'wpcomplete-courses' ) ) && isset( $_GET['export'] ) ) {

      if ( ! current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
      }
      if ( ! WPCOMPLETE_IS_ACTIVATED ) {
        wp_die( __( 'You only get access to this data once you activate your license.' ) );
      }

      $course = ( isset( $_GET['course'] ) && !empty( $_GET['course'] ) ) ? sanitize_text_field($_GET['course']) : false;

      $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
      // Get all users that are able to complete the post:
      $args = array('fields' => ['id','user_email']);
      if ($selected_role != 'all') $args['role'] = $selected_role;
      $total_users = get_users($args);

      // TODO: limit by course...
      $data = array();
      $user_completed_raw = array();
      foreach ($total_users as $user) {
        $user_completed_raw[$user->id] = $this->get_user_activity( $user->id );
        $user_completed = array();
        foreach ($user_completed_raw[$user->id] as $button_id => $value) {
          $user_completed[$button_id] = array();
          if ( isset( $value['first_seen'] ) ) {
            $user_completed[$button_id]['first_seen'] = $value['first_seen'];
          }
          if ( isset( $value['completed'] ) ) {
            if ($value['completed'] === true) {
              $value['completed'] = 'Yes';
            }
            $user_completed[$button_id]['completed'] = $value['completed'];
          }
        }
        $data[$user->id] = $user_completed;
      }

      header("Pragma: public");
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: private", false);
      header("Content-Type: application/octet-stream");
      if ( $course ) {
        header("Content-Disposition: attachment; filename=\"wpcomplete-course-" . $this->get_course_class(array('course' => $course)) . ".csv\";");
      } else {
        header("Content-Disposition: attachment; filename=\"wpcomplete-courses.csv\";");
      }
      header("Content-Transfer-Encoding: binary");

      $csv = '"User ID","Email","Post ID","Button","Started","Completed"';

      $total_posts = $this->get_completable_posts();

      foreach ($total_users as $user) {
        foreach ($total_posts as $post_id => $post_data) {
          $post_buttons = ( isset( $post_data['buttons'] ) && is_array( $post_data['buttons'] ) && !empty( $post_data['buttons'] ) ) ? $post_data['buttons'] : array(''.$post_id);

          foreach ($post_buttons as $button_id) {
            $csv .= "\n\"".$user->id.'","'.sanitize_email($user->user_email).'","'.$post_id.'","'.$button_id.'",';
            if ( isset( $user_completed_raw[$user->id][$button_id] ) ) {
              if ( isset( $user_completed_raw[$user->id][$button_id]['first_seen'] ) ) {
                $csv .= '"'.$user_completed_raw[$user->id][$button_id]['first_seen'] . '",';
              } else {
                $csv .= '"No",';
              }
              if ( isset( $user_completed_raw[$user->id][$button_id]['completed'] ) ) {
                if ($user_completed_raw[$user->id][$button_id]['completed'] === true) {
                  $user_completed_raw[$user->id][$button_id]['completed'] = '"Yes"';
                }
                $csv .= $user_completed_raw[$user->id][$button_id]['completed'];
              } else {
                $csv .= '"No"';
              }
            } else {
              $csv .= '"No","No"';
            }
          }
        }

      }

      echo $csv;
      exit;

    }
  }

  /**
   *
   *
   * @since  2.3.0
   * @last   2.3.0
   */
  public function render_course_settings_page() {
    echo "<p><em>loading...</em></p><script type='text/javascript'> window.location = 'options-general.php?page=wpcomplete'; </script>";
    //wp_redirect('options-general.php?page=wpcomplete');
    //exit;
  }

  /**
   *
   *
   * @since  1.4.0
   */
  public function add_post_completion_page() {
    add_submenu_page(
      null,
      __( 'Post Completion', $this->plugin_name ),
      __( 'Post Completion', $this->plugin_name ),
      'manage_options',
      'wpcomplete-posts',
      array( $this, 'render_post_completion_page' )
    );
  }

  /**
   *
   *
   * @since  1.4.0
   * @last   2.9.1
   */
  public function render_post_completion_page() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! $_GET['post_id'] ) {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! WPCOMPLETE_IS_ACTIVATED ) {
      wp_die( __( 'You only get access to this data once you activate your license.' ) );
    }
    // Get post info:
    $post_id = $_GET['post_id'];
    $post = get_post($post_id);

    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    // Get all users that are able to complete the post:
    $args = array('fields' => 'all');
    if ($selected_role != 'all') $args['role'] = $selected_role;
    $total_users = get_users($args);

    $user_started = array();
    $user_completed = array();
    foreach ($total_users as $user) {
      $user_completed_raw = $this->get_user_activity( $user->ID );
      if ( isset( $user_completed_raw[$post_id] ) ) {
        if ( isset( $user_completed_raw[$post_id]['first_seen'] ) ) {
          $user_started[$user->ID] = $user_completed_raw[$post_id]['first_seen'];
        }
        if ( isset( $user_completed_raw[$post_id]['completed'] ) ) {
          if ($user_completed_raw[$post_id]['completed'] === true) {
            $user_completed_raw[$post_id]['completed'] = 'Yes';
          }
          if ( ! isset( $user_started[$user->ID] ) ) {
            $user_started[$user->ID] = $user_completed_raw[$button_id]['completed'];
          }
          $user_completed[$user->ID] = $user_completed_raw[$post_id]['completed'];
        }
      }
    }

    include_once 'partials/wpcomplete-admin-post-completion.php';
  }

  /**
   *
   *
   * @since  2.0.0
   * @last   2.0.0
   */
  public function add_button_completion_page() {
    add_submenu_page(
      null,
      __( 'Button Completion', $this->plugin_name ),
      __( 'Button Completion', $this->plugin_name ),
      'manage_options',
      'wpcomplete-buttons',
      array( $this, 'render_button_completion_page' )
    );
  }

  /**
   *
   *
   * @since  2.0.0
   * @last   2.9.1
   */
  public function render_button_completion_page() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! $_GET['post_id'] || ! $_GET['button'] ) {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! WPCOMPLETE_IS_ACTIVATED ) {
      wp_die( __( 'You only get access to this data once you activate your license.' ) );
    }

    // Get post info:
    $button_id = $_GET['button'];
    list($post_id, $button) = $this->extract_button_info($button_id);
    $post_id = absint($_GET['post_id']);
    $post = get_post($post_id);

    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    // Get all users that are able to complete the post:
    $args = array('fields' => 'all');
    if ($selected_role != 'all') $args['role'] = $selected_role;
    $total_users = get_users($args);
    $total_posts = $this->get_completable_posts();//$this->get_buttons();

    $user_started = array();
    $user_completed = array();
    foreach ($total_users as $user) {
      $user_completed_raw = $this->get_user_activity( $user->ID );
      if ( isset( $total_posts[ $post_id ] ) ) {
        if ( isset( $user_completed_raw[$button_id] ) ) {
          if ( isset( $user_completed_raw[$button_id]['first_seen'] ) ) {
            $user_started[$user->ID] = $user_completed_raw[$button_id]['first_seen'];
          }
          if ( isset( $user_completed_raw[$button_id]['completed'] ) ) {
            if ($user_completed_raw[$button_id]['completed'] === true) {
              $user_completed_raw[$button_id]['completed'] = 'Yes';
            }
            if ( ! isset( $user_started[$user->ID] ) ) {
              $user_started[$user->ID] = $user_completed_raw[$button_id]['completed'];
            }
            $user_completed[$user->ID] = $user_completed_raw[$button_id]['completed'];
          }
        }
      }
    }

    include_once 'partials/wpcomplete-admin-button-completion.php';
  }

  /**
   * Export post and button completion data to a csv. Called from admin_init so loaded before headers are sent.
   *
   * @since  2.3.2
   * @last   2.9.1
   */
  public function export_button_completion_csv() {
    global $wpdb;

    // for entire posts or specific buttons...
    if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'wpcomplete-posts', 'wpcomplete-buttons' ) ) && isset( $_GET['export'] ) ) {

      if ( ! current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
      }
      if ( ! WPCOMPLETE_IS_ACTIVATED ) {
        wp_die( __( 'You only get access to this data once you activate your license.' ) );
      }

      // Get post info:
      $button_id = ( isset( $_GET['button'] ) ) ? $_GET['button'] : $_GET['post_id'];
      list($post_id, $button) = $this->extract_button_info($button_id);
      $post_id = absint($_GET['post_id']);
      $post = get_post($post_id);

      $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
      // Get all users that are able to complete the post:
      $args = array('fields' => 'all');
      if ($selected_role != 'all') $args['role'] = $selected_role;
      $total_users = get_users($args);
      $total_posts = $this->get_completable_posts();

      $user_completed = array();
      foreach ($total_users as $user) {
        $user_completed_raw = $this->get_user_activity( $user->ID );
        $user_completed[$user->ID] = array();
        if ( isset( $total_posts[ $post_id ] ) ) {
          if ( isset( $user_completed_raw[$button_id] ) ) {
            if ( isset( $user_completed_raw[$button_id]['first_seen'] ) ) {
              $user_completed[$user->ID]['started'] = $user_completed_raw[$button_id]['first_seen'];
            }
            if ( isset( $user_completed_raw[$button_id]['completed'] ) ) {
              if ($user_completed_raw[$button_id]['completed'] === true) {
                $user_completed_raw[$button_id]['completed'] = 'Yes';
              }
              $user_completed[$user->ID]['completed'] = $user_completed_raw[$button_id]['completed'];
            }
          }
        }
      }

      header("Pragma: public");
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: private", false);
      header("Content-Type: application/octet-stream");
      if ($button_id == $post_id) {
        header("Content-Disposition: attachment; filename=\"wpcomplete-post-$post_id.csv\";");
      } else {
        header("Content-Disposition: attachment; filename=\"wpcomplete-button-$button_id.csv\";");
      }
      header("Content-Transfer-Encoding: binary");

      $csv = '"Student Email","Started","Completed"';

      foreach ($total_users as $user) {
        $csv .= ("\n\"" . sanitize_email($user->user_email) . '",');
        if ( isset($user_completed[$user->ID]['started']) ) {
          $csv .= '"' . $user_completed[$user->ID]['started'] . '",';
        } else {
          $csv .= '"No",';
        }
        if ( isset($user_completed[$user->ID]['completed']) ) {
          $csv .= '"' . $user_completed[$user->ID]['completed'] . '"';
        } else {
          $csv .= '"No"';
        }

      }

      echo $csv;
      exit;

    }
  }

  /**
   * Add custom field for quick edit of posts and pages.
   *
   * @since  1.0.0
   */
  public function add_custom_quick_edit( $column_name, $post_type ) {
    if ( in_array( $post_type, $this->get_enabled_post_types() ) ) {
      include 'partials/wpcomplete-admin-quickedit.php';
    }
  }

  /**
   * Add the new custom column header, "Lesson Completion" to users page.
   *
   * @since  1.0.0
   * @last   2.5.1
   */
  public function add_user_column_header( $columns ) {
    $posts = $this->get_completable_posts();
    if ( ( get_option( $this->plugin_name . '_show_user_column', 'true' ) === 'true' ) && ( count($posts) > 0 ) ) {
      return array_merge( $columns, array( 'completable' => __( 'Completion', $this->plugin_name) ));
    } else {
      return $columns;
    }
  }

  /**
   * Add the values for each user of the new custom "Completion" column.
   * If user is not in a student role, it shows — in column.
   * Otherwise, it'll show the ratio and percentage of student's completion.
   *
   * @since  1.0.0
   * @last   2.8.10.2
   */
  public function add_user_column_value( $value, $column_name, $user_id ) {
    if ( $column_name == 'completable' ) {
      $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );

      if ( ( $selected_role == 'all' ) || $this->user_has_role($user_id, $selected_role) ) {
        $total_posts = $this->get_buttons( array( 'course' => 'all' ) );
        $post_data = $this->get_completable_posts();
        $post_metadata = $this->get_completable_posts_metadata();

        $user_completed_raw = $this->get_user_completed( $user_id );
        $user_completed = array();
        foreach ($user_completed_raw as $button_id => $value) {
          if ( in_array( $button_id, $total_posts ) && isset( $value['completed'] ) ) {
            if ($value['completed'] === true) {
              $value['completed'] = 'Yes';
            }
            $user_completed[$button_id] = $value['completed'];
          }
        }

        $courses = array();
        // array('course' => array('stats' => array('ratio', 'percentage'), 'buttons' => array('button' => array('link', 'completion' => completion_datetime/false))))
        foreach ($total_posts as $button) {
          list($post_id, $button_id) = $this->extract_button_info($button);

          if ( isset( $post_data[$post_id]['course'] ) ) {
            $course_name = $post_data[$post_id]['course'];
          } else {
            $course_name = get_bloginfo( 'name' );
          }

          if (!isset($courses[$course_name])) {
            $courses[$course_name] = array();
            $courses[$course_name]['buttons'] = array();
            $courses[$course_name]['stats'] = array('completed' => 0);
          }

          if ($this->post_has_multiple_buttons($post_id)) {
            if ($button != $post_id) {
              $button_name = $post_metadata[$post_id]['title'] . " (" . ucwords( str_replace( "_", " ", $post_metadata[$post_id]['type'] ) ) . " #" . $post_id . ") - Button: " . $button_id;
              $courses[$course_name]['buttons'][$button_name] = array('link' => "edit.php?page=wpcomplete-buttons&amp;post_id=" . $post_id . "&amp;button=" . $button);
            } else {
              $button_name = $post_metadata[$post_id]['title'] . " (" . ucwords( str_replace( "_", " ", $post_metadata[$post_id]['type'] ) ) . " #" . $post_id . ") - Default Button";
              $courses[$course_name]['buttons'][$button_name] = array('link' => "edit.php?page=wpcomplete-posts&amp;post_id=" . $post_id);
            }
          } else {
            $button_name = $post_metadata[$post_id]['title'] . " (" . ucwords( str_replace( "_", " ", $post_metadata[$post_id]['type'] ) ) . " #" . $post_id . ")";
            $courses[$course_name]['buttons'][$button_name] = array('link' => "edit.php?page=wpcomplete-posts&amp;post_id=" . $post_id);
          }
          if ( isset($user_completed[$button]) ) {
            $courses[$course_name]['buttons'][$button_name]['completed'] = $user_completed[$button];
            $courses[$course_name]['stats']['completed']++;
          } else {
            $courses[$course_name]['buttons'][$button_name]['completed'] = 'No';
          }
        }
        ksort($courses);

        ob_start();
        include 'partials/wpcomplete-admin-user-completion-column.php';
        return ob_get_clean();
      } else {
        return '<div id="completable-' . $user_id . '">—</div>';
      }
    } else {
      return $value;
    }
  }

  /**
   *
   *
   * @since  1.4.0
   */
  public function add_user_completion_page() {
    add_submenu_page(
      null,
      __( 'User Completion', $this->plugin_name ),
      __( 'User Completion', $this->plugin_name ),
      'manage_options',
      'wpcomplete-users',
      array( $this, 'render_user_completion_page' )
    );
  }

  /**
   *
   *
   * @since  1.4.0
   * @last   2.7.0
   */
  public function render_user_completion_page() {
    if ( ! current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! $_GET['user_id'] ) {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! WPCOMPLETE_IS_ACTIVATED ) {
      wp_die( __( 'You only get access to this data once you activate your license.' ) );
    }

    $user_id = $_GET['user_id'];
    $total_posts = $this->get_buttons( array( 'course' => 'all' ) );
    $post_data = $this->get_completable_posts();

    $user_completed_raw = $this->get_user_activity( $user_id );
    $user_completed = array();
    $user_completed_count = 0;
    foreach ($user_completed_raw as $button_id => $value) {
      if ( in_array( $button_id, $total_posts ) ) {
        $user_completed[$button_id] = array();
        if ( isset( $value['first_seen'] ) ) {
          $user_completed[$button_id]['first_seen'] = $value['first_seen'];
        }
        if ( isset( $value['completed'] ) ) {
          if ($value['completed'] === true) {
            $value['completed'] = 'Yes';
          }
          $user_completed[$button_id]['completed'] = $value['completed'];
          $user_completed_count++;
        }
      }
    }

    $user = get_userdata( $user_id );
    $courses = array();
    // array('course' => array('stats' => array('ratio', 'percentage'), 'buttons' => array('button' => array('link', 'completion' => completion_datetime/false))))
    foreach ($total_posts as $button) {
      list($post_id, $button_id) = $this->extract_button_info($button);

      if ( isset( $post_data[$post_id]['course'] ) ) {
        $course_name = $post_data[$post_id]['course'];
      } else {
        $course_name = get_bloginfo( 'name' );
      }

      if (!isset($courses[$course_name])) {
        $courses[$course_name] = array();
        $courses[$course_name]['buttons'] = array();
        $courses[$course_name]['stats'] = array('completed' => 0);
      }

      if ($this->post_has_multiple_buttons($post_id)) {
        if ($button != $post_id) {
          $button_name = get_the_title($post_id) . " (" . ucwords( str_replace( "_", " ", get_post_type( $post_id ) ) ) . " #" . $post_id . ") - Button: " . $button_id;
          $courses[$course_name]['buttons'][$button_name] = array('link' => "edit.php?page=wpcomplete-buttons&amp;post_id=" . $post_id . "&amp;button=" . $button, 'status' => get_post_status($post_id), 'button' => $button );
        } else {
          $button_name = get_the_title($post_id) . " (" . ucwords( str_replace( "_", " ", get_post_type( $post_id ) ) ) . " #" . $post_id . ") - Default Button";
          $courses[$course_name]['buttons'][$button_name] = array('link' => "edit.php?page=wpcomplete-posts&amp;post_id=" . $post_id, 'status' => get_post_status($post_id), 'button' => $button );
        }
      } else {
        $button_name = get_the_title($post_id) . " (" . ucwords( str_replace( "_", " ", get_post_type( $post_id ) ) ) . " #" . $post_id . ")";
        $courses[$course_name]['buttons'][$button_name] = array('link' => "edit.php?page=wpcomplete-posts&amp;post_id=" . $button, 'status' => get_post_status($post_id), 'button' => $button );
      }
      $courses[$course_name]['buttons'][$button_name]['started'] = 'No';
      $courses[$course_name]['buttons'][$button_name]['completed'] = 'No';
      if ( isset( $user_completed[$button] ) ) {
        if ( isset( $user_completed[$button]['first_seen'] ) ) {
          $courses[$course_name]['buttons'][$button_name]['started'] = $user_completed[$button]['first_seen'];
        }
        if ( isset( $user_completed[$button]['completed'] ) ) {
          $courses[$course_name]['buttons'][$button_name]['completed'] = $user_completed[$button]['completed'];
          $courses[$course_name]['stats']['completed']++;
        }
      }
    }
    ksort($courses);

    include_once 'partials/wpcomplete-admin-user-completion.php';
  }

  /**
   * Export individual user completion data to a csv. Called from admin_init so loaded before
   * headers are sent.
   *
   * @since  2.9.1
   * @last   2.9.1
   */
  public function export_user_completion_csv() {
    global $wpdb;

    // for a specific user...
    if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'wpcomplete-users' ) ) && isset( $_GET['export'] ) ) {

      if ( ! current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
      }
      if ( ! WPCOMPLETE_IS_ACTIVATED ) {
        wp_die( __( 'You only get access to this data once you activate your license.' ) );
      }

      // Get post info:
      $user_id = absint($_GET['user_id']);
      $user = get_user_by('id', $user_id);
      $user_completed_raw = $this->get_user_activity( $user->ID );

      header("Pragma: public");
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: private", false);
      header("Content-Type: application/octet-stream");
      header("Content-Disposition: attachment; filename=\"wpcomplete-user-$user_id.csv\";");
      header("Content-Transfer-Encoding: binary");

      $csv = '"Post ID","Button ID","Started","Completed"';

      $total_posts = $this->get_completable_posts();
      foreach ($total_posts as $post_id => $post_data) {
        $post_buttons = ( isset( $post_data['buttons'] ) && is_array( $post_data['buttons'] ) && !empty( $post_data['buttons'] ) ) ? $post_data['buttons'] : array(''.$post_id);

        foreach ($post_buttons as $button_id) {
          if ( isset( $user_completed_raw[$button_id] ) ) {
            $csv .= "\n\"".$post_id.'","'.$button_id.'",';
            if ( isset( $user_completed_raw[$button_id]['first_seen'] ) ) {
              $csv .= '"' . $user_completed_raw[$button_id]['first_seen'] . '",';
            } else {
              $csv .= '"No",';
            }
            if ( isset( $user_completed_raw[$button_id]['completed'] ) ) {
              if ($user_completed_raw[$button_id]['completed'] === true) {
                $user_completed_raw[$button_id]['completed'] = 'Yes';
              }
              $csv .= '"' . $user_completed_raw[$button_id]['completed'] . '"';
            } else {
              $csv .= '"No"';
            }
          } else {
            $csv .= "\n\"" . $post_id . '","' . $button_id . '","No","No"';
          }
        }
      }

      echo $csv;
      exit;

    }
  }

  /**
   * Delete button stored in database.
   *
   * @since 2.2.6
   */
  public function delete_button() {
    // Verify nonce.
    check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

    // delete the button...
    // add flash message?
    $post_id = (int) sanitize_text_field( wp_unslash( $_REQUEST['post_id'] ?? '' ) );

    // Verify user capabilities.
    if ( ! current_user_can( 'edit_posts', $post_id ) ) {
      wp_send_json_error( 'Unauthorized', 403 );
      exit;
    }

    $post_meta_json = get_post_meta( $post_id, 'wpcomplete', true );
    $post_meta = json_decode( stripslashes( $post_meta_json ), JSON_UNESCAPED_UNICODE );

    //var_dump($post_meta);
    if ( isset( $_REQUEST['button'] ) ) {
      $buttons = $post_meta['buttons'];
      // delete specific button
      $key = array_search( sanitize_text_field( wp_unslash( $_REQUEST['button'] ?? '' ) ), $buttons );
      unset($buttons[$key]);
    } else {
      $buttons = array();
    }

    $post_meta['buttons'] = $buttons;

    //var_dump($post_meta);

    $saved = update_post_meta( $post_id, 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );

    //wp_redirect( $_SERVER['HTTP_REFERER'] );
    $response = json_encode( $saved, JSON_UNESCAPED_UNICODE );
    echo $response;
    exit();
  }

  /**
   * Delete button data for all users stored in database.
   *
   * @since 2.7.0
   */
  public function reset_button() {
    // Verify nonce.
    check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

    // Verify user capabilities.
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized', 403 );
      exit;
    }

    $button = sanitize_text_field( wp_unslash( $_REQUEST['button'] ?? '' ) );
    // loop through each user
    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    // Get all users that are able to complete the post:
    $args = array('fields' => 'all');
    if ($selected_role != 'all') $args['role'] = $selected_role;
    $total_users = get_users($args);
    $total_posts = $this->get_completable_posts();

    $users_reset = array();
    foreach ($total_users as $user) {
      $user_activity = $this->get_user_activity($user->ID);
      if ( isset( $user_activity[$button] ) ) {
        // TODO: don't unset entirely... just store somehow else?
        $user_activity[$button] = array();
        unset( $user_activity[$button] );
        $this->set_user_activity($user_activity, $user->ID);
        $users_reset[] = $user->ID;
      }
    }

    //wp_redirect( $_SERVER['HTTP_REFERER'] );
    $response = json_encode( $users_reset, JSON_UNESCAPED_UNICODE );
    echo $response;
    exit();
  }

  /**
   * Add multiple buttons to a post's meta data if they exist in the post content.
   *
   * @since  2.0.0
   * @last   2.8.8
   */
  public function add_multiple_buttons_to_meta($post_id, $post_meta, $post_content = '') {
    // check if we need to store multiple buttons...
    if ( false !== strpos( $post_content, '[' ) ) {
      // Check for shortcodes to see what buttons we have...
      //$pattern = get_shortcode_regex();
      // We just want wpcomplete buttons... can ignore anything else:
      $pattern = '\[(\[?)(complete_button|wpc_complete_button|wpc_button|wpcomplete_button)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)';
      preg_match_all('/'.$pattern.'/s', $post_content, $matches);

      $shortcodes = array_unique( $matches[0] );

      // loop through and only keep button tags...
      $shortcodes = array_filter($shortcodes, function($value) {
        if (strstr($value, '[complete_button') !== false) return true;
        if (strstr($value, '[wpc_complete_button') !== false) return true;
        if (strstr($value, '[wpc_button') !== false) return true;
        if (strstr($value, '[wpcomplete_button') !== false) return true;

        return false;
      });

      $buttons = array();
      if ( isset( $post_meta['buttons'] ) && is_array( $post_meta['buttons'] ) ) {
        $buttons = $post_meta['buttons'];
      }
      foreach ($shortcodes as $code) {
        // normalize button code...
        $code = str_replace('[complete_button', '[wpc_complete_button', $code);
        $code = str_replace('[wpc_button', '[wpc_complete_button', $code);
        $code = str_replace('[wpcomplete_button', '[wpc_complete_button', $code);
        $code = str_replace("“", '"', $code);
        $code = str_replace("”", '"', $code);
        $code = str_replace("`", '"', $code);
        $code = str_replace("'", '"', $code);
        $code = str_replace('”', '"', $code);

        $parsed_args = shortcode_parse_atts($code);

        if ( count( $parsed_args ) <= 1 ) {
          // no attributes:
          $buttons[] = $this->get_button_id($post_id);
        } else {
          $unparsed_args = stripslashes(trim(str_replace(array('[wpc_complete_button', ']'), '', $code)));
          //$atts_array = new SimpleXMLElement("<element " . stripslashes($unparsed_args) . " />");
          // new in 2.8.8 (better shortcode parsing:
          $atts_array = @simplexml_load_string("<div " . $unparsed_args . "></div>");

          if (!$atts_array) {
            if ( strpos( $unparsed_args, '"' ) === false ) {
              $orig_unparsed_args = $unparsed_args;
              // Shortcode attributes aren't surrounded by quotes. We should just fix it...
              $unparsed_args = trim(preg_replace('/=([^"]+) /', '="$1" ', $unparsed_args . " "));

              // let's check again:
              $atts_array = @simplexml_load_string("<div " . $unparsed_args . "></div>");
              if (!$atts_array) {
                throw new UnexpectedValueException('A WPComplete button shortcode used in post #' . $post_id . ' contains invalid formatting that we attempted to fix but couldn\'t: [wpc_button ' . $orig_unparsed_args . ']');
              }
              // ... but for now, throw a specific error letting the user know:
              //throw new UnexpectedValueException('A WPComplete button shortcode used in post #' . $post_id . ' doesn\'t wrap attribute values in quotes: [wpc_button ' . $unparsed_args . ']');
            } else {
              // Not sure how to handle this, so throw exception and let the user know what is causing the parsing error:
              throw new UnexpectedValueException('A WPComplete button shortcode used in post #' . $post_id . ' contains invalid formatting and caused an error when attempting to save: [wpc_button ' . $unparsed_args . ']');
            }
          }
          $parsed_args = current((array) $atts_array);

          // cleanup attributes...
          $args = array();
          foreach( $parsed_args as $key => $value ) {
            if ( $key == 'name' ) $key = 'id';
            if ( $key == 'post' ) $key = 'post_id';
            $args[$key] = $value;
          }

          if ( isset( $args['post_id'] ) && !empty( $args['post_id'] ) ) {
            // Skip this button, because its not related to this post...
            continue;
          }

          // build button based on defaults...
          if ( isset( $args['id'] ) && !empty( $args['id'] ) ) {
            $buttons[] = $this->get_button_id($post_id, $args['id']);
          } else {
            $buttons[] = $this->get_button_id($post_id);
          }
        }
      }

      $buttons = array_unique($buttons);
      // Clean out invalid buttons from other posts (we don't care)...
      foreach ($buttons as $key => $value) {
        if (substr($value, 0, strlen(''.$post_id)) !== ''.$post_id) {
          unset($buttons[$key]);
        }
      }

      // Only store the buttons if we have more than 1 or its named different than the post id:
      if ( ( count( $buttons ) > 0 ) && ( $buttons != array( ''.$post_id ) ) ) {
        $post_meta['buttons'] = $buttons;
      }

    }

    return $post_meta;
  }

  /**
   * PREMIUM:
   * Autocomplete ajax lookup function. Given search criteria, returns matching posts and pages.
   *
   * @since  1.0.0
   * @last 2.4.2
   */
  public function post_lookup() {
    // Verify nonce.
    check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

    $current_post_id = (int) $_GET['post_id'];
    $term = strtolower( $_GET['term'] );
    $suggestions = array();

    if ( current_user_can( 'edit_posts', $current_post_id ) )  {
      // We want to allow redirect to ANY post type on completion, not just enabled ones:
      $posts = get_posts( array('s' => $term, 'posts_per_page' => 20, 'post_type' => 'any', 'post__not_in' => array($current_post_id)) );

      foreach ($posts as $post) {
        $suggestion = array();
        $post_type = get_post_type_object($post->post_type);
        $sname = $post_type->labels->singular_name;
        $suggestion['label'] = $post->post_title . " (" . __($sname, $this->plugin_name) . " #" . $post->ID . ")";
        $suggestion['link'] = get_permalink($post);

        // only display the post as an option to redirect to if the user has access to edit it:
        // not sure if this totally makes sense, but :shrug:
        //if ( current_user_can( 'edit_posts', $post->ID ) )  {
          $suggestions[] = $suggestion;
        //}
      }
    }

    $response = json_encode( $suggestions, JSON_UNESCAPED_UNICODE );
    echo $response;
    exit();
  }

  /**
   * Script that deletes a user's course completion data.
   *
   * @since  2.3.0
   * @last   2.9.0.8
   */
  public function delete_user_data( ) {
    if ( ! current_user_can( 'edit_users' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! $_GET['user_id'] ) {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! WPCOMPLETE_IS_ACTIVATED ) {
      wp_die( __( 'You only get access to this data once you activate your license.' ) );
    }

    $user_id = (int) $_REQUEST['user_id'];
    $user_completed = array();

    // delete a users specific course info...
    if ( isset( $_REQUEST['course'] ) && !empty( $_REQUEST['course'] ) ) {
      $course = sanitize_text_field($_REQUEST['course']);
      if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'delete_user_course_data-' . $user_id . '-' . $course ) ) {
        wp_die( __( 'Are you sure you have permission to do this? No nonce present.' ) );
      }

      $user_completed = $this->get_user_activity($user_id);

      $buttons = $this->get_course_buttons($course);

      foreach ( $buttons as $button ) {
        // Remove this button's data:
        if ( isset( $user_completed[ $button ] ) ) {
          unset($user_completed[ $button ]);
        }
      }
    } else {
      if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'delete_user_data-' . $user_id ) ) {
        wp_die( __( 'Are you sure you have permission to do this? No nonce present.' ) );
      }
    }

    $this->set_user_activity($user_completed, $user_id);

    if ( wp_get_referer() ) {
      wp_safe_redirect( wp_get_referer() );
    } else {
      wp_safe_redirect( admin_url("users.php?page=wpcomplete-users&user_id=" . $_REQUEST['user_id']) );
    }

  }

  /**
   * Script that marks a user's specific button as completed or incomplete.
   *
   * @since  2.9.0
   * @last   2.9.0
   */
  public function admin_user_completion( ) {
    if ( ! current_user_can( 'edit_users' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! $_REQUEST['user_id'] ) {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( ! WPCOMPLETE_IS_ACTIVATED ) {
      wp_die( __( 'You only get access to this data once you activate your license.' ) );
    }

    $user_id = $_REQUEST['user_id'];
    $user_completed = $this->get_user_activity($user_id);

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
      update_post_meta( $post_id, 'wpcomplete', json_encode( $post_meta, JSON_UNESCAPED_UNICODE ) );
      wp_cache_set( "posts", json_encode( $posts, JSON_UNESCAPED_UNICODE ), 'wpcomplete' );
    }

    // Mark this button as completed:
    if ( ! isset( $user_completed[ $unique_button_id ] ) ) $user_completed[ $unique_button_id ] = array();
    if ( $_REQUEST['complete'] === 'false' ) {
      unset($user_completed[ $unique_button_id ]['completed']);
    } else {
      $user_completed[ $unique_button_id ]['completed'] = date('Y-m-d H:i:s');
    }
    if ( ! isset( $user_completed[ $unique_button_id ]['admin_activity'] ) ) $user_completed[ $unique_button_id ]['admin_activity'] = array();
    $user_completed[ $unique_button_id ]['admin_activity'][] = date('Y-m-d H:i:s');

    // Save to database/cache:
    $this->set_user_activity($user_completed, $user_id);

    if ( wp_get_referer() ) {
      wp_safe_redirect( wp_get_referer() );
    } else {
      wp_safe_redirect( admin_url("users.php?page=wpcomplete-users&user_id=" . $_REQUEST['user_id']) );
    }
  }

}
