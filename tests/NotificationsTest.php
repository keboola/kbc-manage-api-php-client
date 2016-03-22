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
        $this->markTestSkipped('must be revisited.');

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $addRes = $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Project is over quota',
            'payload' => [
                'limit' => 'kbc.adminsCount'
            ]
        ]);

        $notification = $this->getNotificationById($addRes['id']);

        $this->assertArrayHasKey('id', $notification);
        $this->assertEquals('limit', $notification['type']);
        $this->assertEquals('Project is over quota', $notification['title']);
        $this->assertEquals('kbc.adminsCount', $notification['payload']['limit']);
    }

    public function testGetNotifications()
    {
        $this->markTestSkipped('must be revisited.');
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $res = $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'DEMO project outage',
            'message' => 'In 30 days this DEMO project will be deleted.'
        ]);

        // it takes a while to update child feeds
        $notification = $this->getNotificationById($res['id']);

        $this->assertEquals($res['id'], $notification['id']);
        $this->assertEquals('common', $notification['type']);
        $this->assertArrayHasKey('title', $notification);
        $this->assertArrayHasKey('message', $notification);
        $this->assertArrayHasKey('project', $notification);
        $this->assertArrayHasKey('id', $notification['project']);
        $this->assertArrayHasKey('name', $notification['project']);
        $this->assertArrayHasKey('payload', $notification);
        $this->assertArrayHasKey('created', $notification);

        $created = new \DateTime($notification['created']);
        $dateDiff = $created->diff(new \DateTime(), true);
        $secs = $dateDiff->s + $dateDiff->i*60 + $dateDiff->h*60*60;

        $this->assertLessThan(30, $secs);
    }

    public function testNotificationsForAddedAdmin()
    {
        $this->markTestSkipped('must be revisited.');
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

        $origClient = $this->client;
        $this->client = new Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);

        $notification = $this->getNotificationById($addRes['id']);

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

        $this->client = $origClient;
    }

    public function testNotificationsMarkRead()
    {
        $this->markTestSkipped('must be revisited.');
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $res = $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Limit is over quota',
            'payload' => [
                'limit' => 'kbc.storageSize'
            ]
        ]);

        $notification = $this->getNotificationById($res['id']);

        $this->assertFalse($notification['isRead']);

        $this->client->markReadNotifications([
            $notification['id']
        ]);

        $notification = $this->getNotificationById($res['id']);
        $this->assertTrue($notification['isRead']);
    }

    public function testMarkAllNotificationsAsRead()
    {
        $this->markTestSkipped('must be revisited.');
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $notification1 = $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Limit is over quota',
            'payload' => [
                'limit' => 'kbc.storageSize'
            ]
        ]);

        $notification2 = $this->client->addNotification([
            'type' => 'limit',
            'projectId' => $project['id'],
            'title' => 'Limit is over quota',
            'payload' => [
                'limit' => 'kbc.storageSize'
            ]
        ]);

        // wait for notifications
        $notification1 = $this->getNotificationById($notification1['id']);
        $notification2 = $this->getNotificationById($notification2['id']);

        $this->assertFalse($notification1['isRead']);
        $this->assertFalse($notification2['isRead']);

        $this->client->markAllNotificationsAsRead();

        $notifications = $this->client->getNotifications();
        $this->assertNotEmpty($notifications);

        foreach ($notifications as $notification) {
            $this->assertTrue($notification['isRead']);
        }


    }

    public function testNotificationsAdminRemovedFromProject()
    {
        $this->markTestSkipped('must be revisited.');
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

        $notification = $this->getNotificationById($res1['id']);

        $this->assertEquals($res1['id'], $notification['id']);

        $user = $origClient->getUser($adminEmail);
        $origClient->removeUserFromProject($project['id'], $user['id']);

        $msg2 = 'anotherAdminTestMessage' . microtime();
        $res2 = $origClient->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'anotherAdminTest',
            'message' => $msg2
        ]);

        try {
            $this->getNotificationById($res2['id']);
        } catch (\Exception $e) {
        }

        $response = $this->client->getNotifications();
        $notificationsFromProject = array_filter($response, function ($item) use ($project) {
            return isset($item['project']) && $item['project']['id'] == $project['id'];
        });

        $this->assertCount(0, $notificationsFromProject);

        $this->client = $origClient;
    }


    public function testUserShouldNotReceiveOldNotificationsOnProjectEnter()
    {
        $this->markTestSkipped('must be revisited.');
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $msg1 = 'notificationBeforeAdminEnters' . microtime();
        $notification = $this->client->addNotification([
            'type' => 'common',
            'projectId' => $project['id'],
            'title' => 'notificationBeforeAdminEnters',
            'message' => $msg1
        ]);

        // ensure that current admin which is member of project will receive notification
        $this->getNotificationById($notification['id']);

        // add new user to project
        $adminEmail = getenv('KBC_TEST_ADMIN_EMAIL');
        $this->client->addUserToProject($project['id'], [
            'email' => $adminEmail
        ]);

        $newAdminClient = new Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);

        $notifications = $newAdminClient->getNotifications();

        $received = array_filter($notifications, function($iteratedNotification) use($notification) {
           return $iteratedNotification['id'] === $notification['id'];
        });
        $this->assertCount(0, $received, 'New project admin should not receive old notifications');
    }

    private function getNotificationById($id)
    {
        $i=0;
        do {
            $response = $this->client->getNotifications();
            foreach ($response as $r) {

                if ($id == $r['id']) {
                    return $r;
                }
            }
            sleep(pow(2, $i));
            $i++;
        } while ($i < 5);

        throw new \Exception("Unable to find notification {$id}.");
    }
}