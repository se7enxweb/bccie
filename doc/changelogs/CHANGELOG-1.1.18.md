Changes for 1.1.18
==================

Fixed

* Fixed: The status of an export in the background (bccie/job) and its file (bccie/download) are answered only for
  the user who started the export, and only while that user can still read the object whose collected information
  it exports. A job records the id of the user who starts it; a job written by an earlier version has no owner and
  can no longer be opened or downloaded (it is pruned as before).

Updated

* Updated: The status pages use the shared .a4-badge classes of the administration's components.css instead of a
  pill of their own.
* Updated: The Polish translation, with the contributor's wording where both had translated a message.
