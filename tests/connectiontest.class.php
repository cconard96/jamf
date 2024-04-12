<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

use GuzzleHttp\ClientTrait;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PluginJamfConnectionTest extends PluginJamfConnection
{
    public function getClient(): ClientInterface
    {
        if (!isset($this->client)) {
            $this->client = new class implements ClientInterface {
                use ClientTrait;

                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    $endpoint = $request->getUri()->getPath();
                    var_dump($endpoint);
                    // remove query parameters
                    $endpoint = str_contains($endpoint, '?') ? explode('?', $endpoint)[0] : $endpoint;
                    $response_type = $request->getHeader('Accept')[0] ?? 'application/json';
                    $response_ext = $response_type === 'application/xml' ? 'xml' : 'json';
                    $mock_file_path = GLPI_ROOT . '/plugins/jamf/tools/samples/' . $endpoint . '.' . $response_ext;
                    return new \GuzzleHttp\Psr7\Response(200, [], file_get_contents($mock_file_path));
                }

                public function request(string $method, $uri, array $options = []): ResponseInterface
                {
                    $request = new \GuzzleHttp\Psr7\Request($method, $uri, $options['headers'] ?? []);
                    return $this->sendRequest($request);
                }

                public function requestAsync(string $method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
                {
                    return \GuzzleHttp\Promise\Create::promiseFor($this->request($method, $uri, $options));
                }
            };
        }

        return $this->client;
    }
}
