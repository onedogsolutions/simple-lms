/**
 * Analytics – Owner-facing reporting dashboard.
 *
 * Renders on the SimpleLMS → Analytics admin page. Provides:
 *  - KPI tiles (active students, enrollments, completions, certificates + deltas)
 *  - Enrollments vs completions time-series (lightweight inline SVG, no chart dep)
 *  - Course drill-down: funnel bars + per-lesson drop-off + time-to-complete
 *  - At-risk students table with actions (extend access, open in Student Manager,
 *    copy email list) and CSV export on every view.
 *
 * @package
 */

import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { Button, Spinner, Notice, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/* ─── Palette (validated data-viz reference slots) ───────────────────────── */
const COLOR_ENROLL = '#2a78d6'; // categorical slot 1 (blue)
const COLOR_COMPLETE = '#008300'; // categorical slot 2 (green)
const STATUS_WARNING = '#fab219';
const STATUS_CRITICAL = '#d03b3b';

/* ─── Date helpers ───────────────────────────────────────────────────────── */
const toYmd = ( date ) => date.toISOString().slice( 0, 10 );

const rangeForPreset = ( preset ) => {
	const to = new Date();
	const from = new Date();
	if ( preset === '7' ) {
		from.setDate( to.getDate() - 6 );
	} else if ( preset === '30' ) {
		from.setDate( to.getDate() - 29 );
	} else if ( preset === '90' ) {
		from.setDate( to.getDate() - 89 );
	} else if ( preset === 'quarter' ) {
		const q = Math.floor( to.getMonth() / 3 );
		from.setMonth( q * 3, 1 );
	} else if ( preset === 'year' ) {
		from.setMonth( 0, 1 );
	}
	return { from: toYmd( from ), to: toYmd( to ) };
};

const PRESETS = [
	{ label: __( 'Last 7 days', 'simple-lms-bridge' ), value: '7' },
	{ label: __( 'Last 30 days', 'simple-lms-bridge' ), value: '30' },
	{ label: __( 'Last 90 days', 'simple-lms-bridge' ), value: '90' },
	{ label: __( 'This quarter', 'simple-lms-bridge' ), value: 'quarter' },
	{ label: __( 'This year', 'simple-lms-bridge' ), value: 'year' },
];

/* ─── CSV export URL builder ─────────────────────────────────────────────── */
const exportUrl = ( report, params = {} ) => {
	const base = window.slmsAdmin?.analyticsExportUrl;
	if ( ! base ) {
		return '#';
	}
	const url = new URL( base );
	url.searchParams.set( 'report', report );
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== null && value !== undefined && value !== '' ) {
			url.searchParams.set( key, value );
		}
	} );
	return url.toString();
};

const ExportButton = ( { report, params, label } ) => (
	<a
		href={ exportUrl( report, params ) }
		className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors no-underline"
	>
		<span aria-hidden="true">⬇</span>
		{ label || __( 'Export CSV', 'simple-lms-bridge' ) }
	</a>
);

/* ─── Trend delta badge ──────────────────────────────────────────────────── */
const TrendBadge = ( { delta } ) => {
	if ( ! delta ) {
		return null;
	}
	const { change, pct } = delta;
	const up = change > 0;
	const flat = change === 0;
	const cls = flat
		? 'text-gray-500 bg-gray-100'
		: up
		? 'text-green-700 bg-green-50'
		: 'text-red-700 bg-red-50';
	const arrow = flat ? '→' : up ? '▲' : '▼';
	return (
		<span
			className={ `inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold ${ cls }` }
			title={ sprintf(
				/* translators: %d: previous period value */
				__( 'vs previous period (%d)', 'simple-lms-bridge' ),
				delta.previous
			) }
		>
			{ arrow } { Math.abs( pct ) }%
		</span>
	);
};

/* ─── KPI tile ───────────────────────────────────────────────────────────── */
const KpiTile = ( { label, value, delta, accent } ) => (
	<div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col gap-2">
		<span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">
			{ label }
		</span>
		<div className="flex items-end justify-between gap-2">
			<span
				className="text-3xl font-bold"
				style={ { color: accent || '#0b0b0b' } }
			>
				{ Number( value ).toLocaleString() }
			</span>
			<TrendBadge delta={ delta } />
		</div>
	</div>
);

/* ─── Time-series chart (inline SVG, enrollments vs completions) ─────────── */
const TimeSeriesChart = ( { series } ) => {
	const [ hover, setHover ] = useState( null );

	const W = 760;
	const H = 260;
	const pad = { top: 20, right: 20, bottom: 34, left: 40 };
	const innerW = W - pad.left - pad.right;
	const innerH = H - pad.top - pad.bottom;

	const max = useMemo( () => {
		const m = Math.max(
			1,
			...series.map( ( p ) =>
				Math.max( p.enrollments, p.completions )
			)
		);
		// Round the axis top up to a "nice" number.
		const pow = Math.pow( 10, Math.floor( Math.log10( m ) ) );
		return Math.ceil( m / pow ) * pow;
	}, [ series ] );

	if ( ! series || series.length === 0 ) {
		return (
			<div className="text-sm text-gray-400 italic p-8 text-center">
				{ __( 'No activity in this period.', 'simple-lms-bridge' ) }
			</div>
		);
	}

	const n = series.length;
	const x = ( i ) => pad.left + ( n === 1 ? innerW / 2 : ( i / ( n - 1 ) ) * innerW );
	const y = ( v ) => pad.top + innerH - ( v / max ) * innerH;

	const path = ( key ) =>
		series
			.map( ( p, i ) => `${ i === 0 ? 'M' : 'L' } ${ x( i ) } ${ y( p[ key ] ) }` )
			.join( ' ' );

	const gridLines = [ 0, 0.25, 0.5, 0.75, 1 ];

	// Sparse x labels: first, middle, last.
	const labelIdx = new Set( [ 0, Math.floor( ( n - 1 ) / 2 ), n - 1 ] );

	return (
		<div className="relative">
			<svg
				viewBox={ `0 0 ${ W } ${ H }` }
				className="w-full h-auto"
				role="img"
				aria-label={ __(
					'Enrollments versus completions over time',
					'simple-lms-bridge'
				) }
				onMouseLeave={ () => setHover( null ) }
			>
				{ /* Gridlines + y labels */ }
				{ gridLines.map( ( g ) => {
					const gv = max * ( 1 - g );
					const gy = pad.top + g * innerH;
					return (
						<g key={ g }>
							<line
								x1={ pad.left }
								x2={ W - pad.right }
								y1={ gy }
								y2={ gy }
								stroke="#e1e0d9"
								strokeWidth="1"
							/>
							<text
								x={ pad.left - 6 }
								y={ gy + 3 }
								textAnchor="end"
								fontSize="10"
								fill="#898781"
								style={ { fontVariantNumeric: 'tabular-nums' } }
							>
								{ Math.round( gv ) }
							</text>
						</g>
					);
				} ) }

				{ /* x labels */ }
				{ series.map( ( p, i ) =>
					labelIdx.has( i ) ? (
						<text
							key={ p.date }
							x={ x( i ) }
							y={ H - 12 }
							textAnchor="middle"
							fontSize="10"
							fill="#898781"
						>
							{ p.date.slice( 5 ) }
						</text>
					) : null
				) }

				{ /* Series lines */ }
				<path
					d={ path( 'enrollments' ) }
					fill="none"
					stroke={ COLOR_ENROLL }
					strokeWidth="2"
					strokeLinejoin="round"
					strokeLinecap="round"
				/>
				<path
					d={ path( 'completions' ) }
					fill="none"
					stroke={ COLOR_COMPLETE }
					strokeWidth="2"
					strokeLinejoin="round"
					strokeLinecap="round"
				/>

				{ /* Hover hit-areas + crosshair */ }
				{ series.map( ( p, i ) => (
					<rect
						key={ p.date }
						x={ x( i ) - innerW / n / 2 }
						y={ pad.top }
						width={ Math.max( 6, innerW / n ) }
						height={ innerH }
						fill="transparent"
						onMouseEnter={ () => setHover( i ) }
					/>
				) ) }
				{ hover !== null && (
					<g>
						<line
							x1={ x( hover ) }
							x2={ x( hover ) }
							y1={ pad.top }
							y2={ pad.top + innerH }
							stroke="#c3c2b7"
							strokeWidth="1"
							strokeDasharray="3 3"
						/>
						<circle
							cx={ x( hover ) }
							cy={ y( series[ hover ].enrollments ) }
							r="4"
							fill={ COLOR_ENROLL }
							stroke="#fff"
							strokeWidth="2"
						/>
						<circle
							cx={ x( hover ) }
							cy={ y( series[ hover ].completions ) }
							r="4"
							fill={ COLOR_COMPLETE }
							stroke="#fff"
							strokeWidth="2"
						/>
					</g>
				) }
			</svg>

			{ /* Legend */ }
			<div className="flex items-center gap-5 justify-center mt-1 text-xs text-gray-600">
				<span className="inline-flex items-center gap-1.5">
					<span
						className="inline-block w-3 h-3 rounded-sm"
						style={ { background: COLOR_ENROLL } }
					/>
					{ __( 'Enrollments', 'simple-lms-bridge' ) }
				</span>
				<span className="inline-flex items-center gap-1.5">
					<span
						className="inline-block w-3 h-3 rounded-sm"
						style={ { background: COLOR_COMPLETE } }
					/>
					{ __( 'Completions', 'simple-lms-bridge' ) }
				</span>
			</div>

			{ /* Tooltip */ }
			{ hover !== null && (
				<div className="absolute top-0 right-0 bg-white border border-gray-200 rounded-lg shadow-md px-3 py-2 text-xs pointer-events-none">
					<div className="font-semibold text-gray-800 mb-1">
						{ series[ hover ].date }
					</div>
					<div style={ { color: COLOR_ENROLL } }>
						{ __( 'Enrollments', 'simple-lms-bridge' ) }:{ ' ' }
						<strong>{ series[ hover ].enrollments }</strong>
					</div>
					<div style={ { color: COLOR_COMPLETE } }>
						{ __( 'Completions', 'simple-lms-bridge' ) }:{ ' ' }
						<strong>{ series[ hover ].completions }</strong>
					</div>
				</div>
			) }
		</div>
	);
};

/* ─── Funnel bars ────────────────────────────────────────────────────────── */
const FunnelBars = ( { funnel } ) => {
	const stages = [
		{ key: 'enrolled', label: __( 'Enrolled', 'simple-lms-bridge' ) },
		{ key: 'started', label: __( 'Started', 'simple-lms-bridge' ) },
		{ key: 'completed', label: __( 'Completed', 'simple-lms-bridge' ) },
		{ key: 'certificate', label: __( 'Certificate', 'simple-lms-bridge' ) },
	];
	const top = Math.max( 1, funnel.stages.enrolled );

	return (
		<div className="space-y-2">
			{ stages.map( ( s ) => {
				const value = funnel.stages[ s.key ] || 0;
				const pct = Math.round( ( value / top ) * 100 );
				return (
					<div key={ s.key } className="flex items-center gap-3">
						<div className="w-28 text-sm text-gray-600 text-right shrink-0">
							{ s.label }
						</div>
						<div className="flex-grow bg-gray-100 rounded-md h-7 relative overflow-hidden">
							<div
								className="h-full rounded-md flex items-center transition-all"
								style={ {
									width: `${ Math.max( pct, 2 ) }%`,
									background: COLOR_ENROLL,
								} }
							/>
							<span className="absolute inset-y-0 left-3 flex items-center text-xs font-semibold text-gray-800">
								{ value.toLocaleString() }
								<span className="ml-2 text-gray-500 font-normal">
									{ pct }%
								</span>
							</span>
						</div>
					</div>
				);
			} ) }
		</div>
	);
};

/* ─── Drop-off table ─────────────────────────────────────────────────────── */
const DropoffTable = ( { dropoff } ) => {
	if ( ! dropoff || dropoff.length === 0 ) {
		return (
			<p className="text-sm text-gray-400 italic">
				{ __(
					'Not enough lessons to compute drop-off.',
					'simple-lms-bridge'
				) }
			</p>
		);
	}
	const worst = Math.max( ...dropoff.map( ( d ) => d.dropped ) );
	return (
		<div className="overflow-x-auto">
			<table className="min-w-full text-sm">
				<thead>
					<tr className="text-xs uppercase text-gray-500 tracking-wider border-b border-gray-200">
						<th className="py-2 px-3 text-left">
							{ __( 'Transition', 'simple-lms-bridge' ) }
						</th>
						<th className="py-2 px-3 text-right">
							{ __( 'Reached', 'simple-lms-bridge' ) }
						</th>
						<th className="py-2 px-3 text-right">
							{ __( 'Dropped', 'simple-lms-bridge' ) }
						</th>
						<th className="py-2 px-3 text-right">
							{ __( 'Drop %', 'simple-lms-bridge' ) }
						</th>
					</tr>
				</thead>
				<tbody className="divide-y divide-gray-100">
					{ dropoff.map( ( d, i ) => {
						const isWorst = d.dropped === worst && worst > 0;
						return (
							<tr
								key={ i }
								className={
									isWorst
										? 'bg-red-50'
										: 'hover:bg-gray-50'
								}
							>
								<td className="py-2 px-3 text-gray-700">
									<span className="text-gray-400">
										{ d.from_title }
									</span>
									<span className="mx-1">→</span>
									<span className="font-medium">
										{ d.to_title }
									</span>
									{ isWorst && (
										<span className="ml-2 inline-flex items-center gap-1 text-[10px] font-bold text-red-700">
											<span aria-hidden="true">⚠</span>
											{ __(
												'BIGGEST DROP',
												'simple-lms-bridge'
											) }
										</span>
									) }
								</td>
								<td className="py-2 px-3 text-right tabular-nums text-gray-600">
									{ d.to_completed }
								</td>
								<td className="py-2 px-3 text-right tabular-nums font-semibold text-gray-800">
									{ d.dropped }
								</td>
								<td
									className="py-2 px-3 text-right tabular-nums font-semibold"
									style={ {
										color:
											d.drop_pct >= 25
												? STATUS_CRITICAL
												: d.drop_pct >= 10
												? '#b45309'
												: '#52514e',
									} }
								>
									{ d.drop_pct }%
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
};

/* ─── Time-to-complete summary ───────────────────────────────────────────── */
const TimeToComplete = ( { ttc } ) => {
	const buckets = [
		{ key: 'lt_7', label: __( '< 1 week', 'simple-lms-bridge' ) },
		{ key: '7_30', label: __( '1–4 weeks', 'simple-lms-bridge' ) },
		{ key: '30_90', label: __( '1–3 months', 'simple-lms-bridge' ) },
		{ key: 'gt_90', label: __( '3 months+', 'simple-lms-bridge' ) },
		{ key: 'unknown', label: __( 'Unknown', 'simple-lms-bridge' ) },
	];
	const total = buckets.reduce(
		( sum, b ) => sum + ( ttc.buckets[ b.key ] || 0 ),
		0
	);
	return (
		<div>
			<div className="flex gap-4 mb-4 text-sm">
				<div>
					<span className="text-gray-500">
						{ __( 'Median', 'simple-lms-bridge' ) }:{ ' ' }
					</span>
					<strong>
						{ ttc.median_days !== null
							? sprintf(
									/* translators: %s: number of days */
									__( '%s days', 'simple-lms-bridge' ),
									ttc.median_days
							  )
							: '—' }
					</strong>
				</div>
				<div>
					<span className="text-gray-500">
						{ __( 'Average', 'simple-lms-bridge' ) }:{ ' ' }
					</span>
					<strong>
						{ ttc.average_days !== null
							? sprintf(
									/* translators: %s: number of days */
									__( '%s days', 'simple-lms-bridge' ),
									ttc.average_days
							  )
							: '—' }
					</strong>
				</div>
			</div>
			<div className="space-y-1.5">
				{ buckets.map( ( b ) => {
					const v = ttc.buckets[ b.key ] || 0;
					const pct = total > 0 ? Math.round( ( v / total ) * 100 ) : 0;
					return (
						<div key={ b.key } className="flex items-center gap-3">
							<div className="w-24 text-xs text-gray-600 text-right shrink-0">
								{ b.label }
							</div>
							<div className="flex-grow bg-gray-100 rounded h-5 relative overflow-hidden">
								<div
									className="h-full rounded"
									style={ {
										width: `${ Math.max( pct, v > 0 ? 2 : 0 ) }%`,
										background:
											b.key === 'unknown'
												? '#c3c2b7'
												: COLOR_COMPLETE,
									} }
								/>
								<span className="absolute inset-y-0 left-2 flex items-center text-[11px] font-medium text-gray-700">
									{ v }
								</span>
							</div>
						</div>
					);
				} ) }
			</div>
		</div>
	);
};

/* ─── Main component ─────────────────────────────────────────────────────── */
const Analytics = () => {
	const [ preset, setPreset ] = useState( '30' );
	const [ range, setRange ] = useState( rangeForPreset( '30' ) );
	const [ overview, setOverview ] = useState( null );
	const [ loadingOverview, setLoadingOverview ] = useState( true );

	const [ courses, setCourses ] = useState( [] );
	const [ selectedCourse, setSelectedCourse ] = useState( '' );
	const [ courseDetail, setCourseDetail ] = useState( null );
	const [ loadingCourse, setLoadingCourse ] = useState( false );

	const [ days, setDays ] = useState( 30 );
	const [ atRisk, setAtRisk ] = useState( null );
	const [ loadingAtRisk, setLoadingAtRisk ] = useState( true );
	const [ extending, setExtending ] = useState( {} );

	const [ notice, setNotice ] = useState( null );

	/* ── Deep-link: #/analytics/course/{id} selects a course on load ── */
	useEffect( () => {
		const match = ( window.location.hash || '' ).match(
			/#\/analytics\/course\/(\d+)/
		);
		if ( match ) {
			setSelectedCourse( match[ 1 ] );
		}
	}, [] );

	/* ── Fetchers ── */
	const fetchOverview = useCallback( async ( r ) => {
		setLoadingOverview( true );
		try {
			const res = await apiFetch( {
				path: `/simple-lms/v1/analytics/overview?from=${ r.from }&to=${ r.to }`,
			} );
			setOverview( res );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setLoadingOverview( false );
		}
	}, [] );

	const fetchAtRisk = useCallback( async ( d ) => {
		setLoadingAtRisk( true );
		try {
			const res = await apiFetch( {
				path: `/simple-lms/v1/analytics/at-risk?days=${ d }`,
			} );
			setAtRisk( res );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setLoadingAtRisk( false );
		}
	}, [] );

	const fetchCourse = useCallback( async ( id ) => {
		if ( ! id ) {
			setCourseDetail( null );
			return;
		}
		setLoadingCourse( true );
		try {
			const res = await apiFetch( {
				path: `/simple-lms/v1/analytics/course/${ id }`,
			} );
			setCourseDetail( res );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setLoadingCourse( false );
		}
	}, [] );

	useEffect( () => {
		fetchOverview( range );
	}, [ range, fetchOverview ] );

	useEffect( () => {
		fetchAtRisk( days );
	}, [ days, fetchAtRisk ] );

	useEffect( () => {
		fetchCourse( selectedCourse );
	}, [ selectedCourse, fetchCourse ] );

	useEffect( () => {
		apiFetch( { path: '/simple-lms/v1/analytics/courses' } )
			.then( ( res ) => setCourses( res || [] ) )
			.catch( () => {} );
	}, [] );

	const onPreset = ( value ) => {
		setPreset( value );
		setRange( rangeForPreset( value ) );
	};

	/* ── At-risk actions ── */
	const extendAccess = async ( userId, courseId ) => {
		const key = `${ userId }-${ courseId }`;
		setExtending( ( p ) => ( { ...p, [ key ]: true } ) );
		try {
			await apiFetch( {
				path: '/simple-lms/v1/analytics/extend-access',
				method: 'POST',
				data: { user_id: userId, course_id: courseId },
			} );
			setNotice( {
				status: 'success',
				message: __(
					'Access extended — enrollment clock reset.',
					'simple-lms-bridge'
				),
			} );
			fetchAtRisk( days );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setExtending( ( p ) => ( { ...p, [ key ]: false } ) );
		}
	};

	const openInStudentManager = ( courseId ) => {
		const base = window.slmsAdmin?.studentsUrl;
		if ( base ) {
			window.location.href = `${ base }#/students?course=${ courseId }`;
		}
	};

	const copyEmails = async ( rows ) => {
		const emails = [ ...new Set( rows.map( ( r ) => r.email ) ) ].join(
			', '
		);
		try {
			await navigator.clipboard.writeText( emails );
			setNotice( {
				status: 'success',
				message: sprintf(
					/* translators: %d: number of email addresses copied */
					__( 'Copied %d email addresses.', 'simple-lms-bridge' ),
					[ ...new Set( rows.map( ( r ) => r.email ) ) ].length
				),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message: __(
					'Could not copy to clipboard.',
					'simple-lms-bridge'
				),
			} );
		}
	};

	const courseOptions = [
		{ label: __( '— Select a course —', 'simple-lms-bridge' ), value: '' },
		...courses.map( ( c ) => ( {
			label: c.title,
			value: c.id.toString(),
		} ) ),
	];

	return (
		<div className="slms-analytics max-w-7xl mx-auto py-6 space-y-8">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ /* ── Header + range presets ── */ }
			<div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
				<div>
					<h1 className="text-3xl font-bold text-gray-900">
						{ __( 'Student Analytics', 'simple-lms-bridge' ) }
					</h1>
					<p className="text-gray-500 mt-1">
						{ overview
							? sprintf(
									/* translators: 1: from date, 2: to date */
									__(
										'Reporting period: %1$s → %2$s',
										'simple-lms-bridge'
									),
									overview.range.from,
									overview.range.to
							  )
							: '' }
					</p>
				</div>
				<div className="flex flex-wrap gap-2">
					{ PRESETS.map( ( p ) => (
						<button
							key={ p.value }
							type="button"
							onClick={ () => onPreset( p.value ) }
							className={ `px-3 py-1.5 text-xs font-medium rounded-md border cursor-pointer transition-colors ${
								preset === p.value
									? 'bg-gray-800 text-white border-gray-800'
									: 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'
							}` }
						>
							{ p.label }
						</button>
					) ) }
				</div>
			</div>

			{ /* ── KPI tiles ── */ }
			{ loadingOverview ? (
				<div className="flex justify-center p-12">
					<Spinner />
				</div>
			) : (
				overview && (
					<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
						<KpiTile
							label={ __(
								'Active Students',
								'simple-lms-bridge'
							) }
							value={ overview.kpis.active_students }
							accent="#0b0b0b"
						/>
						<KpiTile
							label={ __(
								'Enrollments',
								'simple-lms-bridge'
							) }
							value={ overview.kpis.enrollments }
							delta={ overview.deltas.enrollments }
							accent={ COLOR_ENROLL }
						/>
						<KpiTile
							label={ __(
								'Completions',
								'simple-lms-bridge'
							) }
							value={ overview.kpis.completions }
							delta={ overview.deltas.completions }
							accent={ COLOR_COMPLETE }
						/>
						<KpiTile
							label={ __(
								'Certificates',
								'simple-lms-bridge'
							) }
							value={ overview.kpis.certificates }
							delta={ overview.deltas.certificates }
							accent="#4a3aa7"
						/>
					</div>
				)
			) }

			{ /* ── Time-series ── */ }
			{ overview && (
				<div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
					<div className="flex items-center justify-between mb-4">
						<h2 className="text-lg font-semibold text-gray-800">
							{ __(
								'Enrollments vs Completions',
								'simple-lms-bridge'
							) }
						</h2>
						<ExportButton
							report="overview"
							params={ range }
						/>
					</div>
					<TimeSeriesChart series={ overview.series } />
				</div>
			) }

			{ /* ── Course drill-down ── */ }
			<div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
				<div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
					<h2 className="text-lg font-semibold text-gray-800">
						{ __( 'Course Drill-down', 'simple-lms-bridge' ) }
					</h2>
					<div className="flex items-end gap-3">
						<div className="w-64">
							<SelectControl
								label={ __(
									'Course',
									'simple-lms-bridge'
								) }
								value={ selectedCourse }
								options={ courseOptions }
								onChange={ ( v ) => setSelectedCourse( v ) }
								__nextHasNoMarginBottom={ true }
							/>
						</div>
						{ selectedCourse && (
							<ExportButton
								report="course"
								params={ { course_id: selectedCourse } }
							/>
						) }
					</div>
				</div>

				{ loadingCourse ? (
					<div className="flex justify-center p-12">
						<Spinner />
					</div>
				) : courseDetail ? (
					<div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
						<div>
							<h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
								{ __( 'Funnel', 'simple-lms-bridge' ) }
							</h3>
							<FunnelBars funnel={ courseDetail.funnel } />
							<h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mt-8 mb-4">
								{ __(
									'Time to Complete',
									'simple-lms-bridge'
								) }
							</h3>
							<TimeToComplete
								ttc={ courseDetail.time_to_complete }
							/>
						</div>
						<div>
							<h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
								{ __(
									'Per-lesson Drop-off',
									'simple-lms-bridge'
								) }
							</h3>
							<DropoffTable dropoff={ courseDetail.dropoff } />
							<div className="mt-4">
								<Button
									variant="link"
									onClick={ () =>
										openInStudentManager(
											selectedCourse
										)
									}
								>
									{ __(
										'View this cohort in Student Manager →',
										'simple-lms-bridge'
									) }
								</Button>
							</div>
						</div>
					</div>
				) : (
					<p className="text-sm text-gray-400 italic p-6 text-center">
						{ __(
							'Select a course to see its funnel, drop-off and time-to-complete.',
							'simple-lms-bridge'
						) }
					</p>
				) }
			</div>

			{ /* ── At-risk students ── */ }
			<div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
				<div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
					<div>
						<h2 className="text-lg font-semibold text-gray-800">
							{ __(
								'At-risk Students',
								'simple-lms-bridge'
							) }
						</h2>
						<p className="text-sm text-gray-500">
							{ __(
								'Inactive learners and expiring access.',
								'simple-lms-bridge'
							) }
						</p>
					</div>
					<div className="flex items-end gap-3">
						<div className="w-40">
							<SelectControl
								label={ __(
									'Inactive for',
									'simple-lms-bridge'
								) }
								value={ days.toString() }
								options={ [
									{ label: __( '14 days', 'simple-lms-bridge' ), value: '14' },
									{ label: __( '30 days', 'simple-lms-bridge' ), value: '30' },
									{ label: __( '60 days', 'simple-lms-bridge' ), value: '60' },
									{ label: __( '90 days', 'simple-lms-bridge' ), value: '90' },
								] }
								onChange={ ( v ) => setDays( parseInt( v, 10 ) ) }
								__nextHasNoMarginBottom={ true }
							/>
						</div>
						{ atRisk && atRisk.students.length > 0 && (
							<>
								<Button
									variant="secondary"
									onClick={ () =>
										copyEmails( atRisk.students )
									}
								>
									{ __(
										'Copy emails',
										'simple-lms-bridge'
									) }
								</Button>
								<ExportButton
									report="at-risk"
									params={ { days } }
								/>
							</>
						) }
					</div>
				</div>

				{ loadingAtRisk ? (
					<div className="flex justify-center p-12">
						<Spinner />
					</div>
				) : atRisk && atRisk.students.length > 0 ? (
					<div className="overflow-x-auto">
						<table className="min-w-full text-sm">
							<thead>
								<tr className="text-xs uppercase text-gray-500 tracking-wider border-b border-gray-200">
									<th className="py-2 px-3 text-left">
										{ __(
											'Student',
											'simple-lms-bridge'
										) }
									</th>
									<th className="py-2 px-3 text-left">
										{ __(
											'Course',
											'simple-lms-bridge'
										) }
									</th>
									<th className="py-2 px-3 text-right">
										{ __(
											'Inactive',
											'simple-lms-bridge'
										) }
									</th>
									<th className="py-2 px-3 text-right">
										{ __(
											'Expires in',
											'simple-lms-bridge'
										) }
									</th>
									<th className="py-2 px-3 text-left">
										{ __(
											'Flags',
											'simple-lms-bridge'
										) }
									</th>
									<th className="py-2 px-3 text-right">
										{ __(
											'Actions',
											'simple-lms-bridge'
										) }
									</th>
								</tr>
							</thead>
							<tbody className="divide-y divide-gray-100">
								{ atRisk.students.map( ( s ) => {
									const key = `${ s.user_id }-${ s.course_id }`;
									return (
										<tr
											key={ key }
											className="hover:bg-gray-50"
										>
											<td className="py-2 px-3">
												<div className="font-medium text-gray-800">
													{ s.display_name }
												</div>
												<a
													href={ `mailto:${ s.email }` }
													className="text-xs text-gray-500 hover:text-blue-600"
												>
													{ s.email }
												</a>
											</td>
											<td className="py-2 px-3 text-gray-600">
												{ s.course_title }
											</td>
											<td className="py-2 px-3 text-right tabular-nums text-gray-700">
												{ s.days_inactive !== null
													? sprintf(
															/* translators: %d: days */
															__(
																'%dd',
																'simple-lms-bridge'
															),
															s.days_inactive
													  )
													: '—' }
											</td>
											<td
												className="py-2 px-3 text-right tabular-nums font-medium"
												style={ {
													color:
														s.days_until_expiry !==
															null &&
														s.days_until_expiry <=
															14
															? STATUS_CRITICAL
															: '#52514e',
												} }
											>
												{ s.days_until_expiry !==
												null
													? sprintf(
															/* translators: %d: days */
															__(
																'%dd',
																'simple-lms-bridge'
															),
															s.days_until_expiry
													  )
													: '—' }
											</td>
											<td className="py-2 px-3">
												<div className="flex gap-1">
													{ s.reasons.includes(
														'expiring'
													) && (
														<span
															className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold text-white"
															style={ {
																background:
																	STATUS_CRITICAL,
															} }
														>
															<span aria-hidden="true">
																⏰
															</span>
															{ __(
																'EXPIRING',
																'simple-lms-bridge'
															) }
														</span>
													) }
													{ s.reasons.includes(
														'inactive'
													) && (
														<span
															className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold"
															style={ {
																background:
																	STATUS_WARNING,
																color: '#3a2e00',
															} }
														>
															<span aria-hidden="true">
																💤
															</span>
															{ __(
																'INACTIVE',
																'simple-lms-bridge'
															) }
														</span>
													) }
												</div>
											</td>
											<td className="py-2 px-3 text-right whitespace-nowrap">
												<Button
													variant="secondary"
													size="small"
													isBusy={
														extending[ key ]
													}
													disabled={
														extending[ key ]
													}
													onClick={ () =>
														extendAccess(
															s.user_id,
															s.course_id
														)
													}
												>
													{ __(
														'Extend access',
														'simple-lms-bridge'
													) }
												</Button>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					</div>
				) : (
					<p className="text-sm text-gray-400 italic p-6 text-center">
						{ __(
							'No at-risk students for this threshold. 🎉',
							'simple-lms-bridge'
						) }
					</p>
				) }
			</div>
		</div>
	);
};

export default Analytics;
