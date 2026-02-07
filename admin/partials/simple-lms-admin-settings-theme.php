  
  <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>"<?php if ($disabled) echo " disabled" ?>>
    <?php foreach ($themes as $theme) : ?>
    <option value="<?php echo esc_attr($theme); ?>"<?php if ($selected_theme === $theme) echo ' selected="selected"'; ?>><?php echo ucwords($theme); ?></option>
    <?php endforeach; ?>
    <option value="false"<?php if ($selected_theme === 'false') echo ' selected="selected"'; ?>>None</option>
  </select>  <?php if ($disabled) echo '<span class="profeature"></span>' ?><br>
  <small>Note: Select "None" if you don't intend on using this style of graph.</small>
