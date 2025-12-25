<?php
/**
 * API Check IMAP Connection
 * 
 * Kiểm tra kết nối IMAP với thông tin được cung cấp
 */

header('Content-Type: application/json');

// Ngăn truy cập nếu không phải POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Lấy thông tin từ request
$host = trim($_POST['host'] ?? '');
$port = intval($_POST['port'] ?? 993);
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Nếu không có password, thử load từ config
if (empty($password)) {
    define('MILITARY_VERIFY', true);
    require_once __DIR__ . '/../config.php';
    
    $host = $host ?: (defined('IMAP_HOST') ? IMAP_HOST : '');
    $port = $port ?: (defined('IMAP_PORT') ? IMAP_PORT : 993);
    $username = $username ?: (defined('IMAP_USERNAME') ? IMAP_USERNAME : '');
    $password = defined('IMAP_PASSWORD') ? IMAP_PASSWORD : '';
}

// Validate
if (empty($host)) {
    echo json_encode([
        'success' => false,
        'message' => 'IMAP Host không được để trống'
    ]);
    exit;
}

if (empty($username)) {
    echo json_encode([
        'success' => false,
        'message' => 'IMAP Username không được để trống'
    ]);
    exit;
}

if (empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'IMAP Password không được để trống'
    ]);
    exit;
}

// Kiểm tra extension imap - nếu không có thì thử Python fallback
$usePythonFallback = !function_exists('imap_open');

if ($usePythonFallback) {
    // Thử dùng Python để check IMAP
    $scriptPath = __DIR__ . '/../scripts/imap_reader.py';
    
    if (!file_exists($scriptPath)) {
        echo json_encode([
            'success' => false,
            'message' => 'PHP IMAP extension không có và Python script cũng không tìm thấy.'
        ]);
        exit;
    }
    
    $mailbox = sprintf('{%s:%d/imap/ssl}INBOX', $host, $port);
    
    // Test bằng cách tìm bất kỳ email nào (dùng email test không tồn tại)
    $command = sprintf(
        'python3 %s %s %s %s %s %d 2>&1',
        escapeshellarg($scriptPath),
        escapeshellarg($mailbox),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg('test-connection-check@nonexistent.com'),
        5  // timeout 5 giây
    );
    
    $output = shell_exec($command);
    $result = json_decode($output, true);
    
    // Nếu có error về login thì fail, ngược lại thành công (kể cả không tìm thấy email)
    if ($result && isset($result['error']) && stripos($result['error'], 'authentication') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Sai username hoặc password. Với Gmail, hãy sử dụng App Password.',
            'method' => 'python'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Kết nối IMAP thành công! (sử dụng Python fallback)',
            'method' => 'python',
            'details' => [
                'host' => $host,
                'port' => $port,
                'username' => $username
            ]
        ]);
    }
    exit;
}

// Thử kết nối IMAP với PHP extension
$mailbox = sprintf(
    '{%s:%d/imap/ssl/novalidate-cert}INBOX',
    $host,
    $port
);

// Suppress warnings và thử kết nối
$connection = @imap_open($mailbox, $username, $password, 0, 1, [
    'DISABLE_AUTHENTICATOR' => 'GSSAPI'
]);

if ($connection) {
    // Lấy thông tin mailbox
    $mailboxInfo = imap_check($connection);
    $numMessages = $mailboxInfo ? $mailboxInfo->Nmsgs : 0;
    
    imap_close($connection);
    
    echo json_encode([
        'success' => true,
        'message' => "Kết nối IMAP thành công! Inbox có {$numMessages} email.",
        'method' => 'php_imap',
        'details' => [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'messages' => $numMessages
        ]
    ]);
} else {
    $error = imap_last_error();
    
    // Clear error stack
    imap_errors();
    imap_alerts();
    
    // Friendly error messages
    $friendlyError = $error;
    if (stripos($error, 'AUTHENTICATIONFAILED') !== false) {
        $friendlyError = 'Sai username hoặc password. Với Gmail, hãy sử dụng App Password.';
    } elseif (stripos($error, 'Connection refused') !== false) {
        $friendlyError = 'Không thể kết nối đến server. Kiểm tra host và port.';
    } elseif (stripos($error, 'Connection timed out') !== false) {
        $friendlyError = 'Kết nối timeout. Kiểm tra host và port.';
    } elseif (stripos($error, 'certificate') !== false) {
        $friendlyError = 'Lỗi SSL certificate. Server có thể không hỗ trợ SSL.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $friendlyError,
        'method' => 'php_imap',
        'raw_error' => $error
    ]);
}
