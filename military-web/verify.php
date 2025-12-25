<?php
/**
 * Military SheerID Verification - Process Handler
 * 
 * Xử lý xác minh và xóa dữ liệu sau khi sử dụng
 */

define('MILITARY_VERIFY', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/VeteranDataReader.php';
require_once __DIR__ . '/includes/SheerIDVerifier.php';
require_once __DIR__ . '/includes/ProxyHandler.php';

// Chỉ chấp nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Lấy dữ liệu từ form
$sheeridUrl = trim($_POST['sheerid_url'] ?? '');
$email = trim($_POST['email'] ?? '');
$militaryStatus = $_POST['military_status'] ?? 'VETERAN';
$organizationId = intval($_POST['organization_id'] ?? 4070);
$organizationName = $_POST['organization_name'] ?? 'Army';

// Thông tin cá nhân từ form
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$birthDate = $_POST['birth_date'] ?? '';
$dischargeDate = $_POST['discharge_date'] ?? '';

// Thông tin gốc để xóa (với source file cho multi-file support)
$originalFirstName = $_POST['original_first_name'] ?? $firstName;
$originalLastName = $_POST['original_last_name'] ?? $lastName;
$sourceFile = $_POST['source_file'] ?? '';

// Proxy settings
$useProxy = isset($_POST['use_proxy']) && $_POST['use_proxy'] == '1';
$proxyList = $_POST['proxy_list'] ?? '';
$skipProxy = isset($_POST['skip_proxy']) && $_POST['skip_proxy'] == '1';

// Validate URL
$verificationId = SheerIDVerifier::parseVerificationId($sheeridUrl);
if (!$verificationId) {
    $error = 'URL không hợp lệ. Không tìm thấy verificationId.';
}

// Validate required fields
if (!isset($error)) {
    if (empty($firstName) || empty($lastName)) {
        $error = 'Thiếu thông tin họ tên.';
    } elseif (empty($birthDate)) {
        $error = 'Thiếu ngày sinh.';
    } elseif (empty($email)) {
        $error = 'Vui lòng nhập email.';
    } elseif (empty($dischargeDate)) {
        $error = 'Thiếu ngày xuất ngũ.';
    }
}

// Chuẩn bị thông tin cá nhân
$personalInfo = [
    'first_name' => $firstName,
    'last_name' => $lastName,
    'birth_date' => $birthDate,
    'discharge_date' => $dischargeDate,
    'email' => $email,
    'phone_number' => ''
];

$organization = [
    'id' => $organizationId,
    'name' => $organizationName
];

// Thực hiện xác minh
$result = null;
$deleteResult = false;
$newCount = 0;
$usedProxy = null;
$proxyInfo = null;
$noProxyAvailable = false;
$deadProxies = [];

if (!isset($error)) {
    $proxy = null;
    
    // Xử lý proxy nếu được bật và không bỏ qua
    if ($useProxy && !$skipProxy && !empty(trim($proxyList))) {
        $proxyHandler = new ProxyHandler($proxyList);
        
        if ($proxyHandler->getCount() > 0) {
            // Tìm proxy hoạt động
            $proxy = $proxyHandler->getWorkingProxy(5);
            $deadProxies = $proxyHandler->getDeadProxies();
            
            if ($proxy === null) {
                // Không có proxy nào hoạt động - hiển thị dialog trực tiếp
                $noProxyAvailable = true;
            } else {
                $usedProxy = $proxy['original'];
                $proxyInfo = $proxy;
            }
        }
    }
}

// Nếu không có proxy available - hiển thị trang hỏi user
if ($noProxyAvailable && !isset($error)):
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cảnh báo Proxy - Military SheerID</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎖️</text></svg>">
    <style>
        .warning-box {
            background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            margin: 50px auto;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(237, 137, 54, 0.4);
        }
        .warning-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .warning-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .warning-message {
            font-size: 1rem;
            opacity: 0.95;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .warning-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .warning-btn {
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 1rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .warning-btn-yes {
            background: white;
            color: #ed8936;
        }
        .warning-btn-yes:hover {
            background: #fffaf0;
            transform: translateY(-2px);
        }
        .warning-btn-no {
            background: rgba(0,0,0,0.2);
            color: white;
        }
        .warning-btn-no:hover {
            background: rgba(0,0,0,0.3);
        }
        .dead-proxy-list {
            background: rgba(0,0,0,0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 0.85rem;
            text-align: left;
        }
        .dead-proxy-list strong {
            display: block;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>
                <span class="icon">🎖️</span>
                Military Verification
            </h1>
        </header>

        <div class="warning-box">
            <div class="warning-icon">⚠️</div>
            <div class="warning-title">Không có proxy nào hoạt động</div>
            <div class="warning-message">
                Tất cả <?= count($deadProxies) ?> proxy trong danh sách đều không hoạt động.<br>
                Bạn có muốn gửi request trực tiếp không?
            </div>
            <div class="warning-buttons">
                <form method="POST" action="verify.php" style="display: inline;">
                    <?php foreach ($_POST as $key => $value): ?>
                        <?php if ($key !== 'proxy_list'): ?>
                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="skip_proxy" value="1">
                    <button type="submit" class="warning-btn warning-btn-yes">✅ Có, gửi trực tiếp</button>
                </form>
                <a href="index.php" class="warning-btn warning-btn-no">❌ Không, quay lại</a>
            </div>
            
            <?php if (!empty($deadProxies)): ?>
            <div class="dead-proxy-list">
                <strong>☠️ Proxy đã chết:</strong>
                <?php foreach ($deadProxies as $dp): ?>
                    <div>• <?= htmlspecialchars($dp['original']) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
exit;
endif;

// Thực hiện xác minh (nếu không bị chặn bởi proxy warning)
if (!isset($error) && !$noProxyAvailable) {
    $verifier = new SheerIDVerifier($verificationId, $proxy, $sheeridUrl);
    $result = $verifier->verify($personalInfo, $organization, $militaryStatus);
    
    // XÓA RECORD SAU KHI XÁC MINH (dù thành công hay thất bại)
    // Sử dụng source_file để xóa đúng file chứa record
    $deleteResult = VeteranDataReader::deleteRecord($sourceFile, $originalFirstName, $originalLastName, $birthDate);
    
    // Lấy số lượng records mới (từ tất cả files)
    $newCount = VeteranDataReader::getTotalRecordsFromFiles();
}

// Organization icons
$orgIcons = [
    'Army' => '⚔️',
    'Air Force' => '✈️',
    'Navy' => '⚓',
    'Marine Corps' => '🦅',
    'Coast Guard' => '🚢',
    'Space Force' => '🚀'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả xác minh - Military SheerID</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎖️</text></svg>">
    <style>
        .counter-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .counter-number {
            font-size: 3rem;
            font-weight: 700;
            display: block;
            line-height: 1;
        }
        .counter-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-top: 5px;
        }
        .delete-status {
            margin-top: 15px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .delete-success {
            background: #c6f6d5;
            color: #22543d;
        }
        .delete-failed {
            background: #feebc8;
            color: #744210;
        }
        .proxy-status {
            margin-top: 10px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            background: #e9d8fd;
            color: #553c9a;
        }
        .proxy-status.no-proxy {
            background: #fed7d7;
            color: #742a2a;
        }
        .dead-proxies-info {
            margin-top: 10px;
            padding: 8px 12px;
            background: #fef5e7;
            border: 1px solid #f6ad55;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #744210;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>
                <span class="icon">🎖️</span>
                Kết quả xác minh
            </h1>
            <p>Military SheerID Verification Result</p>
        </header>

        <!-- Updated Counter -->
        <?php if (!isset($error)): ?>
        <div class="counter-box">
            <span class="counter-number"><?= $newCount ?></span>
            <span class="counter-label">📊 Records Veteran còn lại</span>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <!-- Error Card -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);">
                <h2>
                    <span>❌</span>
                    Lỗi xác minh
                </h2>
            </div>
            <div class="card-body">
                <div class="alert alert-error">
                    <span>❌</span>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
                <a href="index.php" class="btn btn-primary">← Quay lại</a>
            </div>
        </div>

        <?php elseif ($result): ?>
        <!-- Result Card -->
        <div class="card">
            <?php 
            // Xác định màu và tiêu đề dựa trên kết quả
            $isPending = !empty($result['pending']) || !empty($result['email_verification_required']);
            if ($result['success'] && $isPending) {
                $headerBg = '#667eea, #764ba2'; // Purple for pending
                $headerIcon = '📧';
                $headerTitle = 'Gửi thông tin thành công';
            } elseif ($result['success']) {
                $headerBg = '#38a169, #276749'; // Green for success
                $headerIcon = '✅';
                $headerTitle = 'Xác minh thành công';
            } else {
                $headerBg = '#e53e3e, #c53030'; // Red for failure
                $headerIcon = '❌';
                $headerTitle = 'Xác minh thất bại';
            }
            ?>
            <div class="card-header" style="background: linear-gradient(135deg, <?= $headerBg ?>);">
                <h2>
                    <span><?= $headerIcon ?></span>
                    <?= $headerTitle ?>
                </h2>
            </div>
            <div class="card-body">
                <!-- Status Alert -->
                <?php if ($result['success'] && $isPending): ?>
                <div class="alert alert-info" style="background: #ebf8ff; border: 1px solid #90cdf4; color: #2a4365;">
                    <span>📧</span>
                    <div>
                        <strong><?= htmlspecialchars($result['message']) ?></strong>
                        <br><br>
                        <small>⏳ Vui lòng kiểm tra email và nhấn vào link xác nhận để hoàn tất quá trình xác minh.</small>
                    </div>
                </div>
                <?php elseif ($result['success']): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div>
                        <strong><?= htmlspecialchars($result['message']) ?></strong>
                        <?php if (!empty($result['redirect_url'])): ?>
                        <br><br>
                        <a href="<?= htmlspecialchars($result['redirect_url']) ?>" target="_blank" class="btn btn-success" style="margin-top: 10px;">
                            🔗 Mở liên kết chuyển hướng
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-error">
                    <span>❌</span>
                    <div>
                        <strong><?= htmlspecialchars($result['message']) ?></strong>
                        <?php if (!empty($result['requires_document'])): ?>
                        <br><small>Hệ thống yêu cầu tải lên tài liệu xác minh bổ sung.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Delete Status -->
                <div class="delete-status <?= $deleteResult ? 'delete-success' : 'delete-failed' ?>">
                    <?php if ($deleteResult): ?>
                        🗑️ Record đã được xóa khỏi database. Còn lại: <strong><?= $newCount ?></strong> records.
                    <?php else: ?>
                        ⚠️ Không thể xóa record (có thể đã bị xóa trước đó).
                    <?php endif; ?>
                </div>

                <!-- Proxy Status -->
                <?php if ($usedProxy): ?>
                <div class="proxy-status">
                    🔐 Request được gửi qua proxy: <strong><?= htmlspecialchars($usedProxy) ?></strong>
                </div>
                <?php elseif ($useProxy && $skipProxy): ?>
                <div class="proxy-status no-proxy">
                    ⚠️ Proxy đã bị bỏ qua - Request gửi trực tiếp
                </div>
                <?php elseif (!$useProxy): ?>
                <!-- No proxy used, nothing to show -->
                <?php endif; ?>

                <?php if (!empty($deadProxies)): ?>
                <div class="dead-proxies-info">
                    ☠️ <?= count($deadProxies) ?> proxy đã chết và bị loại bỏ:
                    <small><?= htmlspecialchars(implode(', ', array_map(fn($p) => $p['original'], $deadProxies))) ?></small>
                </div>
                <?php endif; ?>

                <!-- Personal Info Used -->
                <div class="result-box" style="margin-top: 20px;">
                    <h3>👤 Thông tin đã sử dụng</h3>
                    <div class="result-item">
                        <span class="label">Họ tên:</span>
                        <span class="value"><?= htmlspecialchars($result['personal_info']['first_name'] . ' ' . $result['personal_info']['last_name']) ?></span>
                    </div>
                    <div class="result-item">
                        <span class="label">Ngày sinh:</span>
                        <span class="value"><?= htmlspecialchars($result['personal_info']['birth_date']) ?></span>
                    </div>
                    <div class="result-item">
                        <span class="label">Email:</span>
                        <span class="value"><?= htmlspecialchars($result['personal_info']['email']) ?></span>
                    </div>
                    <div class="result-item">
                        <span class="label">Ngày xuất ngũ:</span>
                        <span class="value"><?= htmlspecialchars($result['personal_info']['discharge_date']) ?></span>
                    </div>
                    <div class="result-item">
                        <span class="label">Đơn vị:</span>
                        <span class="value"><?= $orgIcons[$result['organization']['name']] ?? '🎖️' ?> <?= htmlspecialchars($result['organization']['name']) ?></span>
                    </div>
                    <div class="result-item">
                        <span class="label">Trạng thái:</span>
                        <span class="value"><span class="badge badge-success"><?= htmlspecialchars($militaryStatus) ?></span></span>
                    </div>
                </div>

                <!-- Technical Details (Collapsible) -->
                <details style="margin-top: 20px;">
                    <summary style="cursor: pointer; font-weight: 600; color: var(--primary-color);">
                        🔧 Chi tiết kỹ thuật
                    </summary>
                    <div class="result-box" style="margin-top: 10px;">
                        <h4>Verification ID:</h4>
                        <code style="word-break: break-all;"><?= htmlspecialchars($verificationId) ?></code>

                        <h4 style="margin-top: 15px;">Step được thực hiện:</h4>
                        <code><?= htmlspecialchars($result['step']) ?></code>

                        <?php if (!empty($result['step1_response'])): ?>
                        <h4 style="margin-top: 15px;">Response Bước 1 (collectMilitaryStatus):</h4>
                        <div class="code-block">
                            <pre><?= htmlspecialchars(json_encode($result['step1_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($result['step2_response'])): ?>
                        <h4 style="margin-top: 15px;">Response Bước 2 (collectPersonalInfo):</h4>
                        <div class="code-block">
                            <pre><?= htmlspecialchars(json_encode($result['step2_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                </details>

                <!-- Action Buttons -->
                <div style="margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-primary">← Xác minh tiếp</a>
                    <?php if ($result['success'] && !empty($result['redirect_url'])): ?>
                    <a href="<?= htmlspecialchars($result['redirect_url']) ?>" target="_blank" class="btn btn-success">
                        🔗 Tiếp tục đến ChatGPT
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="footer">
            <p>Military SheerID Verification Tool &copy; <?= date('Y') ?></p>
        </footer>
    </div>
</body>
</html>
