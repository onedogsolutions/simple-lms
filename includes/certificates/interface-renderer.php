<?php
/**
 * Certificate renderer contract.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS\Certificates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Renderer
 *
 * Abstraction over the PDF engine so the certificate pipeline never depends
 * on a concrete library (dompdf today, mPDF/wkhtmltopdf tomorrow). Swap the
 * implementation via the `slms_certificate_renderer` filter.
 */
interface Renderer {

	/**
	 * Render an HTML document to raw PDF bytes.
	 *
	 * @param string $html    Fully-formed HTML document.
	 * @param array  $options Engine options: 'orientation' (portrait|landscape),
	 *                        'paper' (e.g. 'letter', 'a4').
	 * @return string Raw PDF binary.
	 *
	 * @throws \RuntimeException When rendering fails or the engine is unavailable.
	 */
	public function render( string $html, array $options = array() ): string;

	/**
	 * Whether the underlying engine can be used in this environment.
	 *
	 * @return bool
	 */
	public function is_available(): bool;
}
