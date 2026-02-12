<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/config.php';

function sendVerificationEmail($to, $username, $token, $config){
    $mail = new PHPMailer(true);
    try{
        $mail->isSMTP();
        $mail->Host = $config['mail']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['mail']['username'];
        $mail->Password = $config['mail']['password'];
        $mail->SMTPSecure = $config['mail']['encryption'];
        $mail->Port = $config['mail']['port'];


        $mail->setFrom('samtalerpaanett@gmail.com', 'Samtaler på nett');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Bekreft e-posten din hos Samtaler på nett';

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        $verificationUrl = "$protocol://$host$basePath/verify_email.php?token=$token";

        $mail->CharSet = 'UTF-8';
        $mail->Body = "<div style='
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        padding: 20px;
                        border-radius: 8px;
                        max-width: 600px;
                        margin: 0 auto;
                        color: #333;'>
                        <h2 style='color: #2c3e50;'>Hei <strong>$username</strong>,</h2>
                        <p>Klikk på knappen under for å bekrefte e-posten din:</p>
                        <p style='text-align: center;'>
                            <a href='$verificationUrl' style='
                            display: inline-block;
                            padding: 12px 20px;
                            background-color: #3498db;
                            color: #fff;
                            text-decoration: none;
                            border-radius: 5px;
                            font-weight: bold;
                            '>Bekreft e-post</a></p>
                        <p style='font-size: 12px; color: #888;'>Hvis du ikke ba om denne e-posten, kan du bare ignorere den.</p>
                        </div>";
        $mail->send();
        return true;
    }
    catch (Exception $e) {
        error_log('Epostfeil' . $mail->ErrorInfo);
        return false;
    }
}
?>
