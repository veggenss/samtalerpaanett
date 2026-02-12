<?php
require_once 'include/db.inc.php';
$mysqli = dbConnection();

$verified = null;
$message = null;
$error = null;
$redirect = null;
$invalid = null;

if(isset($_GET['token'])){
    $token = $_GET['token'];

    $sql = "SELECT password_reset_token FROM users WHERE password_reset_token = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $verified = true;
    }
    else{
        $invalid = "Ugyldig eller utløpt token! <br><br><a href='login.php'>logg inn her</a>";
    }
}
else{
    header("Location: login.php");
}

if($verified && $_SERVER['REQUEST_METHOD'] == 'POST'){

    $new_password = trim($_POST['password']);
    $r_new_password = trim($_POST['r-password']);

    if($new_password !== $r_new_password){
        $error = "De nye passordene er ikke like";
    }
    // ser om passorder er bra nok, fordi alle samtaler på nett brukere har bare de beste passordene!
    elseif(!preg_match('/^.{5,}/', $new_password)){
        $error = "Passorder må være 5 siffer eller mer";
    }
    elseif(!preg_match('/(?=.*\w)(?=.*\d)/', $new_password)){
        $error = "Passordet må ha minst 1 tegn og 1 tall";
    }
    elseif(preg_match('/[ ]/', $new_password)){
        $error = "Passordet kan ikke ha mellomrom";
    }

    if(!isset($error)){
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ?, password_reset_token = NULL WHERE password_reset_token = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ss", $hashed_password, $token);

        if($stmt->execute()){
            $success = true;
            $message = "Passord er oppdatert :) <br>Du må logge in igjen";
            $redirect = true;
        }
        else{
            $error = "Kunne ikke oppdatere passord :(";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/userRegLog.css">
    <link rel="icon" href="assets/icons/logo.ico">
    <title>Samtaler på nett | Tilbakestill Passord</title>
</head>
<body>
    <div class="auth-con">
        <h2>Passord Tilbakestilling...</h2><br>
        <?php if($error){echo "<div class='error'>$error</div>";}?>
        <?php if($invalid){echo "<div class='error'>$invalid</div>";}?>
        <?php if(isset($verified)):?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?token=' . urldecode($token))?>" method="POST">

            <div class="form-group">
                <label>Nytt passord:</label>
                <input type="password" name="password" placeholder="passord" required>
            </div>

            <div class="form-group">
                <label>Gjenta nye passord</label>
                <input type="password" name="r-password" placeholder="gjenta passord" required>
            </div>

            <button type="submit"><?php echo (!empty($message)) ? "Redirekter om 5..." : "Oppdater Passord"; ?></button>
                <?php if (isset($success) && $success):?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            const button = document.querySelector("button[type='submit']");
                            if(button){
                                button.disabled = true;
                                let seconds = 5;
                                button.textContent = "Redirekter om " + seconds;
                                const countdown = setInterval(() => {
                                    seconds--;
                                    if(seconds <= 0){
                                        clearInterval(countdown);
                                    }
                                    else{
                                        button.textContent = "Redirekter om " + seconds;
                                    }
                                }, 1000);
                            }
                        });
                    </script>
                <?php endif; ?>
                <?php if(isset($message)){echo "<div class='positive'>$message</div>";}?>
            </form>
        <?php endif;?>
    </div>
    <?php
    if (isset($redirect) && $redirect) {
        echo '<meta http-equiv="refresh" content="5;url=login.php">';
    }
    ?>
</body>
</html>