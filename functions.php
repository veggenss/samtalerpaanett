<?php
require_once 'include/db.inc.php';
$mysqli = dbConnection();
$socketParams = socketParams();

if(!isset($_SESSION['user_id'])){
    return;
}
else {
    $user_id = $_SESSION['user_id'];

    // sjekker om tokens i databasen er satt, så blir de logget ut hvis de er
    $sql = "SELECT email_verification_token, email_verified, password_reset_token FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // sjekker om epost er verifisert
    if ($user['email_verification_token'] !== NULL || (int) $user['email_verified'] !== 1) {
        setcookie("not_verified", "Du må verifisere eposten din!", time() + 10, "/");
    }
    // finner ut om brukeren har sendt en passord reset mail, hvis de ikke har satt nytt passord blir de kastet ut
    elseif ($user['password_reset_token'] !== NULL) {
        setcookie("password_token_set", "Du må stille nytt passord \nSjekk epost inboksen din", time() + 10, "/");
    }
}