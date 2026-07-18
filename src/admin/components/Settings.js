import { useState, useEffect } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	SelectControl,
	TextControl,
	Button,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		apiFetch( { path: '/simple-lms/v1/settings' } ).then( ( response ) => {
			setSettings( response.settings || {} );
		} ).catch( ( error ) => {
			console.error( 'Failed to load settings', error );
			setSettings( {} );
		} );
	}, [] );

	if ( ! settings ) {
		return <Spinner />;
	}

	const handleSave = () => {
		setIsSaving( true );
		apiFetch( {
			path: '/simple-lms/v1/settings',
			method: 'POST',
			data: settings,
		} )
			.then( ( response ) => {
				setSettings( response.settings );
			} )
			.catch( ( error ) => {
				console.error( 'Failed to save settings', error );
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	};

	return (
		<div className="slms-settings-wrap p-6 max-w-4xl mx-auto">
			<div className="flex justify-between items-center mb-6">
				<h1 className="text-2xl font-bold m-0">SimpleLMS Settings</h1>
				<Button isPrimary isBusy={ isSaving } onClick={ handleSave }>
					{ isSaving ? 'Saving...' : 'Save Settings' }
				</Button>
			</div>

			<Panel>
				<PanelBody title="Access & Guarding" initialOpen={ true }>
					<SelectControl
						label="Default Course Guard Mode"
						help="The default access control behavior for newly created courses."
						value={ settings.default_guard_mode || 'enrolled' }
						options={ [
							{ label: 'Enrolled (Requires explicit enrollment)', value: 'enrolled' },
							{ label: 'Level (Requires PMPro membership)', value: 'level' },
							{ label: 'Public (Open to everyone)', value: 'public' },
						] }
						onChange={ ( val ) => setSettings( { ...settings, default_guard_mode: val } ) }
					/>
					<TextControl
						label="Checkout / Levels Page URL"
						help="Where to redirect unenrolled users who try to access guarded content."
						value={ settings.checkout_url || '' }
						onChange={ ( val ) => setSettings( { ...settings, checkout_url: val } ) }
					/>
					<TextControl
						label="Login Redirect URL"
						help="Where to redirect logged-out users. Leave blank for default WordPress login."
						value={ settings.login_redirect || '' }
						onChange={ ( val ) => setSettings( { ...settings, login_redirect: val } ) }
					/>
				</PanelBody>

				<PanelBody title="Certificates" initialOpen={ true }>
					<TextControl
						label="Gravity Forms Certificate Field IDs"
						help="Comma-separated list of Gravity Forms field IDs to use for course mapping on certificates."
						value={ settings.certificate_gf_fields || '' }
						onChange={ ( val ) => setSettings( { ...settings, certificate_gf_fields: val } ) }
					/>
				</PanelBody>
			</Panel>
		</div>
	);
}
