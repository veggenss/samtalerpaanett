<?php
function dbConnection()
{
    $db_server = "localhost";
    $db_user = "dbUsername";
    $db_pass = "dbUserPass";
    $db_name = "dbName";
    $conn = "";

    $conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    return $conn;
}

$protocol = 'http';
$hostname = 'localhost';
$port = 8080;