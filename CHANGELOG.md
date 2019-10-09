# Jamf Plugin for GLPI Changelog

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
