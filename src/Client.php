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

        if (isset($config['userAgent'])) {
            $this->userAgent .= ' ' . $config['userAgent'];
        }

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
        return $this->apiPatch($this->encode('/manage/maintainers/%s', $maintainerId), $params);
    }

    public function deleteMaintainer($maintainerId)
    {
        $this->apiDelete($this->encode('/manage/maintainers/%s', $maintainerId));
    }

    public function getMaintainer($maintainerId)
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s', $maintainerId));
    }

    public function listMaintainerMembers($maintainerId)
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/users', $maintainerId));
    }

    public function addUserToMaintainer($maintainerId, $params = [])
    {
        return $this->apiPost($this->encode('/manage/maintainers/%s/users', $maintainerId), $params);
    }

    public function removeUserFromMaintainer($maintainerId, $userId)
    {
        $this->apiDelete($this->encode('/manage/maintainers/%s/users/%s', $maintainerId, $userId));
    }

    public function removeUser($userId)
    {
        $this->apiDelete($this->encode('/manage/users/%s', $userId));
    }

    public function inviteUserToMaintainer($maintainerId, $params = [])
    {
        return $this->apiPost($this->encode('manage/maintainers/%s/invitations', $maintainerId), $params);
    }

    public function listMaintainerInvitations($id)
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/invitations', $id));
    }

    public function getMaintainerInvitation($maintainerId, $invitationId)
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/invitations/%s', $maintainerId, $invitationId));
    }

    public function cancelMaintainerInvitation($maintainerId, $invitationId)
    {
        $this->apiDelete($this->encode('/manage/maintainers/%s/invitations/%s', $maintainerId, $invitationId));
    }

    public function listMyMaintainerInvitations()
    {
        return $this->apiGet('/manage/current-user/maintainers-invitations');
    }

    public function acceptMyMaintainerInvitation($id)
    {
        $this->apiPut($this->encode('/manage/current-user/maintainers-invitations/%s', $id), []);
    }

    public function getMyMaintainerInvitation($id)
    {
        return $this->apiGet($this->encode('/manage/current-user/maintainers-invitations/%s', $id));
    }

    public function declineMyMaintainerInvitation($id)
    {
        $this->apiDelete($this->encode('/manage/current-user/maintainers-invitations/%s', $id));
    }

    public function joinMaintainer($maintainerId)
    {
        $this->apiPost($this->encode('/manage/maintainers/%s/join-maintainer', $maintainerId));
    }

    public function listMaintainerOrganizations($maintainerId)
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/organizations', $maintainerId));
    }

    public function listOrganizations()
    {
        return $this->apiGet('/manage/organizations');
    }

    public function listOrganizationProjects($organizationId)
    {
        return $this->apiGet($this->encode('/manage/organizations/%s/projects', $organizationId));
    }

    public function createOrganization($maintainerId, $params)
    {
        return $this->apiPost($this->encode('/manage/maintainers/%s/organizations', $maintainerId), $params);
    }

    public function getOrganization($organizationId)
    {
        return $this->apiGet($this->encode('/manage/organizations/%s', $organizationId));
    }

    public function updateOrganization($organizationId, $params)
    {
        return $this->apiPatch($this->encode('/manage/organizations/%s', $organizationId), $params);
    }

    public function enableOrganizationMfa($organizationId)
    {
        return $this->apiPatch($this->encode('/manage/organizations/%s/force-mfa', $organizationId), []);
    }

    public function listOrganizationUsers($organizationId)
    {
        return $this->apiGet($this->encode('manage/organizations/%s/users', $organizationId));
    }

    public function listOrganizationProjectsUsers($organizationId)
    {
        return $this->apiGet($this->encode('manage/organizations/%s/projects-users', $organizationId));
    }

    public function addUserToOrganization($organizationId, $params = [])
    {
        return $this->apiPost($this->encode('manage/organizations/%s/users', $organizationId), $params);
    }

    public function removeUserFromOrganization($organizationId, $userId)
    {
        $this->apiDelete($this->encode('manage/organizations/%s/users/%s', $organizationId, $userId));
    }

    public function deleteOrganization($id)
    {
        $this->apiDelete($this->encode('/manage/organizations/%s', $id));
    }

    public function inviteUserToOrganization($organizationId, $params = [])
    {
        return $this->apiPost($this->encode('manage/organizations/%s/invitations', $organizationId), $params);
    }

    public function listOrganizationInvitations($id)
    {
        return $this->apiGet($this->encode('/manage/organizations/%s/invitations', $id));
    }

    public function getOrganizationInvitation($organizationId, $invitationId)
    {
        return $this->apiGet($this->encode('/manage/organizations/%s/invitations/%s', $organizationId, $invitationId));
    }

    public function cancelOrganizationInvitation($organizationId, $invitationId)
    {
        $this->apiDelete($this->encode('/manage/organizations/%s/invitations/%s', $organizationId, $invitationId));
    }

    public function listMyOrganizationInvitations()
    {
        return $this->apiGet('/manage/current-user/organizations-invitations');
    }

    public function acceptMyOrganizationInvitation($id)
    {
        $this->apiPut($this->encode('/manage/current-user/organizations-invitations/%s', $id), []);
    }

    public function getMyOrganizationInvitation($id)
    {
        return $this->apiGet($this->encode('/manage/current-user/organizations-invitations/%s', $id));
    }

    public function declineMyOrganizationInvitation($id)
    {
        $this->apiDelete($this->encode('/manage/current-user/organizations-invitations/%s', $id));
    }

    public function joinOrganization($organizationId)
    {
        $this->apiPost($this->encode('/manage/organizations/%s/join-organization', $organizationId));
    }

    public function createProject($organizationId, $params)
    {
        return $this->apiPost($this->encode('/manage/organizations/%s/projects', $organizationId), $params);
    }

    public function updateProject($id, $params)
    {
        return $this->apiPut($this->encode('/manage/projects/%s', $id), $params);
    }

    public function getProject($id)
    {
        return $this->apiGet($this->encode('/manage/projects/%s', $id));
    }

    public function deleteProject($id)
    {
        $this->apiDelete($this->encode('/manage/projects/%s', $id));
    }

    public function undeleteProject($id, $params = array())
    {
        $this->apiDelete($this->encode('/manage/deleted-projects/%s?', $id) . http_build_query($params));
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
        return $this->apiGet($this->encode('/manage/deleted-projects/%s', $id));
    }

    public function purgeDeletedProject($id, array $params = [])
    {
        return $this->apiPost($this->encode('/manage/deleted-projects/%s/purge', $id), $params);
    }

    public function listProjectUsers($id)
    {
        return $this->apiGet($this->encode('/manage/projects/%s/users', $id));
    }

    public function listProjectInvitations($id)
    {
        return $this->apiGet($this->encode('/manage/projects/%s/invitations', $id));
    }

    public function listMyProjectInvitations()
    {
        return $this->apiGet('/manage/current-user/projects-invitations');
    }

    public function acceptMyProjectInvitation($id)
    {
        $this->apiPut($this->encode('/manage/current-user/projects-invitations/%s', $id), []);
    }

    public function getMyProjectInvitation($id)
    {
        return $this->apiGet($this->encode('/manage/current-user/projects-invitations/%s', $id));
    }

    public function declineMyProjectInvitation($id)
    {
        $this->apiDelete($this->encode('/manage/current-user/projects-invitations/%s', $id));
    }

    public function addUserToProject($projectId, $params = [])
    {
        return $this->apiPost($this->encode('/manage/projects/%s/users', $projectId), $params);
    }

    public function inviteUserToProject($projectId, $params = [])
    {
        return $this->apiPost($this->encode('/manage/projects/%s/invitations', $projectId), $params);
    }

    public function removeUserFromProject($projectId, $userId)
    {
        $this->apiDelete($this->encode('/manage/projects/%s/users/%s', $projectId, $userId));
    }

    public function updateUserProjectMembership($projectId, $userId, $params)
    {
        return $this->apiPatch($this->encode('/manage/projects/%s/users/%s', $projectId, $userId), $params);
    }

    public function getProjectInvitation($projectId, $invitationId)
    {
        return $this->apiGet($this->encode('/manage/projects/%s/invitations/%s', $projectId, $invitationId));
    }

    public function cancelProjectInvitation($projectId, $invitationId)
    {
        $this->apiDelete($this->encode('/manage/projects/%s/invitations/%s', $projectId, $invitationId));
    }

    public function enableProject($projectId)
    {
        $this->apiPost($this->encode('/manage/projects/%s/disabled', $projectId), [
           'isDisabled' => false,
        ]);
    }

    public function disableProject($projectId, $params = [])
    {
        $this->apiPost($this->encode('/manage/projects/%s/disabled', $projectId), array_merge($params, [
            'isDisabled' => true,
        ]));
    }

    public function createProjectStorageToken($projectId, array $params)
    {
        return $this->apiPost($this->encode('/manage/projects/%s/tokens', $projectId), $params);
    }

    public function giveProjectCredits($projectId, $params = [])
    {
        return $this->apiPost($this->encode('/manage/projects/%s/credits', $projectId), $params);
    }

    public function assignProjectStorageBackend($projectId, $backendId)
    {
        return $this->apiPost($this->encode('/manage/projects/%s/storage-backend', $projectId), [
            'storageBackendId' => (int) $backendId,
        ]);
    }

    public function removeProjectStorageBackend($projectId, $backendId)
    {
        $this->apiDelete($this->encode('/manage/projects/%s/storage-backend/%s', $projectId, $backendId));
    }

    public function assignFileStorage($projectId, $storageId)
    {
        return $this->apiPost($this->encode('/manage/projects/%s/file-storage', $projectId), [
           'fileStorageId' => (int) $storageId,
        ]);
    }

    public function changeProjectOrganization($projectId, $newOrganizationId)
    {
        return $this->apiPost($this->encode('/manage/projects/%s/organizations', $projectId), [
            'organizationId' => $newOrganizationId,
        ]);
    }

    public function setProjectLimits($projectId, array $limits)
    {
        return $this->apiPost($this->encode('/manage/projects/%s/limits', $projectId), [
           'limits' => $limits,
        ]);
    }

    public function removeProjectLimit($projectId, $limitName)
    {
        $this->apiDelete($this->encode('/manage/projects/%s/limits/%s', $projectId, $limitName));
    }

    public function setProjectMetadata(int $projectId, string $provider, array $metadata): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/metadata', $projectId), [
            'provider' => $provider,
            'metadata' => $metadata,
        ]);
    }

    public function listProjectMetadata(int $projectId): array
    {
        return $this->apiGet($this->encode('/manage/projects/%s/metadata', $projectId));
    }

    public function deleteProjectMetadata(int $projectId, int $metadataId): void
    {
        $this->apiDelete($this->encode('/manage/projects/%s/metadata/%s', $projectId, $metadataId));
    }

    public function setOrganizationMetadata(int $organizationId, string $provider, array $metadata): array
    {
        return $this->apiPost($this->encode('/manage/organizations/%s/metadata', $organizationId), [
            'provider' => $provider,
            'metadata' => $metadata,
        ]);
    }

    public function listOrganizationMetadata(int $organizationId): array
    {
        return $this->apiGet($this->encode('/manage/organizations/%s/metadata', $organizationId));
    }

    public function deleteOrganizationMetadata(int $organizationId, int $metadataId): void
    {
        $this->apiDelete($this->encode('/manage/organizations/%s/metadata/%s', $organizationId, $metadataId));
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
        $this->apiDelete($this->encode('/manage/features/%s', $id));
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
        return $this->apiGet($this->encode('/manage/features/%s', $id));
    }

    public function getFeatureProjects($id)
    {
        return $this->apiGet($this->encode('/manage/features/%s/projects', $id));
    }

    public function getFeatureAdmins($id)
    {
        return $this->apiGet($this->encode('/manage/features/%s/admins', $id));
    }

    public function addProjectFeature($projectId, $feature)
    {
        return $this->apiPost($this->encode('/manage/projects/%s/features', $projectId), [
           'feature' => (string) $feature,
        ]);
    }

    public function removeProjectFeature($projectId, $feature)
    {
        $this->apiDelete($this->encode('/manage/projects/%s/features/%s', $projectId, $feature));
    }

    public function getUser($emailOrId)
    {
        return $this->apiGet($this->encode('/manage/users/%s', $emailOrId));
    }

    public function updateUser($emailOrId, $params)
    {
        return $this->apiPut($this->encode('/manage/users/%s', $emailOrId), $params);
    }

    public function addUserFeature($emailOrId, $feature)
    {
        return $this->apiPost($this->encode('/manage/users/%s/features', $emailOrId), [
            'feature' => $feature,
        ]);
    }

    public function removeUserFeature($emailOrId, $feature)
    {
        $this->apiDelete($this->encode('/manage/users/%s/features/%s', $emailOrId, $feature));
    }

    public function getProjectTemplate($templateStringId)
    {
        return $this->apiGet($this->encode('/manage/project-templates/%s', $templateStringId));
    }

    public function getProjectTemplates()
    {
        return $this->apiGet('/manage/project-templates');
    }

    public function getProjectTemplateFeatures($templateStringId)
    {
        return $this->apiGet($this->encode('/manage/project-templates/%s/features', $templateStringId));
    }

    public function addProjectTemplateFeature($templateStringId, $featureName)
    {
        return $this->apiPost($this->encode('/manage/project-templates/%s/features', $templateStringId), [
            'feature' => $featureName,
        ]);
    }

    public function createProjectFromPromoCode($promoCode)
    {
        return $this->apiPost('/manage/current-user/promo-codes', [
            'code' => $promoCode,
        ]);
    }

    public function listUsedPromoCodes()
    {
        return $this->apiGet('/manage/current-user/promo-codes');
    }

    public function removeProjectTemplateFeature($templateStringId, $featureName)
    {
        $this->apiDelete($this->encode('/manage/project-templates/%s/features/%s', $templateStringId, $featureName));
    }

    public function disableUserMFA($emailOrId)
    {
        $this->apiDelete($this->encode('/manage/users/%s/mfa', $emailOrId));
    }

    public function createS3FileStorage(array $options)
    {
        return $this->apiPost('/manage/file-storage-s3/', $options);
    }

    public function setS3FileStorageAsDefault($fileStorageId)
    {
        return $this->apiPost($this->encode('/manage/file-storage-s3/%s/default', $fileStorageId));
    }

    public function listS3FileStorage()
    {
        return $this->apiGet('manage/file-storage-s3');
    }

    public function rotateS3FileStorageCredentials(int $fileStorageId, array $options)
    {
        return $this->apiPost($this->encode('manage/file-storage-s3/%s/credentials', $fileStorageId), $options);
    }

    public function createAbsFileStorage(array $options)
    {
        return $this->apiPost('/manage/file-storage-abs/', $options);
    }

    public function setAbsFileStorageAsDefault($fileStorageId)
    {
        return $this->apiPost($this->encode('/manage/file-storage-abs/%s/default', $fileStorageId));
    }

    public function listAbsFileStorage()
    {
        return $this->apiGet('manage/file-storage-abs/');
    }


    public function rotateAbsFileStorageCredentials(int $fileStorageId, array $options)
    {
        return $this->apiPost($this->encode('manage/file-storage-abs/%s/credentials', $fileStorageId), $options);
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

    public function registerUiApp(array $options)
    {
        return $this->apiPost('manage/ui-apps', $options);
    }

    public function deleteUiApp($name)
    {
        $this->apiDelete($this->encode('manage/ui-apps/%s', $name));
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
        return $this->apiGet($this->encode('/manage/current-user/projects-join-requests/%s', $id));
    }

    public function requestAccessToProject($projectId, $params = [])
    {
        return $this->apiPost($this->encode('/manage/projects/%s/request-access', $projectId), $params);
    }

    public function joinProject($projectId)
    {
        $this->apiPost($this->encode('/manage/projects/%s/join-project', $projectId));
    }

    public function deleteMyProjectJoinRequest($id)
    {
        $this->apiDelete($this->encode('/manage/current-user/projects-join-requests/%s', $id));
    }

    public function approveMyProjectJoinRequest($id)
    {
        $this->apiPut($this->encode('/manage/current-user/projects-join-requests/%s', $id), []);
    }

    public function listProjectJoinRequests($id)
    {
        return $this->apiGet($this->encode('/manage/projects/%s/join-requests', $id));
    }

    public function getProjectJoinRequest($projectId, $joinRequestId)
    {
        return $this->apiGet($this->encode('/manage/projects/%s/join-requests/%s', $projectId, $joinRequestId));
    }

    public function approveProjectJoinRequest($projectId, $joinRequestId)
    {
        $this->apiPut($this->encode('/manage/projects/%s/join-requests/%s', $projectId, $joinRequestId), []);
    }

    public function rejectProjectJoinRequest($projectId, $joinRequestId)
    {
        $this->apiDelete($this->encode('/manage/projects/%s/join-requests/%s', $projectId, $joinRequestId));
    }

    public function listPromoCodes($maintainerId)
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/promo-codes', $maintainerId));
    }

    public function createPromoCode($maintainerId, $params = [])
    {
        return $this->apiPost($this->encode('/manage/maintainers/%s/promo-codes/', $maintainerId), $params);
    }

    /**
     * Encode url parameters with `urlencode`. Use `printf` syntax for variables in url (%s, %d, ...).
     */
    private function encode(string $url, ...$params): string
    {
        foreach ($params as &$param) {
            $param = rawurlencode($param);
        }
        return vsprintf($url, $params);
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
