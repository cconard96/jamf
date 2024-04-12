<?php
global $CFG_GLPI;

define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));

if (file_exists("vendor/autoload.php")) {
    require_once "vendor/autoload.php";
}
include GLPI_ROOT . "/inc/includes.php";
//include_once GLPI_ROOT . '/tests/GLPITestCase.php';
//include_once GLPI_ROOT . '/tests/DbTestCase.php';
include_once "AbstractDBTest.php";

$plugin = new Plugin();
$plugin->checkPluginState('jamf');
$plugin->getFromDBbyDir('jamf');

if (!plugin_jamf_check_prerequisites()) {
    echo "\nPrerequisites are not met!";
    die(1);
}

if (!$plugin->isInstalled('jamf')) {
    $plugin->install($plugin->getID());
}
if (!$plugin->isActivated('jamf')) {
    $plugin->activate($plugin->getID());
}

include_once 'apitest.class.php';
include_once 'connectiontest.class.php';
include_once 'mobiletestsync.class.php';
include_once 'computertestsync.class.php';
