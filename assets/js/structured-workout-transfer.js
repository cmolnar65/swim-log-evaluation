/* Local clipboard only: no prescription content is sent to another service. */
(function () {
  'use strict';
  document.querySelectorAll('[data-swimlog-copy]').forEach(function (button) {
    button.addEventListener('click', async function () {
      const input = document.getElementById(button.dataset.swimlogCopy);
      const status = button.parentElement.querySelector('[data-swimlog-copy-status]');
      if (!input) return;
      try {
        if (!navigator.clipboard || !navigator.clipboard.writeText) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(input.value);
        if (status) status.textContent = 'Copied.';
      } catch (error) {
        input.focus(); input.select();
        if (status) status.textContent = 'Text selected. Press Ctrl+C (Command+C on Mac) to copy.';
      }
    });
  });
}());
