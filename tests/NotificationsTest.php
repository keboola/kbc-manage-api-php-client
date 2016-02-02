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
        $this->assertEquals($project['id'], $response['projectId']);
        $this->assertEquals('kbc.adminsCount', $response['object']);
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

        $notification = array_shift($response['result']);

        $this->assertArrayHasKey('type', $notification);
        $this->assertArrayHasKey('created', $notification);
        $this->assertArrayHasKey('project', $notification);
        $this->assertArrayHasKey('id', $notification['project']);
        $this->assertArrayHasKey('name', $notification['project']);
        $this->assertArrayHasKey('isRead', $notification);
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

        $notification = array_shift($response['result']);

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

}