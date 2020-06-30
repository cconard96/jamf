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
 * JamfMobileDevice class. This represents a mobile device from Jamf.
 * This is mainly used to store extra fields that are not already in Computer or Phone classes.
 */
class PluginJamfMobileDevice extends PluginJamfAbstractDevice
{
   public static $rightname = 'plugin_jamf_mobiledevice';

   public static $jamftype_name = 'MobileDevice';

   public static function getTypeName($nb = 1)
   {
      return _n('Jamf mobile device', 'Jamf mobile devices', $nb, 'jamf');
   }

   public static function showForItem(array $params)
   {
      global $CFG_GLPI;

      /** @var CommonDBTM $item */
      $item = $params['item'];

      if (!self::canView() || (!($item::getType() === 'Computer') && !($item::getType() === 'Phone'))) {
         return false;
      }

      $getYesNo = static function($value) {
         return $value ? __('Yes') : __('No');
      };

      $out = '';
      if ($item::getType() === 'Phone') {
         $uuid = PluginJamfExtField::getValue('Phone', $item->getID(), 'uuid');
         $out .= '<tr><td>' . __('UUID', 'jamf') . '</td><td>';
         $out .= Html::input('_plugin_jamf_uuid', [
            'value' => $uuid
         ]);
         $out .= '</td></tr>';
      }
      $mobiledevice = new self();
      $match = $mobiledevice->find([
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()]);

      if (!count($match)) {
         echo $out;
         return false;
      }
      $match = reset($match);

      $out .= "<tr><th colspan='4'>".__('Jamf General Information', 'jamf'). '</th></tr>';
      $out .= '<tr><td>' .__('Import date', 'jamf'). '</td>';
      $out .= '<td>' .Html::convDateTime($match['import_date']). '</td>';
      $out .= '<td>' .__('Last sync', 'jamf'). '</td>';
      $out .= '<td>' .Html::convDateTime($match['sync_date']). '</td></tr>';

      $out .= '<tr><td>' .__('Jamf last inventory', 'jamf'). '</td>';
      $out .= '<td>'.Html::convDateTime($match['last_inventory']). '</td>';
      $out .= '<td>'.__('Jamf import date', 'jamf'). '</td>';
      $out .= '<td>' .Html::convDateTime($match['entry_date']). '</td></tr>';

      $out .= '<tr><td>'.__('Enrollment date', 'jamf').'</td>';
      $out .= '<td>'.Html::convDateTime($match['enroll_date']).'</td>';
      $out .= '<td>'.__('Shared device', 'jamf').'</td>';
      $out .= '<td>'.$match['shared']. '</td></tr>';

      $out .= '<tr><td>'.__('Supervised', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['supervised']).'</td>';
      $out .= '<td>'.__('Managed', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['managed']).'</td></tr>';

      $out .= '<td>'.__('Cloud backup enabled', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['cloud_backup_enabled']).'</td>';
      $out .= '<td>'.__('Activation locked', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['activation_lock_enabled']).'</td></tr>';

      $link = self::getJamfDeviceURL($match['jamf_items_id']);
      $view_msg = __('View in Jamf', 'jamf');
      $out .= "<tr><td colspan='4' class='center'>";
      $out .= "<a class='vsubmit' href='{$link}' target='_blank'>{$view_msg}</a>";

      if ($item->canUpdate()) {
         $onclick = "syncDevice(\"{$item::getType()}\", {$item->getID()}); return false;";
         $out .= "&nbsp;&nbsp;<a class='vsubmit' onclick='{$onclick}'>".__('Sync now', 'jamf'). '</a>';
         $ajax_url = $CFG_GLPI['root_doc']. '/plugins/jamf/ajax/sync.php';
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
         $out .= Html::scriptBlock($js);
      }
      $out .= '</td></tr>';

      $out .= "<tr><th colspan='4'>".__('Jamf Lost Mode Information', 'jamf'). '</th></tr>';
      $enabled = $match['lost_mode_enabled'];
      if (!$enabled || ($enabled != 'true')) {
         $out .= "<tr class='center'><td colspan='4'>".__('Lost mode is not enabled'). '</td></tr>';
      } else {
         $out .= '<tr><td>'.__('Enabled', 'jamf'). '</td>';
         $out .= '<td>'.$enabled. '</td>';
         $out .= '<td>'.__('Enforced', 'jamf'). '</td>';
         $out .= '<td>'.$getYesNo($match['lost_mode_enforced']). '</td></tr>';

         $out .= '<tr><td>'.__('Enable date', 'jamf'). '</td>';
         $out .= '<td>'.Html::convDateTime($match['lost_mode_enable_date']). '</td></tr>';

         $out .= '<tr><td>'.__('Message', 'jamf'). '</td>';
         $out .= '<td>'.$match['lost_mode_message']. '</td>';
         $out .= '<td>'.__('Phone', 'jamf'). '</td>';
         $out .= '<td>'.$match['lost_mode_phone']. '</td></tr>';

         $lat = $match['lost_location_latitude'];
         $long = $match['lost_location_longitude'];
         $out .= '<td>'.__('GPS'). '</td><td>';
         $out .= Html::link("$lat, $long", "https://www.google.com/maps/place/$lat,$long", [
            'display'   => false
         ]);
         $out .= '<tr><td>'.__('Altitude'). '</td>';
         $out .= '<td>'.$match['lost_location_altitude']. '</td>';
         $out .= '<tr><td>'.__('Speed', 'jamf'). '</td>';
         $out .= '<td>'.$match['lost_location_speed']. '</td>';
         $out .= '<td>'.__('Lost location date'). '</td>';
         $out .= '<td>'.Html::convDateTime($match['lost_location_date']). '</td></tr>';
      }

      echo $out;
   }

   public static function getJamfDeviceURL(int $jamf_id): string
   {
      $config = PluginJamfConfig::getConfig();
      return "{$config['jssserver']}/mobileDevices.html?id={$jamf_id}";
   }

   public function getExtensionAttributes()
   {
       global $DB;

       $ext_table = PluginJamfExtensionAttribute::getTable();
       $item_ext_table = PluginJamfItem_ExtensionAttribute::getTable();

       $iterator = $DB->request([
           'SELECT' => [
               'name', 'data_type', 'value'
           ],
           'FROM'   => $ext_table,
           'LEFT JOIN'  => [
               $item_ext_table => [
                   'FKEY'   => [
                       $ext_table       => 'id',
                       $item_ext_table  => 'glpi_plugin_jamf_extensionattributes_id'
                   ]
               ]
           ],
           'WHERE'  => [
               $item_ext_table.'.itemtype'   => self::getType(),
               'items_id'   => $this->getID()
           ]
       ]);

       $attributes = [];
       while ($data = $iterator->next()) {
           $attributes[] = $data;
       }
       return $attributes;
   }

   public function getMDMCommands()
   {
      $commandhistory = PluginJamfAPIClassic::getItems('mobiledevicehistory', [
         'id' => $this->fields['jamf_items_id'],
         'subset' => 'ManagementCommands'
      ]);
      return $commandhistory['management_commands'] ?? [
         'completed' => [],
         'pending'   => [],
         'failed'    => []
      ];
   }

   public function getSpecificType()
   {
      $item = $this->getGLPIItem();
      $modelclass = $this->fields['itemtype'].'Model';
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
         'widgettype'  => ['bigNumber'],
         'label'       => __('Jamf Lost Mobile Device Count'),
         'provider'    => 'PluginJamfMobileDevice::cardLostModeProvider'
      ];
      $cards['plugin_jamf_mobile_managed'] = [
         'widgettype'  => ['bigNumber'],
         'label'       => __('Jamf Managed Mobile Device Count'),
         'provider'    => 'PluginJamfMobileDevice::cardManagedProvider'
      ];
      $cards['plugin_jamf_mobile_supervised'] = [
         'widgettype'  => ['bigNumber'],
         'label'       => __('Jamf Supervised Mobile Device Count'),
         'provider'    => 'PluginJamfMobileDevice::cardSupervisedProvider'
      ];

      return $cards;
   }

   public static function cardLostModeProvider($params = [])
   {
      global $DB;

      $table = self::getTable();
      $iterator = $DB->request([
         'SELECT'   => [
            'COUNT' => 'lost_mode_enabled as cpt'
         ],
         'FROM'  => $table,
         'WHERE' => ['lost_mode_enabled' => 'Enabled'],
      ]);

      return [
         'label' => __('Jamf Lost Mobile Device Count'),
         'number' => $iterator->next()['cpt']
      ];
   }

   public static function cardManagedProvider($params = [])
   {
      global $DB;

      $table = self::getTable();
      $iterator = $DB->request([
         'SELECT'   => [
            'COUNT' => 'managed as cpt'
         ],
         'FROM'  => $table,
         'WHERE' => ['managed' => 1],
      ]);

      return [
         'label' => __('Jamf Managed Mobile Device Count'),
         'number' => $iterator->next()['cpt']
      ];
   }

   public static function cardSupervisedProvider($params = [])
   {
      global $DB;

      $table = self::getTable();
      $iterator = $DB->request([
         'SELECT'   => [
            'COUNT' => 'supervised as cpt'
         ],
         'FROM'  => $table,
         'WHERE' => ['supervised' => 1],
      ]);
      return [
         'label'  => __('Jamf Supervised Mobile Device Count'),
         'number' => $iterator->next()['cpt']
      ];
   }
}
