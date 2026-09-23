<?php

header("Content-Type: application/json; charset=UTF-8");

// Allow cross-origin requests during local development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Read payload (supports JSON payload and form-encoded data)
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (!$input && !empty($_POST)) {
    $input = $_POST;
}

if (!$input) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid submission payload."
    ]);
    exit;
}

// Extract and sanitize strictly the 6 contact form fields
$firstName      = htmlspecialchars(trim($input["firstName"] ?? $input["fn"] ?? ""));
$lastName       = htmlspecialchars(trim($input["lastName"] ?? $input["ln"] ?? ""));
$company        = htmlspecialchars(trim($input["company"] ?? $input["co"] ?? ""));
$email          = filter_var(trim($input["email"] ?? $input["em"] ?? ""), FILTER_VALIDATE_EMAIL);
$areaOfInterest = htmlspecialchars(trim($input["areaOfInterest"] ?? $input["sv"] ?? ""));
$message        = nl2br(htmlspecialchars(trim($input["message"] ?? $input["msg"] ?? "")));

// Validation
if (empty($firstName) || empty($lastName) || !$email) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please fill all required fields (First name, Last name, and a valid Email address)."
    ]);
    exit;
}

// Locate logo for email embedding
$logoPath = null;
$possibleLogos = [
    __DIR__ . "/logo-white.png",
    __DIR__ . "/logo.png",
    __DIR__ . "/../public/GSSP-white.png",
    __DIR__ . "/../public/GSSP.png",
    __DIR__ . "/../dist/GSSP-white.png",
    __DIR__ . "/../GSSP-white.png",
];
foreach ($possibleLogos as $candidate) {
    if (file_exists($candidate)) {
        $logoPath = $candidate;
        break;
    }
}

// Mailer factory helper
function createMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = (defined('SMTP_SECURE') && strtolower(SMTP_SECURE) === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    if (defined('SMTP_HOST') && strpos(SMTP_HOST, 'gmail') !== false) {
        $mail->Hostname = 'gmail.com';
    } else {
        $mail->Hostname = 'globalsystemsolutionspartners.com';
    }
    $mail->CharSet    = "UTF-8";
    $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
    $mail->Sender     = SENDER_EMAIL;
    return $mail;
}

$submissionTime = date("d M Y &bull; H:i T");
$clientIp       = $_SERVER["REMOTE_ADDR"] ?? "Unknown";

// --------------------------------------------------------------------------
// 1. Template: Admin Notification Email
// --------------------------------------------------------------------------
$adminSubject = "New Enquiry: " . $firstName . " " . $lastName . (!empty($company) ? " (" . $company . ")" : "") . " — GSSP";

$adminBody = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0; padding:0; background:#EDEAE4; font-family:"DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color:#1A1814; }
  .wrapper { width:100%; padding:40px 0; background:#EDEAE4; }
  .container { max-width:680px; margin:auto; background:#F7F5F1; border:1px solid #D4D0C8; box-shadow:0 12px 35px rgba(26,24,20,0.06); }
  .header { background:#141311; padding:38px 40px; text-align:center; border-bottom:2px solid #9C8866; }
  .logo-img { max-width:180px; height:auto; display:block; margin:0 auto 14px; }
  .badge { display:inline-block; padding:5px 16px; background:rgba(156,136,102,0.16); border:1px solid #9C8866; color:#9C8866; font-size:11px; font-weight:600; letter-spacing:0.2em; text-transform:uppercase; }
  .content { padding:42px 40px 36px; }
  h1 { font-family:Georgia, serif; margin:0 0 10px; color:#1A1814; font-size:24px; font-weight:400; }
  p.intro { line-height:1.7; font-size:14px; color:#5C5850; margin:0 0 28px; }
  .section-title { margin-top:28px; margin-bottom:12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.18em; color:#9C8866; }
  table.data-table { width:100%; border-collapse:collapse; background:#ffffff; border:1px solid #D4D0C8; }
  td.label { width:32%; background:#F7F5F1; font-weight:600; color:#5C5850; text-transform:uppercase; font-size:11px; letter-spacing:0.12em; padding:12px 18px; border-bottom:1px solid #EDEAE4; }
  td.val { padding:12px 18px; border-bottom:1px solid #EDEAE4; font-size:14px; color:#1A1814; }
  .message-box { padding:20px; background:#ffffff; border:1px solid #D4D0C8; border-left:3px solid #9C8866; font-size:14px; line-height:1.8; color:#1A1814; margin-top:6px; }
  .footer { background:#141311; padding:32px 40px; text-align:center; color:#A8A49E; font-size:12px; line-height:1.8; border-top:1px solid rgba(255,255,255,0.08); }
  .footer-brand { font-family:Georgia, serif; color:#F7F5F1; font-size:13px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; margin-bottom:6px; }
  .footer-tag { font-size:11px; color:#9C8866; text-transform:uppercase; letter-spacing:0.14em; margin-bottom:14px; }
  .footer-legal { font-size:11px; color:#7A766F; border-top:1px solid rgba(255,255,255,0.08); padding-top:14px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="container">
    <div class="header">';

if ($logoPath) {
    $adminBody .= '<img src="cid:gssp_logo" alt="Global System Solutions Partners" class="logo-img" width="180">';
} else {
    $adminBody .= '<div style="font-family:Georgia,serif; font-size:26px; font-weight:700; color:#F7F5F1; letter-spacing:0.25em; margin-bottom:12px;">GLOBAL SYSTEM SOLUTIONS PARTNERS</div>';
}

$adminBody .= '
      <div class="badge">WEBSITE CONTACT ENQUIRY</div>
    </div>
    <div class="content">
      <h1>New Partnership Enquiry</h1>
      <p class="intro">A visitor has submitted a new inquiry via the Global System Solutions Partners website contact form.</p>
      
      <div class="section-title">Sender Information</div>
      <table class="data-table">
        <tr><td class="label">First Name</td><td class="val">' . $firstName . '</td></tr>
        <tr><td class="label">Last Name</td><td class="val">' . $lastName . '</td></tr>
        <tr><td class="label">Email Address</td><td class="val"><a href="mailto:' . $email . '" style="color:#1A1814; font-weight:600; text-decoration:underline;">' . $email . '</a></td></tr>
        <tr><td class="label">Company / Organisation</td><td class="val">' . (!empty($company) ? $company : '<span style="color:#8C877E; font-style:italic;">Not provided</span>') . '</td></tr>
        <tr><td class="label">Area of Interest</td><td class="val">' . (!empty($areaOfInterest) ? $areaOfInterest : '<span style="color:#8C877E; font-style:italic;">General Enquiry</span>') . '</td></tr>
        <tr><td class="label">Timestamp</td><td class="val">' . $submissionTime . '</td></tr>
        <tr><td class="label">Origin IP</td><td class="val">' . $clientIp . '</td></tr>
      </table>';

if (!empty($message)) {
    $adminBody .= '
      <div class="section-title">Message / Project Scope</div>
      <div class="message-box">' . $message . '</div>';
}

$adminBody .= '
    </div>
    <div class="footer">
      <div class="footer-brand">GLOBAL SYSTEM SOLUTIONS PARTNERS</div>
      <div class="footer-tag">Part of the EquusChain Ecosystem infrastructure</div>
      <div class="footer-legal">
        &copy; ' . date("Y") . ' Global System Solutions Partners. All rights reserved.<br>
        This email and any attachments are confidential and intended solely for GSSP administration.
      </div>
    </div>
  </div>
</div>
</body>
</html>';

// --------------------------------------------------------------------------
// 2. Template: Submitter Confirmation Email
// --------------------------------------------------------------------------
$confirmSubject = "Thank you for contacting Global System Solutions Partners";

$confirmBody = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0; padding:0; background:#EDEAE4; font-family:"DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color:#1A1814; }
  .wrapper { width:100%; padding:40px 0; background:#EDEAE4; }
  .container { max-width:680px; margin:auto; background:#F7F5F1; border:1px solid #D4D0C8; box-shadow:0 12px 35px rgba(26,24,20,0.06); }
  .header { background:#141311; padding:38px 40px; text-align:center; border-bottom:2px solid #9C8866; }
  .logo-img { max-width:180px; height:auto; display:block; margin:0 auto; }
  .content { padding:42px 40px 36px; }
  h1 { font-family:Georgia, serif; margin:0 0 16px; color:#1A1814; font-size:24px; font-weight:400; }
  p { line-height:1.8; font-size:15px; color:#5C5850; margin:0 0 18px; }
  .quote { border-left:3px solid #9C8866; padding:12px 18px; font-style:italic; color:#1A1814; font-family:Georgia, serif; font-size:15px; margin:24px 0; background:rgba(156,136,102,0.05); }
  .summary-table { width:100%; border-collapse:collapse; background:#ffffff; border:1px solid #D4D0C8; margin-top:20px; }
  .summary-table td { padding:12px 18px; border-bottom:1px solid #EDEAE4; font-size:14px; }
  .summary-label { width:34%; background:#F7F5F1; font-weight:600; color:#5C5850; text-transform:uppercase; font-size:11px; letter-spacing:0.12em; }
  .footer { background:#141311; padding:32px 40px; text-align:center; color:#A8A49E; font-size:12px; line-height:1.8; border-top:1px solid rgba(255,255,255,0.08); }
  .footer-brand { font-family:Georgia, serif; color:#F7F5F1; font-size:13px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; margin-bottom:6px; }
  .footer-tag { font-size:11px; color:#9C8866; text-transform:uppercase; letter-spacing:0.14em; margin-bottom:14px; }
  .footer-legal { font-size:11px; color:#7A766F; border-top:1px solid rgba(255,255,255,0.08); padding-top:14px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="container">
    <div class="header">';

if ($logoPath) {
    $confirmBody .= '<img src="cid:gssp_logo" alt="Global System Solutions Partners" class="logo-img" width="180">';
} else {
    $confirmBody .= '<div style="font-family:Georgia,serif; font-size:26px; font-weight:700; color:#F7F5F1; letter-spacing:0.25em;">GLOBAL SYSTEM SOLUTIONS PARTNERS</div>';
}

$confirmBody .= '
    </div>
    <div class="content">
      <h1>Thank you for contacting us</h1>
      <p>Dear ' . $firstName . ',</p>
      <p>Thank you for reaching out to Global System Solutions Partners. We have received your enquiry' . (!empty($areaOfInterest) ? ' regarding <strong>' . $areaOfInterest . '</strong>' : '') . '.</p>
      <p>At Global System Solutions Partners, every engagement is handled with commercial discretion, rigor, and personal oversight.</p>
      
      <div class="quote">"International trade is ultimately a business of relationships and deserves the attention of a trusted advisor."</div>
      
      <p>A member of our team will review your inquiry and respond personally — typically within one business day.</p>
      
      <p style="margin-top:28px; margin-bottom:8px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.15em; color:#9C8866;">Summary of your enquiry</p>
      <table class="summary-table">
        <tr><td class="summary-label">Name</td><td>' . $firstName . ' ' . $lastName . '</td></tr>';

if (!empty($company)) {
    $confirmBody .= '<tr><td class="summary-label">Company</td><td>' . $company . '</td></tr>';
}
if (!empty($areaOfInterest)) {
    $confirmBody .= '<tr><td class="summary-label">Area of Interest</td><td>' . $areaOfInterest . '</td></tr>';
}
if (!empty($message)) {
    $confirmBody .= '<tr><td class="summary-label">Message / Scope</td><td>' . $message . '</td></tr>';
}

$confirmBody .= '
      </table>

      <p style="margin-top:32px;">Best regards,<br><br><strong style="color:#1A1814;">The GSSP Team</strong><br><span style="color:#8C877E; font-size:13px;">Global System Solutions Partners</span></p>
    </div>
    <div class="footer">
      <div class="footer-brand">GLOBAL SYSTEM SOLUTIONS PARTNERS</div>
      <div class="footer-tag">Part of the EquusChain Ecosystem infrastructure</div>
      <div class="footer-legal">
        &copy; ' . date("Y") . ' Global System Solutions Partners. All rights reserved.<br>
        This communication is confidential and intended solely for the addressee.
      </div>
    </div>
  </div>
</div>
</body>
</html>';

// --------------------------------------------------------------------------
// 3. Email Dispatch Execution
// --------------------------------------------------------------------------
$adminSent = false;
$adminError = "";

// 3.1 Send Admin Notification
try {
    $adminMail = createMailer();
    if ($logoPath) {
        $adminMail->addEmbeddedImage($logoPath, 'gssp_logo', 'gssp-logo.png');
    }
    $adminMail->addAddress(ADMIN_EMAIL, 'GSSP Administrator');
    $adminMail->addReplyTo($email, $firstName . ' ' . $lastName);
    $adminMail->isHTML(true);
    $adminMail->Subject = $adminSubject;
    $adminMail->Body    = $adminBody;
    $adminMail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $adminBody));
    
    $adminMail->send();
    $adminSent = true;
} catch (Exception $eAdmin) {
    $adminError = $adminMail->ErrorInfo ?: $eAdmin->getMessage();

    // Fallback if direct send to external ADMIN_EMAIL encounters local IP relay restrictions
    if (defined('SENDER_EMAIL') && ADMIN_EMAIL !== SENDER_EMAIL) {
        try {
            $fbMail = createMailer();
            if ($logoPath) {
                $fbMail->addEmbeddedImage($logoPath, 'gssp_logo', 'gssp-logo.png');
            }
            $fbMail->addAddress(SENDER_EMAIL, 'GSSP Mailbox');
            $fbMail->addReplyTo($email, $firstName . ' ' . $lastName);
            $fbMail->isHTML(true);
            $fbMail->Subject = '[Enquiry Delivery] ' . $adminSubject;
            $fbMail->Body    = $adminBody;
            $fbMail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $adminBody));
            
            $fbMail->send();
            $adminSent = true;

            @file_put_contents(
                __DIR__ . "/../storage/logs/mail.log",
                date("[Y-m-d H:i:s] ") . "Delivered to SENDER_EMAIL (" . SENDER_EMAIL . ") due to external relay policy on ADMIN_EMAIL (" . ADMIN_EMAIL . ")\n",
                FILE_APPEND
            );
        } catch (Exception $eFb) {
            $adminError .= " | Fallback error: " . ($fbMail->ErrorInfo ?: $eFb->getMessage());
        }
    }
}

// 3.2 Send Submitter Confirmation Email
if ($adminSent) {
    try {
        $userMail = createMailer();
        if ($logoPath) {
            $userMail->addEmbeddedImage($logoPath, 'gssp_logo', 'gssp-logo.png');
        }
        $userMail->addAddress($email, $firstName . ' ' . $lastName);
        $userMail->isHTML(true);
        $userMail->Subject = $confirmSubject;
        $userMail->Body    = $confirmBody;
        $userMail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $confirmBody));
        
        $userMail->send();
    } catch (Exception $eUser) {
        // Log user confirmation status; does not invalidate successful enquiry intake
        @file_put_contents(
            __DIR__ . "/../storage/logs/mail.log",
            date("[Y-m-d H:i:s] ") . "User confirmation dispatch notice for " . $email . ": " . ($userMail->ErrorInfo ?: $eUser->getMessage()) . "\n",
            FILE_APPEND
        );
    }

    echo json_encode([
        "success" => true,
        "message" => "Your message has been received. We will be in touch shortly."
    ]);
} else {
    $logEntry = date("[Y-m-d H:i:s] ") . "Mailer Error: " . $adminError . "\n";
    @file_put_contents(__DIR__ . "/../storage/logs/mail.log", $logEntry, FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "An error occurred while sending your request. Please try again or contact us directly.",
        "error"   => $adminError
    ]);
}