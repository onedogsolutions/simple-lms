/**
 * PMProLevels – Multi-select checkbox list of PMPro membership levels.
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import { CheckboxControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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
		<div className="slms-pmpro-levels">
			{ levels.map( ( level ) => (
				<CheckboxControl
					key={ level.id }
					label={
						level.expiration_days > 0
							? `${ level.name } (${ level.expiration_days } days)`
							: level.name
					}
					checked={ selectedLevels.includes( level.id ) }
					onChange={ ( checked ) => toggleLevel( level.id, checked ) }
				/>
			) ) }
		</div>
	);
};

export default PMProLevels;
