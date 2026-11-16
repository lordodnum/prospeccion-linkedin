<?php
declare(strict_types=1);
// Receptor webhook Unipile (aceptaciones y respuestas). Solo registra.
// Configure notify_url en Unipile apuntando aqui con secreto compartido.
$secret = getenv('UNIPILE_WEBHOOK_SECRET') ?: '';
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_UNIPILE_SIGNATURE'] ?? '';
if ($secret !== '' && $sig !== '' && !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
    http_response_code(403);
    echo 'firma invalida';
    exit;
}
@mkdir(__DIR__ . '/data', 0770, true);
file_put_contents(__DIR__ . '/data/webhook.log', date('c') . ' ' . $raw . "\n", FILE_APPEND);
http_response_code(200);
echo 'ok';
