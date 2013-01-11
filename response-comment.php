<?php

require_once 'vendor/autoload.php';

use Guzzle\Http\Client;

if (file_exists(__DIR__.'/config.php') === false) {
    echo 'Copy config.dist.php to config.php at first.', PHP_EOL;
    exit(1);
}

require __DIR__.'/config.php';

main($trelloKey, $trelloToken, $trelloUserId, $pandoraBotId);

/**
 * Pandora Client
 */
class PandoraClient
{
    private $botId;

    private $client;

    /**
     * @param string $botId
     */
    public function __construct($botId)
    {
        $this->botId = $botId;
        $this->client = new Client('http://www.pandorabots.com/pandora');
    }

    /**
     * Send message and return response
     * @param string $message Message to bot
     * @return string Bot's response message
     */
    public function message($message)
    {
        $request = $this->client->post('talk-xml', null, array(
            'botid' => $this->botId,
            'input' => $message,
        ));

        return $request->send()->xml()->that->__toString();
    }
}

/**
 * Trello Client
 */
class TrelloClient
{
    private $client;

    /**
     * @param string $key Trello API key
     * @param string $token Trello API access token
     * @param string $userId Trello user ID
     */
    public function __construct($key, $token, $userId)
    {
        $this->client = new Client('https://api.trello.com/1', array(
            'key'     => $key,
            'token'   => $token,
            'userId'  => $userId,
        ));
    }

    /**
     * Return trello notifications
     * @return array
     */
    public function getNotifications()
    {
        $request = $this->client->get(array(
            'members{/userId}/notifications{?key,token,read_filter,filter}',
            array(
                'read_filter' => 'unread',
                'filter'      => 'commentCard',
            )
        ));

        $response = $request->send();
        $notifications = $response->json();

        return $notifications;
    }

    /**
     * Comment to trello card
     * @param string $cardId
     * @param string $commentText
     */
    public function commentToCard($cardId, $commentText)
    {
        $request = $this->client->post(
            'cards/'.$cardId.'/actions/comments{?key,token}',
            null,
            array('text' => $commentText)
        );

        $request->send();
    }

    /**
     * Change all trello notifications as read
     */
    public function readAllNotifications()
    {
        $request = $this->client->post('notifications/all/read{?key,token}');
        $request->send();
    }
}

/**
 * Main process
 * @param string $trelloKey
 * @param string $trelloToken
 * @param string $trelloUserId
 * @param string $pandoraBotId
 */
function main($trelloKey, $trelloToken, $trelloUserId, $pandoraBotId)
{
    $trelloClient = new TrelloClient($trelloKey, $trelloToken, $trelloUserId);
    $pandoraClient = new PandoraClient($pandoraBotId);

    $notifications = $trelloClient->getNotifications();
    $notificationTotal = count($notifications);

    echo sprintf('%u notifications found.', $notificationTotal), PHP_EOL;

    if ($notificationTotal === 0) {
        echo 'There is no comment to reply. So finish to response comments.', PHP_EOL;
        exit;
    }

    foreach ($notifications as $notification) {
        $notificationId = $notification['id'];
        $commentText = $notification['data']['text'];
        $cardId = $notification['data']['card']['id'];
        echo sprintf('Notification ID: %s', $notificationId), PHP_EOL;
        echo sprintf('Comment Text: %s', $commentText), PHP_EOL;
        echo sprintf('Card ID: %s', $cardId), PHP_EOL;

        echo 'Bot is thinking response...', PHP_EOL;
        $botResponse = $pandoraClient->message($commentText);
        echo sprintf('Bot response: %s', $botResponse), PHP_EOL;

        echo 'Bot is commenting to card...', PHP_EOL;
        $trelloClient->commentToCard($cardId, $botResponse);
    }

    echo 'Bot has read all notifications...', PHP_EOL;
    $trelloClient->readAllNotifications();

    echo 'Done', PHP_EOL;
}

