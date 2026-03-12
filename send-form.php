<?php
/**
 * Nova Computer Academy — Application Form Handler
 * HARDENED: CSRF origin check · Honeypot · Rate limiting
 *           Null-byte removal · Control-char stripping · XSS encoding
 *           Input whitelisting · Strict regex validation
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '/var/www/novait.edu.lk/vendor/autoload.php';

// ══════════════════════════════════════════════════════
//  CONFIG  — edit only this block
// ══════════════════════════════════════════════════════
define('SMTP_USER',   'novaitacademy@gmail.com');
define('SMTP_PASS',   'akal iadn rrdc hyxv');   // Gmail App Password
define('MAIL_TO',     'novaitacademy@gmail.com');
define('MAIL_CC',     'gihanhareendra@gmail.com');
define('RATE_LIMIT',  5);     // max submissions per IP per hour
define('RATE_WINDOW', 3600);  // 1 hour in seconds
define('MAX_LEN',     500);   // max chars per general field
// ══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=UTF-8');

// ── 1. Method check ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── 2. CSRF: Origin / Referer check ──────────────────
$allowed = ['https://novait.edu.lk', 'https://www.novait.edu.lk'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
$ok = false;
foreach ($allowed as $a) { if (str_starts_with($origin, $a)) { $ok = true; break; } }
if (!$ok) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// ── 3. Honeypot bot trap ──────────────────────────────
// Add <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
// to your HTML form. Real users never fill it; bots do.
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Application received.']);
    exit;
}

// ── 4. Rate limiting (file-based) ────────────────────
$ip_hash  = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rl_dir   = sys_get_temp_dir() . '/nova_rl';
if (!is_dir($rl_dir)) mkdir($rl_dir, 0700, true);
$rl_file  = $rl_dir . '/' . $ip_hash . '_apply.json';
$now      = time();
$hits     = file_exists($rl_file) ? (json_decode(file_get_contents($rl_file), true) ?? []) : [];
$hits     = array_filter($hits, fn($t) => ($now - $t) < RATE_WINDOW);
if (count($hits) >= RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please wait an hour and try again.']);
    exit;
}
$hits[] = $now;
file_put_contents($rl_file, json_encode(array_values($hits)), LOCK_EX);

// ══════════════════════════════════════════════════════
//  SANITIZATION HELPERS
// ══════════════════════════════════════════════════════

/** General clean: strips tags, null bytes, control chars, encodes HTML, enforces length */
function clean(string $v, int $max = MAX_LEN): string {
    $v = str_replace("\0", '', $v);                                          // null bytes
    $v = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $v);      // control chars
    $v = strip_tags($v);                                                     // no HTML/PHP
    $v = trim($v);
    $v = mb_substr($v, 0, $max, 'UTF-8');                                    // length cap
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Whitelist: only accepts values from a predefined list */
function whitelist(string $v, array $list): string {
    $v = trim($v);
    return in_array($v, $list, true) ? $v : '';
}

/** Name: letters (Unicode for Sinhala/Tamil), spaces, dots, hyphens, apostrophes */
function clean_name(string $v): string {
    $v = clean($v, 120);
    $v = html_entity_decode($v, ENT_QUOTES, 'UTF-8');
    $v = preg_replace('/[^\p{L}\p{M}\s.\-\']/u', '', $v);
    return htmlspecialchars(trim($v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Address: letters, numbers, common punctuation — no scripts */
function clean_address(string $v): string {
    $v = clean($v, 350);
    $v = html_entity_decode($v, ENT_QUOTES, 'UTF-8');
    $v = preg_replace('/[^\p{L}\p{M}0-9\s,.\-\/\'#]/u', '', $v);
    return htmlspecialchars(trim($v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ══════════════════════════════════════════════════════
//  COLLECT & SANITIZE
// ══════════════════════════════════════════════════════
$fullname = clean_name($_POST['fullname'] ?? '');
$dob      = clean($_POST['dob']      ?? '', 10);
$gender   = whitelist($_POST['gender'] ?? '', ['Male', 'Female', 'Prefer not to say']);
$nic      = preg_replace('/\D/', '', $_POST['nic']      ?? '');  // digits only
$address  = clean_address($_POST['address'] ?? '');
$mobile   = preg_replace('/\D/', '', $_POST['mobile']   ?? '');  // digits only
$whatsapp = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');  // digits only
$course   = whitelist($_POST['course'] ?? '', [
    'ICTT – NVQ Level 3',
    'MS Office & Computer Basics',
    'Hardware & Networking',
]);
$notes    = clean($_POST['notes'] ?? '', 800);

// ══════════════════════════════════════════════════════
//  VALIDATE
// ══════════════════════════════════════════════════════
$errors = [];
if (mb_strlen($fullname, 'UTF-8') < 2)     $errors[] = 'Full name is required (min 2 characters).';
if (empty($dob) || !strtotime($dob))        $errors[] = 'A valid date of birth is required.';
if (!empty($dob) && strtotime($dob) >= $now) $errors[] = 'Date of birth must be in the past.';
if (empty($gender))                          $errors[] = 'Please select a valid gender.';
if (!preg_match('/^\d{12}$/', $nic))         $errors[] = 'NIC must be exactly 12 digits.';
if (mb_strlen($address, 'UTF-8') < 5)        $errors[] = 'Address is required.';
if (!preg_match('/^0\d{9}$/', $mobile))      $errors[] = 'Mobile must be a valid 10-digit number (07XXXXXXXX).';
if (!empty($whatsapp) && !preg_match('/^0\d{9}$/', $whatsapp))
                                             $errors[] = 'WhatsApp must be a valid 10-digit number.';
if (empty($course))                          $errors[] = 'Please select a valid course from the list.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ══════════════════════════════════════════════════════
//  BUILD HTML EMAIL
// ══════════════════════════════════════════════════════
$date       = date('Y-m-d H:i:s T');
$wa_display = $whatsapp ?: 'Not provided';

ob_start(); ?>
<html><body style="font-family:Arial,sans-serif;color:#111;background:#f3f4f6;padding:20px;">
<div style="max-width:600px;margin:0 auto;border:1px solid #ddd;border-radius:8px;overflow:hidden;background:#fff;">
  <div style="background:#1a56db;padding:24px;text-align:center;">
    <h2 style="color:#f5b800;margin:0;">Nova Computer Academy</h2>
    <p style="color:#fff;margin:6px 0 0;font-size:14px;">New Online Application Received</p>
  </div>
  <div style="padding:24px;">
    <p style="background:#e8f0fe;padding:10px 14px;border-radius:6px;font-size:13px;color:#1341a8;">
      Received: <strong><?= $date ?></strong>
    </p>
    <h3 style="color:#1a56db;border-bottom:2px solid #f5b800;padding-bottom:6px;">Course Applied For</h3>
    <p style="font-size:16px;font-weight:bold;background:#fff8e1;padding:10px;border-radius:6px;"><?= $course ?></p>
    <h3 style="color:#1a56db;border-bottom:2px solid #f5b800;padding-bottom:6px;">Personal Details</h3>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr><td style="padding:8px;background:#f9f9f9;width:38%;"><strong>Full Name</strong></td><td style="padding:8px;"><?= $fullname ?></td></tr>
      <tr><td style="padding:8px;"><strong>Date of Birth</strong></td><td style="padding:8px;"><?= $dob ?></td></tr>
      <tr><td style="padding:8px;background:#f9f9f9;"><strong>Gender</strong></td><td style="padding:8px;background:#f9f9f9;"><?= $gender ?></td></tr>
      <tr><td style="padding:8px;"><strong>NIC</strong></td><td style="padding:8px;"><?= $nic ?></td></tr>
      <tr><td style="padding:8px;background:#f9f9f9;"><strong>Address</strong></td><td style="padding:8px;background:#f9f9f9;"><?= $address ?></td></tr>
    </table>
    <h3 style="color:#1a56db;border-bottom:2px solid #f5b800;padding-bottom:6px;margin-top:20px;">Contact Details</h3>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr><td style="padding:8px;background:#f9f9f9;width:38%;"><strong>Mobile</strong></td><td style="padding:8px;background:#f9f9f9;"><?= $mobile ?></td></tr>
      <tr><td style="padding:8px;"><strong>WhatsApp</strong></td><td style="padding:8px;"><?= $wa_display ?></td></tr>
    </table>
    <?php if ($notes): ?>
    <h3 style="color:#1a56db;border-bottom:2px solid #f5b800;padding-bottom:6px;margin-top:20px;">Notes</h3>
    <p style="font-size:14px;background:#f9f9f9;padding:12px;border-radius:6px;"><?= $notes ?></p>
    <?php endif; ?>
  </div>
  <div style="background:#f3f4f6;padding:14px;text-align:center;font-size:12px;color:#6b7280;">
    Sent automatically from novait.edu.lk
  </div>
</div>
</body></html>
<?php
$body = ob_get_clean();

// ══════════════════════════════════════════════════════
//  SEND EMAIL
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

    $mail->setFrom(SMTP_USER, 'Nova Computer Academy');
    $mail->addAddress(MAIL_TO, 'Nova Academy');
    $mail->addCC(MAIL_CC);

    $mail->isHTML(true);
    $mail->Subject = "[Application] {$fullname} — {$course}";
    $mail->Body    = $body;
    $mail->AltBody = "Application: {$fullname} | {$course} | Mobile: {$mobile} | NIC: {$nic} | {$date}";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => "Thank you, {$fullname}! Your application has been received. We will contact you on {$mobile} within 1-2 business days.",
    ]);

} catch (Exception $e) {
    error_log('[NovaAcademy][apply] ' . $mail->ErrorInfo);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not send your application right now. Please call us or WhatsApp us directly.',
    ]);
}