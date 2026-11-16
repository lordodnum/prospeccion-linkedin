<?php
declare(strict_types=1);
// Estado del modulo: cuenta, cuotas de hoy, leads guardados, webhook.
// php scripts/status.php
$dir = __DIR__ . '/../data';
$leads = file_exists($dir . '/leads.json') ? (json_decode(file_get_contents($dir . '/leads.json'), true) ?: []) : [];
$quotas = file_exists($dir . '/quotas.json') ? (json_decode(file_get_contents($dir . '/quotas.json'), true) ?: []) : [];
$bl = file_exists($dir . '/blocklist.json') ? (json_decode(file_get_contents($dir . '/blocklist.json'), true) ?: []) : [];
$wh = file_exists($dir . '/webhook.log') ? count(file($dir . '/webhook.log')) : 0;
$today = date('Y-m-d');
echo "Leads en data/leads.json: " . count($leads) . "\n";
echo "Bloqueados: " . count($bl) . " | eventos webhook: $wh\n";
foreach ($quotas as $acc => $days) {
    $d = $days[$today] ?? [];
    $inv = (int) ($d['invites'] ?? 0);
    $ms = (int) ($d['messages'] ?? 0);
    echo "Cuenta $acc hoy: invitaciones $inv/20, mensajes $ms/80\n";
}
if (empty($quotas)) {
    echo "Sin envíos hoy.\n";
}
