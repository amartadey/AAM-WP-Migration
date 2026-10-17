=== Aam WP Migration ===
Contributors: amartadey
Tags: backup, migration, transfer, clone
Requires at least: 3.3
Tested up to: 6.7
Requires PHP: 5.3
Stable tag: 7.87
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Export or import a whole WordPress site as one .wpress file, with no upload size limit.

== Description ==
Aam WP Migration bundles your database, media, plugins and themes into a single .wpress file and restores it on another site.

* No import size limit on single-site installs (Unlimited features built in).
* Backups page with restore, retention settings, schedules and Reset Hub (single-site only).
* Remote Upload: pull a .wpress backup from a public URL straight into the backups folder.
* Updates come from GitHub releases, not wordpress.org.

On WordPress Multisite only the core export/import is available.

Source, issues and downloads: https://github.com/amartadey/AAM-WP-Migration

== Installation ==
1. Download aam-wp-migration.zip from https://github.com/amartadey/AAM-WP-Migration/releases/latest/download/aam-wp-migration.zip
2. Plugins > Add New > Upload Plugin, choose the zip, then Install and Activate.
3. Deactivate the original All-in-One WP Migration first if it is installed. Both cannot run at the same time.

== Changelog ==
= 7.87 =
**Fixed**

* Resolved a vulnerability where error logs were publicly accessible with a known name by appending random affixes to error log filenames, making them unguessable. Error logs are now automatically deleted daily and during plugin updates. Special thanks to villu164 for responsibly disclosing this issue.
* Resolved a vulnerability where an administrator user could inject arbitrary PHP code through specific inputs. This vulnerability requires administrator-level access to exploit, ensuring that unauthorized users cannot perform this action. Special thanks to Ryan Kozak for responsibly disclosing this issue.

= 7.86 =
**Fixed**

* Resolved an issue with PHP 8.4 compatibility and restoring backup files via WP-CLI

= 7.85 =
**Added**

* PHP 8.4 compatibility

= 7.84 =
**Added**

* New hooks during the export and import processes to allow for custom actions and integrations

= 7.83 =
**Fixed**

* Resolved an issue where downloading backup files was failing on WordPress Playground environments

= 7.82 =
**Added**

* SQLite support
* WordPress Playground support

= 7.81 =
**Added**

* Reset Hub Page: Introducing a new reset hub page, providing users with powerful reset tools for efficient site management. This feature allows for easier resets of WordPress environments, facilitating smoother development and testing workflows.

**Improved**

* Better W3TC Support
* PHP Compatibility Checks: Display a warning notification, when you move/restore your site to a different PHP version.

= 7.80 =
**Added**

* Support for update-services plugin
* Domain name conversion to dashes from dots in the backup name for improved hosting providers compatibility

**Improved**

* Better support for Multisite to Standalone and Standalone to Multisite exports and imports, streamlining the migration process

= 7.79 =
**Added**

* Support for WordPress v6.4

= 7.78 =
**Added**

* Implemented a new Schedules page within the plugin, displaying various advanced features exclusive to premium extensions

= 7.77 =
**Added**

* Tested the new version of WordPress 6.3

= 7.76 =
**Fixed**

* Removed the [beta] label from advanced settings
