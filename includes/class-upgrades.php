<?php
/**
 * Schema upgrade runner for SimpleLMS.
 *
 * Provides a single, versioned authority for database schema changes so that
 * deploys made via git (rather than plugin (de)activation) still pick up new
 * tables and columns. Each numbered step runs exactly once and the highest
 * applied version is stored in the `slms_db_version` option.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Upgrades
 *
 * Runs pending schema migrations on load and on activation.
 */
class Upgrades
{

    /**
     * Option key that stores the last-applied schema version.
     *
     * @var string
     */
    const OPTION = 'slms_db_version';

    /**
     * Target schema version. Bump this when adding a new step below.
     *
     * @var int
     */
    const TARGET = 2;

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        // Run pending upgrades on admin load. Cheap no-op once versions match.
        add_action('admin_init', array(__CLASS__, 'maybe_upgrade'));
    }

    /**
     * Run any pending upgrade steps if the stored version is behind TARGET.
     *
     * @return void
     */
    public static function maybe_upgrade()
    {
        $current = (int) get_option(self::OPTION, 0);

        if ($current >= self::TARGET) {
            return;
        }

        self::run($current);
    }

    /**
     * Force all upgrade steps to run (used on plugin activation).
     *
     * @return void
     */
    public static function run_all()
    {
        self::run((int) get_option(self::OPTION, 0));
    }

    /**
     * Execute every step greater than the given version, in order.
     *
     * @param int $from The version already applied.
     * @return void
     */
    private static function run($from)
    {
        // Map of version => callable. Steps must be idempotent.
        $steps = array(
            1 => array(__CLASS__, 'step_1_base_tables'),
            2 => array(__CLASS__, 'step_2_lesson_progress_table'),
        );

        foreach ($steps as $version => $callback) {
            if ($version <= $from) {
                continue;
            }

            call_user_func($callback);
            update_option(self::OPTION, $version, false);
        }
    }

    /**
     * Step 1: Ensure the base relationship / history tables exist.
     *
     * These were previously created only on activation; running them through
     * the upgrade runner guarantees they exist after a git deploy too.
     *
     * @return void
     */
    public static function step_1_base_tables()
    {
        Relationships::create_table();
        CourseHistory::create_table();
    }

    /**
     * Step 2: Create the queryable lesson-progress table.
     *
     * @return void
     */
    public static function step_2_lesson_progress_table()
    {
        Progress::create_table();
    }
}
