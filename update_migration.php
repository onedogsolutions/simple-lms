<?php
$file = 'includes/class-migration.php';
$content = file_get_contents($file);

$content = str_replace(
    'return self::migrate_progress_batch($limit);',
    'return self::migrate_progress_batch($limit);',
    $content
);

file_put_contents($file, $content);
