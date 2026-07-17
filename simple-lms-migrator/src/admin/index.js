/**
 * SimpleLMS Migrator – React entry point.
 *
 * Conditionally renders the appropriate component based on the current page:
 * - Migration Tool → MigrationTool
 * - Debug Log      → DebugLog
 *
 * @package
 */

import { createRoot, render } from '@wordpress/element';
import './index.css';

import MigrationTool from './components/MigrationTool';
import DebugLog from './components/DebugLog';

const mount = () => {
	const page = window.slmsMigrator?.page;

	let rootId = 'slms-migration-root';
	if ( page === 'slms-debug-log' ) {
		rootId = 'slms-debug-log-root';
	}

	const root = document.getElementById( rootId );
	if ( ! root ) {
		return;
	}

	let App;

	if ( page === 'slms-migration' ) {
		App = <MigrationTool />;
	} else if ( page === 'slms-debug-log' ) {
		App = <DebugLog />;
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
