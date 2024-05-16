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

use Glpi\Application\View\TemplateRenderer;

/**
 * PluginJamfComputer class. This represents a computer from Jamf.
 * This is mainly used to store extra fields that are not already in the GLPI Computer class.
 */
class PluginJamfComputer extends PluginJamfAbstractDevice
{
    static $rightname = 'plugin_jamf_computer';

    public static $jamftype_name = 'Computer';

    public static function getTypeName($nb = 1)
    {
        return _nx('itemtype', 'Jamf computer', 'Jamf computers', $nb, 'jamf');
    }

    /**
     * Display the extra information for Jamf computers on the main Computer tab.
     * @param array $params
     * @return void|bool Displays HTML only if a supported item is in the params parameter. If there is any issue, false is returned.
     * @throws Exception
     * @since 2.0.0 Renamed from showForComputerOrPhoneMain to showForItem
     * @since 1.0.0
     */
    public static function showForItem(array $params)
    {
        $item = $params['item'];

        if (!self::canView() || $item::getType() !== 'Computer') {
            return false;
        }

        $getYesNo = static function ($value) {
            return $value ? __('Yes') : __('No');
        };

        $jamf_item = static::getJamfItemForGLPIItem($item);
        if ($jamf_item === null) {
            return false;
        }
        $match = $jamf_item->fields;
        $match = array_merge($match, $jamf_item->getJamfDeviceData());

        $js = '';
        if ($item->canUpdate()) {
            $ajax_url = Plugin::getWebDir('jamf') . '/ajax/sync.php';
            $js = <<<JAVASCRIPT
               function syncDevice(itemtype, items_id) {
                  $.ajax({
                     type: "POST",
                     url: "{$ajax_url}",
                     data: {"itemtype": itemtype, "items_id": items_id},
                     contentType: 'application/json',
                     success: function() {
                        location.reload();
                     }
                  });
               }
JAVASCRIPT;
        }
        $info = [
            'general'   => [
                'caption' => _x('form_section', 'Jamf General Information', 'jamf'),
                'fields'    => [
                    'import_date' => [
                        'caption' => _x('field', 'Import date', 'jamf'),
                        'value' => Html::convDateTime($match['import_date']),
                    ],
                    'sync_date' => [
                        'caption' => _x('field', 'Last sync', 'jamf'),
                        'value' => Html::convDateTime($match['sync_date']),
                    ],
                    'last_inventory' => [
                        'caption' => _x('field', 'Jamf last inventory', 'jamf'),
                        'value' => PluginJamfToolbox::utcToLocal($match['last_inventory']),
                    ],
                    'entry_date' => [
                        'caption' => _x('field', 'Jamf import date', 'jamf'),
                        'value' => PluginJamfToolbox::utcToLocal($match['entry_date']),
                    ],
                    'enroll_date' => [
                        'caption' => _x('field', 'Enrollment date', 'jamf'),
                        'value' => PluginJamfToolbox::utcToLocal($match['enroll_date']),
                    ],
                    'supervised' => [
                        'caption' => _x('field', 'Supervised', 'jamf'),
                        'value' => $getYesNo($match['supervised']),
                    ],
                    'managed' => [
                        'caption' => _x('field', 'Managed', 'jamf'),
                        'value' => $getYesNo($match['managed']),
                    ],
                    'activation_lock_enabled' => [
                        'caption' => _x('field', 'Activation locked', 'jamf'),
                        'value' => $getYesNo($match['activation_lock_enabled']),
                    ],
                ],
                'buttons' => [
                    'view_in_jamf' => [
                        'caption' => _x('action', 'View in Jamf', 'jamf'),
                        'url' => self::getJamfDeviceURL($match['jamf_items_id'])
                    ],
                    'sync' => [
                        'caption' => _x('action', 'Sync now', 'jamf'),
                        'on_click' => "syncDevice(\"{$item::getType()}\", {$item->getID()}); return false;"
                    ],
                ],
                'extra_js'  => $js
            ]
        ];

        TemplateRenderer::getInstance()->display('@jamf/inventory_info.html.twig', [
            'info' => $info,
        ]);
    }

    /**
     * Get a direct link to the device on the Jamf server.
     * @param int $jamf_id The Jamf ID of the device.
     * @return string Jamf URL for the mobile device.
     */
    public static function getJamfDeviceUrl(int $jamf_id): string
    {
        $config = PluginJamfConfig::getConfig();
        return "{$config['jssserver']}/computers.html?id={$jamf_id}";
    }

    public function getMDMCommands()
    {
        return [
            'completed' => [],
            'pending' => [],
            'failed' => []
        ];
    }
}
