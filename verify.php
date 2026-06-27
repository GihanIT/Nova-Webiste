<?php
/**
 * Nova Computer Academy — Certificate Verification Endpoint
 * GET  ?id=NCA-CCA-2026-00017          → lookup by certificate number
 * GET  ?nic=XXXXXXXXXXXX               → lookup by NIC
 * GET  ?id=...&nic=...                 → dual verification (both must match)
 */

// ── CONFIG ────────────────────────────────────────────
define('CERTS_FILE',  __DIR__ . '/data/certificates.json');
define('RATE_LIMIT',  30);    // max lookups per IP per hour
define('RATE_WINDOW', 3600);
// ─────────────────────────────────────────────────────

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://novait.edu.lk');
header('X-Content-Type-Options: nosniff');

// ── Method ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Rate limiting ────────────────────────────────────
$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rl_dir  = sys_get_temp_dir() . '/nova_rl';
if (!is_dir($rl_dir)) mkdir($rl_dir, 0700, true);
$rl_file = $rl_dir . '/' . $ip_hash . '_verify.json';
$now     = time();
$hits    = file_exists($rl_file) ? (json_decode(file_get_contents($rl_file), true) ?? []) : [];
$hits    = array_filter($hits, fn($t) => ($now - $t) < RATE_WINDOW);
if (count($hits) >= RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait and try again.']);
    exit;
}
$hits[] = $now;
file_put_contents($rl_file, json_encode(array_values($hits)), LOCK_EX);

// ── Input sanitization ───────────────────────────────
function clean_input(string $v, int $max): string {
    $v = str_replace("\0", '', $v);
    $v = preg_replace('/[\x01-\x1F\x7F]/', '', $v);
    $v = trim(strip_tags($v));
    return mb_substr($v, 0, $max, 'UTF-8');
}

$raw_id  = clean_input($_GET['id']  ?? '', 30);
$raw_nic = clean_input($_GET['nic'] ?? '', 12);

// ── Validate at least one input present ──────────────
if ($raw_id === '' && $raw_nic === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a certificate number or NIC.']);
    exit;
}

// ── Format validation ─────────────────────────────────
if ($raw_id !== '' && !preg_match('/^NCA-[A-Z]{3}-\d{4}-\d{5}$/', $raw_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid certificate number format. Expected: NCA-XXX-YYYY-NNNNN']);
    exit;
}

if ($raw_nic !== '' && !preg_match('/^\d{12}$/', $raw_nic)) {
    echo json_encode(['success' => false, 'message' => 'Invalid NIC format. Must be 12 digits.']);
    exit;
}

// ── Load certificates ─────────────────────────────────
if (!file_exists(CERTS_FILE)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Certificate database unavailable.']);
    exit;
}

$certs = json_decode(file_get_contents(CERTS_FILE), true);
if (!is_array($certs)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Certificate database error.']);
    exit;
}

// ── Search ────────────────────────────────────────────
$found = null;

foreach ($certs as $cert) {
    $id_match  = ($raw_id  === '' || strtoupper($cert['cert_id']) === strtoupper($raw_id));
    $nic_match = ($raw_nic === '' || $cert['nic'] === $raw_nic);

    if ($id_match && $nic_match) {
        $found = $cert;
        break;
    }
}

// ── Response ──────────────────────────────────────────
if (!$found) {
    // Searched both fields but no match
    if ($raw_id !== '' && $raw_nic !== '') {
        echo json_encode([
            'success' => false,
            'message' => 'No certificate found matching both the certificate number and NIC provided. Please check your details.'
        ]);
    } elseif ($raw_id !== '') {
        echo json_encode([
            'success' => false,
            'message' => 'No certificate found with that certificate number.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No certificate found for that NIC number.'
        ]);
    }
    exit;
}

// ── Build safe response (never expose full NIC) ───────
$masked_nic = substr($found['nic'], 0, 4) . '****' . substr($found['nic'], -4);

// Determine status label
$status_map = [
    'valid'   => ['label' => 'Valid',   'color' => 'green'],
    'revoked' => ['label' => 'Revoked', 'color' => 'red'],
    'expired' => ['label' => 'Expired', 'color' => 'orange'],
];

// Auto-check expiry
$status = $found['status'];
if ($status === 'valid' && !empty($found['expiry_date']) && strtotime($found['expiry_date']) < $now) {
    $status = 'expired';
}

$status_info = $status_map[$status] ?? ['label' => 'Unknown', 'color' => 'gray'];

// Format dates for display
function fmt_date(string $d): string {
    $ts = strtotime($d);
    return $ts ? date('d F Y', $ts) : $d;
}

echo json_encode([
    'success'     => true,
    'status'      => $status,
    'status_label'=> $status_info['label'],
    'status_color'=> $status_info['color'],
    'cert_id'     => htmlspecialchars($found['cert_id'],     ENT_QUOTES, 'UTF-8'),
    'name'        => htmlspecialchars($found['name'],         ENT_QUOTES, 'UTF-8'),
    'course_code' => htmlspecialchars($found['course_code'],  ENT_QUOTES, 'UTF-8'),
    'course_name' => htmlspecialchars($found['course_name'],  ENT_QUOTES, 'UTF-8'),
    'issue_date'  => fmt_date($found['issue_date']),
    'expiry_date' => $found['expiry_date'] ? fmt_date($found['expiry_date']) : null,
    'nic_masked'  => $masked_nic,
]);