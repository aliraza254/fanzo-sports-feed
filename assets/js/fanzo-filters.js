/**
 * Fanzo Sports Feed — Frontend Filter & Date Navigator
 *
 * Vanilla JS, no jQuery. Wrapped in an IIFE to avoid polluting global scope.
 * Handles date strip pagination, date selection, and sport filter.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

( function () {
	'use strict';

	/** Number of date buttons visible at once. */
	var VISIBLE = 7;

	/** All available date strings (Y-m-d) collected from data attributes. */
	var allDates = [];

	/** Current offset for the visible window within allDates. */
	var offset = 0;

	/**
	 * Initialise the date strip and attach event listeners.
	 * Called on DOMContentLoaded (or immediately if DOM is ready).
	 */
	function init() {
		var wrapper = document.querySelector( '.fanzo-sports-feed' );
		if ( ! wrapper ) {
			return;
		}

		// Collect all date keys from the date buttons.
		var btns = wrapper.querySelectorAll( '.fanzo-date-btn' );
		if ( ! btns.length ) {
			return;
		}

		btns.forEach( function ( btn ) {
			allDates.push( btn.getAttribute( 'data-date' ) );
		} );

		// Find the index of the initially active date to set the correct offset.
		var activeBtn = wrapper.querySelector( '.fanzo-date-btn.fanzo-date-active' );
		if ( activeBtn ) {
			var activeDate = activeBtn.getAttribute( 'data-date' );
			var activeIdx  = allDates.indexOf( activeDate );
			if ( activeIdx >= VISIBLE ) {
				// Scroll so the active date is approximately centered.
				offset = Math.max( 0, activeIdx - Math.floor( VISIBLE / 2 ) );
			}
		}

		renderStrip( wrapper );
		bindEventListeners( wrapper );
	}

	/**
	 * Re-render the visible window of date buttons and update arrow states.
	 *
	 * @param {Element} wrapper The .fanzo-sports-feed container.
	 */
	function renderStrip( wrapper ) {
		var btns   = wrapper.querySelectorAll( '.fanzo-date-btn' );
		var arrows = {
			prev : wrapper.querySelector( '.fanzo-prev-arrow' ),
			next : wrapper.querySelector( '.fanzo-next-arrow' ),
		};

		btns.forEach( function ( btn, i ) {
			btn.style.display = ( i >= offset && i < offset + VISIBLE ) ? '' : 'none';
		} );

		if ( arrows.prev ) {
			arrows.prev.disabled = ( offset === 0 );
		}
		if ( arrows.next ) {
			arrows.next.disabled = ( offset + VISIBLE >= allDates.length );
		}
	}

	/**
	 * Bind click events on arrows, date buttons, and the sport filter.
	 *
	 * @param {Element} wrapper The .fanzo-sports-feed container.
	 */
	function bindEventListeners( wrapper ) {
		var prevArrow  = wrapper.querySelector( '.fanzo-prev-arrow' );
		var nextArrow  = wrapper.querySelector( '.fanzo-next-arrow' );
		var sportSelect = wrapper.querySelector( '#fanzo_sport_select' );

		if ( prevArrow ) {
			prevArrow.addEventListener( 'click', function () {
				shiftDates( -1, wrapper );
			} );
		}

		if ( nextArrow ) {
			nextArrow.addEventListener( 'click', function () {
				shiftDates( 1, wrapper );
			} );
		}

		// Date button click — use event delegation on the strip for efficiency.
		var strip = wrapper.querySelector( '#fanzo_date_strip' );
		if ( strip ) {
			strip.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.fanzo-date-btn' );
				if ( ! btn ) return;
				selectDate( btn, wrapper );
			} );
		}

		if ( sportSelect ) {
			sportSelect.addEventListener( 'change', function () {
				fanzoFilter( wrapper );
			} );
		}
	}

	/**
	 * Shift the visible date window left or right.
	 *
	 * @param {number}  dir     Direction: -1 for previous, 1 for next.
	 * @param {Element} wrapper The .fanzo-sports-feed container.
	 */
	function shiftDates( dir, wrapper ) {
		var next = offset + dir;
		if ( next < 0 || next + VISIBLE > allDates.length ) {
			return;
		}
		offset = next;
		renderStrip( wrapper );
	}

	/**
	 * Mark a date button as active and trigger the fixture filter.
	 *
	 * @param {Element} btn     The clicked date button element.
	 * @param {Element} wrapper The .fanzo-sports-feed container.
	 */
	function selectDate( btn, wrapper ) {
		wrapper.querySelectorAll( '.fanzo-date-btn' ).forEach( function ( b ) {
			b.classList.remove( 'fanzo-date-active' );
			b.setAttribute( 'aria-pressed', 'false' );
		} );

		btn.classList.add( 'fanzo-date-active' );
		btn.setAttribute( 'aria-pressed', 'true' );

		fanzoFilter( wrapper );
	}

	/**
	 * Show fixture groups and items that match the selected date and sport.
	 * Hides everything else. Displays the no-fixtures message if needed.
	 *
	 * @param {Element} wrapper The .fanzo-sports-feed container.
	 */
	function fanzoFilter( wrapper ) {
		var activeBtn    = wrapper.querySelector( '.fanzo-date-btn.fanzo-date-active' );
		var selectedDate = activeBtn ? activeBtn.getAttribute( 'data-date' ) : null;

		var sportSelect  = wrapper.querySelector( '#fanzo_sport_select' );
		var selectedSport = sportSelect ? sportSelect.value : 'all';

		var hasAnyVisible = false;

		wrapper.querySelectorAll( '.fanzo-day' ).forEach( function ( dayGroup ) {
			var groupDate  = dayGroup.getAttribute( 'data-date-group' );
			var showGroup  = groupDate === selectedDate;

			dayGroup.style.display = showGroup ? '' : 'none';

			if ( showGroup ) {
				var groupHasVisible = false;

				dayGroup.querySelectorAll( '.api_item' ).forEach( function ( item ) {
					var sportMatch = selectedSport === 'all' || item.getAttribute( 'data-sport' ) === selectedSport;
					item.style.display = sportMatch ? '' : 'none';
					if ( sportMatch ) {
						groupHasVisible   = true;
						hasAnyVisible     = true;
					}
				} );

				// Hide the entire day group if none of its items are visible.
				if ( ! groupHasVisible ) {
					dayGroup.style.display = 'none';
				}
			}
		} );

		// Show or hide the no-fixtures message.
		var noFixtures = wrapper.querySelector( '.fanzo-no-fixtures' );
		if ( noFixtures ) {
			noFixtures.style.display = hasAnyVisible ? 'none' : '';
		}
	}

	// ──────────────────────────────────────────────────────────────
	// Expose fanzoFilter globally for multi-instance support
	// (used by the Gutenberg block when multiple feeds are on page).
	// ──────────────────────────────────────────────────────────────
	window.fanzoFilter = function ( wrapperEl ) {
		var wrapper = wrapperEl instanceof Element
			? wrapperEl
			: document.querySelector( '.fanzo-sports-feed' );
		if ( wrapper ) {
			fanzoFilter( wrapper );
		}
	};

	// Initialise on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
