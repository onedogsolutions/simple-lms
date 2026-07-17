/**
 * Tools – Admin maintenance & compliance screen.
 *
 * - Repair certificate form IDs (legacy backfill).
 * - Purge corrupted history records (typed-confirmation guard).
 * - Compliance export (CSV summary or ZIP of PDFs) by course / date range.
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import {
	PanelBody,
	Button,
	TextControl,
	SelectControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const PURGE_PHRASE = 'DELETE CORRUPTED';

const Tools = () => {
	const [ courses, setCourses ] = useState( [] );
	const [ notice, setNotice ] = useState( null );

	// Repair
	const [ repairing, setRepairing ] = useState( false );

	// Purge
	const [ purgeConfirm, setPurgeConfirm ] = useState( '' );
	const [ purging, setPurging ] = useState( false );

	// Export
	const [ exportCourse, setExportCourse ] = useState( '' );
	const [ exportFrom, setExportFrom ] = useState( '' );
	const [ exportTo, setExportTo ] = useState( '' );
	const [ exportFormat, setExportFormat ] = useState( 'csv' );

	// Progress backfill
	const [ backfilling, setBackfilling ] = useState( false );
	const [ backfillStats, setBackfillStats ] = useState( {
		processed_users: 0,
		inserted_rows: 0,
		skipped_entries: 0,
	} );
	const [ parity, setParity ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/simple-lms/v1/relationships/courses' } )
			.then( ( res ) => setCourses( res || [] ) )
			.catch( () => setCourses( [] ) );
	}, [] );

	const handleBackfill = async () => {
		setBackfilling( true );
		setNotice( null );
		let currentOffset = 0;
		let isComplete = false;
		let totalProcessedUsers = 0;
		let totalInsertedRows = 0;
		let totalSkippedEntries = 0;

		try {
			while ( ! isComplete ) {
				const res = await apiFetch( {
					path: '/simple-lms/v1/tools/progress-backfill',
					method: 'POST',
					data: {
						batch_size: 100,
						offset: currentOffset,
					},
				} );

				totalProcessedUsers += res.processed_users;
				totalInsertedRows += res.inserted_rows;
				totalSkippedEntries += res.skipped_entries;
				currentOffset = res.next_offset;
				isComplete = res.complete;

				setBackfillStats( {
					processed_users: totalProcessedUsers,
					inserted_rows: totalInsertedRows,
					skipped_entries: totalSkippedEntries,
				} );

				if ( res.parity ) {
					setParity( res.parity );
				}

				if ( res.processed_users === 0 ) {
					break;
				}
			}

			setNotice( {
				status: 'success',
				message:
					__( 'Backfill complete.', 'simple-lms-bridge' ) +
					` (processed: ${ totalProcessedUsers } users, inserted: ${ totalInsertedRows } rows, skipped: ${ totalSkippedEntries } malformed entries)`,
			} );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setBackfilling( false );
		}
	};

	const handleRepair = async () => {
		setRepairing( true );
		setNotice( null );
		try {
			const res = await apiFetch( {
				path: '/simple-lms/v1/course-history/repair-form-ids',
				method: 'POST',
			} );
			setNotice( {
				status: 'success',
				message: __(
					'Repair complete.',
					'simple-lms-bridge'
				) + ` (updated: ${ res.updated }, failed: ${ res.failed })`,
			} );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setRepairing( false );
		}
	};

	const handlePurge = async () => {
		if ( purgeConfirm !== PURGE_PHRASE ) {
			return;
		}
		// eslint-disable-next-line no-alert
		if (
			! window.confirm(
				__(
					'This permanently deletes history rows missing a form_id or entry_id, including some legitimate pre-migration records. This cannot be undone. Continue?',
					'simple-lms-bridge'
				)
			)
		) {
			return;
		}
		setPurging( true );
		setNotice( null );
		try {
			const res = await apiFetch( {
				path: '/simple-lms/v1/course-history/purge-corrupted',
				method: 'POST',
				data: { confirm: purgeConfirm },
			} );
			setNotice( {
				status: 'success',
				message:
					__( 'Purge complete. Rows deleted: ', 'simple-lms-bridge' ) +
					res.deleted,
			} );
			setPurgeConfirm( '' );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setPurging( false );
		}
	};

	const exportUrl = () => {
		const base = window.slmsAdmin?.adminPost || '';
		const params = new URLSearchParams( {
			action: 'slms_export_certificates',
			_wpnonce: window.slmsAdmin?.exportNonce || '',
			format: exportFormat,
			course: exportCourse,
			from: exportFrom,
			to: exportTo,
		} );
		return `${ base }?${ params.toString() }`;
	};

	return (
		<div className="slms-tools">
			<h1 className="tw-text-2xl tw-font-semibold tw-mb-4">
				{ __( 'SimpleLMS Tools', 'simple-lms-bridge' ) }
			</h1>

			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ /* ── Compliance Export ─────────────────────────────── */ }
			<PanelBody
				title={ __( 'Compliance Export', 'simple-lms-bridge' ) }
				initialOpen={ true }
			>
				<p className="slms-panel-desc">
					{ __(
						'Export certificate records for a state audit. CSV is a summary; ZIP bundles the branded PDFs.',
						'simple-lms-bridge'
					) }
				</p>
				<SelectControl
					label={ __( 'Course (optional)', 'simple-lms-bridge' ) }
					value={ exportCourse }
					options={ [
						{ label: __( 'All courses', 'simple-lms-bridge' ), value: '' },
						...courses.map( ( c ) => ( {
							label: c.title,
							value: c.title,
						} ) ),
					] }
					onChange={ setExportCourse }
				/>
				<div className="tw-flex tw-gap-4">
					<TextControl
						type="date"
						label={ __( 'From', 'simple-lms-bridge' ) }
						value={ exportFrom }
						onChange={ setExportFrom }
					/>
					<TextControl
						type="date"
						label={ __( 'To', 'simple-lms-bridge' ) }
						value={ exportTo }
						onChange={ setExportTo }
					/>
				</div>
				<SelectControl
					label={ __( 'Format', 'simple-lms-bridge' ) }
					value={ exportFormat }
					options={ [
						{ label: __( 'CSV summary', 'simple-lms-bridge' ), value: 'csv' },
						{ label: __( 'ZIP of PDFs', 'simple-lms-bridge' ), value: 'zip' },
					] }
					onChange={ setExportFormat }
				/>
				<Button variant="primary" href={ exportUrl() } target="_blank">
					{ __( 'Download Export', 'simple-lms-bridge' ) }
				</Button>
			</PanelBody>

			{ /* ── Progress Table Backfill ───────────────────────── */ }
			<PanelBody
				title={ __( 'Progress Table Backfill', 'simple-lms-bridge' ) }
				initialOpen={ false }
			>
				<p className="slms-panel-desc">
					{ __(
						'Backfills the wp_slms_lesson_progress database table from the legacy serialized user metadata. Safe to run repeatedly.',
						'simple-lms-bridge'
					) }
				</p>
				{ parity && (
					<div className="tw-bg-gray-50 tw-p-4 tw-rounded tw-mb-4 tw-text-sm">
						<p className="tw-font-medium tw-mb-1">
							{ __( 'Sync Parity Status', 'simple-lms-bridge' ) }:
						</p>
						<ul className="tw-list-disc tw-pl-4">
							<li>
								{ __( 'Metadata tuples', 'simple-lms-bridge' ) }: { parity.meta_tuples }
							</li>
							<li>
								{ __( 'Table rows', 'simple-lms-bridge' ) }: { parity.table_rows }
							</li>
							<li
								className={
									parity.in_sync
										? 'tw-text-green-600'
										: 'tw-text-amber-600 tw-font-semibold'
								}
							>
								{ parity.in_sync
									? __( 'In sync.', 'simple-lms-bridge' )
									: __(
											'Sync required — table row count differs from metadata.',
											'simple-lms-bridge'
									  ) }
							</li>
						</ul>
					</div>
				) }
				<div className="tw-flex tw-items-center tw-gap-4">
					<Button
						variant="secondary"
						isBusy={ backfilling }
						disabled={ backfilling }
						onClick={ handleBackfill }
					>
						{ backfilling ? (
							<Spinner />
						) : (
							__( 'Run Backfill', 'simple-lms-bridge' )
						) }
					</Button>
					{ backfilling && (
						<span className="tw-text-sm tw-text-gray-600">
							{ `Processed: ${ backfillStats.processed_users } users…` }
						</span>
					) }
				</div>
			</PanelBody>

			{ /* ── Repair Form IDs ───────────────────────────────── */ }
			<PanelBody
				title={ __( 'Repair Certificate Form IDs', 'simple-lms-bridge' ) }
				initialOpen={ false }
			>
				<p className="slms-panel-desc">
					{ __(
						'Backfills the Gravity Forms form ID on legacy history rows that have an entry ID but no form ID. Safe to run repeatedly.',
						'simple-lms-bridge'
					) }
				</p>
				<Button
					variant="secondary"
					isBusy={ repairing }
					disabled={ repairing }
					onClick={ handleRepair }
				>
					{ repairing ? (
						<Spinner />
					) : (
						__( 'Run Repair', 'simple-lms-bridge' )
					) }
				</Button>
			</PanelBody>

			{ /* ── Purge Corrupted Records ────────────────────────── */ }
			<PanelBody
				title={ __( 'Purge Corrupted Records', 'simple-lms-bridge' ) }
				initialOpen={ false }
			>
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Destructive. Deletes every history row missing a form_id or entry_id — this includes legitimate pre-migration records. Never run this unless you understand the consequences.',
						'simple-lms-bridge'
					) }
				</Notice>
				<TextControl
					label={ sprintfPhrase() }
					value={ purgeConfirm }
					onChange={ setPurgeConfirm }
					placeholder={ PURGE_PHRASE }
				/>
				<Button
					isDestructive
					isBusy={ purging }
					disabled={ purging || purgeConfirm !== PURGE_PHRASE }
					onClick={ handlePurge }
				>
					{ purging ? (
						<Spinner />
					) : (
						__( 'Purge Corrupted Records', 'simple-lms-bridge' )
					) }
				</Button>
			</PanelBody>
		</div>
	);
};

const sprintfPhrase = () =>
	__( 'Type "DELETE CORRUPTED" to enable', 'simple-lms-bridge' );

export default Tools;
