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

use JsonSchema\Validator;

class PluginJamfInventoryAdapter extends \DbTestCase {

   public function testGetGlpiInventoryData() {
      $sample_dir = GLPI_ROOT . '/plugins/jamf/tools/samples/classic_api/';
      $sample1 = json_decode(file_get_contents($sample_dir . '/mobiledevices/id/28.json'), true)['mobile_device'];
      $sample1['_metadata'] = [
         'itemtype'  => 'Computer',
         'jamf_type' => 'MobileDevice'
      ];
      $adapter = new \PluginJamfInventoryAdapter($sample1);
      $glpi_inv_data = $adapter->getGlpiInventoryData();

      $this->array($glpi_inv_data)->hasKeys([
         'itemtype', 'action', 'deviceid', 'content'
      ]);

      $this->array($glpi_inv_data['content'])->hasKeys([
         'accesslog', 'bios', 'cpus', 'hardware'
      ]);

      $validator = new Validator();
      $glpi_inv_data = json_decode(json_encode($glpi_inv_data));
      $validator->validate($glpi_inv_data, [
         '$ref' => 'file://' . \Plugin::getPhpDir('jamf').'/tools/inventory.schema.json'
      ]);

      //Debug
      if (!$validator->isValid()) {
         echo "The given data does not match the inventory schema:\n";
         $errors = $validator->getErrors();
         foreach ($errors as $error) {
            echo $error['property'] . ': ' . $error['message'] . PHP_EOL;
         }
      }

      $this->boolean($validator->isValid())->isTrue();
   }
}
