{ezcss_require( 'bccie.css' )}
{def $totals = $summary.totals
     $last = $summary.last
     $qpart = cond( $vp.q, concat( '/(q)/', $vp.q_url ), '' )
     $other_order = cond( eq( $vp.order, 'asc' ), 'desc', 'asc' )}

<div class="context-block cie">
    <div class="box-header">
        <h1 class="context-title">{'Collected information export'|i18n( 'extension/bccie' )}</h1>
        <div class="header-mainline"></div>
    </div>
    <div class="box-content">

        {include uri='design:parts/bccie/notices.tpl' notices=$notices}

        {if $confirm_list|count}
        <form class="cie-confirm" action={'bccie/overview'|ezurl} method="post">
            <h2>{'Remove the collected information of these forms?'|i18n( 'extension/bccie' )}</h2>
            <ul>
            {foreach $confirm_list as $item}
                <li>{$item.name|wash} ({$item.collections} {'collections'|i18n( 'extension/bccie' )}) <input type="hidden" name="ObjectIDArray[]" value="{$item.id}" /></li>
            {/foreach}
            </ul>
            <p>{'The answers people sent are removed. The forms themselves stay. This cannot be undone: export the data first.'|i18n( 'extension/bccie' )}</p>
            <input class="button cie-danger" type="submit" name="ConfirmRemoveButton" value="{'Yes, remove'|i18n( 'extension/bccie' )|wash}" />
            <a class="button" href={'bccie/overview'|ezurl}>{'Cancel'|i18n( 'extension/bccie' )}</a>
        </form>
        {/if}

        {* Status strip. *}
        <div class="cie-status">
            <div class="cie-status-facts">
                <span><strong>{$totals.objects}</strong> {'forms with collected information'|i18n( 'extension/bccie' )}</span>
                <span><strong>{$totals.collections}</strong> {'collections'|i18n( 'extension/bccie' )}</span>
                {if $totals.newest}<span>{'Newest'|i18n( 'extension/bccie' )}: <strong>{$totals.newest|l10n( 'shortdatetime' )}</strong></span>{/if}
            </div>
            <div>
                {if $summary.attention}<span class="cie-pill is-warn">{'%count to look at'|i18n( 'extension/bccie',, hash( '%count', $summary.attention ) )}</span>
                {else}<span class="cie-pill is-ok">{'No problems'|i18n( 'extension/bccie' )}</span>{/if}
            </div>
        </div>

        <div class="cie-cards">
            <section class="cie-card">
                <h2>{'Last exports'|i18n( 'extension/bccie' )}</h2>
                {if $summary.log|count}
                <ul class="cie-list">
                    {foreach $summary.log as $entry}
                    <li>
                        <a href={concat( 'bccie/export/', $entry.object_id )|ezurl}>{$entry.name|wash}</a>
                        <span class="cie-muted">{$entry.format|upcase|wash}, {$entry.rows} {'rows'|i18n( 'extension/bccie' )}, {$entry.time|l10n( 'shortdatetime' )}, {$entry.by|wash}</span>
                    </li>
                    {/foreach}
                </ul>
                {else}
                <p class="cie-muted">{'Nothing was exported yet.'|i18n( 'extension/bccie' )}</p>
                {/if}
            </section>

            <section class="cie-card">
                <h2>{'Scheduled export'|i18n( 'extension/bccie' )}</h2>
                <p class="cie-muted">{'The cronjob parts write the collected information of the objects in cie.ini to files.'|i18n( 'extension/bccie' )}</p>
                <ul class="cie-stats">
                    <li><strong>{$summary.cron.objects}</strong><span class="cie-muted">{'objects'|i18n( 'extension/bccie' )}</span></li>
                    <li><strong>{if $summary.cron.range}{$summary.cron.range}{else}&infin;{/if}</strong><span class="cie-muted">{'days of data'|i18n( 'extension/bccie' )}</span></li>
                </ul>
                <p class="cie-muted">{'Folder'|i18n( 'extension/bccie' )}: <code>{$summary.cron.directory|wash}</code></p>
                <p class="cie-hint">{'In cron:'|i18n( 'extension/bccie' )} <code>php runcronjobs.php exportcsv</code> &middot; <code>php runcronjobs.php exportsylk</code></p>
                <p class="cie-hint">{'By hand:'|i18n( 'extension/bccie' )} <code>./console ext:bccie:export --help</code></p>
            </section>

            <section class="cie-card">
                <h2>{'Large exports'|i18n( 'extension/bccie' )}</h2>
                <p class="cie-muted">{'An export of more than %limit collections runs in the background; the file is offered for download when it is done.'|i18n( 'extension/bccie',, hash( '%limit', $summary.cron.direct_limit ) )}</p>
                <p>{if $summary.background}<span class="cie-pill is-ok">{'Background runs available'|i18n( 'extension/bccie' )}</span>{else}<span class="cie-pill is-warn">{'Background runs not available'|i18n( 'extension/bccie' )}</span>{/if}</p>
            </section>
        </div>

        <section>
            <h2>{'Problems and hints'|i18n( 'extension/bccie' )}</h2>
            {if $summary.problems|count}
            <ul class="cie-problems">
                {foreach $summary.problems as $problem}
                <li>
                    <span class="cie-pill {cond( eq( $problem.level, 'error' ), 'is-bad', eq( $problem.level, 'warning' ), 'is-warn', 'is-info' )}">{cond( eq( $problem.level, 'error' ), 'Problem'|i18n( 'extension/bccie' ), eq( $problem.level, 'warning' ), 'Warning'|i18n( 'extension/bccie' ), 'Hint'|i18n( 'extension/bccie' ) )}</span>
                    <span>{$problem.text|wash}{if $problem.url} <a href={$problem.url|ezurl}>{'Open'|i18n( 'extension/bccie' )}</a>{/if}</span>
                </li>
                {/foreach}
            </ul>
            {else}
            <p class="cie-ok">{'Nothing to report.'|i18n( 'extension/bccie' )}</p>
            {/if}
        </section>

        <h2>{'Forms with collected information'|i18n( 'extension/bccie' )}</h2>

        <form class="cie-filter" action={'bccie/overview'|ezurl} method="post">
            <label>{'Find a form'|i18n( 'extension/bccie' )}
                <input type="search" name="Filter" value="{$vp.q|wash}" placeholder="{'Name of the form'|i18n( 'extension/bccie' )|wash}" /></label>
            <input class="button" type="submit" name="FilterButton" value="{'Filter'|i18n( 'extension/bccie' )|wash}" />
            {if $vp.q}<a class="button" href={'bccie/overview'|ezurl}>{'Clear'|i18n( 'extension/bccie' )}</a>{/if}
        </form>

        {if $object_array|count}
        <form name="objects" action={'bccie/overview'|ezurl} method="post">
        <table class="list cie-table">
            <tr>
                {if $can_remove}<th class="tight"><input type="checkbox" data-cie-check-all="1" title="{'Select all'|i18n( 'extension/bccie' )|wash}" /></th>{/if}
                <th><a href={concat( 'bccie/overview', $qpart, '/(sort)/name/(order)/', cond( eq( $vp.sort, 'name' ), $other_order, 'asc' ) )|ezurl} class="{if eq( $vp.sort, 'name' )}is-sorted{if eq( $vp.order, 'desc' )} is-desc{/if}{/if}">{'Name'|i18n( 'extension/bccie' )}</a></th>
                <th>{'Type'|i18n( 'extension/bccie' )}</th>
                <th><a href={concat( 'bccie/overview', $qpart, '/(sort)/first_collection/(order)/', cond( eq( $vp.sort, 'first_collection' ), $other_order, 'asc' ) )|ezurl} class="{if eq( $vp.sort, 'first_collection' )}is-sorted{if eq( $vp.order, 'desc' )} is-desc{/if}{/if}">{'First collection'|i18n( 'extension/bccie' )}</a></th>
                <th><a href={concat( 'bccie/overview', $qpart, '/(sort)/last_collection/(order)/', cond( eq( $vp.sort, 'last_collection' ), $other_order, 'desc' ) )|ezurl} class="{if eq( $vp.sort, 'last_collection' )}is-sorted{if eq( $vp.order, 'desc' )} is-desc{/if}{/if}">{'Last collection'|i18n( 'extension/bccie' )}</a></th>
                <th class="cie-num"><a href={concat( 'bccie/overview', $qpart, '/(sort)/collections/(order)/', cond( eq( $vp.sort, 'collections' ), $other_order, 'desc' ) )|ezurl} class="{if eq( $vp.sort, 'collections' )}is-sorted{if eq( $vp.order, 'desc' )} is-desc{/if}{/if}">{'Collections'|i18n( 'extension/bccie' )}</a></th>
                <th class="tight">{'Actions'|i18n( 'extension/bccie' )}</th>
            </tr>
            {foreach $object_array as $row sequence array( 'bglight', 'bgdark' ) as $seq}
            <tr class="{$seq}">
                {if $can_remove}<td><input type="checkbox" name="ObjectIDArray[]" value="{$row.contentobject_id}" aria-label="{'Select'|i18n( 'extension/bccie' )|wash} {$row.name|wash}" /></td>{/if}
                <td class="cie-wrap">{$row.class_identifier|icon( 'small', $row.class_name )}&nbsp;<a href={concat( '/content/view/full/', $row.main_node_id )|ezurl}>{$row.name|wash}</a></td>
                <td>{$row.class_name|wash}</td>
                <td>{$row.first_collection|l10n( 'shortdatetime' )}</td>
                <td>{$row.last_collection|l10n( 'shortdatetime' )}</td>
                <td class="cie-num"><a href={concat( '/infocollector/collectionlist/', $row.contentobject_id )|ezurl}>{$row.collections}</a></td>
                <td class="cie-actions">
                    <a class="button" href={concat( '/bccie/export/', $row.contentobject_id )|ezurl}>{'Export'|i18n( 'extension/bccie' )}</a>
                    <a class="button" href={concat( '/infocollector/collectionlist/', $row.contentobject_id )|ezurl}>{'Details'|i18n( 'extension/bccie' )}</a>
                </td>
            </tr>
            {/foreach}
        </table>
        {if $can_remove}<p><input class="button" type="submit" name="RemoveObjectCollectionButton" value="{'Remove selected'|i18n( 'extension/bccie' )|wash}" title="{'Remove all information that was collected by the selected objects.'|i18n( 'extension/bccie' )|wash}" /></p>{/if}
        </form>

        <div class="cie-pager">
            <span>{'%count of %all forms'|i18n( 'extension/bccie',, hash( '%count', $object_array|count, '%all', $object_count ) )}</span>
            {include name=navigator uri='design:navigator/google.tpl' page_uri='bccie/overview' item_count=$object_count view_parameters=$view_parameters item_limit=$vp.limit}
        </div>
        {else}
        <div class="cie-empty">
            {if $vp.q}
            <p><strong>{'No form matches "%q".'|i18n( 'extension/bccie',, hash( '%q', $vp.q ) )|wash}</strong></p>
            <p><a href={'bccie/overview'|ezurl}>{'Show all forms'|i18n( 'extension/bccie' )}</a></p>
            {else}
            <p><strong>{'There are no objects that have collected any information.'|i18n( 'extension/bccie' )}</strong></p>
            <p>{'Make an attribute of a class collect information, and the answers sent through the form show up here.'|i18n( 'extension/bccie' )}</p>
            {/if}
        </div>
        {/if}
    </div>
</div>
{include uri='design:parts/bccie/script.tpl'}
