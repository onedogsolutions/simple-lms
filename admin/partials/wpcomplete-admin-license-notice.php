
  <div class="notice update-nag license-nag is-dismissible" style="display: block;">
    <p><?php echo $msg; ?></p>

    <?php if ( $pagenow != 'options-general.php' ) { ?>
    <p>
      <a href="<?php echo admin_url( 'options-general.php?page=' . $this->plugin_name ); ?>">
        <?php _e( 'Complete the setup now.', $this->plugin_name ); ?>
      </a>
    </p>
    <?php } ?>
  </div>
