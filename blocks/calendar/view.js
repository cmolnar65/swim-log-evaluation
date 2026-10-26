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
	function item( className, parts, strong, workout ) {
		var div = document.createElement( workout ? 'button' : 'div' ); div.className = 'swimlog-calendar-item ' + className;
		if ( workout ) { div.type = 'button'; div.dataset.calendarWorkout = JSON.stringify( workout ); div.setAttribute( 'aria-expanded', 'false' ); }
		parts.forEach( function ( part, index ) {
			if ( index ) div.appendChild( document.createTextNode( ' · ' ) );
			var node = strong && index === 0 ? document.createElement( 'strong' ) : document.createTextNode( part );
			if ( node.nodeType === 1 ) { node.textContent = part; div.appendChild( node ); } else div.appendChild( node );
		} );
		return div;
	}
	function renderMonth( root, data, focus ) {
		var firstDay = data.range.first_day || root.dataset.firstDay || 'sunday';
		root.dataset.firstDay = firstDay; root.dataset.year = focus.getFullYear(); root.dataset.month = focus.getMonth() + 1; root.dataset.focusDate = dateKey( focus );
		root.querySelector( '.swimlog-calendar-title' ).textContent = monthName( focus );
		var grid = root.querySelector( '.swimlog-calendar-grid' ); grid.textContent = ''; grid.classList.remove( 'is-week-view' );
		labelsFor( firstDay ).forEach( function ( label ) {
			var h = document.createElement( 'div' ); h.className = 'swimlog-calendar-weekday'; h.setAttribute( 'role', 'columnheader' ); h.textContent = label; grid.appendChild( h );
		} );
		var byDate = {};
		( data.workouts || [] ).forEach( function ( workout ) {
			var key = String( workout.workout_start || '' ).slice( 0, 10 ), parts = [ workout.workout_type === 'open_water' ? 'Open Water' : 'Pool Swim' ];
			if ( workout.total_distance_m !== null ) parts.push( Math.round( workout.total_distance_m ).toLocaleString() + ' m' );
			var active = Number( workout.moving_time_ms || workout.elapsed_time_ms || 0 ); if ( active > 0 ) parts.push( duration( active ) );
			( byDate[ key ] = byDate[ key ] || [] ).push( item( 'swimlog-calendar-workout', parts, false, workout ) );
		} );
		( data.events || [] ).forEach( function ( event ) {
			( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true, null ) );
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
	function detailRow( list, label, value ) {
		if ( value === null || value === undefined || value === '' ) return;
		var dt = document.createElement( 'dt' ); dt.textContent = label;
		var dd = document.createElement( 'dd' ); dd.textContent = value;
		list.appendChild( dt ); list.appendChild( dd );
	}
	function strokeName( value ) {
		var names = { FR: 'Freestyle', BR: 'Breaststroke', BACK: 'Backstroke', FLY: 'Butterfly', MIXED: 'Mixed' };
		return names[ value ] || value || '';
	}
	function showWorkoutDetail( root, button ) {
		var workout; try { workout = JSON.parse( button.dataset.calendarWorkout || '{}' ); } catch ( error ) { return; }
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
		button.setAttribute( 'aria-expanded', 'true' );
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false;
		var heading = document.createElement( 'h3' ); heading.className = 'swimlog-calendar-detail-title'; heading.textContent = workout.workout_type === 'open_water' ? 'Open Water Workout' : 'Pool Swim Workout'; panel.appendChild( heading );
		var list = document.createElement( 'dl' ); list.className = 'swimlog-calendar-detail-list';
		if ( workout.workout_start ) { var when = new Date( String( workout.workout_start ).replace( ' ', 'T' ) ); detailRow( list, 'Date', isNaN( when.getTime() ) ? String( workout.workout_start ) : when.toLocaleString() ); }
		detailRow( list, 'Location', workout.location );
		if ( workout.total_distance_m !== null ) detailRow( list, 'Distance', Math.round( workout.total_distance_m ).toLocaleString() + ' m' );
		var active = Number( workout.moving_time_ms || workout.elapsed_time_ms || 0 ); if ( active > 0 ) detailRow( list, workout.moving_time_ms ? 'Active time' : 'Elapsed time', duration( active ) );
		if ( workout.moving_time_ms && workout.elapsed_time_ms && Number( workout.elapsed_time_ms ) !== Number( workout.moving_time_ms ) ) detailRow( list, 'Elapsed time', duration( workout.elapsed_time_ms ) );
		if ( workout.workout_type !== 'open_water' ) {
			if ( workout.original_pool_length !== null && workout.original_pool_length !== undefined && workout.pool_length_unit ) detailRow( list, 'Pool length', workout.original_pool_length + ' ' + workout.pool_length_unit );
			else if ( workout.pool_length_m !== null && workout.pool_length_m !== undefined ) detailRow( list, 'Pool length', workout.pool_length_m + ' m' );
			detailRow( list, 'Primary stroke', strokeName( workout.primary_stroke ) );
		}
		panel.appendChild( list );
		var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button swimlog-calendar-detail-close'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Close'; panel.appendChild( close );
		panel.scrollIntoView( { block: 'nearest' } );
	}
	function closeWorkoutDetail( root ) {
		var panel = root.querySelector( '[data-calendar-detail]' ); if ( panel ) { panel.hidden = true; panel.textContent = ''; }
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
	}
	function weekStart( focus, firstDay ) {
		var date = new Date( focus.getFullYear(), focus.getMonth(), focus.getDate() ), target = firstDayNumber( firstDay );
		date.setDate( date.getDate() - ( ( date.getDay() - target + 7 ) % 7 ) ); return date;
	}
	function weekTitle( start, end ) {
		var sameYear = start.getFullYear() === end.getFullYear(), sameMonth = sameYear && start.getMonth() === end.getMonth();
		if ( sameMonth ) return start.toLocaleDateString( undefined, { month: 'long', day: 'numeric' } ) + '–' + end.getDate() + ', ' + end.getFullYear();
		return start.toLocaleDateString( undefined, { month: 'short', day: 'numeric', year: sameYear ? undefined : 'numeric' } ) + '–' + end.toLocaleDateString( undefined, { month: 'short', day: 'numeric', year: 'numeric' } );
	}
	function renderWeek( root, data, focus ) {
		var firstDay = data.range.first_day || root.dataset.firstDay || 'sunday', start = weekStart( focus, firstDay ), end = new Date( start.getFullYear(), start.getMonth(), start.getDate() + 6 );
		root.dataset.firstDay = firstDay; root.dataset.focusDate = dateKey( focus );
		root.querySelector( '.swimlog-calendar-title' ).textContent = weekTitle( start, end );
		var grid = root.querySelector( '.swimlog-calendar-grid' ); grid.textContent = ''; grid.classList.add( 'is-week-view' );
		var byDate = {};
		( data.workouts || [] ).forEach( function ( workout ) {
			var key = String( workout.workout_start || '' ).slice( 0, 10 ), parts = [ workout.workout_type === 'open_water' ? 'Open Water' : 'Pool Swim' ];
			if ( workout.total_distance_m !== null ) parts.push( Math.round( workout.total_distance_m ).toLocaleString() + ' m' );
			var active = Number( workout.moving_time_ms || workout.elapsed_time_ms || 0 ); if ( active > 0 ) parts.push( duration( active ) );
			( byDate[ key ] = byDate[ key ] || [] ).push( item( 'swimlog-calendar-workout', parts, false ) );
		} );
		( data.events || [] ).forEach( function ( event ) { ( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true ) ); } );
		for ( var i = 0; i < 7; i++ ) {
			var date = new Date( start.getFullYear(), start.getMonth(), start.getDate() + i ), key = dateKey( date ), cell = document.createElement( 'div' );
			cell.className = 'swimlog-calendar-day swimlog-calendar-week-day'; cell.setAttribute( 'role', 'gridcell' ); cell.dataset.date = key;
			var heading = document.createElement( 'span' ); heading.className = 'swimlog-calendar-date'; heading.textContent = date.toLocaleDateString( undefined, { weekday: 'short', month: 'short', day: 'numeric' } ); cell.appendChild( heading );
			( byDate[ key ] || [] ).forEach( function ( child ) { cell.appendChild( child ); } ); grid.appendChild( cell );
		}
		var summary = ( data.weeks || [] )[0];
		if ( summary ) {
			var totals = document.createElement( 'div' ); totals.className = 'swimlog-calendar-week-summary';
			totals.textContent = summary.workout_count + ' workouts · ' + Math.round( summary.distance_m || 0 ).toLocaleString() + ' m · ' + duration( summary.active_time_ms || 0 );
			grid.appendChild( totals );
		}
	}
	function setViewButtons( root, viewName ) {
		root.dataset.view = viewName;
		root.querySelectorAll( '[data-calendar-view]' ).forEach( function ( button ) { var active = button.dataset.calendarView === viewName; button.classList.toggle( 'is-active', active ); button.setAttribute( 'aria-pressed', active ? 'true' : 'false' ); } );
	}
	function loadWeek( root, focus ) {
		var firstDay = root.dataset.firstDay || 'sunday', startDate = weekStart( focus, firstDay ), endDate = new Date( startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + 6 );
		var url = new URL( root.dataset.restUrl, window.location.origin ); url.searchParams.set( 'start', dateKey( startDate ) ); url.searchParams.set( 'end', dateKey( endDate ) );
		if ( root.dataset.user ) url.searchParams.set( 'user', root.dataset.user );
		var status = root.querySelector( '.swimlog-calendar-status' ); status.textContent = 'Loading calendar…'; root.classList.add( 'is-loading' );
		fetch( url.toString(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': ( window.wpApiSettings && window.wpApiSettings.nonce ) || '' } } )
			.then( function ( response ) { if ( ! response.ok ) throw new Error( 'calendar' ); return response.json(); } )
			.then( function ( data ) { renderWeek( root, data, focus ); status.textContent = ''; } )
			.catch( function () { status.textContent = 'Unable to load calendar.'; } )
			.finally( function () { root.classList.remove( 'is-loading' ); } );
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
		root.dataset.view = 'month'; root.dataset.focusDate = dateKey( new Date( Number( root.dataset.year ), Number( root.dataset.month ) - 1, 1 ) );
		root.addEventListener( 'click', function ( event ) {
			var workoutButton = event.target.closest( '[data-calendar-workout]' ); if ( workoutButton ) { showWorkoutDetail( root, workoutButton ); return; }
			var closeButton = event.target.closest( '[data-calendar-detail-close]' ); if ( closeButton ) { closeWorkoutDetail( root ); return; }
			var viewButton = event.target.closest( '[data-calendar-view]' );
			if ( viewButton ) {
				closeWorkoutDetail( root );
				var viewName = viewButton.dataset.calendarView, focus = new Date( root.dataset.focusDate + 'T12:00:00' );
				setViewButtons( root, viewName );
				if ( viewName === 'week' ) loadWeek( root, focus ); else loadMonth( root, new Date( focus.getFullYear(), focus.getMonth(), 1 ) );
				return;
			}
			var button = event.target.closest( '[data-calendar-action]' ); if ( ! button ) return;
			closeWorkoutDetail( root );
			var currentView = root.dataset.view || 'month', focus = new Date( root.dataset.focusDate + 'T12:00:00' );
			if ( button.dataset.calendarAction === 'today' ) focus = new Date();
			else if ( currentView === 'week' ) focus.setDate( focus.getDate() + ( button.dataset.calendarAction === 'previous' ? -7 : 7 ) );
			else focus.setMonth( focus.getMonth() + ( button.dataset.calendarAction === 'previous' ? -1 : 1 ) );
			if ( currentView === 'week' ) loadWeek( root, focus ); else loadMonth( root, new Date( focus.getFullYear(), focus.getMonth(), 1 ) );
		} );
	}
	document.addEventListener( 'DOMContentLoaded', function () { document.querySelectorAll( '[data-swimlog-calendar="1"]' ).forEach( init ); } );
} )();
