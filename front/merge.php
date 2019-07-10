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

include('../../../inc/includes.php');
Session::checkRight("plugin_jamf_mobiledevice", CREATE);
Html::header('Jamf Plugin', '', 'tools', 'PluginJamfMenu', 'import');

global $DB, $CFG_GLPI;

$start = isset($_GET['start']) ? $_GET['start'] : 0;

$mobiledevice = new PluginJamfMobileDevice();
$import = new PluginJamfImport();
$importcount = countElementsInTable(PluginJamfImport::getTable());
$pending = $DB->request([
   'FROM'   => PluginJamfImport::getTable(),
   'START'  => $start,
   'LIMIT'  => $_SESSION['glpilist_limit']
]);

$linked_computers = $mobiledevice->find([
   'itemtype'  => 'Computer'
]);
$linked_phones = $mobiledevice->find([
   'itemtype'  => 'Phone'
]);

$computer_ids = array_map(function($a) {
   return $a['items_id'];
}, $linked_computers);

$phone_ids = array_map(function($a) {
   return $a['items_id'];
}, $linked_phones);

Html::printPager($start, $importcount, "{$CFG_GLPI['root_doc']}/plugins/jamf/front/merge.php", '');
echo "<form>";
echo "<div class='center'><table id='merge_table' class='tab_cadre' style='width: 50%'>";
echo "<thead>";
echo "<th>".__('Jamf ID', 'jamf')."</th>";
echo "<th>".__('Name', 'jamf')."</th>";
echo "<th>".__('Type', 'jamf')."</th>";
echo "<th>".__('UDID', 'jamf')."</th>";
echo "<th>".__('Discovery Date', 'jamf')."</th>";
echo "<th>".__('GLPI Item', 'jamf')."</th>";
echo "</thead><tbody>";
while ($data = $pending->next()) {
   $rowid = $data['jamf_items_id'];
   $itemtype = $data['type'];

   echo "<tr>";
   echo "<td>{$data['jamf_items_id']}</td>";
   $jamf_link = Html::link($data['name'], PluginJamfMobileDevice::getJamfDeviceURL($data['udid']));
   echo "<td>{$jamf_link}</td>";
   echo "<td>{$data['type']}</td>";
   echo "<td>{$data['udid']}</td>";
   $date_discover = Html::convDateTime($data['date_discover']);
   echo "<td>{$date_discover}</td><td>";
   if ($itemtype === 'Computer') {
      $itemtype::dropdown([
         'used'   => array_values($computer_ids)
      ]);
   } else {
      $itemtype::dropdown([
         'used'   => array_values($phone_ids)
      ]);
   }
   echo "</td></tr>";
}
echo "</tbody></table><br>";

echo "<a class='vsubmit' onclick='mergeDevices(); return false;'>".__('Merge')."</a>";
echo "</div>";
$ajax_url = $CFG_GLPI['root_doc']."/plugins/jamf/ajax/merge.php";
$js = <<<JAVASCRIPT
      function mergeDevices() {
         var post_data = [];
         var table = $("#merge_table")[0];
         for (var i = 1, row; row = table.rows[i]; i++) {
            var jamf_id = row.cells[0].innerText;
            var itemtype = row.cells[2].innerText;
            var glpi_sel = row.cells[5].childNodes[0].childNodes[0];
            var glpi_id = glpi_sel.value;
            if (glpi_id && glpi_id > 0) {
               data = [];
               post_data[glpi_id] = {'itemtype': itemtype, 'jamf_id': jamf_id};
            }
         }
         $.ajax({
            type: "POST",
            url: "{$ajax_url}",
            data: {action: "merge", item_ids: post_data},
            contentType: 'application/json',
            success: function() {
               location.reload();
            }
         });
      }
JAVASCRIPT;
Html::closeForm();
Html::printPager($start, $importcount, "{$CFG_GLPI['root_doc']}/plugins/jamf/front/merge.php", '');
echo Html::scriptBlock($js);
Html::footer();