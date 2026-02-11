/**
 * CourseEditor – Lesson Sorter, Certificate Dropdown, Access Days, PMPro Levels.
 *
 * Renders inside the Course CPT meta box.
 *
 * @package SimpleLMS
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import {
    PanelBody,
    PanelRow,
    SelectControl,
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

/**
 * CourseEditor component.
 *
 * @param {Object} props
 * @param {number} props.postId  Current course post ID.
 * @return {JSX.Element}
 */
const CourseEditor = ({ postId }) => {
    // ── State ──────────────────────────────────────────────────────
    const [lessonOrder, setLessonOrder] = useState([]);
    const [allLessons, setAllLessons] = useState([]);
    const [forms, setForms] = useState([]);
    const [certificateForm, setCertificateForm] = useState(0);
    const [accessDays, setAccessDays] = useState(0);
    const [pmproLevels, setPmproLevels] = useState([]);
    const [allPMProLevels, setAllPMProLevels] = useState([]);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);
    const [loading, setLoading] = useState(true);

    // ── Load initial data ─────────────────────────────────────────
    useEffect(() => {
        const load = async () => {
            try {
                const [lessonsRes, formsRes, levelsRes, postRes] = await Promise.all([
                    apiFetch({ path: '/simple-lms/v1/lessons' }),
                    apiFetch({ path: '/simple-lms/v1/forms' }),
                    apiFetch({ path: '/simple-lms/v1/pmpro-levels' }),
                    apiFetch({ path: `/wp/v2/lms-courses/${postId}` }),
                ]);

                setAllLessons(lessonsRes);
                setForms(formsRes);
                setAllPMProLevels(levelsRes);

                const meta = postRes.meta || {};
                setLessonOrder(meta._simple_lms_order || []);
                setCertificateForm(meta._lms_certificate_form || 0);
                setAccessDays(meta._lms_access_days || 0);
                setPmproLevels(meta._lms_pmpro_levels || []);
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
            await apiFetch({
                path: `/wp/v2/lms-courses/${postId}`,
                method: 'POST',
                data: {
                    meta: {
                        _simple_lms_order: lessonOrder,
                        _lms_certificate_form: parseInt(certificateForm, 10) || 0,
                        _lms_access_days: parseInt(accessDays, 10) || 0,
                        _lms_pmpro_levels: pmproLevels,
                    },
                },
            });
            setNotice({ status: 'success', message: __('Course settings saved.', 'simple-lms-bridge') });
        } catch (err) {
            setNotice({ status: 'error', message: err.message });
        } finally {
            setSaving(false);
        }
    }, [postId, lessonOrder, certificateForm, accessDays, pmproLevels]);

    // ── Auto-populate access days from PMPro ──────────────────────
    useEffect(() => {
        if (!pmproLevels.length || !allPMProLevels.length) return;

        // Find the first level with an expiration.
        const levelWithExp = allPMProLevels.find(
            (l) => pmproLevels.includes(l.id) && l.expiration_days > 0
        );

        if (levelWithExp && accessDays !== levelWithExp.expiration_days) {
            setAccessDays(levelWithExp.expiration_days);
        }
    }, [pmproLevels, allPMProLevels]); // eslint-disable-line react-hooks/exhaustive-deps

    // Determine if PMPro is driving the expiration.
    const activePMProExpiration = allPMProLevels.find(
        (l) => pmproLevels.includes(l.id) && l.expiration_days > 0 && l.expiration_days === accessDays
    );

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
                title={__('Lesson Order', 'simple-lms-bridge')}
                initialOpen={true}
            >
                {lessonOrder.length === 0 && (
                    <p className="slms-empty">
                        {__('No lessons added yet. Use the dropdown below to add lessons.', 'simple-lms-bridge')}
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

                {availableLessons.length > 0 && (
                    <PanelRow>
                        <SelectControl
                            label={__('Add Lesson', 'simple-lms-bridge')}
                            value=""
                            options={[
                                { label: __('— Select —', 'simple-lms-bridge'), value: '' },
                                ...availableLessons.map((l) => ({
                                    label: l.title,
                                    value: l.id,
                                })),
                            ]}
                            onChange={addLesson}
                        />
                    </PanelRow>
                )}
            </PanelBody>

            { /* ── Certificate Form ──────────────────────────────── */}
            <PanelBody
                title={__('Certificate', 'simple-lms-bridge')}
                initialOpen={false}
            >
                <SelectControl
                    label={__('Certificate Gravity Form', 'simple-lms-bridge')}
                    value={certificateForm}
                    options={[
                        { label: __('— None —', 'simple-lms-bridge'), value: 0 },
                        ...forms.map((f) => ({
                            label: f.title,
                            value: f.id,
                        })),
                    ]}
                    onChange={(val) => setCertificateForm(parseInt(val, 10))}
                />
            </PanelBody>

            { /* ── Access Days ────────────────────────────────────── */}
            <PanelBody
                title={__('Access Control', 'simple-lms-bridge')}
                initialOpen={false}
            >
                {activePMProExpiration && (
                    <Notice status="info" isDismissible={false} className="slms-pmpro-notice">
                        {sprintf(
                            __('Course access is set to %d days based on the "%s" PMPro level.', 'simple-lms-bridge'),
                            activePMProExpiration.expiration_days,
                            activePMProExpiration.name
                        )}
                    </Notice>
                )}
                <TextControl
                    label={__('Access Duration (days)', 'simple-lms-bridge')}
                    help={__('0 = unlimited access', 'simple-lms-bridge')}
                    type="number"
                    min={0}
                    value={accessDays}
                    onChange={(val) => setAccessDays(parseInt(val, 10) || 0)}
                    disabled={!!activePMProExpiration}
                />
            </PanelBody>

            { /* ── PMPro Membership Levels ─────────────────────────── */}
            <PanelBody
                title={__('PMPro Enrollment', 'simple-lms-bridge')}
                initialOpen={false}
            >
                <p className="slms-panel-desc">
                    {__('Select membership levels that grant access to this course.', 'simple-lms-bridge')}
                </p>
                <PMProLevels
                    selectedLevels={pmproLevels}
                    onChange={setPmproLevels}
                />
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
