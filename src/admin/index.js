/**
 * SimpleLMS Admin – React entry point.
 *
 * Conditionally renders the appropriate component based on the current screen:
 * - Course edit screen  → CourseEditor
 * - Lesson edit screen  → LessonSettings
 * - Student Manager page → StudentManager
 *
 * @package SimpleLMS
 */

import { createRoot, render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import CourseEditor from './components/CourseEditor';
import LessonSettings from './components/LessonSettings';
import StudentManager from './components/StudentManager';

import './index.css';

// Configure apiFetch to use the nonce provided by PHP.
apiFetch.use(apiFetch.createNonceMiddleware(window.slmsAdmin?.nonce));

/**
 * Determine which component to render and mount it.
 */
const mount = () => {
    const root = document.getElementById('slms-admin-root');
    if (!root) {
        return;
    }

    const { postType, postId, page } = window.slmsAdmin || {};

    let App;

    if (page === 'slms-students') {
        App = <StudentManager />;
    } else if (postType === 'lms_course' && postId) {
        App = <CourseEditor postId={parseInt(postId, 10)} />;
    } else if (postType === 'lms_lesson' && postId) {
        App = <LessonSettings postId={parseInt(postId, 10)} />;
    } else {
        return;
    }

    // Use createRoot (React 18+) if available, otherwise fall back to render.
    if (typeof createRoot === 'function') {
        createRoot(root).render(App);
    } else {
        render(App, root);
    }
};

// Wait for DOM before mounting.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
