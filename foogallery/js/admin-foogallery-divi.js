( function ( window, document ) {
	'use strict';

	let observer;
	let scheduled = false;

	/**
	 * Initialize galleries copied into the Divi canvas without their jQuery data.
	 *
	 * Divi renders legacy modules in a hidden preview document and copies their
	 * HTML into the visual builder. Shortcodes inside native modules are cloned
	 * in the same way. The copied elements retain FooGallery's CSS state classes,
	 * but JavaScript instances and event handlers cannot survive that copy.
	 */
	function initializeCopiedGalleries() {
		const FooGallery = window.FooGallery;

		scheduled = false;

		if ( ! FooGallery || ! FooGallery.$ || ! FooGallery.Template ) {
			return;
		}

		FooGallery.$( '.foogallery[id^="foogallery-gallery-"]' ).each(
			function () {
				const instance = FooGallery.get( this );

				if ( instance instanceof FooGallery.Template ) {
					return;
				}

				FooGallery.$( this ).foogallery( FooGallery.autoDefaults );
			}
		);
	}

	/**
	 * Debounce repeated mutations generated while Divi updates a module.
	 */
	function scheduleInitialization() {
		if ( scheduled ) {
			return;
		}

		scheduled = true;
		window.setTimeout( initializeCopiedGalleries, 50 );
	}

	/**
	 * Watch the visual builder canvas for shortcode and legacy module previews.
	 */
	function observeBuilder() {
		if ( observer || ! document.body ) {
			return;
		}

		observer = new window.MutationObserver( scheduleInitialization );
		observer.observe( document.body, {
			childList: true,
			subtree: true,
		} );

		scheduleInitialization();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', observeBuilder, {
			once: true,
		} );
	} else {
		observeBuilder();
	}

	document.addEventListener( 'foogallery-ready', scheduleInitialization );
} )( window, document );
