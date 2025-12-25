<?php
/**
 * SheerID Military Verifier Class
 * 
 * Thực hiện quy trình xác minh quân nhân 2 bước qua SheerID API
 */

class SheerIDVerifier
{
    private string $verificationId;
    private string $baseUrl;
    private string $originalUrl;
    private array $lastResponse = [];
    private string $lastError = '';
    private ?array $proxy = null;

    /**
     * Constructor
     * 
     * @param string $verificationId ID xác minh từ SheerID link
     * @param array|null $proxy Proxy config ['host', 'port', 'username', 'password']
     * @param string $originalUrl URL gốc từ SheerID (dùng làm referer)
     */
    public function __construct(string $verificationId, ?array $proxy = null, string $originalUrl = '')
    {
        $this->verificationId = $verificationId;
        $this->baseUrl = SHEERID_BASE_URL;
        $this->proxy = $proxy;
        $this->originalUrl = $originalUrl ?: sprintf('%s/verify/?verificationId=%s', SHEERID_BASE_URL, $verificationId);
    }

    /**
     * Set proxy
     */
    public function setProxy(?array $proxy): void
    {
        $this->proxy = $proxy;
    }

    /**
     * Parse verification ID từ URL
     * 
     * @param string $url SheerID URL
     * @return string|null Verification ID hoặc null nếu không hợp lệ
     */
    public static function parseVerificationId(string $url): ?string
    {
        // Pattern: verificationId=xxxxx
        if (preg_match('/verificationId=([a-f0-9]+)/i', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern: /verify/xxxxx/
        if (preg_match('/\/verify\/[^\/]+\/\?verificationId=([a-f0-9]+)/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Gửi HTTP request đến SheerID API
     * 
     * @param string $method HTTP method
     * @param string $url URL đầy đủ
     * @param array|null $data Dữ liệu POST
     * @return array|null Response data hoặc null nếu lỗi
     */
    private function sendRequest(string $method, string $url, ?array $data = null): ?array
    {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Language: en-US,en;q=0.9',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
            'clientname: jslib',
            'clientversion: 2.157.0',
            'Origin: https://services.sheerid.com',
            'Referer: ' . $this->originalUrl,
            'sec-ch-ua: "Microsoft Edge";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin'
        ];

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        // Thêm proxy nếu có
        if ($this->proxy !== null) {
            $curlOptions[CURLOPT_PROXY] = $this->proxy['host'] . ':' . $this->proxy['port'];
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            
            if (!empty($this->proxy['username']) && !empty($this->proxy['password'])) {
                $curlOptions[CURLOPT_PROXYUSERPWD] = $this->proxy['username'] . ':' . $this->proxy['password'];
            }
        }

        curl_setopt_array($ch, $curlOptions);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->lastError = "cURL Error: " . $error;
            return null;
        }

        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $this->lastError = "HTTP Error {$httpCode}: " . ($decoded['message'] ?? $response);
            return null;
        }

        $this->lastResponse = $decoded ?? [];
        return $decoded;
    }

    /**
     * Bước 1: Gửi trạng thái quân nhân
     * 
     * @param string $status Trạng thái (VETERAN, ACTIVE_DUTY, RESERVIST)
     * @return array|null Response hoặc null nếu lỗi
     */
    public function collectMilitaryStatus(string $status = 'VETERAN'): ?array
    {
        $url = sprintf(
            '%s/rest/%s/verification/%s/step/collectMilitaryStatus',
            $this->baseUrl,
            SHEERID_API_VERSION,
            $this->verificationId
        );

        $data = ['status' => $status];

        $response = $this->sendRequest('POST', $url, $data);

        if ($response === null) {
            return null;
        }

        // Kiểm tra response hợp lệ
        if (!isset($response['submissionUrl'])) {
            $this->lastError = "Invalid response: missing submissionUrl";
            return null;
        }

        return $response;
    }

    /**
     * Bước 2: Gửi thông tin cá nhân quân nhân
     * 
     * @param string $submissionUrl URL từ bước 1
     * @param array $personalInfo Thông tin cá nhân
     * @param array $organization Thông tin đơn vị quân đội
     * @return array|null Response hoặc null nếu lỗi
     */
    public function collectPersonalInfo(string $submissionUrl, array $personalInfo, array $organization): ?array
    {
        global $METADATA_TEMPLATE;

        // Generate device fingerprint hash (random MD5-like string)
        $deviceFingerprint = md5(uniqid(mt_rand(), true));

        $metadata = $METADATA_TEMPLATE;
        $metadata['verificationId'] = $this->verificationId;
        $metadata['refererUrl'] = $this->originalUrl;

        $data = [
            'firstName' => $personalInfo['first_name'],
            'lastName' => $personalInfo['last_name'],
            'birthDate' => $personalInfo['birth_date'],
            'email' => $personalInfo['email'],
            'phoneNumber' => $personalInfo['phone_number'] ?? '',
            'organization' => [
                'id' => $organization['id'],
                'name' => $organization['name']
            ],
            'dischargeDate' => $personalInfo['discharge_date'],
            'deviceFingerprintHash' => $deviceFingerprint,
            'locale' => DEFAULT_LOCALE,
            'country' => DEFAULT_COUNTRY,
            'metadata' => $metadata
        ];

        return $this->sendRequest('POST', $submissionUrl, $data);
    }

    /**
     * Thực hiện toàn bộ quy trình xác minh
     * 
     * @param array $personalInfo Thông tin cá nhân (hoặc null để tự động tạo)
     * @param array|null $organization Đơn vị quân đội (hoặc null để random)
     * @param string $status Trạng thái quân nhân
     * @return array Kết quả xác minh
     */
    public function verify(?array $personalInfo = null, ?array $organization = null, string $status = 'VETERAN'): array
    {
        global $MILITARY_ORGANIZATIONS;

        // Tự động tạo thông tin nếu không cung cấp
        if ($personalInfo === null) {
            $personalInfo = NameGenerator::generateFullInfo();
        }

        // Random đơn vị quân đội nếu không cung cấp
        if ($organization === null) {
            $organization = $MILITARY_ORGANIZATIONS[array_rand($MILITARY_ORGANIZATIONS)];
        }

        $result = [
            'success' => false,
            'step' => 'init',
            'message' => '',
            'personal_info' => $personalInfo,
            'organization' => $organization,
            'response' => null
        ];

        // Bước 1: Gửi trạng thái quân nhân
        $result['step'] = 'collectMilitaryStatus';
        $step1Response = $this->collectMilitaryStatus($status);

        if ($step1Response === null) {
            $result['message'] = $this->lastError;
            return $result;
        }

        $result['step1_response'] = $step1Response;

        // Lấy submissionUrl cho bước 2
        $submissionUrl = $step1Response['submissionUrl'];

        // Bước 2: Gửi thông tin cá nhân
        $result['step'] = 'collectPersonalInfo';
        $step2Response = $this->collectPersonalInfo($submissionUrl, $personalInfo, $organization);

        if ($step2Response === null) {
            $result['message'] = $this->lastError;
            return $result;
        }

        $result['step2_response'] = $step2Response;
        $result['response'] = $step2Response;

        // Kiểm tra kết quả dựa trên response
        $currentStep = $step2Response['currentStep'] ?? '';
        $hasSubmissionUrl = !empty($step2Response['submissionUrl']);
        
        // Nếu có submissionUrl → Gửi thành công, chờ xác nhận email
        if ($hasSubmissionUrl && $currentStep !== 'error') {
            $result['success'] = true;
            $result['message'] = 'Gửi thông tin thành công. Vui lòng xác nhận email để kiểm tra kết quả.';
            $result['pending'] = true;
            $result['email_verification_required'] = true;
        } elseif ($currentStep === 'error') {
            // Nếu currentStep là error → Thất bại
            $result['success'] = false;
            $result['message'] = 'Xác minh thất bại. Vui lòng lấy lại link SheerID.';
        } elseif ($currentStep === 'success') {
            $result['success'] = true;
            $result['message'] = 'Xác minh thành công!';
            $result['redirect_url'] = $step2Response['redirectUrl'] ?? null;
        } elseif ($currentStep === 'docUpload') {
            $result['success'] = false;
            $result['message'] = 'Cần tải lên tài liệu xác minh.';
            $result['requires_document'] = true;
        } else {
            // Các trường hợp khác (emailLoop, pending, etc.) → coi như thành công
            $result['success'] = true;
            $result['message'] = 'Gửi thông tin thành công. Vui lòng xác nhận email để kiểm tra kết quả.';
            $result['pending'] = true;
        }

        return $result;
    }

    /**
     * Lấy lỗi cuối cùng
     * 
     * @return string
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Lấy response cuối cùng
     * 
     * @return array
     */
    public function getLastResponse(): array
    {
        return $this->lastResponse;
    }
}
