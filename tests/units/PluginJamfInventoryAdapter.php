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

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

class PluginJamfInventoryAdapter extends \DbTestCase {

   private function getMobileDeviceAdapter() {
      $sample_dir = GLPI_ROOT . '/plugins/jamf/tools/samples/classic_api/';
      $sample1 = json_decode(file_get_contents($sample_dir . '/mobiledevices/id/28.json'), true)['mobile_device'];
      $sample1['_metadata'] = [
         'itemtype'  => 'Computer',
         'jamf_type' => 'MobileDevice'
      ];
      return new \PluginJamfInventoryAdapter($sample1);
   }

   private function getComputerAdapter() {
      $sample_dir = GLPI_ROOT . '/plugins/jamf/tools/samples/classic_api/';
      $sample1 = json_decode(file_get_contents($sample_dir . '/computers/id/33.json'), true)['computer'];
      $sample1['_metadata'] = [
         'itemtype'  => 'Computer',
         'jamf_type' => 'Computer'
      ];
      return new \PluginJamfInventoryAdapter($sample1);
   }

   public function testGetMobileDeviceBiosData() {
      $adapter = $this->getMobileDeviceAdapter();
      $bios = $adapter->getBiosData();

      $this->array($bios)->hasKeys(['mmanufacturer', 'smanufacturer', 'msn', 'ssn', 'mmodel']);
      // Check array doesn't have computer-only keys
      $this->array($bios)->notHasKeys(['bmanufacturer', 'bversion', 'biosserial']);

      $this->string($bios['mmanufacturer'])->isIdenticalTo($adapter->getManufacturer());
      $this->string($bios['smanufacturer'])->isIdenticalTo($adapter->getManufacturer());
      $this->string($bios['msn'])->isIdenticalTo('CA44C89860A3');
      $this->string($bios['ssn'])->isIdenticalTo('CA44C89860A3');
      $this->string($bios['mmodel'])->isIdenticalTo('iPad mini (CDMA)');
   }

   public function testGetComputerBiosData() {
      $adapter = $this->getComputerAdapter();
      $bios = $adapter->getBiosData();

      $this->array($bios)->hasKeys(['mmanufacturer', 'smanufacturer', 'msn', 'ssn', 'mmodel', 'bmanufacturer', 'bversion', 'biosserial']);

      $this->string($bios['mmanufacturer'])->isIdenticalTo($adapter->getManufacturer());
      $this->string($bios['smanufacturer'])->isIdenticalTo($adapter->getManufacturer());
      $this->string($bios['bmanufacturer'])->isIdenticalTo($adapter->getManufacturer());
      $this->string($bios['msn'])->isIdenticalTo('CA40DA6C60A3');
      $this->string($bios['ssn'])->isIdenticalTo('CA40DA6C60A3');
      $this->string($bios['biosserial'])->isIdenticalTo('CA40DA6C60A3');
      $this->string($bios['mmodel'])->isIdenticalTo('13-inch MacBook Pro (2011)');
      $this->string($bios['bversion'])->isIdenticalTo('MBP81.0047.B27');
   }

   public function testGetMobileDeviceCpusData() {
      $adapter = $this->getMobileDeviceAdapter();
      $cpus = $adapter->getCpusData();
      $this->variable($cpus)->isNull();
   }
   public function testGetComputerCpusData() {
      $adapter = $this->getComputerAdapter();
      $cpus = $adapter->getCpusData();

      $this->array($cpus)->hasKey('items');
      $this->array($cpus['items'])->hasSize(1);
      $this->boolean(isset($cpus['items'][0]))->isTrue();
   }

   public function testGetGlpiInventoryData() {
      $adapter = $this->getMobileDeviceAdapter();
      $glpi_inv_data = $adapter->getGlpiInventoryData();

      $this->array($glpi_inv_data)->hasKeys([
         'itemtype', 'action', 'deviceid', 'content'
      ]);

      $this->array($glpi_inv_data['content'])->hasKeys([
         'accesslog', 'bios', 'hardware'
      ]);

      $validator = new Validator();
      $glpi_inv_data = json_decode(json_encode($glpi_inv_data));
      $validator->validate($glpi_inv_data, [
         '$ref' => 'file://' . \Plugin::getPhpDir('jamf').'/tools/inventory.schema.json'
      ], Constraint::CHECK_MODE_COERCE_TYPES | Constraint::CHECK_MODE_TYPE_CAST);

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
