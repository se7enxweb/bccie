Changes for 1.1.15
==================

Fixed

* Fixed: The check-all box of the overview (bccie/overview) has an accessible name: it carried only a title, which
  an accessibility check reports (axe: label-title-only); it now has the same "Select all" as its aria-label. A
  test without a database (tests/bccieTemplateAccessibilityTest.php) checks it and the row boxes.
