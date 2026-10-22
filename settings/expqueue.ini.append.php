<?php /* #?ini charset="utf-8"?

# The background exports of CIE in the job queue (expqueue, Exponential 6.0.15 and later): the kernel's transfer runner
# queues them as this job type when expqueue is active and a queue worker is alive (kernelservices.ini.append.php,
# [Service_transfer.bccie] QueueJobType). The handler runs bccieTransferTask, which runs the export command of the
# job and waits for it. Without expqueue this file is not read.

[JobType_bccie.export]
Handler=expKernelTransferQueueHandler
Class=bulk
# seconds; an export of several hundred thousand submissions takes a while
Timeout=7200
# an export is not repeated by itself: the run history says why it failed, the export page starts it again
MaxAttempts=1
Description=A background export of the information collected by a form (CIE)

*/ ?>
