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

use Glpi\Application\View\TemplateRenderer;

/**
 * User_JSSAccount class. Links GLPI users to a JSS account for the purpose of managing permissions.
 * A user may be linked to any JSS account, which is why it is recommended to only give JSS Account Links rights to
 * admins that already have full access in Jamf. Otherwise, they could escalate their own permissions.
 *
 * @since 1.1.0
 */
class PluginJamfUser_JSSAccount extends CommonDBChild
{
    static public $itemtype = 'User';
    static public $items_id = 'users_id';
    static public $rightname = 'plugin_jamf_jssaccount';

    public const LINK = 256;

    public static function getTypeName($nb = 0)
    {
        return _nx('itemtype', 'JSS Account Link', 'JSS Account Links', $nb, 'jamf');
    }

    public function prepareInputForUpdate($input)
    {
        global $DB;
        if ($input['jssaccounts_id'] === 0) {
            $DB->delete(self::getTable(), ['id' => $this->fields['id']]);
            return false;
        }
        return $input;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!self::canView()) {
            return false;
        }
        return self::getTypeName(1);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        return self::showForUser($item);
    }

    public function getJSSPrivileges()
    {
        // Cache JSS account privileges information to avoid extra, costly API calls.
        static $privileges = [];

        if (!isset($privileges[$this->fields['jssaccounts_id']])) {
            $privileges[$this->fields['jssaccounts_id']] = PluginJamfAPI::getJSSAccountRights($this->fields['jssaccounts_id']);
        }
        return $privileges[$this->fields['jssaccounts_id']];
    }

    private static function getItemRightMap()
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'accounts' => ['Accounts'],
                'advancedcomputersearches' => ['Advanced Computer Searches'],
                'advancedmobiledevicesearches' => ['Advanced Mobile Device Searches'],
                'advancedusersearches' => ['Advanced User Searches'],
                'buildings' => ['Buildings'],
                'categories' => ['Categories'],
                'classes' => ['Classes'],
                'departments' => ['Departments'],
                'mobiledeviceapplications' => ['Mobile Device Applications'],
                'mobiledeviceextensionattributes' => ['Mobile Device Extension Attributes'],
                'mobiledevicegroups' => ['Smart Mobile Device Groups', 'Static Mobile Device Groups'],
                'mobiledevices' => ['Mobile Devices'],
                'users' => ['Users'],
            ];
        }
        return $map;
    }

    public static function canCreateJSSItem($itemtype, $meta)
    {
        if ($itemtype == 'mobiledevicecommands') {
            $commands = PluginJamfMDMCommand::getAvailableCommands();
            return self::haveJSSRight('jss_actions', $commands[$meta]['jss_right']);
        }
        $map = self::getItemRightMap();
        if (!isset($map[$itemtype])) {
            return false;
        }
        $rights = $map[$itemtype];
        foreach ($rights as $right) {
            if (!self::haveJSSRight('jss_objects', 'Create ' . $right)) {
                return false;
            }
        }
        return true;
    }

    public static function canReadJSSItem($itemtype)
    {
        $map = self::getItemRightMap();
        if (!isset($map[$itemtype])) {
            return false;
        }
        $rights = $map[$itemtype];
        foreach ($rights as $right) {
            if (!self::haveJSSRight('jss_objects', 'Read ' . $right)) {
                return false;
            }
        }
        return true;
    }

    public static function canUpdateJSSItem($itemtype)
    {
        $map = self::getItemRightMap();
        if (!isset($map[$itemtype])) {
            return false;
        }
        $rights = $map[$itemtype];
        foreach ($rights as $right) {
            if (!self::haveJSSRight('jss_objects', 'Update ' . $right)) {
                return false;
            }
        }
        return true;
    }

    public static function canDeleteJSSItem($itemtype)
    {
        $map = self::getItemRightMap();
        if (!isset($map[$itemtype])) {
            return false;
        }
        $rights = $map[$itemtype];
        foreach ($rights as $right) {
            if (!self::haveJSSRight('jss_objects', 'Delete ' . $right)) {
                return false;
            }
        }
        return true;
    }

    public static function hasLink()
    {
        $user_jssaccount = new self();
        $matches = $user_jssaccount->find([
            'users_id' => Session::getLoginUserID()
        ]);
        return count($matches) > 0;
    }

    public static function haveJSSRight($type, $jss_right)
    {
        $user_jssaccount = new self();
        static $matches = null;

        if ($matches === null) {
            $matches = $user_jssaccount->find([
                'users_id' => Session::getLoginUserID()
            ]);
        }
        if (count($matches) === 0) {
            // No JSS account link
            Toolbox::logError(_x('error', 'Attempt to use JSS user rights without a linked account', 'jamf'));
            return false;
        }
        $user_jssaccount->getFromDB(reset($matches)['id']);
        $type_rights = $user_jssaccount->getJSSPrivileges()[$type] ?? [];
        if (count($type_rights) === 0) {
            //Toolbox::logError("Linked JSS account has no rights of type $type");
            return false;
        }
        return in_array($jss_right, $type_rights);
    }

    public static function showForUser($item)
    {
        $canedit = self::canUpdate();

        $user_jssaccount = new self();
        $mylink = $user_jssaccount->find(['users_id' => $item->getID()]);
        if (count($mylink)) {
            $mylink = reset($mylink);
        } else {
            $mylink = null;
        }

        $allusers = PluginJamfAPI::getItemsClassic('accounts')['users'];
        $values = [];
        foreach ($allusers as $user) {
            $values[$user['id']] = $user['name'];
        }
        if (!$canedit) {
            $values = [$mylink['jssaccounts_id'] => $values[$mylink['jssaccounts_id']]];
        }

        TemplateRenderer::getInstance()->display('@jamf/user_jssaccount.html.twig', [
            'can_edit' => $canedit,
            'jssaccounts_id' => $mylink['jssaccounts_id'] ?? null,
            'jssaccount_link' => $mylink ? self::getJSSAccountURL($mylink['jssaccounts_id']) : null,
            'users_id' => $item->getID(),
            'url' => self::getFormURL(),
            'jssaccounts' => $values
        ]);
    }

    /**
     * Get a direct link to the JSS account on the Jamf server.
     * @param string $jssaccount_id The ID of the JSS Account.
     * @return string Jamf URL for the JSS account.
     */
    public static function getJSSAccountURL($jssaccount_id)
    {
        $config = PluginJamfConfig::getConfig();
        return "{$config['jssserver']}/accounts.html?id={$jssaccount_id}";
    }

    public function getRights($interface = 'central')
    {
        if ($interface == 'central') {
            return [
                READ => __('Read'),
                UPDATE => __('Update')
            ];
        }

        return [];
    }
}
