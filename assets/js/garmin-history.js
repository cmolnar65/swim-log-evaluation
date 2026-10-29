(function () {
    'use strict';
    var settings = window.swimlogHistorySettings;
    if (!settings) return;
    async function update() {
        var region = document.getElementById('swimlog-history-status');
        if (!region || region.dataset.active !== '1') return;
        if (document.hidden || region.contains(document.activeElement)) { window.setTimeout(update, 10000); return; }
        var body = new URLSearchParams({action: 'swimlog_history_status', nonce: settings.nonce,
            job: region.dataset.job, offset: region.dataset.offset || '0', filter: region.dataset.filter || 'all'});
        try {
            var response = await fetch(settings.url, {method: 'POST', credentials: 'same-origin', body: body});
            if (!response.ok) throw new Error('status');
            var result = await response.json();
            if (!result.success || typeof result.data.html !== 'string') throw new Error('status');
            var doc = new DOMParser().parseFromString(result.data.html, 'text/html');
            var next = doc.getElementById('swimlog-history-status');
            if (!next || next.dataset.job !== region.dataset.job) throw new Error('status');
            if (region.isConnected && !region.contains(document.activeElement)) region.replaceWith(next);
        } catch (error) {
            // Keep the last usable controls and manual refresh link on failure.
        }
        window.setTimeout(update, 10000);
    }
    window.setTimeout(update, 10000);
}());
