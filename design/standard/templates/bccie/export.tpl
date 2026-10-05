{ezcss_require( 'bccie.css' )}
{def $display_leave_empty_option = ezini( 'CieSettings', 'DisplayLeaveEmptyOption', 'cie.ini' )|eq( 'enabled' )}

<div class="context-block cie">
    <div class="box-header">
        <h1 class="context-title">{'Information collected by %object_name'|i18n( 'extension/bccie',, hash( '%object_name', $object.name ) )|wash} [{$collection_count}]</h1>
        <div class="header-mainline"></div>
    </div>
    <div class="box-content">

        {include uri='design:parts/bccie/notices.tpl' notices=$notices}
        {include uri='design:parts/bccie/job.tpl' job_id=$job_id}

        {if $error_list|count}
        <div class="message-error" role="alert">
            <h2>{'The export was not started.'|i18n( 'extension/bccie' )}</h2>
            <ul>{foreach $error_list as $error}<li>{$error|wash}</li>{/foreach}</ul>
        </div>
        {/if}

        {if $confirm_remove}
        <form class="cie-confirm" action={concat( 'bccie/export/', $object.id )|ezurl} method="post">
            <h2>{'Remove all collected information of this form?'|i18n( 'extension/bccie' )}</h2>
            <p>{'%count collections of "%name" are removed. The form itself stays. This cannot be undone: export the data first.'|i18n( 'extension/bccie',, hash( '%count', $collection_count, '%name', $object.name ) )|wash}</p>
            <input class="button cie-danger" type="submit" name="ConfirmRemoveButton" value="{'Yes, remove'|i18n( 'extension/bccie' )|wash}" />
            <input class="button" type="submit" name="CancelRemoveButton" value="{'Cancel'|i18n( 'extension/bccie' )|wash}" />
        </form>
        {/if}

        {if $collection_count|gt( 0 )}
        <p class="cie-muted">
            {'%count collections between %first and %last.'|i18n( 'extension/bccie',, hash( '%count', $collection_count, '%first', $first_date, '%last', $last_date ) )}
            <a href={concat( '/infocollector/collectionlist/', $object.id )|ezurl}>{'Show the collections'|i18n( 'extension/bccie' )}</a>
        </p>

        <form class="cie-form" name="collections" method="post" action={concat( 'bccie/export/', $object.id )|ezurl}>

            <fieldset>
                <legend>{'Fields'|i18n( 'extension/bccie' )}</legend>
                <div class="cie-grid">
                    <label class="cie-check"><input type="checkbox" name="include_id" value="1"{if $include_id} checked="checked"{/if} /> {'Collection ID'|i18n( 'extension/bccie' )}</label>
                    {foreach $attributes as $attribute}
                    <label class="cie-check"><input type="checkbox" name="columns[]" value="{$attribute.id}"{if $attribute.checked} checked="checked"{/if} /> {$attribute.name|wash} <span class="cie-muted">[{$attribute.id}]</span>
                        {if $attribute.exportable|not}<span class="cie-pill is-info" title="{'This datatype has no export handler; its stored text is exported.'|i18n( 'extension/bccie' )|wash}">{$attribute.datatype|wash}</span>{/if}</label>
                    {/foreach}
                </div>
                {if $errors.fields}<p class="cie-form-error">{$errors.fields|wash}</p>{/if}
                <div class="cie-grid">
                    <label class="cie-check"><input name="creation_date" type="checkbox" value="1"{if $input.creation_date} checked="checked"{/if} /> {'Export Creation Date'|i18n( 'design/bccie/export' )}</label>
                    <label class="cie-check"><input name="modification_date" type="checkbox" value="1"{if $input.modification_date} checked="checked"{/if} /> {'Export Modification Date'|i18n( 'design/bccie/export' )}</label>
                </div>
            </fieldset>

            <fieldset>
                <legend>{'Date range'|i18n( 'extension/bccie' )}</legend>
                <div class="cie-grid">
                    <label class="cie-field">{'Start Date'|i18n( 'design/bccie/export' )}
                        <input type="date" name="start_date" value="{cond( $start_date, $start_date, $first_date )|wash}" /></label>
                    <label class="cie-field">{'End Date'|i18n( 'design/bccie/export' )}
                        <input type="date" name="end_date" value="{cond( $end_date, $end_date, $last_date )|wash}" /></label>
                </div>
                <p class="cie-hint">{'Empty dates mean no limit. A collection counts for the day it was created.'|i18n( 'extension/bccie' )}</p>
                {if $errors.dates}<p class="cie-form-error">{$errors.dates|wash}</p>{/if}
            </fieldset>

            <fieldset>
                <legend>{'Export type'|i18n( 'design/bccie/export' )}</legend>
                <div class="cie-grid">
                    <label class="cie-field">{'Export type'|i18n( 'design/bccie/export' )}
                        <select name="export_type">
                            <option value="csv"{if $input.format|eq( 'csv' )} selected="selected"{/if}>CSV</option>
                            <option value="sylk"{if $input.format|eq( 'sylk' )} selected="selected"{/if}>{'SYLK (Excel)'|i18n( 'design/bccie/export' )}</option>
                        </select></label>
                    <label class="cie-field">{'Character set'|i18n( 'extension/bccie' )}
                        <select name="charset">
                            <option value="">{'Default (%name)'|i18n( 'extension/bccie',, hash( '%name', $default_charset ) )|wash}</option>
                            {foreach $charsets as $key}
                            <option value="{$key|wash}"{if $input.charset|eq( $key )} selected="selected"{/if}>{$key|wash}</option>
                            {/foreach}
                        </select></label>
                </div>
                {if $errors.format}<p class="cie-form-error">{$errors.format|wash}</p>{/if}
                {if $errors.charset}<p class="cie-form-error">{$errors.charset|wash}</p>{/if}
                <p class="cie-hint">{'utf8bom opens correctly in Excel on Windows, utf16le in Excel on macOS. Text that starts with = + - or @ gets a leading quote, so a spreadsheet does not run it as a formula.'|i18n( 'extension/bccie' )}</p>
            </fieldset>

            <fieldset>
                <legend>{'Separation char for CSV export'|i18n( 'design/bccie/export' )}</legend>
                <div class="cie-grid">
                    {foreach $separators as $char => $name}
                    <label class="cie-check"><input type="radio" name="separation_char" value="{$char|wash}"{if $input.separator|eq( $char )} checked="checked"{/if} /> {$name|upfirst|i18n( 'design/bccie/export' )} ('{$char|wash}')</label>
                    {/foreach}
                </div>
                {if $errors.separator}<p class="cie-form-error">{$errors.separator|wash}</p>{/if}
            </fieldset>

            <div class="cie-actions">
                <input class="defaultbutton" type="submit" name="DoExport" value="{'Do export'|i18n( 'design/bccie/export' )}" title="{'Do export.'|i18n( 'design/bccie/export' )}"{if $collection_count|gt( $direct_limit )} disabled="disabled"{/if} />
                <input class="button" type="submit" name="RunBackgroundButton" value="{'Run in the background'|i18n( 'extension/bccie' )|wash}"{if $summary_background|not} disabled="disabled"{/if} />
                <span class="cie-muted">{'More than %limit collections must run in the background; the file is offered for download when it is done.'|i18n( 'extension/bccie',, hash( '%limit', $direct_limit ) )}</span>
            </div>
            {if $errors.size}<p class="cie-form-error">{$errors.size|wash}</p>{/if}

            {if $can_remove}
            <div class="cie-danger-zone">
                <h2>{'Remove collected information'|i18n( 'extension/bccie' )}</h2>
                <p class="cie-muted">{'Removes every collection of this form. Export the data first.'|i18n( 'extension/bccie' )}</p>
                <input class="button" type="submit" name="RemoveCollectedButton" value="{'Remove all collected information'|i18n( 'extension/bccie' )|wash}" />
            </div>
            {/if}
        </form>
        {else}
        <div class="cie-empty">
            <p><strong>{'No information has been collected by this object.'|i18n( 'design/admin/infocollector/collectionlist' )}</strong></p>
            <p><a href={'bccie/overview'|ezurl}>{'Back to the overview'|i18n( 'extension/bccie' )}</a></p>
        </div>
        {/if}

        {if $history|count}
        <section>
            <h2>{'Exports of this form'|i18n( 'extension/bccie' )}</h2>
            <table class="list cie-table">
                <tr><th>{'Time'|i18n( 'extension/bccie' )}</th><th>{'Type'|i18n( 'extension/bccie' )}</th><th class="cie-num">{'Rows'|i18n( 'extension/bccie' )}</th><th>{'By'|i18n( 'extension/bccie' )}</th><th>{'Started from'|i18n( 'extension/bccie' )}</th><th class="tight"></th></tr>
                {foreach $history as $entry sequence array( 'bglight', 'bgdark' ) as $seq}
                <tr class="{$seq}">
                    <td>{$entry.time|l10n( 'shortdatetime' )}</td>
                    <td>{$entry.format|upcase|wash}</td>
                    <td class="cie-num">{$entry.rows}</td>
                    <td>{$entry.by|wash}</td>
                    <td>{$entry.source|wash}</td>
                    <td class="cie-actions">{if $entry.downloadable}<a class="button" href={concat( 'bccie/download/', $entry.job )|ezurl}>{'Download'|i18n( 'extension/bccie' )}</a>{/if}</td>
                </tr>
                {/foreach}
            </table>
        </section>
        {/if}
    </div>
</div>
{include uri='design:parts/bccie/script.tpl'}
