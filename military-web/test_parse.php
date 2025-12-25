<?php
/**
 * Test script for parseVerificationLink
 */

define('MILITARY_VERIFY', true);

// Simple test without requiring full config
class TestEmailReader {
    public static function parseVerificationLink(string $emailBody): ?string
    {
        // Bước 1: Decode quoted-printable
        $emailBody = quoted_printable_decode($emailBody);
        
        // Bước 2: Thêm xử lý =3D còn sót
        $emailBody = str_replace('=3D', '=', $emailBody);
        
        // Bước 3: Loại bỏ soft line breaks
        $emailBody = preg_replace('/=\s*[\r\n]+/', '', $emailBody);
        
        // Bước 4: Decode HTML entities
        $emailBody = html_entity_decode($emailBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Bước 5: Thay &amp; thành &
        $emailBody = str_replace('&amp;', '&', $emailBody);
        
        // Pattern tìm link SheerID
        $patterns = [
            '/href\s*=\s*["\']?(https:\/\/services\.sheerid\.com\/verify\/[a-f0-9]+\/\?verificationId=[a-f0-9]+&emailToken=[0-9]+)["\']?/i',
            '/(https:\/\/services\.sheerid\.com\/verify\/[a-f0-9]+\/\?verificationId=[a-f0-9]+&emailToken=[0-9]+)/i',
            '/Finish\s+Verifying\s+\((https:\/\/services\.sheerid\.com\/verify\/[^\)]+)\)/i',
            '/(https:\/\/services\.sheerid\.com[^\s<>"\']+emailToken=[^\s<>"\'&]+)/i'
        ];

        foreach ($patterns as $i => $pattern) {
            if (preg_match($pattern, $emailBody, $matches)) {
                $link = trim($matches[1]);
                $link = rtrim($link, '"\'>);');
                
                if (strpos($link, 'verificationId=') !== false && strpos($link, 'emailToken=') !== false) {
                    echo "✅ Matched pattern $i\n";
                    return $link;
                }
            }
        }

        return null;
    }
}

// Test cases
echo "=== Test parseVerificationLink ===\n\n";

// Test 1: Clean HTML
echo "Test 1 - Clean HTML:\n";
$html1 = '<a href="https://services.sheerid.com/verify/690415d58971e73ca187d8c9/?verificationId=694d5e1a31835a5e0aeff8aa&emailToken=279897"> Finish Verifying </a>';
$result1 = TestEmailReader::parseVerificationLink($html1);
echo "Result: " . ($result1 ?? "NULL") . "\n\n";

// Test 2: Quoted-printable encoded
echo "Test 2 - Quoted-printable:\n";
$qp = 'href=3D"https://services.sheerid' . "=\n" . '.com/verify/690415d58971e73ca187d8c9/?verificationId=3D694d5e1a31835a5e0aef' . "=\n" . 'f8aa&emailToken=3D279897"';
echo "Input: $qp\n";
$result2 = TestEmailReader::parseVerificationLink($qp);
echo "Result: " . ($result2 ?? "NULL") . "\n\n";

// Test 3: Plain text with parentheses
echo "Test 3 - Plain text format:\n";
$plain = 'Finish Verifying  (https://services.sheerid.com/verify/690415d58971e73ca187d8c9/?verificationId=694d5e1a31835a5e0aeff8aa&emailToken=279897)';
$result3 = TestEmailReader::parseVerificationLink($plain);
echo "Result: " . ($result3 ?? "NULL") . "\n\n";

// Test 4: Real email HTML snippet
echo "Test 4 - Real email HTML:\n";
$realHtml = '<a href=3D"https://services.sheerid=
.com/verify/690415d58971e73ca187d8c9/?verificationId=3D694d5e1a31835a5e0aef=
f8aa&emailToken=3D279897" style=3D"display:inline-block" target=3D"_=
blank"> Finish Verifying </a>';
$result4 = TestEmailReader::parseVerificationLink($realHtml);
echo "Result: " . ($result4 ?? "NULL") . "\n\n";

echo "=== Done ===\n";
