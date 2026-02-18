<?php
class DmService{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli){
        $this->mysqli = $mysqli;
   }

   public function getUserId(string $getData):array{
       $reciverUser = $getData;

       $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE username = ?");
       $stmt->bind_param("s", $reciverUser);
       $stmt->execute();
       $stmt->store_result();

       if($stmt->num_rows > 0){
           $stmt->bind_result($reciverUserId);
           $stmt->fetch();
           $usernameToUserId = ["success" => true, "response" => "Id funnet", "reciverUserId" => $reciverUserId];
           $stmt->close();
       }
       else{
           $usernameToUserId = ["success" => false, "response" => "Kunne ikke finne id med brukernavn \"$reciverUser\" i db"];
       }
       return $usernameToUserId;
   }

   public function createConversation(int $user1_id, int $user2_id):array{
       if ($user1_id > $user2_id) {
           [$user1_id, $user2_id] = [$user2_id, $user1_id];
       }

       //sjekk om samtale allerede eksisterer
       $stmt = $this->mysqli->prepare("SELECT id FROM dm_conversations WHERE user1_id = ? AND user2_id = ?");
       $stmt->bind_param("ii", $user1_id, $user2_id);
       $stmt->execute();
       $stmt->store_result();
       if($stmt->num_rows >= 1){
           $convResponse = ["success" => false, "response" => "Samtale finnes allerede mellom bruker $user1_id og $user2_id"];
           $stmt->close();
           return $convResponse;
       }
       $stmt->close();

       //opprett samtale
       $stmt = $this->mysqli->prepare("INSERT INTO dm_conversations (user1_id, user2_id) VALUES (?, ?)");
       $stmt->bind_param("ii", $user1_id, $user2_id);
       $result = $stmt->execute();

       $result && $stmt->affected_rows === 1 ? $convResponse = ["success" => true, "response" => "Opprettet samtale mellom $user1_id og $user2_id"] : $convResponse = ["success" => false, "response" => "Denne samtalen finnes allerede"];

       $stmt->close();
       return $convResponse;
   }

   //laster inn aktive samtaler slik at de er listet i sidepanelet
   //@param array $data JSON payload
   public function loadConversationDiv(array $data):array{
       if (!isset($data['user_id'])) {
           return $convResponse = ["success" => false, "response" => "user_id er ikke definert"];
       }

       $user_id = intval($data['user_id']);

        $query = "
            SELECT c.id AS conversation_id, c.prev_str, u.id AS recipientId, u.username AS recipientUsername, u.profile_picture AS recipient_profile_icon FROM dm_conversations c
            JOIN users u ON
                u.id = CASE
                    WHEN c.user1_id = ? THEN c.user2_id
                    ELSE c.user1_id
                END
            WHERE c.user1_id = ? OR c.user2_id = ?
            ORDER BY c.id DESC";

       $conversations = [];

       $stmt = $this->mysqli->prepare($query);
       $stmt->bind_param("iii", $user_id, $user_id, $user_id);
       $stmt->execute();
       $result = $stmt->get_result();

       while ($row = $result->fetch_assoc()) {

           $profile_picture = $row['recipient_profile_icon'] ?? 'default.png';
           $profile_picture_url = '/samtalerpanett/uploads/' . ltrim($profile_picture, '/');

           $conversations[] = [
                "conversation_id" => $row['conversation_id'],
                "recipientUsername" => $row['recipientUsername'] ?? 'unknown',
                "recipientId" => $row['recipientId'],
                "recipient_profile_icon" => $profile_picture_url,
                "prevStr" => $row['prev_str']
            ];
        }

        count($conversations) > 0 ? $convResponse = ["success" => true, "response" => "Fant " . count($conversations) . " samtaler", "conversations" => $conversations] : $convResponse = ["success" => false, "response" => "Ingen samtaler funnet"];
        return $convResponse;
   }

   //@param array $data JSON payload
   public function loadConversationLog(array $data):array{
       //laster meldinger fra messages|
       if(!isset($data['user1_id'], $data['user2_id'], $data['conversation_id'])){
            $convResponse = ['success' => false, "response" => "user1 or user2 id are undefined"];
            return $convResponse;
       }

       $stmt = $this->mysqli->prepare("SELECT message, sender_id FROM dm_messages WHERE (sender_id = ? AND conversation_id = ?) OR (sender_id = ? AND conversation_id = ?)");
       $stmt->bind_param("iiii", $data['user1_id'], $data['conversation_id'], $data['user2_id'], $data['conversation_id']);
       $stmt->execute();
       $result = $stmt->get_result();

       $messageData = [];

       while($row = $result->fetch_assoc()){
           switch ($row['sender_id']){
               case $data['user1_id']:
                   $user1_icon = $this->getUserIcon($data['user1_id']);
                   $messageData[] = [
                       "profilePictureUrl" => $user1_icon,
                       "username" => $data['user1_name'],
                       "message" => $row['message'],
                       "userId" => $row['sender_id']
                   ];
                   break;

               case $data['user2_id']:
                   $user2_icon = $this->getUserIcon($data['user2_id']);

                   $messageData[] = [
                       "profilePictureUrl" => $user2_icon,
                       "username" => $data['user2_name'],
                       "message" => $row['message'],
                       "userId" => $row['sender_id']
                   ];
                   break;
           }
       }
       $stmt->close();

       $convResponse = ["success" => true, "messageData" => $messageData];
       return $convResponse;
   }

   private function getUserIcon(int $user_id):string{
       $icon_stmt = $this->mysqli->prepare("SELECT profile_picture FROM users WHERE id = ?");
       $icon_stmt->bind_param("i", $user_id);
       $icon_stmt->execute();
       $icon_result = $icon_stmt->get_result();
       $icon_data = $icon_result->fetch_assoc();
       $icon_stmt->close();

       $profile_picture = $icon_data['profile_picture'] ?? 'default.png';
       $profile_picture_url = '/samtalerpanett/uploads/' . ltrim($profile_picture, '/');
       return $profile_picture_url;
   }
}