/**
 * Student Dashboard – Frontend JavaScript
 *
 * Responsibilities:
 *   1. Tab switching: clicking a .slms-tab-link activates the corresponding
 *      .slms-tab-pane and deactivates all others.
 *   2. Password toggle: the "Update Password" checkbox reveals/hides the
 *      New Password / Confirm Password fields.
 *
 * Vanilla JS only – no jQuery dependency.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		var dashboards = document.querySelectorAll( '.slms-student-dashboard' );

		dashboards.forEach( function ( dashboard ) {

			// ── Tab Switching ────────────────────────────────────────────────
			var tabLinks = dashboard.querySelectorAll( '.slms-tab-link' );
			var tabPanes = dashboard.querySelectorAll( '.slms-tab-pane' );

			tabLinks.forEach( function ( link ) {
				link.addEventListener( 'click', function () {
					var targetId = 'slms-tab-' + this.getAttribute( 'data-tab' );

					// Deactivate all tabs and panes within this dashboard instance
					tabLinks.forEach( function ( l ) {
						l.classList.remove( 'active' );
						l.setAttribute( 'aria-selected', 'false' );
					} );
					tabPanes.forEach( function ( p ) {
						p.classList.remove( 'active' );
					} );

					// Activate clicked tab and its matching pane
					this.classList.add( 'active' );
					this.setAttribute( 'aria-selected', 'true' );

					var targetPane = dashboard.querySelector( '#' + targetId );
					if ( targetPane ) {
						targetPane.classList.add( 'active' );
					}
				} );
			} );

			// ── Password Toggle ──────────────────────────────────────────────
			var passwordCheckbox = dashboard.querySelector( '#slms_update_password' );
			var passwordFields   = dashboard.querySelector( '#slms-password-fields' );

			if ( passwordCheckbox && passwordFields ) {

				// Set initial state
				if ( ! passwordCheckbox.checked ) {
					passwordFields.classList.add( 'slms-hidden' );
					passwordFields.setAttribute( 'aria-hidden', 'true' );
				}

				passwordCheckbox.addEventListener( 'change', function () {
					if ( this.checked ) {
						passwordFields.classList.remove( 'slms-hidden' );
						passwordFields.setAttribute( 'aria-hidden', 'false' );
					} else {
						passwordFields.classList.add( 'slms-hidden' );
						passwordFields.setAttribute( 'aria-hidden', 'true' );

						// Clear the password inputs when hiding so they are not
						// inadvertently submitted as empty strings
						var passInputs = passwordFields.querySelectorAll( 'input[type="password"]' );
						passInputs.forEach( function ( input ) {
							input.value = '';
						} );
					}
				} );
			}

		} ); // end dashboards.forEach

	} ); // end DOMContentLoaded

}() );
