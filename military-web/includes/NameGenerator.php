<?php
/**
 * Name Generator Class
 * 
 * Tạo thông tin cá nhân ngẫu nhiên cho việc xác minh
 */

class NameGenerator
{
    /**
     * Tạo tên ngẫu nhiên
     * 
     * @param string|null $gender 'male', 'female', hoặc null để random
     * @return array ['first_name' => string, 'last_name' => string]
     */
    public static function generateName(?string $gender = null): array
    {
        if ($gender === null) {
            $gender = rand(0, 1) ? 'male' : 'female';
        }

        $firstNames = $gender === 'male' ? FIRST_NAMES_MALE : FIRST_NAMES_FEMALE;
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = LAST_NAMES[array_rand(LAST_NAMES)];

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $firstName . ' ' . $lastName
        ];
    }

    /**
     * Tạo ngày sinh ngẫu nhiên
     * 
     * @param int $minAge Tuổi tối thiểu
     * @param int $maxAge Tuổi tối đa
     * @return string Ngày sinh định dạng YYYY-MM-DD
     */
    public static function generateBirthDate(int $minAge = MIN_AGE, int $maxAge = MAX_AGE): string
    {
        $currentYear = (int) date('Y');
        $birthYear = rand($currentYear - $maxAge, $currentYear - $minAge);
        $birthMonth = rand(1, 12);
        $birthDay = rand(1, 28); // Sử dụng 28 để tránh lỗi ngày không hợp lệ

        return sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);
    }

    /**
     * Tạo ngày xuất ngũ ngẫu nhiên
     * 
     * @return string Ngày xuất ngũ định dạng YYYY-MM-DD
     */
    public static function generateDischargeDate(): string
    {
        $currentDate = new DateTime();
        $maxYearsAgo = MAX_YEARS_SINCE_DISCHARGE;
        
        // Random từ 1 tháng trước đến MAX_YEARS_SINCE_DISCHARGE năm trước
        $daysAgo = rand(30, $maxYearsAgo * 365);
        $dischargeDate = clone $currentDate;
        $dischargeDate->sub(new DateInterval("P{$daysAgo}D"));

        return $dischargeDate->format('Y-m-d');
    }

    /**
     * Tạo email ngẫu nhiên
     * 
     * @param string $firstName Tên
     * @param string $lastName Họ
     * @return string Email
     */
    public static function generateEmail(string $firstName, string $lastName): string
    {
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com'];
        $domain = $domains[array_rand($domains)];
        
        $patterns = [
            strtolower($firstName) . '.' . strtolower($lastName),
            strtolower($firstName) . strtolower($lastName),
            strtolower(substr($firstName, 0, 1)) . strtolower($lastName),
            strtolower($firstName) . rand(100, 999),
            strtolower($lastName) . '.' . strtolower($firstName),
        ];
        
        $username = $patterns[array_rand($patterns)];
        $username .= rand(10, 99);

        return $username . '@' . $domain;
    }

    /**
     * Tạo số điện thoại US ngẫu nhiên
     * 
     * @return string Số điện thoại
     */
    public static function generatePhoneNumber(): string
    {
        $areaCode = rand(200, 999);
        $prefix = rand(200, 999);
        $lineNumber = rand(1000, 9999);

        return sprintf('%03d-%03d-%04d', $areaCode, $prefix, $lineNumber);
    }

    /**
     * Tạo toàn bộ thông tin cá nhân
     * 
     * @return array Thông tin cá nhân đầy đủ
     */
    public static function generateFullInfo(): array
    {
        $name = self::generateName();
        
        return [
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'full_name' => $name['full_name'],
            'birth_date' => self::generateBirthDate(),
            'discharge_date' => self::generateDischargeDate(),
            'email' => self::generateEmail($name['first_name'], $name['last_name']),
            'phone_number' => self::generatePhoneNumber()
        ];
    }
}
