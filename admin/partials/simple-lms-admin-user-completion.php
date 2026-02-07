
<div class="wrap">

  <h1>
    User Completion - <?php echo $user->user_email; ?> 
    <span style="text-align: right; float: right; font-size: 13px;">
      <a href="<?php echo $_SERVER['REQUEST_URI']; ?>&amp;export" class="button button-primary">Export to CSV</a><br>
      <br>
      <?php echo $user_completed_count; ?> / <?php echo count($total_posts); ?> completed in total
      <?php if (count($user_completed) > 0) : ?>
      <form method="post" action="<?php echo wp_nonce_url( admin_url( "admin-post.php?action=delete_user_data&amp;user_id=" . $user->ID ), 'delete_user_data-' . $user->ID ); ?>" style="display: inline;">
        - <a href="#" onclick="if (confirm('Are you sure you want to delete all of this user\'s completion data?')) { console.log(this.parentNode); this.parentNode.submit(); } return false;">DELETE ALL DATA</a>
      </form>
      <?php endif; ?>
    </span>
  </h1>

  <?php foreach ($courses as $name => $values) : ?>
  <div class="tablenav top" id="<?php echo $name; ?>" style="padding-top: 32px;">
    <div class="alignleft actions bulkactions">
      <h2 style="margin-top: 8px;"><?php echo $name; ?></h2>
    </div>
    <div class="one-page" style="float: right; height: 28px; margin-top: 3px; cursor: default; color: #555;">
      <span class="displaying-num">
        <?php echo $values['stats']['completed']; ?> / <?php echo count($values['buttons']); ?> completed
        <?php if ($values['stats']['completed'] > 0) : ?>
        <form method="post" action="<?php echo wp_nonce_url( admin_url( "admin-post.php?action=delete_user_data&amp;user_id=" . $user->ID . "&amp;course=" . $name ), 'delete_user_course_data-' . $user->ID . '-' . $name ); ?>" style="display: inline;">
        - <a href="#" onclick="if (confirm('Are you sure you want to delete this user\'s course completion data?')) { console.log(this.parentNode); this.parentNode.submit(); } return false;">DELETE COURSE DATA</a>
        </form>
        <?php endif; ?>
      </span>
    </div>
    <br class="clear">
  </div>

  <table class="wp-list-table widefat fixed striped users">
    <thead>
      <tr>
        <th scope="col" class='manage-column column-title'>Post - Button</th>
        <th scope="col" class='manage-column column-started'>Started</th>
        <th scope="col" class='manage-column column-completable'>Completed</th>
        <th scope="col" class='manage-column column-utilities'></th>
      </tr>
    </thead>
    <tbody data-wp-lists='list:posts'>
      <?php foreach ($values['buttons'] as $button => $info) : ?>
      <tr id='post-<?php echo $post_id; ?>'>
        <td class='name column-title' data-colname="Title">
          <a href="<?php echo $info['link']; ?>"><?php echo $button ?></a>
        </td>
        <td class='completable column-started' data-colname="Started">
          <div id="completable-<?php echo $user->ID; ?>-started">
            <?php if ( $info['status'] === 'future' ) : ?>
            Scheduled
            <?php elseif ( $info['status'] === 'pending' ) : ?>
            Pending Approval
            <?php else : ?>
            <?php echo $info['started']; ?> 
            <?php endif; ?>
          </div>
        </td>
        <td class='completable column-completable' data-colname="Completed">
          <div id="completable-<?php echo $this->get_course_class($info['button']); ?>">
            <?php if ( $info['status'] === 'future' ) : ?>
            Scheduled
            <?php elseif ( $info['status'] === 'pending' ) : ?>
            Pending Approval
            <?php else : ?>
            <?php echo $info['completed']; ?>
            <?php endif; ?> 
          </div>
        </td>
        <td class='completable column-utilities' data-colname="Utilities">
          <?php if ( $info['completed'] === 'No' ) : ?>
          <form method="post" action="<?php echo admin_url("admin-post.php?action=user_completion&amp;user_id=" . $user->ID . "&amp;button=" . $info['button'] . "&amp;complete=true"); ?>" style="display: inline;">
            <a href="#" onclick="if (confirm('Are you sure you want to mark \'<?php echo $button; ?>\' completed for this user?')) { this.parentNode.submit(); } return false;">Mark Completed</a>
          </form>
          <?php else : ?>
          <form method="post" action="<?php echo admin_url("admin-post.php?action=user_completion&amp;user_id=" . $user->ID . "&amp;button=" . $info['button'] . "&amp;complete=false"); ?>" style="display: inline;">
            <a href="#" onclick="if (confirm('Are you sure you want to mark \'<?php echo $button; ?>\' as incomplete for this user?')) { this.parentNode.submit(); } return false;">Mark Incomplete</a>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endforeach; ?>

</div>
