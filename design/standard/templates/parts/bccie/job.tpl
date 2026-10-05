{* The progress of an export in the background: parameter $job_id (empty: nothing to show). The page polls bccie/job/<id>. *}
{if $job_id}
<section class="cie-card" id="cie-job" data-url="{concat( 'bccie/job/', $job_id )|ezurl( 'no' )}" data-download="{concat( 'bccie/download/', $job_id )|ezurl( 'no' )}" aria-live="polite">
    <h2>{'Export in the background'|i18n( 'extension/bccie' )} <span class="cie-pill is-info" id="cie-job-status">{'starting'|i18n( 'extension/bccie' )}</span></h2>
    <div class="cie-progress" aria-hidden="true"><span id="cie-job-bar"></span></div>
    <p class="cie-muted" id="cie-job-result"></p>
    <p id="cie-job-download" hidden><a class="defaultbutton button" href="{concat( 'bccie/download/', $job_id )|ezurl( 'no' )}">{'Download the file'|i18n( 'extension/bccie' )}</a> <span class="cie-muted" id="cie-job-size"></span></p>
    <pre class="cie-log" id="cie-job-log"></pre>
</section>
<script>
(function () {ldelim}
    var box = document.getElementById('cie-job');
    var labels = {ldelim} starting: '{'starting'|i18n( 'extension/bccie' )|wash( 'javascript' )}', running: '{'running'|i18n( 'extension/bccie' )|wash( 'javascript' )}',
                          done: '{'finished'|i18n( 'extension/bccie' )|wash( 'javascript' )}', failed: '{'failed'|i18n( 'extension/bccie' )|wash( 'javascript' )}', unknown: '{'unknown'|i18n( 'extension/bccie' )|wash( 'javascript' )}' {rdelim};
    var classes = {ldelim} starting: 'is-info', running: 'is-warn', done: 'is-ok', failed: 'is-bad', unknown: 'is-muted' {rdelim};
    function poll() {ldelim}
        fetch(box.getAttribute('data-url'), {ldelim} credentials: 'same-origin', headers: {ldelim} 'Accept': 'application/json' {rdelim} {rdelim})
            .then(function (r) {ldelim} return r.json(); {rdelim})
            .then(function (job) {ldelim}
                var status = document.getElementById('cie-job-status');
                status.textContent = labels[job.status] || job.status;
                status.className = 'cie-pill ' + (classes[job.status] || 'is-muted');
                document.getElementById('cie-job-log').textContent = (job.log || []).join('\n');
                var result = job.result || {ldelim}{rdelim};
                var parts = [];
                if (typeof result.done === 'number' && typeof result.total === 'number') {ldelim}
                    parts.push(result.done + ' / ' + result.total);
                    document.getElementById('cie-job-bar').style.width = (result.total ? Math.round(100 * result.done / result.total) : 100) + '%';
                {rdelim}
                if (result.error) {ldelim} parts.push(result.error); {rdelim}
                document.getElementById('cie-job-result').textContent = parts.join(' · ');
                if (job.status === 'done' && job.file) {ldelim}
                    document.getElementById('cie-job-download').hidden = false;
                    document.getElementById('cie-job-size').textContent = job.file.name + ' (' + Math.max(1, Math.round(job.file.size / 1024)) + ' KB)';
                    document.getElementById('cie-job-bar').style.width = '100%';
                {rdelim}
                if (job.status === 'starting' || job.status === 'running') {ldelim} window.setTimeout(poll, 1500); {rdelim}
            {rdelim})
            .catch(function () {ldelim} window.setTimeout(poll, 5000); {rdelim});
    {rdelim}
    poll();
{rdelim})();
</script>
{/if}
