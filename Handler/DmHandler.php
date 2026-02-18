<?php
session_start();
require '../include/db.inc.php';
require '../Service/DmService.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? NULL;
$mysqli = dbConnection();

$dmService = new DmService($mysqli);

switch($action){

    case 'getUserId':
        echo json_encode($dmService->getUserId($_GET['reciverUser']));
        exit;

    case 'createConversation':
        echo json_encode($dmService->createConversation($data['user1_id'], $data['user2_id']));
        exit;

    case 'loadConversationDiv':
        echo json_encode($dmService->loadConversationDiv($data));
        exit;

    case 'loadConversationLog':
        echo json_encode($dmService->loadConversationLog($data));
        exit;

    default:
        echo json_encode(["success" => false, "message" => "No action"]);
        exit;
}