<?php
/**
 * Military SheerID Verification - Configuration
 * 
 * Cấu hình cho hệ thống xác minh quân nhân SheerID
 */

// Ngăn truy cập trực tiếp
if (!defined('MILITARY_VERIFY')) {
    die('Direct access not allowed');
}

// SheerID API Configuration
define('SHEERID_BASE_URL', 'https://services.sheerid.com');
define('SHEERID_API_VERSION', 'v2');

// Military Organizations (US)
$MILITARY_ORGANIZATIONS = [
    [
        'id' => 4070,
        'name' => 'Army',
        'description' => 'Lục quân Hoa Kỳ'
    ],
    [
        'id' => 4073,
        'name' => 'Air Force',
        'description' => 'Không quân Hoa Kỳ'
    ],
    [
        'id' => 4072,
        'name' => 'Navy',
        'description' => 'Hải quân Hoa Kỳ'
    ],
    [
        'id' => 4071,
        'name' => 'Marine Corps',
        'description' => 'Thủy quân lục chiến Hoa Kỳ'
    ],
    [
        'id' => 4074,
        'name' => 'Coast Guard',
        'description' => 'Tuần duyên Hoa Kỳ'
    ],
    [
        'id' => 4544268,
        'name' => 'Space Force',
        'description' => 'Lực lượng Không gian Hoa Kỳ'
    ]
];

// Military Status Options
$MILITARY_STATUSES = [
    'VETERAN' => 'Cựu chiến binh (Veteran)',
    'ACTIVE_DUTY' => 'Đang tại ngũ (Active Duty)',
    'RESERVIST' => 'Lực lượng dự bị (Reservist)'
];

// Default Settings
define('DEFAULT_LOCALE', 'en-US');
define('DEFAULT_COUNTRY', 'US');

// Metadata template for OpenAI ChatGPT
$METADATA_TEMPLATE = [
    'marketConsentValue' => false,
    'refererUrl' => '',
    'verificationId' => '',
    'flags' => '{"doc-upload-considerations":"default","doc-upload-may24":"default","doc-upload-redesign-use-legacy-message-keys":false,"docUpload-assertion-checklist":"default","include-cvec-field-france-student":"not-labeled-optional","org-search-overlay":"default","org-selected-display":"default"}',
    'submissionOptIn' => 'By submitting the personal information above, I acknowledge that my personal information is being collected under the <a target="_blank" rel="noopener noreferrer" class="sid-privacy-policy sid-link" href="https://openai.com/policies/privacy-policy/">privacy policy</a> of the business from which I am seeking a discount, and I understand that my personal information will be shared with SheerID as a processor/third-party service provider in order for SheerID to confirm my eligibility for a special offer. Contact OpenAI Support for further assistance at support@openai.com'
];

// Name generation settings
define('FIRST_NAMES_MALE', ['James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Christopher', 'Charles', 'Daniel', 'Matthew', 'Anthony', 'Mark', 'Donald', 'Steven', 'Paul', 'Andrew', 'Joshua']);
define('FIRST_NAMES_FEMALE', ['Mary', 'Patricia', 'Jennifer', 'Linda', 'Barbara', 'Elizabeth', 'Susan', 'Jessica', 'Sarah', 'Karen', 'Lisa', 'Nancy', 'Betty', 'Margaret', 'Sandra', 'Ashley', 'Kimberly', 'Emily', 'Donna', 'Michelle']);
define('LAST_NAMES', ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson']);

// Birth date range (must be at least 18 years old, max 65 for veterans)
define('MIN_AGE', 18);
define('MAX_AGE', 65);

// Discharge date range (within last 20 years)
define('MAX_YEARS_SINCE_DISCHARGE', 20);

// Service Branch ID Mapping
$SERVICE_BRANCH_MAPPING = [
    'AR' => 4070,      // Army
    'AF' => 4073,      // Air Force
    'NA' => 4072,      // Navy
    'MC' => 4071,      // Marine Corps
    'CG' => 4074,      // Coast Guard
    'SF' => 4544268    // Space Force
];

// Data directory path (contains multiple JSON files)
define('DATA_VETERAN_DIR', __DIR__ . '/data/');

// Valid service branch IDs only
$VALID_SERVICE_BRANCHES = ['AR', 'AF', 'NA', 'MC', 'CG', 'SF'];
