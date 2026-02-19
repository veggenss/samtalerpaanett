<?php
class GlobalChatService{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli){
        $this->mysqli = $mysqli;
    }

    public function getLogs():array{
        $globalLog = $this->mysqli->query("SELECT * FROM global_messages");
        if(!$globalLog){
            return ["success" => false, "message" => "Database Error"];
        }
        return ["success" => true, "globalLog" => $globalLog->fetch_all(MYSQLI_ASSOC)];
    }

    public function pushMessage(array $data):array{
        $pushStmt = $this->mysqli->prepare("INSERT INTO global_messages (sender_id, message, sender_name, sender_pfp) VALUES (?, ?, ?, ?)");
        if (!$pushStmt) return ["success" => false, "message" => "Prepare failed: " . $this->mysqli->error];

        $pushStmt->bind_param("isss", $data['userId'], $data['message'], $data['username'], $data['profilePictureUrl']);

        $result = $pushStmt->execute();
        if (!$result) return ["success" => false, "message" => "pushMessage execute failed: {$pushStmt->error}"];

        $result && $pushStmt->affected_rows === 1 ? $response = ["success" => true, "message" => "Global message inserted"] : $response = ["success" => false, "message" => "Could not execute database insert in global_messages"];
        return $response;
    }
}