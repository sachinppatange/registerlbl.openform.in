<?php
// WhatsApp Cloud API credentials and app config

define('WA_GRAPH_VERSION', 'v19.0');           // तुमच्या WABA साठी योग्य Graph version
define('WA_TOKEN',        'EAARb94KQlA0BPfSyTSYvTVjRyBU2Ag1azxiwQMwrINlnTmZBuDZAZC0ZCfEzGNJDZAhs3CXeKnKwOxEVTz3IVlyH2gHauSZCc7Lxd7r1SRZB5nWH6rQHY4qmcDZBfFZB6D5lZBHOYZCZAxwEdTxM28kZAlyKgzkmgra2nUdSsnuAIfNegaZBCoYSiZBJ1gpA7vR7BkZB9ZBdA8AZDZD'); // WhatsApp Cloud API Bearer Token
define('WA_PHONE_ID',     '636471032888916');   // Phone Number ID (WhatsApp Manager -> API Setup)
define('WA_TEMPLATE',     'atharvmediaotp');   // Approved template name (body मध्ये 1 variable = OTP)
define('WA_LANG',         'en');               // Template language code
define('WA_COUNTRY_CODE', '91');               // Default country code (India)

define('APP_DEBUG',         true);             // true -> UI debug + logs
define('OTP_EXPIRY_SECONDS', 300);             // OTP वैधता: 5 मिनिटे
define('OTP_RESEND_COOLDOWN', 60);             // Resend cooldown: 60 सेकंद
define('OTP_LENGTH', 4);                       // 4-अंकी OTP

// तुमच्या Template मध्ये URL बटन parameter आवश्यक असल्यास (पूर्वीच्या error नुसार आवश्यक)
define('WA_URL_BUTTON_PARAM_REQUIRED', true);
define('WA_URL_BUTTON_INDEX', '0');
define('WA_URL_BUTTON_PARAM_USE_OTP', true);
define('WA_URL_BUTTON_PARAM_STATIC', 'STATIC_VALUE');

// लॉग फाईल
define('LOG_DIR',  __DIR__ . '/../userpanel/storage/logs');
define('LOG_FILE', LOG_DIR . '/../userpanel/whatsapp.log');