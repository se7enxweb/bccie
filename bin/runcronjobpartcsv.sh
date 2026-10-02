#!/bin/bash
# @description Run the exportcsv cronjob part by hand and log it to var/log/cie.log

# php ./runcronjobs.php -dall exportcsv;
# php ./runcronjobs.php -dall exportcsv | tee var/log/cie.log
# php ./runcronjobs.php -dall exportsylk | tee var/log/cie.log

php ./runcronjobs.php -dall exportcsv | tee var/log/cie.log

