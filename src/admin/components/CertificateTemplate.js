/**
 * CertificateTemplate – per-course native certificate template editor.
 *
 * Renders placeholder/position controls plus a live HTML preview whose CSS
 * anchoring model mirrors Template::placeholder_css() on the PHP side, so the
 * admin preview matches the dompdf output.
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const PLACEHOLDERS = [
	{ key: 'student_name', label: __( 'Student Name', 'simple-lms-bridge' ) },
	{ key: 'course_title', label: __( 'Course Title', 'simple-lms-bridge' ) },
	{ key: 'completed_date', label: __( 'Completion Date', 'simple-lms-bridge' ) },
	{ key: 'license_number', label: __( 'License Number', 'simple-lms-bridge' ) },
	{ key: 'cert_uuid', label: __( 'Certificate ID', 'simple-lms-bridge' ) },
];

const SAMPLES = {
	student_name: 'Jane Q. Student',
	course_title: 'Course Title',
	completed_date: 'July 16, 2026',
	license_number: 'License #12345',
	cert_uuid: 'Certificate ID: 0000-0000',
};

const PRESETS = {
	classic: { bg: '#faf8f0', font: 'Georgia, "Times New Roman", serif', frame: '6px double #b8860b' },
	modern: { bg: '#ffffff', font: 'Helvetica, Arial, sans-serif', frame: '10px solid #1e40af' },
	minimal: { bg: '#ffffff', font: 'Helvetica, Arial, sans-serif', frame: '1px solid #cccccc' },
};

// Full-page pixel dimensions at 96dpi (US Letter).
const PAGE = {
	landscape: { w: 1056, h: 816 },
	portrait: { w: 816, h: 1056 },
};

const PREVIEW_W = 620;

/**
 * Build the inline style for a placeholder line, mirroring the PHP model.
 *
 * @param {Object} p     Placeholder config.
 * @param {number} scale Preview scale factor.
 * @return {Object} React style object.
 */
const placeholderStyle = ( p, scale ) => {
	const style = {
		position: 'absolute',
		top: `${ p.y }%`,
		fontSize: `${ Math.max( 1, p.size * scale ) }px`,
		color: p.color,
		fontWeight: p.weight,
		textAlign: p.align,
		lineHeight: 1.2,
		whiteSpace: 'nowrap',
	};
	if ( p.align === 'center' ) {
		style.left = 0;
		style.width = '100%';
		style.whiteSpace = 'normal';
	} else if ( p.align === 'right' ) {
		style.right = `${ 100 - p.x }%`;
	} else {
		style.left = `${ p.x }%`;
	}
	return style;
};

const CertificateTemplate = ( { template, onChange, courseTitle } ) => {
	const [ bgUrl, setBgUrl ] = useState( '' );

	// Resolve the background image URL for the preview when an ID is set.
	useEffect( () => {
		if ( ! template.background_id ) {
			setBgUrl( '' );
			return;
		}
		apiFetch( { path: `/wp/v2/media/${ template.background_id }` } )
			.then( ( m ) => setBgUrl( m?.source_url || '' ) )
			.catch( () => setBgUrl( '' ) );
	}, [ template.background_id ] );

	const preset = PRESETS[ template.preset ] || PRESETS.classic;
	const page = PAGE[ template.orientation ] || PAGE.landscape;
	const scale = PREVIEW_W / page.w;
	const previewH = page.h * scale;

	const updatePlaceholder = ( key, field, value ) => {
		onChange( {
			...template,
			placeholders: {
				...template.placeholders,
				[ key ]: { ...template.placeholders[ key ], [ field ]: value },
			},
		} );
	};

	const openMedia = () => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		const frame = window.wp.media( {
			title: __( 'Select Certificate Background', 'simple-lms-bridge' ),
			multiple: false,
			library: { type: 'image' },
		} );
		frame.on( 'select', () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			onChange( { ...template, background_id: att.id } );
			setBgUrl( att.url );
		} );
		frame.open();
	};

	const samples = { ...SAMPLES, course_title: courseTitle || SAMPLES.course_title };

	return (
		<>
			{ /* ── Live Preview ─────────────────────────────────── */ }
			<div
				className="slms-cert-preview"
				style={ {
					position: 'relative',
					width: `${ PREVIEW_W }px`,
					maxWidth: '100%',
					height: `${ previewH }px`,
					margin: '0 auto 16px',
					boxShadow: '0 2px 10px rgba(0,0,0,.15)',
					background: bgUrl
						? `center / cover no-repeat url('${ bgUrl }')`
						: preset.bg,
					fontFamily: preset.font,
					overflow: 'hidden',
				} }
			>
				<div
					style={ {
						position: 'absolute',
						top: '3%',
						left: '2%',
						right: '2%',
						bottom: '3%',
						border: preset.frame,
						pointerEvents: 'none',
					} }
				/>
				{ PLACEHOLDERS.map( ( { key } ) => (
					<div key={ key } style={ placeholderStyle( template.placeholders[ key ], scale ) }>
						{ samples[ key ] }
					</div>
				) ) }
			</div>

			{ /* ── Base Settings ────────────────────────────────── */ }
			<div style={ { display: 'flex', gap: '16px' } }>
				<SelectControl
					label={ __( 'Preset', 'simple-lms-bridge' ) }
					value={ template.preset }
					options={ [
						{ label: __( 'Classic', 'simple-lms-bridge' ), value: 'classic' },
						{ label: __( 'Modern', 'simple-lms-bridge' ), value: 'modern' },
						{ label: __( 'Minimal', 'simple-lms-bridge' ), value: 'minimal' },
					] }
					onChange={ ( val ) => onChange( { ...template, preset: val } ) }
				/>
				<SelectControl
					label={ __( 'Orientation', 'simple-lms-bridge' ) }
					value={ template.orientation }
					options={ [
						{ label: __( 'Landscape', 'simple-lms-bridge' ), value: 'landscape' },
						{ label: __( 'Portrait', 'simple-lms-bridge' ), value: 'portrait' },
					] }
					onChange={ ( val ) => onChange( { ...template, orientation: val } ) }
				/>
			</div>

			<div className="slms-cert-bg-picker" style={ { margin: '8px 0 12px' } }>
				<Button variant="secondary" onClick={ openMedia }>
					{ template.background_id
						? __( 'Change Background', 'simple-lms-bridge' )
						: __( 'Set Background Image', 'simple-lms-bridge' ) }
				</Button>
				{ template.background_id ? (
					<Button
						variant="link"
						isDestructive
						onClick={ () => onChange( { ...template, background_id: 0 } ) }
						style={ { marginLeft: 8 } }
					>
						{ __( 'Remove', 'simple-lms-bridge' ) }
					</Button>
				) : null }
			</div>

			{ /* ── Per-placeholder position controls ────────────── */ }
			{ PLACEHOLDERS.map( ( { key, label } ) => {
				const p = template.placeholders[ key ];
				return (
					<PanelBody key={ key } title={ label } initialOpen={ false }>
						<div style={ { display: 'flex', gap: '16px' } }>
							<RangeControl
								label={ __( 'X %', 'simple-lms-bridge' ) }
								value={ p.x }
								min={ 0 }
								max={ 100 }
								onChange={ ( v ) => updatePlaceholder( key, 'x', v ) }
							/>
							<RangeControl
								label={ __( 'Y %', 'simple-lms-bridge' ) }
								value={ p.y }
								min={ 0 }
								max={ 100 }
								onChange={ ( v ) => updatePlaceholder( key, 'y', v ) }
							/>
						</div>
						<RangeControl
							label={ __( 'Font size (px)', 'simple-lms-bridge' ) }
							value={ p.size }
							min={ 6 }
							max={ 120 }
							onChange={ ( v ) => updatePlaceholder( key, 'size', v ) }
						/>
						<div style={ { display: 'flex', gap: '16px', alignItems: 'flex-end' } }>
							<SelectControl
								label={ __( 'Align', 'simple-lms-bridge' ) }
								value={ p.align }
								options={ [
									{ label: __( 'Left', 'simple-lms-bridge' ), value: 'left' },
									{ label: __( 'Center', 'simple-lms-bridge' ), value: 'center' },
									{ label: __( 'Right', 'simple-lms-bridge' ), value: 'right' },
								] }
								onChange={ ( v ) => updatePlaceholder( key, 'align', v ) }
							/>
							<SelectControl
								label={ __( 'Weight', 'simple-lms-bridge' ) }
								value={ p.weight }
								options={ [
									{ label: __( 'Normal', 'simple-lms-bridge' ), value: 'normal' },
									{ label: __( 'Bold', 'simple-lms-bridge' ), value: 'bold' },
								] }
								onChange={ ( v ) => updatePlaceholder( key, 'weight', v ) }
							/>
							<label className="slms-color-field">
								<span>{ __( 'Color', 'simple-lms-bridge' ) }</span>
								<input
									type="color"
									value={ p.color }
									onChange={ ( e ) => updatePlaceholder( key, 'color', e.target.value ) }
								/>
							</label>
						</div>
					</PanelBody>
				);
			} ) }
		</>
	);
};

export default CertificateTemplate;
