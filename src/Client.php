<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:24
 */

namespace Keboola\ManageApi;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client
{
    private $apiUrl = "https://connection.keboola.com";

    private $tokenString = '';

    private $backoffMaxTries = 10;

    private $userAgent = 'Keboola Manage API PHP Client';

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * Clients accept an array of constructor parameters.
     *
     * Client configuration settings include the following options:
     *  - url - API URL
     *  - token - Keboola Manage api token
     *  - backoffMaxTries - backoff maximum retries count
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!isset($config['token'])) {
            throw new \InvalidArgumentException('token must be set');
        }
        $this->tokenString = $config['token'];

        if (isset($config['url'])) {
            $this->apiUrl = $config['url'];
        }

        if (isset($config['backoffMaxTries'])) {
            $this->backoffMaxTries = (int)$config['backoffMaxTries'];
        }
        $this->initClient();
    }

    private function initClient()
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            self::createDefaultDecider($this->backoffMaxTries),
            self::createExponentialDelay()
        ));

        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->apiUrl,
            'handler' => $handlerStack,
        ]);
    }

    private static function createDefaultDecider($maxRetries = 3)
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            $error = null
        ) use ($maxRetries) {
            if ($retries >= $maxRetries) {
                return false;
            } elseif ($response && $response->getStatusCode() > 499) {
                return true;
            } elseif ($error) {
                return true;
            } else {
                return false;
            }
        };
    }

    private static function createExponentialDelay()
    {
        return function ($retries) {
            return (int)pow(2, $retries - 1) * 1000;
        };
    }

    public function verifyToken()
    {
        return $this->apiGet("/manage/tokens/verify");
    }

    public function listMaintainers()
    {
        return $this->apiGet("/manage/maintainers");
    }

    public function listOrganizations()
    {
        return $this->apiGet("/manage/organizations");
    }

    public function listOrganizationProjects($organizationId)
    {
        return $this->apiGet("/manage/organizations/{$organizationId}/projects");
    }

    public function createOrganization($maintainerId, $params)
    {
        return $this->apiPost("/manage/maintainers/{$maintainerId}/organizations",$params);
    }

    public function deleteOrganization($id)
    {
        $this->apiDelete("/manage/organizations/{$id}");
    }

    public function createProject($organizationId, $params)
    {
        return $this->apiPost("/manage/organizations/{$organizationId}/projects", $params);
    }

    public function updateProject($id, $params)
    {
        return $this->apiPut("/manage/projects/{$id}", $params);
    }

    public function getProject($id)
    {
        return $this->apiGet("/manage/projects/{$id}");
    }

    public function deleteProject($id)
    {
        $this->apiDelete("/manage/projects/{$id}");
    }

    public function listProjectUsers($id)
    {
        return $this->apiGet("/manage/projects/{$id}/users");
    }

    public function addUserToProject($projectId, $params = [])
    {
        return $this->apiPost("/manage/projects/{$projectId}/users", $params);
    }

    public function removeUserFromProject($projectId, $userId)
    {
        $this->apiDelete("/manage/projects/{$projectId}/users/{$userId}");
    }

    public function changeProjectOrganization($projectId, $newOrganizationId)
    {
        return $this->apiPost("/manage/projects/{$projectId}/organizations", [
            'organizationId' => $newOrganizationId,
        ]);
    }

    private function apiGet($url)
    {
        return $this->request('GET', $url);
    }

    private function apiPost($url, $data)
    {
        return $this->request('POST', $url, [
            'json' => $data,
        ]);
    }

    private function apiPut($url, $data)
    {
        return $this->request('PUT', $url, [
            'json' => $data,
        ]);
    }

    private function apiDelete($url)
    {
        $this->request('DELETE', $url);
    }

    private function request($method, $url, array $options = [])
    {
        $requestOptions = array_merge($options, [
            'headers' => [
                'X-KBC-ManageApiToken' => $this->tokenString,
                'Accept-Encoding' => 'gzip',
                'User-Agent' => $this->userAgent,
            ]
        ]);

        try {
            /**
             * @var ResponseInterface $response
             */
            $response = $this->client->request($method, $url, $requestOptions);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $body = $response ? json_decode((string)$response->getBody(), true) : [];

            if ($response && $response->getStatusCode() == 503) {
                throw new MaintenanceException(isset($body["reason"]) ? $body['reason'] : 'Maintenance', $response && $response->hasHeader('Retry-After') ? (string)$response->getHeader('Retry-After')[0] : null, $body);
            }

            throw new ClientException(
                isset($body['error']) ? $body['error'] : $e->getMessage(),
                $response ? $response->getStatusCode() : $e->getCode(),
                $e,
                isset($body['code']) ? $body['code'] : "",
                $body
            );
        }

        if ($response->hasHeader('Content-Type') && $response->getHeader('Content-Type')[0] == 'application/json') {
            return json_decode((string)$response->getBody(), true);
        }

        return (string)$response->getBody();
    }

}