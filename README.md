# Módulo LinkedIn aparte — Remates Pavez

Standalone, sin Laravel y sin FENIX. Servicio `src/LinkedinService.php` portado de
`FENIX/marmota`, con Guzzle en vez de facade Http. Sin credenciales de terceros:
la cuenta se conecta por hosted-auth Unipile.

## Instalar
```
composer install
cp .env.example .env   # completar UNIPILE_DSN y UNIPILE_API_KEY
sqlite3 base.sqlite < schema.sql
```

## Probar conexión (sin Unipile responde unipile_not_configured)
```
php scripts/test_connection.php
```

## Estructura
- `src/LinkedinService.php` — listAccounts, searchLeads, sendInvite, sendDirectMessage
- `schema.sql` — accounts, prospects, daily_quotas, blocklist
- `scripts/test_connection.php` — prueba de credenciales
- `ESPEC_MODULO_LINKEDIN_APARTE.md` — alcance acotado aprobado

## Límites anti-sanción
20 invitaciones/día, 80 mensajes/día por cuenta, 09:00–18:00 America/Santiago,
pausa 60–180 s, opt-out estricto. Ver especificación.
