<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class UsersTest extends ClientTestCase
{

    public function testGetUser()
    {
        $token = $this->client->verifyToken();
        $userEmail = $token['user']['email'];
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($userId, $user['id']);
        $initialFeaturesCount = count($user['features']);

        $feature = 'manage-feature-test-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($feature, 'admin', $feature);
        $this->client->addUserFeature($userEmail, $feature);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($initialFeaturesCount + 1, count($user['features']));
        $this->assertContains($feature, $user['features']);

        $feature2 = 'manage-feature-test-2-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($feature2, 'admin', $feature2);
        $this->client->addUserFeature($userId, $feature2);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($initialFeaturesCount + 2, count($user['features']));
        $this->assertContains($feature, $user['features']);

        $this->client->removeUserFeature($userId, $feature);
        $this->client->removeUserFeature($userId, $feature2);

        $user = $this->client->getUser($userId);
        $this->assertEquals($initialFeaturesCount, count($user['features']));
    }

    public function testGetNonexistentUser()
    {
        try {
            $this->client->getUser('nonexistent.user@keboola.com');
            $this->fail('nonexistent.user@keboola.com not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testAddNonexistentFeature()
    {
        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $featureName = 'random-feature-' . $this->getRandomFeatureSuffix();

        try {
            $this->client->addUserFeature($token['user']['id'], $featureName);
            $this->fail('Feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testAddUserFeatureTwice()
    {
        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $initialFeaturesCount = count($user['features']);

        $newFeature = 'new-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($newFeature, 'admin', $newFeature);
        $this->client->addUserFeature($userId, $newFeature);

        $user = $this->client->getUser($userId);

        $this->assertSame($initialFeaturesCount + 1, count($user['features']));

        try {
            $this->client->addUserFeature($userId, $newFeature);
            $this->fail('Feature already added');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        $user = $this->client->getUser($userId);

        $this->assertSame($initialFeaturesCount + 1, count($user['features']));
    }

    public function testUpdateUser()
    {
        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $oldUserName = $user['name'];
        $newUserName = 'Rename ' . date('y-m-d H:i:s');

        $updatedUser = $this->client->updateUser($userId, ['name' => $newUserName]);

        $this->assertNotEquals($oldUserName, $updatedUser['name']);
        $this->assertEquals($newUserName, $updatedUser['name']);
    }

    public function testDisableUserMFA()
    {
        $token = $this->normalUserClient->verifyToken();
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $this->assertArrayHasKey('mfaEnabled', $user);
        $this->assertFalse($user['mfaEnabled']);

        try {
            $this->client->disableUserMFA($userId);
            $this->fail('you cannot disable mfa for user having mfa disabled');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    public function testNormalUserShouldNotBeAbleDisableMFA()
    {
        $token = $this->normalUserClient->verifyToken();
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userId);

        $this->assertArrayHasKey('mfaEnabled', $user);
        $this->assertFalse($user['mfaEnabled']);

        try {
            $this->normalUserClient->disableUserMFA($userId);
            $this->fail('normal user should not be able to enable mfa via thea api');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testRemoveUserFromDeletedStructures()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, ['name' => 'RemoveMeOrg']);
        $project = $this->client->createProject($organization['id'], [
            'name' => 'RemoveMeProj',
            'dataRetentionTimeInDays' => 1,
        ]);
        $maintainer = $this->client->createMaintainer(['name' => 'RemoveMeMain']);
        $email = 'devel-tests+remove' . uniqid() . '@keboola.com';
        $this->client->addUserToProject($project['id'], ['email' => $email]);
        $user = $this->client->getUser($email);
        $this->client->addUserToMaintainer($maintainer['id'], ['email' => $email]);
        $this->client->addUserToOrganization($organization['id'], ['email' => $email]);
        $this->client->deleteProject($project['id']);
        $this->client->deleteMaintainer($maintainer['id']);
        $this->client->deleteOrganization($organization['id']);

        $this->client->removeUser($user['id']);

        $deletedUser = $this->client->getUser($user['id']);
        $this->assertSame('DELETED', $deletedUser['email'], 'User e-mail has not been deleted');
    }

    public function testRemoveUserFromEverywhere()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, ['name' => 'ToRemoveOrg-1']);
        $inviteOrganization = $this->client->createOrganization($this->testMaintainerId, ['name' => 'ToRemoveOrg-2']);
        $project = $this->client->createProject($organization['id'], [
            'name' => 'ToRemoveProj-1',
            'dataRetentionTimeInDays' => 1,
        ]);
        $email = 'devel-tests+remove' . uniqid() . '@keboola.com';
        //PROJECT, ORGANIZATION & MAINTAINER
        $this->client->addUserToProject($project['id'], ['email' => $email]);
        $user = $this->client->getUser($email);
        $this->client->addUserToOrganization($organization['id'], ['email' => $user['email']]);
        $this->client->inviteUserToOrganization($inviteOrganization['id'], ['email' => $user['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $user['email']]);
        //INVITATION
        $inviteProject = $this->client->createProject($organization['id'], [
            'name' => 'ToRemoveProj-2',
            'dataRetentionTimeInDays' => 1,
        ]);
        $this->client->inviteUserToProject($inviteProject['id'], ['email' => $email]);

        $this->client->removeUser($email);

        $usersInProject = $this->client->listProjectUsers($project['id']);
        foreach ($usersInProject as $userInProject) {
            if ($userInProject['id'] === $user['id']) {
                $this->fail('User has not been deleted from project');
            }
        }

        $usersInOrganization = $this->client->listOrganizationUsers($organization['id']);
        foreach ($usersInOrganization as $userInOrganization) {
            if ($userInOrganization['id'] === $user['id']) {
                $this->fail('User has not been deleted from organization');
            }
        }

        $usersInMaintainer = $this->client->listMaintainerMembers($this->testMaintainerId);
        foreach ($usersInMaintainer as $userInMaintainer) {
            if ($userInMaintainer['id'] === $user['id']) {
                $this->fail('User has not been deleted from maintainer');
            }
        }

        $usersProjectInvitations = $this->client->listProjectInvitations($inviteProject['id']);
        foreach ($usersProjectInvitations as $invitation) {
            if ($invitation['user']['id'] === $user['id']) {
                $this->fail('User\'s project invitation has not been deleted');
            }
        }

        $usersOrganizationInvitations = $this->client->listOrganizationInvitations($inviteOrganization['id']);
        foreach ($usersOrganizationInvitations as $invitation) {
            if ($invitation['user']['id'] === $user['id']) {
                $this->fail('User\'s organization invitation has not been deleted');
            }
        }

        $deletedUser = $this->client->getUser($user['id']);

        $this->assertSame('DELETED', $deletedUser['email'], 'User e-mail has not been deleted');
        $this->assertSame(false, $deletedUser['mfaEnabled'], 'User mfa has not been disabled');
        $this->assertSame('DELETED', $deletedUser['name'], 'User name has not been deleted');
    }

    public function testRemoveUserFromEverywhereFailsWhenLastUserInOrg()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, ['name' => 'ToRemoveOrg-1']);
        $project = $this->client->createProject($organization['id'], [
            'name' => 'ToRemoveProj-1',
            'dataRetentionTimeInDays' => 1,
        ]);
        $email = 'devel-tests+remove' . uniqid() . '@keboola.com';
        //PROJECT, ORGANIZATION & MAINTAINER
        $this->client->addUserToProject($project['id'], ['email' => $email]);
        $this->client->addUserToOrganization($organization['id'], ['email' => $email]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $email]);
        //INVITATION
        $inviteProject = $this->client->createProject($organization['id'], [
            'name' => 'ToRemoveProj-2',
            'dataRetentionTimeInDays' => 1,
        ]);
        $this->client->inviteUserToProject($inviteProject['id'], ['email' => $email]);

        // Ensure superadmin is not in org
        $this->client->removeUserFromOrganization($organization['id'], $this->superAdmin['id']);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot remove "%s" from "%s". Organization must have at least 1 member',
            $email,
            $organization['id']
        ));
        $this->client->removeUser($email);
    }

    public function testRemoveNonExistingUser()
    {
        $email = 'non-existing' . uniqid() . '@non-existing-keboola.com';
        $this->expectExceptionMessage("Admin $email not found.");
        $this->expectException(ClientException::class);
        $this->client->removeUser($email);
    }
}
