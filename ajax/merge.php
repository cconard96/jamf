<?php

/**
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of JAMF plugin for GLPI.
 *
 * JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * JAMF plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024-2024 by Teclib'
 * @copyright Copyright (C) 2019-2024 by Curtis Conard
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/jamf
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('jamf')) {
    Html::displayNotFoundError();
}

Html::header_nocache();

Session::checkLoginUser();

global $DB;

// Get AJAX input and load it into $_REQUEST
$input = file_get_contents('php://input');
parse_str($input, $_REQUEST);

// An action must be specified
if (!isset($_REQUEST['action'])) {
    throw new RuntimeException('Required argument missing!');
}
if ($_REQUEST['action'] === 'merge') {
    // Trigger extension attribute definition sync
    PluginJamfMobileSync::syncExtensionAttributeDefinitions();
    PluginJamfComputerSync::syncExtensionAttributeDefinitions();
    $supported_glpi_types = [
        'Computer' => PluginJamfComputerSync::getSupportedGlpiItemtypes(),
        'MobileDevice' => PluginJamfMobileSync::getSupportedGlpiItemtypes()
    ];
    // An array of item IDs is required
    if (isset($_REQUEST['item_ids']) && is_array($_REQUEST['item_ids'])) {
        $failures  = 0;
        $successes = 0;
        foreach ($_REQUEST['item_ids'] as $glpi_id => $data) {
            if (!isset($data['jamf_id'], $data['itemtype'])) {
                continue;
            }
            $jamf_id  = $data['jamf_id'];
            $itemtype = $data['itemtype'];

            if (!in_array($itemtype, $supported_glpi_types[$data['jamf_type']])) {
                // Invalid itemtype for a mobile device
                throw new RuntimeException('Invalid itemtype!');
            }
            $item = new $itemtype();
            /** @var PluginJamfAbstractDevice $plugin_itemtype */
            $plugin_itemtype = 'PluginJamf' . $data['jamf_type'];
            /** @var PluginJamfDeviceSync $plugin_sync_itemtype */
            $plugin_sync_itemtype = 'PluginJamf' . $data['jamf_type'] . 'Sync';
            if ($data['jamf_type'] === 'MobileDevice') {
                $plugin_sync_itemtype = 'PluginJamfMobileSync';
            }

            if ($data['jamf_type'] === 'MobileDevice') {
                $jamf_item = PluginJamfAPI::getMobileDeviceByID($jamf_id, true);
            } else {
                $jamf_item = PluginJamfAPI::getComputerByID($jamf_id, true);
            }

            if ($jamf_item === null) {
                // API error or device no longer exists in Jamf
                throw new RuntimeException('Jamf API error or item no longer exists!');
            }

            // Run import rules on merged devices manually since this doesn't go through the usual import process
            $rules = new PluginJamfRuleImportCollection();

            //WTF is this, Jamf?
            $os_details = $jamf_item['ios'] ?? $jamf_item['tvos'] ?? '';
            $ruleinput  = [
                'name'           => $jamf_item['name'] ?? $jamf_item['general']['name'],
                'itemtype'       => $itemtype,
                'last_inventory' => $jamf_item['lastInventoryUpdateTimestamp'] ?? $jamf_item['general']['lastContactTime'],
                'managed'        => $jamf_item['managed']                      ?? $os_details['managed'],
                'supervised'     => $jamf_item['supervised']                   ?? $os_details['supervised'],
            ];
            $ruleinput = $rules->processAllRules($ruleinput, $ruleinput, ['recursive' => true]);
            $import    = isset($ruleinput['_import']) ? $ruleinput['_import'] : 'NS';

            if (isset($ruleinput['_import']) && !$ruleinput['_import']) {
                // Dropped by rules
                continue;
            }

            $DB->beginTransaction();
            try {
                // Partial import
                $r = $DB->insert('glpi_plugin_jamf_devices', [
                    'itemtype'      => $itemtype,
                    'items_id'      => $glpi_id,
                    'udid'          => $jamf_item['udid'],
                    'jamf_type'     => $data['jamf_type'],
                    'jamf_items_id' => $data['jamf_id'],
                ]);
                if ($r === false) {
                    throw new \RuntimeException('Failed to import the device data!');
                }
                // Link
                $plugin_item     = new $plugin_itemtype();
                $plugin_items_id = $plugin_item->add([
                    'glpi_plugin_jamf_devices_id' => $DB->insertId(),
                ]);

                // Sync
                $sync_result = $plugin_sync_itemtype::sync($itemtype, $glpi_id, false);

                // Update merged device and then delete the pending import
                if ($sync_result) {
                    $DB->update('glpi_plugin_jamf_devices', [
                        'import_date' => $_SESSION['glpi_currenttime'],
                    ], [
                        'itemtype' => $itemtype,
                        'items_id' => $glpi_id,
                    ]);
                    $DB->delete(PluginJamfImport::getTable(), [
                        'jamf_type'     => $data['jamf_type'],
                        'jamf_items_id' => $jamf_id,
                    ]);
                    $DB->commit();
                    $successes++;
                } else {
                    $failures++;
                    $DB->rollBack();
                }
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                $failures++;
                $DB->rollBack();
            }
        }
        if ($failures) {
            Session::addMessageAfterRedirect(sprintf(__('An error occurred while merging %d devices!', 'jamf'), $failures), false, ERROR);
        }
    } else {
        throw new RuntimeException('Required argument missing!');
    }
} else {
    throw new RuntimeException('Invalid action!');
}
