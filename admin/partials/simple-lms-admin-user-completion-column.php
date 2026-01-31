
<div id="completable-<?php echo $user_id; ?>">

  <?php foreach ($courses as $name => $values) : ?>
  <a href="users.php?page=simple-lms-users&amp;user_id=<?php echo $user_id; ?>#<?php echo $name; ?>">
    <?php echo $name; ?>: <?php echo $values['stats']['completed']; ?> / <?php echo count($values['buttons']); ?>
    <?php if (count($values['buttons']) > 0) : ?> (<?php echo round(100 * ($values['stats']['completed'] / count($values['buttons'])), 1); ?>%)<?php endif; ?><br>
  </a>
  <?php endforeach; ?>
  
</div>
