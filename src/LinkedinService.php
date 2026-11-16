<?php
declare(strict_types=1);

namespace RpLinkedin;

use GuzzleHttp\Client;

/**
 * Cliente Unipile para LinkedIn, portado de FENIX/marmota a modulo standalone.
 * Sin framework: Guzzle + log a error_log. Sin credenciales de terceros:
 * la cuenta se conecta por hosted-auth Unipile, nunca se guarda su clave.
 */
class LinkedinService
{
    private string $dsn;
    private string $apiKey;
    private Client $http;

    public function __construct(?string $dsn = null, ?string $apiKey = null)
    {
        $this->dsn = rtrim($dsn ?: (string) getenv('UNIPILE_DSN'), '/');
        $this->apiKey = $apiKey ?? (string) getenv('UNIPILE_API_KEY');
        $this->http = new Client(['timeout' => 25, 'headers' => [
            'X-API-KEY' => $this->apiKey,
            'Accept' => 'application/json',
        ]]);
    }

    public function configured(): bool
    {
        return $this->dsn !== '' && $this->apiKey !== '';
    }

    /** GET /api/v1/accounts — verifica credenciales y lista cuentas. */
    public function listAccounts(): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'status' => 0, 'accounts' => [], 'error' => 'unipile_not_configured'];
        }
        try {
            $r = $this->http->get($this->dsn . '/api/v1/accounts');
            $st = $r->getStatusCode();
            if ($st >= 200 && $st < 300) {
                $j = json_decode((string) $r->getBody(), true) ?? [];
                $raw = $j['items'] ?? $j['data'] ?? (is_array($j) ? $j : []);
                return ['ok' => true, 'status' => $st, 'accounts' => $this->normalizeAccounts($raw), 'error' => null];
            }
            return ['ok' => false, 'status' => $st, 'accounts' => [], 'error' => 'http_' . $st];
        } catch (\Throwable $e) {
            error_log('Unipile listAccounts: ' . $e->getMessage());
            return ['ok' => false, 'status' => 0, 'accounts' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Busqueda de personas. 1 titulo por llamada (LinkedIn no hace OR con comas).
     * POST /api/v1/linkedin/search?account_id=XXX&limit=N
     */
    public function searchLeads(array $filters, ?string $accountId = null, int $limit = 20): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'leads' => [], 'error' => 'unipile_not_configured'];
        }
        if (empty($accountId)) {
            return ['ok' => false, 'leads' => [], 'error' => 'account_id_required'];
        }
        $limit = max(1, min(50, $limit));
        $keywords = trim((string) preg_replace('/\s+/', ' ', str_replace(',', ' ', $filters['keywords'] ?? '')));
        $titles = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) ($filters['cargo'] ?? '')))));
        $titles = array_slice($titles, 0, 3);
        if (empty($titles)) {
            $titles = [null];
        }
        $all = [];
        $searches = 0;
        foreach ($titles as $title) {
            $body = ['api' => 'classic', 'category' => 'people'];
            if ($keywords !== '') {
                $body['keywords'] = $keywords;
            }
            if ($title) {
                $body['advanced_keywords'] = ['title' => $title];
            }
            try {
                $r = $this->http->post(
                    $this->dsn . '/api/v1/linkedin/search?account_id=' . urlencode($accountId) . '&limit=' . $limit,
                    ['json' => $body]
                );
                $searches++;
                if ($r->getStatusCode() >= 200 && $r->getStatusCode() < 300) {
                    $j = json_decode((string) $r->getBody(), true) ?? [];
                    $raw = $j['items'] ?? $j['results'] ?? $j['data'] ?? [];
                    foreach ($this->normalizeLeads(is_array($raw) ? array_values($raw) : []) as $lead) {
                        $all[($lead['linkedin_url'] ?? '') . '|' . $lead['nombre']] = $lead;
                    }
                } elseif (empty($all)) {
                    return ['ok' => false, 'leads' => [], 'error' => 'http_' . $r->getStatusCode()];
                }
            } catch (\Throwable $e) {
                if (empty($all)) {
                    return ['ok' => false, 'leads' => [], 'error' => $e->getMessage()];
                }
            }
        }
        return ['ok' => true, 'leads' => array_values($all), 'searches' => $searches, 'error' => null];
    }

    /** Invitacion con nota max 300 caracteres. POST /api/v1/users/invite */
    public function sendInvite(string $accountId, string $providerId, ?string $message = null): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'unipile_not_configured'];
        }
        $payload = ['provider_id' => $providerId, 'account_id' => $accountId];
        if ($message !== null && trim($message) !== '') {
            $payload['message'] = mb_substr(trim($message), 0, 300);
        }
        try {
            $r = $this->http->post($this->dsn . '/api/v1/users/invite', ['json' => $payload]);
            if ($r->getStatusCode() >= 200 && $r->getStatusCode() < 300) {
                return ['ok' => true, 'result' => json_decode((string) $r->getBody(), true), 'error' => null];
            }
            return ['ok' => false, 'result' => null, 'error' => 'http_' . $r->getStatusCode()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mensaje directo (requiere ser contacto; con InMail a no-contactos si la
     * cuenta tiene creditos). POST /api/v1/chats multipart.
     */
    public function sendDirectMessage(string $accountId, string $providerId, string $text, bool $inmail = false): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'unipile_not_configured'];
        }
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'text_required'];
        }
        try {
            $multipart = [
                ['name' => 'account_id', 'contents' => $accountId],
                ['name' => 'text', 'contents' => $text],
                ['name' => 'attendees_ids[]', 'contents' => $providerId],
                ['name' => 'linkedin[api]', 'contents' => 'classic'],
            ];
            if ($inmail) {
                $multipart[] = ['name' => 'linkedin[inmail]', 'contents' => 'true'];
            }
            $r = $this->http->post($this->dsn . '/api/v1/chats', ['multipart' => $multipart]);
            if ($r->getStatusCode() >= 200 && $r->getStatusCode() < 300) {
                return ['ok' => true, 'result' => json_decode((string) $r->getBody(), true), 'error' => null];
            }
            return ['ok' => false, 'result' => null, 'error' => 'http_' . $r->getStatusCode()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }
    }

    private function normalizeAccounts(array $raw): array
    {
        return array_values(array_map(function ($a) {
            if (!is_array($a)) {
                return ['id' => (string) $a, 'nombre' => (string) $a, 'estado' => 'unknown'];
            }
            return [
                'id' => (string) ($a['id'] ?? $a['account_id'] ?? ''),
                'nombre' => $a['name'] ?? $a['username'] ?? ($a['id'] ?? 'cuenta'),
                'estado' => strtolower((string) ($a['connection_status'] ?? $a['status'] ?? 'connected')),
            ];
        }, $raw));
    }

    private function normalizeLeads(array $raw): array
    {
        return array_values(array_map(function ($p) {
            if (!is_array($p)) {
                return ['nombre' => (string) $p, 'cargo' => '', 'empresa' => '', 'ubicacion' => '', 'linkedin_url' => null, 'provider_id' => null, 'public_id' => null];
            }
            $nombre = $p['name'] ?? $p['full_name'] ?? trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
            $empresa = $p['company'] ?? $p['company_name'] ?? $p['current_company'] ?? '';
            return [
                'nombre' => $nombre ?: 'Sin nombre',
                'cargo' => $p['headline'] ?? $p['title'] ?? $p['occupation'] ?? '',
                'empresa' => is_string($empresa) ? $empresa : '',
                'ubicacion' => $p['location'] ?? $p['geo'] ?? '',
                'linkedin_url' => $p['linkedin_url'] ?? $p['profile_url'] ?? null,
                'provider_id' => $p['provider_id'] ?? $p['providerId'] ?? null,
                'public_id' => $p['public_identifier'] ?? $p['public_id'] ?? null,
            ];
        }, $raw));
    }

    /**
     * Resuelve un perfil por public_id o URL para obtener su provider_id.
     * GET /api/v1/users/{identifier}?account_id=XXX (sin costo de envio).
     */
    public function resolveProfile(string $accountId, string $identifier): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'profile' => null, 'error' => 'unipile_not_configured'];
        }
        $identifier = trim($identifier);
        if (preg_match('~linkedin\.com/in/([^/?#]+)~i', $identifier, $m)) {
            $identifier = urldecode($m[1]);
        }
        $identifier = trim($identifier, '/');
        if ($identifier === '') {
            return ['ok' => false, 'profile' => null, 'error' => 'identifier_required'];
        }
        try {
            $r = $this->http->get($this->dsn . '/api/v1/users/' . urlencode($identifier), ['query' => ['account_id' => $accountId]]);
            if ($r->getStatusCode() >= 200 && $r->getStatusCode() < 300) {
                $p = json_decode((string) $r->getBody(), true) ?? [];
                return ['ok' => true, 'profile' => [
                    'provider_id' => $p['provider_id'] ?? null,
                    'nombre' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: ($p['name'] ?? ''),
                    'headline' => $p['headline'] ?? $p['occupation'] ?? '',
                    'public_id' => $p['public_identifier'] ?? $identifier,
                    'linkedin_url' => 'https://www.linkedin.com/in/' . ($p['public_identifier'] ?? $identifier),
                ], 'error' => null];
            }
            return ['ok' => false, 'profile' => null, 'error' => 'http_' . $r->getStatusCode()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'profile' => null, 'error' => $e->getMessage()];
        }
    }
}
