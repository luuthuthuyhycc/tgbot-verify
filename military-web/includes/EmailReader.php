<?php
/**
 * Email Reader - Đọc email qua IMAP và parse link xác nhận SheerID
 */

defined('MILITARY_VERIFY') or die('Direct access not allowed');

class EmailReader
{
    private string $imapHost;
    private int $imapPort;
    private string $username;
    private string $password;
    private string $emailDomain;
    public $connection = null;
    private string $lastError = '';
    private bool $usePythonFallback = false;

    /**
     * Check if PHP IMAP extension is available
     */
    public static function isImapAvailable(): bool
    {
        return function_exists('imap_open');
    }

    /**
     * Constructor
     * 
     * @param string $imapHost IMAP host (vd: imap.gmail.com)
     * @param int $imapPort IMAP port (993 for SSL)
     * @param string $username Email username
     * @param string $password Email password hoặc App Password
     * @param string $emailDomain Domain để tạo email random
     */
    public function __construct(
        string $imapHost = '',
        int $imapPort = 993,
        string $username = '',
        string $password = '',
        string $emailDomain = ''
    ) {
        $this->imapHost = $imapHost ?: IMAP_HOST;
        $this->imapPort = $imapPort ?: IMAP_PORT;
        $this->username = $username ?: IMAP_USERNAME;
        $this->password = $password ?: IMAP_PASSWORD;
        $this->emailDomain = $emailDomain ?: EMAIL_DOMAIN;
    }

    /**
     * Tạo email address từ tên + số random
     * 
     * @param string $firstName First name
     * @param string $lastName Last name
     * @param string $domain Email domain (optional, uses instance domain if empty)
     * @return string Email address (e.g., johnsmith123@domain.com)
     */
    public function generateEmailFromName(string $firstName, string $lastName, string $domain = ''): string
    {
        $domain = $domain ?: $this->emailDomain;
        
        // Chuẩn hóa tên: lowercase, loại bỏ ký tự đặc biệt
        $firstName = preg_replace('/[^a-z]/i', '', strtolower($firstName));
        $lastName = preg_replace('/[^a-z]/i', '', strtolower($lastName));
        
        // Random 3-4 số
        $randomNum = rand(100, 9999);
        
        return "{$firstName}{$lastName}{$randomNum}@{$domain}";
    }

    /**
     * Tạo email address ngẫu nhiên (static version cho index.php)
     * 
     * @param string $firstName First name
     * @param string $lastName Last name
     * @param string $domain Email domain
     * @return string Email address
     */
    public static function generateRandomEmail(string $firstName, string $lastName, string $domain): string
    {
        // Chuẩn hóa tên
        $firstName = preg_replace('/[^a-z]/i', '', strtolower($firstName));
        $lastName = preg_replace('/[^a-z]/i', '', strtolower($lastName));
        
        // Random 3-4 số
        $randomNum = rand(100, 9999);
        
        return "{$firstName}{$lastName}{$randomNum}@{$domain}";
    }

    /**
     * Kết nối đến IMAP server
     * 
     * @return bool
     */
    public function connect(): bool
    {
        $mailbox = sprintf(
            '{%s:%d/imap/ssl/novalidate-cert}INBOX',
            $this->imapHost,
            $this->imapPort
        );

        $this->connection = @imap_open($mailbox, $this->username, $this->password);

        if (!$this->connection) {
            $this->lastError = imap_last_error() ?: 'Cannot connect to IMAP server';
            return false;
        }

        return true;
    }

    /**
     * Đóng kết nối IMAP
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Fallback: Dùng Python script khi PHP IMAP không có
     * 
     * @param string $toEmail Email cần tìm
     * @param int $timeout Timeout in seconds
     * @return array|null
     */
    public function findEmailViaPython(string $toEmail, int $timeout = 120): ?array
    {
        $scriptPath = __DIR__ . '/../scripts/imap_reader.py';
        
        if (!file_exists($scriptPath)) {
            $this->lastError = 'Python IMAP script not found';
            return null;
        }
        
        $mailbox = sprintf('{%s:%d/imap/ssl}INBOX', $this->imapHost, $this->imapPort);
        
        $command = sprintf(
            'python3 %s %s %s %s %s %d 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($mailbox),
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($toEmail),
            $timeout
        );
        
        $output = shell_exec($command);
        
        if (empty($output)) {
            $this->lastError = 'Python script returned no output';
            return null;
        }
        
        $result = json_decode($output, true);
        
        if (!$result || !isset($result['success'])) {
            $this->lastError = 'Invalid JSON from Python script';
            return null;
        }
        
        if (!$result['success']) {
            $this->lastError = $result['error'] ?? 'Unknown error from Python script';
            return null;
        }
        
        // Convert to expected format
        return [
            'subject' => $result['email']['subject'] ?? '',
            'from' => $result['email']['from'] ?? '',
            'to' => $result['email']['to'] ?? '',
            'to_email' => $toEmail,
            'date' => $result['email']['date'] ?? '',
            'body' => $result['email']['body'] ?? '',
            'emailToken' => $result['emailToken'] ?? null
        ];
    }

    /**
     * Tìm email từ SheerID gửi đến địa chỉ cụ thể
     * 
     * @param string $toEmail Email cần tìm
     * @param int $maxWaitSeconds Thời gian chờ tối đa
     * @param int $checkInterval Khoảng thời gian giữa các lần check (giây)
     * @return array|null Email data hoặc null
     */
    public function waitForSheerIDEmail(
        string $toEmail,
        int $maxWaitSeconds = 60,
        int $checkInterval = 5
    ): ?array {
        // Nếu PHP IMAP không có, dùng Python fallback
        if (!self::isImapAvailable()) {
            error_log("EmailReader: PHP IMAP not available, using Python fallback");
            return $this->findEmailViaPython($toEmail, $maxWaitSeconds);
        }
        
        $startTime = time();
        
        while ((time() - $startTime) < $maxWaitSeconds) {
            $email = $this->findSheerIDEmail($toEmail);
            if ($email !== null) {
                return $email;
            }
            
            sleep($checkInterval);
            
            // Refresh connection
            if ($this->connection) {
                imap_ping($this->connection);
                imap_gc($this->connection, IMAP_GC_ENV);
            }
        }

        $this->lastError = "Timeout waiting for email to {$toEmail}";
        return null;
    }

    /**
     * Tìm email SheerID
     * 
     * @param string $toEmail Email đích
     * @return array|null
     */
    public function findSheerIDEmail(string $toEmail): ?array
    {
        if (!$this->connection) {
            if (!$this->connect()) {
                return null;
            }
        }

        // Tìm email từ SheerID gửi đến địa chỉ cụ thể
        // Search trong 5 phút gần nhất
        $since = date('d-M-Y', strtotime('-5 minutes'));
        
        // Tìm tất cả email chưa đọc từ SheerID
        $searchCriteria = sprintf(
            'UNSEEN FROM "sheerid" SINCE "%s"',
            $since
        );
        
        $emails = @imap_search($this->connection, $searchCriteria);
        
        if (!$emails) {
            // Thử tìm theo TO (cho catch-all)
            $searchCriteria = sprintf('UNSEEN SINCE "%s"', $since);
            $emails = @imap_search($this->connection, $searchCriteria);
        }

        if (!$emails) {
            return null;
        }

        // Sắp xếp theo mới nhất
        rsort($emails);

        foreach ($emails as $emailNumber) {
            $header = imap_headerinfo($this->connection, $emailNumber);
            
            // Kiểm tra email có phải gửi đến địa chỉ cần tìm
            $toAddresses = [];
            if (isset($header->to)) {
                foreach ($header->to as $to) {
                    $toAddresses[] = strtolower($to->mailbox . '@' . $to->host);
                }
            }

            // Kiểm tra email có phải gửi đến địa chỉ CHÍNH XÁC không
            $isExactMatch = false;
            foreach ($toAddresses as $addr) {
                if ($addr === strtolower($toEmail)) {
                    $isExactMatch = true;
                    break;
                }
            }

            if (!$isExactMatch) {
                continue;
            }

            // Lấy nội dung email
            $body = $this->getEmailBody($emailNumber);
            
            // Kiểm tra có phải email SheerID verification không
            if (stripos($body, 'sheerid') !== false && 
                (stripos($body, 'Finish Verifying') !== false || 
                 stripos($body, 'emailToken') !== false)) {
                
                // Đánh dấu đã đọc
                imap_setflag_full($this->connection, (string)$emailNumber, '\\Seen');
                
                return [
                    'number' => $emailNumber,
                    'subject' => $header->subject ?? '',
                    'from' => $header->fromaddress ?? '',
                    'to' => $toAddresses,
                    'to_email' => $toEmail,  // Email chính xác đã match
                    'date' => $header->date ?? '',
                    'body' => $body
                ];
            }
        }

        return null;
    }

    /**
     * Lấy nội dung email (HTML hoặc plain text)
     * 
     * @param int $emailNumber
     * @return string
     */
    private function getEmailBody(int $emailNumber): string
    {
        $structure = imap_fetchstructure($this->connection, $emailNumber);
        
        if (!$structure) {
            return '';
        }

        // Email đơn giản (không multipart)
        if (!isset($structure->parts) || empty($structure->parts)) {
            $body = imap_body($this->connection, $emailNumber);
            return $this->decodeBody($body, $structure->encoding ?? 0);
        }

        // Email multipart - tìm phần HTML
        return $this->getMultipartBody($emailNumber, $structure);
    }

    /**
     * Lấy body từ email multipart
     */
    private function getMultipartBody(int $emailNumber, $structure, string $partNumber = ''): string
    {
        $htmlBody = '';
        $textBody = '';

        foreach ($structure->parts as $index => $part) {
            $currentPart = $partNumber ? "$partNumber." . ($index + 1) : (string)($index + 1);
            
            // Nếu là multipart/alternative hoặc multipart/related, đệ quy
            if ($part->type === 1 && isset($part->parts)) {
                $result = $this->getMultipartBody($emailNumber, $part, $currentPart);
                if (!empty($result)) {
                    // Nếu tìm thấy emailToken, return ngay
                    if (stripos($result, 'emailToken') !== false) {
                        return $result;
                    }
                    if (empty($textBody)) {
                        $textBody = $result;
                    }
                }
                continue;
            }

            // Lấy nội dung
            $body = imap_fetchbody($this->connection, $emailNumber, $currentPart);
            $body = $this->decodeBody($body, $part->encoding ?? 0);
            
            // Thêm decode =3D
            $body = str_replace('=3D', '=', $body);

            // Text/HTML
            if ($part->type === 0) {
                $subtype = strtolower($part->subtype ?? '');
                if ($subtype === 'html') {
                    $htmlBody = $body;
                    // Nếu HTML có emailToken, return ngay
                    if (stripos($body, 'emailToken') !== false) {
                        error_log("EmailReader::getMultipartBody - Found emailToken in HTML part");
                        return $body;
                    }
                } elseif ($subtype === 'plain') {
                    $textBody = $body;
                    // Nếu plain text có emailToken, lưu lại
                    if (stripos($body, 'emailToken') !== false) {
                        error_log("EmailReader::getMultipartBody - Found emailToken in PLAIN part");
                    }
                }
            }
        }

        // Ưu tiên body có emailToken, sau đó HTML, cuối cùng là plain text
        if (stripos($textBody, 'emailToken') !== false) {
            return $textBody;
        }
        
        $result = $htmlBody ?: $textBody;
        
        // Debug log
        error_log("EmailReader::getMultipartBody - HTML length: " . strlen($htmlBody) . ", Text length: " . strlen($textBody));
        
        return $result;
    }

    /**
     * Decode email body
     */
    private function decodeBody(string $body, int $encoding): string
    {
        switch ($encoding) {
            case 0: // 7BIT
            case 1: // 8BIT
                break;
            case 2: // BINARY
                break;
            case 3: // BASE64
                $body = base64_decode($body);
                break;
            case 4: // QUOTED-PRINTABLE
                $body = quoted_printable_decode($body);
                break;
            case 5: // OTHER
                break;
        }

        return $body;
    }

    /**
     * Parse emailToken từ nội dung email
     * 
     * Chỉ cần tìm emailToken=XXXXX trong email, sau đó ghép với link gốc
     * 
     * @param string $emailBody
     * @return string|null emailToken hoặc null
     */
    public static function parseEmailToken(string $emailBody): ?string
    {
        // Debug: log original body length
        $originalLength = strlen($emailBody);
        error_log("EmailReader::parseEmailToken - Original length: $originalLength");
        
        // Tìm emailToken TRƯỚC KHI decode - vì decode có thể làm hỏng
        $patterns = [
            '/emailToken=([0-9]+)/i',                   // emailToken=261104 (số)
            '/emailToken=([a-zA-Z0-9_-]+)/i',           // emailToken=ABC123xyz
            '/emailToken%3D([a-zA-Z0-9_-]+)/i',         // URL encoded
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $emailBody, $matches)) {
                error_log("EmailReader::parseEmailToken - Found token (before decode): " . $matches[1]);
                return $matches[1];
            }
        }
        
        // Nếu không tìm thấy, thử decode rồi tìm lại
        $decoded = quoted_printable_decode($emailBody);
        $decoded = str_replace('=3D', '=', $decoded);
        $decoded = preg_replace('/=\s*[\r\n]+/', '', $decoded);
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        error_log("EmailReader::parseEmailToken - Decoded length: " . strlen($decoded));
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $matches)) {
                error_log("EmailReader::parseEmailToken - Found token (after decode): " . $matches[1]);
                return $matches[1];
            }
        }
        
        // Tìm trong URL pattern
        if (preg_match('/services\.sheerid\.com[^"\'>\s]*emailToken=([0-9]+)/i', $emailBody, $matches)) {
            error_log("EmailReader::parseEmailToken - Found in URL: " . $matches[1]);
            return $matches[1];
        }
        
        // Debug: log sample of body để xem nội dung
        error_log("EmailReader::parseEmailToken - Token NOT found. Body sample: " . substr($emailBody, 0, 500));
        
        return null;
    }

    /**
     * Tạo link xác nhận từ URL gốc và emailToken
     * 
     * @param string $originalUrl URL SheerID gốc (có verificationId)
     * @param string $emailToken Token từ email
     * @return string Link xác nhận hoàn chỉnh
     */
    public static function buildVerificationLink(string $originalUrl, string $emailToken): string
    {
        // Đảm bảo URL có dấu ? hoặc &
        $separator = (strpos($originalUrl, '?') !== false) ? '&' : '?';
        
        // Nếu URL đã có emailToken, thay thế
        if (strpos($originalUrl, 'emailToken=') !== false) {
            return preg_replace('/emailToken=[^&]+/', 'emailToken=' . $emailToken, $originalUrl);
        }
        
        return $originalUrl . $separator . 'emailToken=' . $emailToken;
    }

    /**
     * Parse link xác nhận từ nội dung email (legacy - giữ lại để tương thích)
     * 
     * @param string $emailBody
     * @return string|null Link xác nhận hoặc null
     * @deprecated Use parseEmailToken() + buildVerificationLink() instead
     */
    public static function parseVerificationLink(string $emailBody): ?string
    {
        // Decode quoted-printable
        $emailBody = quoted_printable_decode($emailBody);
        $emailBody = str_replace('=3D', '=', $emailBody);
        $emailBody = preg_replace('/=\s*[\r\n]+/', '', $emailBody);
        $emailBody = html_entity_decode($emailBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $emailBody = str_replace('&amp;', '&', $emailBody);
        
        // Pattern tìm link SheerID
        $patterns = [
            '/href\s*=\s*["\']?(https:\/\/services\.sheerid\.com\/verify\/[a-f0-9]+\/\?verificationId=[a-f0-9]+&emailToken=[0-9]+)["\']?/i',
            '/(https:\/\/services\.sheerid\.com\/verify\/[a-f0-9]+\/\?verificationId=[a-f0-9]+&emailToken=[0-9]+)/i',
            '/\(?(https:\/\/services\.sheerid\.com\/verify\/[^\s\)<>"]+emailToken=[^\s\)<>"]+)\)?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $emailBody, $matches)) {
                $link = trim($matches[1]);
                $link = rtrim($link, '"\'>);');
                
                if (strpos($link, 'verificationId=') !== false && strpos($link, 'emailToken=') !== false) {
                    return $link;
                }
            }
        }

        return null;
    }

    /**
     * Click vào link xác nhận (gửi GET request) - 3 bước
     * 
     * Bước 3: Gửi GET đến link có emailToken (load trang xác nhận)
     * Bước 3b: Gọi API my.sheerid.com để trigger confirm email (giống browser JS)
     * Bước 4: Gọi API /rest/v2/verification/{verificationId} để check "currentStep": "success"
     * 
     * @param string $verificationLink Link có emailToken
     * @param array|null $proxy
     * @return array Kết quả
     */
    public static function clickVerificationLink(string $verificationLink, ?array $proxy = null): array
    {
        // Headers giống browser
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
            'Upgrade-Insecure-Requests: 1',
        ];

        // Extract verificationId từ URL
        $verificationId = null;
        if (preg_match('/verificationId=([a-f0-9]+)/i', $verificationLink, $matches)) {
            $verificationId = $matches[1];
        }
        
        if (!$verificationId) {
            return [
                'success' => false,
                'message' => 'Không tìm thấy verificationId trong URL',
                'http_code' => 0,
                'final_url' => $verificationLink
            ];
        }

        error_log("EmailReader::clickVerificationLink - verificationId: $verificationId");

        // Kết quả để trả về
        $step3Response = [];
        $step4Response = [];
        $allAttempts = [];

        // === BƯỚC 3: Gửi GET đến link có emailToken ===
        error_log("EmailReader::clickVerificationLink - Step 3: Sending GET to link with emailToken");
        
        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $verificationLink,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADER => true,  // Lấy cả header để debug
        ];

        if ($proxy !== null) {
            $curlOptions[CURLOPT_PROXY] = $proxy['host'] . ':' . $proxy['port'];
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            if (!empty($proxy['username']) && !empty($proxy['password'])) {
                $curlOptions[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
            }
        }

        curl_setopt_array($ch, $curlOptions);
        $response1Full = curl_exec($ch);
        $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl1 = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error1 = curl_error($ch);
        curl_close($ch);

        // Tách header và body
        $headerSize1 = strpos($response1Full, "\r\n\r\n");
        $responseHeaders1 = substr($response1Full, 0, $headerSize1);
        $responseBody1 = substr($response1Full, $headerSize1 + 4);

        $step3Response = [
            'url' => $verificationLink,
            'http_code' => $httpCode1,
            'final_url' => $finalUrl1,
            'error' => $error1 ?: null,
            'proxy_used' => $proxy ? ($proxy['host'] . ':' . $proxy['port']) : 'none',
            'response_headers' => $responseHeaders1,
            'response_body_preview' => substr($responseBody1, 0, 500)
        ];

        if ($error1) {
            error_log("EmailReader::clickVerificationLink - Step 3 error: $error1");
            return [
                'success' => false,
                'message' => "Step 3 cURL Error: " . $error1,
                'http_code' => 0,
                'final_url' => $verificationLink,
                'step3_response' => $step3Response,
                'step4_response' => null
            ];
        }

        error_log("EmailReader::clickVerificationLink - Step 3 completed, HTTP code: $httpCode1");

        // === BƯỚC 3b: Gọi API my.sheerid.com để trigger email confirmation ===
        // Browser gửi OPTIONS (CORS preflight) trước, sau đó GET
        $mySheerIdApiUrl = "https://my.sheerid.com/rest/v2/verification/{$verificationId}";
        error_log("EmailReader::clickVerificationLink - Step 3b: Calling my.sheerid.com API");
        
        $step3bResponse = [];
        
        // === BƯỚC 3b-1: Gửi OPTIONS request (CORS preflight) ===
        $optionsHeaders = [
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9',
            'Access-Control-Request-Headers: content-type',
            'Access-Control-Request-Method: GET',
            'Origin: https://services.sheerid.com',
            'Referer: ' . $verificationLink,
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-site',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
        ];
        
        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $mySheerIdApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'OPTIONS',
            CURLOPT_HTTPHEADER => $optionsHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADER => true,
        ];
        
        if ($proxy !== null) {
            $curlOptions[CURLOPT_PROXY] = $proxy['host'] . ':' . $proxy['port'];
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            if (!empty($proxy['username']) && !empty($proxy['password'])) {
                $curlOptions[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
            }
        }
        
        curl_setopt_array($ch, $curlOptions);
        $optionsResponse = curl_exec($ch);
        $optionsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $optionsError = curl_error($ch);
        curl_close($ch);
        
        error_log("EmailReader::clickVerificationLink - Step 3b-1 OPTIONS completed, HTTP: $optionsHttpCode");
        
        // === BƯỚC 3b-2: Gửi GET request thực sự ===
        $getHeaders = [
            'Accept: application/json',
            'Accept-Language: en-US,en;q=0.9',
            'Content-Type: application/json',
            'Origin: https://services.sheerid.com',
            'Referer: ' . $verificationLink,
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-site',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
        ];
        
        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $mySheerIdApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $getHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        
        if ($proxy !== null) {
            $curlOptions[CURLOPT_PROXY] = $proxy['host'] . ':' . $proxy['port'];
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            if (!empty($proxy['username']) && !empty($proxy['password'])) {
                $curlOptions[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
            }
        }
        
        curl_setopt_array($ch, $curlOptions);
        $response3b = curl_exec($ch);
        $httpCode3b = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error3b = curl_error($ch);
        curl_close($ch);
        
        $data3b = json_decode($response3b, true);
        $currentStep3b = $data3b['currentStep'] ?? 'unknown';
        
        $step3bResponse = [
            'api_url' => $mySheerIdApiUrl,
            'options_http_code' => $optionsHttpCode,
            'options_error' => $optionsError ?: null,
            'get_http_code' => $httpCode3b,
            'get_error' => $error3b ?: null,
            'proxy_used' => $proxy ? ($proxy['host'] . ':' . $proxy['port']) : 'none',
            'currentStep' => $currentStep3b,
            'response_data' => $data3b
        ];
        
        error_log("EmailReader::clickVerificationLink - Step 3b-2 GET completed, currentStep: $currentStep3b");
        
        // Nếu đã success sau bước 3b, không cần retry nữa
        if ($currentStep3b === 'success') {
            error_log("EmailReader::clickVerificationLink - Already success after step 3b!");
            $redirectUrl = $data3b['redirectUrl'] ?? null;
            return [
                'success' => true,
                'message' => 'Xác minh thành công!',
                'http_code' => $httpCode3b,
                'final_url' => $redirectUrl ?? $mySheerIdApiUrl,
                'currentStep' => 'success',
                'verificationId' => $verificationId,
                'response_data' => $data3b,
                'step3_response' => $step3Response,
                'step3b_response' => $step3bResponse,
                'step4_response' => null
            ];
        }

        // === BƯỚC 4: Gọi API để check trạng thái verification (với retry) ===
        $apiUrl = "https://services.sheerid.com/rest/v2/verification/{$verificationId}";
        error_log("EmailReader::clickVerificationLink - Step 4: Checking API at: $apiUrl");

        $apiHeaders = [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ];

        $maxRetries = 5;  // Thử tối đa 5 lần
        $retryDelay = 2;  // Chờ 2 giây giữa mỗi lần
        $currentStep = 'unknown';
        $data = null;
        $httpCode = 0;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            // Chờ trước mỗi lần check (để server xử lý)
            sleep($retryDelay);
            
            error_log("EmailReader::clickVerificationLink - API check attempt $attempt/$maxRetries");
            
            $ch = curl_init();
            $curlOptions = [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $apiHeaders,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ];
            
            if ($proxy !== null) {
                $curlOptions[CURLOPT_PROXY] = $proxy['host'] . ':' . $proxy['port'];
                $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
                if (!empty($proxy['username']) && !empty($proxy['password'])) {
                    $curlOptions[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
                }
            }
            
            curl_setopt_array($ch, $curlOptions);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $attemptData = [
                'attempt' => $attempt,
                'http_code' => $httpCode,
                'error' => $error ?: null,
                'currentStep' => null,
                'raw_response' => substr($response, 0, 1000)
            ];

            if ($error) {
                error_log("EmailReader::clickVerificationLink - API error on attempt $attempt: $error");
                $allAttempts[] = $attemptData;
                continue;
            }

            $data = json_decode($response, true);
            
            if (!$data) {
                error_log("EmailReader::clickVerificationLink - Invalid JSON on attempt $attempt");
                $attemptData['parse_error'] = 'Invalid JSON';
                $allAttempts[] = $attemptData;
                continue;
            }

            $currentStep = $data['currentStep'] ?? 'unknown';
            $attemptData['currentStep'] = $currentStep;
            $attemptData['response_data'] = $data;
            $allAttempts[] = $attemptData;
            
            error_log("EmailReader::clickVerificationLink - Attempt $attempt: currentStep = $currentStep");
            
            // Nếu đã có kết quả cuối cùng (success hoặc error), thoát vòng lặp
            if ($currentStep === 'success' || $currentStep === 'error' || $currentStep === 'docUpload') {
                break;
            }
            
            // Nếu vẫn emailLoop và còn retry, tiếp tục chờ
            if ($attempt < $maxRetries) {
                error_log("EmailReader::clickVerificationLink - Still $currentStep, retrying...");
            }
        }

        $step4Response = [
            'api_url' => $apiUrl,
            'proxy_used' => $proxy ? ($proxy['host'] . ':' . $proxy['port']) : 'none',
            'total_attempts' => count($allAttempts),
            'final_http_code' => $httpCode,
            'final_currentStep' => $currentStep,
            'attempts' => $allAttempts,
            'final_response' => $data
        ];
        
        // Kiểm tra nếu không có data sau tất cả retry
        if (!$data) {
            error_log("EmailReader::clickVerificationLink - All retries failed");
            return [
                'success' => false,
                'message' => 'Không thể kiểm tra trạng thái xác minh sau ' . $maxRetries . ' lần thử',
                'http_code' => $httpCode,
                'final_url' => $apiUrl,
                'step3_response' => $step3Response,
                'step3b_response' => $step3bResponse,
                'step4_response' => $step4Response
            ];
        }

        error_log("EmailReader::clickVerificationLink - Final currentStep: $currentStep");
        
        $isSuccess = ($currentStep === 'success');
        $redirectUrl = $data['redirectUrl'] ?? null;
        
        if ($isSuccess) {
            $message = 'Xác minh thành công!';
        } else {
            $errorIds = $data['errorIds'] ?? [];
            $errorDetail = $data['errorDetailId'] ?? '';
            $message = "Xác minh thất bại (currentStep: $currentStep). Vui lòng thử lại với link SheerID khác.";
            
            if (!empty($errorIds)) {
                $message .= " Lỗi: " . implode(', ', $errorIds);
            }
        }

        error_log("EmailReader::clickVerificationLink - Final result: success=" . ($isSuccess ? 'true' : 'false') . ", message=$message");

        return [
            'success' => $isSuccess,
            'message' => $message,
            'http_code' => $httpCode,
            'final_url' => $redirectUrl ?? $apiUrl,
            'currentStep' => $currentStep,
            'verificationId' => $verificationId,
            'response_data' => $data,
            'step3_response' => $step3Response,
            'step3b_response' => $step3bResponse,
            'step4_response' => $step4Response
        ];
    }

    /**
     * Lấy lỗi cuối cùng
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }
}
