<?php
/**
 * Nova Computer Academy — Contact Form Handler
 * HARDENED: same security layers as send-form.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '/var/www/novait.edu.lk/vendor/autoload.php';

// ══════════════════════════════════════════════════════
//  CONFIG
// ══════════════════════════════════════════════════════
define('SMTP_USER',   'novaitacademy@gmail.com');
define('SMTP_PASS',   'akal iadn rrdc hyxv');   // Gmail App Password
define('MAIL_TO',     'novaitacademy@gmail.com');
define('RATE_LIMIT',  10);    // contact form allows slightly more
define('RATE_WINDOW', 3600);
define('MAX_LEN',     500);
// ══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── CSRF origin check ─────────────────────────────────
$allowed = ['https://novait.edu.lk', 'https://www.novait.edu.lk'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
$ok = false;
foreach ($allowed as $a) { if (str_starts_with($origin, $a)) { $ok = true; break; } }
if (!$ok) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// ── Honeypot ──────────────────────────────────────────
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Message sent.']);
    exit;
}

// ── Rate limit ────────────────────────────────────────
$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rl_dir  = sys_get_temp_dir() . '/nova_rl';
if (!is_dir($rl_dir)) mkdir($rl_dir, 0700, true);
$rl_file = $rl_dir . '/' . $ip_hash . '_contact.json';
$now     = time();
$hits    = file_exists($rl_file) ? (json_decode(file_get_contents($rl_file), true) ?? []) : [];
$hits    = array_filter($hits, fn($t) => ($now - $t) < RATE_WINDOW);
if (count($hits) >= RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many messages. Please wait an hour and try again.']);
    exit;
}
$hits[] = $now;
file_put_contents($rl_file, json_encode(array_values($hits)), LOCK_EX);

// ══════════════════════════════════════════════════════
//  SANITIZE
// ══════════════════════════════════════════════════════
function clean(string $v, int $max = MAX_LEN): string {
    $v = str_replace("\0", '', $v);
    $v = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $v);
    $v = strip_tags($v);
    $v = trim($v);
    $v = mb_substr($v, 0, $max, 'UTF-8');
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function whitelist(string $v, array $list): string {
    return in_array(trim($v), $list, true) ? trim($v) : '';
}

$name    = clean($_POST['name']    ?? '', 120);
$email   = trim($_POST['email']    ?? '');   // validated separately
$subject = whitelist($_POST['subject'] ?? '', [
    'Course Enquiry', 'Application Help', 'Fees & Payment', 'General Question', 'Other'
]) ?: 'General Enquiry';
$message = clean($_POST['message'] ?? '', 1500);

// ══════════════════════════════════════════════════════
//  VALIDATE
// ══════════════════════════════════════════════════════
$errors = [];
if (mb_strlen($name, 'UTF-8') < 2)                        $errors[] = 'Name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (mb_strlen($message, 'UTF-8') < 5)                     $errors[] = 'Message is too short.';

// Sanitize email after validation
$email = htmlspecialchars($email, ENT_QUOTES | ENT_HTML5, 'UTF-8');

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ══════════════════════════════════════════════════════
//  BUILD EMAIL
// ══════════════════════════════════════════════════════
$date = date('Y-m-d H:i:s T');

ob_start(); ?>
<html><body style="font-family:Arial,sans-serif;color:#111;background:#f3f4f6;padding:20px;">
<div style="max-width:600px;margin:0 auto;border:1px solid #ddd;border-radius:8px;overflow:hidden;background:#fff;">
  <div style="background:#1a56db;padding:24px;text-align:center;">
    <h2 style="color:#f5b800;margin:0;">Nova Computer Academy</h2>
    <p style="color:#fff;margin:6px 0 0;font-size:14px;">New Contact Message</p>
  </div>
  <div style="padding:24px;">
    <p style="background:#e8f0fe;padding:10px 14px;border-radius:6px;font-size:13px;color:#1341a8;">
      Received: <strong><?= $date ?></strong>
    </p>
    <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:20px;">
      <tr><td style="padding:8px;background:#f9f9f9;width:30%;"><strong>From</strong></td><td style="padding:8px;"><?= $name ?></td></tr>
      <tr><td style="padding:8px;"><strong>Email</strong></td><td style="padding:8px;"><?= $email ?></td></tr>
      <tr><td style="padding:8px;background:#f9f9f9;"><strong>Subject</strong></td><td style="padding:8px;background:#f9f9f9;"><?= $subject ?></td></tr>
    </table>
    <h3 style="color:#1a56db;border-bottom:2px solid #f5b800;padding-bottom:6px;">Message</h3>
    <p style="font-size:14px;line-height:1.75;background:#f9f9f9;padding:16px;border-radius:6px;white-space:pre-wrap;"><?= $message ?></p>
  </div>
  <div style="background:#f3f4f6;padding:14px;text-align:center;font-size:12px;color:#6b7280;">
    Sent from novait.edu.lk — Reply directly to <?= $email ?>
  </div>
</div>
</body></html>
<?php
$body = ob_get_clean();

// ══════════════════════════════════════════════════════
//  SEND
// ══════════════════════════════════════════════════════
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_USER, 'Nova Academy Website');
    $mail->addAddress(MAIL_TO, 'Nova Academy');
    $mail->addReplyTo(filter_var($_POST['email'], FILTER_VALIDATE_EMAIL), $name);

    $mail->isHTML(true);
    $mail->Subject = "[Contact] {$subject} — {$name}";
    $mail->Body    = $body;
    $mail->AltBody = "From: {$name} ({$email}) | Subject: {$subject}\n\n{$_POST['message']}";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => "Thank you, {$name}! Your message has been sent. We will reply to {$email} within 1 business day.",
    ]);

} catch (Exception $e) {
    error_log('[NovaAcademy][contact] ' . $mail->ErrorInfo);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not send your message right now. Please email us directly at novaitacademy@gmail.com',
    ]);
}