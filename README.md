# osintdeck
# OSINT Deck

Plugin de WordPress para construir un mazo de herramientas OSINT con filtros dinámicos, métricas y controles de abuso.

## Shortcode
```
[osint_deck]
[osint_deck category="dominios" access="gratuito" limit="20"]
```
- `category`: filtra por categoría.
- `access`: filtra por tipo de acceso.
- `limit`: cantidad inicial de resultados (por defecto 20).

## Frontend
- Interfaz estilo chat: botón pegar/limpiar, filtros dinámicos (tipo, acceso, licencia, categoría).
- Detección automática de input: email, dominio/subdominio, URL, IP (v4/v6), ASN, MAC, UUID, hashes (MD5/SHA1/SHA256), teléfono, coordenadas, wallets (BTC/ETH), archivos conocidos, PGP, username/alias, nombre completo, ZIP/código postal, paquetes `com.*.*`, palabras clave/intención.
- Badges dinámicos por herramienta: Popular, Nueva, Reportada, Recomendada (según último `input_type` usado).
- Deck/carrusel de cartas por herramienta, botón principal “Analizar {input}”, copiar/compartir, reporte.
- Accesibilidad: `aria-live` en detección, `aria-labelledby` en modal, focus trap en overlay, `alt` descriptivo en logos.

## Métricas
- Se almacenan en la opción `osd_tool_metrics` con ventanas de 30 días.
- Cálculo diario (cron `osd_metrics_daily`) desde logs para clicks y reportes.
- `clicks_7d`, `reports_7d`, `last_input_type`, `created_at` y badges calculados en `OSD_Metrics::meta_for()`.
- Endpoint admin AJAX `osd_metrics_summary` devuelve: `tool_id, clicks_7d, reports_7d, badges[], created_at, last_input_type`.

## Rate limiting
- Límite: 60 acciones/minuto por IP, cooldown automático.
- Límite diario opcional y 1 reporte por día por herramienta/IP.
- Integrado en AJAX `osd_user_event` con respuestas estándar:
  - `{ ok:false, code:"rate_limited", msg:"..." }`
  - `{ ok:false, code:"report_limit", msg:"..." }`

## TLDs y validación de dominios
- Archivo local IANA: `/mnt/data/tlds-alpha-by-domain.txt` (copiado a `assets/data`).
- Cron semanal `osd_refresh_tlds_weekly` actualiza lista y opción `osd_valid_tlds`.
- Función `osd_is_valid_domain()` valida sin llamadas externas.

## Admin
- Ajustes de seguridad: QPM, QPD, cooldown, reportes/día, umbral Popular y días “Nueva”.
- CRUD de herramientas, import/export JSON, logs de usuario/admin, métricas resumidas.
- Assets de admin en `assets/admin/`.

## Hooks y cron
- Activación: opciones base, semillas TLD, schedule de métricas y TLD.
- Desactivación: limpia cron de métricas y TLD.
- Cron diarios/semanales: `osd_metrics_daily`, `osd_refresh_tlds_weekly`.

## AJAX principales
- `osd_user_event`: clicks/reportes con rate limit.
- `osd_check_tld`: validación offline de TLD.
- Admin: `osd_metrics_summary`, `osd_tools_*`, `osd_logs_*`, export CSV/JSON.
