<div class="wrap">

  <div style="float: right">

    <a href="<?php echo $_SERVER['REQUEST_URI']; ?>&amp;export" class="button button-primary">Export to CSV</a>

  </div>

  <h1><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 90 90" class="logo"><path class="inner" fill="#0a4de5" d="M47.6,70.9c-1.1,2.3-4.1,2.8-5.9,1L16.1,46.5c-1.5-1.6-1.4-4.1,0.3-5.4l3.4-3.4c1.3-1.1,3.2-1.1,4.6,0
    l13.5,11.9c1.5,1.2,3.8,1,5-0.5c7.7-8.9,21.7-24.8,40.3-37.4C76.8,2.8,65.2,0,45,0C9,0,0,9,0,45s9,45,45,45s45-9,45-45
    c0-11.3-0.9-19.9-3.2-26.4C69.2,35.9,54.3,56.8,47.6,70.9z"></path></svg></h1>

  <?php foreach ($courses as $name => $values) : ?>
  <div class="tablenav top" id="<?php echo $name; ?>" style="padding-top: 32px;">
    <div class="alignleft actions bulkactions">
      <h2 style="margin-top: 8px;"><?php echo $name; ?></h2>
    </div>
    <br class="clear">
  </div>

  <table class="wp-list-table widefat fixed striped users">
    <thead>
      <tr>
        <th scope="col" class='manage-column column-title'>Post - Button</th>
        <?php if (count($total_users) > 0) : ?>
        <th scope="col" class='manage-column column-started num'>User Started (<?php echo $values['stats']['started'] . '/' . $values['stats']['users']; ?>)</th>
        <?php endif; ?>
        <th scope="col" class='manage-column column-completion num'>User Completion (<?php echo $values['stats']['finished'] . '/' . $values['stats']['users']; ?>)</th>
        <?php if (count($total_users) > 0) : ?>
        <th scope="col" class='manage-column column-reset'></th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody data-wp-lists='list:posts'>
      <?php foreach ($values['buttons'] as $button => $info) : ?>
      <tr id='post-<?php echo $post_id; ?>'>
        <td class='name column-title' data-colname="Title">
          <a href="<?php echo $info['link']; ?>"><?php echo $button ?></a>
        </td>
        <?php if (count($total_users) > 0) : ?>
        <td class='completable column-started num' data-colname="User Started">
          <div id="completable-<?php echo $button; ?>-started">
          <?php if ( $info['status'] === 'future' ) : ?>
          Scheduled
          <?php elseif ( $info['status'] === 'pending' ) : ?>
          Pending Approval
          <?php else : ?>
          <?php echo $info['started']; ?> / <?php echo count($total_users); ?> (<?php echo round($info['started'] / count($total_users), 4) * 100; ?>%)
          <?php endif; ?>
          </div>
        </td>
        <?php endif; ?>
        <td class='completable column-completion num' data-colname="User Completion">
          <div id="completable-<?php echo $button; ?>">
            <?php if ( $info['status'] === 'future' ) : ?>
            Scheduled
            <?php elseif ( $info['status'] === 'pending' ) : ?>
            Pending Approval
            <?php elseif (count($total_users) > 0) : ?>
            <?php echo $info['completed']; ?> / <?php echo count($total_users); ?> (<?php echo round($info['completed'] / count($total_users), 4) * 100; ?>%)
            <?php else : ?>
            <em title="Adjust your Completable User Types in the <?php echo SIMPLE_LMS_PRODUCT_NAME; ?> Settings page">No users found.</em>
            <?php endif; ?>
          </div>
        </td>
        <?php if (count($total_users) > 0) : ?>
        <td class="completable column-reset" data-colname="Reset">
          <a href="#" class="wpc_reset_button" data-button-id="<?php echo $info['id']; ?>">Reset User Activity</a>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endforeach; ?>
  <?php if (count($courses) <= 0) : ?>
  <div>
    <p>Welcome to <?php echo SIMPLE_LMS_PRODUCT_NAME; ?>! This page will populate with the courses and completable content that you enable.</p>
    <p>To adjust your settings, please visit your <a href="<?php echo admin_url( 'options-general.php?page=' . SIMPLE_LMS_PREFIX ); ?>"><?php echo SIMPLE_LMS_PRODUCT_NAME; ?> Settings</a> page.</p>
  </div>
  <?php endif; ?>

</div>
