/**
 * MigrationTool – Three-Phase Migration UI.
 *
 * Phase 1: CPT Migration (Legacy Pods -> New CPTs)
 * Phase 2: Student Progress Migration (WPComplete -> New DB)
 * Phase 3: Historical Certificate Migration (Gravity Forms)
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Notice, Spinner, ProgressBar } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const MigrationTool = () => {
	const [ status, setStatus ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ migrating, setMigrating ] = useState( false );
	const [ phase, setPhase ] = useState( 0 ); // 0 = idle, 1 = content, 2 = progress, 3 = history

	// Track total counts to allow percentage calculation
	const [ totals, setTotals ] = useState( { content: 0, progress: 0, history: 0 } );
	const [ error, setError ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const loadStatus = async () => {
		try {
			const res = await apiFetch( {
				path: '/simple-lms/v1/migration/status',
			} );
			setStatus( res );
			setTotals( ( prev ) => ( {
				content:
					prev.content > res.content.pending
						? prev.content
						: res.content.pending,
				progress: res.progress.total || (prev.progress > res.progress.pending ? prev.progress : res.progress.pending),
				history:
					prev.history > (res.history?.pending || 0)
						? prev.history
						: (res.history?.pending || 0),
			} ) );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		loadStatus();
	}, [] );

	const runMigration = async ( type ) => {
		setMigrating( true );
		setError( null );
		setNotice( null );
		
		let activePhase = 0;
		if (type === 'content') activePhase = 1;
		if (type === 'progress') activePhase = 2;
		if (type === 'history') activePhase = 3;

		setPhase( activePhase );

		let endpoint = '';
		if (type === 'content') endpoint = '/simple-lms/v1/migration/cpts';
		if (type === 'progress') endpoint = '/simple-lms/v1/migration/progress';
		if (type === 'history') endpoint = '/simple-lms/v1/migration/history';

		try {
			let pending =
				type === 'content'
					? status.content.pending
					: type === 'progress' 
						? status.progress.pending
						: status.history.pending;

			let offset = 0;

			while ( pending > 0 ) {
				let payload = { limit: type === 'content' ? 5 : 10 };
				if (type === 'progress') {
					payload = { batch_size: 50, offset: offset };
				}

				const res = await apiFetch( {
					path: endpoint,
					method: 'POST',
					data: payload,
				} );

				if ( ! res.success ) {
					throw new Error(
						__(
							'Migration failed unexpectedly.',
							'simple-lms-bridge'
						)
					);
				}

				if (type === 'progress') {
					offset += 50;
					const total = res.total_count || totals.progress || 1;
					// Use processed_count / total logic directly or update pending safely
					// If total is known, pending is just total minus offset.
					pending = Math.max(0, total - offset);
					
					setStatus( ( prev ) => ( {
						...prev,
						[ type ]: { pending: pending },
					} ) );

					if (offset >= total || res.processed_count === 0 && pending === 0) {
						break;
					}
				} else {
					pending = res.pending;
					setStatus( ( prev ) => ( {
						...prev,
						[ type ]: { pending: res.pending },
					} ) );
				}
			}

			let noticeMessage = '';
			if (type === 'content') noticeMessage = 'Phase 1: Content migration completed successfully.';
			if (type === 'progress') noticeMessage = 'Phase 2: Student Progress migration completed successfully.';
			if (type === 'history') noticeMessage = 'Phase 3: Historical Certificates migration completed successfully.';

			setNotice( __( noticeMessage, 'simple-lms-bridge' ) );
			await loadStatus();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setMigrating( false );
			setPhase( 0 );
		}
	};

	const clearCache = () => {
		try {
			window.localStorage.clear();
			window.sessionStorage.clear();
		} catch (e) {
			// Ignore if access is blocked
		}
		
		// Reset state entirely and force a hard reload of data
		setStatus(null);
		setLoading(true);
		setPhase(0);
		setMigrating(false);
		setTotals({ content: 0, progress: 0, history: 0 });
		setError(null);
		setNotice(__('Local cache cleared. Reloading status from server...', 'simple-lms-bridge'));
		loadStatus();
	};

	if ( loading ) {
		return (
			<div className="flex justify-center p-12">
				<Spinner />
			</div>
		);
	}

	const contentPending = status?.content?.pending || 0;
	const progressPending = status?.progress?.pending || 0;
	const historyPending = status?.history?.pending || 0;

	// Determine explicit states based on exact conditions
	// 'locked', 'pending', 'running', 'completed'
	const contentState = migrating && phase === 1 ? 'running' 
						: contentPending === 0 ? 'completed' 
						: 'pending';

	const progressState = contentState !== 'completed' ? 'locked'
						: migrating && phase === 2 ? 'running'
						: progressPending === 0 ? 'completed'
						: 'pending';

	const historyState = progressState !== 'completed' ? 'locked'
						: migrating && phase === 3 ? 'running'
						: historyPending === 0 ? 'completed'
						: 'pending';

	// Safe Progress Calculation to prevent over 100% bugs
	const calculateProgress = (total, pending, state) => {
		if (state === 'completed') return 100;
		if (total <= 0) return pending === 0 ? 100 : 0;
		const progress = ((total - pending) / total) * 100;
		return Math.max(0, Math.min(progress, 100)); // Clamp between 0 and 100
	};

	const contentProgress = calculateProgress(totals.content, contentPending, contentState);
	const progressProgress = calculateProgress(totals.progress, progressPending, progressState);
	const historyProgress = calculateProgress(totals.history, historyPending, historyState);

	return (
		<div className="max-w-4xl mx-auto py-8">
			<div className="bg-white rounded-lg shadow-sm p-8 mb-8 border border-gray-200 flex justify-between items-start">
				<div>
					<h1 className="text-3xl font-bold text-gray-900 mb-3">
						{ __( 'SimpleLMS Migration Hub', 'simple-lms-bridge' ) }
					</h1>
					<p className="text-gray-600">
						{ __(
							'Execute the structural and historical data migration for your LMS platform sequentially.',
							'simple-lms-bridge'
						) }
					</p>
				</div>
				<Button variant="secondary" onClick={clearCache} className="text-xs">
					{ __( 'Reset UI State', 'simple-lms-bridge' ) }
				</Button>
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false } className="mb-6 rounded-md">
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice
					status="success"
					onDismiss={ () => setNotice( null ) }
					className="mb-6 rounded-md"
				>
					{ notice }
				</Notice>
			) }

			<div className="space-y-6">
				{ /* Phase 1 */ }
				<div className="bg-white border border-gray-200 shadow-sm rounded-lg p-6 transition-all duration-200">
					<div className="flex flex-col sm:flex-row justify-between items-start mb-5">
						<div className="mb-4 sm:mb-0">
							<h2 className="text-xl font-bold text-gray-900 flex items-center gap-3">
								<span className="bg-blue-100 text-blue-800 rounded-full h-8 w-8 flex items-center justify-center text-sm">1</span>
								{ __( 'Content Migration', 'simple-lms-bridge' ) }
							</h2>
							<p className="text-gray-500 text-sm mt-2 ml-11">
								{ __(
									'Import legacy Course and Lesson posts into the new SimpleLMS schema.',
									'simple-lms-bridge'
								) }
							</p>
						</div>
						<span
							className={ `px-3 py-1 rounded-full text-xs font-semibold ${
								contentState === 'completed'
									? 'bg-green-100 text-green-800'
									: 'bg-yellow-100 text-yellow-800'
							}` }
						>
							{ contentState === 'completed'
								? __( 'Completed', 'simple-lms-bridge' )
								: contentState === 'running'
								? __( 'Running', 'simple-lms-bridge' )
								: __( 'Pending', 'simple-lms-bridge' ) }
						</span>
					</div>

					<div className="mb-5 ml-11">
						<div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
							<span>
								{ contentState === 'completed'
									? __( 'All content mapped', 'simple-lms-bridge' )
									: sprintf( __( '%d items remaining', 'simple-lms-bridge' ), contentPending ) }
							</span>
							<span className={contentState === 'completed' ? 'text-green-600' : 'text-blue-600'}>{ Math.round( contentProgress ) }%</span>
						</div>
						<ProgressBar value={ contentProgress } className="h-2.5 rounded-full" />
					</div>

					{ contentState !== 'completed' && (
						<div className="mt-4 ml-11">
							<Button
								variant="primary"
								isBusy={ contentState === 'running' }
								disabled={ contentState === 'running' }
								onClick={ () => runMigration( 'content' ) }
								className="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 shadow-sm rounded-md transition-colors"
							>
								{ contentState === 'running'
									? __( 'Processing…', 'simple-lms-bridge' )
									: __( 'Start Content Migration', 'simple-lms-bridge' ) }
							</Button>
						</div>
					) }
				</div>

				{ /* Phase 2 */ }
				<div
					className={ `bg-white border ${
						contentState === 'completed'
							? 'border-gray-200'
							: 'border-gray-200 opacity-60'
					} shadow-sm rounded-lg p-6 transition-all duration-200` }
				>
					<div className="flex flex-col sm:flex-row justify-between items-start mb-5">
						<div className="mb-4 sm:mb-0">
							<h2 className="text-xl font-bold text-gray-900 flex items-center gap-3">
								<span className={`${contentState === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'} rounded-full h-8 w-8 flex items-center justify-center text-sm`}>2</span>
								{ __( 'WPComplete Progress Migration', 'simple-lms-bridge' ) }
							</h2>
							<p className="text-gray-500 text-sm mt-2 ml-11">
								{ __(
									'Migrate existing student lesson completion data. Requires Phase 1 to be finished.',
									'simple-lms-bridge'
								) }
							</p>
						</div>
						<span
							className={ `px-3 py-1 rounded-full text-xs font-semibold ${
								progressState === 'completed'
									? 'bg-green-100 text-green-800'
									: progressState === 'locked'
									? 'bg-gray-100 text-gray-700'
									: 'bg-yellow-100 text-yellow-800'
							}` }
						>
							{ progressState === 'completed'
								? __( 'Completed', 'simple-lms-bridge' )
								: progressState === 'locked'
								? __( 'Locked', 'simple-lms-bridge' )
								: progressState === 'running'
								? __( 'Running', 'simple-lms-bridge' )
								: __( 'Pending', 'simple-lms-bridge' ) }
						</span>
					</div>

					<div className="mb-5 ml-11">
						<div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
							<span>
								{ progressState === 'completed'
									? __( 'All progress synced', 'simple-lms-bridge' )
									: progressState === 'locked'
									? __( 'Waiting for Phase 1', 'simple-lms-bridge' )
									: sprintf( __( '%d users remaining', 'simple-lms-bridge' ), progressPending ) }
							</span>
							<span className={progressState === 'completed' ? 'text-green-600' : 'text-blue-600'}>{ Math.round( progressProgress ) }%</span>
						</div>
						<ProgressBar value={ progressProgress } className="h-2.5 rounded-full" />
					</div>

					{ progressState !== 'completed' && (
						<div className="mt-4 ml-11">
							<Button
								variant="primary"
								isBusy={ progressState === 'running' }
								disabled={ progressState === 'locked' || progressState === 'running' }
								onClick={ () => runMigration( 'progress' ) }
								className={ `${
									progressState === 'locked'
										? 'bg-gray-300 text-gray-500 cursor-not-allowed'
										: 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'
								} font-medium px-6 py-2 rounded-md transition-colors` }
							>
								{ progressState === 'locked'
									? __( 'Waiting for Phase 1…', 'simple-lms-bridge' )
									: progressState === 'running'
									? __( 'Syncing Data…', 'simple-lms-bridge' )
									: __( 'Start Progress Migration', 'simple-lms-bridge' ) }
							</Button>
						</div>
					) }
				</div>

				{ /* Phase 3 */ }
				<div
					className={ `bg-white border ${
						progressState === 'completed'
							? 'border-gray-200'
							: 'border-gray-200 opacity-60'
					} shadow-sm rounded-lg p-6 transition-all duration-200` }
				>
					<div className="flex flex-col sm:flex-row justify-between items-start mb-5">
						<div className="mb-4 sm:mb-0">
							<h2 className="text-xl font-bold text-gray-900 flex items-center gap-3">
								<span className={`${progressState === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'} rounded-full h-8 w-8 flex items-center justify-center text-sm`}>3</span>
								{ __( 'Historical Certificate Sync', 'simple-lms-bridge' ) }
							</h2>
							<p className="text-gray-500 text-sm mt-2 ml-11">
								{ __(
									'Query Gravity Forms entries to backfill read-only historical certificate records. Requires Phase 2.',
									'simple-lms-bridge'
								) }
							</p>
						</div>
						<span
							className={ `px-3 py-1 rounded-full text-xs font-semibold ${
								historyState === 'completed'
									? 'bg-green-100 text-green-800'
									: historyState === 'locked'
									? 'bg-gray-100 text-gray-700'
									: 'bg-yellow-100 text-yellow-800'
							}` }
						>
							{ historyState === 'completed'
								? __( 'Completed', 'simple-lms-bridge' )
								: historyState === 'locked'
								? __( 'Locked', 'simple-lms-bridge' )
								: historyState === 'running'
								? __( 'Running', 'simple-lms-bridge' )
								: __( 'Pending', 'simple-lms-bridge' ) }
						</span>
					</div>

					<div className="mb-5 ml-11">
						<div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
							<span>
								{ historyState === 'completed'
									? __( 'All histories synced', 'simple-lms-bridge' )
									: historyState === 'locked'
									? __( 'Waiting for Phase 2', 'simple-lms-bridge' )
									: sprintf( __( '%d users remaining', 'simple-lms-bridge' ), historyPending ) }
							</span>
							<span className={historyState === 'completed' ? 'text-green-600' : 'text-blue-600'}>{ Math.round( historyProgress ) }%</span>
						</div>
						<ProgressBar value={ historyProgress } className="h-2.5 rounded-full" />
					</div>

					{ historyState !== 'completed' && (
						<div className="mt-4 ml-11">
							<Button
								variant="primary"
								isBusy={ historyState === 'running' }
								disabled={ historyState === 'locked' || historyState === 'running' }
								onClick={ () => runMigration( 'history' ) }
								className={ `${
									historyState === 'locked'
										? 'bg-gray-300 text-gray-500 cursor-not-allowed'
										: 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'
								} font-medium px-6 py-2 rounded-md transition-colors` }
							>
								{ historyState === 'locked'
									? __( 'Waiting for Phase 2…', 'simple-lms-bridge' )
									: historyState === 'running'
									? __( 'Querying API…', 'simple-lms-bridge' )
									: __( 'Start Certificate Sync', 'simple-lms-bridge' ) }
							</Button>
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default MigrationTool;

