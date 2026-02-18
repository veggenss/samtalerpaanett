<?php
require '../include/db.inc.php';
require '../Service/GlobalChatService.php';

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? NULL;
$mysqli = dbConnection();

$globalChatService = new GlobalChatService($mysqli);

switch($action){
    case 'getLogs':
        echo json_encode($globalChatService->getLogs());
        exit;

    case 'pushMessage':
        echo json_encode($globalChatService->pushMessage($action['message']));
        exit;

    default:
        echo json_encode(["success" => false, "message" => "No Action"]);
        exit;
}