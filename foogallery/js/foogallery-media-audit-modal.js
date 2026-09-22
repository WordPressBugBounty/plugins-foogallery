/* global FooGalleryMediaAudit, jQuery */
( function ( $, wp, config ) {
	'use strict';
	const {
		createElement: el,
		createRoot,
		useEffect,
		useState,
		useRef,
	} = wp.element;
	const { Modal, Spinner, Notice, Button } = wp.components;
	const { __, _n, sprintf } = wp.i18n;
	const results = document.getElementById( 'foogallery-media-audit-results' );
	if ( ! results ) {
		return;
	}
	const root = document.createElement( 'div' );
	document.body.appendChild( root );

	function Subject( { subject } ) {
		if ( ! subject ) {
			return null;
		}
		return el(
			'span',
			null,
			subject.edit_url
				? el( 'a', { href: subject.edit_url }, subject.label )
				: subject.label,
			subject.missing &&
				el(
					'small',
					null,
					' — ',
					__( 'Missing or deleted', 'foogallery' )
				)
		);
	}

	function FindingStats( { stats } ) {
		return el(
			'p',
			{ className: 'fga-where fga-stats', role: 'list' },
			...stats.map( ( label, index ) =>
				el(
					'span',
					{
						className:
							'fga-stat' +
							( index === stats.length - 1
								? ' fga-card-coverage'
								: '' ),
						key: index,
						role: 'listitem',
					},
					index > 0 &&
						el(
							'span',
							{
								className: 'fga-stat-divider',
								'aria-hidden': true,
							},
							'·'
						),
					el( 'span', { className: 'fga-stat-label' }, label )
				)
			)
		);
	}

	function AffectedCount( { data, galleryFinding } ) {
		if ( data.pages > 1 ) {
			return galleryFinding
				? sprintf(
						/* translators: 1: page number, 2: page count, 3: total galleries. */
						__(
							'Page %1$s of %2$s · %3$s galleries',
							'foogallery'
						),
						data.page.toLocaleString(),
						data.pages.toLocaleString(),
						data.total.toLocaleString()
				  )
				: sprintf(
						/* translators: 1: page number, 2: page count, 3: total image/gallery entries. */
						__(
							'Page %1$s of %2$s · %3$s image/gallery entries',
							'foogallery'
						),
						data.page.toLocaleString(),
						data.pages.toLocaleString(),
						data.total.toLocaleString()
				  );
		}
		return galleryFinding
			? sprintf(
					/* translators: %s: total galleries. */
					_n(
						'%s gallery',
						'%s galleries',
						data.total,
						'foogallery'
					),
					data.total.toLocaleString()
			  )
			: sprintf(
					/* translators: %s: total image/gallery entries. */
					_n(
						'%s image/gallery entry',
						'%s image/gallery entries',
						data.total,
						'foogallery'
					),
					data.total.toLocaleString()
			  );
	}

	function PrimarySubject( { item, galleryFinding } ) {
		if ( galleryFinding && item.gallery ) {
			return el( Subject, { subject: item.gallery } );
		}
		if ( item.image ) {
			return el( Subject, { subject: item.image } );
		}
		return __( 'Gallery configuration', 'foogallery' );
	}

	function FindingModal( { finding, onClose } ) {
		const listHeading = useRef( null );
		const previousPage = useRef( 1 );
		const [ page, setPage ] = useState( 1 );
		const [ data, setData ] = useState( null );
		const [ loading, setLoading ] = useState( true );
		const [ error, setError ] = useState( null );
		const [ retry, setRetry ] = useState( 0 );
		const [ revision, setRevision ] = useState( finding.revision );
		const referenceUrl = finding.referenceUrl;
		const stats = data?.stats || finding.stats;
		const galleryFinding = finding.rule.indexOf( 'FG-CFG-' ) === 0;
		useEffect( () => {
			let active = true;
			setLoading( true );
			setError( null );
			const request = $.ajax( {
				url: config.url,
				method: 'POST',
				dataType: 'json',
				timeout: 20000,
				data: {
					action: 'foogallery_media_audit_progress',
					operation: 'affected',
					nonce: config.nonce,
					rule: finding.rule,
					page,
					revision,
				},
			} )
				.done( ( response ) => {
					if ( active ) {
						setData( response.data );
						setLoading( false );
					}
				} )
				.fail( ( xhr ) => {
					if ( active ) {
						setError( {
							message:
								xhr.responseJSON?.data?.message ||
								__(
									'The affected images could not be loaded. Please try again.',
									'foogallery'
								),
							revision: xhr.responseJSON?.data?.revision,
						} );
						setLoading( false );
					}
				} );
			return () => {
				active = false;
				request.abort();
			};
		}, [ finding.rule, page, revision, retry ] );

		useEffect( () => {
			if ( data && data.page !== previousPage.current ) {
				listHeading.current?.scrollIntoView( { block: 'start' } );
				listHeading.current?.focus( { preventScroll: true } );
				previousPage.current = data.page;
			}
		}, [ data ] );

		return el(
			Modal,
			{
				title: data?.title || finding.title,
				icon: el( 'span', {
					className: 'fga-dot',
					'aria-hidden': true,
				} ),
				onRequestClose: onClose,
				className: 'foogallery-audit-modal fga-' + finding.severity,
			},
			el( FindingStats, { stats } ),
			el(
				'div',
				{ className: 'fga-modal-guidance' },
				el(
					'div',
					{ className: 'fga-modal-guidance-copy' },
					el( 'strong', null, __( 'How to fix:', 'foogallery' ) ),
					el( 'p', null, data?.guidance || finding.guidance )
				),
				referenceUrl &&
					el(
						Button,
						{
							variant: 'secondary',
							href: referenceUrl,
							target: '_blank',
							rel: 'noopener noreferrer',
							className: 'fga-modal-fix-button',
						},
						__( 'Fix this →', 'foogallery' )
					)
			),
			el(
				'h3',
				{
					ref: listHeading,
					tabIndex: -1,
					className: 'fga-modal-list-heading',
				},
				galleryFinding
					? __( 'Affected galleries', 'foogallery' )
					: __( 'Affected images', 'foogallery' )
			),
			error &&
				el(
					Notice,
					{ status: 'error', isDismissible: false },
					error.message,
					el(
						Button,
						{
							variant: 'secondary',
							onClick: () => {
								if ( error.revision ) {
									setData( null );
									setPage( 1 );
									setRevision( error.revision );
								} else {
									setRetry( retry + 1 );
								}
							},
						},
						error.revision
							? __( 'Load latest findings', 'foogallery' )
							: __( 'Try again', 'foogallery' )
					)
				),
			loading &&
				el(
					'div',
					{ className: 'fga-modal-loading', role: 'status' },
					el( Spinner ),
					galleryFinding
						? __( 'Loading affected galleries…', 'foogallery' )
						: __( 'Loading affected images…', 'foogallery' )
				),
			! loading &&
				! error &&
				data &&
				el(
					'div',
					null,
					el(
						'p',
						{ role: 'status', 'aria-live': 'polite' },
						el( AffectedCount, { data, galleryFinding } )
					),
					! data.items.length &&
						el(
							'p',
							null,
							galleryFinding
								? __(
										'No affected galleries are recorded for this finding.',
										'foogallery'
								  )
								: __(
										'No affected images are recorded for this finding.',
										'foogallery'
								  )
						),
					el(
						'ul',
						{ className: 'fga-modal-images' },
						...data.items.map( ( item ) =>
							el(
								'li',
								{
									key:
										( item.image?.id || 0 ) +
										'-' +
										( item.gallery?.id || 0 ),
								},
								el(
									'div',
									{
										className: 'fga-modal-thumbnail',
										'aria-hidden': true,
									},
									el( 'span', {
										className: galleryFinding
											? 'dashicons dashicons-format-gallery'
											: 'dashicons dashicons-format-image',
									} ),
									item.image?.thumbnail &&
										el( 'img', {
											src: item.image.thumbnail,
											alt: '',
											loading: 'lazy',
											onError: ( event ) => {
												event.currentTarget.hidden = true;
											},
										} )
								),
								el(
									'div',
									{ className: 'fga-modal-image-details' },
									el(
										'strong',
										null,
										el( PrimarySubject, {
											item,
											galleryFinding,
										} )
									),
									item.alt_text !== null &&
										el(
											'p',
											{ className: 'fga-modal-alt-text' },
											el(
												'strong',
												null,
												__( 'Alt text:', 'foogallery' )
											),
											' ',
											item.alt_text ||
												__( 'Empty', 'foogallery' )
										),
									item.details?.length > 0 &&
										el(
											'p',
											{
												className:
													'fga-modal-attachment-meta',
											},
											item.details.join( ' · ' )
										),
									item.gallery &&
										! galleryFinding &&
										el(
											'p',
											null,
											__( 'Gallery:', 'foogallery' ),
											' ',
											el( Subject, {
												subject: item.gallery,
											} )
										),
									item.sampled_url &&
										el(
											'a',
											{
												href: item.sampled_url,
												target: '_blank',
												rel: 'noopener noreferrer',
											},
											__(
												'Open image URL ↗',
												'foogallery'
											)
										)
								)
							)
						)
					),
					data.pages > 1 &&
						el(
							'nav',
							{
								className: 'fga-modal-pagination',
								'aria-label': galleryFinding
									? __(
											'Affected galleries pages',
											'foogallery'
									  )
									: __(
											'Affected images pages',
											'foogallery'
									  ),
							},
							el(
								Button,
								{
									variant: 'secondary',
									disabled: data.page <= 1,
									onClick: () => setPage( data.page - 1 ),
								},
								__( 'Previous', 'foogallery' )
							),
							el(
								Button,
								{
									variant: 'secondary',
									disabled: data.page >= data.pages,
									onClick: () => setPage( data.page + 1 ),
								},
								__( 'Next', 'foogallery' )
							)
						)
				)
		);
	}

	function Findings() {
		const [ finding, setFinding ] = useState( null );
		useEffect( () => {
			function open( event ) {
				const link = event.target.closest( '.fga-view-affected' );
				if (
					! link ||
					event.ctrlKey ||
					event.metaKey ||
					event.shiftKey ||
					event.altKey ||
					event.button
				) {
					return;
				}
				event.preventDefault();
				const card = link.closest( '.fga-group' );
				const referenceLink = card.querySelector(
					'.fga-rule-reference'
				);
				setFinding( {
					rule: link.dataset.auditRule,
					revision: link.dataset.auditRevision,
					severity:
						[ 'error', 'warning', 'info' ].find( ( severity ) =>
							card.classList.contains( 'fga-' + severity )
						) || 'info',
					title: card.querySelector( '.fga-heading-label' )
						.textContent,
					stats: Array.from(
						card.querySelectorAll( '.fga-stat-label' )
					).map( ( stat ) => stat.textContent.trim() ),
					guidance: card
						.querySelector( '.fga-fix' )
						.textContent.replace(
							card.querySelector( '.fga-fix strong' ).textContent,
							''
						)
						.trim(),
					referenceUrl: referenceLink?.href || '',
				} );
			}
			results.addEventListener( 'click', open );
			return () => results.removeEventListener( 'click', open );
		}, [] );
		return (
			finding &&
			el( FindingModal, {
				key: finding.rule + finding.revision,
				finding,
				onClose: () => setFinding( null ),
			} )
		);
	}
	createRoot( root ).render( el( Findings ) );
} )( jQuery, window.wp, FooGalleryMediaAudit );
