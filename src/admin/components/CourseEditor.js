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
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Reorder } from 'motion/react';

import LessonItem from './LessonItem';
import PMProLevels from './PMProLevels';

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
	const [pmproLevels, setPmproLevels] = useState([]);
	const [allPMProLevels, setAllPMProLevels] = useState([]);
	const [guardMode, setGuardMode] = useState('enrolled');
	const [denialBehavior, setDenialBehavior] = useState('redirect');
	const [checkoutOverride, setCheckoutOverride] = useState(0);
	const [pages, setPages] = useState([]);
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
					pagesRes,
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
					apiFetch({
						path: '/wp/v2/pages?per_page=100&_fields=id,title&orderby=title&order=asc',
					}).catch(() => []),
				]);

				setAllLessons(lessonsRes);
				setForms(formsRes);
				setAllPMProLevels(levelsRes);
				setEnrolledStudents(studentsRes || []);
				setPages(
					(pagesRes || []).map((p) => ({
						id: p.id,
						title: p.title?.rendered || `#${p.id}`,
					}))
				);

				const meta = postRes.meta || {};
				setLessonOrder(relationshipsRes.map((l) => l.id) || []);
				setCertificateForm(meta._lms_certificate_form || 0);
				setPmproLevels(meta._lms_pmpro_levels || []);
				setGuardMode(meta._lms_guard_mode || 'enrolled');
				setDenialBehavior(meta._lms_denial_behavior || 'redirect');
				setCheckoutOverride(meta._lms_checkout_override || 0);
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
							_lms_pmpro_levels: pmproLevels,
							_lms_guard_mode: guardMode,
							_lms_denial_behavior: denialBehavior,
							_lms_checkout_override:
								parseInt(checkoutOverride, 10) || 0,
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
		pmproLevels,
		guardMode,
		denialBehavior,
		checkoutOverride,
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

			{ /* ── Access Control ────────────────────────────────── */}
			<PanelBody
				title={__('Access Control', 'simple-lms-bridge')}
				initialOpen={false}
			>
				<SelectControl
					label={__('Guard Mode', 'simple-lms-bridge')}
					help={__(
						'Who can view this course and its lessons.',
						'simple-lms-bridge'
					)}
					value={guardMode}
					options={[
						{
							label: __(
								'Public — anyone can view',
								'simple-lms-bridge'
							),
							value: 'public',
						},
						{
							label: __(
								'Enrolled — enrollment row required',
								'simple-lms-bridge'
							),
							value: 'enrolled',
						},
						{
							label: __(
								'Membership level — PMPro level required',
								'simple-lms-bridge'
							),
							value: 'level',
						},
					]}
					onChange={(val) => setGuardMode(val)}
				/>

				<SelectControl
					label={__('When Access Is Denied', 'simple-lms-bridge')}
					help={__(
						'Redirect visitors to login/checkout, or keep them on the page with an inline message and call-to-action.',
						'simple-lms-bridge'
					)}
					value={denialBehavior}
					options={[
						{
							label: __(
								'Redirect (login / checkout)',
								'simple-lms-bridge'
							),
							value: 'redirect',
						},
						{
							label: __(
								'Inline message + CTA',
								'simple-lms-bridge'
							),
							value: 'message',
						},
					]}
					onChange={(val) => setDenialBehavior(val)}
				/>

				<SelectControl
					label={__(
						'Checkout Page Override',
						'simple-lms-bridge'
					)}
					help={__(
						'Send denied users to this page instead of the PMPro checkout. Leave as default to use the mapped membership level.',
						'simple-lms-bridge'
					)}
					value={checkoutOverride}
					options={[
						{
							label: __(
								'— Default (PMPro checkout) —',
								'simple-lms-bridge'
							),
							value: 0,
						},
						...pages.map((p) => ({
							label: p.title,
							value: p.id,
						})),
					]}
					onChange={(val) =>
						setCheckoutOverride(parseInt(val, 10) || 0)
					}
				/>
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
