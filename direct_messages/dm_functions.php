<?php
header('Content-Type: application/json');

require_once '../include/db.inc.php';
$mysqli = dbConnection();

//Ser om action er POST eller GET
$data = json_decode(file_get_contents("php://input"), true);
$action = $_GET['action'] ?? ($data['action'] ?? null);


if($action === 'get_user_id'){

    if($_GET['reciverUser']){
        $reciverUser = $_GET['reciverUser'];
        $UsernameToUserId = ['success' => NULL, "response" => NULL];

        $query="SELECT id FROM users WHERE username = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("s", $reciverUser);
        $stmt->execute();
        $stmt->store_result();

        if($stmt->num_rows > 0){
            $stmt->bind_result($reciverUserId);
            $stmt->fetch();
            $UsernameToUserId = ["success" => true, "response" => "Id funnet", "reciverUserId" => $reciverUserId];
            $stmt->close();
        }
        else{
            $UsernameToUserId = ["success" => false, "response" => "Kunne ikke finne id med brukernavn \"$reciverUser\" i db"];
        }
        echo json_encode($UsernameToUserId);
        return;
    }
    else{
        $UsernameToUserId = ['success' => false, "response" => "reciverUser er udefinert"];
        echo json_encode($UsernameToUserId);
        return;
    }
}

elseif($action === 'createConversation'){


        $newConversationResponse = ["success" => null, "response" => null];
        $newConversationUserData = json_decode(file_get_contents("php://input"), true);

        $user1_id = $newConversationUserData['user1_id'];
        $user2_id = $newConversationUserData['user2_id'];

        if ($user1_id > $user2_id) {
            [$user1_id, $user2_id] = [$user2_id, $user1_id];
        }

        // Sjekk om samtale allerede eksisterer
        $query = "SELECT id FROM dm_conversations WHERE user1_id = ? AND user2_id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ii", $user1_id, $user2_id);
        $stmt->execute();
        $stmt->store_result();

        if($stmt->num_rows >= 1){
            $newConversationResponse = [
                "success" => false,
                "response" => "Samtale finnes allerede mellom bruker $user1_id og $user2_id"
            ];
            $stmt->close();
            echo json_encode($newConversationResponse);
            return;
        }

        $stmt->close();

        // Opprett samtale
        $query = "INSERT INTO dm_conversations (user1_id, user2_id) VALUES (?, ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ii", $user1_id, $user2_id);
        $result = $stmt->execute();

        $result && $stmt->affected_rows === 1 ? $newConversationResponse = ["success" => true, "response" => "Opprettet samtale mellom $user1_id og $user2_id"] :$newConversationResponse = ["success" => false, "response" => "Klarte ikke å opprette samtale. Den kan allerede eksistere."];

        $stmt->close();
        echo json_encode($newConversationResponse);
        return;

}

elseif($action === 'loadConversationDiv'){
    // Laster inn aktive samtaler slik at de er listet i sidepanelet
    $loadConversationDivResponse = ["success" => null, "response" => null];
    $loadConversationDivData = json_decode(file_get_contents("php://input"), true);

    if (!isset($loadConversationDivData['user_id']) || empty($loadConversationDivData['user_id'])) {
        echo json_encode(["success" => false, "response" => "user_id er ikke definert"]);
        return;
    }

    $user_id = intval($loadConversationDivData['user_id']);

    // Funksjon for å hente brukernavn basert på ID
    function user2NameById($mysqli, $user2_id) {
        $query = "SELECT username FROM users WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $user2_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['username'] ?? 'Ukjent';
    }

    $conversations = [];

    $query = "SELECT id, user1_id, user2_id, prev_str FROM dm_conversations WHERE user1_id = ? OR user2_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $user2_id = ($row['user1_id'] == $user_id) ? $row['user2_id'] : $row['user1_id'];

        if (!$user2_id) {
            echo json_encode(["success" => false, "response" => "Kunne ikke finne gyldig mottaker-ID"]);
            return;
        }

        $user2_name = user2NameById($mysqli, $user2_id);

        // Hent profilbilde
        $icon_query = "SELECT profile_picture FROM users WHERE id = ?";
        $icon_stmt = $mysqli->prepare($icon_query);
        $icon_stmt->bind_param("i", $user2_id);
        $icon_stmt->execute();
        $icon_result = $icon_stmt->get_result();
        $icon_data = $icon_result->fetch_assoc();
        $icon_stmt->close();

        $profile_picture = $icon_data['profile_picture'] ?? 'default.png';
        $profile_picture_url = '/samtalerpanett/uploads/' . ltrim($profile_picture, '/');

        $conversations[] = [
            "conversation_id" => $row['id'],
            "recipientUsername" => $user2_name,
            "recipientId" => $user2_id,
            "recipient_profile_icon" => $profile_picture_url,
            "prevStr" => $row['prev_str']
        ];
    }

    $stmt->close();

    if (count($conversations) > 0) {
        echo json_encode([
            "success" => true,
            "response" => "Fant " . count($conversations) . " samtaler",
            "conversations" => $conversations,
        ]);
    }
    else {
        echo json_encode([
            "success" => false,
            "response" => "Ingen samtaler funnet"
        ]);
    }



}
elseif($action === 'loadConversationLog'){
    //Laster meldinger fra messages
    $loadConversationLogResponse = ["success" => NULL, "response" => NULL];
    $loadConversationLogData = json_decode(file_get_contents("php://input"), true);
    $user1_id = $loadConversationLogData['user1_id'];
    $user1_name = $loadConversationLogData['user1_name'];
    $user2_id = $loadConversationLogData['user2_id'];
    $user2_name = $loadConversationLogData['user2_name'];
    $conv_id = $loadConversationLogData['conversation_id'];

    if(!$user1_id || !$user2_id){
        $loadConversationLogResponse = ['success' => false, "response" => "user1 or user2 id are undefined"];
        echo json_encode($loadConversationLogResponse);
        return;
    }

    function getUserIcon($mysqli, $user_id){
        $icon_query = "SELECT profile_picture FROM users WHERE id = ?";
        $icon_stmt = $mysqli->prepare($icon_query);
        $icon_stmt->bind_param("i", $user_id);
        $icon_stmt->execute();
        $icon_result = $icon_stmt->get_result();
        $icon_data = $icon_result->fetch_assoc();


        $profile_picture = $icon_data['profile_picture'] ?? 'default.png';
        $profile_picture_url = '/samtalerpanett/uploads/' . ltrim($profile_picture, '/');
        return $profile_picture_url;
    }
    //Endret table navn @isakBH
    $query = "SELECT message, sender_id FROM dm_messages WHERE (sender_id = ? AND conversation_id = ?) OR (sender_id = ? AND conversation_id = ?)";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("iiii", $user1_id, $conv_id, $user2_id, $conv_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messageData = [];

    while($row = $result->fetch_assoc()){
        switch ($row['sender_id']){

            case $user1_id:
                $user1_icon = getUserIcon($mysqli, $user1_id);
                $messageData[] = [
                    "profilePictureUrl" => $user1_icon,
                    "username" => $user1_name,
                    "message" => $row['message']
                ];
                break;

            case $user2_id:
                $user2_icon = getUserIcon($mysqli, $user2_id);

                $messageData[] = [
                    "profilePictureUrl" => $user2_icon,
                    "username" => $user2_name,
                    "message" => $row['message']
                ];
                break;
        }
    }
    $stmt->close();
    echo json_encode($messageData);
}
