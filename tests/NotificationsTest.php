<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 02/02/16
 * Time: 11:21
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

class NotificationsTest extends ClientTestCase
{
    public function testCreateNotification()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $response = $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'limit' => 'kbc.adminsCount'
        ]);

        $this->assertEquals('limit', $response['type']);
        $this->assertEquals($project['id'], $response['data']['projectId']);
        $this->assertEquals('kbc.adminsCount', $response['data']['limit']);
    }

    public function testGetNotifications()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'DEMO project outage',
            'message' => 'In 30 days this DEMO project will be deleted.'
        ]);

        $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Limit is over quota',
            'limit' => 'kbc.storageSize'
        ]);

        $this->client->addNotification([
            'type' => 'global',
            'title' => 'Maintenance announcement',
            'message' => 'There will be maintenance at some point in future'
        ]);

        // it takes a while while the children feeds get updated
        sleep(3);

        $response = $this->client->getNotifications();
        $notification = array_shift($response);

        $this->assertArrayHasKey('type', $notification);
        $this->assertArrayHasKey('created', $notification);
        $this->assertArrayHasKey('isRead', $notification);
        $this->assertArrayHasKey('title', $notification);
        $this->assertArrayHasKey('message', $notification);
        $this->assertEquals('global', $notification['type']);

        $notification2 = array_shift($response);
        $this->assertEquals('limit', $notification2['type']);
        $this->assertArrayHasKey('project', $notification2);
        $this->assertArrayHasKey('id', $notification2['project']);
        $this->assertArrayHasKey('name', $notification2['project']);

        $notification3 = array_shift($response);
        $this->assertEquals('common', $notification3['type']);
        $this->assertArrayHasKey('title', $notification);
        $this->assertArrayHasKey('message', $notification);
        $this->assertArrayHasKey('project', $notification2);
        $this->assertArrayHasKey('id', $notification2['project']);
        $this->assertArrayHasKey('name', $notification2['project']);
    }

    public function testNotificationsForAddedAdmin()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addUserToProject($project['id'], [
            'email' => getenv('KBC_TEST_ADMIN_EMAIL')
        ]);

        $msg = 'anotherAdminTestMessage' . microtime();

        $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'anotherAdminTest',
            'message' => $msg
        ]);

        $client2 = new Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);

        $response = $client2->getNotifications();

        $notification = array_shift($response);

        $this->assertArrayHasKey('id', $notification);
        $this->assertArrayHasKey('type', $notification);
        $this->assertArrayHasKey('created', $notification);
        $this->assertArrayHasKey('project', $notification);
        $this->assertArrayHasKey('id', $notification['project']);
        $this->assertArrayHasKey('name', $notification['project']);
        $this->assertArrayHasKey('isRead', $notification);

        $this->assertArrayHasKey('title', $notification);
        $this->assertArrayHasKey('message', $notification);

        $this->assertEquals($msg, $notification['message']);
        $this->assertEquals($project['id'], $notification['project']['id']);
    }

    public function testNotificationsMarkRead()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Limit is over quota',
            'limit' => 'kbc.adminsCount'
        ]);

        $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Limit is over quota',
            'limit' => 'kbc.storageSize'
        ]);

        $response = $this->client->getNotifications();
        $notification1 = array_shift($response);
        $notification2 = array_shift($response);
        $this->assertFalse($notification1['isRead']);
        $this->assertFalse($notification2['isRead']);

        $this->client->markReadNotifications([
            $notification1['id'],
            $notification2['id']
        ]);

        $response = $this->client->getNotifications();
        $notification1 = array_shift($response);
        $notification2 = array_shift($response);
        $this->assertTrue($notification1['isRead']);
        $this->assertTrue($notification2['isRead']);
    }

    public function testNotificationsAdminRemovedFromProject()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $adminEmail = getenv('KBC_TEST_ADMIN_EMAIL');
        $this->client->addUserToProject($project['id'], [
            'email' => $adminEmail
        ]);

        $msg1 = 'anotherAdminTestMessage' . microtime();
        $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'anotherAdminTest',
            'message' => $msg1
        ]);

        $client2 = new Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);

        sleep(5);

        $response = $client2->getNotifications();

        $notificationsFromProject = array_filter($response, function ($item) use ($project) {
            return isset($item['project']) && $item['project']['id'] == $project['id'];
        });

        $this->assertCount(1, $notificationsFromProject);

        $user = $this->client->getUser($adminEmail);
        $this->client->removeUserFromProject($project['id'], $user['id']);

        $msg2 = 'anotherAdminTestMessage' . microtime();
        $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'anotherAdminTest',
            'message' => $msg2
        ]);

        sleep(5);

        $response = $client2->getNotifications();
        $notificationsFromProject = array_filter($response, function ($item) use ($project) {
            return isset($item['project']) && $item['project']['id'] == $project['id'];
        });

        $this->assertCount(0, $notificationsFromProject);
    }
}