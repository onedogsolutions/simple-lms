
  <p>
    <input type="hidden" name="wpcomplete[completable]" value="false">
    <label><input type="checkbox" id="completable" name="wpcomplete[completable]" value="true"<?php if ($completable) echo " checked"; ?> onclick="jQuery('#completable-enabled').toggle();"><?php echo __( 'Yes, I want this page to be completable.', $this->plugin_name ); ?></label>
  </p>

  <div id="completable-enabled"<?php if (!$completable) echo " style='display:none;'"; ?>>
    <p style="margin-top: -10px;"><em><small>
      <?php if ( get_option( $this->plugin_name . '_auto_append', 'true' ) === 'true' ) { ?>
      As per your <a href="options-general.php?page=<?php echo $this->plugin_name; ?>">settings</a>, if you do not include the shortcode <code>[wpc_button]</code> anywhere inside your page content, we will auto append a button at the end of this page.
      <?php } else { ?>
      As per your <a href="options-general.php?page=<?php echo $this->plugin_name; ?>">settings</a>, you are responsible for using the <code>[wpc_button]</code> shortcode to place your button(s) on this page.
      <?php } ?>
    </small></em></p>

    <?php if ( !WPCOMPLETE_IS_ACTIVATED ) : ?>
    <!-- FREE: -->
    <p>
      <?php echo __( 'Upgrade to the <a href="https://wpcomplete.co">PRO version</a> for support and to unlock all available features.', $this->plugin_name ); ?>
    </p>

    <?php else : ?>
    <!-- PREMIUM: -->
    <p>
      <label for="course-assigned"><?php echo __( 'This is a part of:', $this->plugin_name ); ?></label>
      <select name="wpcomplete[course]" id="course-assigned" class="course-toggle course-rename" onchange="if (this.value == '--new--') { jQuery('.course-toggle').toggle(); jQuery('select.course-toggle').attr('disabled', 'disabled'); jQuery('.course-toggle input').attr('disabled', false); jQuery('.course-toggle input:last-child').focus(); this.selectedIndex = 0; } else if (this.value == '--rename--') { jQuery('.course-rename').toggle(); jQuery('select.course-rename').attr('disabled', 'disabled'); jQuery('.course-rename input').attr('disabled', false); jQuery('.course-rename input:last-child').focus(); this.selectedIndex = 0; }">
        <option value="true"><?php echo get_bloginfo( 'name' ); ?></option>
        <?php $course_names = $this->get_course_names(); ?>
        <?php if ($course_names) : ?>
          <?php foreach ( $course_names as $course_name ) : ?>
        <option value="<?php echo $course_name; ?>"<?php if ($course_name == $post_course) echo ' selected'; ?>><?php echo $course_name; ?></option>
          <?php endforeach; ?>
          <?php if ($post_course) : ?>
        <option value="--rename--">-- <?php echo __( 'Rename current course', $this->plugin_name ); ?> --</option>
          <?php endif; ?>
        <?php endif; ?>
        <option value="--new--">-- <?php echo __( 'Add a new course', $this->plugin_name ); ?> --</option>
      </select>
      <?php if ($post_course) : ?>
      <span class="course-rename" style="display: none;">
        <input type="hidden" name="wpcomplete[course-original]" value="<?php echo $post_course; ?>" disabled>
        <input type="text" name="wpcomplete[course-rename]" value="<?php echo $post_course; ?>" disabled> or <a href="javascript:void();" onclick="jQuery('.course-rename').toggle(); jQuery('select.course-rename').attr('disabled', false); jQuery('.course-rename input').attr('disabled', 'disabled'); jQuery('select.course-rename').val('<?php echo $post_course; ?>');">cancel</a>
      </span>
      <?php endif; ?>
      <span class="course-toggle" style="display: none;">
        <input type="text" name="wpcomplete[course-custom]" disabled> or <a href="javascript:void();" onclick="jQuery('.course-toggle').toggle(); jQuery('select.course-toggle').attr('disabled', false); jQuery('.course-toggle input').attr('disabled', 'disabled'); ">cancel</a>
      </span>
    </p>

    <p>
      <label for="completion_redirect_url"><?php echo __( 'Where would you like to redirect your students upon marking all buttons on this page as completed?', $this->plugin_name ); ?></label><br>
      <input type="text" id="completion_redirect_to" name="wpcomplete[completion-redirect-to]" value="<?php echo $redirect['title']; ?>" placeholder="">
      <input type="hidden" id="completion_redirect_url" name="wpcomplete[completion-redirect-url]" value="<?php echo $redirect['url']; ?>">
      <span class="howto"><?php echo __( 'Leave empty to not redirect.', $this->plugin_name ); ?></span>
    </p>

      <?php if ( isset($post_meta['buttons']) && count($post_meta['buttons']) > 0 ) : ?>
    <h4>Registered Buttons</h4>
    <p><em><small>Note: Buttons are added when present in the post's content body or when a user views the button for the first time. If you delete the button below, but do not remove the shortcode from the post content, the button will continue to appear and re-register.</small></em></p>

    <div class="wpc-buttons-container">
      <ul style="list-style: disc; margin: 1em;">
      <?php 
        foreach ($post_meta['buttons'] as $key => $button) :
          list($post_id, $button_id) = $this->extract_button_info($button); 
      ?>
      <li>
        <?php echo (empty($button_id)) ? 'Default' : $button_id; ?> - <a href="#" class="wpc_delete_button" data-post-id="<?php echo $post_id; ?>" data-button="<?php echo $button; ?>">(delete)</a>
      </li>
      <?php endforeach; ?>
      </ul>
        <?php if ( count( $post_meta['buttons'] ) > 1 ) : ?>
      <a href="#" class="wpc_delete_all" data-post-id="<?php echo $post_id; ?>">Reset all buttons</a>
        <?php endif; ?>
    </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>  
