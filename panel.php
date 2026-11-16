<?php
declare(strict_types=1);
// Panel local del modulo LinkedIn. Solo localhost. Todo envio real exige confirmacion.
require __DIR__ . '/vendor/autoload.php';

use RpLinkedin\LinkedinService;

foreach (['.env'] as $f) {
    $p = __DIR__ . '/' . $f;
    if (!file_exists($p)) {
        continue;
    }
    foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$DATA = __DIR__ . '/data';
@mkdir($DATA, 0770, true);
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$today = date('Y-m-d');
$quotas = file_exists("$DATA/quotas.json") ? (json_decode(file_get_contents("$DATA/quotas.json"), true) ?: []) : [];
$leads = file_exists("$DATA/leads.json") ? (json_decode(file_get_contents("$DATA/leads.json"), true) ?: []) : [];
$msg = isset($_GET['m']) ? (string) $_GET['m'] : '';
$confirm = null;

function saveQuotas(array $q): void
{
    global $DATA;
    file_put_contents("$DATA/quotas.json", json_encode($q, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $svc = new LinkedinService();
    $account = trim($_POST['account'] ?? '');
    if ($action === 'invite_preview' || $action === 'message_preview') {
        $confirm = [
            'type' => $action === 'invite_preview' ? 'invite' : 'message',
            'account' => $account,
            'provider' => trim($_POST['provider'] ?? ''),
            'text' => trim($_POST['text'] ?? ''),
            'inmail' => isset($_POST['inmail']),
        ];
    } else {
        if ($action === 'search') {
            $r = $svc->searchLeads(['cargo' => $_POST['cargo'] ?? '', 'keywords' => $_POST['keywords'] ?? ''], $account, (int) ($_POST['limit'] ?? 20));
            if ($r['ok']) {
                file_put_contents("$DATA/leads.json", json_encode($r['leads'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $leads = $r['leads'];
                $msg = 'Búsqueda OK: ' . count($leads) . ' leads.';
            } else {
                $msg = 'Búsqueda falló: ' . ($r['error'] ?? '');
            }
        } elseif ($action === 'resolve') {
            $r = $svc->resolveProfile($account, trim($_POST['identifier'] ?? ''));
            $msg = $r['ok'] ? ('Perfil: ' . $e($r['profile']['nombre'] ?? '') . ' | provider_id: <code>' . $e($r['profile']['provider_id'] ?? '') . '</code>') : ('No se pudo resolver: ' . $e($r['error'] ?? ''));
    } elseif ($action === 'invite_send' || $action === 'message_send') {
        $isInvite = $action === 'invite_send';
        $provider = trim($_POST['provider'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $inmail = isset($_POST['inmail']);
        $kind = $isInvite ? 'invites' : 'messages';
        $cap = (int) (getenv($isInvite ? 'DAILY_INVITE_LIMIT' : 'DAILY_MSG_LIMIT') ?: ($isInvite ? 20 : 80));
        $used = (int) ($quotas[$account][$today][$kind] ?? 0);
        if ($used >= $cap) {
            $msg = "TOPE: $used/$cap hoy.";
        } elseif ($isInvite && mb_strlen($text) > 300) {
            $msg = 'Invitación supera 300 caracteres.';
        } else {
            $r = $isInvite ? $svc->sendInvite($account, $provider, $text) : $svc->sendDirectMessage($account, $provider, $text, $inmail);
            if ($r['ok']) {
                $quotas[$account][$today][$kind] = $used + 1;
                saveQuotas($quotas);
                $msg = 'Enviado OK (' . ($used + 1) . "/$cap hoy).";
            } else {
                $msg = 'Falló envío: ' . $e($r['error'] ?? '');
            }
        }
    } elseif ($action === 'block') {
        $bl = file_exists("$DATA/blocklist.json") ? (json_decode(file_get_contents("$DATA/blocklist.json"), true) ?: []) : [];
        $p = trim($_POST['provider'] ?? '');
        if ($p !== '' && !in_array($p, $bl, true)) {
            $bl[] = $p;
            file_put_contents("$DATA/blocklist.json", json_encode($bl, JSON_PRETTY_PRINT));
        }
        $msg = 'Agregado a no contactar.';
        }
        header('Location: panel.php?m=' . urlencode($msg));
        exit;
    }
}

$accounts = (new LinkedinService())->listAccounts();
$defAccount = $accounts['accounts'][0]['id'] ?? '';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Panel LinkedIn — Remates Pavez</title>
<style>:root{--li-blue:#0a66c2;--li-blue-dark:#004182;--li-bg:#f4f2ee;--li-card:#ffffff;--li-text:rgba(0,0,0,.9);--li-gray:#666666;--li-border:#e8e5df}body{font-family:-apple-system,"Segoe UI",Roboto,sans-serif;max-width:900px;margin:0 auto;padding:16px;background:var(--li-bg);color:var(--li-text)}h1{background:var(--li-blue);color:#fff;padding:12px 16px;border-radius:8px 8px 0 0;margin:0 -12px 16px}h2{color:var(--li-blue-dark)}table{border-collapse:collapse;width:100%;background:var(--li-card)}td,th{border:1px solid var(--li-border);padding:6px 10px;font-size:13px}th{background:var(--li-blue);color:#fff}.warn{background:#fff8e1;padding:12px;border:1px solid #e0c36a;border-radius:8px}form{margin:12px 0;padding:12px;border:1px solid var(--li-border);border-radius:8px;background:var(--li-card)}input,textarea,select{width:100%;margin:4px 0;padding:8px;border:1px solid var(--li-border);border-radius:4px}button{background:var(--li-blue);color:#fff;border:none;border-radius:24px;padding:8px 20px;font-weight:600;cursor:pointer}button:hover{background:var(--li-blue-dark)}a{color:var(--li-blue)}code{color:var(--li-blue-dark)}</style>
</head><body>
<h1>Panel LinkedIn — Remates Pavez (local)</h1>
<?php if ($msg): ?><p><b><?= $msg ?></b></p><?php endif; ?>
<h2>Cuentas</h2>
<ul><?php foreach ($accounts['accounts'] ?? [] as $a): ?>
<li><?= $e($a['nombre']) ?> — <?= $e($a['estado']) ?> — invitaciones hoy: <?= (int) ($quotas[$a['id']][$today]['invites'] ?? 0) ?>/20, mensajes: <?= (int) ($quotas[$a['id']][$today]['messages'] ?? 0) ?>/80</li>
<?php endforeach; ?></ul>

<?php if ($confirm): ?>
<div class="warn"><h2>Confirmar envío (<?= $e($confirm['type']) ?>)</h2>
<p>Cuenta: <?= $e($confirm['account']) ?><br>Destino: <code><?= $e($confirm['provider']) ?></code><?= $confirm['inmail'] ? ' (INMAIL: gasta crédito)' : '' ?></p>
<p><?= nl2br($e($confirm['text'])) ?></p>
<form method="post">
<input type="hidden" name="action" value="<?= $confirm['type'] === 'invite' ? 'invite_send' : 'message_send' ?>">
<input type="hidden" name="account" value="<?= $e($confirm['account']) ?>">
<input type="hidden" name="provider" value="<?= $e($confirm['provider']) ?>">
<input type="hidden" name="text" value="<?= $e($confirm['text']) ?>">
<?php if ($confirm['inmail']): ?><input type="hidden" name="inmail" value="1"><?php endif; ?>
<button type="submit">Confirmar y enviar</button>
</form></div>
<?php endif; ?>

<h2>Buscar leads</h2>
<form method="post"><input type="hidden" name="action" value="search">
Cuenta: <input name="account" value="<?= $e($defAccount) ?>">
Cargo: <select name="cargo">
<option>Liquidador de siniestros</option>
<option>Liquidador de siniestros de vehículos</option>
<option>Jefe de siniestros</option>
<option>Jefe de liquidación de siniestros</option>
<option>Supervisor de siniestros</option>
<option>Liquidador concursal</option>
<option>Veedor</option>
<option>Ajustador de seguros</option>
</select>
Keywords: <input name="keywords" value="Chile">
Ubicación: <input name="ubicacion" value="Chile">
Límite: <select name="limit"><option>10</option><option selected>20</option><option>30</option><option>50</option></select>
<button>Buscar</button></form>

<h2>Resolver perfil por URL (para invitar)</h2>
<form method="post"><input type="hidden" name="action" value="resolve">
Cuenta: <input name="account" value="<?= $e($defAccount) ?>">
URL o public_id: <input name="identifier" placeholder="https://www.linkedin.com/in/...">
<button>Resolver</button></form>

<h2>Invitar / mensaje (pide confirmación)</h2>
<form method="post"><input type="hidden" name="action" value="invite_preview">
Cuenta: <input name="account" value="<?= $e($defAccount) ?>">
Provider destino: <input name="provider" placeholder="provider_id del lead">
Texto invitación (máx 300): <textarea name="text" rows="3">Soy Ignacio Guajardo, de Remates Pavez. Conectemos para compartirle nuestros próximos remates de vehículos y activos de liquidadores. Quedo atento.</textarea>
<button>Previsualizar invitación</button></form>
<form method="post"><input type="hidden" name="action" value="message_preview">
Cuenta: <input name="account" value="<?= $e($defAccount) ?>">
Provider destino: <input name="provider">
<label><input type="checkbox" name="inmail" value="1"> InMail (no contacto, gasta crédito)</label>
Texto mensaje: <textarea name="text" rows="3"></textarea>
<button>Previsualizar mensaje</button></form>

<h2>Eventos recibidos (webhook)</h2>
<?php $elog = file_exists("$DATA/webhook.log") ? array_slice(file("$DATA/webhook.log"), -10) : []; ?>
<table><tr><th>Fecha</th><th>Evento</th></tr>
<?php foreach (array_reverse($elog) as $line): $p = explode(' ', trim($line), 2); ?>
<tr><td><?= $e($p[0] ?? '') ?></td><td><code><?= $e(mb_substr($p[1] ?? '(verificación)', 0, 300)) ?></code></td></tr>
<?php endforeach; ?></table>
</body></html>
