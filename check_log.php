<?php
$logPath = __DIR__ . '/storage/logs/laravel.log';
if (file_exists($logPath)) {
    $content = file_get_contents($logPath);
    // Convert from UTF-16LE to UTF-8
    $utf8_content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
    if (empty($utf8_content) || strlen($utf8_content) < 10) {
        $utf8_content = $content; // Try as standard UTF-8/ANSI
    }
    echo "--- Last 500 characters of laravel.log ---\n";
    echo substr($utf8_content, -1500) . "\n";
} else {
    echo "Log file not found.\n";
}
