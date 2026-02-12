<?php
require_once "include/db.inc.php";
$mysqli = dbConnection();

$registerd = null;


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // default profilbilde
    $profile_picture = 'default.png';

    // TIL WIGGO OG MULIGE ANDRE UTVIKLERE I SAMTALER PÅ NETT:
    // hvis det ikke funker å laste opp profilbilde i det hele tatt og du bare får "kunne ikke flytte bildet" eller "feil under opplastning av fil"
    // så er det mest sannsynlig fordi webserveren (og andre brukere) ikke har skrivetilgang til uploads mappen. eller at uploads mappen ikke finnes i det hele tatt
    // hvis uploads mappen ikke finnes: LAG DEN! den heter bare 'uploads' og er i 'samtalerpanett' mappen.
    // hvordan gi webserveren skrivetilgang til uploads mappen: skriv 'chmod 777 uploads' i 'samtalerpanett' mappen. den kommandoen gir read, write og execute
    // tilgang til alle brukere.
    // vent... jeg vet ikke om dette er et problem på windows en gang... men hvis det er det, så er løsningen her i hvertfall! vet at det er et problem på ordentlige operativsystemer, altså, UNIX-baserte OS-er. - Isak 25/05/25 19:47
    // fant nettopp ut av at det ikke er et problem på windows :( jeg skrev hele den kommentaren for ingenting. vel ikke fjern den! jeg la så mye innsats inn i den!!!! - Isak 25/05/25 19:57
    // FUCK WINDOWS! nå er jeg frustrert på windows brukeres vegne. dette er unsafe design av microsoft! alle brukere har jo fanken i lanken meg tilgang til alle filer og mapper? hva er det for noe drit? det gjør at hvis du har et virus som kjører under sin egen bruker på pcen, så har den tilgang til alle filer på pcen? bro? hva faen tenkte microsoft på når de gjorde det på den måten? - Isak 25/05/25 20:07

    // sjekker om brukeren har lastet opp et bilde
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif']; // liste over filtypene som er tillat
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); // finner filtypen til bildet/filen du lastet opp

        // sjekker om filtypen til bildet du lastet opp er i $allowed arrayen, altså, om den er tillat
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext; // uniqid() lager en helt unik id til filene - tror id-en er basert på klokkeslettet bildet ble lastet opp, men er ikke helt sikker. hvis du virkelig vil finne ut av det så sjekk php wikien lolololololololol
            $upload_dir = 'uploads/';

            // sjekker om det finnes en uploads mappe i det hele tatt lol
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // mkdir er make directory, så den maker et directory med navnet i $uploads_dir (uploads), 0777 betyr det jeg skrev tidligere i den kommentaren øverst - read, write og execute permissions. 'true' trengs egentlig ikke her, fordi den betyr at mkdir er recursive, som beyr at hvis du skriver '/apekatt/uploads', og apekatt mappen finnes ikke, så lager den både apekatt og uploads. men her trenger vi jo ikke det fordi vi lager bare uploads, men why not tenker jeg. det er gøy å ha "true" der!! :D
            }

            // flytter filen/bildet til uploads mappen
            // jeg gidder ikke skrive flere kommentarer, sorry bro - locket inn på kommentarene over
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                $profile_picture = $new_filename;
            } else {
                error_log('Kunne ikke flytte bildet til uploads. :( Error: ' . error_get_last()['message']);
                $error = "Kunne ikke laste opp bildet. :(";
            }
        } else {
            $error = "Ugyldig filtype! Bare JPG, JPEG, PNG og GIF er tillatt. Skaff deg et ordentlig bilde!";
        }
    } elseif ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        // sånn hvis erroren ikke er at brukeren ikke lastet opp en fil så er det denne liksom. hvordan kan jeg forklare dette D:
        $error = "Feil under opplasting av fil. Error: " . $_FILES['profile_picture']['error'];
    }

    // sigma regex - sjekker om brukernavn følger requirements
    if (!preg_match('/^.{4,}$/', $_POST['username'])) {
        $error = "Brukernavnet må være minst 4 tegn.";
    }
    elseif(preg_match('/[ ]/', $_POST['username'])) {
        $error = "Brukernavnet kan ikke ha mellomrom";
    }
    else{
        $username = filter_input(INPUT_POST, "username", FILTER_SANITIZE_SPECIAL_CHARS);
        $sql = "SELECT username FROM users WHERE username = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if($stmt->num_rows >= 1){
            $error = "Brukernavnet finnes allerede";
        }
        else{
            if (!preg_match('/^.{4,}$/', $_POST['password'])) {
                $error = "Passordet må være minst 4 tegn.";
            } elseif (!preg_match('/(?=.*\w)(?=.*\d)/', $_POST['password'])) {
                $error = "Passordet må ha minst 1 bokstav og 1 tall.";
            } elseif (preg_match('/[ ]/', $_POST['password'])) {
                $error = "Passordet kan ikke inneholde mellomrom.";
            } else {
                // e-postvalidering
                $email = trim($_POST['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Ugyldig e-post.";
                } elseif (!checkdnsrr(substr(strrchr($email, "@"), 1), "MX")) {
                    $error = "E-postdomenet finnes ikke.";
                } else {
                    // sjekk om e-post allerede er i db
                    $sql = "SELECT * FROM users WHERE mail = ?";
                    $stmt = $mysqli->prepare($sql);
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $fetch_mail = $result->fetch_assoc();

                    if ($fetch_mail) {
                        $error = "E-posten er allerede i bruk.";
                    } else {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                        // generer e-posttoken
                        $token = bin2hex(random_bytes(16));

                        // lager brukeren i databasen
                        $sql = "INSERT INTO users (username, mail, password, profile_picture, email_verification_token, email_verified) VALUES (?, ?, ?, ?, ?, 0)";
                        $stmt = $mysqli->prepare($sql);
                        $stmt->bind_param("sssss", $username, $email, $password, $profile_picture, $token);

                        if ($stmt->execute()) {
                            require 'mailer/send_email_verification.php';
                            $config = require __DIR__ . '/mailer/config.php';
                            if (sendVerificationEmail($email, $username, $token, $config)) {
                                $registerd = true;
                            }
                            else {
                                $error = "E-post kunne ikke sendes.";
                            }
                        }
                        else {
                            $error = "Kunne ikke registreres.";
                        }
                        $stmt->close();
                    }
                }
            }
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
    <link rel="icon" href="assets/icons/logo.ico" />
    <title>Samtaler på nett | Registrer</title>
</head>

<body>
    <div class="auth-con">
        <h2>Registrering</h2>
        <p>Du må registrere deg for å bruke nettsiden</p>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo "{$error}<br>"; ?></div>
        <?php elseif($registerd): ?>
            <div class="positive">Du er nå registrert!<br>Bekreftelses epost har blir sent til <?php echo $email; ?></div>
        <?php endif; ?>


        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="register-form" enctype="multipart/form-data">

            <div class="form-group">
                <label>Brukernavn:</label>
                <input type="text" placeholder="brukernavn" name="username" required>
            </div>

            <div class="form-group">
                <label>E-post:</label>
                <input type="email" placeholder="e-post" name="email" required>
            </div>

            <div class="form-group">
                <label>Passord:</label>
                <input type="password" placeholder="passord" name="password" required>
            </div>

            <div class="form-group">
                <label>Profilbilde:</label>
                <input type="file" name="profile_picture">
            </div>

            <button type="submit" value="Register" class="submit" id="registerSubmit">Registrer deg</button>

            <p>Har du allerede bruker? <a href="login.php">Logg inn her</a></p>
        </form>
    </div>
</body>
</html>
