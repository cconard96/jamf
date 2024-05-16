# JAMF Plugin for GLPI

Syncs data from JAMF Pro to GLPI.

## Requirements
- GLPI >= 10.0.0
- PHP >= 7.4.0
- The latest version of Jamf Pro

## Usage
- Server/sync configuration is found in Setup > Config under the JAMF Plugin tab.
- JSS User account used must have read access to mobile devices at least. Additional access may be required depending on what items are synced (software, etc).
- The two automatic actions "importJamf' and 'syncJamf" can only be run in CLI/Cron mode due to how long they can take.
- There is a rule engine used to filter out imported devices. The default import action is to allow the import.
- iPads and AppleTVs are imported as Computers, while iPhones can be imported as Phones or Computers.
