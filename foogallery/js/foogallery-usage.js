/* global jQuery */
( function ( $, wp, config ) {
	'use strict';
	if ( ! config || ! config.ajaxUrl ) {
		return;
	}
	const __ = wp.i18n.__;
	const sprintf = wp.i18n.sprintf;
	const h = wp.element.createElement;
	const { useState, useEffect, useRef } = wp.element;
	const { Button, CheckboxControl, Notice, Spinner, Modal } = wp.components;
	const invitation = $( '.foogallery-usage-invitation' );
	function invitationVisibility() {
		invitation.prop( 'hidden', window.location.hash === '#improve' );
	}
	$( window ).on( 'hashchange.foogalleryUsage', invitationVisibility );
	invitationVisibility();
	function post( operation ) {
		return $.ajax( {
			url: config.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'foogallery_usage',
				operation,
				consent_version: config.consentVersion,
				nonce: config.nonce,
			},
		} );
	}
	invitation.on( 'click', '.notice-dismiss', function () {
		post( 'dismiss' );
	} );
	const root = document.getElementById( 'foogallery-usage' );
	if ( ! root ) {
		return;
	}
	function label( value ) {
		const labels = {
			enabled: __( 'Enabled', 'foogallery' ),
			disabled: __( 'Disabled', 'foogallery' ),
			unavailable: __( 'Unavailable', 'foogallery' ),
			unknown: __( 'Unknown', 'foogallery' ),
			unmeasured: __( 'Not yet measured', 'foogallery' ),
		};
		return labels[ value ] || value;
	}
	function date( value, emptyLabel ) {
		const fallback = emptyLabel || __( 'Not scheduled', 'foogallery' );
		if ( ! value ) {
			return fallback;
		}
		const parsed = new Date(
			'number' === typeof value ? value * 1000 : value
		);
		return Number.isNaN( parsed.getTime() )
			? fallback
			: parsed.toLocaleString();
	}
	function parse( body ) {
		try {
			return JSON.parse( body || '{}' );
		} catch {
			return {};
		}
	}
	function download( source, type, hash ) {
		return (
			config.downloadUrl +
			'&source=' +
			encodeURIComponent( source ) +
			'&type=' +
			type +
			'&hash=' +
			encodeURIComponent( hash || '' )
		);
	}
	function Packet( { title, source, packet, previewHash } ) {
		if ( ! packet || ! packet.body ) {
			return null;
		}
		const hash = packet.body_sha256 || previewHash || '';
		return h(
			'details',
			{ className: 'foogallery-usage-packet' },
			h( 'summary', null, title ),
			source === 'preview' &&
				h(
					'p',
					null,
					__(
						'Local preview only. Identity and sequence placeholders are assigned after opt-in. This preview has not been sent.',
						'foogallery'
					)
				),
			h( 'pre', { tabIndex: 0, 'aria-label': title }, packet.body ),
			h(
				'p',
				null,
				__( 'Body SHA-256:', 'foogallery' ),
				' ',
				h( 'code', null, hash )
			),
			h(
				'p',
				null,
				h(
					'a',
					{ href: download( source, 'body', hash ) },
					__( 'Download exact JSON body', 'foogallery' )
				),
				' · ',
				h(
					'a',
					{ href: download( source, 'request', hash ) },
					__(
						'Download request details (credentials masked)',
						'foogallery'
					)
				)
			),
			packet.receipt &&
				h(
					'details',
					null,
					h(
						'summary',
						null,
						__( 'Authenticated receipt', 'foogallery' )
					),
					h( 'pre', null, JSON.stringify( packet.receipt, null, 2 ) )
				)
		);
	}
	function Catalog( { catalog, measurement } ) {
		const features = catalog.features || {};
		const groups = [
			{
				key: 'modules',
				label: __( 'Active modules', 'foogallery' ),
				ids: Object.keys( catalog.modules || {} ),
			},
			{
				key: 'features',
				label: __( 'Global settings', 'foogallery' ),
				ids: Object.keys( features ).filter( ( id ) =>
					id.startsWith( 'settings.' )
				),
			},
			{
				key: 'features',
				label: __( 'Datasources', 'foogallery' ),
				ids: Object.keys( features ).filter( ( id ) =>
					id.startsWith( 'datasource.' )
				),
			},
			{
				key: 'features',
				label: __( 'Layouts', 'foogallery' ),
				ids: Object.keys( features ).filter( ( id ) =>
					id.startsWith( 'layout.' )
				),
			},
			{
				key: 'features',
				label: __( 'Configured features', 'foogallery' ),
				ids: Object.keys( features ).filter(
					( id ) =>
						! id.startsWith( 'settings.' ) &&
						! id.startsWith( 'datasource.' ) &&
						! id.startsWith( 'layout.' )
				),
			},
			{
				key: 'actions',
				label: __( 'Recently used admin tools', 'foogallery' ),
				ids: Object.keys( catalog.actions || {} ),
			},
		];
		return h(
			'div',
			{ className: 'foogallery-usage-catalog' },
			groups.map( ( { key: group, label: title, ids } ) =>
				h(
					'details',
					{ key: title },
					h( 'summary', null, title + ' (' + ids.length + ')' ),
					h(
						'table',
						{ className: 'widefat striped' },
						h(
							'thead',
							null,
							h(
								'tr',
								null,
								h(
									'th',
									null,
									__( 'Measurement', 'foogallery' )
								),
								h( 'th', null, __( 'State', 'foogallery' ) )
							)
						),
						h(
							'tbody',
							null,
							ids.map( ( id ) => {
								const definition = catalog[ group ][ id ];
								return h(
									'tr',
									{ key: id },
									h(
										'td',
										null,
										h( 'strong', null, definition.label ),
										h( 'br' ),
										h(
											'small',
											null,
											definition.description
										),
										h( 'br' ),
										h( 'code', null, id )
									),
									h(
										'td',
										null,
										label(
											measurement[ group ] &&
												measurement[ group ][ id ]
												? measurement[ group ][ id ]
												: 'unmeasured'
										)
									)
								);
							} )
						)
					)
				)
			)
		);
	}
	function Improve() {
		const [ status, setStatus ] = useState( null );
		const [ busy, setBusy ] = useState( false );
		const [ error, setError ] = useState( '' );
		const [ confirmed, setConfirmed ] = useState( false );
		const [ previewOpen, setPreviewOpen ] = useState( false );
		const [ previewBody, setPreviewBody ] = useState( '' );
		const [ lastReportOpen, setLastReportOpen ] = useState( false );
		const [ lastReportBody, setLastReportBody ] = useState( '' );
		const [ lastReportReceipt, setLastReportReceipt ] = useState( '' );
		const [ deletePrompt, setDeletePrompt ] = useState( false );
		const requestRef = useRef( null );
		const inFlight = useRef( false );
		const previewActive = useRef( false );
		const pollUntil = useRef( 0 );
		const timer = useRef( null );
		function request( operation ) {
			if ( inFlight.current ) {
				return;
			}
			window.clearTimeout( timer.current );
			inFlight.current = true;
			setBusy( true );
			setError( '' );
			if ( operation === 'preview_start' ) {
				previewActive.current = true;
				setPreviewBody( '' );
				setPreviewOpen( true );
			}
			if ( operation === 'last_report' ) {
				setLastReportBody( '' );
				setLastReportReceipt( '' );
				setLastReportOpen( true );
			}
			if (
				[ 'enable', 'send', 'delete', 'migrate' ].includes( operation )
			) {
				pollUntil.current = Date.now() + 60000;
			}
			post( operation )
				.done( ( response ) => {
					if ( ! response || ! response.success ) {
						setError(
							response && response.data && response.data.message
								? response.data.message
								: __(
										'The operation could not be completed. Reload this page before trying again.',
										'foogallery'
								  )
						);
						previewActive.current = false;
						return;
					}
					const data = response.data;

					if ( operation === 'last_report' ) {
						try {
							setLastReportBody(
								JSON.stringify(
									JSON.parse( data.last_report_body ),
									null,
									2
								)
							);
							setLastReportReceipt(
								JSON.stringify(
									data.last_report_receipt || {},
									null,
									2
								)
							);
						} catch {
							setError(
								__(
									'The last report could not be read.',
									'foogallery'
								)
							);
						}
						return;
					}
					setStatus( data );
					if ( data.dismissed ) {
						invitation.remove();
					}
					if ( previewActive.current && data.preview_in_progress ) {
						timer.current = window.setTimeout(
							() => request( 'preview_step' ),
							300
						);
					} else {
						if ( previewActive.current ) {
							try {
								setPreviewBody(
									JSON.stringify(
										JSON.parse( data.preview_body ),
										null,
										2
									)
								);
							} catch {
								setError(
									__(
										'The local report could not be read. Please generate it again.',
										'foogallery'
									)
								);
							}
						}
						previewActive.current = false;
						if (
							Date.now() < pollUntil.current &&
							[
								'collecting',
								'sending',
								'deleting',
								'deletion_pending',
							].includes( data.phase )
						) {
							timer.current = window.setTimeout(
								() => request( 'status' ),
								10000
							);
						}
					}
				} )
				.fail( ( xhr ) => {
					previewActive.current = false;
					setError(
						xhr.responseJSON &&
							xhr.responseJSON.data &&
							xhr.responseJSON.data.message
							? xhr.responseJSON.data.message
							: __(
									'The response was interrupted. The operation may have completed; reload this page to check. A timed-out report may already have reached the service.',
									'foogallery'
							  )
					);
				} )
				.always( () => {
					inFlight.current = false;
					if ( ! previewActive.current ) {
						setBusy( false );
					}
				} );
		}
		requestRef.current = request;
		useEffect( () => {
			requestRef.current( 'status' );
			return () => {
				window.clearTimeout( timer.current );
			};
		}, [] );
		const s = status || {};
		const measurement = parse(
			s.preview_body || ( s.outbox && s.outbox.body )
		);
		const action = ( operation, text, disabled, extra ) =>
			h(
				Button,
				Object.assign(
					{
						isSecondary: true,
						disabled: busy || disabled,
						onClick: () => request( operation ),
					},
					extra
				),
				text
			);
		const gates = {
			clone: __(
				'The site address changed. Reporting is paused until you choose how to handle this copy.',
				'foogallery'
			),
			environment: __(
				'Reporting is disabled by an environment policy filter.',
				'foogallery'
			),
			disabled: __(
				'Reporting is disabled by site or network configuration.',
				'foogallery'
			),
			policy: __(
				'The collection policy changed. Review the new disclosure and explicitly enable sharing again.',
				'foogallery'
			),
		};
		const statusErrors = {
			service_rejected: __(
				'The usage service could not accept this report. No report was recorded, and automatic delivery has been paused. Please contact FooGallery support if the problem continues.',
				'foogallery'
			),
		};
		let activityLabel = __(
			'Feature sharing is off. You can generate a local report without sending it.',
			'foogallery'
		);
		if ( [ 'deleting', 'deletion_pending' ].includes( s.phase ) ) {
			activityLabel = __(
				'A request to stop sharing and anonymize retained server data is being processed.',
				'foogallery'
			);
		} else if ( s.preview_body ) {
			activityLabel = __(
				'A local feature report is ready for review. It has not been sent.',
				'foogallery'
			);
		} else if ( s.outbox ) {
			activityLabel = __(
				'A feature report has been generated and is waiting to be delivered.',
				'foogallery'
			);
		} else if ( 'collecting' === s.phase ) {
			activityLabel = __(
				'FooGallery is preparing a feature report.',
				'foogallery'
			);
		} else if ( s.acknowledged ) {
			activityLabel = __(
				'The latest feature report was delivered successfully.',
				'foogallery'
			);
		} else if ( s.consent ) {
			activityLabel = __(
				'Sharing is enabled. FooGallery will prepare the next report automatically.',
				'foogallery'
			);
		}

		return h(
			'div',
			{ 'aria-busy': busy },
			h(
				'p',
				null,
				sprintf(
					/* translators: %s: White-labelled plugin name. */
					__(
						'Help improve %s by sharing feature usage, so we improve the features that are used the most. Sharing is optional, and you can preview what is sent, stop sharing or request deletion at any time.',
						'foogallery'
					),
					config.pluginName
				)
			),
			h(
				'p',
				null,
				__(
					'Feature sharing is separate from Freemius account and diagnostic sharing. An earlier Freemius opt-in or license activation does not enable these reports.',
					'foogallery'
				)
			),
			status &&
				h(
					'div',
					{ className: 'foogallery-usage-consent' },
					s.consent &&
						h(
							'p',
							{
								className: 'foogallery-usage-enabled',
								role: 'status',
							},
							h( 'span', {
								className: 'dashicons dashicons-yes-alt',
								'aria-hidden': true,
							} ),
							h(
								'strong',
								null,
								__(
									'Sharing Enabled - thank you!',
									'foogallery'
								)
							)
						),
					! s.consent &&
						h( CheckboxControl, {
							className: 'foogallery-usage-opt-in',
							label: __(
								'I agree to share FooGallery feature reports with FooPlugins.',
								'foogallery'
							),
							checked: confirmed,
							onChange: setConfirmed,
						} ),
					h(
						'div',
						{ className: 'foogallery-usage-actions' },

						! s.consent &&
							action(
								'enable',
								__( 'Opt-in to feature sharing', 'foogallery' ),
								! confirmed ||
									! s.available ||
									[ 'deleting', 'deletion_pending' ].includes(
										s.phase
									),
								{ isPrimary: true, isSecondary: false }
							),
						s.consent &&
							action(
								'pause',
								__( 'Opt out of sharing', 'foogallery' ),
								false
							),
						( s.consent || s.site_id ) &&
							h(
								Button,
								{
									isDestructive: true,
									disabled: busy,
									onClick: () => setDeletePrompt( true ),
								},
								__( 'Stop and request deletion', 'foogallery' )
							)
					)
				),
			deletePrompt &&
				h(
					Notice,
					{ status: 'warning', isDismissible: false },
					h(
						'p',
						null,
						__(
							'Stop sharing and request deletion of your feature-sharing data?',
							'foogallery'
						)
					),
					h(
						'div',
						{ className: 'foogallery-usage-inline-actions' },
						h(
							Button,
							{
								isDestructive: true,
								isPrimary: true,
								onClick: () => {
									setDeletePrompt( false );
									request( 'delete' );
								},
							},
							__( 'Stop and request deletion', 'foogallery' )
						),
						h(
							Button,
							{
								isSecondary: true,
								onClick: () => setDeletePrompt( false ),
							},
							__( 'Cancel', 'foogallery' )
						)
					)
				),
			h(
				'details',
				{ className: 'foogallery-usage-disclosure' },
				h(
					'summary',
					null,
					__(
						'See more details about what is shared and why',
						'foogallery'
					)
				),
				h(
					'p',
					null,
					sprintf(
						/* translators: %s: White-labelled plugin name. */
						__(
							'After you opt in, the plugin collects and sends a feature usage report to our servers, and then continues to send it weekly. The usage report scans your %s settings and galleries to build up a list of features you actively use. We use this data to help us decide which features our users actually use, so we can make better development decisions.',
							'foogallery'
						),
						config.pluginName
					)
				),
				h(
					'p',
					null,
					__( 'The usage report includes:', 'foogallery' )
				),
				h(
					'ul',
					null,
					h(
						'li',
						null,
						sprintf(
							/* translators: %s: White-labelled plugin name. */
							__(
								'Summary of the %s features you are using (including add-ons).',
								'foogallery'
							),
							config.pluginName
						)
					),
					h(
						'li',
						null,
						__( 'Summary of your gallery settings.', 'foogallery' )
					),
					h(
						'li',
						null,
						__(
							'Aggregate measurements, without individual gallery or media records.',
							'foogallery'
						)
					),
					h(
						'li',
						null,
						__(
							'A randomly generated website ID, which we use to identify your site. No user identities are included.',
							'foogallery'
						)
					),
					h(
						'li',
						null,
						__(
							'Whether successful use of Bulk Copy, Import, Export, Migration, or Media Audit was observed in the last 30 days after consent, without identifying the user or galleries involved.',
							'foogallery'
						)
					),
					h(
						'li',
						null,
						__(
							'The WordPress environment type (production, staging, development or local).',
							'foogallery'
						)
					)
				),
				h(
					'p',
					null,
					__(
						'WordPress also sends its version and your site address in its default User-Agent. FooPlugins stores that header to help deduplicate sites: reporting is site-identifiable, not anonymous. Cloudflare receives the connecting server IP and hosts the service infrastructure.',
						'foogallery'
					)
				),
				h(
					'p',
					null,
					__( 'The report will NEVER contain:', 'foogallery' )
				),
				h(
					'ul',
					null,
					h(
						'li',
						null,
						__(
							'Personal information about your users.',
							'foogallery'
						)
					),
					h(
						'li',
						null,
						__(
							'Unrelated site settings or settings from other third-party plugins.',
							'foogallery'
						)
					),
					h(
						'li',
						null,
						__(
							'Details or content from individual posts or media, including image IDs, filenames, URLs, alt text and captions.',
							'foogallery'
						)
					),
					h(
						'li',
						null,
						__(
							'Visitor activity or individual user activity logs.',
							'foogallery'
						)
					)
				),
				h(
					'p',
					null,
					__(
						'Sharing is optional and can be turned off at any time. You can pause or request deletion. An authenticated deletion request immediately revokes the reporting identity and removes the site address, User-Agent, random site identifier and raw report from the retained measurements. FooPlugins may keep compact anonymous feature measurements for aggregate reporting under the normal 90-day retention window while it reviews and closes the request. Pausing keeps the reporting identity so sharing can resume.',
						'foogallery'
					)
				),
				h(
					'p',
					null,
					h(
						'a',
						{
							href: 'https://fooplugins.com/privacy-policy/',
							target: '_blank',
							rel: 'noopener noreferrer',
						},
						__( 'FooPlugins privacy policy', 'foogallery' )
					),
					' · ',
					h(
						'a',
						{
							href: 'https://fooplugins.com/terms-and-conditions/',
							target: '_blank',
							rel: 'noopener noreferrer',
						},
						__( 'Service terms', 'foogallery' )
					)
				)
			),
			h(
				'div',
				{ className: 'foogallery-usage-heading' },
				h( 'h3', null, __( 'Sharing details', 'foogallery' ) ),
				busy && h( Spinner )
			),
			status && h( 'p', { role: 'status' }, activityLabel ),
			previewOpen &&
				h(
					Modal,
					{
						title: __( 'Local report (not sent)', 'foogallery' ),
						className: 'foogallery-usage-preview-modal',
						onRequestClose: () => setPreviewOpen( false ),
					},
					h(
						'p',
						null,
						__(
							'This report is generated locally and has not been sent.',
							'foogallery'
						)
					),
					error &&
						h(
							Notice,
							{ status: 'error', isDismissible: false },
							error
						),
					! error &&
						! previewBody &&
						h(
							'div',
							{ role: 'status' },
							h( Spinner ),
							__( 'Generating local report…', 'foogallery' )
						),
					previewBody &&
						h(
							'pre',
							{
								tabIndex: 0,
								'aria-label': __(
									'Local report JSON',
									'foogallery'
								),
							},
							previewBody
						)
				),
			lastReportOpen &&
				h(
					Modal,
					{
						title: __( 'Last report sent', 'foogallery' ),
						className: 'foogallery-usage-preview-modal',
						onRequestClose: () => setLastReportOpen( false ),
					},
					error &&
						h(
							Notice,
							{ status: 'error', isDismissible: false },
							error
						),
					! error &&
						! lastReportBody &&
						h(
							'div',
							{ role: 'status' },
							h( Spinner ),
							__( 'Loading last report…', 'foogallery' )
						),
					lastReportBody &&
						h(
							'div',
							null,
							h(
								'pre',
								{
									tabIndex: 0,
									'aria-label': __(
										'Last report JSON',
										'foogallery'
									),
								},
								lastReportBody
							),
							lastReportReceipt &&
								h(
									'details',
									null,
									h(
										'summary',
										null,
										__(
											'Authenticated receipt',
											'foogallery'
										)
									),
									h( 'pre', null, lastReportReceipt )
								)
						)
				),
			error &&
				! previewOpen &&
				! lastReportOpen &&
				h( Notice, { status: 'error', isDismissible: false }, error ),
			status &&
				h(
					'div',
					null,

					s.gate &&
						h(
							Notice,
							{ status: 'warning', isDismissible: false },
							gates[ s.gate ] || s.gate
						),
					s.last_error &&
						h(
							Notice,
							{ status: 'warning', isDismissible: false },
							statusErrors[ s.last_error ] || s.last_error
						),
					h(
						'dl',
						{ className: 'foogallery-usage-status' },
						h(
							'dt',
							null,
							__( 'Next delivery attempt', 'foogallery' )
						),
						h(
							'dd',
							null,
							date( s.next_attempt || s.next_collection )
						),
						h(
							'dt',
							null,
							__( 'Last successful delivery', 'foogallery' )
						),
						h(
							'dd',
							null,
							date(
								s.acknowledged &&
									s.acknowledged.receipt &&
									s.acknowledged.receipt.received_at,
								__( 'No successful delivery yet', 'foogallery' )
							)
						),
						h( 'dt', null, __( 'Destination URL', 'foogallery' ) ),
						h( 'dd', null, h( 'code', null, s.endpoint ) )
					),

					[ 'clone', 'site_changed' ].includes( s.gate ) &&
						h(
							'div',
							null,
							h(
								'p',
								null,
								__(
									'For a migration, retain this identity and stop reporting from the old installation. For an independent copy, discard the copied identity locally; the original site’s server data will not be deleted. Enable sharing separately afterward.',
									'foogallery'
								)
							),
							action(
								'migrate',
								__(
									'This is the same site, migrated',
									'foogallery'
								),
								false
							),
							action(
								'independent',
								__(
									'This is an independent copy',
									'foogallery'
								),
								false
							)
						),

					h( Packet, {
						title: __(
							'Prepared report (delivery pending or unconfirmed)',
							'foogallery'
						),
						source: 'outbox',
						packet: s.outbox,
					} ),
					h(
						'div',
						{ className: 'foogallery-usage-actions' },
						action(
							'preview_start',
							__( 'Generate local report', 'foogallery' ),
							false
						),
						s.acknowledged &&
							h(
								Button,
								{
									isLink: true,
									disabled: busy,
									onClick: () => request( 'last_report' ),
								},
								__( 'View last report', 'foogallery' )
							)
					),
					h( 'h3', null, __( 'Feature catalog', 'foogallery' ) ),
					h( Catalog, { catalog: s.catalog || {}, measurement } )
				)
		);
	}
	wp.element.render( h( Improve ), root );
} )( jQuery, window.wp, window.FooGalleryUsageConfig );
