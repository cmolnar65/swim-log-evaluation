(function () {
	'use strict';

	function updateGarminBatchButtons(form) {
		var selected = form.querySelectorAll('.swimlog-garmin-select:checked').length;
		form.querySelectorAll('[data-swimlog-garmin-import-selected="1"]').forEach(function (button) {
			button.disabled = selected === 0;
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var garminPage = document.querySelector('.swimlog-admin[data-swimlog-garmin-configured="1"]');
		var garminActivities = document.getElementById('swimlog-garmin-activities');
		if (garminPage && garminActivities) {
			var statusTable = garminPage.querySelector('.form-table');
			if (statusTable) {
				statusTable.insertAdjacentElement('afterend', garminActivities);
			}
		}

		var apiKey = document.getElementById('swimlog-garmin-api-key');
		var apiKeySubmit = document.getElementById('swimlog-garmin-api-key-submit');
		if (apiKey && apiKeySubmit) {
			var updateApiKeySubmit = function () {
				apiKeySubmit.disabled = apiKey.value.trim() === '';
			};
			apiKey.addEventListener('input', updateApiKeySubmit);
			updateApiKeySubmit();
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
