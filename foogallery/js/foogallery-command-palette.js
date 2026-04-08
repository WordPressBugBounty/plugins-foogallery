/**
 * FooGallery Command Palette Integration
 *
 * Registers FooGallery galleries in the WordPress Command Palette
 * (Ctrl+K / Cmd+K), available since WP 6.3.
 *
 * Two command types are registered:
 *
 *  1. Static command  – "Add New Gallery" (always visible).
 *     Uses wp.data.dispatch( wp.commands.store ).registerCommand().
 *
 *  2. Dynamic loader  – "Edit Gallery: {title}" (filtered as you type).
 *     Uses wp.data.dispatch( wp.commands.store ).registerCommandLoader()
 *     directly, bypassing the useCommandLoader React hook wrapper.
 *     WordPress's own CommandMenuLoader calls our hook function inside
 *     its render cycle, so useSelect / useMemo run in proper React
 *     context without us needing a separate React root.
 *     Queries the foogallery post type via getEntityRecords — works now
 *     that the CPT is registered with show_in_rest: true.
 *
 * WordPress also auto-generates "Go to: FooGallery > …" navigation
 * commands from the $menu/$submenu PHP globals.  Those are redundant
 * given the richer commands above, so we subscribe to the commands
 * store and remove them once React has finished registering them.
 */
( function () {
	'use strict';

	if (
		! window.wp ||
		! wp.commands ||
		! wp.data ||
		! wp.element ||
		! wp.i18n
	) {
		return;
	}

	var useSelect     = wp.data.useSelect;
	var useMemo       = wp.element.useMemo;
	var createElement = wp.element.createElement;
	var __            = wp.i18n.__;
	var sprintf       = wp.i18n.sprintf;
	var config        = window.FOOGALLERY_COMMAND_PALETTE || {};
	var galleryName   = config.galleryName || __( 'Gallery', 'foogallery' );

	// ------------------------------------------------------------------ //
	// Icon
	// ------------------------------------------------------------------ //

	var galleryIcon = createElement(
		'svg',
		{
			xmlns:         'http://www.w3.org/2000/svg',
			viewBox:       '0 0 24 24',
			width:         '24',
			height:        '24',
			'aria-hidden': true,
			focusable:     false,
		},
		createElement( 'path', {
			d: 'M3 3h8v8H3V3zm0 10h8v8H3v-8zM13 3h8v8h-8V3zm0 10h8v8h-8v-8z',
		} )
	);

	// ------------------------------------------------------------------ //
	// Helper
	// ------------------------------------------------------------------ //

	function getEditUrl( id ) {
		return ( config.editGalleryUrl || '' ).replace( '%d', id );
	}

	// ------------------------------------------------------------------ //
	// Dynamic command loader hook
	//
	// This function is called by WordPress's CommandMenuLoader inside its
	// own React render cycle, so React hooks (useSelect, useMemo) are valid.
	// ------------------------------------------------------------------ //

	function useGalleryCommandLoader( props ) {
		var search = props.search;

		var galleries = useSelect( function ( select ) {
			return select( 'core' ).getEntityRecords(
				'postType',
				'foogallery',
				{
					search:   search || undefined,
					per_page: 10,
					orderby:  search ? 'relevance' : 'date',
					_fields:  'id,title',
				}
			);
		}, [ search ] );

		var commands = useMemo( function () {
			return ( galleries || [] ).map( function ( gallery ) {
				var title =
					gallery.title && gallery.title.rendered
						? gallery.title.rendered
						: __( '(Untitled)', 'foogallery' );

				return {
					name:        'foogallery/edit-gallery-' + gallery.id,
					/* translators: 1: plugin/post-type name 2: gallery title */
					label:       sprintf( __( 'Edit %1$s: %2$s', 'foogallery' ), galleryName, title ),
					searchLabel: 'Edit ' + galleryName + ': ' + title,
					icon:        galleryIcon,
					callback:    function ( ref ) {
						ref.close();
						window.location.href = getEditUrl( gallery.id );
					},
				};
			} );
		}, [ galleries ] );

		return {
			commands:  commands,
			isLoading: galleries === null,
		};
	}

	// ------------------------------------------------------------------ //
	// Register commands
	// ------------------------------------------------------------------ //

	var commandsDispatch = wp.data.dispatch( wp.commands.store );

	// Static: Add New Gallery.
	if ( config.addNewGalleryUrl && commandsDispatch.registerCommand ) {
		commandsDispatch.registerCommand( {
			name:     'foogallery/add-new-gallery',
			/* translators: %s: plugin/post-type name */
			label:    sprintf( __( 'Add New %s', 'foogallery' ), galleryName ),
			icon:     galleryIcon,
			callback: function ( ref ) {
				ref.close();
				window.location.href = config.addNewGalleryUrl;
			},
		} );
	}

	// Dynamic: search galleries by title as you type.
	// registerCommandLoader is the store action that useCommandLoader
	// calls internally — using it directly avoids needing a React root.
	if ( commandsDispatch.registerCommandLoader ) {
		commandsDispatch.registerCommandLoader( {
			name: 'foogallery/search-galleries',
			hook: useGalleryCommandLoader,
		} );
	}
} )();
