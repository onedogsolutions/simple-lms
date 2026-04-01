/**
 * LMS Account Dashboard – Frontend JavaScript
 *
 * Delegates to the slms-student-dashboard script logic.
 * Handles tab switching and the "Update Password" checkbox toggle.
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

					tabLinks.forEach( function ( l ) {
						l.classList.remove( 'active' );
						l.setAttribute( 'aria-selected', 'false' );
					} );
					tabPanes.forEach( function ( p ) {
						p.classList.remove( 'active' );
					} );

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

						var passInputs = passwordFields.querySelectorAll( 'input[type="password"]' );
						passInputs.forEach( function ( input ) {
							input.value = '';
						} );
					}
				} );
			}

		} );

	} );

}() );
