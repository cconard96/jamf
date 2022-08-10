<?php

/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019-2021 by Curtis Conard
 * https://github.com/cconard96/jamf
 * -------------------------------------------------------------------------
 * LICENSE
 * This file is part of JAMF plugin for GLPI.
 * JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * JAMF plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace tests\units;

use AbstractDBTest;
use PluginJamfComputer;
use PluginJamfComputerTestSync;
use PluginJamfExtensionAttribute;
use PluginJamfImport;
use PluginJamfItem_ExtensionAttribute;

class PluginJamfComputerSync extends AbstractDBTest {

    public function testDiscover() {
        global $DB;

        PluginJamfComputerTestSync::discover();

        $iterator = $DB->request([
            'FROM' => PluginJamfImport::getTable()
        ]);
        $this->integer($iterator->count())->isEqualTo(5);
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
        $this->integer($iterator->count())->isEqualTo(4);

        // Make sure syncing again does not cause duplicates
        PluginJamfComputerTestSync::syncExtensionAttributeDefinitions();

        $iterator = $DB->request([
            'FROM' => PluginJamfExtensionAttribute::getTable(),
            'WHERE' => [
                'jamf_type' => 'Computer'
            ]
        ]);
        $this->integer($iterator->count())->isEqualTo(4);
    }

    public function testImport() {
        global $DB;

        PluginJamfComputerTestSync::syncExtensionAttributeDefinitions();

        PluginJamfComputerTestSync::import('Computer', 33, false);

        // Make sure the computer was created
        $iterator = $DB->request([
            'FROM' => \Computer::getTable(),
            'WHERE' => [
                'name' => 'Computer 2'
            ]
        ]);
        $this->integer($iterator->count())->isEqualTo(1);
        $item = $iterator->next();

        // Make sure the new computer is linked properly
        $link_iterator = $DB->request([
            //'SELECT' => ['id', 'udid'],
            'FROM' => PluginJamfComputer::getTable(),
            'WHERE' => [
                'itemtype' => 'Computer',
                'items_id' => $item['id']
            ]
        ]);
        $this->integer($link_iterator->count())->isEqualTo(1);
        $link = $link_iterator->next();
        $this->string($link['udid'])->isEqualTo('CA40DA58-60A3-11E4-90B8-12DF261F2C7E');

        $ext_attr_iterator = $DB->request([
            'FROM' => PluginJamfItem_ExtensionAttribute::getTable(),
            'WHERE' => [
                'itemtype' => PluginJamfComputer::class,
                'items_id' => $link['id']
            ]
        ]);
        $this->integer($ext_attr_iterator->count())->isEqualTo(4);
    }
}
