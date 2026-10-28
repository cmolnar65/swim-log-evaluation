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
	function item( className, parts, strong, workout, plannedWorkout, calendarEvent ) {
		var interactive = workout || plannedWorkout || calendarEvent, div = document.createElement( interactive ? 'button' : 'div' ); div.className = 'swimlog-calendar-item ' + className; if ( workout && workout.matched_to_plan ) div.classList.add( 'is-matched-to-plan' );
		var kind = className.indexOf( 'planned-workout' ) !== -1 ? 'Planned' : ( className.indexOf( 'calendar-event' ) !== -1 ? 'Event' : 'Recorded' );
		var badge = document.createElement( 'span' ); badge.className = 'swimlog-calendar-kind'; badge.textContent = kind; div.appendChild( badge );
		if ( workout && workout.has_personal_best ) { var star = document.createElement( 'span' ); star.className = 'swimlog-calendar-personal-best'; star.textContent = '★'; star.setAttribute( 'aria-label', 'Personal best' ); star.title = 'Personal best'; div.appendChild( star ); }
		if ( workout ) { div.type = 'button'; div.dataset.calendarWorkout = JSON.stringify( workout ); div.setAttribute( 'aria-expanded', 'false' ); div.setAttribute( 'aria-controls', 'swimlog-calendar-detail-' + workout.id ); }
		if ( plannedWorkout ) { if ( plannedWorkout.status === 'planned' && !plannedWorkout.completed_workout_id ) div.draggable = true; div.type = 'button'; div.dataset.calendarPlannedWorkout = JSON.stringify( plannedWorkout ); div.setAttribute( 'aria-expanded', 'false' ); div.setAttribute( 'aria-controls', 'swimlog-calendar-detail-plan-' + plannedWorkout.id ); }
		if ( calendarEvent ) { div.type = 'button'; div.dataset.calendarEvent = JSON.stringify( calendarEvent ); div.setAttribute( 'aria-expanded', 'false' ); div.setAttribute( 'aria-controls', 'swimlog-calendar-detail-event-' + calendarEvent.id ); }
		parts.forEach( function ( part, index ) {
			if ( index ) div.appendChild( document.createTextNode( ' · ' ) );
			var node = strong && index === 0 ? document.createElement( 'strong' ) : document.createTextNode( part );
			if ( node.nodeType === 1 ) { node.textContent = part; div.appendChild( node ); } else div.appendChild( node );
		} );
		return div;
	}
	function planDateButton( key, root ) {
		if ( !root.dataset.siteToday || key < root.dataset.siteToday ) return null;
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
		( data.events || [] ).forEach( function ( event ) { ( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true, null, null, event ) ); } );
		return byDate;
	}
	function renderAgenda( root, data, start, end ) {
		var agenda = root.querySelector( '[data-calendar-agenda]' ); if ( ! agenda ) return;
		agenda.textContent = ''; var byDate = agendaItemsByDate( data ), cursor = new Date( start.getFullYear(), start.getMonth(), start.getDate() ), itemCount = 0;
		while ( cursor <= end ) {
			var key = dateKey( cursor ), items = byDate[ key ] || [];
			var group = document.createElement( 'section' ); group.className = 'swimlog-calendar-agenda-day'; group.dataset.date = key;
			var heading = document.createElement( 'h3' ); heading.className = 'swimlog-calendar-agenda-date'; heading.textContent = cursor.toLocaleDateString( undefined, { weekday: 'long', month: 'long', day: 'numeric' } ); group.appendChild( heading ); var planButton = planDateButton( key, root ); if ( planButton ) group.appendChild( planButton );
			if ( ! items.length ) { var none = document.createElement( 'p' ); none.className = 'swimlog-calendar-agenda-day-empty'; none.textContent = 'No workouts or events.'; group.appendChild( none ); }
			items.forEach( function ( child ) { group.appendChild( child ); itemCount++; } ); agenda.appendChild( group );
			cursor.setDate( cursor.getDate() + 1 );
		}
		if ( ! itemCount ) { var empty = document.createElement( 'p' ); empty.className = 'swimlog-calendar-agenda-empty'; empty.textContent = 'No workouts or events in this period. You can still plan a workout on any date above.'; agenda.appendChild( empty ); }
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
			( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true, null, null, event ) );
		} );
		var first = new Date( focus.getFullYear(), focus.getMonth(), 1 ), last = new Date( focus.getFullYear(), focus.getMonth() + 1, 0 );
		var back = ( first.getDay() - firstDayNumber( firstDay ) + 7 ) % 7, forward = ( firstDayNumber( firstDay ) + 6 - last.getDay() + 7 ) % 7;
		var cursor = new Date( first.getFullYear(), first.getMonth(), 1 - back ), end = new Date( last.getFullYear(), last.getMonth(), last.getDate() + forward );
		while ( cursor <= end ) {
			var key = dateKey( cursor ), cell = document.createElement( 'div' ); cell.className = 'swimlog-calendar-day'; cell.setAttribute( 'role', 'gridcell' ); cell.dataset.date = key;
			if ( cursor.getMonth() !== focus.getMonth() ) cell.classList.add( 'is-outside-month' );
			var n = document.createElement( 'span' ); n.className = 'swimlog-calendar-date'; n.textContent = cursor.getDate(); cell.appendChild( n ); var planButton = planDateButton( key, root ); if ( planButton ) cell.appendChild( planButton );
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
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"],[data-calendar-event][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
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
		if ( workout.id && root.dataset.workoutDetailUrl ) {
			var link = document.createElement( 'a' ); link.className = 'swimlog-calendar-button'; link.href = root.dataset.workoutDetailUrl + encodeURIComponent( workout.id ); link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'View Workout'; panel.appendChild( link );
		}
		var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button swimlog-calendar-detail-close'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Close'; panel.appendChild( close );
		panel.scrollIntoView( { block: 'nearest' } ); close.focus();
	}
	function showEventDetail( root, button ) {
		var calendarEvent; try { calendarEvent = JSON.parse( button.dataset.calendarEvent || '{}' ); } catch ( error ) { return; }
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"],[data-calendar-event][aria-expanded="true"],[data-calendar-event][aria-expanded="true"]' ).forEach( function ( node ) { node.setAttribute( 'aria-expanded', 'false' ); } );
		button.setAttribute( 'aria-expanded', 'true' );
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false; panel.id = button.getAttribute( 'aria-controls' ) || '';
		var heading = document.createElement( 'h3' ); heading.className = 'swimlog-calendar-detail-title'; heading.textContent = calendarEvent.event_name || 'Swim Event'; panel.appendChild( heading );
		var list = document.createElement( 'dl' ); list.className = 'swimlog-calendar-detail-list';
		detailRow( list, 'Date', calendarEvent.event_date ); detailRow( list, 'Time', calendarEvent.event_time ); detailRow( list, 'Location', calendarEvent.location );
		if ( calendarEvent.distance_value !== null && calendarEvent.distance_value !== undefined ) detailRow( list, 'Race distance', calendarEvent.distance_value + ' ' + ( calendarEvent.course_unit || '' ) );
		detailRow( list, 'Stroke', strokeName( calendarEvent.stroke ) ); panel.appendChild( list );
		var settings = window.swimlogCalendarSettings || {};
		var form = document.createElement( 'form' ); form.dataset.calendarEventResultForm = '1'; form.dataset.eventId = calendarEvent.id;
		var time = planField( form, 'Final time (MM:SS.ss)', 'result_time', 'text', '' );
		var place = planField( form, 'Placement', 'placement', 'number', '' ); place.step = '1'; place.min = '1';
		var notes = planField( form, 'Event notes', 'notes', 'textarea', '' );
		var submit = document.createElement( 'button' ); submit.type = 'submit'; submit.className = 'swimlog-calendar-button'; submit.textContent = 'Save Event Results'; form.appendChild( submit );
		var feedback = document.createElement( 'p' ); feedback.setAttribute( 'role', 'status' ); form.appendChild( feedback ); panel.appendChild( form );
		if ( ! settings.ajaxUrl || ! settings.eventNonce ) { feedback.textContent = 'Event result editing is unavailable.'; submit.disabled = true; }
		else {
			feedback.textContent = 'Loading saved event results…';
			requestEventResult( root, calendarEvent.id, 'read', {} ).then( function ( data ) {
				if ( ! form.isConnected ) return;
				time.value = data.result_time || ''; place.value = data.placement === null ? '' : data.placement; notes.value = data.notes || ''; feedback.textContent = '';
			} ).catch( function ( error ) { if ( form.isConnected ) { feedback.textContent = error.message; submit.disabled = true; } } );
		}
		var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button swimlog-calendar-detail-close'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Close'; panel.appendChild( close );
		panel.scrollIntoView( { block: 'nearest' } ); close.focus();
	}
	function requestEventResult( root, id, operation, values ) {
		var settings = window.swimlogCalendarSettings || {};
		if ( ! settings.ajaxUrl || ! settings.eventNonce ) return Promise.reject( new Error( 'Event result editing is unavailable.' ) );
		var body = new URLSearchParams();
		body.append( 'action', 'swimlog_calendar_event_result' ); body.append( 'nonce', settings.eventNonce );
		body.append( 'user', root.dataset.user || '' ); body.append( 'id', id ); body.append( 'operation', operation );
		Object.keys( values ).forEach( function ( key ) { body.append( key, values[key] ); } );
		return fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() } )
			.then( function ( response ) { return response.json().then( function ( payload ) { if ( ! response.ok || ! payload.success ) throw new Error( payload.data && payload.data.message || 'Event result request failed.' ); return payload.data; } ); } );
	}
	function showPlannedWorkoutDetail( root, button ) {
		var plan; try { plan = JSON.parse( button.dataset.calendarPlannedWorkout || '{}' ); } catch ( error ) { return; }
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"],[data-calendar-event][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
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
		if ( plan.comparison ) { var comparisonHeading = document.createElement( 'h4' ); comparisonHeading.textContent = 'Planned vs Actual'; panel.appendChild( comparisonHeading ); var comparison = document.createElement( 'dl' ); comparison.className = 'swimlog-calendar-comparison';
			if ( plan.comparison.planned_distance_m !== null && plan.comparison.actual_distance_m !== null ) detailRow( comparison, 'Distance', Math.round( plan.comparison.planned_distance_m ).toLocaleString() + ' m planned · ' + Math.round( plan.comparison.actual_distance_m ).toLocaleString() + ' m actual · ' + ( plan.comparison.distance_difference_m >= 0 ? '+' : '' ) + Math.round( plan.comparison.distance_difference_m ).toLocaleString() + ' m' );
			if ( plan.comparison.planned_duration_ms !== null && plan.comparison.actual_duration_ms !== null ) detailRow( comparison, 'Duration', duration( plan.comparison.planned_duration_ms ) + ' planned · ' + duration( plan.comparison.actual_duration_ms ) + ' actual · ' + ( plan.comparison.duration_difference_ms >= 0 ? '+' : '−' ) + duration( Math.abs( plan.comparison.duration_difference_ms ) ) ); panel.appendChild( comparison ); }
		if ( plan.workout_instructions ) { var label = document.createElement( 'h4' ); label.textContent = 'Workout Instructions'; panel.appendChild( label ); var instructions = document.createElement( 'pre' ); instructions.className = 'swimlog-calendar-plan-instructions'; instructions.textContent = plan.workout_instructions; panel.appendChild( instructions ); }
		var actions = document.createElement( 'div' ); actions.className = 'swimlog-calendar-plan-actions'; if ( plan.status === 'completed' && plan.completed_workout_id ) { var unlink = document.createElement( 'button' ); unlink.type = 'button'; unlink.className = 'swimlog-calendar-button'; unlink.dataset.calendarPlanAction = 'unlink'; unlink.dataset.plan = JSON.stringify( plan ); unlink.textContent = 'Remove Workout Match'; actions.appendChild( unlink ); } else if ( plan.status === 'planned' ) { var match = document.createElement( 'button' ); match.type = 'button'; match.className = 'swimlog-calendar-button'; match.dataset.calendarPlanAction = 'match'; match.dataset.plan = JSON.stringify( plan ); match.textContent = 'Match Recorded Workout'; actions.appendChild( match ); } [ [ 'edit', 'Edit' ], [ 'move-date', 'Move to Date' ], [ 'copy', 'Copy Workout' ], [ 'cut', 'Cut Workout' ], [ 'duplicate', 'Duplicate' ], [ 'repeat', 'Repeat' ], [ 'skip', 'Mark Skipped' ], [ 'cancel', 'Cancel Plan' ], [ 'delete', 'Delete' ] ].forEach( function ( pair ) { if ( ( pair[0] === 'skip' || pair[0] === 'cancel' || pair[0] === 'cut' || pair[0] === 'move-date' ) && ( plan.status !== 'planned' || ( pair[0] !== 'skip' && pair[0] !== 'cancel' && plan.completed_workout_id ) ) ) return; var action = document.createElement( 'button' ); action.type = 'button'; action.className = 'swimlog-calendar-button'; action.dataset.calendarPlanAction = pair[0]; action.dataset.plan = JSON.stringify( plan ); action.textContent = pair[1]; actions.appendChild( action ); } ); if ( plan.status === 'skipped' || plan.status === 'cancelled' ) { var restore = document.createElement( 'button' ); restore.type = 'button'; restore.className = 'swimlog-calendar-button'; restore.dataset.calendarPlanAction = 'restore'; restore.dataset.plan = JSON.stringify( plan ); restore.textContent = 'Restore'; actions.appendChild( restore ); } panel.appendChild( actions );
		var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button swimlog-calendar-detail-close'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Close'; panel.appendChild( close ); panel.scrollIntoView( { block: 'nearest' } ); close.focus();
	}
	function planField( form, label, name, type, value ) {
		var wrap = document.createElement( 'label' ); wrap.className = 'swimlog-calendar-plan-field'; wrap.appendChild( document.createTextNode( label ) );
		var input = type === 'textarea' ? document.createElement( 'textarea' ) : document.createElement( 'input' ); input.name = name; if ( type !== 'textarea' ) input.type = type; if ( type === 'number' ) { input.step = 'any'; input.min = '0'; } input.value = value === null || value === undefined ? '' : value; wrap.appendChild( input ); form.appendChild( wrap ); return input;
	}
	function showPlanEditor( root, date, plan ) {
		if ( document.activeElement && root.contains( document.activeElement ) ) root._calendarReturnFocus = document.activeElement;
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false; panel.id = 'swimlog-calendar-plan-editor';
		var heading = document.createElement( 'h3' ); heading.textContent = plan ? 'Edit Planned Workout' : 'Plan Workout'; panel.appendChild( heading );
		var form = document.createElement( 'form' ); form.dataset.calendarPlanForm = '1'; form.dataset.planId = plan ? plan.id : '';
		var planDate = planField( form, 'Date', 'planned_date', 'date', plan ? plan.planned_date : date ); if ( ! plan || plan.planned_date >= root.dataset.siteToday ) planDate.min = root.dataset.siteToday; planField( form, 'Start time (optional)', 'planned_start_time', 'time', plan && plan.planned_start_time ? String( plan.planned_start_time ).slice( 0, 5 ) : '' );
		planField( form, 'Title', 'title', 'text', plan ? plan.title : '' ).required = true;
		var typeLabel = document.createElement( 'label' ); typeLabel.className = 'swimlog-calendar-plan-field'; typeLabel.appendChild( document.createTextNode( 'Workout type' ) ); var select = document.createElement( 'select' ); select.name = 'workout_type'; [ [ 'pool', 'Pool Swim' ], [ 'open_water', 'Open Water' ] ].forEach( function ( option ) { var node = document.createElement( 'option' ); node.value = option[0]; node.textContent = option[1]; if ( plan && plan.workout_type === option[0] ) node.selected = true; select.appendChild( node ); } ); typeLabel.appendChild( select ); form.appendChild( typeLabel );
		planField( form, 'Planned distance (m)', 'planned_distance_m', 'number', plan ? plan.planned_distance_m : '' ); planField( form, 'Planned duration (minutes)', 'planned_duration_minutes', 'number', plan && plan.planned_duration_ms ? Math.round( plan.planned_duration_ms / 60000 ) : '' );
		planField( form, 'Pool length (m)', 'pool_length_m', 'number', plan ? plan.pool_length_m : '' ); var courseLabel = document.createElement( 'label' ); courseLabel.className = 'swimlog-calendar-plan-field'; courseLabel.appendChild( document.createTextNode( 'Pool course' ) ); var course = document.createElement( 'select' ); course.name = 'pool_course'; [ [ '', 'Select course (optional)' ], [ 'm', 'Meters' ], [ 'yd', 'Yards' ] ].forEach( function ( option ) { var choice = document.createElement( 'option' ); choice.value = option[0]; choice.textContent = option[1]; choice.selected = option[0] === ( plan && plan.pool_course || '' ); course.appendChild( choice ); } ); courseLabel.appendChild( course ); form.appendChild( courseLabel );
		var strokeLabel = document.createElement( 'label' ); strokeLabel.className = 'swimlog-calendar-plan-field'; strokeLabel.appendChild( document.createTextNode( 'Primary stroke' ) ); var stroke = document.createElement( 'select' ); stroke.name = 'primary_stroke'; [ [ '', 'Select stroke (optional)' ], [ 'FR', 'Freestyle' ], [ 'BACK', 'Backstroke' ], [ 'BR', 'Breaststroke' ], [ 'FLY', 'Butterfly' ], [ 'IM', 'Individual medley' ], [ 'MIXED', 'Mixed' ] ].forEach( function ( option ) { var choice = document.createElement( 'option' ); choice.value = option[0]; choice.textContent = option[1]; choice.selected = option[0] === ( plan && plan.primary_stroke || '' ); stroke.appendChild( choice ); } ); strokeLabel.appendChild( stroke ); form.appendChild( strokeLabel );
		function updatePoolFields() { var isPool = select.value === 'pool'; form.querySelector( '[name="pool_length_m"]' ).disabled = ! isPool; course.disabled = ! isPool; } select.addEventListener( 'change', updatePoolFields ); updatePoolFields();
		var instructions = planField( form, 'Workout Plan / Workout Instructions', 'workout_instructions', 'textarea', plan ? plan.workout_instructions : '' ); instructions.rows = 10;
		var feedback = document.createElement( 'p' ); feedback.className = 'swimlog-calendar-plan-feedback'; feedback.setAttribute( 'role', 'status' ); feedback.setAttribute( 'aria-live', 'polite' ); form.appendChild( feedback );
		var save = document.createElement( 'button' ); save.type = 'submit'; save.className = 'swimlog-calendar-button'; save.textContent = plan ? 'Update Planned Workout' : 'Add Planned Workout'; form.appendChild( save ); var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Cancel'; form.appendChild( close ); panel.appendChild( form ); panel.scrollIntoView( { block: 'nearest' } ); form.querySelector( '[name="title"]' ).focus();
	}
	function calendarPlanRequest( root, payload ) {
		var settings = window.swimlogCalendarSettings || {}; if ( ! settings.ajaxUrl || ! settings.planNonce ) return Promise.reject( new Error( 'Unable to manage planned workout.' ) );
		payload.user = root.dataset.user || ''; payload.action = 'swimlog_calendar_plan'; var body = new URLSearchParams(); Object.keys( payload ).forEach( function ( key ) { body.append( key, payload[key] === null || payload[key] === undefined ? '' : payload[key] ); } ); body.append( 'nonce', settings.planNonce );
		return fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() } ).then( function ( response ) { return response.json().then( function ( data ) { if ( ! response.ok || ! data.success ) throw new Error( data.data && data.data.message ? data.data.message : 'Unable to manage planned workout.' ); return data.data || {}; } ); } );
	}
	function showRecurrenceEditor( root, plan ) {
		if ( document.activeElement && root.contains( document.activeElement ) ) root._calendarReturnFocus = document.activeElement;
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false;
		var heading = document.createElement( 'h3' ); heading.textContent = 'Repeat Planned Workout'; panel.appendChild( heading );
		var form = document.createElement( 'form' ); form.dataset.calendarRecurrenceForm = '1'; form.dataset.planId = plan.id;
		var days = document.createElement( 'fieldset' ), legend = document.createElement( 'legend' ); legend.textContent = 'Repeat on'; days.appendChild( legend );
		[ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ].forEach( function ( label, index ) { var wrap = document.createElement( 'label' ), box = document.createElement( 'input' ); box.type = 'checkbox'; box.name = 'weekday'; box.value = index; if ( new Date( plan.planned_date + 'T12:00:00' ).getDay() === index ) box.checked = true; wrap.appendChild( box ); wrap.appendChild( document.createTextNode( ' ' + label ) ); days.appendChild( wrap ); } ); form.appendChild( days );
		var end = planField( form, 'Repeat through', 'end_date', 'date', plan.planned_date ); end.required = true;
		var submit = document.createElement( 'button' ); submit.type = 'submit'; submit.className = 'swimlog-calendar-button'; submit.textContent = 'Create Recurring Workouts'; form.appendChild( submit );
		var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Cancel'; form.appendChild( close ); panel.appendChild( form ); panel.scrollIntoView( { block: 'nearest' } ); days.querySelector( 'input' ).focus();
	}
	function showCompletionCandidates( root, plan ) {
		if ( document.activeElement && root.contains( document.activeElement ) ) root._calendarReturnFocus = document.activeElement;
		var panel = root.querySelector( '[data-calendar-detail]' ), status = root.querySelector( '.swimlog-calendar-status' ); status.textContent = 'Finding recorded workouts…'; root.setAttribute( 'aria-busy', 'true' );
		calendarPlanRequest( root, { plan_action: 'candidates', id: plan.id } ).then( function ( data ) {
			panel.textContent = ''; var heading = document.createElement( 'h3' ); heading.textContent = 'Match Recorded Workout'; panel.appendChild( heading );
			var intro = document.createElement( 'p' ); intro.textContent = 'Choose the recorded workout that completed this plan. No match is made until you confirm it.'; panel.appendChild( intro );
			var candidates = data.candidates || []; if ( ! candidates.length ) { var none = document.createElement( 'p' ); none.textContent = 'No same-day recorded workouts of the same type were found.'; panel.appendChild( none ); }
			candidates.forEach( function ( candidate ) { var row = document.createElement( 'div' ); row.className = 'swimlog-calendar-match-candidate'; var label = document.createElement( 'span' ); var parts = [ String( candidate.workout_start || '' ).replace( ' ', ' · ' ) ]; if ( candidate.total_distance_m !== null ) parts.push( Math.round( candidate.total_distance_m ).toLocaleString() + ' m' ); if ( candidate.active_time_ms ) parts.push( duration( candidate.active_time_ms ) ); label.textContent = parts.join( ' · ' ); row.appendChild( label ); var choose = document.createElement( 'button' ); choose.type = 'button'; choose.className = 'swimlog-calendar-button'; choose.dataset.calendarMatchWorkout = candidate.id; choose.dataset.planId = plan.id; choose.textContent = 'Confirm Match'; row.appendChild( choose ); panel.appendChild( row ); } );
			var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Back'; panel.appendChild( close ); status.textContent = '';
		} ).catch( function ( error ) { status.textContent = error.message; } ).finally( function () { root.setAttribute( 'aria-busy', 'false' ); } );
	}
	function parsePlanResponse( response ) {
		return response.text().then( function ( raw ) {
			var data;
			try { data = JSON.parse( raw ); } catch ( error ) {
				throw new Error( 'WordPress returned a non-JSON response (HTTP ' + response.status + '). Check the PHP error log for the calendar AJAX request.' );
			}
			if ( !response.ok || !data.success ) throw new Error( data.data && data.data.message ? data.data.message : 'Unable to save planned workout (HTTP ' + response.status + ').' );
			return data;
		} );
	}
	function mutatePlan( root, payload ) {
		var settings = window.swimlogCalendarSettings || {}, status = root.querySelector( '.swimlog-calendar-status' ), feedback = root.querySelector( '[data-calendar-plan-form] .swimlog-calendar-plan-feedback' );
		function report( message ) { status.textContent = message; if ( feedback ) feedback.textContent = message; }
		if ( ! settings.ajaxUrl || ! settings.planNonce ) { report( 'Unable to save planned workout.' ); return Promise.resolve(); }
		if ( payload.plan_action === 'save' && !Number( payload.id || 0 ) && ( !root.dataset.siteToday || payload.planned_date < root.dataset.siteToday ) ) { report( 'New planned workouts must be scheduled for today or a future date.' ); return Promise.resolve(); }
		payload.user = root.dataset.user || ''; payload.plan_action = payload.plan_action || payload.actionName || payload.action; delete payload.actionName;
		// WordPress AJAX reserves "action" for dispatch; lifecycle action travels as plan_action.
		payload.action = 'swimlog_calendar_plan';
		var body = new URLSearchParams(); Object.keys( payload ).forEach( function ( key ) { body.append( key, payload[key] === null || payload[key] === undefined ? '' : payload[key] ); } ); body.append( 'nonce', settings.planNonce );
		report( 'Saving planned workout…' ); root.setAttribute( 'aria-busy', 'true' );
		return fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() } )
			.then( parsePlanResponse )
			.then( function () { closeWorkoutDetail( root ); var focus = new Date( root.dataset.focusDate + 'T12:00:00' ); if ( ( root.dataset.view || 'month' ) === 'week' ) return loadWeek( root, focus ); return loadMonth( root, new Date( focus.getFullYear(), focus.getMonth(), 1 ) ); } )
			.then( function () { return true; } ).catch( function ( error ) { report( error.message || 'Unable to save planned workout.' ); return false; } ).finally( function () { root.setAttribute( 'aria-busy', 'false' ); } );
	}
	function clipboardStatus( root, message ) { var status = root.querySelector( '.swimlog-calendar-status' ); if ( status ) status.textContent = message; }
	function validDestination( root, date ) { return /^\d{4}-\d{2}-\d{2}$/.test( date ) && !!root.dataset.siteToday && date >= root.dataset.siteToday; }
	function pastePlan( root, date ) {
		var clip = root._planClipboard;
		if ( !clip ) { clipboardStatus( root, 'Copy or cut a planned workout first.' ); return; }
		if ( !validDestination( root, date ) ) { clipboardStatus( root, 'Choose today or a future date.' ); return; }
		if ( clip.mode === 'cut' && !window.confirm( 'Move ' + clip.plan.title + ' to ' + date + '?' ) ) return;
		var payload = { plan_action: clip.mode === 'cut' ? 'move' : 'duplicate', id: clip.plan.id, planned_date: date };
		mutatePlan( root, payload ).then( function ( success ) { if ( success && clip.mode === 'cut' ) root._planClipboard = null; } );
	}
	function showMoveDate( root, plan ) {
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false;
		var heading = document.createElement( 'h3' ); heading.textContent = 'Move ' + plan.title; panel.appendChild( heading );
		var form = document.createElement( 'form' ); form.dataset.calendarMoveForm = plan.id;
		var label = document.createElement( 'label' ); label.textContent = 'Destination date '; var input = document.createElement( 'input' ); input.type = 'date'; input.required = true; input.min = root.dataset.siteToday || ''; input.value = plan.planned_date >= input.min ? plan.planned_date : input.min; label.appendChild( input ); form.appendChild( label );
		var submit = document.createElement( 'button' ); submit.type = 'submit'; submit.className = 'swimlog-calendar-button'; submit.textContent = 'Save Move'; form.appendChild( submit ); panel.appendChild( form ); input.focus();
	}
	function showDateActions( root, date ) {
		if ( !validDestination( root, date ) ) return;
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false;
		var heading = document.createElement( 'h3' ); heading.textContent = date; panel.appendChild( heading );
		var paste = document.createElement( 'button' ); paste.type = 'button'; paste.className = 'swimlog-calendar-button'; paste.dataset.calendarPasteDate = date; paste.textContent = 'Paste Workout Here'; paste.disabled = !root._planClipboard; panel.appendChild( paste );
		var create = document.createElement( 'button' ); create.type = 'button'; create.className = 'swimlog-calendar-button'; create.dataset.calendarPlanDate = date; create.textContent = 'Plan Workout'; panel.appendChild( create );
	}
	function planContextMenu( root, plan ) {
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false;
		var title = document.createElement( 'h3' ); title.textContent = plan.title; panel.appendChild( title );
		[ [ 'copy', 'Copy Workout' ], [ 'cut', 'Cut Workout' ], [ 'move-date', 'Move to Date' ] ].forEach( function ( pair ) {
			if ( pair[0] !== 'copy' && ( plan.status !== 'planned' || plan.completed_workout_id ) ) return;
			var button = document.createElement( 'button' ); button.type = 'button'; button.className = 'swimlog-calendar-button'; button.dataset.calendarPlanAction = pair[0]; button.dataset.plan = JSON.stringify( plan ); button.textContent = pair[1]; panel.appendChild( button );
		} );
	}
	function closeWorkoutDetail( root, restoreFocus ) {
		var trigger = root.querySelector( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"],[data-calendar-event][aria-expanded="true"]' ), returnFocus = trigger || root._calendarReturnFocus;
		var panel = root.querySelector( '[data-calendar-detail]' ); if ( panel ) { panel.hidden = true; panel.textContent = ''; panel.removeAttribute( 'id' ); }
		root.querySelectorAll( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"],[data-calendar-event][aria-expanded="true"]' ).forEach( function ( item ) { item.setAttribute( 'aria-expanded', 'false' ); } );
		if ( restoreFocus && returnFocus && document.contains( returnFocus ) ) returnFocus.focus(); root._calendarReturnFocus = null;
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
			( byDate[ key ] = byDate[ key ] || [] ).push( item( 'swimlog-calendar-workout', parts, false, workout ) );
		} );
		( data.planned_workouts || [] ).forEach( function ( plan ) { var parts = [ plan.title || 'Planned Workout' ]; if ( plan.planned_distance_m !== null ) parts.push( Math.round( plan.planned_distance_m ).toLocaleString() + ' m' ); ( byDate[ plan.planned_date ] = byDate[ plan.planned_date ] || [] ).push( item( 'swimlog-calendar-planned-workout status-' + ( plan.status || 'planned' ), parts, true, null, plan ) ); } );
		( data.events || [] ).forEach( function ( event ) { ( byDate[ event.event_date ] = byDate[ event.event_date ] || [] ).push( item( 'swimlog-calendar-event', [ event.event_name || 'Swim Event' ], true, null, null, event ) ); } );
		for ( var i = 0; i < 7; i++ ) {
			var date = new Date( start.getFullYear(), start.getMonth(), start.getDate() + i ), key = dateKey( date ), cell = document.createElement( 'div' );
			cell.className = 'swimlog-calendar-day swimlog-calendar-week-day'; cell.setAttribute( 'role', 'gridcell' ); cell.dataset.date = key;
			var heading = document.createElement( 'span' ); heading.className = 'swimlog-calendar-date'; heading.textContent = date.toLocaleDateString( undefined, { weekday: 'short', month: 'short', day: 'numeric' } ); cell.appendChild( heading ); var planButton = planDateButton( key, root ); if ( planButton ) cell.appendChild( planButton );
			( byDate[ key ] || [] ).forEach( function ( child ) { cell.appendChild( child ); } ); grid.appendChild( cell );
		}
		var duplicate = document.createElement( 'button' ); duplicate.type = 'button'; duplicate.className = 'swimlog-calendar-button swimlog-calendar-duplicate-week'; duplicate.dataset.calendarDuplicateWeek = dateKey( start ); duplicate.textContent = 'Duplicate Week'; grid.appendChild( duplicate );
		var summary = ( data.weeks || [] )[0];
		if ( summary ) {
			var totals = document.createElement( 'div' ); totals.className = 'swimlog-calendar-week-summary';
			totals.textContent = summary.workout_count + ' recorded · ' + Math.round( summary.distance_m || 0 ).toLocaleString() + ' m actual · ' + duration( summary.active_time_ms || 0 ) + ' recorded time · ' + ( summary.planned_workout_count || 0 ) + ' planned · ' + Math.round( summary.planned_distance_m || 0 ).toLocaleString() + ' m planned · ' + ( summary.completed_planned_workout_count || 0 ) + ' matched plans';
			grid.appendChild( totals );
		}
		renderAgenda( root, data, start, end );
	}
	function showDuplicateWeekEditor( root, sourceStart ) {
		if ( document.activeElement && root.contains( document.activeElement ) ) root._calendarReturnFocus = document.activeElement;
		var panel = root.querySelector( '[data-calendar-detail]' ); panel.textContent = ''; panel.hidden = false;
		var heading = document.createElement( 'h3' ); heading.textContent = 'Duplicate Week'; panel.appendChild( heading );
		var form = document.createElement( 'form' ); form.dataset.calendarDuplicateWeekForm = '1'; form.dataset.sourceStart = sourceStart;
		var source = document.createElement( 'p' ); source.textContent = 'Source week begins ' + sourceStart + '.'; form.appendChild( source );
		var target = planField( form, 'Target week begins', 'target_start', 'date', sourceStart < root.dataset.siteToday ? root.dataset.siteToday : sourceStart ); target.required = true; target.min = root.dataset.siteToday;
		var submit = document.createElement( 'button' ); submit.type = 'submit'; submit.className = 'swimlog-calendar-button'; submit.textContent = 'Duplicate Week'; form.appendChild( submit );
		var close = document.createElement( 'button' ); close.type = 'button'; close.className = 'swimlog-calendar-button'; close.dataset.calendarDetailClose = '1'; close.textContent = 'Cancel'; form.appendChild( close ); panel.appendChild( form ); panel.scrollIntoView( { block: 'nearest' } ); target.focus();
	}
	function setViewButtons( root, viewName ) {
		root.dataset.view = viewName;
		root.querySelectorAll( '[data-calendar-view]' ).forEach( function ( button ) { var active = button.dataset.calendarView === viewName; button.classList.toggle( 'is-active', active ); button.setAttribute( 'aria-pressed', active ? 'true' : 'false' ); } );
	}
	function loadWeek( root, focus ) {
		var firstDay = root.dataset.firstDay || 'sunday', startDate = weekStart( focus, firstDay ), endDate = new Date( startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + 6 );
		var url = new URL( root.dataset.restUrl, window.location.origin ); url.searchParams.set( 'start', dateKey( startDate ) ); url.searchParams.set( 'end', dateKey( endDate ) );
		if ( root.dataset.user ) url.searchParams.set( 'user', root.dataset.user );
		var status = root.querySelector( '.swimlog-calendar-status' ); status.textContent = 'Loading calendar…'; root.classList.add( 'is-loading' ); root.setAttribute( 'aria-busy', 'true' );
		return fetch( url.toString(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': ( window.wpApiSettings && window.wpApiSettings.nonce ) || '' } } )
			.then( function ( response ) { if ( ! response.ok ) throw new Error( 'calendar' ); return response.json(); } )
			.then( function ( data ) { renderWeek( root, data, focus ); status.textContent = ''; } )
			.catch( function () { status.textContent = 'Unable to load calendar.'; } )
			.finally( function () { root.classList.remove( 'is-loading' ); root.setAttribute( 'aria-busy', 'false' ); } );
	}
	function loadMonth( root, focus ) {
		var start = dateKey( new Date( focus.getFullYear(), focus.getMonth(), 1 ) ), end = dateKey( new Date( focus.getFullYear(), focus.getMonth() + 1, 0 ) );
		var url = new URL( root.dataset.restUrl, window.location.origin ); url.searchParams.set( 'start', start ); url.searchParams.set( 'end', end );
		if ( root.dataset.user ) url.searchParams.set( 'user', root.dataset.user );
		var status = root.querySelector( '.swimlog-calendar-status' ); status.textContent = 'Loading calendar…'; root.classList.add( 'is-loading' ); root.setAttribute( 'aria-busy', 'true' );
		return fetch( url.toString(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': ( window.wpApiSettings && window.wpApiSettings.nonce ) || '' } } )
			.then( function ( response ) { if ( ! response.ok ) throw new Error( 'calendar' ); return response.json(); } )
			.then( function ( data ) { renderMonth( root, data, focus ); status.textContent = ''; } )
			.catch( function () { status.textContent = 'Unable to load calendar.'; } )
			.finally( function () { root.classList.remove( 'is-loading' ); root.setAttribute( 'aria-busy', 'false' ); } );
	}
	function init( root ) {
		root.dataset.view = 'month'; root.setAttribute( 'aria-busy', 'false' ); root.dataset.focusDate = dateKey( new Date( Number( root.dataset.year ), Number( root.dataset.month ) - 1, 1 ) );
		loadMonth( root, new Date( Number( root.dataset.year ), Number( root.dataset.month ) - 1, 1 ) );
		root.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && ! root.querySelector( '[data-calendar-detail]' ).hidden ) { event.preventDefault(); closeWorkoutDetail( root, true ); }
		} );
		root.addEventListener( 'invalid', function ( event ) { var form = event.target.closest( '[data-calendar-plan-form]' ); if ( ! form ) return; var status = root.querySelector( '.swimlog-calendar-status' ); if ( status ) status.textContent = 'Please correct the highlighted field: ' + ( event.target.closest( 'label' ) ? event.target.closest( 'label' ).textContent.trim() : event.target.name ) + '. ' + ( event.target.validationMessage || '' ); }, true );
		root.addEventListener( 'submit', function ( event ) {
			var form = event.target.closest( '[data-calendar-event-result-form]' ); if ( ! form ) return;
			event.preventDefault();
			var feedback = form.querySelector( '[role="status"]' ), submit = form.querySelector( '[type="submit"]' );
			var values = {}; new FormData( form ).forEach( function ( value, key ) { values[key] = value; } );
			submit.disabled = true; feedback.textContent = 'Saving event results…';
			requestEventResult( root, form.dataset.eventId, 'save', values ).then( function () { feedback.textContent = 'Event results saved.'; } )
				.catch( function ( error ) { feedback.textContent = error.message; } )
				.then( function () { if ( form.isConnected ) submit.disabled = false; } );
		} );
		root.addEventListener( 'submit', function ( event ) { var move = event.target.closest( '[data-calendar-move-form]' ); if ( !move ) return; event.preventDefault(); var date = move.querySelector( '[type="date"]' ).value; if ( !validDestination( root, date ) ) { clipboardStatus( root, 'Choose today or a future date.' ); return; } if ( window.confirm( 'Move this planned workout to ' + date + '?' ) ) mutatePlan( root, { plan_action: 'move', id: move.dataset.calendarMoveForm, planned_date: date } ); } );
		root.addEventListener( 'submit', function ( event ) { var form = event.target.closest( '[data-calendar-plan-form]' ); if ( ! form ) return; event.preventDefault(); var data = new FormData( form ), payload = { plan_action: 'save', id: form.dataset.planId || 0 }; data.forEach( function ( value, key ) { payload[key] = value; } ); payload.planned_duration_ms = payload.planned_duration_minutes ? Math.round( Number( payload.planned_duration_minutes ) * 60000 ) : ''; delete payload.planned_duration_minutes; mutatePlan( root, payload ); } );
		root.addEventListener( 'submit', function ( event ) { var form = event.target.closest( '[data-calendar-recurrence-form]' ); if ( ! form ) return; event.preventDefault(); var weekdays = Array.prototype.map.call( form.querySelectorAll( '[name="weekday"]:checked' ), function ( box ) { return box.value; } ); mutatePlan( root, { plan_action: 'recur', id: form.dataset.planId, weekdays: weekdays.join( ',' ), end_date: form.querySelector( '[name="end_date"]' ).value } ); } );
		root.addEventListener( 'submit', function ( event ) { var form = event.target.closest( '[data-calendar-duplicate-week-form]' ); if ( ! form ) return; event.preventDefault(); mutatePlan( root, { plan_action: 'duplicate_week', source_start: form.dataset.sourceStart, target_start: form.querySelector( '[name="target_start"]' ).value } ); } );
		root.addEventListener( 'contextmenu', function ( event ) {
			var planned = event.target.closest( '[data-calendar-planned-workout]' ), day = event.target.closest( '.swimlog-calendar-day[data-date]' );
			if ( planned ) { event.preventDefault(); try { planContextMenu( root, JSON.parse( planned.dataset.calendarPlannedWorkout ) ); } catch ( error ) {} }
			else if ( day && validDestination( root, day.dataset.date ) ) { event.preventDefault(); showDateActions( root, day.dataset.date ); }
		} );
		root.addEventListener( 'dragstart', function ( event ) {
			var planned = event.target.closest( '[data-calendar-planned-workout]' ); if ( !planned || !planned.draggable ) return;
			try { var plan = JSON.parse( planned.dataset.calendarPlannedWorkout ); root._dragPlan = plan; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData( 'text/plain', String( plan.id ) ); } catch ( error ) {}
		} );
		root.addEventListener( 'dragover', function ( event ) { var day = event.target.closest( '.swimlog-calendar-day[data-date], .swimlog-calendar-agenda-day[data-date]' ); root.querySelectorAll( '.is-drop-target' ).forEach( function ( node ) { if ( node !== day ) node.classList.remove( 'is-drop-target' ); } ); if ( root._dragPlan && day && validDestination( root, day.dataset.date ) ) { event.preventDefault(); event.dataTransfer.dropEffect = 'move'; day.classList.add( 'is-drop-target' ); } } );
		root.addEventListener( 'drop', function ( event ) {
			var day = event.target.closest( '.swimlog-calendar-day[data-date], .swimlog-calendar-agenda-day[data-date]' ), plan = root._dragPlan; root._dragPlan = null; root.querySelectorAll( '.is-drop-target' ).forEach( function ( node ) { node.classList.remove( 'is-drop-target' ); } );
			if ( !plan || !day || !validDestination( root, day.dataset.date ) ) return;
			event.preventDefault(); if ( day.dataset.date === plan.planned_date ) return;
			if ( window.confirm( 'Move ' + plan.title + ' to ' + day.dataset.date + '?' ) ) mutatePlan( root, { plan_action: 'move', id: plan.id, planned_date: day.dataset.date } );
		} );
		root.addEventListener( 'dragend', function () { root._dragPlan = null; root.querySelectorAll( '.is-drop-target' ).forEach( function ( node ) { node.classList.remove( 'is-drop-target' ); } ); } );
		root.addEventListener( 'click', function ( event ) {
			var go = event.target.closest( '[data-calendar-go]' ); if ( go ) { var input = root.querySelector( '[data-calendar-go-date]' ), status = root.querySelector( '.swimlog-calendar-status' ); if ( ! input || ! input.value ) { status.textContent = 'Choose a date first.'; if ( input ) input.focus(); return; } status.textContent = ''; var selected = new Date( input.value + 'T12:00:00' ); if ( ( root.dataset.view || 'month' ) === 'week' ) loadWeek( root, selected ); else loadMonth( root, new Date( selected.getFullYear(), selected.getMonth(), 1 ) ); return; }
			var duplicateWeek = event.target.closest( '[data-calendar-duplicate-week]' ); if ( duplicateWeek ) { showDuplicateWeekEditor( root, duplicateWeek.dataset.calendarDuplicateWeek ); return; }
			var pasteButton = event.target.closest( '[data-calendar-paste-date]' ); if ( pasteButton ) { pastePlan( root, pasteButton.dataset.calendarPasteDate ); return; }
			var dateButton = event.target.closest( '[data-calendar-plan-date]' ); if ( dateButton ) { if ( !root.dataset.siteToday || dateButton.dataset.calendarPlanDate < root.dataset.siteToday ) return; if ( root._planClipboard ) { showDateActions( root, dateButton.dataset.calendarPlanDate ); return; } showPlanEditor( root, dateButton.dataset.calendarPlanDate, null ); return; }
			var planAction = event.target.closest( '[data-calendar-plan-action]' ); if ( planAction ) { var plan; try { plan = JSON.parse( planAction.dataset.plan || '{}' ); } catch ( error ) { return; } var action = planAction.dataset.calendarPlanAction; if ( action === 'copy' || action === 'cut' ) { root._planClipboard = { mode: action, plan: plan }; clipboardStatus( root, ( action === 'copy' ? 'Copied ' : 'Cut ' ) + plan.title + '. Select a destination date and Paste Workout Here.' ); return; } if ( action === 'move-date' ) { showMoveDate( root, plan ); return; } if ( action === 'edit' ) { showPlanEditor( root, plan.planned_date, plan ); return; } if ( action === 'repeat' ) { showRecurrenceEditor( root, plan ); return; } if ( action === 'match' ) { showCompletionCandidates( root, plan ); return; } if ( action === 'delete' && ! window.confirm( 'Delete this planned workout?' ) ) return; mutatePlan( root, { plan_action: action, id: plan.id, planned_date: plan.planned_date } ); return; }
			var matchButton = event.target.closest( '[data-calendar-match-workout]' ); if ( matchButton ) { mutatePlan( root, { plan_action: 'associate', id: matchButton.dataset.planId, workout_id: matchButton.dataset.calendarMatchWorkout } ); return; }
			var workoutButton = event.target.closest( '[data-calendar-workout]' ); if ( workoutButton ) { showWorkoutDetail( root, workoutButton ); return; }
			var eventButton = event.target.closest( '[data-calendar-event]' ); if ( eventButton ) { showEventDetail( root, eventButton ); return; }
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
