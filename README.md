# BC CIE: collected information export for Exponential

BC CIE exports the information that the forms of an Exponential site collect (contact forms, polls, sign-up and
feedback forms: any content object with information-collector fields) to **CSV** or **SYLK** files that open in
Excel, LibreOffice Calc or Numbers. It is for site administrators and editors who need the submissions of a form as
a spreadsheet, and for developers who want the same export from the console or from cron.

What you get:

- A **CIE** tab in the administration (admin4 draws it with the `arrow-down-up` icon) with a dashboard of every form
  that has collected information, the last exports and the problems the extension finds in its settings.
- An export page per form: choose the fields, the date range, CSV or SYLK, the separator and the character set.
- Small exports are written while you wait; large ones run in the background and are offered for download when done.
- An **Export** link per form on the kernel's collected-information overview (`infocollector/overview`) and an
  **Export Collection** button on its collection list (`infocollector/collectionlist`).
- Console commands and two cronjob parts for scheduled exports, with an optional removal of what was exported.
- Spreadsheet safety: a text cell that starts with `=`, `+`, `-` or `@` gets a leading single quote, so a
  spreadsheet does not run it as a formula.
- Translations for 19 locales.

## Requirements

- Exponential 6.0.15 or later. The module views, the cronjob parts and the console commands are built on the
  kernel's `Exponential\Runnable` classes that 6.0.15 introduced. (Exponential 4.x kernels ran the 1.0 and 1.1
  releases up to 1.1.11.)
- PHP 8.0 or later.
- The ezjscore extension, which ships with the kernel (the pages load their script and style through it).
- For exports in the background: `proc_open`, `setsid` and the PHP command line on the server. Without them, admin
  exports are limited to `DirectExportLimit` collections and the dashboard says so.

## Installation

### With Composer

From the root of your Exponential installation:

```bash
composer require se7enxweb/bccie
```

The legacy extension installer puts the files in `extension/bccie`.

### By hand

Download a release from https://github.com/se7enxweb/bccie/releases (or
`git clone https://github.com/se7enxweb/bccie.git extension/bccie`) and place it so that the files are in
`extension/bccie` of your installation. The folder must be called `bccie`.

### Then, either way

1. Activate the extension in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=bccie
   ```

2. Regenerate the autoloads and clear the caches:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   php bin/php/ezcache.php --clear-all
   ```

3. Give the roles that should export the policy `bccie/read` (see [Permissions](#permissions)). Administrators
   with all rights have it already.

The **CIE** tab now shows in the administration's top menu.

> If one of your siteaccesses keeps its own `menu.ini.append.php` with a `[TopAdminMenu] Tabs[]` list, add
> `Tabs[]=bccie_overview` to it.

## Usage

### In the administration

1. Click the **CIE** tab (`/bccie/overview`). The start page lists every form that has collected information, with
   the number of collections and the date of the last one, the last exports, and the problems found (for example a
   scheduled export without objects, or one that removes what it exported).
2. Click a form (`/bccie/export/<object id>`). Choose:
   - **Fields**: the collection ID, each field of the form, and optionally the creation and modification dates.
     A field whose datatype has no export handler exports its stored text.
   - **Date range**: a start and an end date; empty dates mean no limit.
   - **Export type**: CSV or SYLK (Excel).
   - **Character set**: `utf8` (default), `utf8bom` (Excel on Windows), `utf16le` (Excel on macOS) or `cp1252`.
   - **Separation char** for CSV: semicolon, comma, colon, pipe or hash.
3. Click **Do export.** An export of up to `DirectExportLimit` collections (2000 by default) is downloaded at once. A
   larger one runs in the background: the page follows its progress (`bccie/job`) and offers the file for download
   (`bccie/download`) when it is done. Only the user who started an export can see its state and download its file,
   and only while that user can still read the form.
4. A user with `bccie/remove` also sees **Remove all collected information**. It removes every collection of the
   form; the form itself stays. Export the data first.

You can also start from the kernel's **Collected information** pages: the overview has an **Export** link per form
and the collection list of a form an **Export Collection** button; both open the same export page.

### From the console

```bash
# Export one form (content object 123) as CSV, comma separated, UTF-8 with BOM, from a date on
./console ext:bccie:export --object=123 --separator=comma --charset=utf8bom --from=2026-01-01

# The same form as SYLK, only the last 30 days, into a folder of your choice
./console ext:bccie:export --object=123 --format=sylk --days=30 --output=var/export/monthly

# Count what the scheduled export of cie.ini would write, without writing anything
./console ext:bccie:export --cron --dry-run

# The dashboard as text: the forms with collected information, the last exports, the problems
./console ext:bccie:status

# Remove the collections of a form older than a date (nothing is removed without --yes)
./console ext:bccie:purge --object=123 --before=2025-01-01 --dry-run
./console ext:bccie:purge --object=123 --before=2025-01-01 --yes
```

`ext:bccie:export` also takes `--to=YYYY-MM-DD`, `--fields=contentobjectid,<attribute ids>` (`-1` adds an empty
column, `-2` skips one), `--creation-date`, `--modification-date` and `--remove-exported`. Every command lists its
options with `--help`. Without `./console`, run the scripts directly, for example
`php extension/bccie/bin/php/export.php --object=123`.

### Scheduled exports (cron)

The cronjob parts `exportcsv` and `exportsylk` export every object listed in `[CieSettings] Collection[]` to
`[CieSettings] Directory`, the same as `./console ext:bccie:export --cron`:

```bash
php runcronjobs.php exportcsv
php runcronjobs.php exportsylk
```

A crontab line that exports every night at 02:30:

```
30 2 * * * cd /path/to/exponential && php runcronjobs.php -q exportcsv
```

The shell scripts in `bin/` (`runcronjobpart.sh`, `runcronjobpartcsv.sh`, `runcronjobpartsylk.sh`) run a part by
hand from the installation's root and write its output to `var/log/cie.log`.

## Configuration

Copy the settings you want to change into `settings/override/cie.ini.append.php` (or a siteaccess's settings
folder). The defaults are in `extension/bccie/settings/cie.ini.append.php`.

```ini
[CieSettings]
# Folder the scheduled export and the console write to
Directory=var/export
# The objects (forms) the scheduled export writes, by content object ID
Collection[]
Collection[]=123
Collection[]=456
# Class attribute IDs the scheduled export leaves out
ExcludeAttributeID[]
# Only export the last DateRangeToExport days
ExportLimitedRange=disabled
DateRangeToExport=7
# Remove the collected information after the scheduled export wrote it to a file
RemoveExported=disabled
# Separators of the scheduled export
CsvSeparator=;
SylkSeparator=;
# Export an empty string instead of "0"
ExportZeroToEmptyString=disabled
# The user (ID) the cronjob parts and console commands run as
CronUser=14
# Larger admin exports run in the background
DirectExportLimit=2000
# Maximum execution time of an export, in seconds
ExportExecutionTimeLimit=180
# Use the older calculation of the date range
ExportUsingDaysCalcualation=disabled
# Offer "Leave empty (note: field still gets created)" on the export page
DisplayLeaveEmptyOption=enabled
# File name prefix and date format of the export files
ExportFileName=bccie_cie_export-
ExportFileNameDateFormat=Y-m-d_H-i-s
# Character set handlers, and the default
ExportOutputFormatHandlers[]
ExportOutputFormatHandlers[utf8]=bccieExportFormatOutputHandlerUtf8
ExportOutputFormatHandlers[utf8bom]=bccieExportFormatOutputHandlerUtf8Bom
ExportOutputFormatHandlers[utf16le]=bccieExportFormatOutputHandlerUtf16Le
ExportOutputFormatHandlers[cp1252]=bccieExportFormatOutputHandlerCP1252
ExportOutputFormatHandlerDefault=utf8
```

### Datatypes and export handlers

`export.ini` lists the datatypes that are exported (`[General] ExportableDatatypes[]`) and, per datatype, the
handler class that turns a value into a cell. The handlers are in `extension/bccie/classes/handlers/`. Supported out
of the box: `ezboolean`, `ezcountry`, `ezdate`, `ezdatetime`, `ezemail`, `ezenhancedobjectrelation`, `ezfloat`,
`ezidentifier`, `ezinteger`, `ezobjectrelation`, `ezoption`, `ezselection`, `ezstring`, `eztext`, `eztime`, `ezurl`,
`ezxmltext`, and the third-party `owenhancedselection`, `smileobjectrelationlist` and `ezbirthday`. For the object
relation datatypes, `OutputRelatedObjectNames=false` in their block exports the IDs instead of the names.

To export a datatype of your own, write a handler that extends `BaseHandler` (see `ezexamplecustomhandler.php`),
put it in `extension/bccie/classes/handlers/` or a folder below it:

```php
<?php
class myDatatypeHandler extends BaseHandler
{
    function exportAttribute( &$attribute, $separationChar )
    {
        return $this->escape( $attribute->content(), $separationChar );
    }
}
```

and register it in `settings/override/export.ini.append.php`:

```ini
[General]
ExportableDatatypes[]=mydatatype

[mydatatype]
HandlerFile=mydatatypehandler.php
HandlerClass=myDatatypeHandler
```

### Character set handlers

A character set is a class named in `ExportOutputFormatHandlers[]` that extends
`bccieExportFormatOutputHandler`; the four in `classes/exportFormatOutputHandlers/` are examples.

## Permissions

The module `bccie` has two functions (`modules/bccie/module.php`):

| Function | Allows |
|---|---|
| `bccie/read` | The CIE tab, the overview, the export pages, background exports and their downloads |
| `bccie/remove` | Removing the collected information of a form (with `bccie/read`) |

The kernel's **Collected information** pages need `infocollector/read`, as before. The views are not open to
anonymous users: `settings/site.ini.append.php` explains why its `PolicyOmitList[]` lines stay commented out.

## Upgrading

Replace the files in `extension/bccie` (or run `composer update se7enxweb/bccie`), regenerate the autoloads and
clear the caches as in [Installation](#installation). Keep your changes in `settings/override`, never in the
extension's own settings. The changes of each release are in [doc/changelogs](doc/changelogs).

## Known limits

- An export reads the stored data of each field; a datatype without a handler exports its stored text.
- A background job written by a version before 1.1.18 has no owner and can no longer be opened or downloaded; it is
  pruned as before.

## Troubleshooting

- **The CIE tab is missing**: check that the extension is active, that the autoloads were regenerated, that the
  user has `bccie/read`, and that a siteaccess `menu.ini.append.php` does not replace the tab list.
- **Accented characters look wrong in Excel**: export with `utf8bom` on Windows or `utf16le` on macOS.
- **The scheduled export writes nothing**: run `./console ext:bccie:export --cron --dry-run`; it says when
  `Collection[]` is empty. `./console ext:bccie:status` lists the problems the dashboard shows.
- More answers are in [doc/FAQ.md](doc/FAQ.md).

## Development and tests

The PHPUnit tests in `tests/` read the sources and need neither a kernel nor a database. From the installation's
root:

```bash
php vendor/bin/phpunit extension/bccie/tests/
```

Issues and pull requests are welcome at https://github.com/se7enxweb/bccie.

## License

GNU General Public License v2.0 or (at your option) any later version. See [LICENSE](LICENSE) and
[COPYRIGHT.md](COPYRIGHT.md).

## Credits and history

BC CIE was written in 2006 by Mathias Vitalis, inspired by the CSV export module of Gabriel Ambuehl, and was
developed and maintained by Brookins Consulting for eZ Publish, the system Exponential grew out of, until 2017.
7x maintains it for Exponential. See [doc/CREDITS.md](doc/CREDITS.md).
