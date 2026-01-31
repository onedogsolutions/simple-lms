<?php

/**
 * Common functionality of the plugin.
 *
 * @link       https://simplelms.co
 * @since      2.0.0
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 */

/**
 * The common functionality throughout the plugin.
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 * @author     Zack Gilbert <zack@zackgilbert.com>
 */
class SimpleLMS_Common {

  /**
   * The ID of this plugin.
   *
   * @since    2.0.0
   * @access   protected
   * @var      string    $plugin_name    The ID of this plugin.
   */
  protected $plugin_name;

  /**
   * The version of this plugin.
   *
   * @since    2.0.0
   * @access   protected
   * @var      string    $version    The current version of this plugin.
   */
  protected $version;

  /**
   * Initialize the class and set its properties.
   *
   * @since      2.0.0
   * @param      string    $plugin_name       The name of the plugin.
   * @param      string    $version    The version of this plugin.
   */
  public function __construct( $plugin_name, $version ) {

    $this->plugin_name = $plugin_name;
    $this->version = $version;

  }

  /**
   * Returns an array of all wordpress post types that can be completed. This includes custom types.
   *
   * @since  2.0.0
   * @last 2.7.0
   */
  public function get_enabled_post_types() {
    $post_type = get_option( $this->plugin_name . '_post_type', 'page_post' );
    if ( $post_type == 'page_post' ) {
      $screens = array();
      $screens['post'] = 'post';
      $screens['page'] = 'page';
    } else if ( $post_type == 'all' ) {
      $screens = get_post_types( array( '_builtin' => false ) );
      $screens['post'] = 'post';
      $screens['page'] = 'page';
    } else {
      $screens = explode( ',', $post_type );
    }
    return $screens;
  }

  /**
   * Returns an array of all the current user's completed posts.
   *
   * @since  2.0.0
   */
  public function get_user_completed($user_id = false, $prefetch_all = false) {
    if (!$user_id) $user_id = get_current_user_id();
    $user_completed = array();
    
    if ( $prefetch_all ) {
      $user_activity = $this->get_user_activity_from_all($user_id);
    } else {
      $user_activity = $this->get_user_activity($user_id);
    }

    foreach ($user_activity as $button_id => $value) {
      if (isset($value['completed']) && !empty($value['completed'])) {
        $user_completed[$button_id] = $value;
      }
    }

    return $user_completed;
  }

  /**
   * Returns an array of all the current user's completion activity.
   *
   * @since  2.0.0
   * @last   2.9.0
   */
  public function get_user_activity($user_id = false) {
    if (!$user_id) $user_id = get_current_user_id();
    // First: check if we have this cached already in the page request:
    if ( $user_completed_json = wp_cache_get( "user-" . $user_id, 'simple-lms' ) ) {
      return json_decode( $user_completed_json, true );     
    }
    // Second: check if we have all users already cached previously in the page request:
    //if ( wp_cache_get( "user-all", 'simple-lms' ) ) {
    //  return $this->get_user_activity_from_all( $user_id );     
    //}
    // Third: check the database for newest database structure (2.0 format):
    if ( $user_completed_json = get_user_meta( $user_id, 'simple-lms', true ) ) {
      // Save new format into page request cache:
      wp_cache_set( "user-" . $user_id, $user_completed_json, 'simple-lms' );
      return json_decode( $user_completed_json, true );
    }
    // Otherwise, we have the older format version... 
    // this should only run once per user...
    
    // Check for older database formats:
    $user_completed_json = get_user_meta( $user_id, 'wp_completed', true );
    $user_completed = ( $user_completed_json ) ? json_decode( $user_completed_json, true ) : array();
    // Convert old old format to new storage format if we didn't track time of completion:  
    if ( $user_completed == array_values( $user_completed ) ) {
      $new_array = array();
      foreach ( $user_completed as $p ) {
        $new_array[ $p ] = true;
      }
      $user_completed = $new_array;
    }

    // Convert to new 2.0 format:
    if ( ( count($user_completed) > 0 ) && ! is_array( current($user_completed) ) ) {
      // if it's not, correct it...
      $_user_completed = array();
      foreach ( $user_completed as $post_id => $value ) {
        $_user_completed[ $post_id ] = array(
          "completed" => $value
        );
      }
      $user_completed = $_user_completed;
    }

    $this->set_user_activity($user_completed, $user_id);
    //delete_user_meta( $user_id, 'simple-lms' );

    return $user_completed;
  }

  /**
   * Returns an array of all the current user's completion activity, while getting ALL users activity.
   *
   * @since  2.9.0
   */
  public function get_user_activity_from_all( $user_id = false ) {
    global $wpdb;
    if (!$user_id) $user_id = get_current_user_id();
    
    // check to see if we've already cached this user's data...
    if ( $user_completed_json = wp_cache_get( "user-" . $user_id, 'simple-lms' ) ) {
      return json_decode( $user_completed_json, true );     
    }
    // TODO: optimize this... if it's too big, it causes timeouts
    // It's grabbing too much unnecessary data and storing all of it.
    /*if ( $users_json = wp_cache_get( 'users-all', 'simple-lms' ) ) {
      var_dump($users_json);
      $users = json_decode( $users_json, true );
    } else {
      // if not, fetch ALL users' SimpleLMS metadata from the database...
      $users = $wpdb->get_results( $wpdb->prepare( "SELECT um.user_id, um.meta_value FROM {$wpdb->usermeta} um WHERE um.meta_key = %s", 'simple-lms' ), ARRAY_A );
      // store it in cache for easy access...
      var_dump($users);      
      wp_cache_set( "users-all", json_encode( $users, JSON_UNESCAPED_UNICODE ), 'simple-lms' );
    }
    // return just the specific user we want...
    foreach ($users as $key => $user) {
      if ( $user['user_id'] === "".$user_id ) {
        return json_decode( $user['meta_value'], true );
      }
    }

    // return empty handed...
    return array();*/
    // just use our default activity fetcher...
    return $this->get_user_activity( $user_id );
  }

  /**
   * Accepts new user completed data that should be stored in database. 
   * Returns an array of all the current user's completed posts.
   *
   * @since  2.0.0
   * @last   2.0.3
   */
  public function set_user_activity($data, $user_id = false) {
    if (!$user_id) $user_id = get_current_user_id();

    $data['0-site'] = time();
    
    if (!is_string($data)) {
      $data = json_encode( $data, JSON_UNESCAPED_UNICODE );
    }

    // Update the database with the new data:
    $saved = update_user_meta( $user_id, 'simple-lms', $data );

    // If database saved, we should try to cache it for the rest of the page request:
    if ( $saved ) {
      // Save new user completion data into page request cache:
      wp_cache_set( "user-" . $user_id, $data, 'simple-lms' );
    }

    return $saved;
  }

  /**
   * Returns a string containing the normalized class name for the current course.
   *
   * @since  2.0.0
   * @last   2.9.0.9
   */
  public function get_button_class($button) {
    return trim( sanitize_title( strtolower( $button ) ) );
  }

  /**
   * Returns a string containing the normalized class name for the supplied course(s).
   *
   * @since  2.0.0
   * @last   2.9.0.9
   */
  public function get_course_class($atts = array()) {
    $course = get_bloginfo( 'name' );
    
    if ( is_string( $atts ) && !empty( $atts ) ) {
      $course = $atts;
    } else if ( is_array( $atts ) && isset( $atts['course'] ) ) {
      if ( strtolower( $atts['course'] ) == 'all') {
        return 'all-courses';
      } elseif ( $atts['course'] !== false ) {
        $course = $atts['course'];
      }
    }

    return trim( sanitize_title( strtolower( $course ) ) );
  }

  /**
   * Returns a string containing the percentage of completed / total posts for a given course.
   *
   * @since  2.0.0
   * @last   2.8.5
   */
  public function get_percentage($atts = array()) {
    $user_completed = $this->get_user_completed();
    if ( !isset( $atts['scheduled'] ) ) {
      $atts['scheduled'] = 'false';
    }

    $total_buttons = $this->get_buttons( $atts );

    if ( count($total_buttons) > 0 ) {
      $completed_posts = array_intersect( $total_buttons, array_keys( $user_completed ) );
      $percentage = round(100 * ( count($completed_posts) / count($total_buttons) ), 0);
    } else {
      $percentage = 0;
    }
    return $percentage;
  }

  /**
   * Helper method to query for all pages and posts that are completable.
   *
   * @since    2.0.0
   * @last     2.8.5
   */
  public function get_completable_posts( $include_scheduled = 'true' ) {
    global $wpdb;

    if ( $posts_json = wp_cache_get( 'posts-' . $include_scheduled, 'simple-lms' ) ) {
      return json_decode( $posts_json, true );      
    }

    if ( $include_scheduled === 'false' ) {
      $r = $wpdb->get_results( $wpdb->prepare( "
          SELECT pm.post_id,pm.meta_value,p.post_status FROM {$wpdb->postmeta} pm
          LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
          WHERE pm.meta_key = '%s' 
          AND (p.post_status != '%s')
          AND (p.post_status != '%s')
          AND (p.post_status != '%s')
          AND (p.post_status != '%s')
          AND (p.post_type = '" . join("' OR p.post_type = '", $this->get_enabled_post_types()) . "') ORDER BY p.menu_order, p.post_title ASC", 'simple-lms', 'trash', 'draft', 'future', 'pending'), ARRAY_A );
    } else {
      $r = $wpdb->get_results( $wpdb->prepare( "
          SELECT pm.post_id,pm.meta_value,p.post_status FROM {$wpdb->postmeta} pm
          LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
          WHERE pm.meta_key = '%s' 
          AND (p.post_status != '%s')
          AND (p.post_type = '" . join("' OR p.post_type = '", $this->get_enabled_post_types()) . "') ORDER BY p.menu_order, p.post_title ASC", 'simple-lms', 'trash'), ARRAY_A );
    }

    if ($r && ( count($r) > 0 ) ) {
      // clean up database to be in format we can easily handle:
      $posts = array();
      foreach ($r as $row) {
        $posts[$row['post_id']] = json_decode( stripslashes( $row['meta_value'] ), true );
      }
      wp_cache_set( "posts-" . $include_scheduled, json_encode( $posts, JSON_UNESCAPED_UNICODE ), 'simple-lms' );
      return $posts;
    }

    return array();
  }

  /**
   * Helper method to get the metadata (title + type) for all completable posts.
   *
   * @since    2.5.2
   * @last     2.5.2
   */
  public function get_completable_posts_metadata( ) {
    $post_metadata = array();
    
    if ( $meta_json = wp_cache_get( 'posts_metadata', 'simple-lms' ) ) {
      return json_decode( $meta_json, true );      
    }

    $post_data = $this->get_completable_posts();
    
    foreach ($post_data as $post_id => $post) {
      $post_metadata[$post_id] = array(
        'title' => get_the_title( $post_id ),
        'type' => get_post_type( $post_id )
      );
    }

    wp_cache_set( "posts_metadata", json_encode( $post_metadata, JSON_UNESCAPED_UNICODE ), 'simple-lms' );

    return $post_metadata;
  }

  /**
   * Returns all completable buttons for a specific course.
   *
   * @since    2.0.0
   * @last     2.5.2
   */
  public function get_buttons( $atts = array() ) {
    // do this cleaning at the top, so we check for proper cache:
    if ( !isset( $atts['course'] ) || ( strtolower( $atts['course'] ) == strtolower( get_bloginfo( 'name' ) ) ) || 
         ( strtolower( str_replace( '\,', ',', $atts['course'] ) ) == strtolower( get_bloginfo( 'name' ) ) ) ) {
      $atts['course'] = '';
    }
    $atts['course'] = html_entity_decode( $atts['course'], ENT_QUOTES | ENT_HTML401 );

    if ( !isset( $atts['child_of'] ) ) {
      $atts['child_of'] = '';
    }
    if ( !isset( $atts['scheduled'] ) ) {
      $atts['scheduled'] = 'true';
    }

    // check to see if we already cached this:
    if ( $buttons_json = wp_cache_get( "get_buttons-" . $this->get_course_class($atts['course']) . '-' . $atts['child_of'] . '-' . $atts['scheduled'], 'simple-lms' ) ) {
      return json_decode( $buttons_json, true );     
    }

    $posts = false;
    if ( !empty( $atts['child_of'] ) ) {
      $posts = array();
      $all_posts = $this->get_completable_posts($atts['scheduled']);
      // look through all content types (really only handles pages + posts)
      foreach ($this->get_enabled_post_types() as $key => $type) {
        // if we have a requested child_of, grab all the possible ids of the children...
        if ( $child_pages = get_pages( array( 'child_of' => $atts['child_of'], 'post_type' => $type ) ) ) {
          // map just children completable posts...
          foreach ($child_pages as $p) { 
            if ( isset( $all_posts[$p->ID] ) ) {
              $posts[$p->ID] = $all_posts[$p->ID];
            }
          }
        }
      }
    } else {
      $posts = $this->get_completable_posts($atts['scheduled']);
    }

    $buttons = $this->get_course_buttons($atts['course'], $posts);

    // let's cache it so we don't have to do this again:
    wp_cache_set( "get_buttons-" . $this->get_course_class($atts['course']) . '-' . $atts['child_of'] . '-' . $atts['scheduled'], json_encode( $buttons, JSON_UNESCAPED_UNICODE ), 'simple-lms' );

    return $buttons;
  }

  /**
   * Accepts info about button, and builds a button id.
   *
   * @since  2.0.0
   */
  public function get_button_id( $post_id, $button = '' ) {
    $button_id = '' . $post_id;
    if ( isset( $button ) && !empty( $button ) ) {
      $button_id .= ("-" . $button);
    }
    return $button_id;
  }

  /**
   * Get post id and button name from full button name.
   *
   * @since  2.0.0
   */
  public function extract_button_info( $button = '' ) {
    if (strpos($button, '-') !== false) {
      list($post_id, $button_id) = explode('-', $button, 2);
    } else {
      $post_id = $button;
      $button_id = '';
    }
    return array($post_id, $button_id);
  }

  /**
   * Returns a boolean for whether a post can be marked as completable or not.
   *
   * @since  2.0.0
   */
  public function post_can_complete($post_id) {
    $posts = $this->get_completable_posts();

    return isset($posts[$post_id]);
  }

  /**
   * Returns a string indicating a post's completion status. Can be: not-completable, incomplete, partial or completed
   *
   * @since  2.0.0
   * @last   2.2.0
   */
  public function post_completion_status($post_id, $user_id = false, $post = false, $user_completed = false) {
    if (!$user_id) $user_id = get_current_user_id();
    
    if (!$post) {
      $posts = $this->get_completable_posts();

      if ( ! isset( $posts[$post_id] ) ) {
        // hmm... post isn't completable...
        return 'not-completable';
      }

      $post = $posts[$post_id];
    }    

    if (!$user_completed) $user_completed = $this->get_user_completed($user_id);

    if ( isset( $post['buttons'] ) ) {
      $count = 0;
      foreach ( $post['buttons'] as $button ) {
        if ( isset( $user_completed[$button] ) ) {
          $count++;
        }
      }
      if ( $count <= 0 ) {
        return 'incomplete';
      } elseif ( $count < count($post['buttons']) ) {
        return 'partial';
      } else {
        return 'completed';
      }
    } else {
      if ( isset( $user_completed[$post_id] ) ) {
        return 'completed';
      } else {
        return 'incomplete';
      }
    }
  }

  /**
   * Returns a string indicating a course's completion status. Can be: incomplete, partial or completed
   *
   * @since  2.1.0
   */
  public function course_completion_status($course, $user_id = false) {
    if (!$user_id) $user_id = get_current_user_id();

    $buttons = $this->get_course_buttons($course);
    $user_completed = $this->get_user_completed($user_id);
    
    $count = 0;
    foreach ($buttons as $button) {
      if ( isset( $user_completed[$button] ) ) {
        $count++;
      }
    }

    if ( $count >= count($buttons) ) {
      return 'completed';
    } elseif ( $count <= 0 ) {
      return 'incomplete';
    } else {
      return 'partial';
    }
  }

  /**
   * Returns a string for the name of the course a post is associated with
   *
   * @since  2.0.0
   * @last   2.0.6
   */
  public function post_course($post_id, $posts = false) {
    if ($posts === false) $posts = $this->get_completable_posts();
    // make sure we actually have the post_id, and not the full button name:
    list($post_id, $button) = $this->extract_button_info($post_id);

    if ( ! isset( $posts[$post_id] ) ) {
      // hmm... post isn't completable...
      return false;
    }
    // Get specific post:
    $post = $posts[$post_id];
    // See if post has an assigned course:
    if ( isset( $post['course'] ) ) {
      return html_entity_decode( $post['course'], ENT_QUOTES | ENT_HTML401 );
    }
    // No course...
    return false;
  }

  /**
   * Return a boolean 
   *
   * @since  2.0.0
   * @last   2.0.0
   */
  public function post_has_multiple_buttons($post_id) {
    $posts = $this->get_completable_posts();

    if ( ! isset( $posts[$post_id] ) ) {
      // hmm... post isn't completable...
      return false;
    }
    // Get specific post:
    $post = $posts[$post_id];
    // See if post has multiple buttons:
    return isset( $post['buttons'] ) && ( count($post['buttons']) > 1 );
  }

  /**
   * Accepts a string of a course name (or empty).
   * Returns an array of buttons that belong to that course.
   *
   * @since  2.0.0
   * @last   2.4.3
   */
  public function get_course_buttons($course = '', $posts = false) {
    // do this cleaning at the top, so we check for proper cache:
    if ( ( $this->get_course_class( $course ) == $this->get_course_class( get_bloginfo( 'name' ) ) ) || 
         ( $this->get_course_class( str_replace( '\,', ',', $course ) ) == $this->get_course_class( get_bloginfo( 'name' ) ) ) ) {
      $course = '';
    }
    $course = html_entity_decode( $course, ENT_QUOTES | ENT_HTML401 );
    // check to see if we already cached this:
    if ( $buttons_json = wp_cache_get( "course_buttons-" . $this->get_course_class($course), 'simple-lms' ) ) {
      return json_decode( $buttons_json, true );      
    }
    
    // let's handle multiple courses for a graph, but still handle if there's an escaped \, in the course name:
    $courses = array();
    if ( !empty( $course ) ) {
      if ( !in_array( stripslashes( $course ), $this->get_course_names() ) ) { 
        $tmp_str = str_replace('\,', "**simplelms**", $course);
        $tmp_array = explode( ",", strtolower( str_replace( ", ", ",", $tmp_str ) ) );
        foreach ($tmp_array as $tmp) {
          $courses[] = $this->get_course_class( str_replace("**simplelms**", ",", $tmp) );
        }
      } else {
        $courses = array($this->get_course_class( $course ));
      }
    }
  
    if ($posts === false) $posts = $this->get_completable_posts();
    $buttons = array();
    foreach ($posts as $post_id => $post) {
      if ( isset( $post['course'] ) && !empty( $post['course'] ) ) {
        $post['course'] = html_entity_decode( stripslashes($post['course']), ENT_QUOTES | ENT_HTML401 );
      }

      if ( count($courses) > 0 ) {
        if (strtolower($courses[0]) == 'all') { // All posts on entire site
          if (isset($post['buttons'])) {
            foreach ($post['buttons'] as $button) {
              $buttons[] = $button;
            }
          } else {
            $buttons[] = ''.$post_id;
          }
        // Specific course(s):
        } else if ( isset( $post['course'] ) && in_array( $this->get_course_class( $post['course'] ), $courses ) ) {
          if (isset($post['buttons'])) {
            foreach ($post['buttons'] as $button) {
              $buttons[] = $button;
            }
          } else {
            $buttons[] = ''.$post_id;
          }
        } else if ( !isset( $post['course'] ) && in_array( $this->get_course_class( get_bloginfo( 'name' ) ), $courses ) ) {
          if (isset($post['buttons'])) {
            foreach ($post['buttons'] as $button) {
              $buttons[] = $button;
            }
          } else {
            $buttons[] = ''.$post_id;
          }
        }
      } else { // default SiteTitle Course
        if ( !isset( $post['course'] ) || ( $this->get_course_class( $post['course'] ) == $this->get_course_class( get_bloginfo( 'name' ) ) ) || ( $post['course'] === 'true' ) ) {
          if (isset($post['buttons'])) {
            foreach ($post['buttons'] as $button) {
              $buttons[] = $button;
            }
          } else {
            $buttons[] = ''.$post_id;
          }
        }
      }
    }
    // let's cache it so we don't have to do this again:
    wp_cache_set( "course_buttons-" . $this->get_course_class($course), json_encode( $buttons, JSON_UNESCAPED_UNICODE ), 'simple-lms' );

    return $buttons;
  }

  public function get_course_post_ids( $course, $posts = false ) {
    if ( $posts === false ) $posts = $this->get_completable_posts();
    $post_ids = array();
    foreach ( $posts as $post_id => $data ) {
      if ( !isset( $data['course'] ) || ( $data['course'] === 'true' ) ) $data['course'] = get_bloginfo('name');
    
      if ( ( strtolower( $data['course'] ) == strtolower( $course ) ) || ( $this->get_course_class( $data ) == $this->get_course_class( array('course' => $course) ) ) ) {
        $post_ids[] = ''.$post_id;
      }
    }
    return $post_ids;
  }

  /**
   * Returns an array of all specific courses that have been added to the database.
   *
   * @since  1.4.0
   * @last   2.0.4
   */
  public function get_course_names($posts = false) {
    if ( $course_names = wp_cache_get( 'course_names', 'simple-lms' ) ) {
      return json_decode( $course_names, true );
    }

    $course_names = array();
    if ($posts === false) $posts = $this->get_completable_posts();

    foreach ($posts as $post_id => $info) {
      if ( isset($info['course']) && ( $info['course'] != 'true' ) && ( $info['course'] != get_bloginfo( 'name' ) ) ) {
        $course_names[] = html_entity_decode( $info['course'], ENT_QUOTES | ENT_HTML401 );
      }
    }

    $course_names = array_unique( $course_names );

    wp_cache_set( "course_names", json_encode( $course_names, JSON_UNESCAPED_UNICODE ), 'simple-lms' );

    return $course_names;
  }

  /**
   * Return the most recent time this user has completed any buttons
   *
   * @since  2.9.0
   * @last   2.9.0
   */
  public function get_last_activity($user_id = false) {
    if (!$user_id) $user_id = get_current_user_id();

    $user_completed = $this->get_user_activity($user_id);
    
    if ( $user_completed && isset($user_completed['0-site']) && is_numeric($user_completed['0-site']) ) {
      return $user_completed['0-site'];
    }

    $last_activity = false;
    foreach ($user_completed as $key => $values) {
      if ( is_array($values) ) {
        foreach ($values as $k => $t) {
          if ( !$last_activity || ( $t > $last_activity ) ) {
            $last_activity = $t;
          }
        }
      } else {
        // what else could it be?
      }
    }

    return strtotime($last_activity);
  }

  /**
   * Return a user's roles. Allow to prefetch for ALL users.
   *
   * @since  2.8.10.2
   * @last   2.8.10.2
   */
  function user_has_role( $primary_user_id, $selected_role ) {
    // check if we can use cached results:
    if ( $users = wp_cache_get( 'user_roles-' . $selected_role, 'simple-lms' ) ) {
      $users = json_decode( $users, true );
    } else {
      // if not, cache the results we get:
      $user_roles = new WP_User_Query( array( 'role' => $selected_role ) );
      $users_obj = $user_roles->get_results();
      $users = array();
      foreach ($users_obj as $key => $user) {
        $users["".$user->ID] = $user->roles;
      }
      wp_cache_set( "user_roles-" . $selected_role, json_encode($users, JSON_UNESCAPED_UNICODE), 'simple-lms' );
    }
    // loop through all users to find this specific user:
    foreach($users as $user_id => $user_roles) {
      // check for user id and return if when found...
      if ( $user_id === $primary_user_id ) {
        return in_array( $selected_role, $user_roles );
      }
    }
    return false;
  }

  /**
   * Return boolean of whether user has started a course or not.
   *
   * @since  2.0.0
   * @last   2.9.0
   */
  public function user_has_started_course( $user_activity, $course, $posts = false ) {
    if ( !is_array( $user_activity ) ) {
      $user_activity = $this->get_user_activity_from_all( get_current_user_id() );
    }

    foreach ($user_activity as $post_id => $value) {
      if ( ''.$this->post_course($post_id, $posts) === $course ) {
        if ( isset( $value['first_seen'] ) || isset( $value['completed'] ) ) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Return boolean of whether user has finished a course or not.
   *
   * @since  2.0.0
   * @last   2.9.0
   */
  public function user_has_completed_course( $user_activity, $course, $posts = false ) {
    // Get the buttons just for this course:
    $buttons = $this->get_course_buttons($course, $posts);
    
    if ( count( $buttons ) <= 0 ) return false; 

    if ( !is_array( $user_activity ) ) {
      $user_activity = $this->get_user_activity_from_all( get_current_user_id() );
    }
    foreach ($buttons as $button_id ) {
      // if user hasnt completed this button, they havent completed the course...
      if ( !isset( $user_activity[$button_id] ) || !isset( $user_activity[$button_id]['completed'] ) ) {
        return false;
      }
    }

    return true;
  }

  /**
   * Return course statistics
   *
   * @since  2.9.0
   * @last   2.9.0
   */
  function get_course_stats( $posts = false ) {
    global $wpdb;
    // Get all user activity...
    $selected_role = get_option( $this->plugin_name . '_role', 'subscriber' );
    
    // if we need a specific role, only grab those userse first...
    $from_sql = "{$wpdb->users}";
    if ( $selected_role != 'all' ) {
      $from_sql = "(SELECT u.ID FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} AS mt1 ON ( u.ID = mt1.user_id ) WHERE ( (mt1.meta_key = '{$wpdb->prefix}capabilities') AND (mt1.meta_value LIKE '%\"$selected_role\"%') ))";
    }
    $sql = "SELECT um.user_id, um.meta_value FROM {$from_sql} u INNER JOIN {$wpdb->usermeta} AS um ON ( u.ID = um.user_id ) WHERE ( um.meta_key = 'simple-lms' )";
    $user_activities = $wpdb->get_results( $sql, ARRAY_A );
    // fix key/value association:
    $user_activities = array_column($user_activities, 'meta_value', 'user_id');

    if ( !$posts ) $posts = $this->get_completable_posts();

    $courses = array();
    $course_names = $this->get_course_names($posts);
    foreach ($course_names as $course) {
      // buttons count, number of users that started, number of users that finished
      $courses[$course] = array('buttons' => count($this->get_course_buttons($course, $posts)), 'users' => count($user_activities), 'started' => 0, 'finished' => 0);
    }
    $courses[''] = array('buttons' => count($this->get_course_buttons('', $posts)), 'users' => count($user_activities), 'started' => 0, 'finished' => 0);

    foreach ($user_activities as $user_id => $activity) {
      $activity = json_decode( $activity, true );
      foreach ($courses as $course => $course_info) {
        if ( $this->user_has_started_course( $activity, $course, $posts ) ) {
          $courses[$course]['started']++;
        }
        if ( $this->user_has_completed_course( $activity, $course, $posts ) ) {
          $courses[$course]['finished']++;
        }
      }
    }

    if ( $courses['']['buttons'] > 0 ) {
      $courses[get_bloginfo( 'name' )] = $courses[''];
    }
    unset($courses['']);

    // will caching results help?
    return $courses;
  }

  /**
   * Helper function to set cache breaking constants.
   *
   * @pst
   *
   * @since    2.9.1
   * @last     2.9.1
   */
  public function set_nocache() {
    if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
      // Used by LiteSpeed Cache.
      define( 'LSCACHE_NO_CACHE', true );
    }

    if ( ! defined( 'DONOTCACHEPAGE' ) ) { 
      // Used by WPMU DEV Hummingbird, W3 Total Cache, WP Super Cache.
      define( 'DONOTCACHEPAGE', true );
    }
  }

}
