<?php

// Router observasi di repo E-1; entry point dan respons D-2 tidak diubah.
declare(strict_types=1);

$entry = realpath((string) getenv('E1_DEPENDENCY_REPO').'/public/index.php');
$trace = (string) getenv('E1_DEPENDENCY_TRACE');
if ($entry === false || $trace === '') {
    throw new RuntimeException('Konfigurasi router observasi D-2 belum tersedia');
}
$started = gmdate('c');
register_shutdown_function(static function () use ($trace, $started): void {
    file_put_contents($trace, json_encode([
        'service' => 'D2', 'startedAtUtc' => $started, 'finishedAtUtc' => gmdate('c'),
        'method' => $_SERVER['REQUEST_METHOD'], 'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
        'status' => http_response_code() ?: 200, 'requestId' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
});
require $entry;
