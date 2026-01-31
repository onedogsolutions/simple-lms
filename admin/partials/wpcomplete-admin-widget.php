
<table style="width: 100%; margin-bottom: 30px;">
  <tr>
    <th></th>
    <th>Buttons</th>
    <th>Started</th>
    <th>Finished</th>
  </tr>
<?php foreach ($courses as $course_name => $course_info) { ?>
<tr>
  <td><?php echo $course_name; ?></td>
  <td style="text-align: center;"><?php echo $course_info['buttons']; ?></td>
  <td style="text-align: center;"><?php echo $course_info['started']; ?> User<?php if ( $course_info['started'] !== 1) echo 's'; ?></td>
  <td style="text-align: center;"><?php echo $course_info['finished']; ?> User<?php if ( $course_info['finished'] !== 1) echo 's'; ?></td>
</tr>
<?php } ?>
</table>

<a href="./options-general.php?page=<?php echo $this->plugin_name; ?>" class="button button-primary">Settings</a>
