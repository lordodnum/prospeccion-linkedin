# Módulo LinkedIn aparte — Remates Pavez (alcance acotado, cero sanción como prioridad)

## 1. Decisión
Módulo separado del panel de mailing IDA y de FENIX. No comparte base, ni cuenta, ni cola de envío con mailing masivo.

## 2. Objetivo
Prospección uno a uno a liquidadores y veedores (concursales y siniestros), con trazabilidad y topes que evitan sanción. No es envío masivo.

## 3. Lo que hace / no hace
Hace: búsqueda acotada por cargo, invitación con nota <300 caracteres, espera de aceptación, 1 mensaje + hasta 2 seguimientos espaciados, registro de estados y opt-out.
No hace: scraping de LinkedIn, mensajes a no contactos sin InMail, envíos programados masivos, uso de credenciales ajenas, mezcla con base IDA sin consentimiento.

## 4. Cuentas y proveedor
- 1 cuenta LinkedIn dedicada a prospección (no Basilio, no página). Titular autoriza por hosted-auth.
- Proveedor Unipile (pagado por cuenta + uso). Sin Unipile este módulo no envía; el código FENIX está cableado a su API.
- Claves en variables de entorno (UNIPILE_DSN, UNIPILE_API_KEY, secreto webhook), cifrado en reposo.

## 5. Datos mínimos
- linkedin_accounts: id, provider, account_id externo, nombre, estado, límites diarios, última sync.
- linkedin_prospects: asistente/campaña, account_id, urn, nombre, cargo, empresa, url, estado (nuevo/invitado/aceptado/mensajeado/respondido/interesado/no_interesado), fechas, meta json.
- linkedin_daily_quotas: account_id, fecha, invitaciones, mensajes.
- Lista no contactar: global, nunca recontactar.

## 6. Límites anti-sanción (por cuenta)
- 20 invitaciones/día, 80 mensajes/día, máx 100 invitaciones/semana.
- Horario 09:00–18:00 America/Santiago, pausa aleatoria 60–180 s entre acciones, rotación si hay más de 1 cuenta.
- Todo mensaje prerredactado exige revisión y clic afirmativo del operador antes de salir.

## 7. Flujo
1. Operador define filtros (cargo, keywords, ubicación Chile) y plantillas.
2. Búsqueda de prueba con preview y puntaje. Importa a lista tipo linkedin.
3. Cola dedicada: invitar → esperar aceptación (webhook) → mensaje 1 → seguimiento 1-2 con días de pausa → si responde con interés, crea gestión y pide correo para IDA.
4. Monitor con transcript y disposición. Opt-out inmediato si pide no insistir.

## 8. Endpoints mínimos
- GET /linkedin/accounts, POST /linkedin/connect, POST /linkedin/{id}/test
- POST /linkedin/search, POST /linkedin/profile, POST /linkedin/invite, POST /linkedin/message
- POST /webhook/linkedin (firmado): aceptaciones y respuestas.

## 9. Pruebas de aceptación
- Conexión OK, 1 búsqueda test, 1 invitación test a contacto propio, 1 mensaje test, webhook verificado, cuota diaria enforced, opt-out verificado.

## 10. Riesgos declarados
Unipile reduce riesgo frente a scraper casero, no lo elimina. LinkedIn puede restringir la cuenta dedicada. Por eso va aparte: si cae, el mailing IDA sigue intacto.

## 11. Entregables
Código aparte, .env ejemplo, manual operador de 1 página, log de auditoría exportable.
