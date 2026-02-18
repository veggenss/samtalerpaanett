<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // hvis brukeren ikke er logget inn, redirect til login
    header("Location: ../login.php");
    exit();
}

require_once '../include/db.inc.php';
$mysqli = dbConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // håndterer oppdatering av profilbilde :D endelig :DDD
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif']; // array med filtypene som er tillat
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); // filtypen til bildet brukeren lastet opp

        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext; // lager et unikt filnavn basert på klokkeslettet bruker lastet opp (tror jeg, se i wiki hvis du er nysjerrig, idk)
            $upload_dir = '../uploads/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // lag uploads mappe hvis den ikke finnes lol
            }

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                $profile_picture = $new_filename;

                // oppdaterer faktisk profilbildet i databasen :D
                $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("si", $profile_picture, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    // oppdaterer session profilbilde variabelen med det nye profilbildet
                    $_SESSION['profile_picture'] = $profile_picture;
                    $message = "Oppdatering av profilbilde vellykket! :-)";
                } else {
                    $error = "Oppdatering av profilbilde mislykkes! :-(";
                }
            } else {
                $error = "Kunne ikke laste opp bildet. :-(";
            }
        } else {
            $error = "Ugyldig filtype! Bare JPG, JPEG, PNG og GIF er tillatt. Bruk en ordentlig filtype, bro >:(";
        }
    } elseif ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "Feil under opplasting av fil D: Error: " . $_FILES['profile_picture']['error'];
    }

    // selecter shit fra databasen
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $new_username = $_POST['username'];
    $new_email = $_POST['email'];

    $email = trim($new_email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ugyldig e-post >:(";
    }
    // sjekker at domenet til e-posten faktisk finnes
    elseif (!checkdnsrr(substr(strrchr($email, "@"), 1), "MX")) {
        $error = "E-postdomenet finnes ikke >:(";
    }
    elseif (!preg_match('/^.{4,}$/', $new_username)) {
        $error = "Brukernavnet må være 4 tegn eller mer >:(";
    }
    elseif ($new_email !== $user['mail']) {
        // sjekk om e-post allerede er i bruk, eller, om den allerede er i databasen
        $sql = "SELECT id FROM users WHERE mail = ? AND id != ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("si", $new_email, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = "E-posten er allerede i bruk >:(";
        }
    }

    if (!isset($error)) {
        // hvis e-posten er annerledes, oppdater e-post og send ny e-postverifisering greie
        if ($new_email !== $user['mail']) {
            $token = bin2hex(random_bytes(16));

            $sql = "UPDATE users SET username = ?, mail = ?, email_verification_token = ?, email_verified = 0 WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("sssi", $new_username, $new_email, $token, $_SESSION['user_id']);
            if ($stmt->execute()) {
                require '../mailer/send_email_verification.php';
                $config = require __DIR__ . '/../mailer/config.php';
                if (sendVerificationEmail($new_email, $new_username, $token, $config)) {
                    // cookies for verify_email_info-siden (nam nam) (haha skjønner du lololool fordi det er jo sånn cookies, eller, kjeks, og det er jo nam nam hahahaha LOL - isak)
                    setcookie("mail_message", "Du må verifisere e-posten før du logger inn igjen. \nEn verifikasjonslink har blitt sendt til \n$new_email", time() + 10, "/");
                    setcookie("username", $_SESSION['username'], time() + 10, "/");
                    setcookie("mail", $new_email, time() + 10, "/");
                    session_unset();
                    session_destroy();
                    header("Location: verify_email_info.php");
                    exit();
                } else {
                    $error = "Kunne ikke sende e-post :(";
                }
            }
        }
        else {
            // hvis bruker ikke endret noe i e-post feltet bare oppdater brukernavn
            $sql = "UPDATE users SET username = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("si", $new_username, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $message = "Oppdaterte bruker :D";
                $_SESSION['username'] = $new_username;
                $_SESSION['email'] = $new_email;
            }
            else {
                $error = "Kunne ikke oppdatere bruker :(";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Samtaler på nett | Profil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" href="../assets/icons/logo.ico" />
    <link rel="stylesheet" href="../css/userRegLog.css">
    <!-- ikoner fra font awesome og google fonts-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" />
</head>

<body>
    <div class="auth-con">
        <h2><?php echo htmlspecialchars($_SESSION["username"]); ?>'s profil</h2> <!-- det er (username)s profil ikke (username)'s profil!!! vi bruker ikke apostrof for det sånt på norsk!!!!!! - isak -->
        <?php if (isset($error)): ?>
            <div class="error"><?php echo "{$error}<br>"; ?></div>
        <?php elseif(isset($message)): ?>
            <div class="positive"><?php echo "{$message}<br>"; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
            <div class="profile-group">
                <div class="current-profile">
                    <img src="../uploads/<?php echo htmlspecialchars($_SESSION["profile_picture"]); ?>" alt="Profilbilde">
                </div>
                <label for="profile_picture">Velg nytt profilbilde:</label>
                <input type="file" name="profile_picture" id="profile_picture">
            </div>

            <div class="profile-group">
                <label>Brukernavn:</label>
                <input type="text" placeholder="<?php echo htmlspecialchars($_SESSION['username']); ?>" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" name="username">
            </div>

            <div class="profile-group">
                <label>E-post:</label>
                <input type="email" placeholder="<?php echo htmlspecialchars($_SESSION['email']); ?>" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" name="email">
            </div>

            <div class="profile-group">
                <p>Bytte Passord? <br><a id="backButton" href="password_reset.php">Tilbakestill Passord <i class="fa-solid fa-arrow-up-right-from-square"></i></a></p>
            </div>
            <button id="submit" type="submit" onclick="return confirm('Hvis du har endret e-post: \nDu blir logget ut og må verifisere e-posten før du logger inn igjen')">Lagre Endringer</button>
            <div class="profile-group">
                <a href="../logout.php" id="logout">Logg ut</a>
            </div>
        </form>

        <p>Antall samtalepoeng: <?php // samtalepoeng går her ?></p>
        <a id="backButton" href="../main.php">Tilbake til Samtaler På Nett</a>
    </div>
</body>

</html>
