<?php
global $CFG_GLPI;

define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));
define("GLPI_CONFIG_DIR", GLPI_ROOT . "/tests");

if (file_exists("vendor/autoload.php")) {
    require_once "vendor/autoload.php";
}
require_once "vendor/cjdevstudios/atoum-phpunit-shim/src/bootstrap.php";
include GLPI_ROOT . "/inc/includes.php";
//include_once GLPI_ROOT . '/tests/GLPITestCase.php';
//include_once GLPI_ROOT . '/tests/DbTestCase.php';
include "tests/abstractdbtest.class.php";

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

include_once __DIR__ . '/apitestclassic.class.php';
include_once __DIR__ . '/mobiletestsync.class.php';
include_once __DIR__ . '/computertestsync.class.php';
