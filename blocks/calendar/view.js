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
	function item( className, parts, strong, workout, plannedWorkout ) {
		var interactive = workout || plannedWorkout, div = document.createElement( interactive ? 'button' : 'div' ); div.className = 'swimlog-calendar-item ' + className;
		if ( workout ) { div.type = 'button'; div.dataset.calendarWorkout = JSON.stringify( workout ); div.setAttribute( 'aria-expanded', 'false' ); div.setAttribute( 'aria-controls', 'swimlog-calendar-detail-' + workout.id ); }
		if ( plannedWorkout ) { div.type = 'button'; div.dataset.calendarPlannedWorkout = JSON.stringify( plannedWorkout ); div.setAttribute( 'aria-expanded', 'false' ); div.setAttribute( 'aria-controls', 'swimlog-calendar-detail-plan-' + plannedWorkout.id ); }
		parts.forEach( function ( part, index ) {
			if ( index ) div.appendChild( document.createTextNode( ' · ' ) );
			var node = strong && index === 0 ? document.createElement( 'strong' ) : document.createTextNode( part );
			if ( node.nodeType === 1 ) { node.textContent = part; div.appendChild( node ); } else div.appendChild( node );
		} );
		return div;
	}
	function planDateButton( key ) {
		var button = document.createElement( 'button' ); button.type = 'button'; button.className = 'swimlog-calendar-plan-date'; button.dataset.calendarPlanDate = key; button.textContent = 'Plan workout'; return button;
	}
	function agendaItemsByDate( data ) {
		var byDate = {};
		( data.workouts || [] ).forEach( function ( workout ) {
			var key = String( workout.workout_start || '' ).slice( 0, 10 ), parts = [ workout.workout_type === 'open_water' ? 'Open Water' : 'Pool Swim' ];
			if ( workout.total_distance_m !== null ) parts.push( Math.round( workout.total_distance_m ).toLocaleString() + ' m' );
			var active = Number( workout.moving_time_ms || workout.elapsed_time_ms || 0 ); if ( active > 0 ) parts.push( duration( active ) );
			( byDate[ key ] = byDate[ key ] || [] ).push( item( 'swimlog-calendar-workout', parts, false, workout ) );
		} );
		( data.planned_workouts || [] ).forEach( function ( plan ) {
			var parts = [ plan.title || 'Planned Workout' ];
			if ( plan.planned_distance_m !== null ) parts.push( Math.round( plan.planned_distance_m ).toLocaleString() + ' m' );
			if ( Number( plan.planned_duration_ms || 0 ) > 0 ) parts.push( duration( plan.planned_duration_ms ) );
			( byDate[ plan.planned_date ] = byDate[ plan.planned_date ] || [] ).push( item( 'swimlog-calendar-planned-workout status-' + ( plan.status || 'planned' ), parts, true, null, plan ) );
		} );
		( data.events || [] ).forEach( function ( event ) { ( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true, null ) ); } );
		return byDate;
	}
	function renderAgenda( root, data, start, end ) {
		var agenda = root.querySelector( '[data-calendar-agenda]' ); if ( ! agenda ) return;
		agenda.textContent = ''; var byDate = agendaItemsByDate( data ), cursor = new Date( start.getFullYear(), start.getMonth(), start.getDate() ), rendered = 0;
		while ( cursor <= end ) {
			var key = dateKey( cursor ), items = byDate[ key ] || [];
			if ( items.length ) {
				var group = document.createElement( 'section' ); group.className = 'swimlog-calendar-agenda-day'; group.dataset.date = key;
				var heading = document.createElement( 'h3' ); heading.className = 'swimlog-calendar-agenda-date'; heading.textContent = cursor.toLocaleDateString( undefined, { weekday: 'long', month: 'long', day: 'numeric' } ); group.appendChild( heading ); group.appendChild( planDateButton( key ) );
				items.forEach( function ( child ) { group.appendChild( child ); } ); agenda.appendChild( group ); rendered++;
			}
			cursor.setDate( cursor.getDate() + 1 );
		}
		if ( ! rendered ) { var empty = document.createElement( 'p' ); empty.className = 'swimlog-calendar-agenda-empty'; empty.textContent = 'No workouts or events in this period.'; agenda.appendChild( empty ); }
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
		( data.planned_workouts || [] ).forEach( function ( plan ) {
			var parts = [ plan.title || 'Planned Workout' ]; if ( plan.planned_distance_m !== null ) parts.push( Math.round( plan.planned_distance_m ).toLocaleString() + ' m' );
			( byDate[ plan.planned_date ] = byDate[ plan.planned_date ] || [] ).push( item( 'swimlog-calendar-planned-workout status-' + ( plan.status || 'planned' ), parts, true, null, plan ) );
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
			var n = document.createElement( 'span' ); n.className = 'swimlog-calendar-date'; n.textContent = cursor.getDate(); cell.appendChild( n ); cell.appendChild( planDateButton( key ) );
			( byDate[ key ] || [] ).forEach( function ( child ) { cell.appendChild( child ); } ); grid.appendChild( cell );
			cursor.setDate( cursor.getDate() + 1 );
		}
		renderAgenda( root, data, first, last );
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
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
		button.setAttribute( 'aria-expanded', 'true' );
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false; panel.id = button.getAttribute( 'aria-controls' ) || ''; root.dataset.detailTrigger = workout.id || '';
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
		panel.scrollIntoView( { block: 'nearest' } ); close.focus();
	}
	function showPlannedWorkoutDetail( root, button ) {
		var plan; try { plan = JSON.parse( button.dataset.calendarPlannedWorkout || '{}' ); } catch ( error ) { return; }
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
		button.setAttribute( 'aria-expanded', 'true' );
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false; panel.id = button.getAttribute( 'aria-controls' ) || '';
		var heading = document.createElement( 'h3' ); heading.className = 'swimlog-calendar-detail-title'; heading.textContent = plan.title || 'Planned Workout'; panel.appendChild( heading );
		var list = document.createElement( 'dl' ); list.className = 'swimlog-calendar-detail-list';
		detailRow( list, 'Date', plan.planned_date ); detailRow( list, 'Start time', plan.planned_start_time ? String( plan.planned_start_time ).slice( 0, 5 ) : '' );
		detailRow( list, 'Type', plan.workout_type === 'open_water' ? 'Open Water' : 'Pool Swim' );
		if ( plan.planned_distance_m !== null ) detailRow( list, 'Planned distance', Math.round( plan.planned_distance_m ).toLocaleString() + ' m' );
		if ( Number( plan.planned_duration_ms || 0 ) > 0 ) detailRow( list, 'Planned duration', duration( plan.planned_duration_ms ) );
		if ( plan.workout_type !== 'open_water' ) { if ( plan.pool_length_m !== null ) detailRow( list, 'Pool length', plan.pool_length_m + ' m' ); detailRow( list, 'Course', plan.pool_course ); detailRow( list, 'Primary stroke', strokeName( plan.primary_stroke ) ); }
		detailRow( list, 'Status', plan.status ? plan.status.charAt( 0 ).toUpperCase() + plan.status.slice( 1 ) : 'Planned' ); panel.appendChild( list );
		if ( plan.workout_instructions ) { var label = document.createElement( 'h4' ); label.textContent = 'Workout Instructions'; panel.appendChild( label ); var instructions = document.createElement( 'pre' ); instructions.className = 'swimlog-calendar-plan-instructions'; instructions.textContent = plan.workout_instructions; panel.appendChild( instructions ); }
		var actions = document.createElement( 'div' ); actions.className = 'swimlog-calendar-plan-actions'; [ [ 'edit', 'Edit' ], [ 'duplicate', 'Duplicate' ], [ 'skip', 'Mark Skipped' ], [ 'cancel', 'Cancel Plan' ], [ 'delete', 'Delete' ] ].forEach( function ( pair ) { if ( ( pair[0] === 'skip' || pair[0] === 'cancel' ) && plan.status !== 'planned' ) return; var action = document.createElement( 'button' ); action.type = 'button'; action.className = 'swimlog-calendar-button'; action.dataset.calendarPlanAction = pair[0]; action.dataset.plan = JSON.stringify( plan ); action.textContent = pair[1]; actions.appendChild( action ); } ); if ( plan.status === 'skipped' || plan.status === 'cancelled' ) { var restore = document.createElement( 'button' ); restore.type = 'button'; restore.className = 'swimlog-calendar-button'; restore.dataset.calendarPlanAction = 'restore'; restore.dataset.plan = JSON.stringify( plan ); restore.textContent = 'Restore'; actions.appendChild( restore ); } panel.appendChild( actions );
		var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button swimlog-calendar-detail-close'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Close'; panel.appendChild( close ); panel.scrollIntoView( { block: 'nearest' } ); close.focus();
	}
	function planField( form, label, name, type, value ) {
		var wrap = document.createElement( 'label' ); wrap.className = 'swimlog-calendar-plan-field'; wrap.appendChild( document.createTextNode( label ) );
		var input = type === 'textarea' ? document.createElement( 'textarea' ) : document.createElement( 'input' ); input.name = name; if ( type !== 'textarea' ) input.type = type; input.value = value === null || value === undefined ? '' : value; wrap.appendChild( input ); form.appendChild( wrap ); return input;
	}
	function showPlanEditor( root, date, plan ) {
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false; panel.id = 'swimlog-calendar-plan-editor';
		var heading = document.createElement( 'h3' ); heading.textContent = plan ? 'Edit Planned Workout' : 'Plan Workout'; panel.appendChild( heading );
		var form = document.createElement( 'form' ); form.dataset.calendarPlanForm = '1'; form.dataset.planId = plan ? plan.id : '';
		planField( form, 'Date', 'planned_date', 'date', plan ? plan.planned_date : date ); planField( form, 'Start time', 'planned_start_time', 'time', plan && plan.planned_start_time ? String( plan.planned_start_time ).slice( 0, 5 ) : '' );
		planField( form, 'Title', 'title', 'text', plan ? plan.title : '' ).required = true;
		var typeLabel = document.createElement( 'label' ); typeLabel.className = 'swimlog-calendar-plan-field'; typeLabel.appendChild( document.createTextNode( 'Workout type' ) ); var select = document.createElement( 'select' ); select.name = 'workout_type'; [ [ 'pool', 'Pool Swim' ], [ 'open_water', 'Open Water' ] ].forEach( function ( option ) { var node = document.createElement( 'option' ); node.value = option[0]; node.textContent = option[1]; if ( plan && plan.workout_type === option[0] ) node.selected = true; select.appendChild( node ); } ); typeLabel.appendChild( select ); form.appendChild( typeLabel );
		planField( form, 'Planned distance (m)', 'planned_distance_m', 'number', plan ? plan.planned_distance_m : '' ); planField( form, 'Planned duration (minutes)', 'planned_duration_minutes', 'number', plan && plan.planned_duration_ms ? Math.round( plan.planned_duration_ms / 60000 ) : '' );
		planField( form, 'Pool length (m)', 'pool_length_m', 'number', plan ? plan.pool_length_m : '' ); planField( form, 'Pool course (m or yd)', 'pool_course', 'text', plan ? plan.pool_course : '' ); planField( form, 'Primary stroke', 'primary_stroke', 'text', plan ? plan.primary_stroke : '' );
		var instructions = planField( form, 'Workout Plan / Workout Instructions', 'workout_instructions', 'textarea', plan ? plan.workout_instructions : '' ); instructions.rows = 10; planField( form, 'Notes', 'notes', 'textarea', '' ).rows = 4;
		var save = document.createElement( 'button' ); save.type = 'submit'; save.className = 'swimlog-calendar-button'; save.textContent = plan ? 'Update Planned Workout' : 'Add Planned Workout'; form.appendChild( save ); var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Cancel'; form.appendChild( close ); panel.appendChild( form ); panel.scrollIntoView( { block: 'nearest' } ); form.querySelector( '[name="title"]' ).focus();
	}
	function mutatePlan( root, payload ) {
		payload.user = root.dataset.user || ''; var status = root.querySelector( '.swimlog-calendar-status' ); status.textContent = 'Saving planned workout…'; root.setAttribute( 'aria-busy', 'true' );
		return fetch( root.dataset.manageUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': ( window.wpApiSettings && window.wpApiSettings.nonce ) || '' }, body: JSON.stringify( payload ) } )
			.then( function ( response ) { return response.json().then( function ( data ) { if ( ! response.ok ) throw new Error( data.message || 'Unable to save planned workout.' ); return data; } ); } )
			.then( function () { closeWorkoutDetail( root ); var focus = new Date( root.dataset.focusDate + 'T12:00:00' ); if ( ( root.dataset.view || 'month' ) === 'week' ) return loadWeek( root, focus ); return loadMonth( root, new Date( focus.getFullYear(), focus.getMonth(), 1 ) ); } )
			.catch( function ( error ) { status.textContent = error.message || 'Unable to save planned workout.'; } ).finally( function () { root.setAttribute( 'aria-busy', 'false' ); } );
	}
	function closeWorkoutDetail( root, restoreFocus ) {
		var trigger = root.querySelector( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"]' );
		var panel = root.querySelector( '[data-calendar-detail]' ); if ( panel ) { panel.hidden = true; panel.textContent = ''; panel.removeAttribute( 'id' ); }
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
		if ( restoreFocus && trigger ) trigger.focus();
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
		( data.planned_workouts || [] ).forEach( function ( plan ) { var parts = [ plan.title || 'Planned Workout' ]; if ( plan.planned_distance_m !== null ) parts.push( Math.round( plan.planned_distance_m ).toLocaleString() + ' m' ); ( byDate[ plan.planned_date ] = byDate[ plan.planned_date ] || [] ).push( item( 'swimlog-calendar-planned-workout status-' + ( plan.status || 'planned' ), parts, true, null, plan ) ); } );
		( data.events || [] ).forEach( function ( event ) { ( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true ) ); } );
		for ( var i = 0; i < 7; i++ ) {
			var date = new Date( start.getFullYear(), start.getMonth(), start.getDate() + i ), key = dateKey( date ), cell = document.createElement( 'div' );
			cell.className = 'swimlog-calendar-day swimlog-calendar-week-day'; cell.setAttribute( 'role', 'gridcell' ); cell.dataset.date = key;
			var heading = document.createElement( 'span' ); heading.className = 'swimlog-calendar-date'; heading.textContent = date.toLocaleDateString( undefined, { weekday: 'short', month: 'short', day: 'numeric' } ); cell.appendChild( heading ); cell.appendChild( planDateButton( key ) );
			( byDate[ key ] || [] ).forEach( function ( child ) { cell.appendChild( child ); } ); grid.appendChild( cell );
		}
		var summary = ( data.weeks || [] )[0];
		if ( summary ) {
			var totals = document.createElement( 'div' ); totals.className = 'swimlog-calendar-week-summary';
			totals.textContent = summary.workout_count + ' workouts · ' + Math.round( summary.distance_m || 0 ).toLocaleString() + ' m · ' + duration( summary.active_time_ms || 0 );
			grid.appendChild( totals );
		}
		renderAgenda( root, data, start, end );
	}
	function setViewButtons( root, viewName ) {
		root.dataset.view = viewName;
		root.querySelectorAll( '[data-calendar-view]' ).forEach( function ( button ) { var active = button.dataset.calendarView === viewName; button.classList.toggle( 'is-active', active ); button.setAttribute( 'aria-pressed', active ? 'true' : 'false' ); } );
	}
	function loadWeek( root, focus ) {
		var firstDay = root.dataset.firstDay || 'sunday', startDate = weekStart( focus, firstDay ), endDate = new Date( startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + 6 );
		var url = new URL( root.dataset.restUrl, window.location.origin ); url.searchParams.set( 'start', dateKey( startDate ) ); url.searchParams.set( 'end', dateKey( endDate ) );
		if ( root.dataset.user ) url.searchParams.set( 'user', root.dataset.user );
		var status = root.querySelector( '.swimlog-calendar-status' ); status.textContent = 'Loading calendar…'; root.classList.add( 'is-loading' ); root.setAttribute( 'aria-busy', 'true' ); root.setAttribute( 'aria-busy', 'true' );
		fetch( url.toString(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': ( window.wpApiSettings && window.wpApiSettings.nonce ) || '' } } )
			.then( function ( response ) { if ( ! response.ok ) throw new Error( 'calendar' ); return response.json(); } )
			.then( function ( data ) { renderWeek( root, data, focus ); status.textContent = ''; } )
			.catch( function () { status.textContent = 'Unable to load calendar.'; } )
			.finally( function () { root.classList.remove( 'is-loading' ); root.setAttribute( 'aria-busy', 'false' ); } );
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
			.finally( function () { root.classList.remove( 'is-loading' ); root.setAttribute( 'aria-busy', 'false' ); } );
	}
	function init( root ) {
		root.dataset.view = 'month'; root.setAttribute( 'aria-busy', 'false' ); root.dataset.focusDate = dateKey( new Date( Number( root.dataset.year ), Number( root.dataset.month ) - 1, 1 ) );
		root.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && ! root.querySelector( '[data-calendar-detail]' ).hidden ) { event.preventDefault(); closeWorkoutDetail( root, true ); }
		} );
		root.addEventListener( 'submit', function ( event ) { var form = event.target.closest( '[data-calendar-plan-form]' ); if ( ! form ) return; event.preventDefault(); var data = new FormData( form ), payload = { action: 'save', id: form.dataset.planId || 0 }; data.forEach( function ( value, key ) { payload[key] = value; } ); payload.planned_duration_ms = payload.planned_duration_minutes ? Math.round( Number( payload.planned_duration_minutes ) * 60000 ) : ''; delete payload.planned_duration_minutes; mutatePlan( root, payload ); } );
		root.addEventListener( 'click', function ( event ) {
			var dateButton = event.target.closest( '[data-calendar-plan-date]' ); if ( dateButton ) { showPlanEditor( root, dateButton.dataset.calendarPlanDate, null ); return; }
			var planAction = event.target.closest( '[data-calendar-plan-action]' ); if ( planAction ) { var plan; try { plan = JSON.parse( planAction.dataset.plan || '{}' ); } catch ( error ) { return; } var action = planAction.dataset.calendarPlanAction; if ( action === 'edit' ) { showPlanEditor( root, plan.planned_date, plan ); return; } if ( action === 'delete' && ! window.confirm( 'Delete this planned workout?' ) ) return; mutatePlan( root, { action: action, id: plan.id, planned_date: plan.planned_date } ); return; }
			var workoutButton = event.target.closest( '[data-calendar-workout]' ); if ( workoutButton ) { showWorkoutDetail( root, workoutButton ); return; }
			var plannedButton = event.target.closest( '[data-calendar-planned-workout]' ); if ( plannedButton ) { showPlannedWorkoutDetail( root, plannedButton ); return; }
			var closeButton = event.target.closest( '[data-calendar-detail-close]' ); if ( closeButton ) { closeWorkoutDetail( root, true ); return; }
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
