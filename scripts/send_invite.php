<?php
declare(strict_types=1);
// Invitacion con tope diario 20/cuenta. Sin --confirm es simulacro.
// php scripts/send_invite.php --account=ACC --provider=PROV --message="texto" [--confirm]
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

$opt = getopt('', ['account:', 'provider:', 'message:', 'confirm']);
$account = $opt['account'] ?? '';
$provider = $opt['provider'] ?? '';
$message = $opt['message'] ?? '';
$confirm = array_key_exists('confirm', $opt);
$cap = (int) (getenv('DAILY_INVITE_LIMIT') ?: 20);
$today = date('Y-m-d');

$qfile = __DIR__ . '/../data/quotas.json';
$quotas = file_exists($qfile) ? (json_decode(file_get_contents($qfile), true) ?: []) : [];
$used = (int) ($quotas[$account][$today]['invites'] ?? 0);

if ($account === '' || $provider === '') {
    echo "Uso: php scripts/send_invite.php --account=ACC --provider=PROV --message=\"texto\" [--confirm]\n";
    exit(2);
}
if (mb_strlen($message) > 300) {
    echo "ERROR: mensaje supera 300 caracteres (" . mb_strlen($message) . ")\n";
    exit(1);
}
if ($used >= $cap) {
    echo "TOPE: ya se enviaron $used/$cap invitaciones hoy para esta cuenta\n";
    exit(3);
}
if (!$confirm) {
    echo "SIMULACRO: se enviaría invitación ($used/$cap hoy). Agregue --confirm para enviar de verdad.\n";
    exit(0);
}

$svc = new LinkedinService();
$res = $svc->sendInvite($account, $provider, $message);
if (!$res['ok']) {
    echo 'ERROR: ' . ($res['error'] ?? 'desconocido') . "\n";
    exit(1);
}
$quotas[$account][$today]['invites'] = $used + 1;
@mkdir(__DIR__ . '/../data', 0770, true);
file_put_contents($qfile, json_encode($quotas, JSON_PRETTY_PRINT));
echo 'OK: invitación enviada (' . ($used + 1) . "/$cap hoy)\n";
