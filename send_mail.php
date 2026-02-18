<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = strip_tags(trim($_POST['name']));
    $email   = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars($_POST['message']);

    $to      = 'admin@grownitfx.com';
    $subject = "New Contact Form Message from $name";
    $body    = "Name: $name\nEmail: $email\n\nMessage:\n$message";
    $headers = "From: noreply@grownitfx.com\r\nReply-To: $email";

    if (mail($to, $subject, $body, $headers)) {
        echo "Thank you! Your message has been sent.";
    } else {
        echo "Oops! Something went wrong. Please try again.";
    }
}
?>
