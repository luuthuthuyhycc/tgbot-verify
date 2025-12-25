<?php
/**
 * Military SheerID Verification - Main Page
 * 
 * Trang chủ - Tự động load dữ liệu từ TẤT CẢ file JSON trong thư mục data/
 */

define('MILITARY_VERIFY', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/VeteranDataReader.php';
require_once __DIR__ . '/includes/SheerIDVerifier.php';

// Khởi tạo data reader và lấy record ngẫu nhiên
$dataReader = new VeteranDataReader();
$totalRecords = $dataReader->getTotalRecords();
$fileStats = $dataReader->getFileStats();
$veteranRecord = $dataReader->getRandomRecord();

// Chuẩn bị dữ liệu veteran nếu có
$veteranData = null;
if ($veteranRecord) {
    $organization = VeteranDataReader::getOrganizationFromBranchId($veteranRecord['serviceBranchId']);
    $veteranData = [
        'first_name' => VeteranDataReader::formatName($veteranRecord['firstName']),
        'last_name' => VeteranDataReader::formatName($veteranRecord['lastName']),
        'birth_date' => $veteranRecord['date_of_birth'],
        'discharge_date' => VeteranDataReader::generateDischargeDate2025(),
        'organization_id' => $organization['id'],
        'organization_name' => $organization['name'],
        'service_branch_id' => $veteranRecord['serviceBranchId'],
        // Lưu thông tin gốc để xóa
        'original_first_name' => $veteranRecord['firstName'],
        'original_last_name' => $veteranRecord['lastName'],
        'source_file' => $veteranRecord['_source_file'] ?? ''
    ];
}

// API endpoint để lấy số lượng records hiện tại
if (isset($_GET['action']) && $_GET['action'] === 'get_count') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => VeteranDataReader::getTotalRecordsFromFiles()
    ]);
    exit;
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

// Đếm số file
$totalFiles = count($fileStats);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Military SheerID Verification</title>
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
        .no-data-warning {
            background: #fed7d7;
            color: #742a2a;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .stat-item {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.85rem;
        }
        .stat-item .file-name {
            font-weight: 600;
            color: #2d3748;
            word-break: break-all;
            margin-bottom: 5px;
        }
        .stat-item .stat-details {
            color: #718096;
        }
        .stat-item .stat-details span {
            display: inline-block;
            margin-right: 8px;
        }
        .files-summary {
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #2a4365;
        }
        /* Proxy Section */
        .proxy-section {
            margin-top: 20px;
            padding: 15px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .proxy-checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .proxy-checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .proxy-textarea-wrapper {
            margin-top: 15px;
            display: none;
        }
        .proxy-textarea-wrapper.show {
            display: block;
        }
        .proxy-textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            resize: vertical;
        }
        .proxy-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
        }
        .proxy-count {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #718096;
        }
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        .modal-overlay.show .modal-box {
            transform: scale(1);
        }
        .modal-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }
        .modal-message {
            color: #718096;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .modal-btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .modal-btn-yes {
            background: var(--primary-color);
            color: white;
        }
        .modal-btn-yes:hover {
            background: var(--primary-hover);
        }
        .modal-btn-no {
            background: #e2e8f0;
            color: #4a5568;
        }
        .modal-btn-no:hover {
            background: #cbd5e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>
                <span class="icon">🎖️</span>
                Military Verification
            </h1>
            <p>Hệ thống xác minh quân nhân ChatGPT qua SheerID</p>
        </header>

        <!-- Counter Box -->
        <div class="counter-box">
            <span class="counter-number" id="record-counter"><?= $totalRecords ?></span>
            <span class="counter-label">📊 Records Veteran còn lại (từ <?= $totalFiles ?> file)</span>
        </div>

        <!-- Main Form Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <span>📝</span>
                    Thông tin xác minh
                </h2>
            </div>
            <div class="card-body">
                <!-- Alert placeholder -->
                <div id="alert-container"></div>

                <?php if (!$veteranData): ?>
                <!-- No Data Warning -->
                <div class="no-data-warning">
                    <h3>⚠️ Hết dữ liệu Veteran</h3>
                    <p>Không còn record nào trong database.</p>
                    <p style="margin-top: 10px;">Vui lòng thêm file JSON vào thư mục <code>data/</code></p>
                </div>
                <?php else: ?>

                <form id="verify-form" action="verify.php" method="POST">
                    <!-- SheerID URL -->
                    <div class="form-group">
                        <label for="sheerid_url">🔗 SheerID URL <span style="color: #c53030">*</span></label>
                        <input type="url" 
                               id="sheerid_url" 
                               name="sheerid_url" 
                               class="form-control" 
                               placeholder="https://services.sheerid.com/verify/...?verificationId=..."
                               required>
                        <p class="hint">Nhập link xác minh SheerID từ ChatGPT</p>
                    </div>

                    <!-- Email Input -->
                    <div class="form-group">
                        <label for="email">📧 Email <span style="color: #c53030">*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="your.email@gmail.com"
                               required>
                        <p class="hint">Email sẽ được sử dụng để nhận kết quả xác minh</p>
                    </div>

                    <!-- Proxy Section -->
                    <div class="proxy-section">
                        <label class="proxy-checkbox-wrapper">
                            <input type="checkbox" id="use_proxy" name="use_proxy" value="1">
                            <span>🔒 Sử dụng Proxy</span>
                        </label>
                        <div id="proxy-textarea-wrapper" class="proxy-textarea-wrapper">
                            <label for="proxy_list" style="display: block; margin-bottom: 8px; font-weight: 500;">
                                Danh sách Proxy (mỗi proxy 1 dòng)
                            </label>
                            <textarea id="proxy_list" 
                                      name="proxy_list" 
                                      class="proxy-textarea" 
                                      placeholder="Username:Password@Host:Port&#10;user1:pass1@192.168.1.1:8080&#10;user2:pass2@proxy.example.com:3128"></textarea>
                            <div class="proxy-count">
                                📊 Số proxy: <strong id="proxy-count-number">0</strong>
                            </div>
                            <p class="hint" style="margin-top: 5px;">Format: Username:Password@Host:Port hoặc Host:Port:Username:Password</p>
                        </div>
                    </div>

                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;">

                    <!-- Hidden fields for status (default VETERAN) -->
                    <input type="hidden" name="military_status" value="VETERAN">

                    <!-- Veteran Data Preview (Auto-loaded) -->
                    <div class="form-group">
                        <label>👤 Thông tin Veteran (đã tự động load)</label>
                        <div id="veteran-preview" class="result-box" style="background: #f0fff4; border: 1px solid #9ae6b4;">
                            <div class="result-item">
                                <span class="label">Họ tên:</span>
                                <span class="value"><?= htmlspecialchars($veteranData['first_name'] . ' ' . $veteranData['last_name']) ?></span>
                            </div>
                            <div class="result-item">
                                <span class="label">Ngày sinh:</span>
                                <span class="value"><?= htmlspecialchars($veteranData['birth_date']) ?></span>
                            </div>
                            <div class="result-item">
                                <span class="label">Đơn vị:</span>
                                <span class="value"><?= $orgIcons[$veteranData['organization_name']] ?? '🎖️' ?> <?= htmlspecialchars($veteranData['organization_name']) ?></span>
                            </div>
                            <div class="result-item">
                                <span class="label">Ngày xuất ngũ:</span>
                                <span class="value"><?= htmlspecialchars($veteranData['discharge_date']) ?></span>
                            </div>
                            <div class="result-item">
                                <span class="label">Trạng thái:</span>
                                <span class="value"><span class="badge badge-success">VETERAN</span></span>
                            </div>
                        </div>
                        <p class="hint" style="margin-top: 10px;">💡 Refresh trang để load veteran khác</p>
                    </div>

                    <!-- Hidden fields to store veteran data -->
                    <input type="hidden" name="first_name" value="<?= htmlspecialchars($veteranData['first_name']) ?>">
                    <input type="hidden" name="last_name" value="<?= htmlspecialchars($veteranData['last_name']) ?>">
                    <input type="hidden" name="birth_date" value="<?= htmlspecialchars($veteranData['birth_date']) ?>">
                    <input type="hidden" name="discharge_date" value="<?= htmlspecialchars($veteranData['discharge_date']) ?>">
                    <input type="hidden" name="organization_id" value="<?= htmlspecialchars($veteranData['organization_id']) ?>">
                    <input type="hidden" name="organization_name" value="<?= htmlspecialchars($veteranData['organization_name']) ?>">
                    <!-- Thông tin gốc để xóa -->
                    <input type="hidden" name="original_first_name" value="<?= htmlspecialchars($veteranData['original_first_name']) ?>">
                    <input type="hidden" name="original_last_name" value="<?= htmlspecialchars($veteranData['original_last_name']) ?>">
                    <input type="hidden" name="source_file" value="<?= htmlspecialchars($veteranData['source_file']) ?>">

                    <!-- Submit Button -->
                    <button type="submit" id="btn-submit" class="btn btn-primary btn-block">
                        <span class="btn-text">🚀 Bắt đầu xác minh</span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner"></span>
                            Đang xử lý...
                        </span>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- File Stats Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <span>📁</span>
                    Thống kê file dữ liệu
                </h2>
            </div>
            <div class="card-body">
                <div class="files-summary">
                    📂 Thư mục: <code>data/</code> | 
                    📄 Số file: <strong><?= $totalFiles ?></strong> | 
                    ✅ Tổng records hợp lệ: <strong><?= $totalRecords ?></strong>
                </div>
                
                <?php if (!empty($fileStats)): ?>
                <div class="stats-grid">
                    <?php foreach ($fileStats as $fileName => $stats): ?>
                    <div class="stat-item">
                        <div class="file-name">📄 <?= htmlspecialchars($fileName) ?></div>
                        <div class="stat-details">
                            <span>📊 Total: <?= $stats['total'] ?></span>
                            <span>✅ Valid: <?= $stats['valid'] ?></span>
                            <?php if ($stats['duplicates'] > 0): ?>
                            <span>🔄 Dup: <?= $stats['duplicates'] ?></span>
                            <?php endif; ?>
                            <?php if ($stats['invalid_branch'] > 0): ?>
                            <span>❌ Invalid: <?= $stats['invalid_branch'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <span>⚠️</span>
                    <div>Không tìm thấy file JSON nào trong thư mục <code>data/</code></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <span>ℹ️</span>
                    Hướng dẫn sử dụng
                </h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <span>💡</span>
                    <div>
                        <strong>Các bước thực hiện:</strong>
                        <ol style="margin-top: 10px; padding-left: 20px;">
                            <li>Thông tin veteran đã được tự động load</li>
                            <li>Nhập SheerID URL từ trang đăng ký ChatGPT</li>
                            <li>Nhập email của bạn</li>
                            <li>Click "Bắt đầu xác minh"</li>
                            <li>Sau khi xác minh, dữ liệu sẽ tự động bị xóa</li>
                        </ol>
                    </div>
                </div>

                <div class="alert alert-success">
                    <span>📁</span>
                    <div>
                        <strong>Thêm dữ liệu mới:</strong> Chỉ cần copy file JSON vào thư mục <code>data/</code>, hệ thống sẽ tự động đọc và tổng hợp.
                    </div>
                </div>

                <div class="alert alert-warning">
                    <span>⚠️</span>
                    <div>
                        <strong>Lưu ý:</strong> Mỗi record chỉ được sử dụng một lần. Dữ liệu trùng lặp giữa các file sẽ tự động được loại bỏ.
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>Military SheerID Verification Tool &copy; <?= date('Y') ?></p>
            <p>
                <a href="https://github.com/PastKing/tgbot-verify" target="_blank">GitHub</a>
            </p>
        </footer>
    </div>

    <!-- Modal for proxy warning -->
    <div id="proxy-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">⚠️</div>
            <div class="modal-title">Không có proxy nào hoạt động</div>
            <div class="modal-message">
                Tất cả proxy trong danh sách đều không hoạt động.<br>
                Bạn có muốn gửi request trực tiếp không?
            </div>
            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-no" id="modal-btn-no">Không</button>
                <button type="button" class="modal-btn modal-btn-yes" id="modal-btn-yes">Có, gửi trực tiếp</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('verify-form');
            if (!form) return;
            
            const btnSubmit = document.getElementById('btn-submit');
            const btnText = btnSubmit.querySelector('.btn-text');
            const btnLoading = btnSubmit.querySelector('.btn-loading');
            
            // Proxy elements
            const useProxyCheckbox = document.getElementById('use_proxy');
            const proxyTextareaWrapper = document.getElementById('proxy-textarea-wrapper');
            const proxyList = document.getElementById('proxy_list');
            const proxyCountNumber = document.getElementById('proxy-count-number');
            const proxyModal = document.getElementById('proxy-modal');
            const modalBtnYes = document.getElementById('modal-btn-yes');
            const modalBtnNo = document.getElementById('modal-btn-no');
            
            // Load saved proxy from localStorage
            const savedProxies = localStorage.getItem('military_proxies');
            if (savedProxies) {
                proxyList.value = savedProxies;
                updateProxyCount();
            }
            
            // Toggle proxy textarea
            useProxyCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    proxyTextareaWrapper.classList.add('show');
                } else {
                    proxyTextareaWrapper.classList.remove('show');
                }
            });
            
            // Count proxies
            proxyList.addEventListener('input', function() {
                updateProxyCount();
                // Save to localStorage
                localStorage.setItem('military_proxies', this.value);
            });
            
            function updateProxyCount() {
                const lines = proxyList.value.split('\n').filter(line => line.trim() !== '');
                proxyCountNumber.textContent = lines.length;
            }
            
            // Modal handlers
            let pendingSubmit = false;
            
            modalBtnYes.addEventListener('click', function() {
                hideModal();
                // Submit without proxy
                useProxyCheckbox.checked = false;
                pendingSubmit = true;
                form.submit();
            });
            
            modalBtnNo.addEventListener('click', function() {
                hideModal();
                resetSubmitButton();
            });
            
            function showModal() {
                proxyModal.classList.add('show');
            }
            
            function hideModal() {
                proxyModal.classList.remove('show');
            }
            
            function resetSubmitButton() {
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                btnSubmit.disabled = false;
            }

            form.addEventListener('submit', function(e) {
                const url = document.getElementById('sheerid_url').value;
                const email = document.getElementById('email').value;
                
                if (!url.includes('sheerid.com') || !url.includes('verificationId')) {
                    e.preventDefault();
                    showAlert('error', 'URL không hợp lệ. Vui lòng nhập đúng link SheerID.');
                    return;
                }
                
                if (!email || !email.includes('@')) {
                    e.preventDefault();
                    showAlert('error', 'Vui lòng nhập email hợp lệ.');
                    return;
                }
                
                // Check proxy if enabled
                if (useProxyCheckbox.checked) {
                    const proxyLines = proxyList.value.split('\n').filter(line => line.trim() !== '');
                    if (proxyLines.length === 0) {
                        e.preventDefault();
                        showAlert('error', 'Vui lòng nhập ít nhất một proxy hoặc bỏ chọn "Sử dụng Proxy".');
                        return;
                    }
                }

                btnText.style.display = 'none';
                btnLoading.style.display = 'inline-flex';
                btnSubmit.disabled = true;
            });

            function showAlert(type, message) {
                const container = document.getElementById('alert-container');
                const alertClass = type === 'error' ? 'alert-error' : 
                                   type === 'success' ? 'alert-success' : 
                                   type === 'warning' ? 'alert-warning' : 'alert-info';
                
                container.innerHTML = `
                    <div class="alert ${alertClass}">
                        <span>${type === 'error' ? '❌' : type === 'success' ? '✅' : '⚠️'}</span>
                        <div>${message}</div>
                    </div>
                `;

                setTimeout(() => {
                    container.innerHTML = '';
                }, 5000);
            }
        });
    </script>
</body>
</html>
