# Jamf Plugin for GLPI Changelog

## [UNRELEASED]

### Fixes

- SQL error when merging the Jamf device linked to a GLPI asset
- SQL error when adding `Event`

## [3.1.1]

### Fixes

- Fixed missing API connection checks for some API calls.
- Fixed missing password config option during plugin installation.
- Fixed default PMV file path which is used during the plugin installation/v3.0 update.
- Fixed URL reference in the merge page's pager controls.
- Fixed select all checkbox on the import page.
- Fixed some blocking errors when merging devices.
- Fixed the computer type config option remaining after the plugin is uninstalled.
- Fixed unicity errors when discovering devices.

### Changed

- Updated included PMV (product model/OS version support) file to the latest version from Apple.
- Improved the permission error message when accessing the plugin's menu without access to any of the individual plugin pages.
- Migrated HTML code from `/front` files to Twig.
- Overhaul of the automatic tests to help ensure a stable release.
  - Migrated from Atoum to PHPUnit.
  - Added PHPStan level 1 checks.
  - Updated the mock data.
- The config option to ignore the SSL certificate verification is now disabled by default. If you have issues connecting to your Jamf instance over HTTPS, you probably don't have the root certificate installed/trusted for Curl. You should verify this is configured correctly (look up the specific instructions for your OS/distribution).
- The merge page now ignores deleted and template assets when determining merge candidates.

## [3.1.0]

- The plugin now uses the bearer token authentication with the Jamf API. The plugin still uses a standard username and password to get the token though, so there is no change from the point of view of GLPI admins.
- UUID is now fetched when the plugin discovers computers and this field will also be used when suggesting GLPI assets for merges.

## [3.0.2]

### Fixes

- SQL error when fetching the Jamf device linked to a GLPI asset
- Several issues related to the classic API connection
- Several datetime conversion fixes
- Mobile device sync error message
- Migration of config options from plugin versions older than 2.0.0
- Not able to save the config options in the default options section

## [3.0.1]

### Fixes

- Fix typo in the `model_identifier` DB column that caused model data to not import correctly for mobile devices
- Fix issue with the `uuid` DB column for imports not accepting NULL values that caused discovery issues with Computers
- Fixed issues with date and time timezone handling during syncing
- Fixed possible issue with date and time handling if the GLPI date format preference is not YYYY-MM-DD
- Fixed error that preventing showing the device merge form if the list of either Computers or Mobile Devices is empty
- Fixed potential issue with the sync automatic action if the sync interval configuration value is missing or invalid

## [3.0.0]

- The UI was redone a little bit to match the new UI added in GLPI 10 better
- The plugin now adds information to the GLPI status check system meaning you can now monitor this plugin's connection to your Jamf server
- You can specify a version when using the Update MDM command. The versions listed will be restricted to versions released by Apple and marked compatible with that device's model. A new automatic action was added to periodically update the "PMV" file provided by Apple which contains a list of firmware versions and the supported models for each.

## [2.2.0]

- Added "Clear pending imports" button on the import page
- Moved menu under Tools instead of Plugins
- Add Japanese locale

## [2.1.5]

### Fixes

- Fixed PHP warning regarding Session variables when using GLPI CLI tool
- Fixed Marketplace compatibility for some AJAX urls
- Added missing rights for "Jamf Computers" addition in the 2.1.2 migration. If this is a new installation, the new rights will be added automatically. If this is an upgrade, the rights will need added to Profiles manually.
- Fixed missing dashboard cards

## [2.1.4]

### Changes

- Improved dark mode colors on MDM Commands tab

### Fixes

- Fixed some table names in queries which was causing sync and discovery to fail.


## [2.1.3]

### Fixes

- Prevent orphaned software links after purging software in GLPI. Existing orphans will be cleaned as a part of the upgrade.
- Avoid double encryption on the JSS server password.
- Fix table name causing automatic sync issues.

## [2.1.2]

### Fixes

- Fix some random property access issues where a field is a common device field (Mostly during import/sync) instead of a specific device type such as Mobile Device or Computer.
- Fix User matching
- Fix/Implement Old Software installation cleanup

## [2.1.1]

### Fixes

- Some asset form fixes


## [2.1.0]

### Added

- Computer syncing support
- New plugin update/migration system to make it easier to manage changes between versions

### Changed
- JSS password will now be encrypted using Sodium (GLPI's new encryption after they moved away from a shared key). This password will be re-encrypted automatically.


## [2.0.0]

### Added

- Software sync for phones
- Default status configuration setting for newly imported devices
- Simcards, volumes, and lines syncing
- Dashboard cards for extension attributes, lost mode device count, managed device count, and supervised device count

### Changed

- Use client-side translations in JS files
- Dropped Phone OS shim. This is natively handled in GLPI 9.5.0.
- Moved Jamf plugin menu to Plugins menu from Tools
- Bumped GLPI minimum version to 9.5.0
- Bumped minimum PHP version to 7.2.0 to be in-line with GLPI
- Bumped minimum Jamf Pro version to 10.20.0. There are no known issues with 10.9.0-10.19.0 at this time but later features in this plugin may be incompatible.
- MDM command rights are now checked with the Jamf server on a per-command basis based on the user's linked JSS account

## [1.2.1]

- Fix issue with menu visiblity

## [1.2.0]

### Added

- Schedule iOS/iPad OS Update Command
- Sync network information

## [1.1.2]

### Added

- French localization thanks to Syn Cinatti

### Fixed

- Extension attribute definitions now sync when using the import and merge menus
- Fixed import rule tests

## [1.1.1]

### Fixed
- Fix table names used during fresh installs and uninstalls.
- Added all Jamf plugin rights to profiles with Config right by default.
- Fix dropTableOrDie error message on uninstall.
- Hide MDM Command buttons completely if no JSS account is linked to the current user.
- Fix orphaned record when removing a JSS account link from a user.
- Remove QueryExpression in ORDER clause. This is not supported in GLPI yet.

## [1.1.0]

### Added
- View in Jamf button to mobile device info box in Computers and Phones
- When merging items, a GLPI item will be preselected if the UDID/UUID matches (or if the name matches as a backup check)
- View and sync Extension Attributes
- Link GLPI users to JSS accounts for privilege checks. This is mandatory for certain actions/sections such as sending MDM commands.
- Issue commands from GLPI
- Sync software for computer GLPI items (software syncing for Phones will be available in 2.0.0)

## [1.0.1]

### Fixed
- Rights on item forms
- JSS item links

## [1.0.0]

### Added
- Initial release
