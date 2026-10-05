BC CIE
======

What is the BC CIE extension?
================================

BC CIE is a true Exponential extension that provides cronjob parts, class methods and module views to provide easy export of collected informations from content objects into to csv or sylk (Excel) export files.


Version
=======================

The current version of BC CIE is 1.1.12

You can find details about changes for this version in [doc/changelogs/CHANGELOG-1.1.12.md](doc/changelogs/CHANGELOG-1.1.12.md)


Copyright
=========

BC CIE is copyright 1999 - 2017 Brookins Consulting and 2006 - 2007 Mathias VITALIS

See: [doc/COPYRIGHT.md](doc/COPYRIGHT.md) for more information on the terms of the copyright and license


License
=======

BC CIE is licensed under the GNU General Public License.

The complete license agreement is included in the [LICENSE](LICENSE) file.

BC CIE is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

BC CIE is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

The GNU GPL gives you the right to use, modify and redistribute
BC CIE under certain conditions. The GNU GPL license
is distributed with the software, see the file [LICENSE](LICENSE).

It is also available at [http://www.gnu.org/licenses/gpl.txt](http://www.gnu.org/licenses/gpl.txt)

You should have received a copy of the GNU General Public License
along with BC CIE in [LICENSE](LICENSE). If not, see [http://www.gnu.org/licenses/](http://www.gnu.org/licenses/).

Using BC CIE under the terms of the GNU GPL is free (as in freedom).

For more information or questions please contact: license@brookinsconsulting.com


Requirements
============

The following requirements exists for using BC CIE extension:

* Exponential version:

Make sure you use Exponential 6.0 or higher (legacy 4.x kernels run the 1.0 and 1.1 releases up to 1.1.11).

* PHP version:

Make sure you have PHP 5.x or higher.


Installation
============

Details on installing BC CIE located in the file [doc/INSTALL.md](doc/INSTALL.md).


Usage
=====

Click the **CIE** tab of the admin (`/bccie/overview`). The start page shows the forms that collected information, the
last exports and the problems found; open a form to choose the fields, the date range, the type (CSV or SYLK), the
separator and the character set. A small export is written while you wait; a large one (more than `DirectExportLimit`
collections, 2000 by default) runs in the background and the file is offered for download when it is done.

The role needs `bccie/read`; removing collected information needs `bccie/remove` as well.

Spreadsheet safety: a text cell that starts with `=`, `+`, `-` or `@` gets a leading single quote, so a spreadsheet
does not run it as a formula. Text stays UTF-8; choose the `cp1252`, `utf8bom` or `utf16le` character set for older Excel versions.

Console commands
----------------

    ./console ext:bccie:export --object=ID [--format=csv|sylk] [--separator=semicolon] [--charset=utf8bom] [--from=YYYY-MM-DD] [--dry-run]
    ./console ext:bccie:export --cron [--format=sylk] [--dry-run]   # the scheduled export of cie.ini
    ./console ext:bccie:status
    ./console ext:bccie:purge --object=ID [--before=YYYY-MM-DD] [--dry-run] --yes

Every command shows its options with `--help`. The cronjob parts `exportcsv` and `exportsylk` (`php runcronjobs.php exportcsv`)
run the same code as `ext:bccie:export --cron`.


Troubleshooting
===============

* Read the FAQ

Some problems are more common than others. The most common ones are listed in the [doc/FAQ.md](doc/FAQ.md).

* Support

If you have find any problems not handled by this document or the FAQ you can contact Brookins Consulting through the support system: [http://brookinsconsulting.com/contact](http://brookinsconsulting.com/contact)
