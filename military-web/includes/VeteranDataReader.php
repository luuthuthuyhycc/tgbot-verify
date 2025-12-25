<?php
/**
 * Veteran Data Reader Class
 * 
 * Đọc và tổng hợp dữ liệu veteran từ TẤT CẢ file JSON trong thư mục data/
 * - Tự động scan thư mục data/
 * - Loại bỏ duplicate (ưu tiên file đầu tiên theo alphabet)
 * - Lưu source file để xóa đúng nơi
 */

class VeteranDataReader
{
    private array $data = [];
    private array $fileStats = [];  // Thống kê từng file
    
    /**
     * Constructor - Load dữ liệu từ tất cả file JSON
     */
    public function __construct()
    {
        $this->loadAllData();
    }
    
    /**
     * Lấy danh sách tất cả file JSON trong thư mục data/
     * 
     * @return array Danh sách đường dẫn file (đã sắp xếp alphabet)
     */
    private function getJsonFiles(): array
    {
        if (!is_dir(DATA_VETERAN_DIR)) {
            return [];
        }
        
        $files = glob(DATA_VETERAN_DIR . '*.json');
        sort($files);  // Sắp xếp theo alphabet để ưu tiên nhất quán
        
        return $files;
    }
    
    /**
     * Load và tổng hợp dữ liệu từ tất cả file JSON
     * Loại bỏ duplicate theo key: firstName + lastName + date_of_birth
     */
    private function loadAllData(): void
    {
        global $VALID_SERVICE_BRANCHES;
        
        $jsonFiles = $this->getJsonFiles();
        $seenKeys = [];  // Để track duplicate
        
        foreach ($jsonFiles as $file) {
            $fileName = basename($file);
            $this->fileStats[$fileName] = [
                'total' => 0,
                'valid' => 0,
                'duplicates' => 0,
                'invalid_branch' => 0
            ];
            
            $jsonContent = file_get_contents($file);
            $jsonData = json_decode($jsonContent, true);
            
            if ($jsonData === null || !isset($jsonData['data'])) {
                continue;
            }
            
            $this->fileStats[$fileName]['total'] = count($jsonData['data']);
            
            foreach ($jsonData['data'] as $record) {
                $branchId = $record['serviceBranchId'] ?? '';
                
                // Kiểm tra branch hợp lệ
                if (!in_array($branchId, $VALID_SERVICE_BRANCHES)) {
                    $this->fileStats[$fileName]['invalid_branch']++;
                    continue;
                }
                
                // Tạo unique key
                $uniqueKey = $this->createUniqueKey($record);
                
                // Kiểm tra duplicate
                if (isset($seenKeys[$uniqueKey])) {
                    $this->fileStats[$fileName]['duplicates']++;
                    continue;
                }
                
                // Đánh dấu đã thấy
                $seenKeys[$uniqueKey] = true;
                
                // Lưu source file để xóa sau
                $record['_source_file'] = $file;
                $record['_unique_key'] = $uniqueKey;
                
                $this->data[] = $record;
                $this->fileStats[$fileName]['valid']++;
            }
        }
        
        // Shuffle để random hóa thứ tự
        shuffle($this->data);
    }
    
    /**
     * Tạo unique key từ record
     * 
     * @param array $record
     * @return string
     */
    private function createUniqueKey(array $record): string
    {
        $firstName = strtolower(trim($record['firstName'] ?? ''));
        $lastName = strtolower(trim($record['lastName'] ?? ''));
        $birthDate = $record['date_of_birth'] ?? '';
        
        return "{$firstName}|{$lastName}|{$birthDate}";
    }
    
    /**
     * Lấy một record ngẫu nhiên
     * 
     * @return array|null
     */
    public function getRandomRecord(): ?array
    {
        if (empty($this->data)) {
            return null;
        }
        
        return $this->data[array_rand($this->data)];
    }
    
    /**
     * Lấy tổng số record hợp lệ (đã loại duplicate)
     * 
     * @return int
     */
    public function getTotalRecords(): int
    {
        return count($this->data);
    }
    
    /**
     * Lấy thống kê từng file
     * 
     * @return array
     */
    public function getFileStats(): array
    {
        return $this->fileStats;
    }
    
    /**
     * Lấy tổng số record từ tất cả file (đọc trực tiếp, đã loại duplicate)
     * 
     * @return int
     */
    public static function getTotalRecordsFromFiles(): int
    {
        global $VALID_SERVICE_BRANCHES;
        
        if (!is_dir(DATA_VETERAN_DIR)) {
            return 0;
        }
        
        $files = glob(DATA_VETERAN_DIR . '*.json');
        sort($files);
        
        $seenKeys = [];
        $count = 0;
        
        foreach ($files as $file) {
            $jsonContent = file_get_contents($file);
            $jsonData = json_decode($jsonContent, true);
            
            if ($jsonData === null || !isset($jsonData['data'])) {
                continue;
            }
            
            foreach ($jsonData['data'] as $record) {
                $branchId = $record['serviceBranchId'] ?? '';
                
                if (!in_array($branchId, $VALID_SERVICE_BRANCHES)) {
                    continue;
                }
                
                $key = strtolower(
                    trim($record['firstName'] ?? '') . '|' .
                    trim($record['lastName'] ?? '') . '|' .
                    ($record['date_of_birth'] ?? '')
                );
                
                if (!isset($seenKeys[$key])) {
                    $seenKeys[$key] = true;
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * Xóa một record khỏi file JSON gốc
     * 
     * @param string $sourceFile Đường dẫn file gốc
     * @param string $firstName First name (gốc, chưa format)
     * @param string $lastName Last name (gốc, chưa format)
     * @param string $birthDate Birth date
     * @return bool True nếu xóa thành công
     */
    public static function deleteRecord(string $sourceFile, string $firstName, string $lastName, string $birthDate): bool
    {
        if (!file_exists($sourceFile)) {
            return false;
        }
        
        $jsonContent = file_get_contents($sourceFile);
        $jsonData = json_decode($jsonContent, true);
        
        if ($jsonData === null || !isset($jsonData['data'])) {
            return false;
        }
        
        $originalCount = count($jsonData['data']);
        
        // Tìm và xóa record matching
        $jsonData['data'] = array_filter($jsonData['data'], function($record) use ($firstName, $lastName, $birthDate) {
            $recordFirstName = strtolower(trim($record['firstName'] ?? ''));
            $recordLastName = strtolower(trim($record['lastName'] ?? ''));
            $recordBirthDate = $record['date_of_birth'] ?? '';
            
            // Không match = giữ lại
            return !(
                $recordFirstName === strtolower(trim($firstName)) &&
                $recordLastName === strtolower(trim($lastName)) &&
                $recordBirthDate === $birthDate
            );
        });
        
        // Re-index array
        $jsonData['data'] = array_values($jsonData['data']);
        
        // Cập nhật metadata
        if (isset($jsonData['metadata'])) {
            $jsonData['metadata']['total_records'] = count($jsonData['data']);
        }
        
        // Kiểm tra xem có xóa được không
        if (count($jsonData['data']) >= $originalCount) {
            return false;
        }
        
        // Ghi lại file
        $result = file_put_contents(
            $sourceFile, 
            json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        return $result !== false;
    }
    
    /**
     * Chuyển đổi serviceBranchId thành organization info
     * 
     * @param string $branchId Mã branch (AR, AF, NA, MC, CG, SF)
     * @return array Organization info với id và name
     */
    public static function getOrganizationFromBranchId(string $branchId): array
    {
        global $SERVICE_BRANCH_MAPPING, $MILITARY_ORGANIZATIONS;
        
        $orgId = $SERVICE_BRANCH_MAPPING[$branchId] ?? null;
        
        if ($orgId === null) {
            return ['id' => 4070, 'name' => 'Army'];
        }
        
        foreach ($MILITARY_ORGANIZATIONS as $org) {
            if ($org['id'] == $orgId) {
                return ['id' => $org['id'], 'name' => $org['name']];
            }
        }
        
        return ['id' => $orgId, 'name' => 'Unknown'];
    }
    
    /**
     * Tạo ngày xuất ngũ ngẫu nhiên trong 6 tháng cuối năm 2025
     * 
     * @return string Ngày định dạng YYYY-MM-DD
     */
    public static function generateDischargeDate2025(): string
    {
        $month = rand(7, 12);
        $day = rand(1, 28);
        
        return sprintf('2025-%02d-%02d', $month, $day);
    }
    
    /**
     * Format tên - Capitalize properly
     * 
     * @param string $name Tên cần format
     * @return string Tên đã format
     */
    public static function formatName(string $name): string
    {
        $name = strtolower(trim($name));
        
        $words = preg_split('/[\s\-]+/', $name);
        $formatted = array_map('ucfirst', $words);
        
        if (strpos($name, '-') !== false) {
            return implode('-', $formatted);
        }
        
        return implode(' ', $formatted);
    }
}
