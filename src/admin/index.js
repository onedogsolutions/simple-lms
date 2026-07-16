/**
 * SimpleLMS Admin – React entry point.
 *
 * Conditionally renders the appropriate component based on the current screen:
 * - Course edit screen  → CourseEditor
 * - Lesson edit screen  → LessonSettings
 * - Student Manager     → StudentManager
 * - Migration Tool      → MigrationTool
 * - Debug Log           → DebugLog
 *
 * @package
 */

import { createRoot, render } from '@wordpress/element';
import './index.css';

import CourseEditor from './components/CourseEditor';
import LessonSettings from './components/LessonSettings';
import StudentManager from './components/StudentManager';
import MigrationTool from './components/MigrationTool';
import DebugLog from './components/DebugLog';
import Analytics from './components/Analytics';

const mount = () => {
	const postId = window.slmsAdmin?.postId;
	const postType = window.slmsAdmin?.postType;
	const page = window.slmsAdmin?.page;

	let rootId = 'slms-admin-root';
	if ( page === 'slms-migration' ) {
		rootId = 'slms-migration-root';
	}
	
	const root = document.getElementById( rootId ) || document.getElementById( 'slms-admin-root' );
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
	} else if ( page === 'slms-migration' ) {
		App = <MigrationTool />;
	} else if ( page === 'slms-debug-log' ) {
		App = <DebugLog />;
	} else if ( page === 'slms-analytics' ) {
		App = <Analytics />;
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
