<?php

use Glpi\Tests\Log\TestHandler;
use Glpi\Toolbox\Sanitizer;
use Psr\Log\LogLevel;

/**
 * Mimics the DBTestCase class from GLPI but allows using the Atoum shim test class
 */
abstract class AbstractDBTest extends \CJDevStudios\AtoumShim\Atoum
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
            'autoimport' => 0,
            'sync_general' => 1,
            'sync_components' => 1,
            'sync_financial' => 1,
            'sync_os' => 1,
            'sync_software' => 1,
            'sync_user' => 1,
            'ipad_type' => 0,
            'iphone_type' => 0,
            'appletv_type' => 0,
            'default_manufacturer' => 0,
        ];
        foreach ($default_config as $name => $value) {
            $DB->updateOrInsert('glpi_configs', [
                'value' => $value,
                'context' => 'plugin:Jamf',
                'name' => $name,
            ], [
                'context' => 'plugin:Jamf',
                'name' => $name,
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

        $_SESSION['glpi_use_mode'] = Session::NORMAL_MODE;
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
        $this->boolean($auth->login($user_name, $user_pass, $noauto))->isEqualTo($expected);

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
        $res = Session::changeActiveEntities($entity_id, $subtree);
        $this->boolean($res)->isTrue();
    }

    /**
     * Generic method to test if an added object is corretly inserted
     *
     * @param  Object $object The object to test
     * @param  int    $id     The id of added object
     * @param  array  $input  the input used for add object (optionnal)
     *
     * @return void
     */
    protected function checkInput(CommonDBTM $object, $id = 0, $input = [])
    {
        $input = Sanitizer::dbUnescapeRecursive($input); // slashes in input should not be stored in DB

        $this->integer((int)$id)->isGreaterThan(0);
        $this->boolean($object->getFromDB($id))->isTrue();
        $this->variable($object->getField('id'))->isEqualTo($id);

        if (count($input)) {
            foreach ($input as $k => $v) {
                $this->variable($object->fields[$k])->isEqualTo(
                    $v,
                    "
                '$k' key current value '{$object->fields[$k]}' (" . gettype($object->fields[$k]) . ")
                is not equal to '$v' (" . gettype($v) . ")"
                );
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
            ]
        );

        $files_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(GLPI_ROOT . '/src'),
            RecursiveIteratorIterator::SELF_FIRST
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
        $item = new $itemtype();
        $input = Sanitizer::sanitize($input);
        $id = $item->add($input);
        $this->integer($id)->isGreaterThan(0);

        // Remove special fields
        $input = array_filter($input, function ($key) use ($skip_fields) {
            return !in_array($key, $skip_fields) && strpos($key, '_') !== 0;
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
        $item = new $itemtype();
        $input['id'] = $id;
        $input = Sanitizer::sanitize($input);
        $success = $item->update($input);
        $this->boolean($success)->isTrue();

        // Remove special fields
        $input = array_filter($input, function ($key) {
            return strpos($key, '_') !== 0;
        }, ARRAY_FILTER_USE_KEY);

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
