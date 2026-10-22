{*
  The links of the module's left menu, from menu.ini [Leftmenu_bccie]. Shared by parts/bccie/menu.tpl of every admin
  design, so the links are written once.
*}
{include uri='design:parts/ini_menu.tpl' ini_section='Leftmenu_bccie' i18n_hash=hash(
    'overview', 'Collected information export'|i18n( 'extension/bccie' ),
    'collected', 'Collected information'|i18n( 'extension/bccie' ),
    'project', 'Extension project'|i18n( 'extension/bccie' ) )}

{* the "Import and export" list of the transfer registry (Exponential 6.0.15 and later; nothing on an older kernel) *}
{if ezini_hasvariable( 'Service_transfer.registry', 'Class', 'kernelservices.ini' )}{include uri='design:transfer/left_menu.tpl' current='transfer.bccie'}{/if}
