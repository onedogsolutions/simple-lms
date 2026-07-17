<?php
/**
 * Certificate issuance, caching and URL helpers.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS\Certificates;

use SimpleLMS\CourseHistory;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Issuer
 *
 * Owns the native certificate lifecycle: allocate a UUID, persist the
 * compliance row, render a branded PDF and cache it to a protected uploads
 * directory. Also exposes the canonical download / verify URLs.
 */
class Issuer
{
    const UPLOAD_SUBDIR = 'slms-certs';

    /**
     * Resolve the active renderer (filterable).
     *
     * @return Renderer
     */
    public static function renderer(): Renderer
    {
        /**
         * Filter the certificate renderer implementation.
         *
         * @param Renderer $renderer Default dompdf renderer.
         */
        $renderer = apply_filters('slms_certificate_renderer', new DompdfRenderer());
        return $renderer instanceof Renderer ? $renderer : new DompdfRenderer();
    }

    /**
     * Absolute path to the protected certificate storage directory.
     *
     * Creates it (with the same .htaccess pattern as slms-logs) on first use.
     *
     * @return string
     */
    public static function storage_dir(): string
    {
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR;

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            @file_put_contents($dir . '/.htaccess', 'deny from all');
            @file_put_contents($dir . '/index.php', '<?php // Silence is golden.');
        }

        return $dir;
    }

    /**
     * Absolute filesystem path for a certificate PDF.
     *
     * @param string $uuid Certificate UUID.
     * @return string
     */
    public static function pdf_path(string $uuid): string
    {
        return self::storage_dir() . '/' . sanitize_file_name($uuid) . '.pdf';
    }

    /**
     * Whether a cached native PDF exists for this UUID.
     *
     * @param string $uuid Certificate UUID.
     * @return bool
     */
    public static function pdf_exists(string $uuid): bool
    {
        return '' !== $uuid && is_readable(self::pdf_path($uuid));
    }

    /**
     * Canonical public download URL for a certificate.
     *
     * @param string $uuid Certificate UUID.
     * @return string
     */
    public static function download_url(string $uuid): string
    {
        return home_url('certificate/' . rawurlencode($uuid) . '/download');
    }

    /**
     * Canonical public verification URL for a certificate.
     *
     * @param string $uuid Certificate UUID.
     * @return string
     */
    public static function verify_url(string $uuid): string
    {
        return home_url('certificate/verify/' . rawurlencode($uuid));
    }

    /**
     * Issue a certificate for a completed course.
     *
     * Persists the compliance row first (so the record survives even if PDF
     * rendering fails), then renders and caches the branded PDF.
     *
     * @param int    $user_id        Student user ID.
     * @param int    $course_id      Course post ID.
     * @param string $completed_date MySQL datetime of completion.
     * @param array  $extra_meta     Extra metadata to store on the history row
     *                               (e.g. analytics enrolled_at/days_to_complete).
     * @return array{uuid:string,history_id:int,pdf:bool}
     */
    public static function issue(int $user_id, int $course_id, string $completed_date, array $extra_meta = array()): array
    {
        $uuid  = wp_generate_uuid4();
        $title = get_the_title($course_id);

        $meta = array_merge(array('source' => 'native'), $extra_meta);

        $history_id = (int) CourseHistory::insert(
            $user_id,
            $title,
            $completed_date,
            null,   // no GF entry — native pipeline
            null,   // no GF form
            $meta,
            $uuid
        );

        $pdf_ok = self::render_and_cache($uuid, $user_id, $course_id, $completed_date);

        /**
         * Fires after a native certificate has been issued.
         *
         * @param string $uuid       Certificate UUID.
         * @param int    $user_id    Student user ID.
         * @param int    $course_id  Course post ID.
         * @param bool   $pdf_ok     Whether the PDF cached successfully.
         */
        do_action('slms_certificate_issued', $uuid, $user_id, $course_id, $pdf_ok);

        return array(
            'uuid'       => $uuid,
            'history_id' => $history_id,
            'pdf'        => $pdf_ok,
        );
    }

    /**
     * Render a certificate PDF and write it to the cache directory.
     *
     * @param string $uuid           Certificate UUID.
     * @param int    $user_id        Student user ID.
     * @param int    $course_id      Course post ID.
     * @param string $completed_date MySQL datetime.
     * @return bool True on success.
     */
    public static function render_and_cache(string $uuid, int $user_id, int $course_id, string $completed_date): bool
    {
        try {
            $renderer = self::renderer();
            if (!$renderer->is_available()) {
                return false;
            }

            $tpl     = Template::for_course($course_id);
            $context = self::build_context($uuid, $user_id, $course_id, $completed_date);
            $html    = Template::build_html($tpl, $context);

            $pdf = $renderer->render($html, array(
                'orientation' => $tpl['orientation'],
                'paper'       => 'letter',
            ));

            $bytes = file_put_contents(self::pdf_path($uuid), $pdf, LOCK_EX);
            return false !== $bytes;
        } catch (\Throwable $e) {
            error_log('[SimpleLMS] Certificate render failed for ' . $uuid . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build the rendering context for a certificate.
     *
     * @param string $uuid           Certificate UUID.
     * @param int    $user_id        Student user ID.
     * @param int    $course_id      Course post ID.
     * @param string $completed_date MySQL datetime.
     * @return array
     */
    public static function build_context(string $uuid, int $user_id, int $course_id, string $completed_date): array
    {
        $user = get_userdata($user_id);

        $student_name = '';
        if ($user) {
            $student_name = trim($user->first_name . ' ' . $user->last_name);
            if ('' === $student_name) {
                $student_name = $user->display_name;
            }
        }

        $ts = $completed_date ? strtotime($completed_date) : time();
        $verify_url = self::verify_url($uuid);

        return array(
            'student_name'   => $student_name,
            'course_title'   => $course_id ? get_the_title($course_id) : '',
            'completed_date' => date_i18n(get_option('date_format') ?: 'F j, Y', $ts ?: time()),
            'license_number' => (string) get_user_meta($user_id, 'license_number', true),
            'cert_uuid'      => $uuid,
            'verify_url'     => $verify_url,
            'qr_data_uri'    => self::qr_data_uri($verify_url),
        );
    }

    /**
     * Generate a PNG data: URI QR code for a URL, or '' if unavailable.
     *
     * @param string $url URL to encode.
     * @return string
     */
    public static function qr_data_uri(string $url): string
    {
        if ('' === $url) {
            return '';
        }

        $autoload = SLMS_PLUGIN_DIR . 'vendor/autoload.php';
        if (!class_exists('\\chillerlan\\QRCode\\QRCode') && is_readable($autoload)) {
            require_once $autoload;
        }
        if (!class_exists('\\chillerlan\\QRCode\\QRCode')) {
            return '';
        }

        try {
            $options = new \chillerlan\QRCode\QROptions(array(
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'   => \chillerlan\QRCode\QRCode::ECC_M,
                'scale'      => 5,
                'imageBase64' => true,
            ));
            return (new \chillerlan\QRCode\QRCode($options))->render($url);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
