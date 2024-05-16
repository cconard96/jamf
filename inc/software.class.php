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

/**
 * PluginJamfSoftware class.
 * @since 1.0.0
 */
class PluginJamfSoftware extends CommonDBTM
{

    public static function getTypeName($nb = 0)
    {
        return Software::getTypeName($nb);
    }

    /**
     * Cleanup relations when an item is purged.
     * @param Software $item
     * @global DBmysql $DB
     */
    public static function plugin_jamf_purgeSoftware(Software $item)
    {
        global $DB;

        $software_classes = [PluginJamfComputerSoftware::class, PluginJamfMobileDeviceSoftware::class];

        foreach ($software_classes as $software_class) {
            $DB->delete($software_class::getTable(), [
                'softwares_id' => $item->getID(),
            ]);
        }
    }

    public static function getForGlpiItem(CommonDBTM $item): array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => [static::getTable() . '.*'],
            'FROM' => static::getTable(),
            'LEFT JOIN' => [
                Software::getTable() => [
                    'ON' => [
                        Software::getTable() => 'id',
                        static::getTable() => 'softwares_id'
                    ]
                ],
                SoftwareVersion::getTable() => [
                    'ON' => [
                        Software::getTable() => 'id',
                        SoftwareVersion::getTable() => 'softwares_id'
                    ]
                ],
                Item_SoftwareVersion::getTable() => [
                    'ON' => [
                        SoftwareVersion::getTable() => 'id',
                        Item_SoftwareVersion::getTable() => 'softwareversions_id'
                    ]
                ]
            ],
            'WHERE' => [
                'itemtype' => $item::getType(),
                'items_id' => $item->getID()
            ]
        ]);

        $result = [];
        foreach ($iterator as $data) {
            $result[] = $data;
        }
        return $result;
    }
}
