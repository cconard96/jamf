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

namespace tests\units;

use Phone;
use PluginJamfAbstractDevice;
use PluginJamfExtensionAttribute;
use PluginJamfExtField;
use PluginJamfImport;
use PluginJamfItem_ExtensionAttribute;
use PluginJamfMobileDevice;
use PluginJamfMobileTestSync;
use PluginJamfSync;
use ReflectionClass;

class PluginJamfMobileSync extends \AbstractDBTest
{
    public function testDiscover()
    {
        global $DB;

        PluginJamfMobileTestSync::discover();

        $iterator = $DB->request([
            'FROM' => PluginJamfImport::getTable(),
        ]);

        $this->assertEquals(6, $iterator->count());
    }

    public function testSyncExtensionAttributeDefinitions()
    {
        global $DB;

        PluginJamfMobileTestSync::syncExtensionAttributeDefinitions();

        $iterator = $DB->request([
            'FROM'  => PluginJamfExtensionAttribute::getTable(),
            'WHERE' => [
                'jamf_type' => 'MobileDevice',
            ],
        ]);
        $this->assertEquals(1, $iterator->count());

        // Make sure syncing again does not cause duplicates
        PluginJamfMobileTestSync::syncExtensionAttributeDefinitions();

        $iterator = $DB->request([
            'FROM'  => PluginJamfExtensionAttribute::getTable(),
            'WHERE' => [
                'jamf_type' => 'MobileDevice',
            ],
        ]);
        $this->assertEquals(1, $iterator->count());
    }

    public function testImportAsComputer()
    {
        global $DB;

        // Force sync extension attribute definitions
        PluginJamfMobileTestSync::syncExtensionAttributeDefinitions();

        PluginJamfMobileTestSync::import('Computer', 5, false);

        // Make sure the computer was created
        $iterator = $DB->request([
            'FROM'  => \Computer::getTable(),
            'WHERE' => [
                'name' => 'Test iPad 3',
            ],
        ]);
        $this->assertEquals(1, $iterator->count());
        $item = $iterator->current();

        // Make sure the new computer is linked properly
        $link_iterator = $DB->request([
            'SELECT'    => [PluginJamfMobileDevice::getTable() . '.id', 'udid', 'managed', 'supervised', 'activation_lock_enabled', 'lost_mode_enabled'],
            'FROM'      => 'glpi_plugin_jamf_devices',
            'LEFT JOIN' => [
                PluginJamfMobileDevice::getTable() => [
                    'ON' => [
                        PluginJamfMobileDevice::getTable() => 'glpi_plugin_jamf_devices_id',
                        'glpi_plugin_jamf_devices'         => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'itemtype' => 'Computer',
                'items_id' => $item['id'],
            ],
        ]);
        $this->assertEquals(1, $link_iterator->count());
        $link = $link_iterator->current();
        $this->assertEquals('1aec6610a9401d2cc47cb55e1a2f7b500ab75864', $link['udid']);
        $this->assertEquals(1, $link['managed']);
        $this->assertEquals(1, $link['supervised']);
        $this->assertEquals(0, $link['activation_lock_enabled']);
        // No clue why 'Version' is mispelled, but that is how it is directly from Jamf Pro
        $this->assertEquals('Unsupported OS Versione', $link['lost_mode_enabled']);

        $ext_attr_iterator = $DB->request([
            'FROM'  => PluginJamfItem_ExtensionAttribute::getTable(),
            'WHERE' => [
                'itemtype' => PluginJamfMobileDevice::class,
                'items_id' => $link['id'],
            ],
        ]);
        $this->assertEquals(1, $ext_attr_iterator->count());
    }

    public function testImportAsPhone()
    {
        global $DB;
        PluginJamfMobileTestSync::import('Phone', 5, false);

        // Make sure the phone was created
        $iterator = $DB->request([
            'FROM'  => Phone::getTable(),
            'WHERE' => [
                'name' => 'Test iPad 3',
            ],
        ]);
        $this->assertEquals(1, $iterator->count());
        $item = $iterator->current();

        // Make sure the new phone is linked properly
        $link_iterator = $DB->request([
            'FROM'      => 'glpi_plugin_jamf_devices',
            'LEFT JOIN' => [
                PluginJamfMobileDevice::getTable() => [
                    'ON' => [
                        PluginJamfMobileDevice::getTable() => 'id',
                        'glpi_plugin_jamf_devices'         => 'jamf_items_id',
                    ],
                ],
            ],
            'WHERE' => [
                'itemtype' => 'Phone',
                'items_id' => $item['id'],
            ],
        ]);
        $this->assertEquals(1, $link_iterator->count());
        $link = $link_iterator->current();
        $this->assertEquals(5, $link['jamf_items_id']);

        $ext_iterator = $DB->request([
            'FROM'  => PluginJamfExtField::getTable(),
            'WHERE' => [
                'itemtype' => 'Phone',
                'items_id' => $item['id'],
                'name'     => 'uuid',
            ],
        ]);
        $this->assertEquals(1, $ext_iterator->count());
        $ext_field = $ext_iterator->current();
        $this->assertEquals('1aec6610a9401d2cc47cb55e1a2f7b500ab75864', $ext_field['value']);
    }

    public function deviceSyncEnginesProvider()
    {
        $engines = PluginJamfSync::getDeviceSyncEngines();
        $result  = [];
        foreach ($engines as $device_class => $sync_class) {
            $result[] = [
                $device_class,
                $sync_class,
            ];
        }

        return $result;
    }

    /**
     * @dataProvider deviceSyncEnginesProvider
     */
    public function testGetDeviceSyncEngineItem($device_class, $sync_class)
    {
        $rdc = new ReflectionClass($device_class);
        $this->assertSame($rdc->getParentClass()->getName(), PluginJamfAbstractDevice::class);
        $rsc = new ReflectionClass($sync_class);
        $this->assertTrue($rsc->isSubclassOf(PluginJamfSync::class));
    }
}
