Changes for 1.1.19
==================

Updated

* Updated: The CIE tab of the administration shows an icon: `Icon=arrow-down-up` in the tab's block
  `[Topmenu_bccie_overview]` of menu.ini. admin4 draws it before the tab's name; other designs ignore it.
* Updated: README.md is a complete guide: what the extension does, the requirements (Exponential 6.0.15 or later,
  PHP 8.0 or later), the installation with Composer and by hand, the admin pages, the console commands and the
  cronjob parts with examples, every setting of cie.ini, custom export handlers, the policies, upgrading and
  troubleshooting. doc/INSTALL.md points to it.
* Updated: composer.json requires PHP 8.0, names the issue tracker and the source, lists 7x as maintainer and sets
  installer-name. extension.xml carries a `<metadata>` block (name, version, copyright, license, project page) that
  agrees with ezinfo.php.
