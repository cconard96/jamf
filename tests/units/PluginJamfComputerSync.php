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

use PluginJamfComputer;
use PluginJamfComputerTestSync;
use PluginJamfExtensionAttribute;
use PluginJamfImport;
use PluginJamfItem_ExtensionAttribute;

class PluginJamfComputerSync extends \AbstractDBTest {

    public function testDiscover() {
        global $DB;

        PluginJamfComputerTestSync::discover();

        $iterator = $DB->request([
            'FROM' => PluginJamfImport::getTable()
        ]);
        $this->assertEquals(5, $iterator->count());
    }

    public function testSyncExtensionAttributeDefinitions() {
        global $DB;

        PluginJamfComputerTestSync::syncExtensionAttributeDefinitions();

        $iterator = $DB->request([
            'FROM' => PluginJamfExtensionAttribute::getTable(),
            'WHERE' => [
                'jamf_type' => 'Computer'
            ]
        ]);
        $this->assertEquals(1, $iterator->count());

        // Make sure syncing again does not cause duplicates
        PluginJamfComputerTestSync::syncExtensionAttributeDefinitions();

        $iterator = $DB->request([
            'FROM' => PluginJamfExtensionAttribute::getTable(),
            'WHERE' => [
                'jamf_type' => 'Computer'
            ]
        ]);
        $this->assertEquals(1, $iterator->count());
    }

    public function testImport() {
        global $DB;

        PluginJamfComputerTestSync::syncExtensionAttributeDefinitions();

        PluginJamfComputerTestSync::import('Computer', 1, false);

        // Make sure the computer was created
        $iterator = $DB->request([
            'FROM' => \Computer::getTable(),
            'WHERE' => [
                'name' => 'CConardMBA'
            ]
        ]);
        $this->assertEquals(1, $iterator->count());
        $item = $iterator->current();

        // Make sure the new computer is linked properly
        $link_iterator = $DB->request([
            'SELECT' => [PluginJamfComputer::getTable() . '.id', 'udid'],
            'FROM' => 'glpi_plugin_jamf_devices',
            'LEFT JOIN' => [
                PluginJamfComputer::getTable() => [
                    'ON' => [
                        PluginJamfComputer::getTable() => 'glpi_plugin_jamf_devices_id',
                        'glpi_plugin_jamf_devices' => 'id'
                    ]
                ]
            ],
            'WHERE' => [
                'itemtype' => 'Computer',
                'items_id' => $item['id']
            ]
        ]);
        $this->assertEquals(1, $link_iterator->count());
        $link = $link_iterator->current();
        $this->assertEquals('55900BDC-347C-58B1-D249-F32244B11D30', $link['udid']);

        $ext_attr_iterator = $DB->request([
            'FROM' => PluginJamfItem_ExtensionAttribute::getTable(),
            'WHERE' => [
                'itemtype' => 'PluginJamfComputer',
                'items_id' => $link['id']
            ]
        ]);
        $this->assertEquals(1, $ext_attr_iterator->count());
    }
}
