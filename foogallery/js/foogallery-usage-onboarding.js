/* global jQuery */
( function ( $, wp, config ) {
	'use strict';
	const section = document.getElementById( 'foogallery-usage-onboarding' );
	const form = document.querySelector( '#fs_connect .fs-actions form' );
	const permissions = document.querySelector(
		'#fs_connect .fs-permissions > ul'
	);
	if ( ! section || ! form || ! permissions || ! config ) {
		return;
	}
	const button = form.querySelector( '[type="submit"]' );
	const skip = document.querySelector( '#fs_connect #skip_activation' );
	let originalLabel = button.innerHTML;
	const __ = wp.i18n.__;
	const h = wp.element.createElement;
	const root = document.getElementById(
		'foogallery-usage-onboarding-choice'
	);
	let checked = section.dataset.consented === 'true';
	let busy = false;
	let error = '';
	let continuing = false;
	function render() {
		wp.element.render(
			h(
				wp.element.Fragment,
				null,
				h( wp.components.CheckboxControl, {
					label: __( 'Enable feature sharing', 'foogallery' ),
					checked,
					disabled: busy,
					onChange: ( value ) => {
						checked = value;
						render();
					},
				} ),
				error &&
					h(
						wp.components.Notice,
						{ status: 'error', isDismissible: false },
						error
					)
			),
			root
		);
	}
	function save( operation, proceed ) {
		if ( busy ) {
			return;
		}
		busy = true;
		error = '';
		button.disabled = true;
		render();
		$.ajax( {
			url: config.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			timeout: 15000,
			data: {
				action: 'foogallery_usage',
				operation,
				consent_version: config.consentVersion,
				nonce: config.nonce,
			},
		} )
			.done( ( response ) => {
				if ( response && response.success ) {
					continuing = true;
				} else {
					error = __(
						'Your feature-sharing choice could not be saved. Please try again or reload this page.',
						'foogallery'
					);
				}
			} )
			.fail( () => {
				error = __(
					'Your feature-sharing choice could not be confirmed. Please retry or check Settings → Improve before continuing.',
					'foogallery'
				);
			} )
			.always( () => {
				busy = false;
				button.disabled = false;
				button.classList.remove( 'fs-loading' );
				button.innerHTML = originalLabel;
				render();
				if ( continuing ) {
					proceed();
					continuing = false;
				}
			} );
	}
	function submitChoice( event ) {
		if ( continuing ) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		if ( ! form.reportValidity() ) {
			return;
		}
		save( checked ? 'enable' : 'pause', () => button.click() );
	}
	// Capture before the SDK starts its delayed loading state. Replay its entire
	// click/submit flow only after the local decision has been acknowledged.
	button.addEventListener(
		'click',
		( event ) => {
			if ( ! busy && ! continuing ) {
				originalLabel = button.innerHTML;
			}
			submitChoice( event );
		},
		true
	);
	form.addEventListener( 'submit', submitChoice, true );
	const license = document.querySelector( '#fs_connect #fs_license_key' );
	if ( license ) {
		// The SDK's license input sits outside the form and uses a synthetic jQuery
		// click on keypress. Use a native click so it follows the same consent gate.
		license.addEventListener(
			'keydown',
			( event ) => {
				if ( event.key === 'Enter' && ! button.disabled ) {
					event.preventDefault();
					event.stopImmediatePropagation();
					button.click();
				}
			},
			true
		);
	}
	if ( skip ) {
		skip.addEventListener(
			'click',
			( event ) => {
				event.preventDefault();
				event.stopImmediatePropagation();
				save( 'pause', () => {
					window.location.href = skip.href;
				} );
			},
			true
		);
	}
	const row = document.createElement( 'li' );
	row.className = 'fs-permission foogallery-usage-permission';
	const icon = document.createElement( 'i' );
	icon.className = 'dashicons dashicons-block-default';
	icon.setAttribute( 'aria-hidden', 'true' );
	row.append( icon, section );
	permissions.insertBefore(
		row,
		permissions.querySelector( '#fs_permission_newsletter' )
	);
	render();
	section.hidden = false;
} )( jQuery, window.wp, window.FooGalleryUsageOnboarding );
