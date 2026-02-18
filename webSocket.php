<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App;
use Ratchet\WebSocket\WsConnection;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/include/db.inc.php';
require __DIR__ . '/Service/DmService.php';
require __DIR__ . '/Service/GlobalChatService.php';

$mysqli = dbConnection();
$socketParams = socketParams();

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $userConnections = [];
    private GlobalChatService $globalChatService;
    private DmService $dmService;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
        $this->dmService = new DmService(dbConnection());
        $this->globalChatService = new GlobalChatService(dbConnection());
    }

    private function date():string{
        return date("[Y/m/d l:H:i:s]");
    }

    public function onOpen(ConnectionInterface $conn):void{

        /** @var WsConnection $conn */
        $query = [];
        parse_str($conn->httpRequest->getUri()->getQuery(), $query);

        $userId = isset($query['userId']) ? (int)$query['userId'] : null;
        $this->clients[$conn] = ['userId' => $userId];
        $resourceId = spl_object_id($conn);

        if ($userId !== null){

            if(!isset($this->userConnections[$userId])){
                $this->userConnections[$userId] = new \SplObjectStorage();
            }

            $this->userConnections[$userId][$conn] = true;

            echo "{$this->date()} ID-{$userId}({$resourceId}) er tilkoblet\n";
            file_put_contents(__DIR__ . '/webSocketLog.syslog', "{$this->date()} ID-{$userId}({$resourceId}) er tilkoblet\n", FILE_APPEND);
        }
        else {
            echo "{$this->date()} Ukjent bruker koblet til {$resourceId}\n";
            file_put_contents(__DIR__ . '/webSocketLog.syslog', "{$this->date()} Ukjent bruker koblet til {$resourceId}\n", FILE_APPEND);
        }
    }

    // direktemelding funksjonalitet
    // @param array $messageData JSON payload
    private function directMessage(mysqli $mysqli, array $messageData):void{
        // finner conversation Id hvor userid og recipient id matcher
        $conv_query = "SELECT id, prev_str FROM dm_conversations WHERE (user1_id = ? AND user2_id = ?) OR (user2_id = ? AND user1_id = ?)";
        $conv_stmt = $mysqli->prepare($conv_query);
        if (!$conv_stmt){
            $socketResponse = "{$this->date()} prepare conversation query failed :( {$mysqli->error}\n";
            echo $socketResponse;

            file_put_contents(__DIR__ . '/WebSocket_error.log', $socketResponse, FILE_APPEND);

            return;
        }

        $conv_stmt->bind_param("iiii", $messageData['userId'], $messageData['recipientId'], $messageData['userId'], $messageData['recipientId']);
        $conv_stmt->execute();
        $conv_stmt->store_result();

        if ($conv_stmt->num_rows === 0){
            echo "{$this->date()} Kunne ikke finne samtale mellom {$messageData['userId']} og {$messageData['recipientId']}";
            file_put_contents(__DIR__ . '/WebSocket_error.log', "{$this->date()} Kunne ikke finne samtale mellom {$messageData['userId']} og {$messageData['recipientId']}", FILE_APPEND);
            return;
        }

        $conversationId = NULL;
        $prevStr = NULL;
        $conv_stmt->bind_result($conversationId, $prevStr);
        $conv_stmt->fetch();

        $msg_query = "INSERT INTO dm_messages (conversation_id, sender_id, to_user_id, message) VALUES (?, ?, ?, ?)";

        $msg_stmt = $mysqli->prepare($msg_query);
        if (!$msg_stmt) {
            echo "{$this->date()} Message prepare failed :( {$mysqli->error}\n";
            file_put_contents(__DIR__ . '/WebSocket_error.log', "{$this->date()} Message prepare failed :( {$mysqli->error}\n", FILE_APPEND);
            return;
        }

        $msg_stmt->bind_param("iiis", $conversationId, $messageData['userId'], $messageData['recipientId'], $messageData['message']);

        if (!$msg_stmt->execute()) {
            echo "{$this->date()} Message insertion failed {$mysqli->error}\n";
            file_put_contents(__DIR__ . '/WebSocket_error.log', "{$this->date()} Message insertion failed {$mysqli->error}\n", FILE_APPEND);
            return;
        }

        if ($prevStr !== $messageData['message']) {
            $prev_query = "UPDATE dm_conversations SET prev_str = ? WHERE id = ?";
            $prev_stmt = $mysqli->prepare($prev_query);
            $prev_stmt->bind_param("si", $messageData['message'], $conversationId);
            $prev_stmt->execute();
        }

        $this->sendToUser($messageData['userId'], $messageData['recipientId'], json_encode($messageData));
    }

    // sender meldinger til brukere
    //@param array $message JSON
    private function sendToUser(int $userId, int $recipientId, string $message):void{
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
    public function onMessage(ConnectionInterface $fromConn, $msg):void{
        $data = json_decode($msg, true);

        // hvis det ikke var noe i meldingen
        if (!$data || !isset($data['username'], $data['message'], $data['profilePictureUrl'])) {
            return;
        }

        $userId = $this->clients[$fromConn]['userId'] ?? null;
        if (!$userId) {
            echo "{$this->date()} Bruker-ID mangler fra tilkoblingen\n";
            file_put_contents(__DIR__ . '/WebSocket_error.log', "{$this->date()} Bruker-ID mangler fra tilkoblingen\n", FILE_APPEND);
            return;
        }

        // dataen fra meldingen
        $messageData = [
            'recipientId' => $data['recipientId'],
            'type' => $data['type'],
            'username' => $data['username'],
            'userId' => $data['userId'],
            'profilePictureUrl' => $data['profilePictureUrl'],
            'message' => $data['message']
        ];

        // sjekker hvis du er i global chat, og logger deretter til global_chat_log.txt :D
        if ($data['type'] === 'global' && $data['recipientId'] === 'all') {
            $encodedMessage = json_encode($messageData);

            $response = $this->globalChatService->pushMessage($messageData);

            if(!$response['success']){
                echo $response['message'];
            }

            foreach ($this->clients as $clientConn) {
                $clientConn->send($encodedMessage);
            }
        }

        // hvis du ikke er i global chat, call heller på directMessage() funksjonen og pass messageData over til den
        elseif ($data['type'] === 'direct' && $data['recipientId'] !== 'all') {
            $this->directMessage(dbConnection(), $messageData);
        }
    }

    // når tilkobling til websocket blir lukket -> når en bruker disconnecter eller hvis tilkoblingen krasjer
    public function onClose(ConnectionInterface $conn):void{
        $userId = $this->clients[$conn]['userId'] ?? 'unknown';
        $resourceId = spl_object_id($conn);

        foreach ($this->userConnections as $userId => $connections) {
            if (isset($connections[$conn])) {
                unset($connections[$conn]);

                if (count($connections) === 0) {
                    unset($this->userConnections[$userId]);
                }

                break;
            }
        }
        unset($this->clients[$conn]);

        echo "{$this->date()} ID-{$userId}({$resourceId}) er frakoblet\n";
        file_put_contents(__DIR__ . '/webSocketLog.syslog', "{$this->date()} ID-{$userId}({$resourceId}) er frakoblet\n", FILE_APPEND);
    }

    // sender feilmeldinger til error log fil :D
    public function onError(ConnectionInterface $conn, \Exception $e):void{
        file_put_contents(__DIR__ . '/WebSocket_error.log', $this->date() . " Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);

        $conn->close();
    }
}

// lager websocket :D
$server = new App($socketParams['hostname'], $socketParams['port']);
$server->route($socketParams['route'], new Chat, ['*']);
$server->run();
