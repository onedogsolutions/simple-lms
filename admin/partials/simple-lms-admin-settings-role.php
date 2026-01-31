  
  <select name="<?php echo esc_attr($this->plugin_name . '_role'); ?>" id="<?php echo esc_attr($this->plugin_name . '_role'); ?>"<?php if ($disabled) echo " disabled" ?>>
    <?php wp_dropdown_roles($selected_role); ?>
    <option value="all"<?php if ($selected_role == 'all') echo ' selected="selected"'; ?>>All Logged In Users</option>
  </select>  <?php if ($disabled) echo '<span class="profeature"></span>' ?><br>
  <small>Note: All logged in users will see buttons and graphs, but only the selected Student Role Type(s) will be tracked.</small>
