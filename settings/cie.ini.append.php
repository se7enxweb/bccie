<?php /* #?ini charset="utf8"?

[CieSettings]
# Cronjob debug output
Debug=disabled

# Log File
Log=var/log/cie.log

# Log Debug Output (Reserved but not used)
LogDebug=disabled

# Export Directory
Directory=var/export

# Export only records created in the last n of days
# ExportLimitedRange=disabled
ExportLimitedRange=disabled

# Number of days in the past to export. Must enable, 'ExportLimitedRange' to use
DateRangeToExport=7

# Remove the collected information after the scheduled export wrote it to a file
# (only what the file holds; nothing is removed when the file could not be written)
# RemoveExported=enabled
RemoveExported=disabled

# Collections to export, Array of IDs
Collection[]
# Collection[]=4242
# Collection[]=2442
# Collection[]=2424

# Collection attributes to exclude, Array of IDs
ExcludeAttributeID[]
# ExcludeAttributeID[]=001
# ExcludeAttributeID[]=002
# ExcludeAttributeID[]=003

# Csv Export Format
CsvFormat=csv

# Csv Export Separator
CsvSeparator=;
# CsvSeparator=|
# CsvSeparator=,
# CsvSeparator=:
# CsvSeparator=#

# Sylk Export Format
SylkFormat=sylk

# Sylk Export Separator
SylkSeparator=;

# Optional setting to export an empty
# string instead of "0" collected value
ExportZeroToEmptyString=disabled

# The user (ID) the cronjob parts and the console commands run as
CronUser=14

# An export of the admin that has more collections than this runs in the background,
# the file is offered for download when it is done
DirectExportLimit=2000

# Required setting to control the maximum export
# execution time limit
ExportExecutionTimeLimit=180

# Required setting to control the export
# date range calcuation. This setting
# disables an older questionable method
# used to calculate what date range of
# cie to export
ExportUsingDaysCalcualation=disabled

# Optional setting to disable display
# of 'Leave empty (note: field still gets created)'
# option in export module view
DisplayLeaveEmptyOption=enabled

# Required settings to partially control
# export file name and date format
ExportFileName=bccie_cie_export-
ExportFileNameDateFormat=Y-m-d_H-i-s

# Report / Export Format Control Handlers
ExportOutputFormatHandlers[]
ExportOutputFormatHandlers[utf8]=bccieExportFormatOutputHandlerUtf8
ExportOutputFormatHandlers[utf8bom]=bccieExportFormatOutputHandlerUtf8Bom
ExportOutputFormatHandlers[utf16le]=bccieExportFormatOutputHandlerUtf16Le
ExportOutputFormatHandlers[cp1252]=bccieExportFormatOutputHandlerCP1252

# Report / Export Format Control Handler Default
ExportOutputFormatHandlerDefault=utf8

*/ ?>