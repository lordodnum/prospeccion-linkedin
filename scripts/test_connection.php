<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use RpLinkedin\LinkedinService;

if (file_exists(__DIR__ . '/../.env')) {
    foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$svc = new LinkedinService();
if (!$svc->configured()) {
    echo "unipile_not_configured: complete UNIPILE_DSN y UNIPILE_API_KEY en .env\n";
    exit(2);
}
$res = $svc->listAccounts();
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($res['ok'] ? 0 : 1);
