( function () {
	'use strict';

	function pad( value ) { return String( value ).padStart( 2, '0' ); }
	function dateKey( date ) { return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() ); }
	function monthName( date ) { return date.toLocaleDateString( undefined, { month: 'long', year: 'numeric' } ); }
	function firstDayNumber( value ) { return value === 'monday' ? 1 : ( value === 'saturday' ? 6 : 0 ); }
	function labelsFor( value ) {
		if ( value === 'monday' ) return [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
		if ( value === 'saturday' ) return [ 'Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri' ];
		return [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
	}
	function duration( milliseconds ) {
		var minutes = Math.floor( Number( milliseconds || 0 ) / 60000 ), hours = Math.floor( minutes / 60 );
		minutes %= 60;
		return hours ? hours + 'h ' + String( minutes ).padStart( 2, '0' ) + 'm' : minutes + 'm';
	}
	function item( className, parts, strong ) {
		var div = document.createElement( 'div' ); div.className = 'swimlog-calendar-item ' + className;
		parts.forEach( function ( part, index ) {
			if ( index ) div.appendChild( document.createTextNode( ' · ' ) );
			var node = strong && index === 0 ? document.createElement( 'strong' ) : document.createTextNode( part );
			if ( node.nodeType === 1 ) { node.textContent = part; div.appendChild( node ); } else div.appendChild( node );
		} );
		return div;
	}
	function renderMonth( root, data, focus ) {
		var firstDay = data.range.first_day || root.dataset.firstDay || 'sunday';
		root.dataset.firstDay = firstDay; root.dataset.year = focus.getFullYear(); root.dataset.month = focus.getMonth() + 1;
		root.querySelector( '.swimlog-calendar-title' ).textContent = monthName( focus );
		var grid = root.querySelector( '.swimlog-calendar-grid' ); grid.textContent = '';
		labelsFor( firstDay ).forEach( function ( label ) {
			var h = document.createElement( 'div' ); h.className = 'swimlog-calendar-weekday'; h.setAttribute( 'role', 'columnheader' ); h.textContent = label; grid.appendChild( h );
		} );
		var byDate = {};
		( data.workouts || [] ).forEach( function ( workout ) {
			var key = String( workout.workout_start || '' ).slice( 0, 10 ), parts = [ workout.workout_type === 'open_water' ? 'Open Water' : 'Pool Swim' ];
			if ( workout.total_distance_m !== null ) parts.push( Math.round( workout.total_distance_m ).toLocaleString() + ' m' );
			var active = Number( workout.moving_time_ms || workout.elapsed_time_ms || 0 ); if ( active > 0 ) parts.push( duration( active ) );
			( byDate[ key ] = byDate[ key ] || [] ).push( item( 'swimlog-calendar-workout', parts, false ) );
		} );
		( data.events || [] ).forEach( function ( event ) {
			( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true ) );
		} );
		var first = new Date( focus.getFullYear(), focus.getMonth(), 1 ), last = new Date( focus.getFullYear(), focus.getMonth() + 1, 0 );
		var back = ( first.getDay() - firstDayNumber( firstDay ) + 7 ) % 7, forward = ( firstDayNumber( firstDay ) + 6 - last.getDay() + 7 ) % 7;
		var cursor = new Date( first.getFullYear(), first.getMonth(), 1 - back ), end = new Date( last.getFullYear(), last.getMonth(), last.getDate() + forward );
		while ( cursor <= end ) {
			var key = dateKey( cursor ), cell = document.createElement( 'div' ); cell.className = 'swimlog-calendar-day'; cell.setAttribute( 'role', 'gridcell' ); cell.dataset.date = key;
			if ( cursor.getMonth() !== focus.getMonth() ) cell.classList.add( 'is-outside-month' );
			var n = document.createElement( 'span' ); n.className = 'swimlog-calendar-date'; n.textContent = cursor.getDate(); cell.appendChild( n );
			( byDate[ key ] || [] ).forEach( function ( child ) { cell.appendChild( child ); } ); grid.appendChild( cell );
			cursor.setDate( cursor.getDate() + 1 );
		}
	}
	function loadMonth( root, focus ) {
		var start = dateKey( new Date( focus.getFullYear(), focus.getMonth(), 1 ) ), end = dateKey( new Date( focus.getFullYear(), focus.getMonth() + 1, 0 ) );
		var url = new URL( root.dataset.restUrl, window.location.origin ); url.searchParams.set( 'start', start ); url.searchParams.set( 'end', end );
		if ( root.dataset.user ) url.searchParams.set( 'user', root.dataset.user );
		var status = root.querySelector( '.swimlog-calendar-status' ); status.textContent = 'Loading calendar…'; root.classList.add( 'is-loading' );
		fetch( url.toString(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': ( window.wpApiSettings && window.wpApiSettings.nonce ) || '' } } )
			.then( function ( response ) { if ( ! response.ok ) throw new Error( 'calendar' ); return response.json(); } )
			.then( function ( data ) { renderMonth( root, data, focus ); status.textContent = ''; } )
			.catch( function () { status.textContent = 'Unable to load calendar.'; } )
			.finally( function () { root.classList.remove( 'is-loading' ); } );
	}
	function init( root ) {
		root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-calendar-action]' ); if ( ! button ) return;
			var focus = new Date( Number( root.dataset.year ), Number( root.dataset.month ) - 1, 1 );
			if ( button.dataset.calendarAction === 'previous' ) focus.setMonth( focus.getMonth() - 1 );
			else if ( button.dataset.calendarAction === 'next' ) focus.setMonth( focus.getMonth() + 1 );
			else { var now = new Date(); focus = new Date( now.getFullYear(), now.getMonth(), 1 ); }
			loadMonth( root, focus );
		} );
	}
	document.addEventListener( 'DOMContentLoaded', function () { document.querySelectorAll( '[data-swimlog-calendar="1"]' ).forEach( init ); } );
} )();
