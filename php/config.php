<?php
// config.php

// Database konfiguration
define('DB_HOST', 'mysql23.unoeuro.com');
define('DB_USER', 'krisba_dk');
define('DB_PASS', 'Erb2GytRzkDFfmgxdABe');
define('DB_NAME', 'krisba_dk_db');

// SMTP Email konfiguration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587); // Typisk 587 til TLS eller 465 til SSL
define('SMTP_USER', 'your_email@example.com');
define('SMTP_PASS', 'your_email_password');
define('SMTP_FROM', 'your_email@example.com');
define('SMTP_FROM_NAME', 'AGF Billetsystem');

// Ticket secret key
define('TICKET_SECRET_KEY', 'kris+sebastian=awesome');

// Andre globale indstillinger
date_default_timezone_set('Europe/Copenhagen');
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>