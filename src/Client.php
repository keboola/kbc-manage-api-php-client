<?php

namespace Keboola\ManageApi;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client
{
    private $apiUrl;

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

        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('url must be set');
        }
        $this->apiUrl = $config['url'];

        if (isset($config['backoffMaxTries'])) {
            $this->backoffMaxTries = (int) $config['backoffMaxTries'];
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
            ?ResponseInterface $response = null,
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
            return (int) pow(2, $retries - 1) * 1000;
        };
    }

    public function verifyToken()
    {
        return $this->apiGet('/manage/tokens/verify');
    }

    public function listMaintainers()
    {
        return $this->apiGet('/manage/maintainers');
    }

    public function createMaintainer($params)
    {
        return $this->apiPost('/manage/maintainers', $params);
    }

    public function updateMaintainer($maintainerId, $params = [])
    {
        return $this->apiPatch("/manage/maintainers/{$maintainerId}", $params);
    }

    public function deleteMaintainer($maintainerId)
    {
        $this->apiDelete("/manage/maintainers/{$maintainerId}");
    }

    public function getMaintainer($maintainerId)
    {
        return $this->apiGet("/manage/maintainers/{$maintainerId}");
    }

    public function listMaintainerMembers($maintainerId)
    {
        return $this->apiGet("/manage/maintainers/{$maintainerId}/users");
    }

    public function addUserToMaintainer($maintainerId, $params = [])
    {
        return $this->apiPost("/manage/maintainers/{$maintainerId}/users", $params);
    }

    public function removeUserFromMaintainer($maintainerId, $userId)
    {
        $this->apiDelete("/manage/maintainers/{$maintainerId}/users/{$userId}");
    }

    public function removeUser($userId)
    {
        $this->apiDelete("/manage/users/{$userId}");
    }

    public function inviteUserToMaintainer($maintainerId, $params = [])
    {
        return $this->apiPost("manage/maintainers/{$maintainerId}/invitations", $params);
    }

    public function listMaintainerInvitations($id)
    {
        return $this->apiGet("/manage/maintainers/{$id}/invitations");
    }

    public function getMaintainerInvitation($maintainerId, $invitationId)
    {
        return $this->apiGet("/manage/maintainers/{$maintainerId}/invitations/{$invitationId}");
    }

    public function cancelMaintainerInvitation($maintainerId, $invitationId)
    {
        $this->apiDelete("/manage/maintainers/{$maintainerId}/invitations/{$invitationId}");
    }

    public function listMyMaintainerInvitations()
    {
        return $this->apiGet('/manage/current-user/maintainers-invitations');
    }

    public function acceptMyMaintainerInvitation($id)
    {
        $this->apiPut("/manage/current-user/maintainers-invitations/{$id}", []);
    }

    public function getMyMaintainerInvitation($id)
    {
        return $this->apiGet("/manage/current-user/maintainers-invitations/{$id}");
    }

    public function declineMyMaintainerInvitation($id)
    {
        $this->apiDelete("/manage/current-user/maintainers-invitations/{$id}");
    }

    public function joinMaintainer($maintainerId)
    {
        $this->apiPost("/manage/maintainers/{$maintainerId}/join-maintainer");
    }

    public function listMaintainerOrganizations($maintainerId)
    {
        return $this->apiGet("/manage/maintainers/{$maintainerId}/organizations");
    }

    public function listOrganizations()
    {
        return $this->apiGet('/manage/organizations');
    }

    public function listOrganizationProjects($organizationId)
    {
        return $this->apiGet("/manage/organizations/{$organizationId}/projects");
    }

    public function createOrganization($maintainerId, $params)
    {
        return $this->apiPost("/manage/maintainers/{$maintainerId}/organizations", $params);
    }

    public function getOrganization($organizationId)
    {
        return $this->apiGet("/manage/organizations/{$organizationId}");
    }

    public function updateOrganization($organizationId, $params)
    {
        return $this->apiPatch("/manage/organizations/{$organizationId}", $params);
    }

    public function enableOrganizationMfa($organizationId)
    {
        return $this->apiPatch("/manage/organizations/{$organizationId}/force-mfa", []);
    }

    public function listOrganizationUsers($organizationId)
    {
        return $this->apiGet("manage/organizations/{$organizationId}/users");
    }

    public function addUserToOrganization($organizationId, $params = [])
    {
        return $this->apiPost("manage/organizations/{$organizationId}/users", $params);
    }

    public function removeUserFromOrganization($organizationId, $userId)
    {
        $this->apiDelete("manage/organizations/{$organizationId}/users/{$userId}");
    }

    public function deleteOrganization($id)
    {
        $this->apiDelete("/manage/organizations/{$id}");
    }

    public function inviteUserToOrganization($organizationId, $params = [])
    {
        return $this->apiPost("manage/organizations/{$organizationId}/invitations", $params);
    }

    public function listOrganizationInvitations($id)
    {
        return $this->apiGet("/manage/organizations/{$id}/invitations");
    }

    public function getOrganizationInvitation($organizationId, $invitationId)
    {
        return $this->apiGet("/manage/organizations/{$organizationId}/invitations/{$invitationId}");
    }

    public function cancelOrganizationInvitation($organizationId, $invitationId)
    {
        $this->apiDelete("/manage/organizations/{$organizationId}/invitations/{$invitationId}");
    }

    public function listMyOrganizationInvitations()
    {
        return $this->apiGet('/manage/current-user/organizations-invitations');
    }

    public function acceptMyOrganizationInvitation($id)
    {
        $this->apiPut("/manage/current-user/organizations-invitations/{$id}", []);
    }

    public function getMyOrganizationInvitation($id)
    {
        return $this->apiGet("/manage/current-user/organizations-invitations/{$id}");
    }

    public function declineMyOrganizationInvitation($id)
    {
        $this->apiDelete("/manage/current-user/organizations-invitations/{$id}");
    }

    public function joinOrganization($organizationId)
    {
        $this->apiPost("/manage/organizations/{$organizationId}/join-organization");
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

    public function undeleteProject($id, $params = array())
    {
        $this->apiDelete("/manage/deleted-projects/{$id}?" . http_build_query($params));
    }

    public function listDeletedProjects($params = array())
    {
        $defaultParams = array(
            'limit' => 100,
            'offset' => 0,
        );

        $queryParams = array_merge($defaultParams, $params);
        return $this->apiGet('/manage/deleted-projects?' . http_build_query($queryParams));
    }

    public function getDeletedProject($id)
    {
        return $this->apiGet("/manage/deleted-projects/{$id}");
    }

    public function purgeDeletedProject($id, array $params = [])
    {
        return $this->apiPost("/manage/deleted-projects/{$id}/purge", $params);
    }

    public function listProjectUsers($id)
    {
        return $this->apiGet("/manage/projects/{$id}/users");
    }

    public function listProjectInvitations($id)
    {
        return $this->apiGet("/manage/projects/{$id}/invitations");
    }

    public function listMyProjectInvitations()
    {
        return $this->apiGet('/manage/current-user/projects-invitations');
    }

    public function acceptMyProjectInvitation($id)
    {
        $this->apiPut("/manage/current-user/projects-invitations/{$id}", []);
    }

    public function getMyProjectInvitation($id)
    {
        return $this->apiGet("/manage/current-user/projects-invitations/{$id}");
    }

    public function declineMyProjectInvitation($id)
    {
        $this->apiDelete("/manage/current-user/projects-invitations/{$id}");
    }

    public function addUserToProject($projectId, $params = [])
    {
        return $this->apiPost("/manage/projects/{$projectId}/users", $params);
    }

    public function inviteUserToProject($projectId, $params = [])
    {
        return $this->apiPost("/manage/projects/{$projectId}/invitations", $params);
    }

    public function removeUserFromProject($projectId, $userId)
    {
        $this->apiDelete("/manage/projects/{$projectId}/users/{$userId}");
    }

    public function updateUserProjectMembership($projectId, $userId, $params)
    {
        return $this->apiPatch("/manage/projects/{$projectId}/users/{$userId}", $params);
    }

    public function getProjectInvitation($projectId, $invitationId)
    {
        return $this->apiGet("/manage/projects/{$projectId}/invitations/{$invitationId}");
    }

    public function cancelProjectInvitation($projectId, $invitationId)
    {
        $this->apiDelete("/manage/projects/{$projectId}/invitations/{$invitationId}");
    }

    public function enableProject($projectId)
    {
        $this->apiPost("/manage/projects/{$projectId}/disabled", [
           'isDisabled' => false,
        ]);
    }

    public function disableProject($projectId, $params = [])
    {
        $this->apiPost("/manage/projects/{$projectId}/disabled", array_merge($params, [
            'isDisabled' => true,
        ]));
    }

    public function createProjectStorageToken($projectId, array $params)
    {
        return $this->apiPost("/manage/projects/{$projectId}/tokens", $params);
    }

    public function assignProjectStorageBackend($projectId, $backendId)
    {
        return $this->apiPost("/manage/projects/{$projectId}/storage-backend", [
            'storageBackendId' => (int) $backendId,
        ]);
    }

    public function removeProjectStorageBackend($projectId, $backendId)
    {
        $this->apiDelete("/manage/projects/{$projectId}/storage-backend/{$backendId}");
    }

    public function assignFileStorage($projectId, $storageId)
    {
        return $this->apiPost("/manage/projects/{$projectId}/file-storage", [
           'fileStorageId' => (int) $storageId,
        ]);
    }

    public function changeProjectOrganization($projectId, $newOrganizationId)
    {
        return $this->apiPost("/manage/projects/{$projectId}/organizations", [
            'organizationId' => $newOrganizationId,
        ]);
    }

    public function setProjectLimits($projectId, array $limits)
    {
        return $this->apiPost("/manage/projects/{$projectId}/limits", [
           'limits' => $limits,
        ]);
    }

    public function removeProjectLimit($projectId, $limitName)
    {
        $this->apiDelete("/manage/projects/{$projectId}/limits/{$limitName}");
    }

    public function createFeature($name, $type, $description)
    {
        return $this->apiPost('/manage/features', [
            'name' => $name,
            'type' => $type,
            'description' => $description,
        ]);
    }

    public function removeFeature($id)
    {
        $this->apiDelete("/manage/features/{$id}");
    }

    public function listFeatures(array $params = [])
    {
        $url = '/manage/features';
        if (isset($params['type'])) {
            $url .= '?' . http_build_query(['type' => $params['type']]);
        }
        return $this->apiGet($url);
    }

    public function getFeature($id)
    {
        return $this->apiGet("/manage/features/{$id}");
    }

    public function getFeatureProjects($id)
    {
        return $this->apiGet("/manage/features/{$id}/projects");
    }

    public function getFeatureAdmins($id)
    {
        return $this->apiGet("/manage/features/{$id}/admins");
    }

    public function addProjectFeature($projectId, $feature)
    {
        return $this->apiPost("/manage/projects/{$projectId}/features", [
           'feature' => (string) $feature,
        ]);
    }

    public function removeProjectFeature($projectId, $feature)
    {
        $this->apiDelete("/manage/projects/{$projectId}/features/{$feature}");
    }

    public function getUser($emailOrId)
    {
        return $this->apiGet("/manage/users/{$emailOrId}");
    }

    public function updateUser($emailOrId, $params)
    {
        return $this->apiPut("/manage/users/{$emailOrId}", $params);
    }

    public function addUserFeature($emailOrId, $feature)
    {
        return $this->apiPost("/manage/users/{$emailOrId}/features", [
            'feature' => $feature,
        ]);
    }

    public function removeUserFeature($emailOrId, $feature)
    {
        $this->apiDelete("/manage/users/{$emailOrId}/features/{$feature}");
    }

    public function getProjectTemplate($templateStringId)
    {
        return $this->apiGet("/manage/project-templates/{$templateStringId}");
    }

    public function getProjectTemplates()
    {
        return $this->apiGet('/manage/project-templates');
    }

    public function getProjectTemplateFeatures($templateStringId)
    {
        return $this->apiGet("/manage/project-templates/{$templateStringId}/features");
    }

    public function addProjectTemplateFeature($templateStringId, $featureName)
    {
        return $this->apiPost("/manage/project-templates/{$templateStringId}/features", [
            'feature' => $featureName,
        ]);
    }

    public function removeProjectTemplateFeature($templateStringId, $featureName)
    {
        $this->apiDelete("/manage/project-templates/{$templateStringId}/features/{$featureName}");
    }

    public function disableUserMFA($emailOrId)
    {
        $this->apiDelete("/manage/users/{$emailOrId}/mfa");
    }

    public function addNotification($data)
    {
        return $this->apiPost('/manage/notifications', $data);
    }

    /**
     * @param $params
     *  - fromId
     *
     * @return mixed|string
     */
    public function getNotifications($params = [])
    {
        $url = '/manage/notifications';
        if (!empty($params['fromId'])) {
            $url .= '?fromId=' .$params['fromId'];
        }
        return $this->apiGet($url);
    }

    public function markAllNotificationsAsRead()
    {
        return $this->apiPut('/manage/notifications', [
           'allRead' => true,
        ]);
    }

    public function markReadNotifications(array $ids)
    {
        return $this->apiPut('/manage/notifications', ['read' => $ids]);
    }

    public function createS3FileStorage(array $options)
    {
        return $this->apiPost('/manage/file-storage-s3/', $options);
    }

    public function setS3FileStorageAsDefault($fileStorageId)
    {
        return $this->apiPost("/manage/file-storage-s3/{$fileStorageId}/default");
    }

    public function listS3FileStorage()
    {
        return $this->apiGet('manage/file-storage-s3');
    }

    public function rotateS3FileStorageCredentials(int $fileStorageId, array $options)
    {
        return $this->apiPost('manage/file-storage-s3/' . $fileStorageId . '/credentials', $options);
    }

    public function createAbsFileStorage(array $options)
    {
        return $this->apiPost('/manage/file-storage-abs/', $options);
    }

    public function setAbsFileStorageAsDefault($fileStorageId)
    {
        return $this->apiPost("/manage/file-storage-abs/{$fileStorageId}/default");
    }

    public function listAbsFileStorage()
    {
        return $this->apiGet('manage/file-storage-abs/');
    }


    public function rotateAbsFileStorageCredentials(int $fileStorageId, array $options)
    {
        return $this->apiPost('manage/file-storage-abs/' . $fileStorageId . '/credentials', $options);
    }

    public function createStorageBackend(array $options)
    {
        return $this->apiPost('/manage/storage-backend', $options);
    }

    public function listStorageBackend($options = [])
    {
        return $this->apiGet('manage/storage-backend?' . http_build_query($options));
    }

    public function listUiApps()
    {
        return $this->apiGet('manage/ui-apps');
    }

    public function runCommand(array $options)
    {
        return $this->apiPost('/manage/commands', $options);
    }

    public function listMyProjectJoinRequests()
    {
        return $this->apiGet('/manage/current-user/projects-join-requests');
    }

    public function getMyProjectJoinRequest($id)
    {
        return $this->apiGet("/manage/current-user/projects-join-requests/{$id}");
    }

    public function requestAccessToProject($projectId, $params = [])
    {
        return $this->apiPost("/manage/projects/{$projectId}/request-access", $params);
    }

    public function joinProject($projectId)
    {
        $this->apiPost("/manage/projects/{$projectId}/join-project");
    }

    public function deleteMyProjectJoinRequest($id)
    {
        $this->apiDelete("/manage/current-user/projects-join-requests/{$id}");
    }

    public function approveMyProjectJoinRequest($id)
    {
        $this->apiPut("/manage/current-user/projects-join-requests/{$id}", []);
    }

    public function listProjectJoinRequests($id)
    {
        return $this->apiGet("/manage/projects/{$id}/join-requests");
    }

    public function getProjectJoinRequest($projectId, $joinRequestId)
    {
        return $this->apiGet("/manage/projects/{$projectId}/join-requests/{$joinRequestId}");
    }

    public function approveProjectJoinRequest($projectId, $joinRequestId)
    {
        $this->apiPut("/manage/projects/{$projectId}/join-requests/{$joinRequestId}", []);
    }

    public function rejectProjectJoinRequest($projectId, $joinRequestId)
    {
        $this->apiDelete("/manage/projects/{$projectId}/join-requests/{$joinRequestId}");
    }

    public function listPromoCodes($maintainerId)
    {
        return $this->apiGet("/manage/maintainers/{$maintainerId}/promo-codes");
    }

    public function createPromoCode($maintainerId, $params = []) {
        return $this->apiPost("/manage/maintainers/{$maintainerId}/promo-codes/", $params);
    }

    private function apiGet($url)
    {
        return $this->request('GET', $url);
    }

    private function apiPost($url, $data = [])
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

    private function apiPatch($url, $data)
    {
        return $this->request('PATCH', $url, [
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
            ],
        ]);

        try {
            /**
             * @var ResponseInterface $response
             */
            $response = $this->client->request($method, $url, $requestOptions);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $body = $response ? json_decode((string) $response->getBody(), true) : [];

            if ($response && $response->getStatusCode() == 503) {
                throw new MaintenanceException(isset($body['reason']) ? $body['reason'] : 'Maintenance', $response && $response->hasHeader('Retry-After') ? (string) $response->getHeader('Retry-After')[0] : null, $body);
            }

            throw new ClientException(
                $this->composeErrorMessage($e, $body),
                $response ? $response->getStatusCode() : $e->getCode(),
                $e,
                isset($body['code']) ? $body['code'] : '',
                $body
            );
        }

        if ($response->hasHeader('Content-Type') && $response->getHeader('Content-Type')[0] == 'application/json') {
            return json_decode((string) $response->getBody(), true);
        }

        return (string) $response->getBody();
    }

    private function composeErrorMessage(RequestException $requestException, ?array $body = null)
    {
        if ($body !== null && isset($body['error'])) {
            $message = $body['error'];
            if (isset($body['errors'])) {
                $message .= "\nErrors:\n";
                foreach ($body['errors'] as $error) {
                    $message .= sprintf("\"%s\": %s\n", $error['key'], $error['message']);
                }
            }
            return $message;
        } else {
            return $requestException->getMessage();
        }
    }
}
