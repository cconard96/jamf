<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2021 by Curtis Conard
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

/**
 * JamfConnection class
 * @since 1.0.0
 */
class PluginJamfConnection
{
    private $config;

    /**
     * Load connection details from the DB and store them in the $config array.
     * @since 1.0.0
     */
    public function __construct()
    {
        $jamf_config = Config::getConfigurationValues('plugin:Jamf', [
            'jssserver', 'jssuser', 'jsspassword', 'jssignorecert']);
        $this->config = $jamf_config;
        $glpikey = new GLPIKey();
        $this->config['jsspassword'] = $glpikey->decrypt($this->config['jsspassword']);
    }

    /**
     * Set or change the connection details in the DB.
     * @param string $jssserver The URL (and port) or the JSS server.
     * @param string $jssuser The user to connect to the JSS with.
     * @param string $jsspassword The password for $jssuser.
     * @since 1.0.0
     */
    public function setConnectionConfig($jssserver, $jssuser, $jsspassword)
    {
        global $DB;

        $glpikey = new GLPIKey();
        $enc = $glpikey->encrypt($jsspassword);
        Config::setConfigurationValues('plugin:Jamf', [
            'jssserver' => $jssserver,
            'jssuser' => $jssuser,
            'jsspassword' => $enc
        ]);
    }

    /**
     * Get the version of the JSS server and cache it for future function calls.
     * @return string The JSS version.
     * @since 1.0.0
     */
    public function getServerVersion()
    {
        static $version = null;
        if (is_null($version)) {
            $version = PluginJamfAPIPro::getLobby()['version'];
        }
        return $version;
    }

    /**
     * Gets the API endpoint URL based on if it is using the classic or pro API.
     * @param string $endpoint The API endpoint.
     * @param bool $pro_api True if using the pro API.
     * @return string The full API endpoint URL.
     */
    public function getAPIUrl($endpoint, $pro_api = false)
    {
        if ($pro_api) {
            return "{$this->config['jssserver']}/uapi/{$endpoint}";
        }

        return "{$this->config['jssserver']}/JSSResource/{$endpoint}";
    }

    public static function getUserAgentString(): string
    {
        return "Jamf%20Plugin%20for%20GLPI/".PLUGIN_JAMF_VERSION;
    }

    /**
     * Sets all common curl options needed for the API calls.
     *
     * @param $curl
     * @return void
     */
    public function setCurlOptions(&$curl): void
    {
        $this->setCurlAuth($curl);
        $this->setCurlSecurity($curl);

        // Set user agent
        curl_setopt($curl, CURLOPT_USERAGENT, self::getUserAgentString());
    }

    /**
     * Set the username and password for the specified curl connection.
     * @param resource $curl The curl handle.
     */
    protected function setCurlAuth(&$curl)
    {
        if (isset($this->config['jssuser']) && !empty($this->config['jssuser'])) {
            curl_setopt($curl, CURLOPT_USERPWD, $this->config['jssuser'] . ':' . $this->config['jsspassword']);
        }
    }

    /**
     * Set the security options for the specified curl connection.
     * @param $curl
     * @since 2.0.0
     */
    protected function setCurlSecurity(&$curl)
    {
        curl_setopt($curl, CURLOPT_SSLVERSION, 6);
        if ($this->config['jssignorecert']) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }
    }
}
