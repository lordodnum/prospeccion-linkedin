<?php
declare(strict_types=1);
// Mensaje directo con tope 80/dia por cuenta. Sin --confirm es simulacro.
// Requiere ser contacto; con --inmail gasta credito de la cuenta (Sales Navigator/Premium).
// php scripts/send_message.php --account=ACC --provider=PROV --message="texto" [--inmail] [--confirm]
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

$opt = getopt('', ['account:', 'provider:', 'message:', 'inmail', 'confirm']);
$account = $opt['account'] ?? '';
$provider = $opt['provider'] ?? '';
$message = $opt['message'] ?? '';
$inmail = array_key_exists('inmail', $opt);
$confirm = array_key_exists('confirm', $opt);
$cap = (int) (getenv('DAILY_MSG_LIMIT') ?: 80);
$today = date('Y-m-d');

if ($account === '' || $provider === '' || trim($message) === '') {
    echo "Uso: php scripts/send_message.php --account=ACC --provider=PROV --message=\"texto\" [--inmail] [--confirm]\n";
    exit(2);
}

$blfile = __DIR__ . '/../data/blocklist.json';
$bl = file_exists($blfile) ? (json_decode(file_get_contents($blfile), true) ?: []) : [];
if (in_array($provider, $bl, true)) {
    echo "BLOQUEADO: provider en lista no contactar\n";
    exit(3);
}

$qfile = __DIR__ . '/../data/quotas.json';
$quotas = file_exists($qfile) ? (json_decode(file_get_contents($qfile), true) ?: []) : [];
$used = (int) ($quotas[$account][$today]['messages'] ?? 0);
if ($used >= $cap) {
    echo "TOPE: ya se enviaron $used/$cap mensajes hoy para esta cuenta\n";
    exit(3);
}
if (!$confirm) {
    echo 'SIMULACRO: se enviaría mensaje (' . "$used/$cap hoy" . ($inmail ? ', inmail' : '') . "). Agregue --confirm para enviar de verdad.\n";
    exit(0);
}

$svc = new LinkedinService();
$res = $svc->sendDirectMessage($account, $provider, $message, $inmail);
if (!$res['ok']) {
    echo 'ERROR: ' . ($res['error'] ?? 'desconocido') . "\n";
    exit(1);
}
$quotas[$account][$today]['messages'] = $used + 1;
@mkdir(__DIR__ . '/../data', 0770, true);
file_put_contents($qfile, json_encode($quotas, JSON_PRETTY_PRINT));
echo 'OK: mensaje enviado (' . ($used + 1) . "/$cap hoy)\n";
