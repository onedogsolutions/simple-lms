<?php
/**
 * Public certificate routes: download + verification.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS\Certificates;

use SimpleLMS\CourseHistory;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Routes
 *
 * Registers pretty rewrite rules:
 *   /certificate/{uuid}/download   → streams the PDF (owner or edit_users)
 *   /certificate/verify/{uuid}     → public verification page (no login)
 */
class Routes
{
    const REWRITE_VERSION = '1';

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'maybe_handle'));
        add_action('admin_init', array(__CLASS__, 'maybe_flush'));
    }

    /**
     * Register the rewrite rules.
     *
     * @return void
     */
    public static function add_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^certificate/verify/([^/]+)/?$',
            'index.php?slms_cert_action=verify&slms_cert_uuid=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^certificate/([^/]+)/download/?$',
            'index.php?slms_cert_action=download&slms_cert_uuid=$matches[1]',
            'top'
        );
    }

    /**
     * Whitelist our query vars.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public static function add_query_vars($vars): array
    {
        $vars[] = 'slms_cert_action';
        $vars[] = 'slms_cert_uuid';
        return $vars;
    }

    /**
     * Flush rewrite rules once after the rule set changes.
     *
     * @return void
     */
    public static function maybe_flush(): void
    {
        if (get_option('slms_cert_rewrite_version') !== self::REWRITE_VERSION) {
            self::add_rewrite_rules();
            flush_rewrite_rules(false);
            update_option('slms_cert_rewrite_version', self::REWRITE_VERSION);
        }
    }

    /**
     * Dispatch a certificate request if one of our query vars is present.
     *
     * @return void
     */
    public static function maybe_handle(): void
    {
        $action = get_query_var('slms_cert_action');
        if (!$action) {
            return;
        }

        $uuid = self::sanitise_uuid(get_query_var('slms_cert_uuid'));

        if ('download' === $action) {
            self::handle_download($uuid);
        } elseif ('verify' === $action) {
            self::handle_verify($uuid);
        }
    }

    /**
     * @param string $raw Raw UUID from the URL.
     * @return string
     */
    private static function sanitise_uuid($raw): string
    {
        $raw = is_string($raw) ? strtolower(trim($raw)) : '';
        return preg_match('/^[0-9a-f\-]{6,64}$/', $raw) ? $raw : '';
    }

    /**
     * Stream a certificate PDF. Permission: owner of the row OR edit_users.
     *
     * @param string $uuid Certificate UUID.
     * @return void
     */
    private static function handle_download(string $uuid): void
    {
        $row = $uuid ? CourseHistory::get_by_uuid($uuid) : null;

        if (!$row) {
            self::not_found(__('Certificate not found.', 'simple-lms-bridge'));
        }

        $owner   = (int) $row->user_id === get_current_user_id() && get_current_user_id() > 0;
        $manager = current_user_can('edit_users');

        if (!$owner && !$manager) {
            status_header(403);
            wp_die(
                esc_html__('You do not have permission to download this certificate.', 'simple-lms-bridge'),
                esc_html__('Forbidden', 'simple-lms-bridge'),
                array('response' => 403)
            );
        }

        // Native path first — regenerate on demand if the cache was evicted.
        if (!Issuer::pdf_exists($uuid) && !empty($row->cert_uuid)) {
            $course_id = self::resolve_course_id((string) $row->course_name);
            if ($course_id) {
                Issuer::render_and_cache($uuid, (int) $row->user_id, $course_id, (string) $row->completed_date);
            }
        }

        if (Issuer::pdf_exists($uuid)) {
            self::stream_pdf(Issuer::pdf_path($uuid), $uuid);
        }

        // Legacy fallback: hand off to the GravityPDF resolver if this is a
        // migrated row that never had a native PDF.
        if (!empty($row->gf_entry_id) && class_exists('\\SimpleLMS\\REST')) {
            $legacy = \SimpleLMS\REST::resolve_legacy_pdf_url(
                (int) $row->gf_entry_id,
                (int) $row->form_id,
                (string) $row->course_name,
                (int) $row->user_id
            );
            if ($legacy) {
                wp_safe_redirect($legacy);
                exit;
            }
        }

        self::not_found(__('This certificate is not available for download.', 'simple-lms-bridge'));
    }

    /**
     * Stream a PDF file to the browser and exit.
     *
     * @param string $path Absolute file path.
     * @param string $uuid Certificate UUID (for the download filename).
     * @return void
     */
    private static function stream_pdf(string $path, string $uuid): void
    {
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="certificate-' . sanitize_file_name($uuid) . '.pdf"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /**
     * Render the public verification page and exit.
     *
     * @param string $uuid Certificate UUID.
     * @return void
     */
    private static function handle_verify(string $uuid): void
    {
        $row = $uuid ? CourseHistory::get_by_uuid($uuid) : null;

        status_header($row ? 200 : 404);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');

        if (!$row) {
            echo self::verify_html(false, array()); // phpcs:ignore WordPress.Security.EscapeOutput
            exit;
        }

        $user = get_userdata((int) $row->user_id);
        $name = '';
        if ($user) {
            $name = trim($user->first_name . ' ' . $user->last_name);
            if ('' === $name) {
                $name = $user->display_name;
            }
        }

        $valid = Issuer::pdf_exists($uuid) || !empty($row->gf_entry_id) || !empty($row->cert_uuid);

        echo self::verify_html($valid, array( // phpcs:ignore WordPress.Security.EscapeOutput
            'name'       => $name,
            'course'     => self::display_course((string) $row->course_name),
            'date'       => $row->completed_date ? date_i18n((string) (get_option('date_format') ?: 'F j, Y'), (int) strtotime((string) $row->completed_date)) : '',
            'uuid'       => (string) ($row->cert_uuid ?: $uuid),
        ));
        exit;
    }

    /**
     * Build a self-contained verification HTML page.
     *
     * @param bool  $valid Whether the certificate is valid.
     * @param array $data  Display data.
     * @return string
     */
    private static function verify_html(bool $valid, array $data): string
    {
        $site  = get_bloginfo('name');
        $title = esc_html__('Certificate Verification', 'simple-lms-bridge');

        if (!$valid) {
            $body = '<div class="slms-v-card slms-v-invalid">'
                . '<div class="slms-v-badge">✕</div>'
                . '<h1>' . esc_html__('Certificate Not Found', 'simple-lms-bridge') . '</h1>'
                . '<p>' . esc_html__('We could not verify a certificate with this identifier.', 'simple-lms-bridge') . '</p>'
                . '</div>';
        } else {
            $rows = '';
            $fields = array(
                __('Student', 'simple-lms-bridge')          => $data['name'] ?? '',
                __('Course', 'simple-lms-bridge')           => $data['course'] ?? '',
                __('Completion Date', 'simple-lms-bridge')  => $data['date'] ?? '',
                __('Certificate ID', 'simple-lms-bridge')   => $data['uuid'] ?? '',
            );
            foreach ($fields as $label => $value) {
                if ('' === (string) $value) {
                    continue;
                }
                $rows .= '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
            }
            $body = '<div class="slms-v-card slms-v-valid">'
                . '<div class="slms-v-badge">✓</div>'
                . '<h1>' . esc_html__('Valid Certificate', 'simple-lms-bridge') . '</h1>'
                . '<p>' . esc_html__('This is an authentic certificate of completion.', 'simple-lms-bridge') . '</p>'
                . '<table class="slms-v-table">' . $rows . '</table>'
                . '</div>';
        }

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<meta name="robots" content="noindex" />'
            . '<title>' . $title . ' — ' . esc_html($site) . '</title>'
            . '<style>'
            . 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;'
            . 'background:#f3f4f6;color:#111827;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px;}'
            . '.slms-v-card{background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);max-width:520px;width:100%;padding:40px;text-align:center;}'
            . '.slms-v-badge{width:64px;height:64px;line-height:64px;border-radius:50%;font-size:32px;color:#fff;margin:0 auto 16px;}'
            . '.slms-v-valid .slms-v-badge{background:#16a34a;}'
            . '.slms-v-invalid .slms-v-badge{background:#dc2626;}'
            . '.slms-v-card h1{font-size:22px;margin:0 0 8px;}'
            . '.slms-v-card p{color:#6b7280;margin:0 0 20px;}'
            . '.slms-v-table{width:100%;border-collapse:collapse;text-align:left;margin-top:8px;}'
            . '.slms-v-table th{color:#6b7280;font-weight:600;padding:10px 12px;width:40%;border-top:1px solid #f0f0f0;font-size:14px;}'
            . '.slms-v-table td{padding:10px 12px;border-top:1px solid #f0f0f0;font-size:14px;word-break:break-all;}'
            . '</style></head><body>' . $body . '</body></html>';
    }

    /**
     * Human-readable course display for the verify page.
     *
     * @param string $course_name Raw stored course name (URL or title).
     * @return string
     */
    private static function display_course(string $course_name): string
    {
        $course_id = self::resolve_course_id($course_name);
        if ($course_id) {
            return get_the_title($course_id);
        }
        return $course_name;
    }

    /**
     * Best-effort resolution of a course post ID from a stored course_name.
     *
     * @param string $course_name URL or plain title.
     * @return int Course ID or 0.
     */
    public static function resolve_course_id(string $course_name): int
    {
        if ('' === $course_name) {
            return 0;
        }

        if (filter_var($course_name, FILTER_VALIDATE_URL)) {
            $post_id = url_to_postid($course_name);
            if ($post_id && 'slms_course' === get_post_type($post_id)) {
                return (int) $post_id;
            }
        }

        $matched = get_posts(array(
            'post_type'      => 'slms_course',
            'title'          => $course_name,
            'posts_per_page' => 1,
            'post_status'    => array('publish', 'private'),
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ));

        return !empty($matched) ? (int) $matched[0] : 0;
    }

    /**
     * Emit a 404 page and exit.
     *
     * @param string $message Message to display.
     * @return never
     */
    private static function not_found(string $message)
    {
        status_header(404);
        wp_die(
            esc_html($message),
            esc_html__('Certificate', 'simple-lms-bridge'),
            array('response' => 404)
        );
    }
}
