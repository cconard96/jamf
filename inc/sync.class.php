<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2020 by Curtis Conard
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

/**
 * PluginJamfSync class.
 * This class handles actively syncing data from JAMF to GLPI.
 */
abstract class PluginJamfSync
{

   /**
    * The sync task completed successfully.
    */
   public const STATUS_OK = 0;

   /**
    * The sync task was skipped because the required data was not supplied (rights error on JSS), the config denies the sync, or another reason.
    */
   public const STATUS_SKIPPED = 1;

   /**
    * An error occurred during the sync task.
    */
   public const STATUS_ERROR = 2;

   /**
    * An attempt was made to run async task without the necessary resources being ready.
    * For example, adding an extension attribute to a mobile device on the first sync before it is created.
    * In this case, the task will get deferred until the sync is finalized. At that stage, the task is retired a final time.
    */
   public const STATUS_DEFERRED = 3;

   /**
    * @var bool If true, it indicates an instance of the sync engine was created without the intention of using it for syncing.
    *              Any task that attempts to run, will be set to an error state.
    */
   protected $dummySync = false;

   protected $config = [];

   protected $item_changes = [];

   protected $extitem_changes = [];

   protected $jamfdevice_changes = [];

   protected $data = [];

   /** @var CommonDBTM */
   protected $item = null;

   protected $jamfitemtype = null;

   /** @var PluginJamfMobileDevice */
   protected $jamfdevice = null;

   protected $status = [];

   /**
    * @var DBmysql
    * @todo Replace with DI hopefully next GLPI version
    */
   protected $db;

   /**
    * PluginJamfSync constructor.
    * @param CommonDBTM|null $item
    * @param array $data
    */
   public function __construct(CommonDBTM $item = null, array $data = [])
   {
      /** @global DBmysql */
      global $DB;

      $this->db = $DB;
      if ($item === null) {
         $this->dummySync = true;
         return;
      }
      $this->config = PluginJamfConfig::getConfig();
      $this->item = $item;
      $this->data = $data;
      $jamfitem = new $this->jamfitemtype();
      $jamf_match = $jamfitem->find([
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()], [], 1);
      if (count($jamf_match)) {
         $jamf_id = reset($jamf_match)['id'];
         $jamfitem->getFromDB($jamf_id);
         $this->jamfdevice = $jamfitem;
      }
   }

   /**
    * Apply all pending changes and retry deferred tasks.
    * @since 1.1.0
    * @return array STATUS_OK if the sync was successful, STATUS_ERROR otherwise.
    */
   protected function finalizeSync()
   {
      if ($this->dummySync) {
         return $this->status;
      }
      $this->jamfdevice_changes['sync_date'] = $_SESSION['glpi_currenttime'];
      $this->item->update([
            'id' => $this->item->getID()
         ] + $this->item_changes);
      foreach ($this->extitem_changes as $key => $value) {
         PluginJamfExtField::setValue($this->item::getType(), $this->item->getID(), $key, $value);
      }
      $this->db->updateOrInsert($this->jamfitemtype::getTable(), $this->jamfdevice_changes, [
         'itemtype' => $this->item::getType(),
         'items_id' => $this->item->getID()
      ]);

      if ($this->jamfdevice === null) {
         $jamf_item = new $this->jamfitemtype();
         $jamf_match = $jamf_item->find([
            'itemtype' => $this->item::getType(),
            'items_id' => $this->item->getID()], [], 1);
         if (count($jamf_match)) {
            $jamf_item->getFromDB(reset($jamf_match)['id']);
            $this->jamfdevice = $jamf_item;
         }
      }

      // Re-run all deferred tasks
      $deferred = array_keys($this->status, self::STATUS_DEFERRED);
      foreach ($deferred as $task) {
         if (method_exists($this, $task)) {
            $this->$task();
         } else {
            $this->status[$task] = self::STATUS_ERROR;
         }
      }
      return $this->status;
   }

   protected function createOrGetItem($itemtype, $criteria, $params) {
       $item = new $itemtype();
       $item_matches = $item->find($criteria);
       if (!count($item_matches)) {
           $items_id = $item->add($params);
           $item->getFromDB($items_id);
       } else {
           $item->getFromDB(reset($item_matches)['id']);
       }
       return $item;
   }

   abstract public static function discover(): bool;

   abstract public static function import(string $itemtype, int $jamf_items_id): bool;

   abstract public static function syncAll(): int;

   abstract public static function sync(string $itemtype, int $items_id, bool $use_transaction = true): bool;

   abstract public static function syncExtensionAttributeDefinitions();

   abstract public static function getSupportedGlpiItemtypes(): array;

   public static function isSupportedGlpiItemtype(string $itemtype): bool
   {
      return in_array($itemtype, self::getSupportedGlpiItemtypes(), true);
   }

   public static function cronSyncJamf(CronTask $task)
   {
      $volume = static::syncAll();
      if ($volume === -1) {
         return 0;
      }
      $task->addVolume($volume);

      return 1;
   }

   public static function cronImportJamf(CronTask $task)
   {
      $volume = static::discover();
      if ($volume === -1) {
         return 0;
      }
      $task->addVolume($volume);

      return 1;
   }
}
