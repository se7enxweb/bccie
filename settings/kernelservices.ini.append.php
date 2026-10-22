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
# The background exports (Exponential 6.0.15 and later, the kernel's doc/eng-us/features/6.0/transfer-runs.md): the job
# queue runs them as the job type bccie.export (settings/expqueue.ini.append.php) when expqueue is active and a worker
# is alive, else the kernel's content jobs, else CIE's own background process. Without Engine= the choice is
# transfer.ini's.
QueueJobType=bccie.export
# Who sees every user's CIE runs in the run history (everybody else sees their own): the users who may remove
# collected information (bccie/remove) already decide over every form's submissions, which is more than seeing who
# exported how many rows of which form. A row holds no submission data, and the job's file stays with the user who
# started it (bccieJob::accessible()); the cronjob exports are recorded as the cron user of cie.ini.
HistoryAllPermission=bccie/remove

*/ ?>
