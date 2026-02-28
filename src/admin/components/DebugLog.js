/**
 * DebugLog – Admin log viewer for SimpleLMS migration diagnostics.
 *
 * @package
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const DebugLog = () => {
	const [ log, setLog ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ filter, setFilter ] = useState( 'all' );
	const logRef = useRef( null );

	const fetchLog = async () => {
		setLoading( true );
		try {
			const res = await apiFetch( { path: '/simple-lms/v1/debug-log' } );
			setLog( res.log || '' );
		} catch ( err ) {
			setLog(
				__( 'Failed to load log:', 'simple-lms-bridge' ) + err.message
			);
		} finally {
			setLoading( false );
		}
	};

	const clearLog = async () => {
		if (
			/* eslint-disable no-alert */
			! window.confirm(
				__(
					'Are you sure you want to clear the log?',
					'simple-lms-bridge'
				)
			)
			/* eslint-enable no-alert */
		) {
			return;
		}
		try {
			await apiFetch( {
				path: '/simple-lms/v1/debug-log',
				method: 'DELETE',
			} );
			setLog( '' );
		} catch ( err ) {
			// silently fail
		}
	};

	useEffect( () => {
		fetchLog();
	}, [] );

	useEffect( () => {
		if ( logRef.current ) {
			logRef.current.scrollTop = logRef.current.scrollHeight;
		}
	}, [ log, filter ] );

	const lines = log ? log.split( '\n' ).filter( Boolean ) : [];

	const filteredLines =
		filter === 'all'
			? lines
			: lines.filter( ( line ) => {
					const upper = '[' + filter.toUpperCase() + ']';
					return line.includes( upper );
			  } );

	const errorCount = lines.filter( ( l ) => l.includes( '[ERROR]' ) ).length;
	const warnCount = lines.filter( ( l ) => l.includes( '[WARN]' ) ).length;

	const getLineColor = ( line ) => {
		if ( line.includes( '[ERROR]' ) ) {
			return 'text-red-400';
		}
		if ( line.includes( '[WARN]' ) ) {
			return 'text-yellow-400';
		}
		if ( line.includes( '[DEBUG]' ) ) {
			return 'text-gray-500';
		}
		return 'text-green-400';
	};

	let logContent;
	if ( loading ) {
		logContent = (
			<div className="flex justify-center p-12">
				<Spinner />
			</div>
		);
	} else if ( lines.length === 0 ) {
		logContent = (
			<div className="bg-gray-900 rounded-lg p-12 text-center text-gray-500 border border-gray-700">
				<p className="text-lg font-medium">
					{ __( 'No log entries yet.', 'simple-lms-bridge' ) }
				</p>
				<p className="text-sm mt-2">
					{ __(
						'Run a migration to generate diagnostic output.',
						'simple-lms-bridge'
					) }
				</p>
			</div>
		);
	} else {
		logContent = (
			<div
				ref={ logRef }
				className="bg-gray-900 rounded-lg p-4 font-mono text-xs leading-relaxed overflow-y-auto border border-gray-700"
				style={ { maxHeight: '600px' } }
			>
				{ filteredLines.map( ( line, idx ) => (
					<div
						key={ idx }
						className={ `py-0.5 ${ getLineColor( line ) }` }
					>
						{ line }
					</div>
				) ) }
			</div>
		);
	}

	return (
		<div className="max-w-7xl mx-auto py-8">
			<div className="bg-white rounded-lg shadow-sm p-8 mb-6 border border-gray-200">
				<div className="flex justify-between items-center">
					<div>
						<h1 className="text-3xl font-bold text-gray-900 mb-2">
							{ __( 'SimpleLMS Debug Log', 'simple-lms-bridge' ) }
						</h1>
						<p className="text-gray-600">
							{ __(
								'View migration and system diagnostic logs.',
								'simple-lms-bridge'
							) }
						</p>
					</div>
					<div className="flex gap-3">
						<Button
							variant="secondary"
							onClick={ fetchLog }
							disabled={ loading }
							className="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md"
						>
							{ __( 'Refresh', 'simple-lms-bridge' ) }
						</Button>
						<Button
							variant="secondary"
							isDestructive
							onClick={ clearLog }
							disabled={ loading || ! log }
							className="border border-red-300 text-red-600 px-4 py-2 rounded-md"
						>
							{ __( 'Clear Log', 'simple-lms-bridge' ) }
						</Button>
					</div>
				</div>
			</div>

			{ /* Stats bar */ }
			<div className="flex gap-4 mb-4">
				<div className="bg-white border border-gray-200 rounded-lg px-4 py-3 shadow-sm">
					<span className="text-sm text-gray-500">
						{ __( 'Total Lines', 'simple-lms-bridge' ) }
					</span>
					<span className="ml-2 font-semibold text-gray-900">
						{ lines.length }
					</span>
				</div>
				{ errorCount > 0 && (
					<div className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 shadow-sm">
						<span className="text-sm text-red-600">
							{ __( 'Errors', 'simple-lms-bridge' ) }
						</span>
						<span className="ml-2 font-semibold text-red-700">
							{ errorCount }
						</span>
					</div>
				) }
				{ warnCount > 0 && (
					<div className="bg-yellow-100 border border-yellow-600 rounded-lg px-4 py-3 shadow-sm">
						<span className="text-sm text-yellow-700">
							{ __( 'Warnings', 'simple-lms-bridge' ) }
						</span>
						<span className="ml-2 font-semibold text-yellow-800">
							{ warnCount }
						</span>
					</div>
				) }
			</div>

			{ /* Filter bar */ }
			<div className="flex gap-2 mb-4">
				{ [ 'all', 'error', 'warn', 'info', 'debug' ].map(
					( level ) => (
						<button
							key={ level }
							type="button"
							onClick={ () => setFilter( level ) }
							className={ `px-3 py-1 text-xs font-medium rounded-full border cursor-pointer transition-colors ${
								filter === level
									? 'bg-gray-800 text-white border-gray-800'
									: 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'
							}` }
						>
							{ level.charAt( 0 ).toUpperCase() +
								level.slice( 1 ) }
						</button>
					)
				) }
			</div>

			{ /* Log output */ }
			{ logContent }
		</div>
	);
};

export default DebugLog;
