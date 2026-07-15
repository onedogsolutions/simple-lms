<?php
/**
 * Certificate handling for SimpleLMS.
 *
 * Removes course access when a certificate form is submitted.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Certificates
 *
 * Hooks into Gravity Forms to handle certificate generation.
 */
class Certificates
{

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        // Hook into Gravity Forms after submission.
        add_action('gform_after_submission', array(__CLASS__, 'handle_certificate_submission'), 10, 2);
    }

    /**
     * Handle certificate form submission.
     *
     * @param array $entry The entry object.
     * @param array $form  The form object.
     * @return void
     */
    public static function handle_certificate_submission($entry, $form)
    {
        $user_id = isset($entry['created_by']) ? (int)$entry['created_by'] : get_current_user_id();

        if (!$user_id) {
            return;
        }

        $form_id = (int)$form['id'];

        // Find courses that use this form for certificates.
        $query = new \WP_Query(array(
            'post_type' => 'slms_course',
            'posts_per_page' => -1,
            'meta_query' => array(
                    array(
                    'key' => '_lms_certificate_form',
                    'value' => $form_id,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ));

        if (!$query->have_posts()) {
            return;
        }

        foreach ($query->posts as $course_id) {
            self::remove_course_access($user_id, $course_id);
        }

        wp_reset_postdata();
    }

    /**
     * Remove a user's access to a course and clear progress.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return void
     */
    private static function remove_course_access($user_id, $course_id)
    {
        // Use the PMPro class helpers if available since they already handle this logic cleanly.
        if (class_exists(__NAMESPACE__ . '\PMPro')) {
            PMPro::de_enroll_user($user_id, $course_id);
        }

        if (function_exists('\pmpro_changeMembershipLevel')) {
            \pmpro_changeMembershipLevel(0, $user_id);
        }
        else {
            // Fallback if PMPro class is missing (should not happen in this plugin).
            $progress = get_user_meta($user_id, '_lms_progress', true);
            if (is_array($progress) && isset($progress[$course_id])) {
                unset($progress[$course_id]);
                update_user_meta($user_id, '_lms_progress', $progress);
            }

            $enrolled = get_user_meta($user_id, '_lms_enrolled_at', true);
            if (is_array($enrolled) && isset($enrolled[$course_id])) {
                unset($enrolled[$course_id]);
                update_user_meta($user_id, '_lms_enrolled_at', $enrolled);
            }
        }


        // Trigger action for others to hook into.
        do_action('slms_certificate_generated', $user_id, $course_id);
    }

    /**
     * Check if a course is completed and handle certificate automation.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course post ID.
     * @return void
     */
    public static function check_course_completion($user_id, $course_id)
    {
        $lesson_ids = get_post_meta($course_id, '_simple_lms_order', true);
        if (!is_array($lesson_ids) || empty($lesson_ids)) {
            return;
        }

        $progress = get_user_meta($user_id, '_lms_progress', true);
        $course_progress = isset($progress[$course_id]) ? $progress[$course_id] : array();

        $all_done = true;
        foreach ($lesson_ids as $lesson_id) {
            if (!isset($course_progress[$lesson_id])) {
                $all_done = false;
                break;
            }
        }

        if ($all_done) {
            // Check if we've already handled completion for this course.
            $completion_recorded = get_user_meta($user_id, '_lms_completed_at', true);
            if (!is_array($completion_recorded)) {
                $completion_recorded = array();
            }

            if (!isset($completion_recorded[$course_id])) {
                $completion_recorded[$course_id] = time();
                update_user_meta($user_id, '_lms_completed_at', $completion_recorded);

                do_action('slms_course_completed', $user_id, $course_id);

                // Automate certificate generation.
                $form_id = (int)get_post_meta($course_id, '_lms_certificate_form', true);

                $linked_entry_id = null;

                if ($form_id > 0 && class_exists('GFAPI')) {
                    // Check if an entry already exists for this user and form to avoid duplicates.
                    $search_criteria = array(
                        'status'        => 'active',
                        'field_filters' => array(
                            array('key' => 'created_by', 'value' => $user_id),
                        ),
                    );
                    $entries = \GFAPI::get_entries($form_id, $search_criteria);

                    if (empty($entries)) {
                        // Populate field 6 (State) and field 18 (Course URL) so GravityPDF
                        // conditional logic can match the correct PDF template immediately.
                        $result = \GFAPI::add_entry(array(
                            'form_id'    => $form_id,
                            'created_by' => $user_id,
                            'status'     => 'active',
                            '6'          => (string) get_user_meta($user_id, 'billing_state', true),
                            '18'         => (string) get_permalink($course_id),
                        ));
                        if (!is_wp_error($result)) {
                            $linked_entry_id = (int) $result;
                        }
                    } else {
                        $linked_entry_id = (int) $entries[0]['id'];
                    }
                }

                if (class_exists(__NAMESPACE__ . '\CourseHistory')) {
                    $course_title = get_the_title($course_id);
                    CourseHistory::insert(
                        $user_id,
                        $course_title,
                        current_time('mysql'),
                        $linked_entry_id,
                        $form_id > 0 ? $form_id : null
                    );
                }

                // Revoke access automatically upon completion (if configured or standard behavior).
                self::remove_course_access($user_id, $course_id);
            }
        }
    }

    /**
     * Resolve a GravityPDF download URL for a completion/compliance history row.
     *
     * Two-stage:
     *  1. GPDFAPI::get_entry_pdfs() evaluates conditional logic against the GF
     *     entry's actual field values. If it comes back empty, the entry is
     *     likely missing field 6 (State) and/or field 18 (Course URL) — common
     *     for entries migrated from the legacy system — so those fields are
     *     backfilled from billing_state user meta and $raw_course via
     *     GFAPI::update_entry_field(), then Stage 1 is retried.
     *  2. If Stage 1 still comes back empty, manually evaluate each PDF
     *     template's conditionalLogic using the same field 6 / field 18 values
     *     (stripos + sanitize_title slug comparison, with a last-path-segment
     *     fallback). This stage exists only because migrated GF entries lacked
     *     fields 6/18; once the migrator backfills those fields for all
     *     historical entries, Stage 2 can be removed. Do not remove it until
     *     that backfill has run.
     *
     * @param int    $gf_entry_id GF entry ID stored in the history row.
     * @param int    $form_id     GF form ID stored in the history row.
     * @param string $raw_course  Best-known course URL or title (raw stored
     *                            value, or a resolved permalink if the caller
     *                            has one) — used both for the field 18 backfill
     *                            and for the Stage 2 slug match.
     * @param int    $user_id     Student user ID (for billing_state lookup).
     * @return string Download URL or empty string.
     */
    public static function pdf_url(int $gf_entry_id, int $form_id, string $raw_course, int $user_id): string
    {
        if (!$gf_entry_id || !$form_id || !class_exists('GPDFAPI')) {
            return '';
        }

        $student_state = (string) get_user_meta($user_id, 'billing_state', true);

        // ── Stage 1: get_entry_pdfs() ────────────────────────────────────────
        $entry_pdfs = \GPDFAPI::get_entry_pdfs($gf_entry_id);

        // When Stage 1 returns empty the GF entry is likely missing field 6
        // (State) and/or field 18 (Course URL). Backfill those fields so
        // GravityPDF's server-side conditional check passes, then retry.
        if (!is_wp_error($entry_pdfs) && empty($entry_pdfs) && class_exists('GFAPI')) {
            $entry = \GFAPI::get_entry($gf_entry_id);
            if (!is_wp_error($entry)) {
                $backfilled = false;
                if ($student_state) {
                    \GFAPI::update_entry_field($gf_entry_id, 6, $student_state);
                    $backfilled = true;
                }
                if ($raw_course && empty($entry['18'])) {
                    \GFAPI::update_entry_field($gf_entry_id, 18, $raw_course);
                    $backfilled = true;
                }
                if ($backfilled) {
                    $entry_pdfs = \GPDFAPI::get_entry_pdfs($gf_entry_id);
                }
            }
        }

        if (!is_wp_error($entry_pdfs) && !empty($entry_pdfs)) {
            $hash_id = array_key_first($entry_pdfs);
            return home_url('/pdf/' . $hash_id . '/' . $gf_entry_id . '/download/');
        }

        // ── Stage 2: Manual conditional logic evaluation ─────────────────────
        $all_pdfs = \GPDFAPI::get_form_pdfs($form_id);
        if (is_wp_error($all_pdfs) || empty($all_pdfs)) {
            return '';
        }

        foreach ($all_pdfs as $id => $pdf_config) {
            if (empty($pdf_config['active'])) {
                continue;
            }

            $logic = !empty($pdf_config['conditionalLogic']) ? $pdf_config['conditionalLogic'] : array();

            if (empty($logic['rules'])) {
                return home_url('/pdf/' . $id . '/' . $gf_entry_id . '/download/');
            }

            $logic_type   = isset($logic['logicType']) ? $logic['logicType'] : 'all';
            $rule_results = array();

            foreach ($logic['rules'] as $rule) {
                $fid = (string)(isset($rule['fieldId']) ? $rule['fieldId'] : '');
                $op  = isset($rule['operator']) ? $rule['operator'] : 'is';
                $val = isset($rule['value']) ? $rule['value'] : '';

                if ('6' === $fid) {
                    $match = ('is' === $op)
                        ? ($student_state === $val)
                        : ($student_state !== $val);
                } elseif ('18' === $fid) {
                    $cond_path  = (string) parse_url($val, PHP_URL_PATH);
                    $cond_parts = array_values(array_filter(explode('/', trim($cond_path, '/'))));
                    $cidx        = array_search('course', $cond_parts, true);
                    // Use segment after "course/" if present; fall back to last segment.
                    $course_slug = ($cidx !== false && isset($cond_parts[$cidx + 1]))
                        ? $cond_parts[$cidx + 1]
                        : (!empty($cond_parts) ? end($cond_parts) : '');

                    if ($course_slug !== '') {
                        // Case-insensitive match against the stored course value.
                        $match = stripos($raw_course, $course_slug) !== false;
                        // Also compare via a title-slug conversion (handles plain-text course_name).
                        if (!$match) {
                            $title_slug = sanitize_title($raw_course);
                            $match = $title_slug !== '' && (
                                stripos($title_slug, $course_slug) !== false ||
                                stripos($course_slug, $title_slug) !== false
                            );
                        }
                    } else {
                        $match = false;
                    }

                    if ('isnot' === $op) {
                        $match = !$match;
                    }
                } else {
                    continue;
                }

                $rule_results[] = $match;
            }

            if (empty($rule_results)) {
                continue;
            }

            $passes = ('any' === $logic_type)
                ? in_array(true, $rule_results, true)
                : !in_array(false, $rule_results, true);

            if ($passes) {
                return home_url('/pdf/' . $id . '/' . $gf_entry_id . '/download/');
            }
        }

        return '';
    }
}