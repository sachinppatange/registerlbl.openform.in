<?php
header('Content-Type: text/plain; charset=utf-8');
echo "RZP_KEY_ID: " . (getenv('RZP_KEY_ID') ?: '<missing>') . PHP_EOL;
echo "RZP_KEY_SECRET: " . (getenv('RZP_KEY_SECRET') ? '<present>' : '<missing>') . PHP_EOL;
echo "RZP_WEBHOOK_SECRET: " . (getenv('RZP_WEBHOOK_SECRET') ? '<present>' : '<missing>') . PHP_EOL;