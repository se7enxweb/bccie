Changes for 1.1.12
==================

Bugs fixed

* Fixed: Every datatype was written by the base handler: the class `Parser` had an old style constructor, which PHP 8 does not call, so no datatype handler (option, country, selection, relation ...) was ever loaded.
* Fixed: Characters outside Latin-1 (the euro sign, Polish, Cyrillic, Greek ...) were exported as question marks, because every cell was converted to ISO-8859-1 and back. Cells stay UTF-8; the output format handlers convert the finished file.
* Fixed: A cell that starts with `=`, `+`, `-` or `@` (or a tab) is run as a formula by a spreadsheet. Such a text gets a leading single quote in CSV and SYLK files; plain numbers are left alone.
* Fixed: A double quote in a CSV cell ended the cell. Quotes are doubled; the line no longer ends with a separator (an empty last column).
* Fixed: The SYLK export used the first collection as its header and dropped it from the data. The field names are the header now; quotes and semicolons in a cell are escaped.
* Fixed: The header of a CSV file did not match the columns when a field was set to "Leave empty" or "Ignore" or the collection id was not the first field. There is one header cell per column, in the order of the fields.
* Fixed: The scheduled export (cronjob parts exportcsv and exportsylk) called functions that were not loaded, wrote rows without the ID its header announced, ignored `ExcludeAttributeID` and `RemoveExported`, used the object's name in the file name unchecked and printed with `print_r`. Rewritten on the runner.
* Fixed: A datatype without a handler (a file, an image) made the export stop with a type error; the stored text is exported. The handlers of relations, selections, options and countries no longer stop on a missing object or option; the URL handler no longer puts the separator inside a cell.
* Fixed: The file name of a download and of a scheduled file is limited to letters, digits, dot, dash and underscore; the Content-Disposition header quotes it; the charset in the Content-Type header names the real charset of a UTF-16LE or Windows-1252 file.
* Fixed: Removing collected information needed only `bccie/read`, took its selection from the session and was not audited. It needs `bccie/remove` now, asks for confirmation on the page and is written to the audit.
* Fixed: The list of forms read every collection of every form (one query per form), had no order for its paging; one grouped query now. Invalid dates were turned into a date by `mktime` or ended in a generic error page.
* Fixed: The left menu was empty (the template named a section that did not exist) and the export link of `infocollector/overview` had no object id.

New

* Added: The start page `bccie/overview` is a dashboard: forms and collections, the last exports, the scheduled export, large exports and the problems found, with the list of forms (filter, sort, paging) and removal with confirmation.
* Added: The export page validates the fields, the date range, the type, the separator and the character set and shows the messages next to the form; every old form and address (`bccie/doexport`, `field_N`, `start_day`) keeps working.
* Added: A large export runs in the background (`expProcessTools`), shows its progress and offers the file for download: views `bccie/job` and `bccie/download`; setting `DirectExportLimit`.
* Added: Console commands `ext:bccie:export` (one form, or `--cron` for the scheduled export), `ext:bccie:status` and `ext:bccie:purge`, all with `--help`, `--dry-run` where they change data and the aliases `cie-export`, `cie-status`, `cie-purge`. The cronjob parts are runnable classes behind thin stubs. A run takes a lock, records its last run and raises the kernel's runnable events for the audit.
* Added: Policy function `bccie/remove`; setting `CronUser`.
* Added: English and German texts for every string of the new views.
* Added: Tests for the writers, the handlers, the validation, the runner and the commands.
