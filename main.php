<?php
session_start();
require 'functions.php';

//redirecter bruker hvis de har uverifisert email eller ikke har svart på passord reset email
if(isset($_COOKIE['not_verified']) || isset($_COOKIE['password_token_set'])){
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    // hvis brukeren ikke er logget inn, redirect til login
    header("Location: logout.php");
    exit();
}

// variabel for versjonsnummer
$version = "0.5.2-alpha";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Samtaler på nett | Main</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="css/mainStyle.css" />
    <link rel="icon" href="assets/icons/logo.ico" />

    <!-- ikoner fra font awesome og google fonts-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />

    <!-- Open Graph meta-tagger -->
    <meta property="og:title" content="Samtaler på Nett <?php echo $version; ?>">
    <meta property="og:description" content="Samtaler på Nett er et sted på nett hvor du kan ha samtaler.">
    <meta property="og:image" content="https://isak.brunhenriksen.no/Pictures/samtalelogo.png">
    <meta property="og:url" content="https://isak.brunhenriksen.no/samtalerpanett">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="no_NO">
    <meta property="og:site_name" content="Samtaler På Nett">
</head>

<body>
    <nav>
        <ul>
            <li><a href="#" id="global-enable"><i class="fa-regular fa-message"></i>Global Chat</a></li>
            <li><a href=""><i class="fa-regular fa-face-smile"></i>Venner</a></li>
        <!--<li><a href="">+ Legg til venner</a></li>--> <!-- hvorfor skal legg til venner være i main.php istedet for i friends.php hvis det eventuelt skal være en ting? - mr brun -->
        </ul>
        <ul class="nav-prof">
            <li><a href="/samtalerpanett/pages/profile.php"><?php echo htmlspecialchars($_SESSION['username']); ?><img id="nav-pfp" src="uploads/<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>"</i></a></li>
        </ul>
    </nav>

    <!-- Direct message liste -->
    <div class="DM-left">
        <div class="DM-act">

            <h3>Mine samtaler</h3>
            <button id="newDM" class="new-dm-button"><i class="fa-solid fa-plus"></i>Ny samtale</button>

            <div class="search-container">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="messageSearch" placeholder="Søk etter samtale med...">
            </div>
            <div id="DMList"></div>

        </div>
    </div>

    <!-- Activity liste (høyre) -->
    <div class="activity-viewer">
        <div class="activity-list">
            <h3>Aktive venner</h3>

        </div>
    </div>

    <!-- Midt delen med chat -->
    <div class="container">
        <div class="header">
            <h1 class="header">Samtaler på Nett</h1>
        </div>

        <!--Global Chat-->
        <div class="chat">
            <div id="messages"></div>
            <div class="message-inputs">
                <input type="text" id="messageInput" placeholder="Skriv melding...">
                <button id="sendButton">Send</button>
            </div>
        </div>
    </div>


    <script>
        window.currentUserId = <?php echo json_encode($_SESSION['user_id']);?>;
        window.currentUsername = <?php echo json_encode($_SESSION['username']);?>;
        window.currentProfilePictureUrl = <?php echo json_encode($socketParams['protocol'] . '://' . $socketParams['hostname'] . '/samtalerpanett/uploads/' . $_SESSION['profile_picture']); ?>;
   </script>
</body>
<script src="js/setupwebsocket.js"></script>
<script src="js/mainScript.js"></script>
</html>
