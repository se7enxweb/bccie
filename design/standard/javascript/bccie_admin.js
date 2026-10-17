/*
 * The bccie administration pages (bccie/overview, bccie/export, the information collection lists) without inline
 * code (they work under a Content Security Policy without 'unsafe-inline'). Printed by parts/bccie/script.tpl, job.tpl
 * and the infocollector templates of the admin design; sets itself up once however often it is printed.
 *  - a form with data-cie-confirm asks before it is sent; a button with data-cie-confirm-button asks for itself;
 *  - input[data-cie-check-all] checks or unchecks every named checkbox of its table;
 *  - #cie-job (parts/bccie/job.tpl) polls data-url for the background export and shows its status, progress, result,
 *    log and download; the status names come from data-labels (JSON);
 *  - [data-cie-toggle-name] (the "Invert selection" icon of the information collection lists) inverts the selection.
 */
(function () {
    'use strict';

    function startJob() {
        var box = document.getElementById('cie-job');
        if (!box || box.hasAttribute('data-cie-polling')) {
            return;
        }
        box.setAttribute('data-cie-polling', '1');
        var labels = {};
        try {
            labels = JSON.parse(box.getAttribute('data-labels') || '{}');
        } catch (e) {
            labels = {};
        }
        var classes = { starting: 'is-info', running: 'is-warn', done: 'is-ok', failed: 'is-bad', unknown: 'is-muted' };

        function poll() {
            fetch(box.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (job) {
                    var status = document.getElementById('cie-job-status');
                    status.textContent = labels[job.status] || job.status;
                    status.className = 'cie-pill ' + (classes[job.status] || 'is-muted');
                    document.getElementById('cie-job-log').textContent = (job.log || []).join('\n');
                    var result = job.result || {};
                    var parts = [];
                    if (typeof result.done === 'number' && typeof result.total === 'number') {
                        parts.push(result.done + ' / ' + result.total);
                        document.getElementById('cie-job-bar').style.width = (result.total ? Math.round(100 * result.done / result.total) : 100) + '%';
                    }
                    if (result.error) { parts.push(result.error); }
                    document.getElementById('cie-job-result').textContent = parts.join(' · ');
                    if (job.status === 'done' && job.file) {
                        document.getElementById('cie-job-download').hidden = false;
                        document.getElementById('cie-job-size').textContent = job.file.name + ' (' + Math.max(1, Math.round(job.file.size / 1024)) + ' KB)';
                        document.getElementById('cie-job-bar').style.width = '100%';
                    }
                    if (job.status === 'starting' || job.status === 'running') { window.setTimeout(poll, 1500); }
                })
                .catch(function () { window.setTimeout(poll, 5000); });
        }
        poll();
    }

    if (window.BCCIEAdmin) {
        // printed again (the job box may only be in the page now)
        startJob();
        return;
    }
    window.BCCIEAdmin = { startJob: startJob };

    // A form with data-cie-confirm asks before it is sent; a button with data-cie-confirm-button asks for itself.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var submitter = event.submitter;
        var text = submitter && submitter.getAttribute('data-cie-confirm-button');
        if (!text && form.getAttribute) { text = form.getAttribute('data-cie-confirm'); }
        if (text && !window.confirm(text)) { event.preventDefault(); }
    });

    // Select all checkboxes of a table.
    document.addEventListener('change', function (event) {
        var box = event.target;
        if (!box || !box.matches || !box.matches('input[data-cie-check-all]')) {
            return;
        }
        var table = box.closest('table');
        if (table) {
            table.querySelectorAll('input[type=checkbox][name]').forEach(function (c) { c.checked = box.checked; });
        }
    });

    // The information collection lists: the "Invert selection" icon of the table head
    // (data-cie-toggle-form="<form name>" data-cie-toggle-name="<checkbox name>") calls the admin's ezjs_toggleCheckboxes().
    document.addEventListener('click', function (event) {
        var icon = event.target && event.target.closest ? event.target.closest('[data-cie-toggle-name]') : null;
        if (!icon) {
            return;
        }
        event.preventDefault();
        var form = document.forms[icon.getAttribute('data-cie-toggle-form')] || icon.closest('form');
        if (form && typeof window.ezjs_toggleCheckboxes === 'function') {
            window.ezjs_toggleCheckboxes(form, icon.getAttribute('data-cie-toggle-name'));
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startJob);
    } else {
        startJob();
    }
})();
