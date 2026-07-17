<?php
/**
 * Per-course certificate template model + HTML builder.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS\Certificates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Template
 *
 * Reads/normalises the `_lms_cert_template` course meta and turns a template
 * plus a rendering context into a self-contained HTML document. The same CSS
 * model is mirrored by the CourseEditor live preview so what admins see is what
 * dompdf renders.
 */
class Template {

	const META_KEY = '_lms_cert_template';

	/**
	 * Placeholder tokens supported by the template.
	 *
	 * @var string[]
	 */
	const PLACEHOLDERS = array(
		'student_name',
		'course_title',
		'completed_date',
		'license_number',
		'cert_uuid',
	);

	/**
	 * Default template used when a course has none configured.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'background_id' => 0,
			'preset'        => 'classic',
			'orientation'   => 'landscape',
			'placeholders'  => array(
				'student_name'   => array(
					'x'      => 50,
					'y'      => 42,
					'size'   => 44,
					'color'  => '#1a1a1a',
					'align'  => 'center',
					'weight' => 'bold',
				),
				'course_title'   => array(
					'x'      => 50,
					'y'      => 58,
					'size'   => 26,
					'color'  => '#333333',
					'align'  => 'center',
					'weight' => 'normal',
				),
				'completed_date' => array(
					'x'      => 50,
					'y'      => 70,
					'size'   => 16,
					'color'  => '#555555',
					'align'  => 'center',
					'weight' => 'normal',
				),
				'license_number' => array(
					'x'      => 12,
					'y'      => 88,
					'size'   => 12,
					'color'  => '#555555',
					'align'  => 'left',
					'weight' => 'normal',
				),
				'cert_uuid'      => array(
					'x'      => 88,
					'y'      => 88,
					'size'   => 10,
					'color'  => '#888888',
					'align'  => 'right',
					'weight' => 'normal',
				),
			),
		);
	}

	/**
	 * REST schema for the object meta so CourseEditor can read/write it.
	 *
	 * @return array
	 */
	public static function rest_schema(): array {
		$placeholder_schema = array(
			'type'       => 'object',
			'properties' => array(
				'x'      => array( 'type' => 'number' ),
				'y'      => array( 'type' => 'number' ),
				'size'   => array( 'type' => 'number' ),
				'color'  => array( 'type' => 'string' ),
				'align'  => array( 'type' => 'string' ),
				'weight' => array( 'type' => 'string' ),
			),
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'background_id' => array( 'type' => 'integer' ),
				'preset'        => array( 'type' => 'string' ),
				'orientation'   => array( 'type' => 'string' ),
				'placeholders'  => array(
					'type'       => 'object',
					'properties' => array(
						'student_name'   => $placeholder_schema,
						'course_title'   => $placeholder_schema,
						'completed_date' => $placeholder_schema,
						'license_number' => $placeholder_schema,
						'cert_uuid'      => $placeholder_schema,
					),
				),
			),
		);
	}

	/**
	 * Load and normalise a course's template, filling any gaps with defaults.
	 *
	 * @param int $course_id Course post ID.
	 * @return array
	 */
	public static function for_course( int $course_id ): array {
		$stored = get_post_meta( $course_id, self::META_KEY, true );
		return self::normalise( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Merge a (possibly partial) template with the defaults.
	 *
	 * @param array $tpl Partial template.
	 * @return array
	 */
	public static function normalise( array $tpl ): array {
		$defaults = self::defaults();

		$out = array(
			'background_id' => isset( $tpl['background_id'] ) ? absint( $tpl['background_id'] ) : $defaults['background_id'],
			'preset'        => self::sanitise_preset( $tpl['preset'] ?? $defaults['preset'] ),
			'orientation'   => ( isset( $tpl['orientation'] ) && 'portrait' === $tpl['orientation'] ) ? 'portrait' : 'landscape',
			'placeholders'  => array(),
		);

		foreach ( self::PLACEHOLDERS as $key ) {
			$p                           = isset( $tpl['placeholders'][ $key ] ) && is_array( $tpl['placeholders'][ $key ] )
				? $tpl['placeholders'][ $key ]
				: array();
			$d                           = $defaults['placeholders'][ $key ];
			$out['placeholders'][ $key ] = array(
				'x'      => isset( $p['x'] ) ? max( 0, min( 100, (float) $p['x'] ) ) : $d['x'],
				'y'      => isset( $p['y'] ) ? max( 0, min( 100, (float) $p['y'] ) ) : $d['y'],
				'size'   => isset( $p['size'] ) ? max( 6, min( 120, (int) $p['size'] ) ) : $d['size'],
				'color'  => self::sanitise_color( $p['color'] ?? $d['color'] ),
				'align'  => in_array( ( $p['align'] ?? '' ), array( 'left', 'center', 'right' ), true ) ? $p['align'] : $d['align'],
				'weight' => ( 'bold' === ( $p['weight'] ?? '' ) ) ? 'bold' : 'normal',
			);
		}

		return $out;
	}

	/**
	 * @param mixed $preset Raw preset (untrusted meta value).
	 * @return string
	 */
	private static function sanitise_preset( $preset ): string {
		$preset = is_string( $preset ) ? $preset : '';
		return in_array( $preset, array( 'classic', 'modern', 'minimal' ), true ) ? $preset : 'classic';
	}

	/**
	 * @param mixed $color Raw hex color (untrusted meta value).
	 * @return string
	 */
	private static function sanitise_color( $color ): string {
		$color = is_string( $color ) ? trim( $color ) : '';
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : '#1a1a1a';
	}

	/**
	 * Base styling for each preset (page background + typography + frame).
	 *
	 * @param string $preset Preset key.
	 * @return array{bg:string,font:string,frame:string}
	 */
	public static function preset_style( string $preset ): array {
		switch ( $preset ) {
			case 'modern':
				return array(
					'bg'    => '#ffffff',
					'font'  => 'DejaVu Sans, sans-serif',
					'frame' => '10px solid #1e40af',
				);
			case 'minimal':
				return array(
					'bg'    => '#ffffff',
					'font'  => 'DejaVu Sans, sans-serif',
					'frame' => '1px solid #cccccc',
				);
			case 'classic':
			default:
				return array(
					'bg'    => '#faf8f0',
					'font'  => 'DejaVu Serif, Georgia, serif',
					'frame' => '6px double #b8860b',
				);
		}
	}

	/**
	 * Build the full HTML document for a certificate.
	 *
	 * @param array $tpl     Normalised template.
	 * @param array $context {
	 *   @type string $student_name
	 *   @type string $course_title
	 *   @type string $completed_date
	 *   @type string $license_number
	 *   @type string $cert_uuid
	 *   @type string $verify_url
	 *   @type string $qr_data_uri  Optional data: URI for the QR image.
	 * }
	 * @return string
	 */
	public static function build_html( array $tpl, array $context ): string {
		$tpl    = self::normalise( $tpl );
		$preset = self::preset_style( $tpl['preset'] );
		$bg_url = $tpl['background_id'] ? wp_get_attachment_image_url( $tpl['background_id'], 'full' ) : '';

		$values = array(
			'student_name'   => $context['student_name'] ?? '',
			'course_title'   => $context['course_title'] ?? '',
			'completed_date' => $context['completed_date'] ?? '',
			'license_number' => ! empty( $context['license_number'] )
				? sprintf( /* translators: %s: license number */ __( 'License #%s', 'simple-lms-bridge' ), $context['license_number'] )
				: '',
			'cert_uuid'      => ! empty( $context['cert_uuid'] )
				? sprintf( /* translators: %s: certificate id */ __( 'Certificate ID: %s', 'simple-lms-bridge' ), $context['cert_uuid'] )
				: '',
		);

		$background_css = $bg_url
			? 'background-image:url(\'' . esc_url( $bg_url ) . '\');background-size:cover;background-repeat:no-repeat;background-position:center;'
			: 'background:' . esc_attr( $preset['bg'] ) . ';';

		// Absolutely-positioned placeholder lines.
		$blocks = '';
		foreach ( self::PLACEHOLDERS as $key ) {
			if ( '' === $values[ $key ] ) {
				continue;
			}
			$blocks .= '<div style="' . esc_attr( self::placeholder_css( $tpl['placeholders'][ $key ] ) ) . '">'
				. esc_html( $values[ $key ] )
				. '</div>';
		}

		// QR / verification block (always present when a verify URL exists).
		$qr_block = '';
		if ( ! empty( $context['verify_url'] ) ) {
			$qr_img   = ! empty( $context['qr_data_uri'] )
				? '<img src="' . esc_attr( $context['qr_data_uri'] ) . '" style="width:90px;height:90px;display:block;margin:0 auto 4px;" alt="QR" />'
				: '';
			$qr_block = '<div style="position:absolute;right:4%;bottom:3%;text-align:center;font-family:' . esc_attr( $preset['font'] ) . ';font-size:8px;color:#666;">'
				. $qr_img
				. '<span>' . esc_html__( 'Verify at', 'simple-lms-bridge' ) . '<br />' . esc_html( $context['verify_url'] ) . '</span>'
				. '</div>';
		}

		$page_font = esc_attr( $preset['font'] );
		$frame     = esc_attr( $preset['frame'] );

		return '<!DOCTYPE html><html><head><meta charset="utf-8" />'
			. '<style>'
			. '@page{margin:0;}'
			. 'html,body{margin:0;padding:0;width:100%;height:100%;}'
			. '.slms-cert{position:relative;width:100%;height:100%;box-sizing:border-box;'
			. $background_css
			. 'font-family:' . $page_font . ';}'
			. '.slms-cert-frame{position:absolute;top:3%;left:2%;right:2%;bottom:3%;border:' . $frame . ';pointer-events:none;}'
			. '.slms-cert div{line-height:1.2;}'
			. '</style></head><body>'
			. '<div class="slms-cert">'
			. '<div class="slms-cert-frame"></div>'
			. $blocks
			. $qr_block
			. '</div>'
			. '</body></html>';
	}

	/**
	 * Convert a placeholder config into an inline CSS string.
	 *
	 * Anchoring model (mirrored in the React preview):
	 *   - center: full-width line, text-align:center (x ignored)
	 *   - left:   left:x%
	 *   - right:  right:(100-x)%
	 *
	 * @param array $p Placeholder config (normalised).
	 * @return string
	 */
	public static function placeholder_css( array $p ): string {
		$css = 'position:absolute;'
			. 'top:' . (float) $p['y'] . '%;'
			. 'font-size:' . (int) $p['size'] . 'px;'
			. 'color:' . $p['color'] . ';'
			. 'font-weight:' . $p['weight'] . ';'
			. 'text-align:' . $p['align'] . ';';

		if ( 'center' === $p['align'] ) {
			$css .= 'left:0;width:100%;';
		} elseif ( 'right' === $p['align'] ) {
			$css .= 'right:' . ( 100 - (float) $p['x'] ) . '%;';
		} else {
			$css .= 'left:' . (float) $p['x'] . '%;';
		}

		return $css;
	}
}
