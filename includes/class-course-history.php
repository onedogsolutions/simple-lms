<?php
/**
 * Compliance handling for SimpleLMS historical records.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CourseHistory
 *
 * Manages the custom table for 9-year compliance retention.
 */
class CourseHistory
{
    /**
     * Table name.
     *
     * @var string
     */
    private static $table_name;

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'slms_course_history';
    }

    /**
     * Create the compliance table using dbDelta.
     *
     * @return void
     */
    public static function create_table()
    {
        global $wpdb;
        self::init();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_name varchar(255) NOT NULL,
            completed_date datetime NOT NULL,
            form_id bigint(20) DEFAULT NULL,
            gf_entry_id bigint(20) DEFAULT NULL,
            cert_data longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY gf_entry_id (gf_entry_id),
            KEY form_id (form_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a record into the course history.
     *
     * @param int    $user_id     User ID.
     * @param string $course_name Course Title.
     * @param string $date        ISO date or Y-m-d H:i:s.
     * @param int    $entry_id    Gravity Forms entry ID if applicable.
     * @param int    $form_id     Gravity Forms form ID if applicable.
     * @param array  $metadata    Any extra metadata.
     * @return int|bool Row ID or false on failure.
     */
    public static function insert($user_id, $course_name, $date, $entry_id = null, $form_id = null, $metadata = array())
    {
        global $wpdb;
        self::init();

        // Avoid duplicate entries for the same user + entry_id if provided
        if ($entry_id) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . self::$table_name . " WHERE user_id = %d AND gf_entry_id = %d",
                $user_id,
                $entry_id
            ));
            if ($exists) {
                return (int)$exists;
            }
        }

        $result = $wpdb->insert(
            self::$table_name,
            array(
            'user_id' => absint($user_id),
            'course_name' => sanitize_text_field($course_name),
            'completed_date' => current_time('mysql', strtotime($date)),
            'form_id' => $form_id ? absint($form_id) : null,
            'gf_entry_id' => $entry_id ? absint($entry_id) : null,
            'cert_data' => !empty($metadata) ? maybe_serialize($metadata) : null,
        ),
            array('%d', '%s', '%s', '%d', '%d', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get history for a user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_for_user( int $user_id ): array
    {
        global $wpdb;
        self::init();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE user_id = %d ORDER BY completed_date DESC",
            $user_id
        ));

        return is_array($results) ? $results : [];
    }

    /**
     * Purge corrupted records from the history table.
     *
     * @return int Number of deleted rows.
     */
    public static function purge_corrupted_records(): int
    {
        global $wpdb;
        self::init();

        return (int) $wpdb->query(
            "DELETE FROM " . self::$table_name . " WHERE form_id IS NULL OR form_id = 0 OR gf_entry_id IS NULL OR gf_entry_id = 0"
        );
    }

    /**
     * Backfill form_id for rows that have gf_entry_id but NULL form_id.
     *
     * @return array { updated: int, skipped: int, failed: int }
     */
    public static function repair_form_ids(): array {
        global $wpdb;
        self::init();

        $rows = $wpdb->get_results(
            "SELECT id, gf_entry_id FROM " . self::$table_name .
            " WHERE gf_entry_id IS NOT NULL AND gf_entry_id > 0 AND (form_id IS NULL OR form_id = 0)"
        );

        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        if ( empty( $rows ) || ! class_exists( 'GFAPI' ) ) {
            return compact( 'updated', 'skipped', 'failed' );
        }

        foreach ( $rows as $row ) {
            $entry = \GFAPI::get_entry( (int) $row->gf_entry_id );

            if ( is_wp_error( $entry ) || empty( $entry['form_id'] ) ) {
                $failed++;
                continue;
            }
            $result = $wpdb->update(
                self::$table_name,
                array( 'form_id' => absint( $entry['form_id'] ) ),
                array( 'id'      => absint( $row->id ) ),
                array( '%d' ),
                array( '%d' )
            );

            if ( false === $result ) {
                $failed++;
            } else {
                $updated++;
            }
        }
        return compact( 'updated', 'skipped', 'failed' );
    }
}