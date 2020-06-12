<?php

/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019-2020 by Curtis Conard
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

/**
 * JSS MDM Command class
 *
 * @since 1.1.0
 */
class PluginJamfMDMCommand {

   /**
    * @return array
    */
   public static function getAvailableCommands(): array
   {
      static $allcommands = null;

      if ($allcommands == null) {
         $allcommands = [
            'UpdateInventory' => [
               'name'   => __('Update Inventory', 'jamf'),
               'icon'   => 'fas fa-clipboard-list',
               'jss_right' => 'Send Inventory Requests to Mobile Devices',
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'   => true
               ]
            ],
            'BlankPush' => [
               'name'   => __('Send Blank Push', 'jamf'),
               'icon'   => 'fas fa-redo',
               'icon_color' => '#007af5',
               'jss_right' => 'Send Blank Pushes to Mobile Devices',
               'requirements' => [
                  'managed'   => true
               ]
            ],
            'DeviceName'   => [
               'name'   => __('Device name', 'jamf'),
               'icon'   => 'fas fa-signature',
               'jss_right' => 'Send Mobile Device Set Device Name Command',
               'params' => [
                  'device_name'  => [
                     'name'   => __('Device name'),
                     'type'   => 'string'
                  ]
               ],
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true
               ]
            ],
            'DeviceLocation'   => [
               'name'   => __('Update device location', 'jamf'),
               'icon'   => 'fas fa-search-location',
               'jss_right' => 'Send Mobile Device Lost Mode Command',
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true,
                  'lostmode'     => true
               ]
            ],
            'DeviceLock'   => [
               'name'      => __('Lock device', 'jamf'),
               'icon'      => 'fas fa-lock',
               'icon_color'   => '#dfac2a',
               'jss_right' => 'Send Mobile Device Remote Lock Command',
               'confirm'   => true,
               'params'    => [
                  'message'  => [
                     'name'      => __('Message', 'jamf'),
                     'type'      => 'string',
                     'min'       => 6,
                     'max'       => 6,
                     'required'  => false
                  ],
                  'phone_number'  => [
                     'name'      => __('Phone', 'jamf'),
                     'type'      => 'string',
                     'required'  => false
                  ]
               ],
               'requirements' => [
                  'devicetypes'         => [
                     'ipad'      => [
                        'version_min'  => '7.0'
                     ],
                     'iphone'    => [
                        'version_min'  => '7.0'
                     ]
                  ],
                  'managed'      => true,
                  'supervised'   => true
               ]
            ],
            'DisableLostMode'   => [
               'name'   => __('Disable lost mode', 'jamf'),
               'jss_right' => 'Send Mobile Device Lost Mode Command',
               'icon' => 'fas fa-map-marked-alt',
               'icon_color' => '#d60505',
               'params' => [
                  'device_name'  => [
                     'name'   => __('Device name', 'jamf'),
                     'type'   => 'string'
                  ]
               ],
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true,
                  'lostmode'     => true
               ]
            ],
            'EnableLostMode'   => [
               'name'      => __('Enable lost mode', 'jamf'),
               'jss_right' => 'Send Mobile Device Lost Mode Command',
               'icon' => 'fas fa-map-marked-alt',
               'icon_color' => '#009000',
               'confirm'   => true,
               'params'    => [
                  'message'  => [
                     'name'      => __('Message', 'jamf'),
                     'type'      => 'string',
                     'min'       => 6,
                     'max'       => 6,
                     'required'  => false
                  ],
                  'phone_number'  => [
                     'name'      => __('Phone', 'jamf'),
                     'type'      => 'string',
                     'required'  => false
                  ],
                  'lost_mode_footnote' => [
                     'name'      => __('Footnote', 'jamf'),
                     'type'      => 'string'
                  ]
               ],
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true,
                  'lostmode'     => false
               ]
            ],
            'EraseDevice'   => [
               'name'      => __('Erase device', 'jamf'),
               'icon'      => 'fas fa-eraser',
               'icon_color'   => '#e46b6b',
               'jss_right' => 'Send Mobile Device Remote Wipe Command',
               'confirm'   => true,
               'params'    => [
                  'preserve_data_plan'  => [
                     'name'   => __('Preserve data plan', 'jamf'),
                     'type'   => 'boolean'
                  ],
                  'disallow_proximity_setup'  => [
                     'name'   => __('Disallow proximity setup', 'jamf'),
                     'type'   => 'boolean'
                  ],
                  'clear_activation_lock'  => [
                     'name'   => __('Clear activation lock', 'jamf'),
                     'type'   => 'boolean'
                  ]
               ],
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true
               ]
            ],
            'PasscodeLockGracePeriod'   => [
               'name'   => __('Password lock grace period', 'jamf'),
               'jss_right' => 'Send Update Passcode Lock Grace Period Command',
               'icon' => 'fas fa-clock',
               'icon_color' => '#00669a',
               'params' => [
                  'passcode_lock_grace_period'  => [
                     'name'   => __('Grace period (seconds)'),
                     'type'   => 'number',
                     'min'    => 0,
                     'max'    => 14400
                  ]
               ],
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'supervised'   => true
               ]
            ],
            'PlayLostModeSound'   => [
               'name'   => __('Play lost mode sound', 'jamf'),
               'icon'   => 'fas fa-bell',
               'icon_color'   => '#e7cc88',
               'jss_right' => 'Send Mobile Device Lost Mode Command',
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true,
                  'lostmode'     => true
               ]
            ],
            'RestartDevice'   => [
               'name'         => __('Restart device', 'jamf'),
               'jss_right' => 'Send Mobile Device Restart Device Command',
               'icon'      => 'fas fa-sync',
               'icon_color' => 'green',
               'confirm'      => true,
               'requirements' => [
                  'devicetypes'  => [], // Empty array = No device type restrictions
                  'managed'      => true,
                  'supervised'   => true
               ]
            ],
            'ShutdownDevice'   => [
               'name'         => __('Shutdown device', 'jamf'),
               'icon'         => 'fas fa-power-off',
               'icon_color'   => '#d60505',
               'jss_right' => 'Send Mobile Device Shut Down Command',
               'confirm'      => true,
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true
               ]
            ],
            'ScheduleOSUpdate'   => [
               'name'         => __('Schedule OS update', 'jamf'),
               'icon'         => 'fas fa-arrow-up',
               'jss_right'    => 'Send Mobile Device Remote Command to Download and Install iOS Update',
               'confirm'      => true,
               'requirements' => [
                  'devicetypes'  => ['mobiledevice'],
                  'managed'      => true,
                  'supervised'   => true
               ],
               'params' => [
                  'install_action'  => [
                     'name'   => __('Install action', 'jamf'),
                     'type'   => 'dropdown',
                     'values' => [
                        '1'   => __('Download and prompt for install', 'jamf'),
                        '2'   => __('Download, install, and restart', 'jamf')
                     ]
                  ],
                  'product_version' => [
                     'name'   => __('Product version', 'jamf'),
                     'type'   => 'string',
                  ]
               ]
            ]
         ];
      }
      return $allcommands;
   }

   /**
    * Get content for the form shown when trying to send a command (not always applicable).
    * @param string $command The name of the command such as "UpdateInventory".
    * @return string|null HTML form or null if it is not applicable.
    */
   static function getFormForCommand($command)
   {
      if (isset(self::getAvailableCommands()[$command])) {
         $command_data = self::getAvailableCommands()[$command];
         if (!isset($command_data['params'])) {
            return null;
         }
         $out = "<form id='jamf-mdmcommand-send-$command'>";
         $out .= "<table>";
         foreach ($command_data['params'] as $name => $params) {
            $out .= "<tr>";
            $fieldtype = $params['type'] ?? 'text';
            $displayname = $params['name'] ?? $name;
            $out .= "<td><label for='$name'>$displayname</label></td>";
            $out .= "<td>";
            if ($fieldtype === 'number') {
               $min = $params['min'] ?? 0;
               $max = $params['max'] ?? PHP_INT_MAX;
               $out .= "<input title='$displayname' name='$name' type='number' min='$min' max='$max'/>";
            } else if ($fieldtype === 'boolean') {
               $out .= "<input title='$displayname' name='$name' type='checkbox'";
               if (isset($params['value']) && $params['value'] === true) {
                  $out .= " checked='checked'";
               }
               $out .= "/>";
            } else if ($fieldtype === 'dropdown') {
               $out .= Dropdown::showFromArray($name, $params['values'], [
                  'display'   => false
               ]);
            } else {
               $out .= "<input title='$displayname' name='$name' type='text'/>";
            }
            $out .= "</td>";
            $out .= "</tr>";
         }
         $out .= "</form>";
         echo $out;
      }
      return null;
   }

   public static function canSend($command)
   {
      $allcommands = self::getAvailableCommands();

      return PluginJamfUser_JSSAccount::haveJSSRight('jss_actions', $allcommands[$command]['jss_right']);
   }
}
