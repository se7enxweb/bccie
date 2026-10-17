{*
  The links of the module's left menu, from menu.ini [Leftmenu_bccie]. Shared by parts/bccie/menu.tpl of every admin
  design, so the links are written once.
*}
{include uri='design:parts/ini_menu.tpl' ini_section='Leftmenu_bccie' i18n_hash=hash(
    'overview', 'Collected information export'|i18n( 'extension/bccie' ),
    'collected', 'Collected information'|i18n( 'extension/bccie' ),
    'project', 'Extension project'|i18n( 'extension/bccie' ) )}
