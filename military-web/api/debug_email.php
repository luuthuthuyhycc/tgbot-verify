<?php
/**
 * API Debug Email - Xem email body để debug
 */

header('Content-Type: application/json');

define('MILITARY_VERIFY', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/EmailReader.php';

// Lấy thông tin từ request
$toEmail = $_POST['to_email'] ?? $_GET['to_email'] ?? '';

if (empty($toEmail)) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu to_email parameter'
    ]);
    exit;
}

// Kết nối IMAP
$emailReader = new EmailReader();

if (!$emailReader->connect()) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể kết nối IMAP: ' . $emailReader->getLastError()
    ]);
    exit;
}

// Tìm email
$emailData = $emailReader->findSheerIDEmail($toEmail);
$emailReader->disconnect();

if ($emailData === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy email cho ' . $toEmail
    ]);
    exit;
}

// Lấy body
$body = $emailData['body'];

// Parse emailToken
$emailToken = EmailReader::parseEmailToken($body);

// Tìm tất cả URLs trong email
$urls = [];
preg_match_all('/https?:\/\/[^\s<>"\']+/i', $body, $urlMatches);
if (!empty($urlMatches[0])) {
    $urls = $urlMatches[0];
}

// Tìm tất cả token patterns
$tokenPatterns = [];
preg_match_all('/emailToken[=\/:]+([a-zA-Z0-9_-]+)/i', $body, $tokenMatches);
if (!empty($tokenMatches[0])) {
    $tokenPatterns = $tokenMatches[0];
}

// Trả về debug info chi tiết
echo json_encode([
    'success' => true,
    'email' => [
        'subject' => $emailData['subject'],
        'from' => $emailData['from'],
        'to' => $emailData['to'],
        'date' => $emailData['date'],
        'body_length' => strlen($body),
        'body_preview' => substr($body, 0, 3000),
        'body_contains_emailToken' => strpos($body, 'emailToken') !== false,
        'body_contains_token' => stripos($body, 'token') !== false,
        'body_contains_sheerid' => stripos($body, 'sheerid') !== false,
        'body_contains_verify' => stripos($body, 'verify') !== false,
    ],
    'urls_found' => $urls,
    'token_patterns' => $tokenPatterns,
    'parsed_token' => $emailToken,
    'raw_body_base64' => base64_encode($body)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
