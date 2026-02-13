<?php
session_start();
require '../include/db.inc.php';
require 'DmService.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? NULL;
$mysqli = dbConnection();

$dmService = new DmService($mysqli);

switch($action){

    case 'getUserId':
        $response = $dmService->getUserId($_GET['reciverUser']);
        echo json_encode($response);
        exit;

    case 'createConversation':
        $response = $dmService->createConversation($data['user1_id'], $data['user2_id']);
        echo json_encode($response);
        exit;

    case 'loadConversationDiv':
        $response = $dmService->loadConversationDiv($data);
        echo json_encode($response);
        exit;

    case 'loadConversationLog':
        $response = $dmService->loadConversationLog($data);
        echo json_encode($response);
        exit;

    default:
        echo json_encode("No action");
        exit;
}