<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2024 by Curtis Conard
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

$plugin = new Plugin();
if (!$plugin->isActivated('jamf')) {
    Html::displayNotFoundError();
}

Session::checkRight("plugin_jamf_mobiledevice", CREATE);
Html::header('Jamf Plugin', '', 'tools', 'PluginJamfMenu', 'import');

global $DB, $CFG_GLPI;

$start = $_GET['start'] ?? 0;

$import = new PluginJamfImport();
$importcount = countElementsInTable(PluginJamfImport::getTable());
$pending = $DB->request([
    'FROM' => PluginJamfImport::getTable(),
    'START' => $start,
    'LIMIT' => $_SESSION['glpilist_limit']
]);

$linked_devices = $DB->request([
    'SELECT' => ['jamf_type', 'itemtype', 'items_id'],
    'FROM' => 'glpi_plugin_jamf_devices',
]);

$linked = [];
foreach ($linked_devices as $data) {
    $linked[$data['itemtype']][] = $data;
}

$ajax_url = Plugin::getWebDir('jamf') . '/ajax/merge.php';

Html::printPager($start, $importcount, Plugin::getWebDir('jamf') . '/front/merge.php', '');
echo "<form>";
echo "<div class='center'><table id='merge_table' class='table table-striped'>";
echo "<thead>";
echo '<tr>';
echo "<th>" . _x('field', 'Jamf ID', 'jamf') . "</th>";
echo "<th>" . _x('field', 'Name', 'jamf') . "</th>";
echo "<th>" . _x('field', 'GLPI Asset Type', 'jamf') . "</th>";
echo "<th>" . _x('field', 'Jamf Type', 'jamf') . "</th>";
echo "<th>" . _x('field', 'UDID', 'jamf') . "</th>";
echo "<th>" . _x('field', 'Discovery Date', 'jamf') . "</th>";
echo "<th>" . _x('field', 'GLPI Item', 'jamf') . "</th>";
echo '</tr>';
echo "</thead><tbody>";
foreach ($pending as $data) {
    $rowid = $data['jamf_items_id'];
    $itemtype = $data['type'];
    /** @var CommonDBTM $item */
    $item = new $itemtype();
    $jamftype = ('PluginJamf' . $data['jamf_type']);

    echo "<tr>";
    echo "<td>{$data['jamf_items_id']}</td>";
    $jamf_link = Html::link($data['name'], $jamftype::getJamfDeviceURL($data['jamf_items_id']));
    echo "<td>{$jamf_link}</td>";
    echo "<td>{$data['type']}</td>";
    echo "<td>{$data['jamf_type']}</td>";
    echo "<td>{$data['udid']}</td>";
    $date_discover = Html::convDateTime($data['date_discover']);
    echo "<td>{$date_discover}</td><td>";
    $guess = $item->find([
        'OR' => [
            'uuid' => $data['udid'],
            'name' => $data['name']
        ]
    ], [new QueryExpression("CASE WHEN uuid='" . $data['udid'] . "' THEN 0 ELSE 1 END")], 1);

    $params = [
        'used' => array_column($linked[$itemtype] ?? [], 'items_id')
    ];
    if (count($guess)) {
        $params['value'] = reset($guess)['id'];
    }
    $itemtype::dropdown($params);
    echo "</td></tr>";
}
echo "</tbody></table><br>";

echo "<button type='button' class='btn btn-primary' onclick='mergeDevices(); return false;'>" . _x('action', 'Merge', 'jamf') . "</button>";
echo "</div>";
$js = <<<JAVASCRIPT
      function mergeDevices() {
         const post_data = {};
         const table = $("#merge_table")[0];
         const row_count = table.rows.length;
         for (let i = 1; i < row_count; i++) {
            const row = table.rows[i];
            const jamf_id = row.cells[0].innerText;
            const itemtype = row.cells[2].innerText;
            const jamf_type = row.cells[3].innerText;
            const glpi_sel = $(row.cells[6]).find('select')[0];
            const glpi_id = glpi_sel.value;
            if (glpi_id && glpi_id > 0) {
               data = [];
               post_data[glpi_id] = {'itemtype': itemtype, 'jamf_id': jamf_id, 'jamf_type': jamf_type};
            }
         }
         $.ajax({
            type: "POST",
            url: "{$ajax_url}",
            data: {action: "merge", item_ids: post_data},
            contentType: 'application/json',
            beforeSend: () => {
               $('#loading-overlay').show();
            },
            complete: () => {
               location.reload();
            }
         });
      }
JAVASCRIPT;
Html::closeForm();
Html::printPager($start, $importcount, Plugin::getWebDir('jamf') . '/front/merge.php', '');
echo Html::scriptBlock($js);

// Create loading indicator
$position = "position: fixed; top: 0; left: 0; right: 0; bottom: 0;";
$style = "display: none; {$position} width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 2; cursor: progress;";
echo "<div id='loading-overlay' style='{$style}'><table class='tab_cadre' style='margin-top: 10%;'>";
echo "<thead><tr><th class='center'><h3>" . _x('action', 'Merging', 'jamf') . '...' . "</h3></th></tr></thead>";
echo "</table></div>";
Html::footer();
