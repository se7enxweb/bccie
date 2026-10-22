{* The links of CIE to Imports (the transfer registry and the run history of Exponential 6.0.15 and later). Include it
   only when the kernel has the registry ({if ezini_hasvariable( 'Service_transfer.registry', 'Class', 'kernelservices.ini' )}),
   so an older kernel never sees the operator. Shown only when the user may use Imports (transfer.sqliimport).
   Parameters: $show 'import' (open the file of a job in Imports: CSV only, Imports does not read SYLK) or 'runs'
   (every run of CIE in the run history of Imports); for 'import' $job_id and $format. *}
{def $cie_imports = transfer_tool( 'transfer.sqliimport' )}
{if and( $cie_imports, $cie_imports.active )}
{if eq( $show, 'import' )}
{if and( is_set( $job_id ), $job_id, is_set( $format ), eq( $format, 'csv' ) )}<a class="button" href={concat( 'sqliimport/edit/new/(from)/bccie/(job)/', $job_id )|ezurl}>{'Open in Imports'|i18n( 'extension/bccie' )}</a>{/if}
{else}
<p class="cie-muted"><a href={'sqliimport/list/(tool)/transfer.bccie'|ezurl}>{'All import and export runs'|i18n( 'extension/bccie' )}</a></p>
{/if}
{/if}
{undef $cie_imports}
