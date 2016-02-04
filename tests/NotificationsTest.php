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
            'title' => 'Project is over quota',
            'limit' => 'kbc.adminsCount'
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('limit', $response['type']);
        $this->assertEquals('Project is over quota', $response['title']);
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

        $res1 = $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'DEMO project outage',
            'message' => 'In 30 days this DEMO project will be deleted.'
        ]);

        $res2 = $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Project is over quota',
            'limit' => 'kbc.storageSize'
        ]);

        $res3 = $this->client->addNotification([
            'type' => 'global',
            'title' => 'Maintenance announcement',
            'message' => 'There will be maintenance at some point in future'
        ]);

        // it takes a while to update child feeds
        $response = $this->getNotificationsFromId($res3['id']);

        $notification = array_shift($response);
        $this->assertEquals($res3['id'], $notification['id']);
        $this->assertEquals('global', $notification['type']);
        $this->assertArrayHasKey('type', $notification);
        $this->assertArrayHasKey('created', $notification);
        $this->assertArrayHasKey('isRead', $notification);
        $this->assertArrayHasKey('title', $notification);
        $this->assertArrayHasKey('message', $notification);

        $notification = array_shift($response);
        $this->assertEquals($res2['id'], $notification['id']);
        $this->assertEquals('limit', $notification['type']);
        $this->assertArrayHasKey('project', $notification);
        $this->assertArrayHasKey('id', $notification['project']);
        $this->assertArrayHasKey('name', $notification['project']);

        $notification = array_shift($response);
        $this->assertEquals($res1['id'], $notification['id']);
        $this->assertEquals('common', $notification['type']);
        $this->assertArrayHasKey('title', $notification);
        $this->assertArrayHasKey('message', $notification);
        $this->assertArrayHasKey('project', $notification);
        $this->assertArrayHasKey('id', $notification['project']);
        $this->assertArrayHasKey('name', $notification['project']);
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

        $addRes = $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'anotherAdminTest',
            'message' => $msg
        ]);

        $this->client = new Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);

        $response = $this->getNotificationsFromId($addRes['id']);

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

        $res2 = $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Limit is over quota',
            'limit' => 'kbc.storageSize'
        ]);

        $response = $this->getNotificationsFromId($res2['id']);
        $notification2 = array_shift($response);
        $notification1 = array_shift($response);
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
        $res1 = $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'anotherAdminTest',
            'message' => $msg1
        ]);

        $origClient = $this->client;
        $this->client = new Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);

        $response = $this->getNotificationsFromId($res1['id']);

        $notificationsFromProject = array_filter($response, function ($item) use ($project) {
            return isset($item['project']) && $item['project']['id'] == $project['id'];
        });

        $this->assertCount(1, $notificationsFromProject);

        $user = $origClient->getUser($adminEmail);
        $origClient->removeUserFromProject($project['id'], $user['id']);

        $msg2 = 'anotherAdminTestMessage' . microtime();
        $origClient->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'anotherAdminTest',
            'message' => $msg2
        ]);

        sleep(5);
        $response = $this->client->getNotifications();
        $notificationsFromProject = array_filter($response, function ($item) use ($project) {
            return isset($item['project']) && $item['project']['id'] == $project['id'];
        });

        $this->assertCount(0, $notificationsFromProject);
    }

    private function getNotificationsFromId($id)
    {
        $i=0;
        do {
            $response = $this->client->getNotifications();
            if ($id == $response[0]['id']) {
                return $response;
            }
            sleep(1);
            $i++;
        } while ($i < 10);

        throw new \Exception("Unable to find notification {$id}.");
    }
}