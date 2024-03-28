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

$start = isset($_GET['start']) ? $_GET['start'] : 0;

$import = new PluginJamfImport();
$importcount = countElementsInTable(PluginJamfImport::getTable());
$pending = $DB->request([
    'FROM' => PluginJamfImport::getTable(),
    'START' => $start,
    'LIMIT' => $_SESSION['glpilist_limit']
]);

$check_all = Html::getCheckAllAsCheckbox('import_table');

Html::printPager($start, $importcount, PluginJamfImport::getSearchURL(), '');
echo "<form>";
echo "<div class='center'><table id='import_table' class='table table-striped'>";
echo "<thead>";
echo '<tr><th colspan="7"><h1>' . _x('form', 'Discovered devices', 'jamf') . '</h1></th></tr>';
echo '<tr>';
echo "<th>{$check_all}</th>";
echo "<th>" . _x('field', 'Jamf ID', 'jamf') . "</th>";
echo "<th>" . _x('field', 'Jamf Type', 'jamf') . "</th>";
echo "<th>" . _x('field', 'Name', 'jamf') . "</th>";
echo "<th>" . _x('field', 'Type', 'jamf') . "</th>";
echo "<th>" . _x('field', 'UDID', 'jamf') . "</th>";
echo "<th>" . _x('field', 'Discovery Date', 'jamf') . "</th>";
echo '</tr>';
echo "</thead><tbody>";
foreach ($pending as $data) {
    $rowid = $data['jamf_items_id'];
    echo "<tr>";
    $import_checkbox = Html::input("import_{$data['id']}", [
        'type' => 'checkbox',
        'display' => false,
        'class' => 'form-check-input massive_action_checkbox'
    ]);
    echo "<td>{$import_checkbox}</td>";
    echo "<td>{$data['jamf_items_id']}</td>";
    echo "<td>{$data['jamf_type']}</td>";
    /** @var class-string<PluginJamfAbstractDevice> $plugin_itemtype */
    $plugin_itemtype = 'PluginJamf' . $data['jamf_type'];
    $jamf_link = Html::link($data['name'], $plugin_itemtype::getJamfDeviceURL($data['jamf_items_id']));
    echo "<td>{$jamf_link}</td>";
    echo "<td>{$data['type']}</td>";
    $udid = !empty($data['udid']) ? $data['udid'] : '<i>(' . _x('message', 'Not collected during discovery', 'jamf') . ')</i>';
    echo "<td>{$udid}</td>";
    $date_discover = Html::convDateTime($data['date_discover']);
    echo "<td>{$date_discover}</td>";
    echo "</tr>";
}
echo "</tbody></table><br>";

echo "<button class='btn btn-primary' onclick='importDevices(); return false;'>" . _x('action', 'Import', 'jamf') . "</button>";
echo "&nbsp;<button class='btn btn-primary' onclick='discoverNow(); return false;'>" . _x('action', 'Discover now', 'jamf') . "</button>";
echo "&nbsp;<button class='btn btn-primary' onclick='clearPendingImports(); return false;'>" . _x('action', 'Clear pending imports', 'jamf') . "</button>";
echo "</div>";
$ajax_root = Plugin::getWebDir('jamf') . '/ajax/';
$import_msg = _x('action', 'Importing', 'jamf') . '...';
$discover_msg = _x('action', 'Discovering', 'jamf') . '...';
$clear_msg = _x('action', 'Clearing', 'jamf') . '...';

$js = <<<JAVASCRIPT
      function importDevices() {
         var import_ids = $(':checkbox:checked').filter(':not([name^="_checkall"])').map(function(){
            return this.name.replace("import","").substring(1).split('_');
         }).toArray();
         $.ajax({
            type: "POST",
            url: "{$ajax_root}import.php",
            data: {
               action: "import",
               import_ids: import_ids
            },
            contentType: 'application/json',
            beforeSend: function() {
              showLoading("{$import_msg}");
            },
            complete: function() {
               location.reload();
            }
         });
      }
      function discoverNow() {
         $.ajax({
            type: "POST",
            url: "{$ajax_root}cron.php",
            data: {crontask: "importJamf"},
            contentType: 'application/json',
            beforeSend: function() {
               showLoading("{$discover_msg}");
            },
            complete: function() {
               location.reload();
            }
         });
      }
      function clearPendingImports() {
         $.ajax({
            type: "POST",
            url: "{$ajax_root}import.php",
            data: {
               action: "clear"
            },
            contentType: 'application/json',
            beforeSend: function() {
              showLoading("{$clear_msg}");
            },
            complete: function() {
               location.reload();
            }
         });
      }
      function showLoading(msg) {
         $('#loading-overlay h3').text(msg);
         $('#loading-overlay').show();
      }
JAVASCRIPT;
Html::closeForm();
Html::printPager($start, $importcount, PluginJamfImport::getSearchURL(), '');
echo Html::scriptBlock($js);

// Create loading indicator
$position = "position: fixed; top: 0; left: 0; right: 0; bottom: 0;";
$style = "display: none; {$position} width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 2; cursor: progress;";
echo "<div id='loading-overlay' style='{$style}'><table class='tab_cadre' style='margin-top: 10%;'>";
echo "<thead><tr><th class='center'><h3>" . $import_msg . "</h3></th></tr></thead>";
echo "</table></div>";
Html::footer();
