{* The messages left by the last action: parameter $notices is a list of hashes with type (feedback, warning, error) and text. *}
{foreach $notices as $notice}
<div class="message-{$notice.type|wash}" role="status">
    <h2>{$notice.text|wash}</h2>
</div>
{/foreach}
