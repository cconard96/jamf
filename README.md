# JAMF Plugin for GLPI
Syncs data from JAMF Pro to GLPI.
Only supports mobile devices for now.

## Requirements
- GLPI >= 9.4.0
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

## Have a feature suggestion?
I have set up a [UserEcho](https://jamfforglpi.userecho.com/) page where you can make feature suggestions for future versions. Additionally, I welcome any contributions from the community weather they be bug fixes or new features. For new feature that aren't already included in a milestone, you should open an issue so that the new feature can be discussed.
