<?php
session_start();
require_once 'include/db.inc.php';
require_once 'functions.php';

$mysqli = dbConnection();

if(isset($_COOKIE['not_verified'])){
    $cookie_message = $_COOKIE['not_verified'];
    setcookie("not_verified", "", time() - 3600, "/");
}

if(isset($_COOKIE['password_token_set'])){
    $cookie_message = $_COOKIE['password_token_set'];
    setcookie("password_token_set", "", time() - 3600, "/");
}

// håndterer innlogging
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = $mysqli->real_escape_string($_POST['username']);

    // sjekker brukernavn og passord opp mot databasen
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // verifiser brukernavn og passord og lager session hvis de er riktig
    if ($user){
        // sjekker om du har profilbilde på brukeren din, hvis ikke så gir den deg default
        if(!$user['profile_picture']){
            $profile_picture = 'default.png';
            $sql = "UPDATE users SET profile_picture = ? WHERE username = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ss", $profile_picture, $user['username']);
            if($stmt->execute()){
                echo "Du har nå fått default profilbilde :D";
            }
            else {
                echo "Kunne ikke gi deg default profilbilde :(";
            }

        }

        if(!password_verify($_POST['password'], $user['password'])) {
            $error = "Ugyldig brukernavn eller passord";
        }
        elseif(!$user['email_verified']){
            if(!empty($user['email_verification_token'])){
                $error = "Du må bekrefte e-posten din";
            }
        }
        else{
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['profile_picture'] = $user['profile_picture'];
            $_SESSION['email'] = $user['mail'];

            header('Location: main.php'); // redirecter til hovedsiden
            exit();
        }
    }
    else {
        $error = "Ugyldig brukernavn eller passord"; // error melding hvis du skrev ugyldig brukernavn eller passord
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Samtaler på nett | Logg inn</title>
    <link rel="icon" href="assets/icons/logo.ico" />
    <link rel="stylesheet" href="css/userRegLog.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" />

    <!-- Open Graph meta-tagger -->
    <meta property="og:title" content="Samtaler på Nett">
    <meta property="og:description" content="Samtaler på Nett er et sted på nett hvor du kan ha samtaler.">
    <meta property="og:image" content="https://isak.brunhenriksen.no/Pictures/samtalelogo.png">
    <meta property="og:url" content="https://isak.brunhenriksen.no/samtalerpanett">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="no_NO">
    <meta property="og:site_name" content="Samtaler På Nett">
</head>

<body>
    <div class="auth-con">
        <h2>Logg inn</h2>
        <p>For å bruke Samtaler på Nett, må du logge inn.</p> <br>

        <?php if(isset($cookie_message)):?>
            <div class="error"><?php echo $cookie_message; ?></div>
        <?php elseif(isset($error)):?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif;?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>" method="post">

            <div class="form-group">
                <label>Brukernavn:</label>
                <input type="text" placeholder="brukernavn" name="username" required>
            </div>

            <div class="form-group">
                <label>Passord:</label>
                <input type="password" placeholder="passord" name="password" required>
                <p>Glemt Passord?<br><a id="backButton" href="pages/forgot_password.php">Tilbakestill Passord <i class="fa-solid fa-arrow-up-right-from-square"></i></a></p>
            </div>


            <label for="remember_me" class="remember_me">
                <input type="checkbox" id="remember_me" name="remember_me"> Husk meg
            </label><br>

            <button id="submit" type="submit">Logg inn</button>

        </form>

        <p>Har du ikke bruker enda? <a href="register.php">Registrer deg her</a></p>
    </div>
</body>

</html>
