<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client
{
    private string $apiUrl;

    private string $tokenString = '';

    private int $backoffMaxTries = 10;

    private string $userAgent = 'Keboola Manage API PHP Client';

    /**
     * @var GuzzleClient
     */
    private GuzzleClient $client;

    /**
     * Clients accept an array of constructor parameters.
     *
     * Client configuration settings include the following options:
     *  - url - API URL
     *  - token - Keboola Manage api token
     *  - backoffMaxTries - backoff maximum retries count
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        if (!isset($config['token'])) {
            throw new InvalidArgumentException('token must be set');
        }
        $this->tokenString = $config['token'];

        if (isset($config['userAgent'])) {
            $this->userAgent .= ' ' . $config['userAgent'];
        }

        if (!isset($config['url'])) {
            throw new InvalidArgumentException('url must be set');
        }
        $this->apiUrl = $config['url'];

        if (isset($config['backoffMaxTries'])) {
            $this->backoffMaxTries = (int) $config['backoffMaxTries'];
        }
        $this->initClient();
    }

    private function initClient(): void
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            $this->createDefaultDecider($this->backoffMaxTries),
            $this->createExponentialDelay(),
        ));

        $this->client = new GuzzleClient([
            'base_uri' => $this->apiUrl,
            'handler' => $handlerStack,
        ]);
    }

    private function createDefaultDecider(int $maxRetries = 3): Closure
    {
        return function ($retries, RequestInterface $request, ?ResponseInterface $response = null, $error = null) use ($maxRetries): bool {
            if ($retries >= $maxRetries) {
                return false;
            }
            if ($response && $response->getStatusCode() > 499) {
                return true;
            }
            return (bool) $error;
        };
    }

    private function createExponentialDelay(): Closure
    {
        return fn($retries): int => (int) 2 ** ($retries - 1) * 1000;
    }

    /**
     * @return array{
     *     id: int,
     *     description: string,
     *     created: string,
     *     lastUsed: string|null,
     *     expires: string|null,
     *     isSessionToken: bool,
     *     isExpired: bool,
     *     isDisabled: bool,
     *     scopes: list<string>,
     *     type: string,
     *     creator: array{
     *         id: int|string,
     *         name: string
     *     },
     *     user?: array{
     *         id: int,
     *         name: string,
     *         email: string,
     *         mfaEnabled: bool,
     *         features: list<string>,
     *         canAccessLogs: bool,
     *         isSuperAdmin: bool
     *     }
     * }
     */
    public function verifyToken(): array
    {
        return $this->apiGet('/manage/tokens/verify');
    }

    /**
     * @return array<string, mixed>
     */
    public function createSessionToken(): array
    {
        return $this->apiPost('/manage/current-user/session-token');
    }

    /**
     * @return array<string, mixed>
     */
    public function listMaintainers(): array
    {
        return $this->apiGet('/manage/maintainers');
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createMaintainer(array $params): array
    {
        return $this->apiPost('/manage/maintainers', $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateMaintainer(int $maintainerId, array $params = []): array
    {
        return $this->apiPatch($this->encode('/manage/maintainers/%s', $maintainerId), $params);
    }

    public function deleteMaintainer(int $maintainerId): void
    {
        $this->apiDelete($this->encode('/manage/maintainers/%s', $maintainerId));
    }

    /**
     * @return array<string, mixed>
     */
    public function getMaintainer(int $maintainerId): array
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s', $maintainerId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listMaintainerMembers(int $maintainerId): array
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/users', $maintainerId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function addUserToMaintainer(int $maintainerId, array $params = []): array
    {
        return $this->apiPost($this->encode('/manage/maintainers/%s/users', $maintainerId), $params);
    }

    public function removeUserFromMaintainer(int $maintainerId, int $userId): void
    {
        $this->apiDelete($this->encode('/manage/maintainers/%s/users/%s', $maintainerId, $userId));
    }

    public function removeUser(int $userId): void
    {
        $this->apiDelete($this->encode('/manage/users/%s', $userId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function inviteUserToMaintainer(int $maintainerId, array $params = []): array
    {
        return $this->apiPost($this->encode('manage/maintainers/%s/invitations', $maintainerId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function listMaintainerInvitations(int $id): array
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/invitations', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function getMaintainerInvitation(int $maintainerId, int $invitationId): array
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/invitations/%s', $maintainerId, $invitationId));
    }

    public function cancelMaintainerInvitation(int $maintainerId, int $invitationId): void
    {
        $this->apiDelete($this->encode('/manage/maintainers/%s/invitations/%s', $maintainerId, $invitationId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listMyMaintainerInvitations(): array
    {
        return $this->apiGet('/manage/current-user/maintainers-invitations');
    }

    public function acceptMyMaintainerInvitation(int $id): void
    {
        $this->apiPut($this->encode('/manage/current-user/maintainers-invitations/%s', $id), []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMyMaintainerInvitation(int $id): array
    {
        return $this->apiGet($this->encode('/manage/current-user/maintainers-invitations/%s', $id));
    }

    public function declineMyMaintainerInvitation(int $id): void
    {
        $this->apiDelete($this->encode('/manage/current-user/maintainers-invitations/%s', $id));
    }

    public function joinMaintainer(int $maintainerId): void
    {
        $this->apiPost($this->encode('/manage/maintainers/%s/join-maintainer', $maintainerId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listMaintainerOrganizations(int|string $maintainerId): array
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/organizations', $maintainerId));
    }

    public function setMaintainerMetadata(int $maintainerId, string $provider, array $metadata): array
    {
        return $this->apiPost($this->encode('/manage/maintainers/%s/metadata', $maintainerId), [
            'provider' => $provider,
            'metadata' => $metadata,
        ]);
    }

    public function listMaintainerMetadata(int $maintainerId): array
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/metadata', $maintainerId));
    }

    public function deleteMaintainerMetadata(int $maintainerId, int $metadataId): void
    {
        $this->apiDelete($this->encode('/manage/maintainers/%s/metadata/%s', $maintainerId, $metadataId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listOrganizations(): array
    {
        return $this->apiGet('/manage/organizations');
    }

    /**
     * @return array<string, mixed>
     */
    public function listOrganizationProjects(int $organizationId): array
    {
        return $this->apiGet($this->encode('/manage/organizations/%s/projects', $organizationId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createOrganization(int $maintainerId, array $params): array
    {
        return $this->apiPost($this->encode('/manage/maintainers/%s/organizations', $maintainerId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrganization(int $organizationId): array
    {
        return $this->apiGet($this->encode('/manage/organizations/%s', $organizationId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateOrganization(int $organizationId, array $params): array
    {
        return $this->apiPatch($this->encode('/manage/organizations/%s', $organizationId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function enableOrganizationMfa(int $organizationId): array
    {
        return $this->apiPatch($this->encode('/manage/organizations/%s/force-mfa', $organizationId), []);
    }

    /**
     * @return array<string, mixed>
     */
    public function listOrganizationUsers(int $organizationId): array
    {
        return $this->apiGet($this->encode('manage/organizations/%s/users', $organizationId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listOrganizationProjectsUsers(int $organizationId): array
    {
        return $this->apiGet($this->encode('manage/organizations/%s/projects-users', $organizationId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function addUserToOrganization(int $organizationId, array $params = []): array
    {
        return $this->apiPost($this->encode('manage/organizations/%s/users', $organizationId), $params);
    }

    public function removeUserFromOrganization(int $organizationId, int $userId): void
    {
        $this->apiDelete($this->encode('manage/organizations/%s/users/%s', $organizationId, $userId));
    }

    public function deleteOrganization(int $id): void
    {
        $this->apiDelete($this->encode('/manage/organizations/%s', $id));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function inviteUserToOrganization(int $organizationId, array $params = []): array
    {
        return $this->apiPost($this->encode('manage/organizations/%s/invitations', $organizationId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function listOrganizationInvitations(int $id): array
    {
        return $this->apiGet($this->encode('/manage/organizations/%s/invitations', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrganizationInvitation(int $organizationId, int $invitationId): array
    {
        return $this->apiGet($this->encode('/manage/organizations/%s/invitations/%s', $organizationId, $invitationId));
    }

    public function cancelOrganizationInvitation(int $organizationId, int $invitationId): void
    {
        $this->apiDelete($this->encode('/manage/organizations/%s/invitations/%s', $organizationId, $invitationId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listMyOrganizationInvitations(): array
    {
        return $this->apiGet('/manage/current-user/organizations-invitations');
    }

    public function acceptMyOrganizationInvitation(int $id): void
    {
        $this->apiPut($this->encode('/manage/current-user/organizations-invitations/%s', $id), []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMyOrganizationInvitation(int $id): array
    {
        return $this->apiGet($this->encode('/manage/current-user/organizations-invitations/%s', $id));
    }

    public function declineMyOrganizationInvitation(int $id): void
    {
        $this->apiDelete($this->encode('/manage/current-user/organizations-invitations/%s', $id));
    }

    public function joinOrganization(int $organizationId): void
    {
        $this->apiPost($this->encode('/manage/organizations/%s/join-organization', $organizationId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createProject(int $organizationId, array $params): array
    {
        return $this->apiPost($this->encode('/manage/organizations/%s/projects', $organizationId), $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateProject(int $id, array $params): array
    {
        return $this->apiPut($this->encode('/manage/projects/%s', $id), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProject(int $id): array
    {
        return $this->apiGet($this->encode('/manage/projects/%s', $id));
    }

    public function deleteProject(int $id): void
    {
        $this->apiDelete($this->encode('/manage/projects/%s', $id));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function undeleteProject(int $id, array $params = []): void
    {
        $this->apiDelete($this->encode('/manage/deleted-projects/%s?', $id) . http_build_query($params));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function listDeletedProjects(array $params = []): array
    {
        $defaultParams = [
            'limit' => 100,
            'offset' => 0,
        ];

        $queryParams = array_merge($defaultParams, $params);
        return $this->apiGet('/manage/deleted-projects?' . http_build_query($queryParams));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeletedProject(int $id): array
    {
        return $this->apiGet($this->encode('/manage/deleted-projects/%s', $id));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function purgeDeletedProject(int $id, array $params = []): array
    {
        return $this->apiPost($this->encode('/manage/deleted-projects/%s/purge', $id), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function listProjectUsers(int $id): array
    {
        return $this->apiGet($this->encode('/manage/projects/%s/users', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function listProjectInvitations(int $id): array
    {
        return $this->apiGet($this->encode('/manage/projects/%s/invitations', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function listMyProjectInvitations(): array
    {
        return $this->apiGet('/manage/current-user/projects-invitations');
    }

    public function acceptMyProjectInvitation(int $id): void
    {
        $this->apiPut($this->encode('/manage/current-user/projects-invitations/%s', $id), []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMyProjectInvitation(int $id): array
    {
        return $this->apiGet($this->encode('/manage/current-user/projects-invitations/%s', $id));
    }

    public function declineMyProjectInvitation(int $id): void
    {
        $this->apiDelete($this->encode('/manage/current-user/projects-invitations/%s', $id));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function addUserToProject(int $projectId, array $params = []): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/users', $projectId), $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function inviteUserToProject(int $projectId, array $params = []): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/invitations', $projectId), $params);
    }

    public function removeUserFromProject(int $projectId, int $userId): void
    {
        $this->apiDelete($this->encode('/manage/projects/%s/users/%s', $projectId, $userId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateUserProjectMembership(int $projectId, int $userId, array $params): array
    {
        return $this->apiPatch($this->encode('/manage/projects/%s/users/%s', $projectId, $userId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectInvitation(int $projectId, int $invitationId): array
    {
        return $this->apiGet($this->encode('/manage/projects/%s/invitations/%s', $projectId, $invitationId));
    }

    public function cancelProjectInvitation(int $projectId, int $invitationId): void
    {
        $this->apiDelete($this->encode('/manage/projects/%s/invitations/%s', $projectId, $invitationId));
    }

    public function enableProject(int $projectId): void
    {
        $this->apiPost($this->encode('/manage/projects/%s/disabled', $projectId), [
           'isDisabled' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function disableProject(int $projectId, array $params = []): void
    {
        $this->apiPost($this->encode('/manage/projects/%s/disabled', $projectId), array_merge($params, [
            'isDisabled' => true,
        ]));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createProjectStorageToken(int $projectId, array $params): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/tokens', $projectId), $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function giveProjectCredits(int $projectId, array $params = []): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/credits', $projectId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function assignProjectStorageBackend(int $projectId, int $backendId): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/storage-backend', $projectId), [
            'storageBackendId' => (int) $backendId,
        ]);
    }

    public function removeProjectStorageBackend(int $projectId, int $backendId): void
    {
        $this->apiDelete($this->encode('/manage/projects/%s/storage-backend/%s', $projectId, $backendId));
    }

    /**
     * @return array<string, mixed>
     */
    public function assignFileStorage(int $projectId, int $storageId): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/file-storage', $projectId), [
           'fileStorageId' => (int) $storageId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function changeProjectOrganization(int $projectId, int $newOrganizationId): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/organizations', $projectId), [
            'organizationId' => $newOrganizationId,
        ]);
    }

    /**
     * @param array<string, mixed> $limits
     * @return array<string, mixed>
     */
    public function setProjectLimits(int $projectId, array $limits): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/limits', $projectId), [
           'limits' => $limits,
        ]);
    }

    public function removeProjectLimit(int $projectId, string $limitName): void
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

    /**
     * @return array<string, mixed>
     */
    public function createFeature(
        string $name,
        string $type,
        string $title,
        string $description,
        ?bool $canBeManageByAdmin = false,
        ?bool $canBeManagedViaAPI = true,
    ): array {
        return $this->apiPost('/manage/features', [
            'name' => $name,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'canBeManageByAdmin' => $canBeManageByAdmin,
            'canBeManagedViaAPI' => $canBeManagedViaAPI,
        ]);
    }

    /**
     * @param array<string, mixed>|null $options
     * @return array<string, mixed>
     */
    public function updateFeature(int $id, ?array $options = []): array
    {
        return $this->apiPatch($this->encode('/manage/features/%s', $id), $options);
    }

    public function removeFeature(int|string $id): void
    {
        $this->apiDelete($this->encode('/manage/features/%s', $id));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function listFeatures(array $params = []): array
    {
        $url = '/manage/features';
        if (isset($params['type'])) {
            $url .= '?' . http_build_query(['type' => $params['type']]);
        }
        return $this->apiGet($url);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeature(int $id): array
    {
        return $this->apiGet($this->encode('/manage/features/%s', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeatureProjects(int $id): array
    {
        return $this->apiGet($this->encode('/manage/features/%s/projects', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeatureAdmins(int $id): array
    {
        return $this->apiGet($this->encode('/manage/features/%s/admins', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function addProjectFeature(int $projectId, string $feature): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/features', $projectId), [
           'feature' => (string) $feature,
        ]);
    }

    public function removeProjectFeature(int $projectId, string $feature): void
    {
        $this->apiDelete($this->encode('/manage/projects/%s/features/%s', $projectId, $feature));
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(int|string $emailOrId): array
    {
        return $this->apiGet($this->encode('/manage/users/%s', $emailOrId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateUser(int|string $emailOrId, array $params): array
    {
        return $this->apiPut($this->encode('/manage/users/%s', $emailOrId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function addUserFeature(int|string $emailOrId, string $feature): array
    {
        return $this->apiPost($this->encode('/manage/users/%s/features', $emailOrId), [
            'feature' => $feature,
        ]);
    }

    public function removeUserFeature(int|string $emailOrId, string $feature): void
    {
        $this->apiDelete($this->encode('/manage/users/%s/features/%s', $emailOrId, $feature));
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function setUserMetadata(int|string $emailOrId, string $provider, array $metadata): array
    {
        return $this->apiPost($this->encode('/manage/users/%s/metadata', $emailOrId), [
            'provider' => $provider,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listUserMetadata(int|string $emailOrId): array
    {
        return $this->apiGet($this->encode('/manage/users/%s/metadata', $emailOrId));
    }

    public function deleteUserMetadata(int|string $emailOrId, int $metadataId): void
    {
        $this->apiDelete($this->encode('/manage/users/%s/metadata/%s', $emailOrId, $metadataId));
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectTemplate(string $templateStringId): array
    {
        return $this->apiGet($this->encode('/manage/project-templates/%s', $templateStringId));
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectTemplates(): array
    {
        return $this->apiGet('/manage/project-templates');
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectTemplateFeatures(string $templateStringId): array
    {
        return $this->apiGet($this->encode('/manage/project-templates/%s/features', $templateStringId));
    }

    /**
     * @return array<string, mixed>
     */
    public function addProjectTemplateFeature(string $templateStringId, string $featureName): array
    {
        return $this->apiPost($this->encode('/manage/project-templates/%s/features', $templateStringId), [
            'feature' => $featureName,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createProjectFromPromoCode(string $promoCode): array
    {
        return $this->apiPost('/manage/current-user/promo-codes', [
            'code' => $promoCode,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listUsedPromoCodes(): array
    {
        return $this->apiGet('/manage/current-user/promo-codes');
    }

    public function removeProjectTemplateFeature(string $templateStringId, string $featureName): void
    {
        $this->apiDelete($this->encode('/manage/project-templates/%s/features/%s', $templateStringId, $featureName));
    }

    public function disableUserMFA(int|string $emailOrId): void
    {
        $this->apiDelete($this->encode('/manage/users/%s/mfa', $emailOrId));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createS3FileStorage(array $options): array
    {
        return $this->apiPost('/manage/file-storage-s3/', $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function setS3FileStorageAsDefault(int $fileStorageId): array
    {
        return $this->apiPost($this->encode('/manage/file-storage-s3/%s/default', $fileStorageId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listS3FileStorage(): array
    {
        return $this->apiGet('manage/file-storage-s3');
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function rotateS3FileStorageCredentials(int $fileStorageId, array $options): array
    {
        return $this->apiPost($this->encode('manage/file-storage-s3/%s/credentials', $fileStorageId), $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createAbsFileStorage(array $options): array
    {
        return $this->apiPost('/manage/file-storage-abs/', $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function setAbsFileStorageAsDefault(int $fileStorageId): array
    {
        return $this->apiPost($this->encode('/manage/file-storage-abs/%s/default', $fileStorageId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listAbsFileStorage(): array
    {
        return $this->apiGet('manage/file-storage-abs/');
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function rotateAbsFileStorageCredentials(int $fileStorageId, array $options): array
    {
        return $this->apiPost($this->encode('manage/file-storage-abs/%s/credentials', $fileStorageId), $options);
    }

    /**
     * @deprecated Use createSnowflakeStorageBackend or createStorageBackendBigquery
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createStorageBackend(array $options): array
    {
        return $this->apiPost('/manage/storage-backend', $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function updateStorageBackend(int $storageBackendId, array $options): array
    {
        return $this->apiPatch($this->encode('/manage/storage-backend/%s', $storageBackendId), $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createStorageBackendBigquery(array $options): array
    {
        return $this->apiPost('/manage/storage-backend/bigquery', $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function updateStorageBackendBigquery(int $storageBackendId, array $options): array
    {
        return $this->apiPatch($this->encode('/manage/storage-backend/%s/bigquery', $storageBackendId), $options);
    }

    public function removeStorageBackend(int $storageBackendId): void
    {
        $this->apiDelete($this->encode('/manage/storage-backend/%s', $storageBackendId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listStorageBackend(): array
    {
        return $this->apiGet('manage/storage-backend');
    }

    public function getStorageBackend(int $id): array
    {
        return $this->apiGet(sprintf('manage/storage-backend/%s', $id));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createSnowflakeStorageBackend(array $options): array
    {
        return $this->apiPost('/manage/storage-backend/snowflake', $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function activateStorageBackend(int $backendId): array
    {
        return $this->apiPost($this->encode('/manage/storage-backend/%s/activate', $backendId), []);
    }

    /**
     * @return array<string, mixed>
     */
    public function listUiApps(): array
    {
        return $this->apiGet('manage/ui-apps');
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function registerUiApp(array $options): array
    {
        return $this->apiPost('manage/ui-apps', $options);
    }

    public function deleteUiApp(string $name): void
    {
        $this->apiDelete($this->encode('manage/ui-apps/%s', $name));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function runCommand(array $options): array
    {
        return $this->apiPost('/manage/commands', $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function listMyProjectJoinRequests(): array
    {
        return $this->apiGet('/manage/current-user/projects-join-requests');
    }

    /**
     * @return array<string, mixed>
     */
    public function getMyProjectJoinRequest(int $id): array
    {
        return $this->apiGet($this->encode('/manage/current-user/projects-join-requests/%s', $id));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function requestAccessToProject(int $projectId, array $params = []): array
    {
        return $this->apiPost($this->encode('/manage/projects/%s/request-access', $projectId), $params);
    }

    public function joinProject(int $projectId): void
    {
        $this->apiPost($this->encode('/manage/projects/%s/join-project', $projectId));
    }

    public function deleteMyProjectJoinRequest(int $id): void
    {
        $this->apiDelete($this->encode('/manage/current-user/projects-join-requests/%s', $id));
    }

    public function approveMyProjectJoinRequest(int $id): void
    {
        $this->apiPut($this->encode('/manage/current-user/projects-join-requests/%s', $id), []);
    }

    /**
     * @return array<string, mixed>
     */
    public function listProjectJoinRequests(int $id): array
    {
        return $this->apiGet($this->encode('/manage/projects/%s/join-requests', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectJoinRequest(int $projectId, int $joinRequestId): array
    {
        return $this->apiGet($this->encode('/manage/projects/%s/join-requests/%s', $projectId, $joinRequestId));
    }

    public function approveProjectJoinRequest(int $projectId, int $joinRequestId): void
    {
        $this->apiPut($this->encode('/manage/projects/%s/join-requests/%s', $projectId, $joinRequestId), []);
    }

    public function rejectProjectJoinRequest(int $projectId, int $joinRequestId): void
    {
        $this->apiDelete($this->encode('/manage/projects/%s/join-requests/%s', $projectId, $joinRequestId));
    }

    /**
     * @return array<string, mixed>
     */
    public function listPromoCodes(int $maintainerId): array
    {
        return $this->apiGet($this->encode('/manage/maintainers/%s/promo-codes', $maintainerId));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createPromoCode(int $maintainerId, array $params = []): array
    {
        return $this->apiPost($this->encode('/manage/maintainers/%s/promo-codes/', $maintainerId), $params);
    }

    /**
     * Encode url parameters with `urlencode`. Use `printf` syntax for variables in url (%s, %d, ...).
     */
    private function encode(string $url, ...$params): string
    {
        foreach ($params as &$param) {
            $param = rawurlencode((string) $param);
        }
        return vsprintf($url, $params);
    }

    private function apiGet(string $url): mixed
    {
        return $this->request('GET', $url);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apiPost(string $url, array $data = []): mixed
    {
        return $this->request('POST', $url, [
            'json' => $data,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apiPut(string $url, array $data): mixed
    {
        return $this->request('PUT', $url, [
            'json' => $data,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apiPatch(string $url, array $data): mixed
    {
        return $this->request('PATCH', $url, [
            'json' => $data,
        ]);
    }

    private function apiDelete(string $url): void
    {
        $this->request('DELETE', $url);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $url, array $options = []): mixed
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
            $body = $response instanceof ResponseInterface ? json_decode((string) $response->getBody(), true) : [];

            if ($response && $response->getStatusCode() === 503) {
                throw new MaintenanceException($body['reason'] ?? 'Maintenance', $response && $response->hasHeader('Retry-After') ? (string) $response->getHeader('Retry-After')[0] : null, $body);
            }

            throw new ClientException(
                $this->composeErrorMessage($e, $body),
                $response instanceof ResponseInterface ? $response->getStatusCode() : $e->getCode(),
                $e,
                $body['code'] ?? '',
                $body,
            );
        }

        if ($response->hasHeader('Content-Type') && $response->getHeader('Content-Type')[0] === 'application/json') {
            return json_decode((string) $response->getBody(), true);
        }

        return (string) $response->getBody();
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function composeErrorMessage(RequestException $requestException, ?array $body = null): string
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
        }
        return $requestException->getMessage();
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createGcsFileStorage(array $options): array
    {
        return $this->apiPost('/manage/file-storage-gcs/', $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function rotateGcsFileStorageCredentials(int $fileStorageId, array $options): array
    {
        return $this->apiPost($this->encode('manage/file-storage-gcs/%s/credentials', $fileStorageId), $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function listGcsFileStorage(): array
    {
        return $this->apiGet('manage/file-storage-gcs/');
    }

    /**
     * @return array<string, mixed>
     */
    public function setGcsFileStorageAsDefault(int $fileStorageId): array
    {
        return $this->apiPost($this->encode('manage/file-storage-gcs/%s/default', $fileStorageId));
    }
}
