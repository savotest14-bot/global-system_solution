<?php

// --------------------------------------------------------------------------
// SMTP Configuration
// --------------------------------------------------------------------------
// To deliver emails directly to Gmail inboxes without Infomaniak relay blocks,
// configure Gmail SMTP with a 16-character Google App Password.

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'ngrokutltest@gmail.com');
define('SMTP_PASS', 'mthi ojlc tord yqds'); // Replace with your 16-character Google App Password
define('SMTP_SECURE', 'tls');

// Admin email to receive all website contact submissions
define('ADMIN_EMAIL', 'wdstpl@gmail.com');

// Sender identity
define('SENDER_EMAIL', 'wdstpl@gmail.com');
define('SENDER_NAME', 'Global System Solutions Partners');
