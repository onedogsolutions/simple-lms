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
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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
    }, []);

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
                                <>
                                    <tr key={student.id}>
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
                                        <tr
                                            key={`${student.id}-detail`}
                                            className="slms-detail-row"
                                        >
                                            <td colSpan={4}>
                                                {student.courses.map((course) => (
                                                    <div
                                                        key={course.course_id}
                                                        className="slms-course-detail"
                                                    >
                                                        <h4>{course.course_title}</h4>
                                                        <div className="slms-lesson-checkboxes">
                                                            { /* Render completed lessons */}
                                                            {Object.keys(course.lessons || {}).map(
                                                                (lessonId) => {
                                                                    const lid = parseInt(lessonId, 10);
                                                                    const tKey = `${student.id}-${course.course_id}-${lid}`;
                                                                    return (
                                                                        <CheckboxControl
                                                                            key={lid}
                                                                            label={`Lesson #${lid}`}
                                                                            checked={true}
                                                                            disabled={
                                                                                !!toggling[tKey]
                                                                            }
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
                                </>
                            ))}
                        </tbody>
                    </table>

                    { /* ── Pagination ────────────────────────────── */}
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
