<?php
global $CFG_GLPI;

define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));
define("GLPI_CONFIG_DIR", GLPI_ROOT . "/tests");

define('TU_USER', '_test_user');
define('TU_PASS', 'PhpUnit_4');

include GLPI_ROOT . "/inc/includes.php";
include  __DIR__ . '/../vendor/autoload.php';
include_once GLPI_ROOT . '/tests/GLPITestCase.php';
include_once GLPI_ROOT . '/tests/DbTestCase.php';

$plugin = new \Plugin();
$plugin->checkStates(true);
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
