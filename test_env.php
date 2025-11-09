<?php
header('Content-Type: text/plain; charset=utf-8');
echo "RZP_KEY_ID: " . (getenv('rzp_live_D53J9UWwYtGimn') ?: '<missing>') . PHP_EOL;
echo "RZP_KEY_SECRET: " . (getenv('w0SnqzH2SOOIc0gnUR7cYO3r') ? '<present>' : '<missing>') . PHP_EOL;
echo "RZP_WEBHOOK_SECRET: " . (getenv('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ? '<present>' : '<missing>') . PHP_EOL;