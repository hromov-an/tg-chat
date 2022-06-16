<?php
namespace Anton\Tg;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TelegramBot
{
    protected $token = '5513289885:AAFH3xnb0nuMHB-RIhuoTaPBuX1mky--2V4';
    protected $lastUpdateId;
    protected int $bannedTime = 60*20;

    public function startChat()
    {
        $updates = $this->getUpdates();
        foreach ($updates as $item) {
            if($this->lastUpdateId < $item->update_id) {
                $this->saveNewMessage($item);

                $message = $item->message->text;
                if ($message == '/start') {
                    $message = 'Привет новый пользователь '.$item->message->chat->first_name;
                    $this->sendMessage($item->message->from->id, $message);
                } else {
                    $message = 'Привет '.$item->message->chat->first_name . ' сообщение отправлено ' . $item->message->text;
                }
                $this->sendMessage($item->message->from->id, $message);
                
                if(!$this->checkMessage($item->message->text)) {
                    $this->banUser($item->message->from->id, time() + $this->bannedTime);
                    $message = "Бан $this->bannedTime сек";
                    $this->sendMessage($item->message->from->id, $message);
                } else {
                    $this->sendAll($item->message->from->id, $item->message->text);
                }
            }

        }
    }


    /**
     * @throws GuzzleException
     */
    protected function query($method, $params = [])
    {
        $baseUrl = 'https://api.telegram.org/bot';
        $url = $baseUrl . $this->token . '/' . $method;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $client = new Client([
            'base_uri' => $url
        ]);
        $request = $client->request('GET');
        return json_decode($request->getBody());
    }





    protected function getUpdates()
    {
        $db = (new DB())->getConnection();
        $this->lastUpdateId = $db->query('SELECT max(tg_update_id) FROM message')->fetchColumn();

        $response = $this->query('getUpdates', ['offset' => $this->lastUpdateId]);

        if(!empty($response)) {
            if (!empty($users = $this->newUsers($response->result))) {
                $this->saveNewUser($users);
            }
        }
        return $response->result;
    }

    protected function newUsers($response)
    {
        $usersForCreate = [];
        if (!empty($response)) {
            $db = (new DB())->getConnection();
            $stmt = $db->query('SELECT * FROM user');
            $oldUsers = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $oldUsers[$row['tg_user_id']] = [$row['user_name'], $row['tg_update_id']];
            }
            foreach ($response as $item) {
                if (!array_key_exists($item->message->chat->id, $oldUsers)) {
                    $usersForCreate[$item->message->chat->id] = [
                        'username' => property_exists($item->message->chat, 'username') ?? $item->message->chat->username,
                        'first_name' => $item->message->chat->first_name,
                        'tg_user_id' => $item->message->chat->id,
                        'update_id' => $item->update_id
                    ];
                }
            }
        }
        return $usersForCreate;
    }

    protected function saveNewUser($users)
    {
        $db = (new DB())->getConnection();
        $stmt = $db->prepare('INSERT INTO user (user_name, first_name, tg_user_id, tg_update_id) VALUES (:userName, :firstName, :tgUserId, :updateId)');

        foreach ($users as $id=>$user) {
            $stmt->bindParam(':userName', $user['username']);
            $stmt->bindParam(':firstName', $user['first_name']);
            $stmt->bindParam(':tgUserId', $user['tg_user_id']);
            $stmt->bindParam(':updateId', $user['update_id']);
            $stmt->execute();
        }
    }
    protected function saveNewMessage($message)
    {
        $db = (new DB())->getConnection();
        $stmt = $db->prepare('INSERT INTO message (message, tg_update_id, tg_message_id, tg_user_id) VALUES (:message, :updateId, :tgMessageId, :userId)');

        $stmt->bindParam(':message', $message->message->text);
        $stmt->bindParam(':updateId', $message->update_id);
        $stmt->bindParam(':tgMessageId', $message->message->message_id);
        $stmt->bindParam(':userId', $message->message->from->id);
        $stmt->execute();

    }

    /**
     * Регулярки себя не оправдали, нормально фильтровать можно только по спискам слов
     * http://orfo.info/poisk.php?sm=%E5%E1%EB
     * http://orfo.info/poisk.php?s=%E1%EB%FF
     * @param string $message
     * @return boolean
     */
    protected function checkMessage(string $message): bool
    {
        $badWords = [
            'хуй',
            'сука',
            'мудак'
        ];
        $wordArray = explode(' ', $message);
        foreach ($wordArray as $word) {
            if(in_array($word, $badWords)) {
                return false;
            }
        }
        return true;
    }

    protected function checkUnBannedUser($userId, $banTime)
    {
        if (time() > $banTime) {
            $this->unBanUser($userId);
            return true;
        }
        return false;
    }


    protected function banUser($userId, $time)
    {
        $db = (new DB())->getConnection();
        $stmt = $db->prepare('UPDATE user SET banned_time=:bannedTime WHERE tg_user_id=:userId');
        $stmt->bindParam(':userId', $userId);
        $stmt->bindParam(':bannedTime', $time);
        $stmt->execute();
    }

    protected function unBanUser($userId)
    {
        $this->banUser($userId, null);
    }


    protected function sendMessage($chatId, $message)
    {
        $params = [
            'text' => $message,
            'chat_id' => $chatId
        ];
        $response = $this->query('sendMessage', $params);
        return $response->result;
    }

    protected function sendAll($senderId, $message) {
        $db = (new DB())->getConnection();
        $sql = $db->prepare('SELECT * FROM `user`');
        $sql->execute();
        $res = $sql->fetchAll();
        foreach ($res as $row) {
            if ($row['tg_user_id'] == $senderId) {
                if ($row['banned_time']) {
                    if (!$this->checkUnBannedUser($senderId, $row['banned_time'])) {
                        $time = time() - $row['banned_time'];
                        $this->sendMessage($row['tg_user_id'], 'Вы забанены, осталось '. $time . 'сек');
                    }
                }
            } else {
                $this->sendMessage($row['tg_user_id'], $message);
            }
        }
    }
}