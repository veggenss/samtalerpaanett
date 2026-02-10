<?php
// Velkommen til webSocket.php - Her ligger masse alien kode som ingen kan fortså :)

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/include/db.inc.php';

$mysqli = dbConnection();
$d = date("[Y/m/d l:H:i:s] ");

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $userConnections = [];
    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        parse_str($conn->httpRequest->getUri()->getQuery(), $query);

        if (isset($query['userId'])){
            $userId = $query['userId'];
            $conn->userId = $userId;

            if(!isset($this->userConnections[$userId])){
                $this->userConnections[$userId] = new \SplObjectStorage();
            }

            $this->userConnections[$userId]->attach($conn);

            $socketResponse = date("[Y/m/d l:H:i:s] ") . "Bruker $userId | $conn->resourceId har koblet til\n";
            echo $socketResponse;

            file_put_contents(__DIR__ . '/webSocketLog.syslog', $socketResponse, FILE_APPEND);
        }
        else {
            $socketResponse = date("[Y/m/d l:H:i:s] ") . "[$d]  Ukjent bruker koblet til $conn->resourceId\n";
            echo $socketResponse;
            file_put_contents(__DIR__ . '/webSocketLog.syslog', $socketResponse, FILE_APPEND);
        }
    }

    // direktemelding funksjonalitet
    private function directMessage($mysqli, $messageData) {
        // finner conversation Id hvor userid og recipient id matcher
        $conv_query = "SELECT id, prev_str FROM dm_conversations WHERE (user1_id = ? AND user2_id = ?) OR (user2_id = ? AND user1_id = ?)";
        $conv_stmt = $mysqli->prepare($conv_query);
        if (!$conv_stmt){
            $socketResponse = date("[Y/m/d l:H:i:s] ") . "prepare conversation query failed :( $mysqli->error \n";
            echo $socketResponse;

            file_put_contents(__DIR__ . '/WebSocket_error.log', $socketResponse, FILE_APPEND);

            return;
        }

        $conv_stmt->bind_param("iiii", $messageData['userId'], $messageData['recipientId'], $messageData['userId'], $messageData['recipientId']);
        $conv_stmt->execute();
        $conv_stmt->store_result();

        if ($conv_stmt->num_rows === 0){
            $socketResponse = date("[Y/m/d l:H:i:s] ") . "Kunne ikke finne samtale mellom " . $messageData['userId'] . " og " . $messageData['recipientId'];
            echo $socketResponse;

            file_put_contents(__DIR__ . '/WebSocket_error.log', $socketResponse, FILE_APPEND);

            return;
        }

        $conversationId = NULL;
        $prevStr = NULL;
        $conv_stmt->bind_result($conversationId, $prevStr);
        $conv_stmt->fetch();

        $msg_query = "INSERT INTO dm_messages (conversation_id, sender_id, to_user_id, message) VALUES (?, ?, ?, ?)";

        $msg_stmt = $mysqli->prepare($msg_query);
        if (!$msg_stmt) {
            $socketResponse = date("[Y/m/d l:H:i:s] ") . "message prepare failed :( $mysqli->error \n";
            echo $socketResponse;

            file_put_contents(__DIR__ . '/WebSocket_error.log', $socketResponse, FILE_APPEND);

            return;
        }

        $msg_stmt->bind_param("iiis", $conversationId, $messageData['userId'], $messageData['recipientId'], $messageData['message']);

        if (!$msg_stmt->execute()) {
            $socketResponse = date("[Y/m/d l:H:i:s] ") . "message insertion failed $mysqli->error \n";
            echo $socketResponse;
            file_put_contents(__DIR__ . '/WebSocket_error.log', $socketResponse, FILE_APPEND);
            return;
        }

        if ($prevStr !== $messageData['message']) {
            echo "Oppdaterer prev_str...";
            $prev_query = "UPDATE dm_conversations SET prev_str = ? WHERE id = ?";
            $prev_stmt = $mysqli->prepare($prev_query);
            $prev_stmt->bind_param("si", $messageData['message'], $conversationId);
            $prev_stmt->execute();
        }

        $this->sendToUser($messageData['userId'], $messageData['recipientId'], json_encode($messageData));
    }

    // sender meldinger til brukere
    private function sendToUser($userId, $recipientId, $message) {
        if (isset($this->userConnections[$userId])) {
            foreach ($this->userConnections[$userId] as $conn) {
                $conn->send($message);
            }
        }

        // sender meldingen til mottaker
        if (isset($this->userConnections[$recipientId])) {
            foreach ($this->userConnections[$recipientId] as $conn) {
                $conn->send($message);
            }
        }
    }


    // når en melding blir sendt
    public function onMessage(ConnectionInterface $fromConn, $msg) {
        $data = json_decode($msg, true);

        // hvis det ikke var noe i meldingen
        if (!$data || !isset($data['username'], $data['message'], $data['profilePictureUrl'])) {
            return;
        }

        $userId = $fromConn->userId ?? null;
        if (!$userId) {
            $socketResponse = date("[Y/m/d l:H:i:s] ") . "Bruker-ID mangler fra tilkoblingen\n";
            echo $socketResponse;

            file_put_contents(__DIR__ . '/WebSocket_error.log', $socketResponse, FILE_APPEND);

            return;
        }

        // dataen fra meldingen :)
        $messageData = [
            'recipientId' => $data['recipientId'],
            'type' => $data['type'],
            'username' => $data['username'],
            'userId' => $data['userId'],
            'profilePictureUrl' => $protocol . '://' . $hostname . '/samtalerpanett/uploads/' . basename($data['profilePictureUrl']),
            'message' => $data['message']
        ];

        // sjekker hvis du er i global chat, og logger deretter til global_chat_log.txt :D
        if ($data['type'] === 'global' && $data['recipientId'] === 'all') {
            $encodedMessage = json_encode($messageData);

            foreach ($this->clients as $clientConn) {
                $clientConn->send($encodedMessage);
            }

            file_put_contents(__DIR__ . '/global_chat/global_chat_log.txt', json_encode($messageData) . PHP_EOL, FILE_APPEND);
        }

        // hvis du ikke er i global chat, call heller på directMessage() funksjonen og pass messageData over til den
        elseif ($data['type'] === 'direct' && $data['recipientId'] !== 'all') {
            $this->directMessage(dbConnection(), $messageData);
        }
    }

    // når tilkobling til websocket blir lukket -> når en bruker disconnecter eller hvis tilkoblingen krasjer
    public function onClose(ConnectionInterface $conn) {
        foreach ($this->userConnections as $userId => $connections) {
            if ($connections->contains($conn)) {
                $connections->detach($conn);

                if (count($connections) === 0) {
                    unset($this->userConnections[$userId]);
                }

                break;
            }
        }

        $this->clients->detach($conn);
        $socketResponse = date("[Y/m/d l:H:i:s] ") . "Bruker $userId | $conn->resourceId har koblet fra\n";
        echo $socketResponse;

        file_put_contents(__DIR__ . '/webSocketLog.syslog', $socketResponse, FILE_APPEND);
    }

    // sender feilmeldinger til error log fil :D
    public function onError(ConnectionInterface $conn, \Exception $e) {
        file_put_contents(__DIR__ . '/WebSocket_error.log', date('c') . " Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);

        $conn->close();
    }
}

// lager websocket :D
$server = new App($wshostname, $wsport);
$server->route($wsroute, new Chat, ['*']);
$server->run();
