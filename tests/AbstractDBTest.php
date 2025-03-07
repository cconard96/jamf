<?php

/**
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of JAMF plugin for GLPI.
 *
 * JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * JAMF plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024-2024 by Teclib'
 * @copyright Copyright (C) 2019-2024 by Curtis Conard
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/jamf
 * -------------------------------------------------------------------------
 */

use PHPUnit\Framework\TestCase;
use Glpi\Tests\Log\TestHandler;
use Glpi\Toolbox\Sanitizer;
use Psr\Log\LogLevel;

class AbstractDBTest extends TestCase
{
    private int $int;

    private string $str;

    /**
     * @var TestHandler
     */
    private static TestHandler $php_log_handler;

    /**
     * @var TestHandler
     */
    private static TestHandler $sql_log_handler;

    public static function setUpBeforeClass(): void
    {
        global $DB;
        $DB->beginTransaction();
        static::resetSession();

        // Ensure cache is clear
        global $GLPI_CACHE;
        $GLPI_CACHE->clear();

        // Init log handlers
        global $PHPLOGGER, $SQLLOGGER;
        /** @var Monolog\Logger $PHPLOGGER */
        static::$php_log_handler = new TestHandler(LogLevel::DEBUG);
        $PHPLOGGER->setHandlers([static::$php_log_handler]);
        static::$sql_log_handler = new TestHandler(LogLevel::DEBUG);
        $SQLLOGGER->setHandlers([static::$sql_log_handler]);

        $default_config = [
            'autoimport'           => 0,
            'sync_general'         => 1,
            'sync_components'      => 1,
            'sync_financial'       => 1,
            'sync_os'              => 1,
            'sync_software'        => 1,
            'sync_user'            => 1,
            'computer_type'        => 0,
            'ipad_type'            => 0,
            'iphone_type'          => 0,
            'appletv_type'         => 0,
            'default_manufacturer' => 0,
        ];
        foreach ($default_config as $name => $value) {
            $DB->updateOrInsert('glpi_configs', [
                'value'   => $value,
                'context' => 'plugin:Jamf',
                'name'    => $name,
            ], [
                'context' => 'plugin:Jamf',
                'name'    => $name,
            ]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        global $DB;
        $DB->rollback();
    }

    protected static function resetSession()
    {
        Session::destroy();
        Session::start();

        $_SESSION['glpi_use_mode']     = Session::NORMAL_MODE;
        $_SESSION['glpiactive_entity'] = 0;

        global $CFG_GLPI;
        foreach ($CFG_GLPI['user_pref_field'] as $field) {
            if (!isset($_SESSION["glpi$field"]) && isset($CFG_GLPI[$field])) {
                $_SESSION["glpi$field"] = $CFG_GLPI[$field];
            }
        }
    }

    /**
     * Get a unique random string
     */
    protected function getUniqueString()
    {
        if (is_null($this->str)) {
            return $this->str = uniqid('str', false);
        }

        return $this->str .= 'x';
    }

    /**
     * Get a unique random integer
     */
    protected function getUniqueInteger()
    {
        if (is_null($this->int)) {
            return $this->int = random_int(1000, 10000);
        }

        return $this->int++;
    }

    /**
     * Connect (using the test user per default)
     *
     * @param string $user_name User name (defaults to TU_USER)
     * @param string $user_pass user password (defaults to TU_PASS)
     * @param bool $noauto disable autologin (from CAS by example)
     * @param bool $expected bool result expected from login return
     *
     * @return \Auth
     */
    protected function login(
        string $user_name = TU_USER,
        string $user_pass = TU_PASS,
        bool $noauto = true,
        bool $expected = true
    ): \Auth {
        \Session::destroy();
        \Session::start();

        $auth = new Auth();
        $this->assertEquals($expected, $auth->login($user_name, $user_pass, $noauto));

        return $auth;
    }

    /**
     * Log out current user
     *
     * @return void
     */
    protected function logOut()
    {
        $ctime = $_SESSION['glpi_currenttime'];
        \Session::destroy();
        $_SESSION['glpi_currenttime'] = $ctime;
    }

    /**
     * change current entity
     *
     * @param int|string $entityname Name of the entity (or its id)
     * @param boolean $subtree   Recursive load
     *
     * @return void
     */
    protected function setEntity($entityname, $subtree)
    {
        $entity_id = is_int($entityname) ? $entityname : getItemByTypeName('Entity', $entityname, true);
        $res       = Session::changeActiveEntities($entity_id, $subtree);
        $this->assertTrue($res);
    }

    /**
     * Generic method to test if an added object is corretly inserted
     *
     * @param  CommonDBTM $object The object to test
     * @param  int    $id     The id of added object
     * @param  array  $input  the input used for add object (optionnal)
     *
     * @return void
     */
    protected function checkInput(CommonDBTM $object, $id = 0, $input = [])
    {
        $input = Sanitizer::dbUnescapeRecursive($input); // slashes in input should not be stored in DB

        $this->assertGreaterThan(0, $id, 'ID is not valid');
        $this->assertTrue($object->getFromDB($id), 'Object not found in DB');
        $this->assertEquals($id, $object->getID(), 'Object could not be loaded');

        if (count($input)) {
            foreach ($input as $k => $v) {
                $this->assertEquals($v, $object->fields[$k], "
                '$k' key current value '{$object->fields[$k]}' (" . gettype($object->fields[$k]) . ")
                is not equal to '$v' (" . gettype($v) . ')');
            }
        }
    }

    /**
     * Get all classes in folder inc/
     *
     * @param boolean $function Whether to look for a function
     * @param array   $excludes List of classes to exclude
     *
     * @return array
     */
    protected function getClasses($function = false, array $excludes = [])
    {
        // Add deprecated classes to excludes to prevent test failure
        $excludes = array_merge(
            $excludes,
            [
                'TicketFollowup', // Deprecated
                '/^RuleImportComputer.*/', // Deprecated
            ],
        );

        $files_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(GLPI_ROOT . '/src'),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $classes = [];
        foreach ($files_iterator as $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $classname = $fileInfo->getBasename('.php');

            $is_excluded = false;
            foreach ($excludes as $exclude) {
                if ($classname === $exclude || @preg_match($exclude, $classname) === 1) {
                    $is_excluded = true;
                    break;
                }
            }
            if ($is_excluded) {
                continue;
            }

            if (!class_exists($classname)) {
                continue;
            }
            $reflectionClass = new ReflectionClass($classname);
            if ($reflectionClass->isAbstract()) {
                continue;
            }

            if ($function) {
                if (method_exists($classname, $function)) {
                    $classes[] = $classname;
                }
            } else {
                $classes[] = $classname;
            }
        }

        return array_unique($classes);
    }

    /**
     * Create an item of the given class
     *
     * @param string $itemtype
     * @param array $input
     * @param array $skip_fields Fields that wont be checked after creation
     *
     * @return CommonDBTM
     */
    protected function createItem($itemtype, $input, $skip_fields = []): CommonDBTM
    {
        $item  = new $itemtype();
        $input = Sanitizer::sanitize($input);
        $id    = $item->add($input);
        $this->assertGreaterThan(0, $id, 'ID is not valid');

        // Remove special fields
        $input = array_filter($input, static function ($key) use ($skip_fields) {
            return !in_array($key, $skip_fields, true) && !str_starts_with($key, '_');
        }, ARRAY_FILTER_USE_KEY);

        $this->checkInput($item, $id, $input);

        return $item;
    }

    /**
     * Create an item of the given class
     *
     * @param string $itemtype
     * @param array $input
     */
    protected function updateItem($itemtype, $id, $input)
    {
        $item        = new $itemtype();
        $input['id'] = $id;
        $input       = Sanitizer::sanitize($input);
        $success     = $item->update($input);
        $this->assertTrue($success);

        // Remove special fields
        $input = array_filter($input, static fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);

        $this->checkInput($item, $id, $input);
    }

    /**
     * Create multiples items of the given class
     *
     * @param string $itemtype
     * @param array $inputs
     */
    protected function createItems($itemtype, $inputs)
    {
        foreach ($inputs as $input) {
            $this->createItem($itemtype, $input);
        }
    }
}
