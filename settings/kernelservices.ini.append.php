<?php /* #?ini charset="utf-8"?

# CIE in Exponential's transfer registry (kernel 6.0.15 and later, settings/kernelservices.ini; the kernel's
# doc/eng-us/features/6.0/transfer-registry.md). The kernel lists the tool in the "Which import or export tool do I
# need?" help page, the "Looking for…?" boxes of the other tools, the "Import and export" lists of the Setup menu and
# the left menus and the administration's search. Older kernels ignore this file.

[Service_transfer.bccie]
Class=expKernelTransferTool
Factory=expKernelTransferTool::fromSettings
Arguments[]=transfer.bccie
Scope=worker
Tags[]=transfer.tool
Description=Download what the site's forms collected as spreadsheet files, in the character set your spreadsheet needs, now or by cronjob.
Name=Collected information export (CIE)
Direction=export
Scopes[]=data
Formats[]=CSV
Formats[]=SYLK
EntryURL=bccie/overview
Permission=bccie/read
HelpPage=ext/bccie/README.md
RoundTrip[]=transfer.infocollector
RoundTrip[]=transfer.sqliimport
Keywords[]=form submissions
Keywords[]=contact form
Keywords[]=survey
Keywords[]=excel
Keywords[]=character set
Keywords[]=Formular
Keywords[]=Einsendungen
Keywords[]=Umfrage
Weight=71

*/ ?>
