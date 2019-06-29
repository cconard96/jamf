# JAMF Plugin for GLPI
Syncs data from JAMF Pro to GLPI.
Only supports mobile devices for now.

## Requirements
- GLPI >= 9.4.0
- Jamf Pro >= 10.9.0

## Usage
- Server/sync configuration is found in Setup > Config under the JAMF Plugin tab.
- JSS User account used must have read access to mobile devces at least.
- The two automatic actions "importJamf' and 'syncJamf" can only be run in CLI/Cron mode due to how long they can take.
- There is a rule enginge used to filter out imported devices. The default import action is to allow the import.
- iPads and AppleTVs are imported as Computers, while iPhones are imported as Phones.
