/**
 * StudentManager – Searchable dashboard for student progress management.
 *
 * Renders on the top-level Students admin page.
 *
 * @package SimpleLMS
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import {
    SearchControl,
    Button,
    Spinner,
    Notice,
    CheckboxControl,
    ProgressBar,
    SelectControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * StudentManager component.
 *
 * @return {JSX.Element}
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
    const [toggling, setToggling] = useState({}); // { 'userId-courseId-lessonId': true }
    const [allAvailableCourses, setAllAvailableCourses] = useState([]);
    const [enrolling, setEnrolling] = useState({}); // { userId: true }

    // Migration state
    const [migrationStatus, setMigrationStatus] = useState({
        pending: 0,
        total: 0,
        active: false,
        complete: false
    });

    // ── Fetch students ────────────────────────────────────────────
    const fetchStudents = useCallback(async (s, p) => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                page: p,
                per_page: 20,
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
        } catch (err) {
            setNotice({ status: 'error', message: err.message });
        } finally {
            setLoading(false);
        }
    }, []);

    // Initial load.
    useEffect(() => {
        fetchStudents('', 1);
        checkMigrationStatus();
        fetchAvailableCourses();
    }, []);

    const fetchAvailableCourses = async () => {
        try {
            const res = await apiFetch({ path: '/simple-lms/v1/relationships/courses' });
            setAllAvailableCourses(res || []);
        } catch (err) {
            console.error('Failed to fetch courses', err);
        }
    };

    const checkMigrationStatus = async () => {
        try {
            const res = await apiFetch({ path: '/simple-lms/v1/migration/status' });
            if (res.pending > 0) {
                setMigrationStatus(prev => ({
                    ...prev,
                    pending: res.pending,
                    total: prev.total || res.pending // Set initial total if not set
                }));

                // Auto-start if 'migrate' param is present.
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('migrate') === '1') {
                    startMigration(res.pending);
                }
            }
        } catch (err) {
            console.error('Failed to check migration status', err);
        }
    };

    const startMigration = async (initialPending) => {
        setMigrationStatus(prev => ({
            ...prev,
            active: true,
            total: initialPending,
            pending: initialPending
        }));

        let currentPending = initialPending;

        while (currentPending > 0) {
            try {
                const res = await apiFetch({
                    path: '/simple-lms/v1/migration/migrate',
                    method: 'POST'
                });
                currentPending = res.pending;
                setMigrationStatus(prev => ({ ...prev, pending: currentPending }));

                if (currentPending === 0) {
                    setMigrationStatus(prev => ({ ...prev, active: false, complete: true }));
                    fetchStudents(search, page);
                }
            } catch (err) {
                setNotice({ status: 'error', message: __('Migration failed: ', 'simple-lms-bridge') + err.message });
                setMigrationStatus(prev => ({ ...prev, active: false }));
                break;
            }
        }
    };

    // Search with debounce.
    useEffect(() => {
        const timeout = setTimeout(() => {
            setPage(1);
            fetchStudents(search, 1);
        }, 400);
        return () => clearTimeout(timeout);
    }, [search, fetchStudents]);

    // Page change.
    useEffect(() => {
        fetchStudents(search, page);
    }, [page]);

    // ── Toggle completion ─────────────────────────────────────────
    const toggleCompletion = async (userId, courseId, lessonId, currentlyCompleted) => {
        const key = `${userId}-${courseId}-${lessonId}`;
        setToggling((prev) => ({ ...prev, [key]: true }));

        try {
            await apiFetch({
                path: '/simple-lms/v1/progress',
                method: 'POST',
                data: {
                    user_id: userId,
                    course_id: courseId,
                    lesson_id: lessonId,
                    completed: !currentlyCompleted,
                },
            });
            // Refresh current student data.
            fetchStudents(search, page);
        } catch (err) {
            setNotice({ status: 'error', message: err.message });
        } finally {
            setToggling((prev) => {
                const next = { ...prev };
                delete next[key];
                return next;
            });
        }
    };

    // ── Enrollment Management ─────────────────────────────────────
    const enrollStudent = async (userId, courseId) => {
        if (!courseId) return;
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
        if (!confirm(__('Are you sure you want to unenroll this student from this course? This will NOT delete their progress, but they will lose access.', 'simple-lms-bridge'))) {
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

    return (
        <div className="slms-student-manager">
            {notice && (
                <Notice
                    status={notice.status}
                    isDismissible
                    onDismiss={() => setNotice(null)}
                >
                    {notice.message}
                </Notice>
            )}

            {migrationStatus.active && (
                <Notice status="info" __nextHasNoMargin>
                    <div className="slms-migration-progress">
                        <p>
                            {__('Migrating WP Complete data...', 'simple-lms-bridge')}
                            {' '}
                            <strong>{migrationStatus.total - migrationStatus.pending} / {migrationStatus.total}</strong>
                        </p>
                        <ProgressBar
                            value={migrationStatus.total - migrationStatus.pending}
                            max={migrationStatus.total}
                        />
                    </div>
                </Notice>
            )}

            {migrationStatus.complete && (
                <Notice status="success" isDismissible onDismiss={() => setMigrationStatus(prev => ({ ...prev, complete: false }))}>
                    {__('Migration complete!', 'simple-lms-bridge')}
                </Notice>
            )}

            {!migrationStatus.active && migrationStatus.pending > 0 && !migrationStatus.complete && (
                <Notice status="warning">
                    <p>
                        {sprintf(
                            __('There are still %d users with WP Complete data pending migration.', 'simple-lms-bridge'),
                            migrationStatus.pending
                        )}
                        {' '}
                        <Button variant="primary" onClick={() => startMigration(migrationStatus.pending)}>
                            {__('Start Migration', 'simple-lms-bridge')}
                        </Button>
                    </p>
                </Notice>
            )}

            <SearchControl
                label={__('Search students', 'simple-lms-bridge')}
                value={search}
                onChange={setSearch}
                className="slms-search"
            />

            {loading ? (
                <Spinner />
            ) : students.length === 0 ? (
                <p className="slms-empty">
                    {__('No students with LMS progress found.', 'simple-lms-bridge')}
                </p>
            ) : (
                <>
                    <table className="widefat slms-student-table">
                        <thead>
                            <tr>
                                <th>{__('Student', 'simple-lms-bridge')}</th>
                                <th>{__('Email', 'simple-lms-bridge')}</th>
                                <th>{__('Courses', 'simple-lms-bridge')}</th>
                                <th>{__('Actions', 'simple-lms-bridge')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.map((student) => (
                                <frament key={student.id}>
                                    <tr>
                                        <td>{student.display_name}</td>
                                        <td>{student.email}</td>
                                        <td>
                                            {student.courses.map((c) => (
                                                <span key={c.course_id} className="slms-course-badge">
                                                    {c.course_title}
                                                    <small>
                                                        {' '}
                                                        ({c.completed}/{c.total})
                                                    </small>
                                                </span>
                                            ))}
                                        </td>
                                        <td>
                                            <Button
                                                variant="secondary"
                                                size="small"
                                                onClick={() =>
                                                    setExpandedStudent(
                                                        expandedStudent === student.id
                                                            ? null
                                                            : student.id
                                                    )
                                                }
                                            >
                                                {expandedStudent === student.id
                                                    ? __('Collapse', 'simple-lms-bridge')
                                                    : __('Details', 'simple-lms-bridge')}
                                            </Button>
                                        </td>
                                    </tr>
                                    {expandedStudent === student.id && (
                                        <tr className="slms-detail-row">
                                            <td colSpan={4}>
                                                <div className="slms-enrollment-manager">
                                                    <h4>{__('Course Enrollment', 'simple-lms-bridge')}</h4>
                                                    <div className="slms-enroll-controls">
                                                        <SelectControl
                                                            label={__('Enroll in Course', 'simple-lms-bridge')}
                                                            value=""
                                                            options={[
                                                                { label: __('— Select Course —', 'simple-lms-bridge'), value: '' },
                                                                ...allAvailableCourses
                                                                    .filter(ac => !student.courses.some(sc => sc.course_id === ac.id))
                                                                    .map(ac => ({ label: ac.title, value: ac.id }))
                                                            ]}
                                                            onChange={(val) => enrollStudent(student.id, val)}
                                                            disabled={enrolling[student.id]}
                                                        />
                                                    </div>
                                                </div>

                                                {student.courses.map((course) => (
                                                    <div
                                                        key={course.course_id}
                                                        className="slms-course-detail"
                                                    >
                                                        <div className="slms-course-detail-header">
                                                            <h4>{course.course_title}</h4>
                                                            <Button
                                                                variant="link"
                                                                isDestructive
                                                                onClick={() => unenrollStudent(student.id, course.course_id)}
                                                                disabled={enrolling[student.id]}
                                                            >
                                                                {__('Unenroll', 'simple-lms-bridge')}
                                                            </Button>
                                                        </div>
                                                        <div className="slms-lesson-checkboxes">
                                                            {Object.keys(course.lessons || {}).map(
                                                                (lessonId) => {
                                                                    const lid = parseInt(lessonId, 10);
                                                                    const tKey = `${student.id}-${course.course_id}-${lid}`;
                                                                    return (
                                                                        <CheckboxControl
                                                                            key={lid}
                                                                            label={course.lessons[lessonId].title || `Lesson #${lid}`}
                                                                            checked={true}
                                                                            disabled={!!toggling[tKey]}
                                                                            onChange={() =>
                                                                                toggleCompletion(
                                                                                    student.id,
                                                                                    course.course_id,
                                                                                    lid,
                                                                                    true
                                                                                )
                                                                            }
                                                                        />
                                                                    );
                                                                }
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </td>
                                        </tr>
                                    )}
                                </frament>
                            ))}
                        </tbody>
                    </table>

                    {pages > 1 && (
                        <div className="slms-pagination">
                            <Button
                                variant="secondary"
                                disabled={page <= 1}
                                onClick={() => setPage(page - 1)}
                            >
                                {__('← Previous', 'simple-lms-bridge')}
                            </Button>
                            <span className="slms-page-info">
                                {page} / {pages} ({total}{' '}
                                {__('students', 'simple-lms-bridge')})
                            </span>
                            <Button
                                variant="secondary"
                                disabled={page >= pages}
                                onClick={() => setPage(page + 1)}
                            >
                                {__('Next →', 'simple-lms-bridge')}
                            </Button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
};

export default StudentManager;
