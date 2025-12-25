<?php
/**
 * Proxy Handler - Quản lý và kiểm tra proxy
 * 
 * Format proxy: Username:Password@Host:Port
 */

defined('MILITARY_VERIFY') or die('Direct access not allowed');

class ProxyHandler
{
    private array $proxies = [];
    private array $deadProxies = [];
    
    /**
     * Parse proxy list từ textarea (mỗi proxy 1 dòng)
     * Format: Username:Password@Host:Port
     */
    public function __construct(string $proxyList = '')
    {
        $this->parseProxyList($proxyList);
    }
    
    /**
     * Parse danh sách proxy từ string
     */
    private function parseProxyList(string $proxyList): void
    {
        $lines = explode("\n", $proxyList);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parsed = $this->parseProxyString($line);
            if ($parsed) {
                $this->proxies[] = $parsed;
            }
        }
    }
    
    /**
     * Parse một proxy string thành array
     * Format: Username:Password@Host:Port
     * Hoặc: Host:Port:Username:Password
     * Hoặc: Host:Port (không có auth)
     */
    private function parseProxyString(string $proxy): ?array
    {
        // Format: Username:Password@Host:Port
        if (strpos($proxy, '@') !== false) {
            preg_match('/^([^:]+):([^@]+)@([^:]+):(\d+)$/', $proxy, $matches);
            if (count($matches) === 5) {
                return [
                    'host' => $matches[3],
                    'port' => (int)$matches[4],
                    'username' => $matches[1],
                    'password' => $matches[2],
                    'original' => $proxy
                ];
            }
        }
        
        // Format: Host:Port:Username:Password
        $parts = explode(':', $proxy);
        if (count($parts) === 4) {
            return [
                'host' => $parts[0],
                'port' => (int)$parts[1],
                'username' => $parts[2],
                'password' => $parts[3],
                'original' => $proxy
            ];
        }
        
        // Format: Host:Port (no auth)
        if (count($parts) === 2 && is_numeric($parts[1])) {
            return [
                'host' => $parts[0],
                'port' => (int)$parts[1],
                'username' => null,
                'password' => null,
                'original' => $proxy
            ];
        }
        
        return null;
    }
    
    /**
     * Lấy số lượng proxy còn lại
     */
    public function getCount(): int
    {
        return count($this->proxies);
    }
    
    /**
     * Kiểm tra proxy có hoạt động không
     */
    public function checkProxy(array $proxy, int $timeout = 5): bool
    {
        $ch = curl_init();
        
        // Test với một URL đơn giản
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://httpbin.org/ip',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_PROXY => $proxy['host'] . ':' . $proxy['port'],
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        if ($proxy['username'] && $proxy['password']) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['username'] . ':' . $proxy['password']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return $httpCode === 200 && !empty($response);
    }
    
    /**
     * Lấy một proxy ngẫu nhiên và còn hoạt động
     * Trả về null nếu không còn proxy nào hoạt động
     */
    public function getWorkingProxy(int $timeout = 5): ?array
    {
        while (count($this->proxies) > 0) {
            // Chọn ngẫu nhiên một proxy
            $index = array_rand($this->proxies);
            $proxy = $this->proxies[$index];
            
            // Kiểm tra proxy
            if ($this->checkProxy($proxy, $timeout)) {
                return $proxy;
            }
            
            // Proxy die, xóa khỏi danh sách
            $this->deadProxies[] = $proxy;
            unset($this->proxies[$index]);
            $this->proxies = array_values($this->proxies); // Re-index
        }
        
        return null;
    }
    
    /**
     * Lấy danh sách proxy đã chết
     */
    public function getDeadProxies(): array
    {
        return $this->deadProxies;
    }
    
    /**
     * Lấy danh sách proxy còn lại (dạng string)
     */
    public function getRemainingProxiesString(): string
    {
        $lines = [];
        foreach ($this->proxies as $proxy) {
            $lines[] = $proxy['original'];
        }
        return implode("\n", $lines);
    }
    
    /**
     * Build cURL options cho proxy
     */
    public static function buildCurlProxyOptions(array $proxy): array
    {
        $options = [
            CURLOPT_PROXY => $proxy['host'] . ':' . $proxy['port'],
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
        ];
        
        if (!empty($proxy['username']) && !empty($proxy['password'])) {
            $options[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
        }
        
        return $options;
    }
}
