{* The progress of an export in the background: parameter $job_id (empty: nothing to show). The page polls bccie/job/<id>
   (bccie_admin.js; the status names in data-labels). *}
{if $job_id}
<section class="cie-card" id="cie-job" data-url="{concat( 'bccie/job/', $job_id )|ezurl( 'no' )}" data-download="{concat( 'bccie/download/', $job_id )|ezurl( 'no' )}" data-labels="{json_encode( hash( 'starting', 'starting'|i18n( 'extension/bccie' ), 'running', 'running'|i18n( 'extension/bccie' ), 'done', 'finished'|i18n( 'extension/bccie' ), 'failed', 'failed'|i18n( 'extension/bccie' ), 'unknown', 'unknown'|i18n( 'extension/bccie' ) ) )|wash}" aria-live="polite">
    <h2>{'Export in the background'|i18n( 'extension/bccie' )} <span class="a4-badge is-info" id="cie-job-status">{'starting'|i18n( 'extension/bccie' )}</span></h2>
    <div class="cie-progress" aria-hidden="true"><span id="cie-job-bar"></span></div>
    <p class="cie-muted" id="cie-job-result"></p>
    <p id="cie-job-download" hidden><a class="defaultbutton button" href="{concat( 'bccie/download/', $job_id )|ezurl( 'no' )}">{'Download the file'|i18n( 'extension/bccie' )}</a> <span class="cie-muted" id="cie-job-size"></span></p>
    <pre class="cie-log" id="cie-job-log"></pre>
</section>
{ezscript( array( 'bccie_admin.js' ) )}
{/if}
