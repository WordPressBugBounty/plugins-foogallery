/* global FooGalleryMediaAudit, jQuery */
( function ( $, wp, config ) {
	'use strict';
	const { createElement: el, createRoot, useEffect, useState } = wp.element;
	const { Spinner, Notice, Button } = wp.components;
	const { __, sprintf } = wp.i18n;
	const mount = document.getElementById( 'foogallery-media-audit-progress' );
	if ( ! mount ) {
		return;
	}
	const results = document.getElementById( 'foogallery-media-audit-results' );
	const form = document.querySelector( '[data-audit-action="run"]' );

	function Progress() {
		const [ status, setStatus ] = useState( config.initial );
		const [ error, setError ] = useState( '' );
		const [ retry, setRetry ] = useState( 0 );
		useEffect( () => {
			let timer;
			let request;
			let stopped = false;
			let failures = 0;
			function accept( data ) {
				setStatus( data );
				results.hidden = data.running;
				if ( ! data.running && typeof data.html === 'string' ) {
					// HTML is rendered and escaped by the authorized PHP report renderer.
					results.innerHTML = data.html;
				}
				form.querySelector( '[type="submit"]' ).disabled = data.running;
				for ( const action of [ 'download', 'clear' ] ) {
					document.querySelector(
						'[data-audit-action="' + action + '"] [type="submit"]'
					).disabled = ! data.has_report;
				}
			}
			function poll( operation = 'status' ) {
				request = $.ajax( {
					url: config.url,
					method: 'POST',
					dataType: 'json',
					timeout: 15000,
					data: {
						action: 'foogallery_media_audit_progress',
						operation,
						nonce: config.nonce,
					},
				} )
					.done( ( response ) => {
						if ( stopped ) {
							return;
						}
						failures = 0;
						setError( '' );
						accept( response.data );
						if ( response.data.running ) {
							timer = setTimeout( poll, 2000 );
						}
					} )
					.fail( ( xhr ) => {
						if ( stopped ) {
							return;
						}
						const message = xhr.responseJSON?.data?.message;
						setError(
							message ||
								__(
									'Connection interrupted. Live updates will retry automatically.',
									'foogallery'
								)
						);
						if ( xhr.status === 400 || xhr.status === 403 ) {
							form.querySelector(
								'[type="submit"]'
							).disabled = false;
							setStatus( ( current ) => ( {
								...current,
								running: false,
								state: 'idle',
							} ) );
							results.hidden = false;
							return;
						}
						// A lost start response is resolved by status; never submit another start blindly.
						timer = setTimeout(
							poll,
							Math.min( 15000, 2000 * ++failures )
						);
					} );
			}
			function start( event ) {
				event.preventDefault();
				clearTimeout( timer );
				if ( request ) {
					request.abort();
					clearTimeout( timer );
				}
				setError( '' );
				accept( {
					...config.initial,
					running: true,
					state: 'running',
					phase: 'galleries',
					galleries: 0,
					images: 0,
				} );
				poll( 'start' );
			}
			form.addEventListener( 'submit', start );
			if ( config.initial.running || retry ) {
				poll();
			}
			return () => {
				stopped = true;
				clearTimeout( timer );
				request?.abort();
				form.removeEventListener( 'submit', start );
			};
		}, [ retry ] );

		let phaseLabel =
			status.phase === 'images'
				? __( 'Checking image sources…', 'foogallery' )
				: __( 'Checking galleries and their images…', 'foogallery' );
		if ( status.gallery_scope ) {
			phaseLabel = __( 'A gallery audit is running…', 'foogallery' );
		}
		return el(
			'div',
			null,
			error &&
				el(
					Notice,
					{ status: 'warning', isDismissible: false },
					error,
					el(
						Button,
						{
							variant: 'link',
							onClick: () => setRetry( retry + 1 ),
						},
						__( 'Retry now', 'foogallery' )
					)
				),
			status.running &&
				el(
					'div',
					{
						role: 'status',
						'aria-live': 'polite',
						'aria-atomic': true,
					},
					el(
						'div',
						{ className: 'foogallery-media-audit-working' },
						el( Spinner ),
						el( 'strong', null, phaseLabel )
					),
					! status.gallery_scope &&
						el(
							'p',
							null,
							sprintf(
								/* translators: 1: galleries checked, 2: distinct images checked. */
								__(
									'Galleries checked: %1$s · Images checked: %2$s',
									'foogallery'
								),
								status.galleries.toLocaleString(),
								status.images.toLocaleString()
							)
						),
					el(
						'p',
						null,
						__(
							'Results will appear here automatically when the audit finishes.',
							'foogallery'
						)
					)
				),
			! status.running &&
				status.state === 'finished' &&
				el(
					Notice,
					{ status: 'info', isDismissible: false },
					status.partial
						? __(
								'Audit finished. Some checks have limited coverage; review the findings below.',
								'foogallery'
						  )
						: __(
								'Audit complete. Your report is ready below.',
								'foogallery'
						  )
				),
			! status.running &&
				status.state === 'failed' &&
				el(
					Notice,
					{ status: 'warning', isDismissible: false },
					status.has_report
						? __(
								'The audit could not produce a new report. Your saved report is shown below. Start another audit to try again.',
								'foogallery'
						  )
						: __(
								'The audit could not produce a report. Start another audit to try again.',
								'foogallery'
						  )
				)
		);
	}
	createRoot( mount ).render( el( Progress ) );
} )( jQuery, window.wp, FooGalleryMediaAudit );
