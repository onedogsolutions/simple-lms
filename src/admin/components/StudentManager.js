/**
 * StudentManager – Searchable dashboard for student progress management.
 *
 * Renders on the top-level Students admin page.
 *
 * @package
 */

import {
	useState,
	useEffect,
	useCallback,
	useRef,
	Fragment,
} from '@wordpress/element';
import {
	SearchControl,
	Button,
	Spinner,
	Notice,
	CheckboxControl,
	SelectControl,
	TabPanel,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Component for rendering historical certificate data.
 * @param {Object} root0
 * @param {number} root0.userId
 * @return {JSX.Element} The rendered component.
 */
const HistoryTab = ({ userId }) => {
	const [history, setHistory] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		apiFetch({ path: `/simple-lms/v1/student/${userId}/history` })
			.then((res) => {
				setHistory(res || []);
				setLoading(false);
			})
			.catch(() => {
				setLoading(false);
			});
	}, [userId]);

	if (loading) {
		return (
			<div className="flex justify-center p-6">
				<Spinner />
			</div>
		);
	}

	if (history.length === 0) {
		return (
			<p className="text-gray-500 italic mt-4 p-6 bg-gray-50 rounded-xl border border-gray-200">
				{__(
					'No completion history records found.',
					'simple-lms-bridge'
				)}
			</p>
		);
	}

	return (
		<div className="space-y-4 mt-4 bg-gray-50 p-6 rounded-xl border border-gray-200 shadow-sm">
			<h4 className="font-semibold text-lg text-gray-800 border-b border-gray-200 pb-2 mb-4">
				{__('Completion History', 'simple-lms-bridge')}
			</h4>
			<div className="overflow-x-auto">
				<table className="min-w-full divide-y divide-gray-200 bg-white rounded-lg shadow-sm border border-gray-200">
					<thead className="bg-gray-100">
						<tr>
							<th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
								{__('Date', 'simple-lms-bridge')}
							</th>
							<th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
								{__('Class', 'simple-lms-bridge')}
							</th>
						</tr>
					</thead>
					<tbody className="divide-y divide-gray-100">
						{history.map((entry, idx) => (
							<tr
								key={idx}
								className="hover:bg-gray-50 transition-colors"
							>
								<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
									{new Date(entry.date).toLocaleString()}
								</td>
								<td className="px-4 py-3 text-sm text-gray-900 font-medium">
									{entry.course_name}
								</td>
							</tr>
						))}
					</tbody>
				</table>
			</div>
		</div>
	);
};

/**
 * StudentManager component.
 *
 * @return {JSX.Element} The rendered component.
 */
const StudentManager = () => {
	const [search, setSearch] = useState('');
	const [students, setStudents] = useState([]);
	const [total, setTotal] = useState(0);
	const [page, setPage] = useState(1);
	const [pages, setPages] = useState(1);
	const [loading, setLoading] = useState(false);
	const [expandedStudent, setExpandedStudent] = useState(null);
	const [notice, setNotice] = useState(null);
	const [allAvailableCourses, setAllAvailableCourses] = useState([]);
	const [enrolling, setEnrolling] = useState({}); // { userId: true }

	// Sorting & Filtering state
	const [sortColumn, setSortColumn] = useState('display_name');
	const [sortDirection, setSortDirection] = useState('asc');
	const [courseFilter, setCourseFilter] = useState('');

	// Dirty state tracking for unsaved changes
	const [dirtyData, setDirtyData] = useState({}); // { userId: { courseId: { lessonId: boolean } } }
	const [metaData, setMetaData] = useState({}); // { userId: { ...fields } }
	const [unsavedChanges, setUnsavedChanges] = useState(false);
	const [saving, setSaving] = useState(false);

	// Handle beforeunload for unsaved changes
	useEffect(() => {
		const handleBeforeUnload = (e) => {
			if (unsavedChanges) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener('beforeunload', handleBeforeUnload);
		return () =>
			window.removeEventListener('beforeunload', handleBeforeUnload);
	}, [unsavedChanges]);

	// ── Fetch students ────────────────────────────────────────────
	const fetchStudents = useCallback(async (s, p) => {
		setLoading(true);
		try {
			const params = new URLSearchParams({
				page: p,
				per_page: 50, // Increased for better filtering experience
			});
			if (s) {
				params.set('search', s);
			}
			const res = await apiFetch({
				path: `/simple-lms/v1/students?${params.toString()}`,
			});
			setStudents(res.students);
			setTotal(res.total);
			setPages(res.pages);

			// Initialize meta state
			const newMeta = {};
			res.students.forEach((student) => {
				newMeta[student.id] = { ...student.meta };
			});
			setMetaData(newMeta);
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		} finally {
			setLoading(false);
		}
	}, []);


	const fetchAvailableCourses = async () => {
		try {
			const res = await apiFetch({
				path: '/simple-lms/v1/relationships/courses',
			});
			setAllAvailableCourses(res || []);
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		}
	};


	// Initial load.
	useEffect(() => {
		fetchStudents('', 1);
		fetchAvailableCourses();
	}, []);

	// Track when search triggers a fetch so page-change effect can skip redundant call.
	const searchTriggeredFetch = useRef(false);

	// Search with debounce.
	useEffect(() => {
		const timeout = setTimeout(() => {
			searchTriggeredFetch.current = true;
			setPage(1);
			fetchStudents(search, 1);
		}, 400);
		return () => clearTimeout(timeout);
	}, [search, fetchStudents]);

	// Page change — skip if search already triggered the fetch.
	useEffect(() => {
		if (searchTriggeredFetch.current) {
			searchTriggeredFetch.current = false;
			return;
		}
		fetchStudents(search, page);
	}, [page, search, fetchStudents]);

	// ── Toggle local completion ─────────────────────────────────────────
	const toggleLocalCompletion = (userId, courseId, lessonId) => {
		setDirtyData((prev) => {
			const next = { ...prev };
			if (!next[userId]) {
				next[userId] = {};
			}
			if (!next[userId][courseId]) {
				next[userId][courseId] = {};
			}

			const currentState = next[userId][courseId][lessonId];
			if (currentState === undefined) {
				const student = students.find((s) => s.id === userId);
				const course = student.courses.find(
					(c) => c.course_id === courseId
				);
				const isCompleted = course.lessons[lessonId].completed;
				next[userId][courseId][lessonId] = !isCompleted;
			} else {
				next[userId][courseId][lessonId] = !currentState;
			}

			return next;
		});
		setUnsavedChanges(true);
	};

	const handleMetaChange = (userId, field, value) => {
		setMetaData((prev) => ({
			...prev,
			[userId]: {
				...prev[userId],
				[field]: value,
			},
		}));
		setUnsavedChanges(true);
	};

	const getLessonStatus = (userId, courseId, lessonId) => {
		if (dirtyData[userId]?.[courseId]?.[lessonId] !== undefined) {
			return dirtyData[userId][courseId][lessonId];
		}
		const student = students.find((s) => s.id === userId);
		const course = student.courses.find(
			(c) => c.course_id === courseId
		);
		return course.lessons[lessonId]?.completed || false;
	};

	const handleUpdate = async (userId) => {
		setSaving(true);
		try {
			const updates = [];

			// Save Progress
			if (dirtyData[userId]) {
				for (const courseId in dirtyData[userId]) {
					for (const lessonId in dirtyData[userId][courseId]) {
						updates.push(
							apiFetch({
								path: '/simple-lms/v1/progress',
								method: 'POST',
								data: {
									user_id: userId,
									course_id: parseInt(courseId, 10),
									lesson_id: parseInt(lessonId, 10),
									completed:
										dirtyData[userId][courseId][
										lessonId
										],
								},
							})
						);
					}
				}
			}

			// Save Meta
			if (metaData[userId]) {
				updates.push(
					apiFetch({
						path: `/simple-lms/v1/students/${userId}/meta`,
						method: 'POST',
						data: metaData[userId],
					})
				);
			}

			if (updates.length > 0) {
				await Promise.all(updates);
			}

			setDirtyData((prev) => {
				const next = { ...prev };
				delete next[userId];
				return next;
			});
			setUnsavedChanges(false);

			fetchStudents(search, page);
			setNotice({
				status: 'success',
				message: __(
					'Profile and progress updated successfully!',
					'simple-lms-bridge'
				),
			});
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		} finally {
			setSaving(false);
		}
	};

	// ── Enrollment Management ─────────────────────────────────────
	const enrollStudent = async (userId, courseId) => {
		if (!courseId) {
			return;
		}
		setEnrolling((prev) => ({ ...prev, [userId]: true }));
		try {
			await apiFetch({
				path: `/simple-lms/v1/enrollments/user/${userId}/courses`,
				method: 'POST',
				data: { course_id: courseId },
			});
			fetchStudents(search, page);
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		} finally {
			setEnrolling((prev) => ({ ...prev, [userId]: false }));
		}
	};

	const unenrollStudent = async (userId, courseId) => {
		if (
			/* eslint-disable no-alert */
			!window.confirm(
				__(
					'Are you sure you want to unenroll this student from this course? This will NOT delete their progress, but they will lose access.',
					'simple-lms-bridge'
				)
			)
			/* eslint-enable no-alert */
		) {
			return;
		}
		setEnrolling((prev) => ({ ...prev, [userId]: true }));
		try {
			await apiFetch({
				path: `/simple-lms/v1/enrollments/user/${userId}/courses/${courseId}`,
				method: 'DELETE',
			});
			fetchStudents(search, page);
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		} finally {
			setEnrolling((prev) => ({ ...prev, [userId]: false }));
		}
	};

	const formatDate = (timestamp) => {
		if (!timestamp) {
			return '';
		}
		return new Date(timestamp * 1000).toLocaleString();
	};

	const handleSort = (column) => {
		if (sortColumn === column) {
			setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
		} else {
			setSortColumn(column);
			setSortDirection('asc');
		}
	};

	// Derived logic for table display
	let displayedStudents = [...students];

	// Filter
	if (courseFilter) {
		displayedStudents = displayedStudents.filter((s) =>
			s.courses.some(
				(c) => c.course_id === parseInt(courseFilter, 10)
			)
		);
	}

	// Sort
	displayedStudents.sort((a, b) => {
		let valA, valB;
		if (sortColumn === 'display_name') {
			valA = a.display_name.toLowerCase();
			valB = b.display_name.toLowerCase();
		} else if (sortColumn === 'email') {
			valA = a.email.toLowerCase();
			valB = b.email.toLowerCase();
		} else if (sortColumn === 'courses') {
			valA = a.courses.length;
			valB = b.courses.length;
		}

		if (valA < valB) {
			return sortDirection === 'asc' ? -1 : 1;
		}
		if (valA > valB) {
			return sortDirection === 'asc' ? 1 : -1;
		}
		return 0;
	});

	const getSortIcon = (column) => {
		if (sortColumn !== column) {
			return (
				<span className="opacity-0 group-hover:opacity-50 ml-1 text-[10px]">
					▼
				</span>
			);
		}
		return sortDirection === 'asc' ? (
			<span className="ml-1 text-[10px] text-blue-600">▲</span>
		) : (
			<span className="ml-1 text-[10px] text-blue-600">▼</span>
		);
	};

	const courseOptions = [
		{
			label: __('All Courses', 'simple-lms-bridge'),
			value: '',
		},
		...allAvailableCourses.map((c) => ({
			label: c.title,
			value: c.id.toString(),
		})),
	];

	return (
		<div className="slms-student-manager max-w-7xl mx-auto py-6">
			{notice && (
				<Notice
					status={notice.status}
					isDismissible
					onDismiss={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			{/* Header Search & Filter Bar */}
			<div className="mb-8 bg-white p-5 shadow-sm rounded-lg border border-gray-200 flex flex-col md:flex-row items-end gap-4">

				{/* Search Column */}
				<div className="flex-grow w-full">
					<label className="block text-sm font-medium text-gray-700 mb-1">
						{__("Enter the Student's name or Email Address", 'simple-lms-bridge')}
					</label>
					<SearchControl
						__nextHasNoMarginBottom={true}
						label={__('Search students', 'simple-lms-bridge')}
						hideLabelFromVision={true} // Keeps it accessible but clean
						value={search}
						onChange={setSearch}
						className="slms-search w-full"
					/>
				</div>

				{/* Course Filter Column */}
				<div className="w-full md:w-1/3">
					<SelectControl
						label={__('Filter by Course', 'simple-lms-bridge')}
						value={courseFilter}
						options={courseOptions}
						onChange={setCourseFilter}
						__nextHasNoMarginBottom={true}
					/>
				</div>
			</div>

			{(() => {
				if (loading) {
					return (
						<div className="flex justify-center p-12">
							<Spinner />
						</div>
					);
				}
				if (displayedStudents.length === 0) {
					return (
						<div className="bg-white p-12 text-center text-gray-500 rounded-lg shadow-sm border border-gray-200">
							{__(
								'No students matching the criteria found.',
								'simple-lms-bridge'
							)}
						</div>
					);
				}
				return (
					<div className="space-y-4">
						<table className="min-w-full divide-y divide-gray-200 shadow-sm rounded-lg overflow-hidden bg-white">
							<thead className="bg-gray-50">
								<tr>
									<th
										scope="col"
										className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer group hover:bg-gray-100 transition-colors"
										onClick={() =>
											handleSort('display_name')
										}
									>
										{__('Student', 'simple-lms-bridge')}
										{getSortIcon('display_name')}
									</th>
									<th
										scope="col"
										className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer group hover:bg-gray-100 transition-colors"
										onClick={() => handleSort('email')}
									>
										{__('Email', 'simple-lms-bridge')}
										{getSortIcon('email')}
									</th>
									<th
										scope="col"
										className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer group hover:bg-gray-100 transition-colors"
										onClick={() =>
											handleSort('courses')
										}
									>
										{__('Courses', 'simple-lms-bridge')}
										{getSortIcon('courses')}
									</th>
									<th
										scope="col"
										className="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider"
									>
										{__('Actions', 'simple-lms-bridge')}
									</th>
								</tr>
							</thead>
							<tbody className="bg-white divide-y divide-gray-200">
								{displayedStudents.map((student) => (
									<Fragment key={student.id}>
										<tr
											className={
												expandedStudent === student.id
													? 'bg-blue-50/50 transition-colors'
													: 'hover:bg-gray-50 transition-colors'
											}
										>
											<td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
												{student.display_name}
											</td>
											<td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
												<a
													href={`mailto:${student.email}`}
													className="hover:text-blue-600 transition-colors"
												>
													{student.email}
												</a>
											</td>
											<td className="px-6 py-4 text-sm text-gray-500 flex flex-wrap gap-2">
												{student.courses.map(
													(c) => (
														<span
															key={c.course_id}
															className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100"
														>
															{c.course_title}{' '}
															<span className="ml-1 opacity-75">
																({c.completed}
																/{c.total})
															</span>
														</span>
													)
												)}
											</td>
											<td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
												<Button
													variant={
														expandedStudent ===
															student.id
															? 'primary'
															: 'secondary'
													}
													size="small"
													onClick={() =>
														setExpandedStudent(
															expandedStudent ===
																student.id
																? null
																: student.id
														)
													}
												>
													{expandedStudent ===
														student.id
														? __(
															'Close',
															'simple-lms-bridge'
														)
														: __(
															'Edit',
															'simple-lms-bridge'
														)}
												</Button>
											</td>
										</tr>
										{expandedStudent === student.id && (
											<tr>
												<td
													colSpan={4}
													className="p-0 border-b-4 border-blue-500"
												>
													<div className="bg-white p-6 md:px-10 border-t border-gray-100 shadow-inner">
														<div className="flex flex-col md:flex-row md:items-center justify-between mb-6 pb-4 border-b border-gray-100">
															<div>
																<h3 className="text-2xl font-bold text-gray-900 flex items-center gap-3">
																	{
																		student.display_name
																	}
																	<span className="text-xs font-normal px-2 py-1 bg-gray-100 text-gray-600 rounded-md">
																		ID:{' '}
																		{
																			student.id
																		}
																	</span>
																</h3>
																<p className="text-sm text-gray-500 mt-1">
																	{
																		student.email
																	}
																</p>
															</div>
															<div className="mt-4 md:mt-0 max-w-sm w-full bg-gray-50 p-3 rounded-lg border border-gray-200">
																<SelectControl
																	label={__(
																		'Enroll in New Course',
																		'simple-lms-bridge'
																	)}
																	value=""
																	options={[
																		{
																			label: __(
																				'— Select Course —',
																				'simple-lms-bridge'
																			),
																			value: '',
																		},
																		...allAvailableCourses
																			.filter(
																				(
																					ac
																				) =>
																					!student.courses.some(
																						(
																							sc
																						) =>
																							sc.course_id ===
																							ac.id
																					)
																			)
																			.map(
																				(
																					ac
																				) => ({
																					label: ac.title,
																					value: ac.id,
																				})
																			),
																	]}
																	onChange={(
																		val
																	) =>
																		enrollStudent(
																			student.id,
																			val
																		)
																	}
																	disabled={
																		enrolling[
																		student
																			.id
																		]
																	}
																	__nextHasNoMarginBottom={
																		true
																	}
																/>
															</div>
														</div>

														<TabPanel
															className="slms-student-tabs"
															activeClass="is-active font-semibold text-blue-600 border-b-2 border-blue-600"
															tabs={[
																{
																	name: 'progress',
																	title: __(
																		'Progress',
																		'simple-lms-bridge'
																	),
																	className:
																		'slms-tab-progress pb-3 px-4 transition-colors hover:text-blue-600',
																},
																{
																	name: 'profile',
																	title: __(
																		'Profile / Meta',
																		'simple-lms-bridge'
																	),
																	className:
																		'slms-tab-profile pb-3 px-4 transition-colors hover:text-blue-600',
																},
																{
																	name: 'history',
																	title: __(
																		'Completion History',
																		'simple-lms-bridge'
																	),
																	className:
																		'slms-tab-history pb-3 px-4 transition-colors hover:text-blue-600',
																},
															]}
														>
															{(tab) => {
																if (
																	tab.name ===
																	'progress'
																) {
																	return (
																		<div className="space-y-6 mt-6">
																			{student.courses.map(
																				(
																					course
																				) => (
																					<div
																						key={
																							course.course_id
																						}
																						className="bg-white rounded-xl p-5 border border-gray-200 shadow-sm"
																					>
																						<div className="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
																							<div>
																								<h4 className="font-semibold text-lg text-gray-800">
																									{
																										course.course_title
																									}
																								</h4>
																								{course.completed_at && (
																									<span className="inline-flex mt-1 text-xs text-green-700 bg-green-50 border border-green-200 px-2 py-1 rounded-md font-medium">
																										{sprintf(
																											__(
																												'Course completed: %s',
																												'simple-lms-bridge'
																											),
																											formatDate(
																												course.completed_at
																											)
																										)}
																									</span>
																								)}
																							</div>
																							<Button
																								variant="link"
																								isDestructive
																								className="text-red-500 hover:text-red-700 hover:bg-red-50 px-3 py-1 rounded-md transition-colors"
																								onClick={() =>
																									unenrollStudent(
																										student.id,
																										course.course_id
																									)
																								}
																								disabled={
																									enrolling[
																									student
																										.id
																									]
																								}
																							>
																								{__(
																									'Unenroll',
																									'simple-lms-bridge'
																								)}
																							</Button>
																						</div>
																						<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
																							{Object.keys(
																								course.lessons ||
																								{}
																							).map(
																								(
																									lessonId
																								) => {
																									const isCompleted =
																										getLessonStatus(
																											student.id,
																											course.course_id,
																											lessonId
																										);
																									const lessonTitle =
																										course
																											.lessons[
																											lessonId
																										]
																											.title ||
																										`Lesson #${lessonId}`;
																									const completedAt =
																										course
																											.lessons[
																											lessonId
																										]
																											.completed_at;
																									return (
																										<div
																											key={
																												lessonId
																											}
																											className="flex items-center justify-between bg-gray-50 p-3 rounded-lg border border-gray-200 transition-colors hover:border-blue-300"
																										>
																											<div className="flex items-center space-x-3 overflow-hidden flex-grow">
																												<CheckboxControl
																													checked={
																														isCompleted
																													}
																													onChange={() =>
																														toggleLocalCompletion(
																															student.id,
																															course.course_id,
																															lessonId
																														)
																													}
																													__nextHasNoMarginBottom={
																														true
																													}
																												/>
																												<div className="flex flex-col truncate">
																													<span
																														className="text-sm font-medium text-gray-700 truncate"
																														title={
																															lessonTitle
																														}
																													>
																														{
																															lessonTitle
																														}
																													</span>
																													{isCompleted &&
																														completedAt && (
																															<span className="text-xs text-gray-400 mt-0.5">
																																{formatDate(
																																	completedAt
																																)}
																															</span>
																														)}
																												</div>
																											</div>
																											<span
																												className={`flex-shrink-0 ml-2 px-2 py-0.5 text-[10px] uppercase font-bold rounded ${isCompleted
																													? 'bg-green-100 text-green-700'
																													: 'bg-gray-200 text-gray-600'
																													}`}
																											>
																												{isCompleted
																													? __(
																														'Done',
																														'simple-lms-bridge'
																													)
																													: __(
																														'Pending',
																														'simple-lms-bridge'
																													)}
																											</span>
																										</div>
																									);
																								}
																							)}
																						</div>
																					</div>
																				)
																			)}
																		</div>
																	);
																} else if (
																	tab.name ===
																	'profile'
																) {
																	const sMeta =
																		metaData[
																		student
																			.id
																		] || {};
																	return (
																		<div className="space-y-6 mt-6 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
																			<div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
																				<TextControl
																					label={__(
																						'Billing Address 1',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.billing_address_1 ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'billing_address_1',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<TextControl
																					label={__(
																						'Billing Address 2',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.billing_address_2 ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'billing_address_2',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<TextControl
																					label={__(
																						'City',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.billing_city ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'billing_city',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<div className="grid grid-cols-2 gap-4">
																					<TextControl
																						label={__(
																							'State',
																							'simple-lms-bridge'
																						)}
																						value={
																							sMeta.billing_state ||
																							''
																						}
																						onChange={(
																							v
																						) =>
																							handleMetaChange(
																								student.id,
																								'billing_state',
																								v
																							)
																						}
																						__nextHasNoMarginBottom={
																							true
																						}
																					/>
																					<TextControl
																						label={__(
																							'Postcode',
																							'simple-lms-bridge'
																						)}
																						value={
																							sMeta.billing_postcode ||
																							''
																						}
																						onChange={(
																							v
																						) =>
																							handleMetaChange(
																								student.id,
																								'billing_postcode',
																								v
																							)
																						}
																						__nextHasNoMarginBottom={
																							true
																						}
																					/>
																				</div>
																				<TextControl
																					label={__(
																						'Phone',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.billing_phone ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'billing_phone',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<TextControl
																					label={__(
																						'AALP Member',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.aalp_member ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'aalp_member',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<TextControl
																					label={__(
																						'Registration Date',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.registration_date ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'registration_date',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<TextControl
																					label={__(
																						'License Number',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.license_number ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'license_number',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<TextControl
																					label={__(
																						'Pro Exam Date',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.pro_exam_date ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'pro_exam_date',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																				<TextControl
																					label={__(
																						'Pro Exam Status',
																						'simple-lms-bridge'
																					)}
																					value={
																						sMeta.pro_exam_status ||
																						''
																					}
																					onChange={(
																						v
																					) =>
																						handleMetaChange(
																							student.id,
																							'pro_exam_status',
																							v
																						)
																					}
																					__nextHasNoMarginBottom={
																						true
																					}
																				/>
																			</div>
																		</div>
																	);
																} else if (
																	tab.name ===
																	'history'
																) {
																	return (
																		<HistoryTab
																			userId={
																				student.id
																			}
																		/>
																	);
																}
															}}
														</TabPanel>

														<div className="mt-8 flex justify-end">
															<Button
																variant="primary"
																className="px-8 py-2 shadow-md bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors disabled:opacity-50"
																onClick={() =>
																	handleUpdate(
																		student.id
																	)
																}
																isBusy={
																	saving
																}
																disabled={
																	(!dirtyData[
																		student
																			.id
																	] &&
																		!unsavedChanges) ||
																	saving
																}
															>
																{__(
																	'Save Changes',
																	'simple-lms-bridge'
																)}
															</Button>
														</div>
													</div>
												</td>
											</tr>
										)}
									</Fragment>
								))}
							</tbody>
						</table>

						{pages > 1 && (
							<div className="flex items-center justify-between bg-white px-6 py-4 border border-gray-200 rounded-lg shadow-sm">
								<Button
									variant="secondary"
									disabled={page <= 1}
									onClick={() => setPage(page - 1)}
									className="hover:bg-gray-50"
								>
									{__('← Previous', 'simple-lms-bridge')}
								</Button>
								<span className="text-sm text-gray-700">
									{__('Page', 'simple-lms-bridge')}{' '}
									<span className="font-semibold text-gray-900">
										{page}
									</span>{' '}
									{__('of', 'simple-lms-bridge')}{' '}
									<span className="font-semibold text-gray-900">
										{pages}
									</span>{' '}
									<span className="text-gray-400 ml-2">
										({total}{' '}
										{__(
											'students total',
											'simple-lms-bridge'
										)}
										)
									</span>
								</span>
								<Button
									variant="secondary"
									disabled={page >= pages}
									onClick={() => setPage(page + 1)}
									className="hover:bg-gray-50"
								>
									{__('Next →', 'simple-lms-bridge')}
								</Button>
							</div>
						)}
					</div>
				);
			})()}
		</div>
	);
};

export default StudentManager;
