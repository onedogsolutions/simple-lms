<?php if ( $column_name == 'completable' ) { ?>
  <fieldset>
    <div class="inline-edit-col column-<?php echo $column_name; ?>">
      <label class="inline-edit-group">
        <?php wp_nonce_field( $this->plugin_name, 'completable_nonce' ); ?>
        <input type="hidden" name="wpcomplete[completable]" value="false">
        <input type="checkbox" name="wpcomplete[completable]" value="true" onclick="jQuery(this).closest('.inline-edit-col').find('.wpcomplete-course-container').toggle();"><?php echo __( 'Yes, I want this page to be completable.', $this->plugin_name ); ?>
      </label>

      <div class="inline-edit-group wpcomplete-course-container">
        <label for="course-assigned">
          <span class="title" style="width: 100px;"><?php echo __( 'This is a part of:', $this->plugin_name ); ?></span>
          <select name="wpcomplete[course]" class="course-toggle">
            <option value="true"><?php echo get_bloginfo( 'name' ); ?></option>
            <?php foreach ( $this->get_course_names() as $course_name ) : ?>
            <option value="<?php echo $course_name; ?>"><?php echo $course_name; ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>
  </fieldset>
<?php } ?>
