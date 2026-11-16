<?php
declare(strict_types=1);
// Busqueda de leads por cargo. Uso:
// php scripts/search_leads.php --account=ACC_ID --cargo="Liquidador de siniestros" --keywords="vehiculos" --limit=20
require __DIR__ . '/../vendor/autoload.php';

use RpLinkedin\LinkedinService;

foreach (glob(__DIR__ . '/../.env') as $f) {
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$opt = getopt('', ['account:', 'cargo:', 'keywords:', 'limit::']);
$account = $opt['account'] ?? '';
$cargo = $opt['cargo'] ?? '';
$keywords = $opt['keywords'] ?? '';
$limit = (int) ($opt['limit'] ?? 20);

if ($account === '' || $cargo === '') {
    echo "Uso: php scripts/search_leads.php --account=ACC_ID --cargo=\"Liquidador de siniestros\" [--keywords=vehiculos] [--limit=20]\n";
    exit(2);
}

$svc = new LinkedinService();
$res = $svc->searchLeads(['cargo' => $cargo, 'keywords' => $keywords], $account, $limit);
if (!$res['ok']) {
    echo 'ERROR: ' . ($res['error'] ?? 'desconocido') . "\n";
    exit(1);
}
@mkdir(__DIR__ . '/../data', 0770, true);
file_put_contents(__DIR__ . '/../data/leads.json', json_encode($res['leads'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'OK: ' . count($res['leads']) . ' leads en data/leads.json (búsquedas: ' . ($res['searches'] ?? 1) . ")\n";
foreach (array_slice($res['leads'], 0, 10) as $l) {
    echo ' - ' . $l['nombre'] . ' | ' . $l['cargo'] . ' | ' . ($l['empresa'] ?? '') . "\n";
}
