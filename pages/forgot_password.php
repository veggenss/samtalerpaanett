<?php
require_once '../include/db.inc.php';
$mysqli = dbConnection();

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $email = trim($_POST['email']);
    $username = trim($_POST['username']);


    if(empty($email) || empty($username)){
        $error = "Fyll ut e-psot og brukernavn";
    }
    else{
        $sql = "SELECT * FROM users WHERE mail = ? AND username = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if(!$user){
            $error = "Kunne ikke finne bruker";
        }
    }

    if(!isset($error)){
          if($user){

            $token = bin2hex(random_bytes(16));

            $sql = "UPDATE users SET password_reset_token = ? WHERE mail = ? AND username = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("sss", $token, $email, $username);

            if($stmt->execute()){
                require 'mailer/send_reset_password_email.php';
                $config = require __DIR__ . '/mailer/config.php';
                if(sendResetPasswordMail($email, $username, $token, $config)){
                    $sent = "E-post sent til $email";
                }
                else{
                    $sent = false;
                    $error = "Kunne ikke sende e-post";
                }
            }

        }
        else{
            $error = "Ugyldig e-post eller brukernavn";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/userRegLog.css">
    <link rel="icon" href="../assets/icons/logo.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" />
    <title>Samtaler på nett | Glemt Passord</title>
</head>
<body>
<div class="auth-con">
    <h2>Glemt Passord?</h2>
    <p>Vi sender deg e-post for å tilbakestille passordet ditt</p>
    <?php if (isset($error)):?>
    <div class="error"><?php echo "{$error}<br>"; ?></div>
    <?php endif; ?>
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'])?>" method="POST">

        <div class="form-group">
            <label>Brukernavnet ditt:</label>
            <input type="text" name="username" placeholder="brukernavn" required>
        </div>

        <div class="form-group">
            <label>E-posten din:</label>
            <input type="email" name="email" placeholder="e-post" required>
        </div>

        <button type="submit" name="submit">Send e-post</button>

        <br>

        <a href="../login.php" class="return"><i class="fa-solid fa-arrow-left"></i>Tilbake</a>
    </form>
    <?php if(isset($sent)):?>
    <div class="positive"><?php echo $sent;?></div>
    <?php endif; ?>
</div>
</body>
</html>