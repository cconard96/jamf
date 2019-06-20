<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019 by Curtis Conard
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
 * JamfWebhook class.
 * This class parses incoming webhooks and triggers the appropriate actions in GLPI.
 */
class JamfWebhook extends CommonGLPI {

   private $valid = true;
   private $timestamp;
   private $id;
   private $name;
   private $event;
   private $data;

   public function __construct($payload) {
      try {
         $this->timestamp = $payload['webhook']['timestamp'];
         $this->id = $payload['webhook']['id'];
         $this->name = $payload['webhook']['name'];
         $this->event = $payload['webhook']['event'];
         $this->data = $payload['event'];
      } catch (Exception $e) {
         $this->valid = false;
      }
   }

   public function isValid() {
      return $this->valid;
   }

   public function executeActions() {
      global $DB;

      if (!$this->isValid()) {
         return false;
      }

      static $action_stacks = null;
      if ($action_stacks == null) {
         $iterator = $DB->request([
            'FROM'   => 'glpi_plugins_jamf_hookactions'
         ]);
         while ($data = $iterator->next()) {
            $action_stacks[$data['hookname']] = $data['actions'];
         }
      }

      
   }

   public static function getSupportedHooks() {
      return [
         'DeviceAddedToDEP' => __('Device added to DEP', 'jamf'),
         'JSSShutdown' => __('JSS shutdown', 'jamf'),
         'JSSStartup' => __('JSS startup', 'jamf'),
         'MobileDeviceCheckIn' => __('Mobile device check-in', 'jamf'),
         'MobileDeviceCommandCompleted' => __('Mobile device command completed', 'jamf'),
         'MobileDeviceEnrolled' => __('Mobile device enrolled', 'jamf'),
         'MobileDevicePushSent' => __('Mobile device push sent', 'jamf'),
         'MobileDeviceUnenrolled' => __('Mobile device unenrolled', 'jamf'),
         'PatchSoftwareTitleUpdated' => __('Software updated', 'jamf'),
         'PushSent' => __('Command sent', 'jamf'),
         'SmartGroupMobileDeviceMembershipChange' => __('Smart group membership change', 'jamf')
      ];
   }
}
