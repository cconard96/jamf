# Jamf Plugin for GLPI Changelog

## [2.2.0]

- Added "Clear pending imports" button on the import page
- Moved menu under Tools instead of Plugins

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
