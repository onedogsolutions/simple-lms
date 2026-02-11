/**
 * LessonSettings – Lesson type, video/quiz pickers, and timer.
 *
 * Renders inside the Lesson CPT meta box.
 *
 * @package SimpleLMS
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
 * @param {number} props.postId  Current lesson post ID.
 * @return {JSX.Element}
 */
const LessonSettings = ({ postId }) => {
    // ── State ──────────────────────────────────────────────────────
    const [lessonType, setLessonType] = useState('');
    const [prestoVideo, setPrestoVideo] = useState(0);
    const [gravityForm, setGravityForm] = useState(0);
    const [quizTimer, setQuizTimer] = useState(0);
    const [videos, setVideos] = useState([]);
    const [forms, setForms] = useState([]);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);
    const [loading, setLoading] = useState(true);

    // ── Load initial data ─────────────────────────────────────────
    useEffect(() => {
        const load = async () => {
            try {
                const [videosRes, formsRes, postRes] = await Promise.all([
                    apiFetch({ path: '/simple-lms/v1/videos' }),
                    apiFetch({ path: '/simple-lms/v1/forms' }),
                    apiFetch({ path: `/wp/v2/lms-lessons/${postId}` }),
                ]);

                setVideos(videosRes);
                setForms(formsRes);

                const meta = postRes.meta || {};
                setLessonType(meta._lms_lesson_type || '');
                setPrestoVideo(meta._lms_presto_video || 0);
                setGravityForm(meta._lms_gravity_form || 0);
                setQuizTimer(meta._lms_quiz_timer || 0);
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
                path: `/wp/v2/lms-lessons/${postId}`,
                method: 'POST',
                data: {
                    meta: {
                        _lms_lesson_type: lessonType,
                        _lms_presto_video: parseInt(prestoVideo, 10) || 0,
                        _lms_gravity_form: parseInt(gravityForm, 10) || 0,
                        _lms_quiz_timer: parseInt(quizTimer, 10) || 0,
                    },
                },
            });
            setNotice({
                status: 'success',
                message: __('Lesson settings saved.', 'simple-lms-bridge'),
            });
        } catch (err) {
            setNotice({ status: 'error', message: err.message });
        } finally {
            setSaving(false);
        }
    }, [postId, lessonType, prestoVideo, gravityForm, quizTimer]);

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

            <PanelBody
                title={__('Lesson Type', 'simple-lms-bridge')}
                initialOpen={true}
            >
                <SelectControl
                    label={__('Content Type', 'simple-lms-bridge')}
                    value={lessonType}
                    options={[
                        { label: __('— None —', 'simple-lms-bridge'), value: '' },
                        { label: __('Video', 'simple-lms-bridge'), value: 'video' },
                        { label: __('Quiz', 'simple-lms-bridge'), value: 'quiz' },
                    ]}
                    onChange={setLessonType}
                />

                { /* ── Video Picker ──────────────────────────────── */}
                {lessonType === 'video' && (
                    <SelectControl
                        label={__('Presto Player Video', 'simple-lms-bridge')}
                        value={prestoVideo}
                        options={[
                            { label: __('— Select Video —', 'simple-lms-bridge'), value: 0 },
                            ...videos.map((v) => ({
                                label: v.title,
                                value: v.id,
                            })),
                        ]}
                        onChange={(val) => setPrestoVideo(parseInt(val, 10))}
                    />
                )}

                { /* ── Quiz Picker + Timer ────────────────────────── */}
                {lessonType === 'quiz' && (
                    <>
                        <SelectControl
                            label={__('Gravity Form (Quiz)', 'simple-lms-bridge')}
                            value={gravityForm}
                            options={[
                                { label: __('— Select Form —', 'simple-lms-bridge'), value: 0 },
                                ...forms.map((f) => ({
                                    label: f.title,
                                    value: f.id,
                                })),
                            ]}
                            onChange={(val) => setGravityForm(parseInt(val, 10))}
                        />
                        <TextControl
                            label={__('Timer (minutes)', 'simple-lms-bridge')}
                            help={__('0 = no time limit', 'simple-lms-bridge')}
                            type="number"
                            min={0}
                            value={quizTimer}
                            onChange={(val) =>
                                setQuizTimer(parseInt(val, 10) || 0)
                            }
                        />
                    </>
                )}
            </PanelBody>

            <div className="slms-save-bar">
                <Button
                    variant="primary"
                    isBusy={saving}
                    disabled={saving}
                    onClick={handleSave}
                >
                    {saving
                        ? __('Saving…', 'simple-lms-bridge')
                        : __('Save Lesson Settings', 'simple-lms-bridge')}
                </Button>
            </div>
        </>
    );
};

export default LessonSettings;
