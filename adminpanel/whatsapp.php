<?php
// Adminpanel WhatsApp OTP helper
// Structure and logic matches userpanel/whatsapp.php, but imports config from shared config folder

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/wa_config.php';

// Log helper (APP_DEBUG applies to both panels)
function app_log(string $message, array $context = []): void {
    if (!APP_DEBUG) return;
    $logDir = __DIR__ . '/storage/logs';
    $logFile = $logDir . '/whatsapp.log';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

// E.164 format utility
function to_e164(string $mobile): ?string {
    $digits = preg_replace('/\D+/', '', $mobile ?? '');
    if (!$digits) return null;
    if (strpos($digits, WA_COUNTRY_CODE) === 0 && strlen($digits) >= strlen(WA_COUNTRY_CODE) + 10) {
        return $digits;
    }
    if (strlen($digits) === 10) return WA_COUNTRY_CODE . $digits;
    if (strlen($digits) === 11 && $digits[0] === '0') return WA_COUNTRY_CODE . substr($digits, 1);
    return null;
}

// Send WhatsApp OTP using Cloud API with template
function send_whatsapp_otp(string $to_e164, string $otp): array {
    $url = sprintf('https://graph.facebook.com/%s/%s/messages', WA_GRAPH_VERSION, WA_PHONE_ID);

    $components = [
        [
            "type" => "body",
            "parameters" => [
                [ "type" => "text", "text" => $otp ]
            ]
        ]
    ];

    if (WA_URL_BUTTON_PARAM_REQUIRED) {
        $paramValue = WA_URL_BUTTON_PARAM_USE_OTP ? $otp : WA_URL_BUTTON_PARAM_STATIC;
        $components[] = [
            "type" => "button",
            "sub_type" => "url",
            "index" => WA_URL_BUTTON_INDEX,
            "parameters" => [
                [ "type" => "text", "text" => $paramValue ]
            ]
        ];
    }

    $payload = [
        "messaging_product" => "whatsapp",
        "to" => $to_e164,
        "type" => "template",
        "template" => [
            "name" => WA_TEMPLATE,
            "language" => [ "code" => WA_LANG ],
            "components" => $components
        ]
    ];

    $headers = [
        "Authorization: Bearer " . WA_TOKEN,
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = ['ok' => false, 'http_code' => $http_code, 'response' => null, 'error' => null];

    if ($curl_err) {
        $result['error'] = "CURL_ERROR: $curl_err";
        app_log('CURL error while sending WhatsApp OTP', ['error' => $curl_err]);
        return $result;
    }

    $decoded = json_decode($raw, true);
    $result['response'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;

    if ($http_code >= 200 && $http_code < 300) {
        $result['ok'] = true;
        app_log('WhatsApp OTP sent successfully', ['http_code' => $http_code, 'resp' => $result['response']]);
    } else {
        $wa_error = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : null;
        $result['error'] = $wa_error ? ("WA_ERROR: " . ($wa_error['message'] ?? 'Unknown')) : "HTTP_$http_code";
        app_log('WhatsApp OTP send failed', ['http_code' => $http_code, 'resp' => $result['response']]);
    }

    return $result;
}