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

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Unified connector for Jamf's Classic and Pro APIs
 */
class PluginJamfAPI
{
    /**
     * PluginJamfConnection object representing the connection to a JSS server
     * @var PluginJamfConnection
     */
    private static $connection;

    /**
     * Get data from a JSS Classic API endpoint.
     * @param string $endpoint The API endpoint.
     * @param bool $raw If true, data is returned as JSON instead of decoded into an array.
     * @param string $response_type
     * @return mixed JSON string or associative array depending on the value of $raw.
     * @throws PluginJamfRateLimitException
     * @since 1.0.0
     */
    private static function getClassic(string $endpoint, $raw = false, $response_type = 'application/json')
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $url = static::$connection->getAPIUrl($endpoint);
        $client = static::$connection->getClient();

        try {
            $response = $client->get($url, [
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                    'Accept' => $response_type
                ]
            ]);
            $httpcode = $response->getStatusCode();
            $response = $response->getBody()->getContents();
        } catch (GuzzleHttp\Exception\ClientException $e) {
            return null;
        }

        if ($httpcode === 500) {
            $response = json_decode($response, true);
            if (isset($response['fault'])) {
                $fault = $response['fault'];
                // We are making too many API calls in a short time.
                if ($fault['detail']['errorcode'] === 'policies.ratelimit.QuotaViolation') {
                    throw new PluginJamfRateLimitException($fault['faultstring']);
                }
            }
            throw new RuntimeException(_x('error', 'Unknown JSS API Error', 'jamf'));
        }

        return ($raw ? $response : json_decode($response, true));
    }

    /**
     * Add a new item through a JSS Classic API endpoint.
     * @param string $endpoint The API endpoint.
     * @param string $payload XML payload of data to post to the endpoint.
     * @return int|bool True if successful, or the HTTP return code if it is not 201.
     * @since 1.1.0
     */
    private static function addClassic(string $endpoint, string $payload)
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $url = (static::$connection)->getAPIUrl($endpoint);
        $client = static::$connection->getClient();

        try {
            $response = $client->post($url, [
                RequestOptions::HEADERS => [
                    'Content-Type: application/xml',
                    'Accept: application/json'
                ],
                RequestOptions::BODY => $payload
            ]);
            $httpcode = $response->getStatusCode();
        } catch (GuzzleHttp\Exception\ClientException $e) {
            return null;
        }
        return ($httpcode === 201) ? true : $httpcode;
    }

    /**
     * Update an item through a JSS Classic API endpoint.
     * @param string $endpoint The API endpoint.
     * @param array $data Associative array of data to put to the endpoint.
     * @return int|bool True if successful, or the HTTP return code if it is not 201.
     * @since 1.1.0
     */
    private static function updateClassic(string $endpoint, array $data)
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $url = (static::$connection)->getAPIUrl($endpoint);
        $client = static::$connection->getClient();

        try {
            $response = $client->put($url, [
                RequestOptions::HEADERS => [
                    'Content-Type: application/xml',
                    'Accept: application/json'
                ],
                RequestOptions::BODY => $data,
            ]);
            $httpcode = $response->getStatusCode();
        } catch (GuzzleException $e) {
            return null;
        }

        return ($httpcode === 201) ? true : $httpcode;
    }

    /**
     * Delete an item through a JSS Classic API endpoint.
     * @param string $endpoint The API endpoint.
     * @return int|bool True if successful, or the HTTP return code if it is not 200.
     * @since 1.1.0
     */
    private static function deleteClassic(string $endpoint)
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $url = (static::$connection)->getAPIUrl($endpoint);
        $client = static::$connection->getClient();

        try {
            $response = $client->delete($url);
            $httpcode = $response->getStatusCode();
        } catch (GuzzleException $e) {
            return null;
        }
        return ($httpcode === 200) ? true : $httpcode;
    }

    /**
     * Construct a parameter query string for a Classic API endpoint.
     * @param array $params API inputs.
     * @return string The constructed parameter string.
     * @since 1.0.0
     */
    private static function getParamStringClassic(array $params = [])
    {
        $param_str = '';
        foreach ($params as $key => $value) {
            $param_str = "{$param_str}/{$key}/{$value}";
        }
        return $param_str;
    }

    /**
     * Get data for a specified JSS itemtype and parameters.
     * @param string $itemtype The type of data to fetch. This matches up with endpoint names.
     * @param array $params API input parameters such as udid, name, or subset.
     * @param bool $user_auth True if the user's linked JSS account privileges should be checked for requested resource.
     * @return array Associative array of the decoded JSON response.
     * @since 1.0.0
     */
    public static function getItemsClassic(string $itemtype, array $params = [], $user_auth = false)
    {
        if ($user_auth && !PluginJamfUser_JSSAccount::canReadJSSItem($itemtype)) {
            return null;
        }
        $param_str = static::getParamStringClassic($params);
        $endpoint = "$itemtype$param_str";
        $response = static::getClassic($endpoint);
        // Strip first key (usually like mobile_devices or mobile_device)
        // No other first level keys exist
        return ($response !== null && count($response)) ? reset($response) : null;
    }

    /**
     * Add an item of the specified JSS itemtype and parameters.
     * @param string $itemtype The type of data to fetch. This matches up with endpoint names.
     * @param string $payload XML payload of data to post to the endpoint.
     * @param bool $user_auth True if the user's linked JSS account privileges should be checked for requested resource.
     * @return bool|int
     * @since 1.1.0
     */
    public static function addItemClassic(string $itemtype, string $payload, $user_auth = false)
    {
        if ($itemtype === 'mobiledevicecommands') {
            $param_str = '/command';
            $meta = (string)simplexml_load_string($payload)->general->command;
        } else {
            $param_str = '';
            $meta = null;
        }
        if ($user_auth && !PluginJamfUser_JSSAccount::canCreateJSSItem($itemtype, $meta)) {
            return null;
        }

        $endpoint = "$itemtype$param_str";
        return static::addClassic($endpoint, $payload);
    }

    /**
     * Update an item of the specified JSS itemtype and parameters.
     * @param string $itemtype The type of data to fetch. This matches up with endpoint names.
     * @param array $params API input parameters such as udid, name, or subset.
     * @param array $fields Associative array of item fields.
     * @param bool $user_auth True if the user's linked JSS account privileges should be checked for requested resource.
     * @return bool|int
     * @since 1.1.0
     */
    public static function updateItemClassic(string $itemtype, array $params = [], array $fields = [], $user_auth = false)
    {
        if ($user_auth && !PluginJamfUser_JSSAccount::canUpdateJSSItem($itemtype)) {
            return null;
        }
        $param_str = static::getParamStringClassic($params);
        $endpoint = "$itemtype$param_str";
        return static::updateClassic($endpoint, $fields);
    }

    /**
     * Delete an item of the specified JSS itemtype and parameters.
     * @param string $itemtype The type of data to fetch. This matches up with endpoint names.
     * @param array $params API input parameters such as udid, name, or subset.
     * @param bool $user_auth True if the user's linked JSS account privileges should be checked for requested resource.
     * @return bool|int
     * @since 1.1.0
     */
    public static function deleteItemClassic(string $itemtype, array $params = [], $user_auth = false)
    {
        if ($user_auth && !PluginJamfUser_JSSAccount::canDeleteJSSItem($itemtype)) {
            return null;
        }
        $param_str = static::getParamStringClassic($params);
        $endpoint = "$itemtype$param_str";
        return static::deleteClassic($endpoint);
    }

    private static function getJSSGroupActionRights($groupid)
    {
        $response = static::getClassic("accounts/groupid/$groupid");
        return $response['group']['privileges']['jss_actions'];
    }

    public static function getJSSAccountRights($userid, $user_auth = false)
    {
        if ($user_auth && !PluginJamfUser_JSSAccount::canReadJSSItem('accounts')) {
            return null;
        }
        $response = static::getClassic("accounts/userid/$userid", true, 'application/xml');
        $account = simplexml_load_string($response);

        $access_level = $account->access_level;
        $rights = [
            'jss_objects' => [],
            'jss_actions' => [],
            'jss_settings' => []
        ];
        if ($access_level === 'Group Access') {
            $group_count = count($account->groups->group);
            //$groups = $account->groups->group;
            for ($i = 0; $i < $group_count; $i++) {
                $group = $account->groups->group[$i];
                if (isset($group->privileges->jss_objects)) {
                    $c = count($group->privileges->jss_objects->privilege);
                    if ($c > 0) {
                        for ($j = 0; $j < $c; $j++) {
                            $rights['jss_objects'][] = reset($group->privileges->jss_objects->privilege[$j]);
                        }
                    }
                }
                // Why are jss_actions not included in the group when all other rights are?
                $action_privileges = static::getJSSGroupActionRights(reset($group->id));
                $rights['jss_actions'] = $action_privileges;

                if (isset($group->privileges->jss_settings)) {
                    $c = count($group->privileges->jss_settings->privilege);
                    if ($c > 0) {
                        for ($j = 0; $j < $c; $j++) {
                            $rights['jss_settings'][] = reset($group->privileges->jss_settings->privilege[$j]);
                        }
                    }
                }
            }
        } else {
            $privileges = $account->privileges;
            if (isset($privileges->jss_objects)) {
                $c = count($privileges->jss_objects->privilege);
                if ($c > 0) {
                    for ($j = 0; $j < $c; $j++) {
                        $rights['jss_objects'][] = $privileges->jss_objects->privilege[$j];
                    }
                }
            }
            if (isset($privileges->jss_actions)) {
                $c = count($privileges->jss_actions->privilege);
                if ($c > 0) {
                    for ($j = 0; $j < $c; $j++) {
                        $rights['jss_actions'][] = $privileges->jss_actions->privilege[$j];
                    }
                }
            }
            if (isset($privileges->jss_settings)) {
                $c = count($privileges->jss_settings->privilege);
                if ($c > 0) {
                    for ($j = 0; $j < $c; $j++) {
                        $rights['jss_settings'][] = $privileges->jss_settings->privilege[$j];
                    }
                }
            }
        }
        return $rights;
    }

    public static function testClassicAPIConnection(): bool
    {
        try {
            static::getItemsClassic('mobiledevices', ['match' => '?name=glpi_conn_test']);
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }
    public static function testProAPIConnection(): bool
    {
        try {
            static::getJamfProVersion();
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * @return string The version of the JSS server.
     */
    public static function getJamfProVersion(): string
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $response = static::$connection->getClient()->get(static::$connection->getAPIUrl('v1/jamf-pro-version', true))->getBody()->getContents();
        return json_decode($response, true)['version'];
    }

    public static function sendMDMCommand(string $payload_xml, bool $user_auth = false)
    {
        return self::addItemClassic('mobiledevicecommands', $payload_xml, $user_auth);
    }

    /**
     * Get an array of all mobile devices. The returned data includes only some fields.
     * @return array Array of mobile devices and some basic fields for each.
     */
    public static function getAllMobileDevices()
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $all_results = [];

        $endpoint_base = '/v2/mobile-devices';
        $query_params = [
            'page' => 0,
            'page-size' => 1000
        ];
        $client = static::$connection->getClient();
        $response = $client->get(static::$connection->getAPIUrl($endpoint_base, true) . '?' . http_build_query($query_params));
        $initial_response = json_decode($response->getBody()->getContents(), true);
        $total_results = $initial_response['totalCount'];
        $all_results = array_merge($all_results, $initial_response['results']);

        // Do we need to get more pages?
        if ($total_results > 1000) {
            $pages = ceil($total_results / 1000);
            for ($i = 1; $i < $pages; $i++) {
                $query_params['page'] = $i;
                $response = $client->get(static::$connection->getAPIUrl($endpoint_base, true) . '?' . http_build_query($query_params));
                $response = json_decode($response->getBody()->getContents(), true);
                $all_results = [...$all_results, ...$response['results']];
            }
        }

        return $all_results;
    }

    /**
     * Get data for a specific mobile device by its id.
     * @param int $id The ID of the device.
     * @param bool $detailed If true, all fields are returned. Otherwise, only a basic subset of fields are returned.
     * @return array Associative array of fields for the specified device.
     */
    public static function getMobileDeviceByID(int $id, bool $detailed = false)
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $endpoint = "/v2/mobile-devices/{$id}" . ($detailed ? '/detail' : '');
        $response = static::$connection->getClient()->get(static::$connection->getAPIUrl($endpoint, true));
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get data for a specific mobile device by its UDID.
     * @param string $udid The UDID of the device.
     * @param string $section The section of the device to get data for.
     * @return ?array Associative array of fields for the specified device or null if not found.
     */
    public static function getMobileDeviceByUDID(string $udid, string $section = 'general'): ?array
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $query_params = [
            'section' => strtoupper($section),
            'filter' => 'udid=="' . $udid . '"'
        ];
        $endpoint = '/v2/mobile-devices/detail' . '?' . http_build_query($query_params);
        $response = static::$connection->getClient()->get(static::$connection->getAPIUrl($endpoint, true));
        $result = json_decode($response->getBody()->getContents(), true);
        if (isset($result['results']) && count($result['results']) > 0) {
            return $result['results'][0];
        }
        return null;
    }

    /**
     * Get an array of all computers. The returned data includes only some fields.
     * @return array Array of computers and some basic fields for each.
     */
    public static function getAllComputers()
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $all_results = [];

        $endpoint_base = '/v1/computers-inventory';
        $query_params = [
            'page' => 0,
            'page-size' => 1000
        ];
        $client = static::$connection->getClient();
        $response = $client->get(static::$connection->getAPIUrl($endpoint_base, true) . '?' . http_build_query($query_params));
        $initial_response = json_decode($response->getBody()->getContents(), true);
        $total_results = $initial_response['totalCount'];
        $all_results = array_merge($all_results, $initial_response['results']);

        // Do we need to get more pages?
        if ($total_results > 1000) {
            $pages = ceil($total_results / 1000);
            for ($i = 1; $i < $pages; $i++) {
                $query_params['page'] = $i;
                $response = $client->get(static::$connection->getAPIUrl($endpoint_base, true) . '?' . http_build_query($query_params));
                $response = json_decode($response->getBody()->getContents(), true);
                $all_results = [...$all_results, ...$response['results']];
            }
        }

        return $all_results;
    }

    public static function getComputerByID(int $id, ?string $section = null): ?array
    {
        if (!static::$connection) {
            static::$connection = new PluginJamfConnection();
        }
        $endpoint = "/v1/computer-inventory";
        $query_params = [
            'section' => $section,
            'filter' => 'id==' . $id
        ];
        $response = static::$connection->getClient()->get(static::$connection->getAPIUrl($endpoint, true) . '?' . http_build_query($query_params));
        $result = json_decode($response->getBody()->getContents(), true);
        if (isset($result['results']) && count($result['results']) > 0) {
            return $result['results'][0];
        }
        return null;
    }
}
