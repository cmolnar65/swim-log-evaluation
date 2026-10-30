(function () {
	'use strict';

	function updateGarminBatchButtons(form) {
		var selected = form.querySelectorAll('.swimlog-garmin-select:checked').length;
		form.querySelectorAll('[data-swimlog-garmin-import-selected="1"]').forEach(function (button) {
			button.disabled = selected === 0;
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
        var context = document.querySelector('[data-swimlog-context-owner]');
        if (context) {
            var pages = JSON.parse(context.dataset.swimlogContextPages);
            document.querySelectorAll('#toplevel_page_swimlog-dashboard a').forEach(function (link) {
                var url = new URL(link.href, window.location.href);
                if (url.origin !== window.location.origin || !pages.includes(url.searchParams.get('page'))) return;
                url.searchParams.set('swimmer_id', context.dataset.swimlogContextOwner);
                url.searchParams.set('_swimlog_swimmer_nonce', context.dataset.swimlogContextNonce);
                link.href = url.href;
            });
        }

        var formError = document.querySelector('[data-swimlog-form-error]');
        if (formError) formError.focus();
        document.querySelectorAll('.swimlog-admin .swimlog-table-wrap').forEach(function (region) {
            var heading = region.previousElementSibling;
            while (heading && !/^H[1-6]$/.test(heading.tagName)) heading = heading.previousElementSibling;
            if (!heading) heading = region.closest('.swimlog-admin').querySelector('h1');
            if (heading) region.setAttribute('aria-label', heading.textContent.trim());
        });


        document.querySelectorAll('.swimlog-garmin-nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                var target = document.querySelector(link.getAttribute('href'));
                if (target && target.tagName === 'DETAILS') {
                    target.open = true;
                    target.querySelector('summary').focus();
                }
            });
        });

		var apiKey = document.getElementById('swimlog-garmin-api-key');
		var apiKeySubmit = document.getElementById('swimlog-garmin-api-key-submit');
		if (apiKey && apiKeySubmit) {
			var updateApiKeySubmit = function () {
				apiKeySubmit.disabled = apiKey.value.trim() === '';
			};
			apiKey.addEventListener('input', updateApiKeySubmit);
			updateApiKeySubmit();
		}

		var manualRows = document.getElementById('swimlog-manual-set-rows');
		var addManualRow = document.getElementById('swimlog-add-set-row');
        if (manualRows && addManualRow) {
            var prepareManualRow = function (row, number) {
                row.querySelector('.swimlog-set-number').textContent = String(number);
                row.querySelectorAll('[data-field-label]').forEach(function (field) {
                    field.setAttribute('aria-label', row.dataset.setLabel.replace('%1$d', number).replace('%2$s', field.dataset.fieldLabel));
                });
                var timing = row.querySelector('[name="segment_timing_type[]"]');
                var updateTiming = function () {
                    var modes = {segment_rep_times:'measured_rep', segment_set_time:'measured_set', segment_interval:'interval'};
                    Object.keys(modes).forEach(function (field) {
                        var input = row.querySelector('[name="'+field+'[]"]');
                        input.hidden = timing.value !== modes[field];
                    });
                };
                timing.addEventListener('change', updateTiming); updateTiming();
            };
            manualRows.querySelectorAll('.swimlog-manual-set-row').forEach(function (row, i) { prepareManualRow(row, i+1); });
            addManualRow.addEventListener('click', function () {
                var rows = manualRows.querySelectorAll('.swimlog-manual-set-row');
                var clone = rows[rows.length-1].cloneNode(true);
                clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
                manualRows.appendChild(clone); prepareManualRow(clone, rows.length+1);
                clone.querySelector('input').focus();
            });
        }
        var carousel = document.querySelector('.swimlog-dashboard .swimlog-dash-chart-track');
        if (carousel) {
            var panels = carousel.querySelectorAll('.swimlog-dash-chart');
            var active = 0;
            var move = function (step) {
                active = (active + step + panels.length) % panels.length;
                panels[active].scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest', inline: 'start'});
            };
            var prev = document.querySelector('[data-swimlog-chart-prev]');
            var next = document.querySelector('[data-swimlog-chart-next]');
            if (prev) prev.addEventListener('click', function () { move(-1); });
            if (next) next.addEventListener('click', function () { move(1); });
            carousel.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                    event.preventDefault();
                    move(event.key === 'ArrowRight' ? 1 : -1);
                }
            });
        }

		var form = document.getElementById('swimlog-garmin-batch-form');
		if (!form) {
			return;
		}
		form.querySelectorAll('.swimlog-garmin-select').forEach(function (checkbox) {
			checkbox.addEventListener('change', function () {
				updateGarminBatchButtons(form);
			});
		});

		form.addEventListener('submit', function () {
			var selected = form.querySelectorAll('.swimlog-garmin-select:checked').length;
			if (selected === 0) {
				return;
			}

			form.querySelectorAll('.swimlog-garmin-select').forEach(function (checkbox) {
				checkbox.addEventListener('click', function (event) {
					event.preventDefault();
				});
				checkbox.setAttribute('aria-disabled', 'true');
			});
			form.querySelectorAll('[data-swimlog-garmin-import-selected="1"]').forEach(function (button) {
				button.disabled = true;
				button.value = 'Importing…';
			});
			var status = document.getElementById('swimlog-garmin-import-progress');
			if (status) {
				status.hidden = false;
				status.querySelector('.spinner').classList.add('is-active');
				status.querySelector('.swimlog-garmin-import-progress-text').textContent =
					'Importing ' + selected + ' selected ' + (selected === 1 ? 'activity' : 'activities') + '… Please wait.';
			}
		});

		updateGarminBatchButtons(form);
	});
}());


