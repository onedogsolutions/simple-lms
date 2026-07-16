/**
 * LessonSettings – Lesson type, video/quiz pickers, and timer.
 *
 * Renders inside the Lesson CPT meta box.
 *
 * @package
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	PanelBody,
	SelectControl,
	TextControl,
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * LessonSettings component.
 *
 * @param {Object} props
 * @param {number} props.postId Current lesson post ID.
 * @return {JSX.Element} The rendered component.
 */
const LessonSettings = ( { postId } ) => {
	// ── State ──────────────────────────────────────────────────────
	const [ lessonType, setLessonType ] = useState( '' );
	const [ prestoVideo, setPrestoVideo ] = useState( 0 );
	const [ gravityForm, setGravityForm ] = useState( 0 );
	const [ quizTimer, setQuizTimer ] = useState( 0 );
	const [ quizPassField, setQuizPassField ] = useState( '' );
	const [ quizPassMin, setQuizPassMin ] = useState( 0 );
	const [ dripDays, setDripDays ] = useState( 0 );
	const [ videoGatePct, setVideoGatePct ] = useState( 0 );
	const [ videos, setVideos ] = useState( [] );
	const [ forms, setForms ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ assignedCourses, setAssignedCourses ] = useState( [] );

	// ── Load initial data ─────────────────────────────────────────
	useEffect( () => {
		const load = async () => {
			try {
				const [ videosRes, formsRes, postRes, relationshipsRes ] =
					await Promise.all( [
						apiFetch( { path: '/simple-lms/v1/videos' } ),
						apiFetch( { path: '/simple-lms/v1/forms' } ),
						apiFetch( { path: `/wp/v2/lms-lessons/${ postId }` } ),
						apiFetch( {
							path: `/simple-lms/v1/relationships/lesson/${ postId }/courses`,
						} ),
					] );

				setVideos( videosRes );
				setForms( formsRes );
				setAssignedCourses( relationshipsRes );

				const meta = postRes.meta || {};
				setLessonType( meta._slms_lesson_type || '' );
				setPrestoVideo( meta._lms_presto_video || 0 );
				setGravityForm( meta._lms_gravity_form || 0 );
				setQuizTimer( meta._lms_quiz_timer || 0 );
				setQuizPassField( meta._lms_quiz_pass_field || '' );
				setQuizPassMin( meta._lms_quiz_pass_min || 0 );
				setDripDays( meta._lms_drip_days || 0 );
				setVideoGatePct( meta._lms_video_gate_pct || 0 );
			} catch ( err ) {
				setNotice( { status: 'error', message: err.message } );
			} finally {
				setLoading( false );
			}
		};
		load();
	}, [ postId ] );

	// ── Save handler ──────────────────────────────────────────────
	const handleSave = useCallback( async () => {
		setSaving( true );
		setNotice( null );
		try {
			await apiFetch( {
				path: `/wp/v2/lms-lessons/${ postId }`,
				method: 'POST',
				data: {
					meta: {
						_slms_lesson_type: lessonType,
						_lms_presto_video: parseInt( prestoVideo, 10 ) || 0,
						_lms_gravity_form: parseInt( gravityForm, 10 ) || 0,
						_lms_quiz_timer: parseInt( quizTimer, 10 ) || 0,
						_lms_quiz_pass_field: quizPassField,
						_lms_quiz_pass_min:
							parseFloat( quizPassMin ) || 0,
						_lms_drip_days: parseInt( dripDays, 10 ) || 0,
						_lms_video_gate_pct:
							parseInt( videoGatePct, 10 ) || 0,
					},
				},
			} );
			setNotice( {
				status: 'success',
				message: __( 'Lesson settings saved.', 'simple-lms-bridge' ),
			} );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setSaving( false );
		}
	}, [
		postId,
		lessonType,
		prestoVideo,
		gravityForm,
		quizTimer,
		quizPassField,
		quizPassMin,
		dripDays,
		videoGatePct,
	] );

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<PanelBody
				title={ __( 'Lesson Type', 'simple-lms-bridge' ) }
				initialOpen={ true }
			>
				<SelectControl
					label={ __( 'Content Type', 'simple-lms-bridge' ) }
					value={ lessonType }
					options={ [
						{
							label: __( '— None —', 'simple-lms-bridge' ),
							value: '',
						},
						{
							label: __( 'Video', 'simple-lms-bridge' ),
							value: 'video',
						},
						{
							label: __( 'Quiz', 'simple-lms-bridge' ),
							value: 'quiz',
						},
					] }
					onChange={ setLessonType }
				/>

				{ /* ── Video Picker ──────────────────────────────── */ }
				{ lessonType === 'video' && (
					<SelectControl
						label={ __(
							'Presto Player Video',
							'simple-lms-bridge'
						) }
						value={ prestoVideo }
						options={ [
							{
								label: __(
									'— Select Video —',
									'simple-lms-bridge'
								),
								value: 0,
							},
							...videos.map( ( v ) => ( {
								label: v.title,
								value: v.id,
							} ) ),
						] }
						onChange={ ( val ) =>
							setPrestoVideo( parseInt( val, 10 ) )
						}
					/>
				) }

				{ lessonType === 'video' && (
					<TextControl
						label={ __(
							'Require % watched to complete',
							'simple-lms-bridge'
						) }
						help={ __(
							'0 = no video gate. Complete button unlocks after this percent is watched.',
							'simple-lms-bridge'
						) }
						type="number"
						min={ 0 }
						max={ 100 }
						value={ videoGatePct }
						onChange={ ( val ) =>
							setVideoGatePct( parseInt( val, 10 ) || 0 )
						}
					/>
				) }

				{ /* ── Quiz Picker + Timer ────────────────────────── */ }
				{ lessonType === 'quiz' && (
					<>
						<SelectControl
							label={ __(
								'Gravity Form (Quiz)',
								'simple-lms-bridge'
							) }
							value={ gravityForm }
							options={ [
								{
									label: __(
										'— Select Form —',
										'simple-lms-bridge'
									),
									value: 0,
								},
								...forms.map( ( f ) => ( {
									label: f.title,
									value: f.id,
								} ) ),
							] }
							onChange={ ( val ) =>
								setGravityForm( parseInt( val, 10 ) )
							}
						/>
						<TextControl
							label={ __(
								'Timer (minutes)',
								'simple-lms-bridge'
							) }
							help={ __(
								'0 = no time limit',
								'simple-lms-bridge'
							) }
							type="number"
							min={ 0 }
							value={ quizTimer }
							onChange={ ( val ) =>
								setQuizTimer( parseInt( val, 10 ) || 0 )
							}
						/>
						<TextControl
							label={ __(
								'Passing Score Field ID',
								'simple-lms-bridge'
							) }
							help={ __(
								'Gravity Forms field ID holding the score. Leave blank to auto-complete on any submission.',
								'simple-lms-bridge'
							) }
							value={ quizPassField }
							onChange={ setQuizPassField }
						/>
						{ quizPassField !== '' && (
							<TextControl
								label={ __(
									'Minimum Passing Score',
									'simple-lms-bridge'
								) }
								help={ __(
									'Auto-complete only when the score is at least this value.',
									'simple-lms-bridge'
								) }
								type="number"
								min={ 0 }
								value={ quizPassMin }
								onChange={ ( val ) =>
									setQuizPassMin( parseFloat( val ) || 0 )
								}
							/>
						) }
					</>
				) }
			</PanelBody>

			<PanelBody
				title={ __( 'Drip Scheduling', 'simple-lms-bridge' ) }
				initialOpen={ false }
			>
				<TextControl
					label={ __(
						'Unlock after (days from enrollment)',
						'simple-lms-bridge'
					) }
					help={ __(
						'0 = available immediately. Otherwise this lesson unlocks this many days after the student enrolls.',
						'simple-lms-bridge'
					) }
					type="number"
					min={ 0 }
					value={ dripDays }
					onChange={ ( val ) =>
						setDripDays( parseInt( val, 10 ) || 0 )
					}
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Assigned Courses', 'simple-lms-bridge' ) }
				initialOpen={ false }
			>
				<p className="slms-panel-desc">
					{ __(
						'This lesson is assigned to the following courses:',
						'simple-lms-bridge'
					) }
				</p>
				{ assignedCourses.length === 0 ? (
					<p className="slms-empty">
						{ __(
							'Not assigned to any course.',
							'simple-lms-bridge'
						) }
					</p>
				) : (
					<ul className="slms-assigned-courses">
						{ assignedCourses.map( ( course ) => (
							<li
								key={ course.id }
								className="slms-assigned-course-item"
							>
								<a
									href={ `post.php?post=${ course.id }&action=edit` }
								>
									{ course.title }
								</a>
							</li>
						) ) }
					</ul>
				) }
			</PanelBody>

			<div className="slms-save-bar">
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving }
					onClick={ handleSave }
				>
					{ saving
						? __( 'Saving…', 'simple-lms-bridge' )
						: __( 'Save Lesson Settings', 'simple-lms-bridge' ) }
				</Button>
			</div>
		</>
	);
};

export default LessonSettings;
