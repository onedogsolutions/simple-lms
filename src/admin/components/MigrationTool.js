/**
 * MigrationTool – Three-Phase Migration UI.
 *
 * Phase 1: CPT Migration (Legacy Pods -> New CPTs)
 * Phase 2: Student Progress Migration (WPComplete -> New DB)
 * Phase 3: Historical Certificate Migration (Gravity Forms)
 * Phase 4: PMPro Membership Migration (GF Form 2 -> PMPro Levels)
 *
 * @package
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, Notice, Spinner, ProgressBar } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const LOG_LEVEL_COLORS = {
	error: 'text-red-600',
	warn: 'text-yellow-600',
	info: 'text-blue-600',
	debug: 'text-gray-500',
};

const MigrationTool = () => {
	const [ status, setStatus ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ migrating, setMigrating ] = useState( false );
	const [ phase, setPhase ] = useState( 0 ); // 0 = idle, 1 = content, 2 = progress, 3 = history, 4 = pmpro

	// Track total counts to allow percentage calculation
	const [ totals, setTotals ] = useState( { content: 0, progress: 0, history: 0, pmpro: 0 } );
	const [ error, setError ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	// Log state
	const [ logEntries, setLogEntries ] = useState( [] );
	const [ showLog, setShowLog ] = useState( false );
	const [ logFilter, setLogFilter ] = useState( 'all' ); // all, error, warn, info, debug
	const logEndRef = useRef( null );

	// Auto-scroll log to bottom
	useEffect( () => {
		if ( logEndRef.current && showLog ) {
			logEndRef.current.scrollIntoView( { behavior: 'smooth' } );
		}
	}, [ logEntries, showLog ] );

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
				pmpro:
					prev.pmpro > (res.pmpro?.pending || 0)
						? prev.pmpro
						: (res.pmpro?.pending || 0),
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

	const appendLog = ( entries ) => {
		if ( Array.isArray( entries ) && entries.length > 0 ) {
			setLogEntries( ( prev ) => [ ...prev, ...entries ] );
			if ( ! showLog ) {
				setShowLog( true );
			}
		}
	};

	const runMigration = async ( type ) => {
		setMigrating( true );
		setError( null );
		setNotice( null );

		let activePhase = 0;
		if (type === 'content') activePhase = 1;
		if (type === 'progress') activePhase = 2;
		if (type === 'history') activePhase = 3;
		if (type === 'pmpro') activePhase = 4;

		setPhase( activePhase );

		let endpoint = '';
		if (type === 'content') endpoint = '/simple-lms/v1/migration/cpts';
		if (type === 'progress') endpoint = '/simple-lms/v1/migration/progress';
		if (type === 'history') endpoint = '/simple-lms/v1/migration/history';
		if (type === 'pmpro') endpoint = '/simple-lms/v1/migration/pmpro';

		try {
			let pending =
				type === 'content'
					? status.content.pending
					: type === 'progress'
						? status.progress.pending
						: type === 'history'
							? status.history.pending
							: (status.pmpro?.pending || 0);

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

				// Append log entries from the response
				if ( res.log ) {
					appendLog( res.log );
				}

				pending = res.pending;

				// Use the total returned by the batch response for accurate tracking.
				if ( res.total && res.total > 0 ) {
					setTotals( ( prev ) => ( {
						...prev,
						[ type ]: Math.max( prev[ type ], res.total ),
					} ) );
				}

				setStatus( ( prev ) => ( {
					...prev,
					[ type ]: { pending: res.pending },
				} ) );
			}

			let noticeMessage = '';
			if (type === 'content') noticeMessage = 'Phase 1: Content migration completed successfully.';
			if (type === 'progress') noticeMessage = 'Phase 2: Student Progress migration completed successfully.';
			if (type === 'history') noticeMessage = 'Phase 3: Historical Certificates migration completed successfully.';
			if (type === 'pmpro') noticeMessage = 'Phase 4: PMPro Membership migration completed successfully.';

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
	const pmproPending = status?.pmpro?.pending || 0;

	// Safe Progress Calculation to prevent over 100% bugs
	const calculateProgress = (total, pending) => {
		if (total <= 0) return pending === 0 ? 100 : 0;
		const progress = ((total - pending) / total) * 100;
		return Math.max(0, Math.min(progress, 100)); // Clamp between 0 and 100
	};

	const contentProgress = calculateProgress(totals.content, contentPending);
	const progressProgress = calculateProgress(totals.progress, progressPending);
	const historyProgress = calculateProgress(totals.history, historyPending);
	const pmproProgress = calculateProgress(totals.pmpro, pmproPending);

	const isPhase1Complete = contentPending === 0 && totals.content >= 0;
	const isPhase2Complete = progressPending === 0 && totals.progress >= 0 && isPhase1Complete;
	const isPhase3Complete = historyPending === 0 && isPhase2Complete;

	const filteredLog = logFilter === 'all'
		? logEntries
		: logEntries.filter( ( e ) => e.level === logFilter );

	const warnCount = logEntries.filter( ( e ) => e.level === 'warn' ).length;
	const errorCount = logEntries.filter( ( e ) => e.level === 'error' ).length;

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
									'Sync historical certificate entries into the permanent compliance history table for 9-year retention. Requires Phase 2.',
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

				{ /* Phase 4 */ }
				<div
					className={ `bg-white border ${
						isPhase3Complete
							? 'border-gray-200'
							: 'border-gray-200 opacity-60'
					} shadow-sm rounded-lg p-6 transition-all duration-200` }
				>
					<div className="flex flex-col sm:flex-row justify-between items-start mb-5">
						<div className="mb-4 sm:mb-0">
							<h2 className="text-xl font-bold text-gray-900 flex items-center gap-3">
								<span className={`${isPhase3Complete ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-500'} rounded-full h-8 w-8 flex items-center justify-center text-sm`}>4</span>
								{ __( 'PMPro Membership Migration', 'simple-lms-bridge' ) }
							</h2>
							<p className="text-gray-500 text-sm mt-2 ml-11">
								{ __(
									'Migrate historical access from Gravity Forms Registration (Form ID 2) into PMPro membership levels with 90-day access windows.',
									'simple-lms-bridge'
								) }
							</p>
						</div>
						<span
							className={ `px-3 py-1 rounded-full text-xs font-semibold ${
								pmproPending === 0 && isPhase3Complete
									? 'bg-green-100 text-green-800'
									: 'bg-gray-100 text-gray-700'
							}` }
						>
							{ pmproPending === 0 && isPhase3Complete
								? __( 'Completed', 'simple-lms-bridge' )
								: __( 'Pending', 'simple-lms-bridge' ) }
						</span>
					</div>

					<div className="mb-5 ml-11">
						<div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
							<span>
								{ pmproPending > 0
									? sprintf( __( '%d entries remaining', 'simple-lms-bridge' ), pmproPending )
									: isPhase3Complete
									? __( 'All memberships synced', 'simple-lms-bridge' )
									: __( 'Waiting for Phase 3', 'simple-lms-bridge' ) }
							</span>
							<span className={pmproPending === 0 && isPhase3Complete ? 'text-green-600' : 'text-purple-600'}>{ Math.round( pmproProgress ) }%</span>
						</div>
						<ProgressBar value={ pmproProgress } className="h-2.5 rounded-full" />
					</div>

					{ pmproPending > 0 && (
						<div className="mt-4 ml-11">
							<Button
								variant="primary"
								isBusy={ migrating && phase === 4 }
								disabled={ migrating || ! isPhase3Complete }
								onClick={ () => runMigration( 'pmpro' ) }
								className={ `${
									! isPhase3Complete
										? 'bg-gray-300 text-gray-500 cursor-not-allowed'
										: 'bg-purple-600 hover:bg-purple-700 text-white shadow-sm'
								} font-medium px-6 py-2 rounded-md transition-colors` }
							>
								{ ! isPhase3Complete
									? __( 'Locked', 'simple-lms-bridge' )
									: migrating && phase === 4
									? __( 'Migrating Memberships…', 'simple-lms-bridge' )
									: __( 'Start PMPro Migration', 'simple-lms-bridge' ) }
							</Button>
						</div>
					) }
				</div>

				{ /* Migration Log Panel */ }
				{ logEntries.length > 0 && (
					<div className="bg-white border border-gray-200 shadow-sm rounded-lg transition-all duration-200">
						<button
							type="button"
							className="w-full p-4 flex justify-between items-center cursor-pointer bg-transparent border-0 text-left"
							onClick={ () => setShowLog( ! showLog ) }
						>
							<h2 className="text-lg font-bold text-gray-900 flex items-center gap-3">
								{ __( 'Migration Log', 'simple-lms-bridge' ) }
								<span className="text-sm font-normal text-gray-500">
									({ logEntries.length } { __( 'entries', 'simple-lms-bridge' ) })
								</span>
								{ errorCount > 0 && (
									<span className="bg-red-100 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full">
										{ errorCount } { __( 'error(s)', 'simple-lms-bridge' ) }
									</span>
								) }
								{ warnCount > 0 && (
									<span className="bg-yellow-100 text-yellow-700 text-xs font-semibold px-2 py-0.5 rounded-full">
										{ warnCount } { __( 'warning(s)', 'simple-lms-bridge' ) }
									</span>
								) }
							</h2>
							<span className="text-gray-400 text-xl">
								{ showLog ? '\u25B2' : '\u25BC' }
							</span>
						</button>

						{ showLog && (
							<div className="border-t border-gray-200">
								<div className="flex gap-2 px-4 py-3 border-b border-gray-100 bg-gray-50">
									{ [ 'all', 'error', 'warn', 'info', 'debug' ].map( ( level ) => (
										<button
											key={ level }
											type="button"
											onClick={ () => setLogFilter( level ) }
											className={ `px-3 py-1 text-xs font-medium rounded-full border cursor-pointer transition-colors ${
												logFilter === level
													? 'bg-gray-800 text-white border-gray-800'
													: 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'
											}` }
										>
											{ level.charAt( 0 ).toUpperCase() + level.slice( 1 ) }
										</button>
									) ) }
									<div className="flex-grow" />
									<button
										type="button"
										onClick={ () => { setLogEntries( [] ); setShowLog( false ); } }
										className="px-3 py-1 text-xs font-medium rounded-full border border-gray-300 text-gray-500 hover:text-red-600 hover:border-red-300 bg-white cursor-pointer transition-colors"
									>
										{ __( 'Clear', 'simple-lms-bridge' ) }
									</button>
								</div>
								<div className="max-h-96 overflow-y-auto bg-gray-900 p-4 rounded-b-lg font-mono text-xs leading-relaxed">
									{ filteredLog.map( ( entry, i ) => (
										<div key={ i } className="flex gap-3 py-0.5">
											<span className="text-gray-500 flex-shrink-0">{ entry.time }</span>
											<span className={ `flex-shrink-0 uppercase font-semibold w-12 ${ LOG_LEVEL_COLORS[ entry.level ] || 'text-gray-400' }` }>
												{ entry.level }
											</span>
											<span className="text-gray-300 break-all">{ entry.msg }</span>
										</div>
									) ) }
									<div ref={ logEndRef } />
								</div>
							</div>
						) }
					</div>
				) }
			</div>
		</div>
	);
};

export default MigrationTool;
