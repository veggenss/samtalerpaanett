<?php
require_once 'include/db.inc.php';
$mysqli = dbConnection();

$message = null;
$error = null;
if(isset($_GET['token'])){
    $token = $_GET['token'];

    $sql = "SELECT * FROM users WHERE email_verification_token = ? AND email_verified = 0";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $sql = "UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE email_verification_token = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $message = "<p>E-posten din er nå bekreftet! <br><br>Du kan lukke denne fanen</p>";
    }
    else{
        $error = "Ugyldig eller utløpt verifikasjonslink! <br><br><a href='register.php'>Registrer deg her</a>";
    }
}
else{
    header("Location: register.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/logo.ico" />
    <link rel="stylesheet" href="css/userRegLog.css">
    <title>Samtaler på nett | Verifiser Email</title>
</head>
<body>
    <div class="mail-con">
        <h2>E-post Verifisering...</h2><br>
        <?php if($message){echo "<div class='positive'>$message</div>";}?>
        <?php if($error){echo "<div class='error'>$error</div>";}?>
    </div>
</body>
</html>


