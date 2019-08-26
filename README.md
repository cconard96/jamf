# JAMF Plugin for GLPI
Syncs data from JAMF Pro to GLPI.
Only supports mobile devices for now.

## Requirements
- GLPI >= 9.4.0
- PHP >= 7.0.0 (Notice this differs from GLPI requirements)
- Jamf Pro >= 10.9.0

## Usage
- Server/sync configuration is found in Setup > Config under the JAMF Plugin tab.
- JSS User account used must have read access to mobile devces at least. Additional access may be required depending on what items are synced (software, etc).
- The two automatic actions "importJamf' and 'syncJamf" can only be run in CLI/Cron mode due to how long they can take.
- There is a rule enginge used to filter out imported devices. The default import action is to allow the import.
- iPads and AppleTVs are imported as Computers, while iPhones are imported as Phones.

## Versioning/Support
- For each new major version of GLPI supported, the major version number of this plugin gets incremented.
- For each feature release on the same major GLPI version, the minor version number of this plugin gets incremented.
- Each bugfix release will increment the patch version number.
- Bugfixes for the current and previous major versions of the plugin will be supported at least. Older versions may be supported depending on community interest and my own company's need. This plugin will not be backported to support versions older than 9.4.0.
- I will strive to have at least a beta release for the latest major version of GLPi within a week of the full release.
