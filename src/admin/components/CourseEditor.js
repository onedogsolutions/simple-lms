/**
 * CourseEditor – Lesson Sorter, Certificate Dropdown, Access Days, PMPro Levels.
 *
 * Renders inside the Course CPT meta box.
 *
 * @package
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	PanelBody,
	SelectControl,
	SearchControl,
	TextControl,
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Reorder } from 'motion/react';

import LessonItem from './LessonItem';
import PMProLevels from './PMProLevels';
import CertificateTemplate from './CertificateTemplate';

// Mirror of Template::defaults() on the PHP side.
const DEFAULT_CERT_TEMPLATE = {
	background_id: 0,
	preset: 'classic',
	orientation: 'landscape',
	placeholders: {
		student_name: { x: 50, y: 42, size: 44, color: '#1a1a1a', align: 'center', weight: 'bold' },
		course_title: { x: 50, y: 58, size: 26, color: '#333333', align: 'center', weight: 'normal' },
		completed_date: { x: 50, y: 70, size: 16, color: '#555555', align: 'center', weight: 'normal' },
		license_number: { x: 12, y: 88, size: 12, color: '#555555', align: 'left', weight: 'normal' },
		cert_uuid: { x: 88, y: 88, size: 10, color: '#888888', align: 'right', weight: 'normal' },
	},
};

/**
 * Deep-merge a stored (possibly partial) template onto the defaults.
 *
 * @param {Object} stored Stored template meta.
 * @return {Object} Complete template.
 */
const mergeTemplate = ( stored ) => {
	const base = { ...DEFAULT_CERT_TEMPLATE, placeholders: {} };
	const s = stored && typeof stored === 'object' ? stored : {};
	base.background_id = s.background_id || 0;
	base.preset = s.preset || DEFAULT_CERT_TEMPLATE.preset;
	base.orientation = s.orientation || DEFAULT_CERT_TEMPLATE.orientation;
	Object.keys( DEFAULT_CERT_TEMPLATE.placeholders ).forEach( ( key ) => {
		base.placeholders[ key ] = {
			...DEFAULT_CERT_TEMPLATE.placeholders[ key ],
			...( s.placeholders && s.placeholders[ key ] ? s.placeholders[ key ] : {} ),
		};
	} );
	return base;
};

/**
 * CourseEditor component.
 *
 * @param {Object} props
 * @param {number} props.postId Current course post ID.
 * @return {JSX.Element} The rendered component.
 */
const CourseEditor = ({ postId }) => {
	// ── State ──────────────────────────────────────────────────────
	const [lessonOrder, setLessonOrder] = useState([]);
	const [allLessons, setAllLessons] = useState([]);
	const [forms, setForms] = useState([]);
	const [certificateForm, setCertificateForm] = useState(0);
	const [certTemplate, setCertTemplate] = useState(DEFAULT_CERT_TEMPLATE);
	const [courseTitle, setCourseTitle] = useState('');
	const [completionRedirect, setCompletionRedirect] = useState('');
	const [pmproLevels, setPmproLevels] = useState([]);
	const [allPMProLevels, setAllPMProLevels] = useState([]);
	const [enrolledStudents, setEnrolledStudents] = useState([]);
	const [saving, setSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [loading, setLoading] = useState(true);
	const [lessonSearch, setLessonSearch] = useState('');

	// ── Load initial data ─────────────────────────────────────────
	useEffect(() => {
		const load = async () => {
			try {
				const [
					lessonsRes,
					formsRes,
					levelsRes,
					postRes,
					relationshipsRes,
					studentsRes,
				] = await Promise.all([
					apiFetch({ path: '/simple-lms/v1/lessons' }),
					apiFetch({ path: '/simple-lms/v1/forms' }),
					apiFetch({ path: '/simple-lms/v1/pmpro-levels' }),
					apiFetch({ path: `/wp/v2/lms-courses/${postId}` }),
					apiFetch({
						path: `/simple-lms/v1/relationships/course/${postId}/lessons`,
					}),
					apiFetch({
						path: `/simple-lms/v1/enrollments/course/${postId}/students`,
					}),
				]);

				setAllLessons(lessonsRes);
				setForms(formsRes);
				setAllPMProLevels(levelsRes);
				setEnrolledStudents(studentsRes || []);

				const meta = postRes.meta || {};
				setLessonOrder(relationshipsRes.map((l) => l.id) || []);
				setCertificateForm(meta._lms_certificate_form || 0);
				setCompletionRedirect(meta._lms_completion_redirect || '');
				setPmproLevels(meta._lms_pmpro_levels || []);
				setCertTemplate(mergeTemplate(meta._lms_cert_template));
				setCourseTitle(postRes.title?.rendered || '');
			} catch (err) {
				setNotice({ status: 'error', message: err.message });
			} finally {
				setLoading(false);
			}
		};
		load();
	}, [postId]);

	// ── Save handler ──────────────────────────────────────────────
	const handleSave = useCallback(async () => {
		setSaving(true);
		setNotice(null);
		try {
			await Promise.all([
				apiFetch({
					path: `/wp/v2/lms-courses/${postId}`,
					method: 'POST',
					data: {
						meta: {
							_lms_certificate_form:
								parseInt(certificateForm, 10) || 0,
							_lms_completion_redirect: completionRedirect,
							_lms_pmpro_levels: pmproLevels,
							_lms_cert_template: certTemplate,
						},
					},
				}),
				apiFetch({
					path: `/simple-lms/v1/relationships/course/${postId}/lessons`,
					method: 'POST',
					data: {
						lesson_ids: lessonOrder,
					},
				}),
			]);
			setNotice({
				status: 'success',
				message: __('Course settings saved.', 'simple-lms-bridge'),
			});
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		} finally {
			setSaving(false);
		}
	}, [
		postId,
		lessonOrder,
		certificateForm,
		completionRedirect,
		pmproLevels,
		certTemplate,
	]);

	// ── Add lesson to order ───────────────────────────────────────
	const addLesson = (lessonId) => {
		const id = parseInt(lessonId, 10);
		if (id && !lessonOrder.includes(id)) {
			setLessonOrder([...lessonOrder, id]);
		}
	};

	// ── Remove lesson from order ──────────────────────────────────
	const removeLesson = (lessonId) => {
		setLessonOrder(lessonOrder.filter((id) => id !== lessonId));
	};

	// ── Build lesson lookup map ───────────────────────────────────
	const lessonMap = {};
	allLessons.forEach((l) => {
		lessonMap[l.id] = l.title;
	});

	// Available lessons (not yet in the order).
	const availableLessons = allLessons.filter(
		(l) => !lessonOrder.includes(l.id)
	);

	if (loading) {
		return <Spinner />;
	}

	return (
		<>
			{notice && (
				<Notice
					status={notice.status}
					isDismissible
					onDismiss={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			{ /* ── Lesson Sorter ─────────────────────────────────── */}
			<PanelBody
				title={__('Course Lessons', 'simple-lms-bridge')}
				initialOpen={true}
			>
				{lessonOrder.length === 0 && (
					<p className="slms-empty">
						{__(
							'No lessons assigned yet.',
							'simple-lms-bridge'
						)}
					</p>
				)}

				<Reorder.Group
					axis="y"
					values={lessonOrder}
					onReorder={setLessonOrder}
					className="slms-lesson-list"
				>
					{lessonOrder.map((id) => (
						<LessonItem
							key={id}
							value={id}
							title={lessonMap[id] || `#${id}`}
							onRemove={() => removeLesson(id)}
						/>
					))}
				</Reorder.Group>

				<div className="slms-relationship-picker">
					<SearchControl
						label={__(
							'Search Lessons to Add',
							'simple-lms-bridge'
						)}
						value={lessonSearch}
						onChange={setLessonSearch}
					/>
					{lessonSearch && (
						<div className="slms-search-results">
							{availableLessons
								.filter((l) =>
									l.title
										.toLowerCase()
										.includes(lessonSearch.toLowerCase())
								)
								.slice(0, 10)
								.map((l) => (
									<button
										key={l.id}
										type="button"
										className="slms-search-result-item"
										onClick={() => {
											addLesson(l.id);
											setLessonSearch('');
										}}
									>
										{l.title}
									</button>
								))}
							{availableLessons.filter((l) =>
								l.title
									.toLowerCase()
									.includes(lessonSearch.toLowerCase())
							).length === 0 && (
									<div className="slms-no-results">
										{__(
											'No matches found.',
											'simple-lms-bridge'
										)}
									</div>
								)}
						</div>
					)}
				</div>
			</PanelBody>

			{ /* ── Certificate Form ──────────────────────────────── */}
			<PanelBody
				title={__('Certificate', 'simple-lms-bridge')}
				initialOpen={false}
			>
				<SelectControl
					label={__(
						'Certificate Gravity Form',
						'simple-lms-bridge'
					)}
					value={certificateForm}
					options={[
						{
							label: __('— None —', 'simple-lms-bridge'),
							value: 0,
						},
						...forms.map((f) => ({
							label: f.title,
							value: f.id,
						})),
					]}
					onChange={(val) =>
						setCertificateForm(parseInt(val, 10))
					}
				/>
				<p className="slms-panel-desc">
					{__(
						'Legacy option: used only for pre-existing migrated certificates. New completions use the native template below.',
						'simple-lms-bridge'
					)}
				</p>
			</PanelBody>

			{ /* ── Native Certificate Template ─────────────────────── */}
			<PanelBody
				title={__('Certificate Template', 'simple-lms-bridge')}
				initialOpen={false}
			>
				<p className="slms-panel-desc">
					{__(
						'Design the branded PDF issued when a student completes this course. Drag the position sliders and watch the live preview.',
						'simple-lms-bridge'
					)}
				</p>
				<CertificateTemplate
					template={certTemplate}
					onChange={setCertTemplate}
					courseTitle={courseTitle}
				/>
			</PanelBody>

			{ /* ── Completion Redirect ───────────────────────────── */}
			<PanelBody
				title={__('Completion', 'simple-lms-bridge')}
				initialOpen={false}
			>
				<TextControl
					label={__(
						'Completion Redirect URL',
						'simple-lms-bridge'
					)}
					help={__(
						'When the final lesson is completed, the student is sent to this URL (e.g. a certificate page). Leave blank for no redirect.',
						'simple-lms-bridge'
					)}
					type="url"
					value={completionRedirect}
					onChange={setCompletionRedirect}
				/>
			</PanelBody>


			{ /* ── PMPro Membership Levels ─────────────────────────── */}
			<PanelBody
				title={__('PMPro Enrollment', 'simple-lms-bridge')}
				initialOpen={false}
			>
				<p className="slms-panel-desc">
					{__(
						'Select membership levels that grant access to this course.',
						'simple-lms-bridge'
					)}
				</p>
				<PMProLevels
					selectedLevels={pmproLevels}
					onChange={setPmproLevels}
				/>
			</PanelBody>

			{ /* ── Enrolled Students ───────────────────────────────── */}
			<PanelBody
				title={__('Enrolled Students', 'simple-lms-bridge')}
				initialOpen={false}
			>
				{enrolledStudents.length === 0 ? (
					<p className="slms-empty">
						{__(
							'No students enrolled in this course.',
							'simple-lms-bridge'
						)}
					</p>
				) : (
					<ul className="slms-student-list">
						{enrolledStudents.map((student) => (
							<li
								key={student.id}
								className="slms-student-item"
							>
								<strong>{student.display_name}</strong>
								<span>{student.email}</span>
								<span className="slms-badge slms-badge-secondary">
									{student.source}
								</span>
							</li>
						))}
					</ul>
				)}
			</PanelBody>

			{ /* ── Save Button ────────────────────────────────────── */}
			<div className="slms-save-bar">
				<Button
					variant="primary"
					isBusy={saving}
					disabled={saving}
					onClick={handleSave}
				>
					{saving
						? __('Saving…', 'simple-lms-bridge')
						: __('Save Course Settings', 'simple-lms-bridge')}
				</Button>
			</div>
		</>
	);
};

export default CourseEditor;
