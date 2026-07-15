/**
 * SimpleLMS Admin – React entry point.
 *
 * Conditionally renders the appropriate component based on the current screen:
 * - Course edit screen  → CourseEditor
 * - Lesson edit screen  → LessonSettings
 * - Student Manager     → StudentManager
 *
 * @package
 */

import { createRoot, render } from '@wordpress/element';
import './index.css';

import CourseEditor from './components/CourseEditor';
import LessonSettings from './components/LessonSettings';
import StudentManager from './components/StudentManager';

const mount = () => {
	const postId = window.slmsAdmin?.postId;
	const postType = window.slmsAdmin?.postType;
	const page = window.slmsAdmin?.page;

	const root = document.getElementById( 'slms-admin-root' );
	if ( ! root ) {
		return;
	}

	let App;

	if ( postType === 'slms_course' ) {
		App = <CourseEditor postId={ postId } />;
	} else if ( postType === 'slms_lesson' ) {
		App = <LessonSettings postId={ postId } />;
	} else if ( page === 'slms-students' ) {
		App = <StudentManager />;
	}

	if ( ! App ) {
		return;
	}

	if ( createRoot ) {
		createRoot( root ).render( App );
	} else {
		render( App, root );
	}
};

// Wait for DOM before mounting.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
