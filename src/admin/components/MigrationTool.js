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
				progress:
					prev.progress > res.progress.pending
						? prev.progress
						: res.progress.pending,
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

			while ( pending > 0 ) {
				const res = await apiFetch( {
					path: endpoint,
					method: 'POST',
					data: { limit: type === 'content' ? 5 : 10 },
				} );

				if ( ! res.success ) {
					throw new Error(
						__(
							'Migration failed unexpectedly.',
							'simple-lms-bridge'
						)
					);
				}

				pending = res.pending;
				setStatus( ( prev ) => ( {
					...prev,
					[ type ]: { pending: res.pending },
				} ) );
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

	// Safe Progress Calculation to prevent over 100% bugs
	const calculateProgress = (total, pending) => {
		if (total <= 0) return pending === 0 ? 100 : 0;
		const progress = ((total - pending) / total) * 100;
		return Math.max(0, Math.min(progress, 100)); // Clamp between 0 and 100
	};

	const contentProgress = calculateProgress(totals.content, contentPending);
	const progressProgress = calculateProgress(totals.progress, progressPending);
	const historyProgress = calculateProgress(totals.history, historyPending);

	const isPhase1Complete = contentPending === 0 && totals.content >= 0; // if 0 total initially, consider done.
	const isPhase2Complete = progressPending === 0 && totals.progress >= 0 && isPhase1Complete;

	return (
		<div className="max-w-4xl mx-auto py-8">
			<div className="bg-white rounded-lg shadow-sm p-8 mb-8 border border-gray-200">
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
								isPhase1Complete
									? 'bg-green-100 text-green-800'
									: 'bg-yellow-100 text-yellow-800'
							}` }
						>
							{ isPhase1Complete
								? __( 'Completed', 'simple-lms-bridge' )
								: __( 'Pending', 'simple-lms-bridge' ) }
						</span>
					</div>

					<div className="mb-5 ml-11">
						<div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
							<span>
								{ contentPending > 0
									? sprintf( __( '%d items remaining', 'simple-lms-bridge' ), contentPending )
									: __( 'All content mapped', 'simple-lms-bridge' ) }
							</span>
							<span className={isPhase1Complete ? 'text-green-600' : 'text-blue-600'}>{ Math.round( contentProgress ) }%</span>
						</div>
						<ProgressBar value={ contentProgress } className="h-2.5 rounded-full" />
					</div>

					{ ! isPhase1Complete && (
						<div className="mt-4 ml-11">
							<Button
								variant="primary"
								isBusy={ migrating && phase === 1 }
								disabled={ migrating }
								onClick={ () => runMigration( 'content' ) }
								className="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 shadow-sm rounded-md transition-colors"
							>
								{ migrating && phase === 1
									? __( 'Processing…', 'simple-lms-bridge' )
									: __( 'Start Content Migration', 'simple-lms-bridge' ) }
							</Button>
						</div>
					) }
				</div>

				{ /* Phase 2 */ }
				<div
					className={ `bg-white border ${
						isPhase1Complete
							? 'border-gray-200'
							: 'border-gray-200 opacity-60'
					} shadow-sm rounded-lg p-6 transition-all duration-200` }
				>
					<div className="flex flex-col sm:flex-row justify-between items-start mb-5">
						<div className="mb-4 sm:mb-0">
							<h2 className="text-xl font-bold text-gray-900 flex items-center gap-3">
								<span className={`${isPhase1Complete ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'} rounded-full h-8 w-8 flex items-center justify-center text-sm`}>2</span>
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
								isPhase2Complete
									? 'bg-green-100 text-green-800'
									: 'bg-gray-100 text-gray-700'
							}` }
						>
							{ isPhase2Complete
								? __( 'Completed', 'simple-lms-bridge' )
								: __( 'Pending', 'simple-lms-bridge' ) }
						</span>
					</div>

					<div className="mb-5 ml-11">
						<div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
							<span>
								{ progressPending > 0
									? sprintf( __( '%d users remaining', 'simple-lms-bridge' ), progressPending )
									: isPhase1Complete
									? __( 'All progress synced', 'simple-lms-bridge' )
									: __( 'Waiting for Phase 1', 'simple-lms-bridge' ) }
							</span>
							<span className={isPhase2Complete ? 'text-green-600' : 'text-blue-600'}>{ Math.round( progressProgress ) }%</span>
						</div>
						<ProgressBar value={ progressProgress } className="h-2.5 rounded-full" />
					</div>

					{ progressPending > 0 && (
						<div className="mt-4 ml-11">
							<Button
								variant="primary"
								isBusy={ migrating && phase === 2 }
								disabled={ migrating || ! isPhase1Complete }
								onClick={ () => runMigration( 'progress' ) }
								className={ `${
									! isPhase1Complete
										? 'bg-gray-300 text-gray-500 cursor-not-allowed'
										: 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'
								} font-medium px-6 py-2 rounded-md transition-colors` }
							>
								{ ! isPhase1Complete
									? __( 'Locked', 'simple-lms-bridge' )
									: migrating && phase === 2
									? __( 'Syncing Data…', 'simple-lms-bridge' )
									: __( 'Start Progress Migration', 'simple-lms-bridge' ) }
							</Button>
						</div>
					) }
				</div>

				{ /* Phase 3 */ }
				<div
					className={ `bg-white border ${
						isPhase2Complete
							? 'border-gray-200'
							: 'border-gray-200 opacity-60'
					} shadow-sm rounded-lg p-6 transition-all duration-200` }
				>
					<div className="flex flex-col sm:flex-row justify-between items-start mb-5">
						<div className="mb-4 sm:mb-0">
							<h2 className="text-xl font-bold text-gray-900 flex items-center gap-3">
								<span className={`${isPhase2Complete ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'} rounded-full h-8 w-8 flex items-center justify-center text-sm`}>3</span>
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
								historyPending === 0 && isPhase2Complete
									? 'bg-green-100 text-green-800'
									: 'bg-gray-100 text-gray-700'
							}` }
						>
							{ historyPending === 0 && isPhase2Complete
								? __( 'Completed', 'simple-lms-bridge' )
								: __( 'Pending', 'simple-lms-bridge' ) }
						</span>
					</div>

					<div className="mb-5 ml-11">
						<div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
							<span>
								{ historyPending > 0
									? sprintf( __( '%d users remaining', 'simple-lms-bridge' ), historyPending )
									: isPhase2Complete
									? __( 'All histories synced', 'simple-lms-bridge' )
									: __( 'Waiting for Phase 2', 'simple-lms-bridge' ) }
							</span>
							<span className={historyPending === 0 && isPhase2Complete ? 'text-green-600' : 'text-blue-600'}>{ Math.round( historyProgress ) }%</span>
						</div>
						<ProgressBar value={ historyProgress } className="h-2.5 rounded-full" />
					</div>

					{ historyPending > 0 && (
						<div className="mt-4 ml-11">
							<Button
								variant="primary"
								isBusy={ migrating && phase === 3 }
								disabled={ migrating || ! isPhase2Complete }
								onClick={ () => runMigration( 'history' ) }
								className={ `${
									! isPhase2Complete
										? 'bg-gray-300 text-gray-500 cursor-not-allowed'
										: 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'
								} font-medium px-6 py-2 rounded-md transition-colors` }
							>
								{ ! isPhase2Complete
									? __( 'Locked', 'simple-lms-bridge' )
									: migrating && phase === 3
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
