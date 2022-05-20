# JAMF Plugin for GLPI

[![CodeFactor](https://www.codefactor.io/repository/github/cconard96/jamf/badge/master)](https://www.codefactor.io/repository/github/cconard96/jamf/overview/master)

Syncs data from JAMF Pro to GLPI.

## Requirements
- GLPI >= 9.5.0
- PHP >= 7.2.0
- Jamf Pro >= 10.20.0

## Usage
- Server/sync configuration is found in Setup > Config under the JAMF Plugin tab.
- JSS User account used must have read access to mobile devices at least. Additional access may be required depending on what items are synced (software, etc).
- The two automatic actions "importJamf' and 'syncJamf" can only be run in CLI/Cron mode due to how long they can take.
- There is a rule engine used to filter out imported devices. The default import action is to allow the import.
- iPads and AppleTVs are imported as Computers, while iPhones are imported as Phones.

## Locale Support
- Contribute to French and Spanish localizations on [POEditor](https://poeditor.com/join/project/BepTgrM7ab).
- To request new languages, please open a GitHub issue.

## Version Support

Multiple versions of this plugin are supported at the same time to ease migration.
Only 2 major versions will be supported at the same time (Ex: v1 and v2).
When a new minor version is released, the previous minor version will have support ended after a month.
Only the latest bug fix version of each minor release will be supported.

Note: There was no official version support policy before 2022-05-19.
The following version table may be reduced based on the policy stated above.

| Plugin Version | GLPI Versions | Start of Support | End of Support |
|----------------|---------------|------------------|----------------|
| 1.2.1          | 9.4.X         | 2020-06-28       | 2022-05-19     |
| 2.2.0          | 9.5.X         | 2021-06-11       | In Support     |
| 3.0.0          | 10.0.X        | 2022-04-28       | In Support     |
