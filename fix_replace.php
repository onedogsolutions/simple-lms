<?php
$file = 'includes/class-migration.php';
$content = file_get_contents($file);

$content = str_replace("    public static function get_pending_migration_count()
    {
        global \$wpdb;
        \$count = \$wpdb->get_var(\"SELECT COUNT(DISTINCT user_id) FROM {\$wpdb->usermeta} WHERE meta_key LIKE 'wpcomplete_%'\");
        return (int) \$count;
    }

    /**
     * Get count of courses pending migration.", "    public static function get_pending_migration_count()
    {
        global \$wpdb;
        \$count = \$wpdb->get_var(\"SELECT COUNT(DISTINCT user_id) FROM {\$wpdb->usermeta} WHERE meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%'\");
        return (int) \$count;
    }

    /**
     * Get count of users pending history migration.
     */
    public static function get_pending_history_count()
    {
        return count(get_users(array(
            'meta_key' => '_lms_history_migrated',
            'meta_compare' => 'NOT EXISTS',
            'fields' => 'ID'
        )));
    }

    /**
     * Get count of courses pending migration.", $content);

file_put_contents($file, $content);
