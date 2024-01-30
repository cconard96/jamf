<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2021 by Curtis Conard
 https://github.com/cconard96/jamf
 -------------------------------------------------------------------------
 LICENSE
 This file is part of JAMF plugin for GLPI.
 JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 JAMF plugin for GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;

/**
 * JamfMobileDevice class. This represents a mobile device from Jamf.
 * This is mainly used to store extra fields that are not already in Computer or Phone classes.
 */
class PluginJamfMobileDevice extends PluginJamfAbstractDevice
{

    static public $itemtype = 'itemtype';
    static public $items_id = 'items_id';
    public static $rightname = 'plugin_jamf_mobiledevice';

    public static function getTypeName($nb = 1)
    {
        return _nx('itemtype', 'Jamf mobile device', 'Jamf mobile devices', $nb, 'jamf');
    }

    /**
     * Display the extra information for mobile devices on the main Computer or Phone tab.
     * @param array $params
     * @return void|bool
     */
    public static function showForComputerOrPhoneMain($params)
    {

    }

    /**
     * Get a direct link to the mobile device on the Jamf server.
     * @param int $jamf_id The Jamf ID of the device.
     * @return string Jamf URL for the mobile device.
     */
    public static function getJamfDeviceURL(int $jamf_id): string
    {
        $config = PluginJamfConfig::getConfig();
        return "{$config['jssserver']}/mobileDevices.html?id={$jamf_id}";
    }

    /**
     * Cleanup relations when an item is purged.
     * @param CommonDBTM $item
     * @global CommonDBTM $DB
     */
    private static function purgeItemCommon(CommonDBTM $item)
    {
        global $DB;

        $DB->delete(self::getTable(), [
            'itemtype' => $item::getType(),
            'items_id' => $item->getID()
        ]);
    }

    /**
     * Cleanup relations when a Computer is purged.
     * @param Computer $item
     */
    public static function plugin_jamf_purgeComputer(Computer $item)
    {
        self::purgeItemCommon($item);
    }

    /**
     * Cleanup relations when a Phone is purged.
     * @param Phone $item
     * @global DBmysql $DB
     */
    public static function plugin_jamf_purgePhone(Phone $item)
    {
        global $DB;

        self::purgeItemCommon($item);
        $DB->delete(Item_OperatingSystem::getTable(), [
            'itemtype' => $item::getType(),
            'items_id' => $item->getID()
        ]);
    }


//   /**
//    * @param CommonDBTM $item
//    * @return PluginJamfMobileDevice
//    */
//   public static function getJamfItemForGLPIItem(CommonDBTM $item)
//   {
//       $mobiledevice = new self();
//       $matches = $mobiledevice->find([
//           'itemtype'   => $item::getType(),
//           'items_id'   => $item->getID()
//       ], [], 1);
//       if (count($matches)) {
//           $id = reset($matches)['id'];
//           $mobiledevice->getFromDB($id);
//           return $mobiledevice;
//       }
//       return null;
//   }

    public static function preUpdatePhone($item)
    {
        if (isset($item->input['_plugin_jamf_uuid'])) {
            PluginJamfExtField::setValue($item::getType(), $item->getID(), 'uuid', $item->input['_plugin_jamf_uuid']);
        }
    }

    public function getGLPIItem()
    {
        $device_data = $this->getJamfDeviceData();
        $itemtype = $device_data['itemtype'];
        $item = new $itemtype();
        $item->getFromDB($device_data['items_id']);
        return $item;
    }

    public function getMDMCommands()
    {
        $device_data = $this->getJamfDeviceData();
        $commandhistory = PluginJamfAPI::getItemsClassic('mobiledevicehistory', [
            'id' => $device_data['jamf_items_id'],
            'subset' => 'ManagementCommands'
        ]);
        return $commandhistory['management_commands'] ?? [
                'completed' => [],
                'pending' => [],
                'failed' => []
            ];
    }

    public function getSpecificType()
    {
        $item = $this->getGLPIItem();
        $modelclass = $this->getJamfDeviceData()['itemtype'] . 'Model';
        if ($item->fields[getForeignKeyFieldForItemType($modelclass)] > 0) {
            /** @var CommonDropdown $model */
            $model = new $modelclass();
            $model->getFromDB($item->fields[getForeignKeyFieldForItemType($modelclass)]);
            $modelname = $model->fields['name'];
            switch ($modelname) {
                case strpos($modelname, 'iPad') !== false:
                    return 'ipad';
                case strpos($modelname, 'iPhone') !== false:
                    return 'iphone';
                case strpos($modelname, 'Apple TV') !== false:
                    return 'appletv';
                default:
                    return null;
            }
        }
        return null;
    }

    public static function dashboardCards()
    {
        $cards = [];

        $cards['plugin_jamf_mobile_lost'] = [
            'widgettype' => ['bigNumber'],
            'label' => _x('dashboard', 'Jamf Lost Mobile Device Count', 'jamf'),
            'provider' => 'PluginJamfMobileDevice::cardLostModeProvider'
        ];
        $cards['plugin_jamf_mobile_managed'] = [
            'widgettype' => ['bigNumber'],
            'label' => _x('dashboard', 'Jamf Managed Mobile Device Count', 'jamf'),
            'provider' => 'PluginJamfMobileDevice::cardManagedProvider'
        ];
        $cards['plugin_jamf_mobile_supervised'] = [
            'widgettype' => ['bigNumber'],
            'label' => _x('dashboard', 'Jamf Supervised Mobile Device Count', 'jamf'),
            'provider' => 'PluginJamfMobileDevice::cardSupervisedProvider'
        ];

        return $cards;
    }

    public static function cardLostModeProvider($params = [])
    {
        global $DB;

        $table = self::getTable();
        $iterator = $DB->request([
            'SELECT' => [
                'COUNT' => 'lost_mode_enabled as cpt'
            ],
            'FROM' => $table,
            'WHERE' => ['lost_mode_enabled' => 'Enabled'],
        ]);

        return [
            'label' => _x('dashboard', 'Jamf Lost Mobile Device Count', 'jamf'),
            'number' => $iterator->current()['cpt']
        ];
    }

    public static function cardManagedProvider($params = [])
    {
        global $DB;

        $table = self::getTable();
        $iterator = $DB->request([
            'SELECT' => [
                'COUNT' => 'managed as cpt'
            ],
            'FROM' => $table,
            'WHERE' => ['managed' => 1],
        ]);

        return [
            'label' => _x('dashboard', 'Jamf Managed Mobile Device Count', 'jamf'),
            'number' => $iterator->current()['cpt']
        ];
    }

    public static function cardSupervisedProvider($params = [])
    {
        global $DB;

        $table = self::getTable();
        $iterator = $DB->request([
            'SELECT' => [
                'COUNT' => 'supervised as cpt'
            ],
            'FROM' => $table,
            'WHERE' => ['supervised' => 1],
        ]);
        return [
            'label' => _x('dashboard', 'Jamf Supervised Mobile Device Count', 'jamf'),
            'number' => $iterator->current()['cpt']
        ];
    }

    public static function showForItem(array $params)
    {
        /** @var CommonDBTM $item */
        $item = $params['item'];

        if (!self::canView() || (!($item::getType() === 'Computer') && !($item::getType() === 'Phone'))) {
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
                    'shared' => [
                        'caption' => _x('field', 'Shared device', 'jamf'),
                        'value' => $match['shared'],
                    ],
                    'supervised' => [
                        'caption' => _x('field', 'Supervised', 'jamf'),
                        'value' => $getYesNo($match['supervised']),
                    ],
                    'managed' => [
                        'caption' => _x('field', 'Managed', 'jamf'),
                        'value' => $getYesNo($match['managed']),
                    ],
                    'cloud_backup_enabled' => [
                        'caption' => _x('field', 'Cloud backup enabled', 'jamf'),
                        'value' => $getYesNo($match['cloud_backup_enabled']),
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
            ],
            'lost_mode' => [
                'caption' => _x('form_section', 'Jamf Lost Mode Information', 'jamf'),
                'fields'    => []
            ]
        ];

        $lost_mode_enabled = $match['lost_mode_enabled'];
        if (!$lost_mode_enabled || ($lost_mode_enabled != 'true')) {
            $info['lost_mode']['fields'] = [
                'lost_mode_enabled' => [
                    'caption' => _x('field', 'Enabled', 'jamf'),
                    'value' => _x('field', 'Lost mode is not enabled', 'jamf'),
                ]
            ];
        } else {
            $lat = $match['lost_location_latitude'];
            $long = $match['lost_location_longitude'];
            $info['lost_mode']['fields'] = [
                'lost_mode_enabled' => [
                    'caption' => _x('field', 'Enabled', 'jamf'),
                    'value' => $getYesNo($lost_mode_enabled),
                ],
                'lost_mode_enforced' => [
                    'caption' => _x('field', 'Enforced', 'jamf'),
                    'value' => $getYesNo($match['lost_mode_enforced']),
                ],
                'lost_mode_enable_date' => [
                    'caption' => _x('field', 'Enable date', 'jamf'),
                    'value' => Html::convDateTime($match['lost_mode_enable_date']),
                ],
                'lost_mode_message' => [
                    'caption' => _x('field', 'Message', 'jamf'),
                    'value' => $match['lost_mode_message'],
                ],
                'lost_mode_phone' => [
                    'caption' => _x('field', 'Phone', 'jamf'),
                    'value' => $match['lost_mode_phone'],
                ],
                'lost_location' => [
                    'caption' => _x('field', 'GPS', 'jamf'),
                    'value' => Html::link("$lat, $long", "https://www.google.com/maps/place/$lat,$long", [
                        'display' => false
                    ])
                ],
                'lost_location_altitude' => [
                    'caption' => _x('field', 'Altitude', 'jamf'),
                    'value' => $match['lost_location_altitude'],
                ],
                'lost_location_speed' => [
                    'caption' => _x('field', 'Speed', 'jamf'),
                    'value' => $match['lost_location_speed'],
                ],
                'lost_location_date' => [
                    'caption' => _x('field', 'Lost location date'),
                    'value' => Html::convDateTime($match['lost_location_date']),
                ],
            ];
        }

        TemplateRenderer::getInstance()->display('@jamf/inventory_info.html.twig', [
            'info' => $info,
        ]);
    }
}
