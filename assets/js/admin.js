(function () {
	'use strict';

	function updateGarminBatchButtons(form) {
		var selected = form.querySelectorAll('.swimlog-garmin-select:checked').length;
		form.querySelectorAll('[data-swimlog-garmin-import-selected="1"]').forEach(function (button) {
			button.disabled = selected === 0;
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('swimlog-garmin-batch-form');
		if (!form) {
			return;
		}
		form.querySelectorAll('.swimlog-garmin-select').forEach(function (checkbox) {
			checkbox.addEventListener('change', function () {
				updateGarminBatchButtons(form);
			});
		});
		updateGarminBatchButtons(form);
	});
}());
