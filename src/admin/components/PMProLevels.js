/**
 * PMProLevels – Toggle-switch list of PMPro membership levels.
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * PMProLevels component.
 *
 * @param {Object}   props
 * @param {number[]} props.selectedLevels Currently selected level IDs.
 * @param {Function} props.onChange       Callback with updated level IDs array.
 * @param {Function} props.onLevelsLoaded Callback with full level data once fetched.
 * @return {JSX.Element} The rendered component.
 */
const PMProLevels = ( { selectedLevels, onChange, onLevelsLoaded } ) => {
	const [ levels, setLevels ] = useState( [] );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		apiFetch( { path: '/simple-lms/v1/pmpro-levels' } )
			.then( ( data ) => {
				setLevels( data );
				if ( onLevelsLoaded ) {
					onLevelsLoaded( data );
				}
			} )
			.catch( () => {
				setLevels( [] );
			} )
			.finally( () => {
				setLoading( false );
			} );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( loading ) {
		return <Spinner />;
	}

	if ( levels.length === 0 ) {
		return (
			<p className="slms-empty">
				{ __(
					'Paid Memberships Pro is not active or has no levels configured.',
					'simple-lms-bridge'
				) }
			</p>
		);
	}

	const toggleLevel = ( levelId, checked ) => {
		if ( checked ) {
			onChange( [ ...selectedLevels, levelId ] );
		} else {
			onChange( selectedLevels.filter( ( id ) => id !== levelId ) );
		}
	};

	return (
		<div className="slms-toggle-list">
			{ levels.map( ( level ) => (
				<label key={ level.id } className="slms-toggle-row">
					<span className="slms-toggle-text">
						<span className="slms-toggle-label">
							{ level.name }
						</span>
						{ level.expiration_days > 0 && (
							<span className="slms-toggle-hint">
								{ sprintf(
									/* translators: %d: number of days of access */
									__( '%d-day access', 'simple-lms-bridge' ),
									level.expiration_days
								) }
							</span>
						) }
					</span>
					<span className="slms-toggle-switch">
						<input
							type="checkbox"
							className="slms-toggle-input"
							checked={ selectedLevels.includes( level.id ) }
							onChange={ ( e ) =>
								toggleLevel( level.id, e.target.checked )
							}
						/>
						<span className="slms-toggle-track" aria-hidden="true">
							<span className="slms-toggle-thumb" />
						</span>
					</span>
				</label>
			) ) }
		</div>
	);
};

export default PMProLevels;
