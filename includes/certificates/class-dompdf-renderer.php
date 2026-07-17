<?php
/**
 * dompdf-backed certificate renderer.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS\Certificates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DompdfRenderer
 *
 * Renders certificate HTML to PDF using the dompdf library bundled under the
 * plugin's own vendor/ directory. The Composer autoloader is loaded lazily and
 * guarded so we never redeclare a dompdf that another plugin already loaded.
 *
 * Note on isolation: dompdf lives in the `Dompdf\` namespace. For hardened
 * multi-plugin coexistence the vendor tree can be prefixed with php-scoper at
 * build time (Dompdf\ -> SimpleLMS\Vendor\Dompdf\); the interface indirection
 * here means only this class would change.
 */
class DompdfRenderer implements Renderer
{
    /**
     * Whether the bundled autoloader has been required this request.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Load the bundled Composer autoloader once, if dompdf isn't already present.
     *
     * @return void
     */
    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        if (class_exists('\\Dompdf\\Dompdf')) {
            return; // Already provided by another plugin / a prefixed build.
        }

        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_readable($autoload)) {
            require_once $autoload;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function is_available(): bool
    {
        self::boot();
        return class_exists('\\Dompdf\\Dompdf');
    }

    /**
     * {@inheritDoc}
     */
    public function render(string $html, array $options = array()): string
    {
        if (!$this->is_available()) {
            throw new \RuntimeException('dompdf is not available.');
        }

        $orientation = (isset($options['orientation']) && 'portrait' === $options['orientation'])
            ? 'portrait'
            : 'landscape';
        $paper = isset($options['paper']) ? (string) $options['paper'] : 'letter';

        $dompdf_options = new \Dompdf\Options();
        $dompdf_options->set('isRemoteEnabled', true); // Allow the background image URL.
        $dompdf_options->set('isHtml5ParserEnabled', true);
        $dompdf_options->set('defaultFont', 'DejaVu Sans');
        // Cache generated font metrics inside uploads, not the read-only plugin dir.
        $tmp = get_temp_dir();
        if ($tmp && is_writable($tmp)) {
            $dompdf_options->set('tempDir', $tmp);
            $dompdf_options->set('fontCache', $tmp);
        }

        $dompdf = new \Dompdf\Dompdf($dompdf_options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        $output = $dompdf->output();
        if (!is_string($output) || '' === $output) {
            throw new \RuntimeException('dompdf produced empty output.');
        }

        return $output;
    }
}
