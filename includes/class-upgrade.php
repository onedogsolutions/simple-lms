<?php
/**
 * Schema versioning and incremental upgrade runner for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Upgrade
 *
 * Compares the stored DB version against SLMS_DB_VERSION and runs any pending
 * incremental schema steps. Each step is idempotent (dbDelta or a guarded ALTER)
 * so fresh installs and in-place updates converge on the same schema without
 * requiring a plugin reactivation.
 */
class Upgrade
{
    /**
     * Option name storing the installed DB schema version.
     *
     * @var string
     */
    const OPTION = 'slms_db_version';

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'run'));
    }

    /**
     * Ordered map of schema version => migration callback.
     *
     * Each callback must be idempotent. To add a new schema change, append a
     * step with the next integer key and bump SLMS_DB_VERSION to match.
     *
     * @return array<int, callable>
     */
    private static function steps()
    {
        return array(
            1 => array(__CLASS__, 'step_1_create_tables'),
            2 => array(__CLASS__, 'step_2_create_analytics_table'),
            3 => array(__CLASS__, 'step_3_certificate_uuid'),
            4 => array(__CLASS__, 'step_4_create_progress_table'),
        );
    }

    /**
     * Run any pending upgrade steps.
     *
     * Safe to call directly (e.g. on activation) or via the admin_init hook.
     *
     * @return void
     */
    public static function run()
    {
        $current = (int) get_option(self::OPTION, 0);
        $target  = defined('SLMS_DB_VERSION') ? (int) SLMS_DB_VERSION : 0;

        if ($current >= $target) {
            return;
        }

        foreach (self::steps() as $version => $callback) {
            if ($current < $version) {
                call_user_func($callback);
                $current = $version;
                update_option(self::OPTION, $current);
            }
        }
    }

    /**
     * Step 1: Ensure all custom tables exist at the current schema.
     *
     * create_table() uses dbDelta, so this both creates missing tables on fresh
     * installs and reconciles column changes on updates.
     *
     * @return void
     */
    public static function step_1_create_tables()
    {
        Relationships::create_table();
        CourseHistory::create_table();
    }

    /**
     * Step 2: Create the analytics daily-rollup table (Stage 3 reporting).
     *
     * @return void
     */
    public static function step_2_create_analytics_table()
    {
        Analytics::create_table();
    }

    /**
     * Step 3: Add the native-certificate cert_uuid column to slms_course_history
     * and backfill UUIDs for every existing row (so legacy certificates are
     * verifiable by URL too).
     *
     * @return void
     */
    public static function step_3_certificate_uuid()
    {
        CourseHistory::add_cert_uuid_column();
    }

    /**
     * Step 4: Create the lesson progress table via dbDelta.
     *
     * @return void
     */
    public static function step_4_create_progress_table()
    {
        Progress::create_table();
    }
}
