Changes for 1.1.16
==================

Updated

* Updated: The export pages and the information collection lists run without inline code (a Content Security Policy
  without 'unsafe-inline'): the confirmations, the check-all boxes and the progress of a background export are in
  design/standard/javascript/bccie_admin.js, and the "Invert selection" icons of the admin design's infocollector
  overview and collection list call ezjs_toggleCheckboxes() from data attributes instead of onclick attributes.
