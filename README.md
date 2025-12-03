# OSINT Deck

OSINT Deck es un plugin de WordPress diseñado para centralizar y organizar herramientas OSINT en un mazo dinámico.  
El sistema detecta automáticamente el tipo de dato ingresado y activa las herramientas correspondientes, permitiendo análisis rápidos, ordenados y reproducibles en un entorno unificado.

Este repositorio contiene:

- `osint-deck/` → plugin completo  
- `template-de-herramientas/` → JSON de ejemplo para importar herramientas  

[IMAGEN...]

==================================================
## <img src="https://unpkg.com/feather-icons/dist/icons/cpu.svg" width="16"> Características principales
==================================================

### <img src="https://unpkg.com/feather-icons/dist/icons/search.svg" width="16"> Detección automática del tipo de dato

El plugin identifica automáticamente el contenido ingresado, soportando:

- Email  
- Dominio / subdominio  
- URL  
- IP v4 / v6  
- ASN  
- MAC  
- UUID  
- Hashes (MD5, SHA1, SHA256)  
- Teléfonos  
- Coordenadas  
- Wallets BTC / ETH  
- Claves PGP  
- Archivos conocidos  
- Username / alias  
- Nombre completo  
- ZIP / Código postal  
- Paquetes `com.*.*`  
- Palabras clave / intención de búsqueda  

### <img src="https://unpkg.com/feather-icons/dist/icons/layers.svg" width="16"> Mazo dinámico de herramientas

- Cartas individuales por herramienta  
- Botón principal “Analizar {input}”  
- Acciones extra: copiar, abrir, compartir  
- Filtros por categoría, licencia y tipo de acceso  
- Badges automáticos: Nueva, Popular, Reportada, Recomendada  
- Reporte de herramientas inactivas con un clic  

### <img src="https://unpkg.com/feather-icons/dist/icons/sliders.svg" width="16"> Filtros dinámicos en el frontend

- Categoría  
- Acceso (gratuito / registro / pago)  
- Tipo de herramienta  
- Licencia  
- Último tipo de input utilizado  

### <img src="https://unpkg.com/feather-icons/dist/icons/bar-chart-2.svg" width="16"> Métricas internas

Las métricas se generan diariamente y se almacenan en `osd_tool_metrics`.

Incluyen:

- `clicks_7d`  
- `reports_7d`  
- `created_at`  
- `last_input_type`  
- Badges calculados desde `OSD_Metrics::meta_for()`  

Endpoint principal de administración: `osd_metrics_summary`.

==================================================
## <img src="https://unpkg.com/feather-icons/dist/icons/code.svg" width="16"> Shortcode
==================================================

```text
[osint_deck]
[osint_deck category="dominios" access="gratuito" limit="20"]
Parámetros:

category: filtra por categoría de herramienta

access: tipo de acceso (por ejemplo, gratuito, registro, pago)

limit: cantidad inicial de resultados (por defecto 20)

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/download.svg" width="16"> Instalación
==================================================

Instalación manual
Descargar este repositorio.

Copiar la carpeta del plugin dentro de:

text
Copiar código
wp-content/plugins/osint-deck
En el panel de WordPress ir a:
Plugins → OSINT Deck → Activar.

(Opcional) Importar herramientas usando los JSON de:

text
Copiar código
template-de-herramientas/*.json
WP-CLI
bash
Copiar código
wp plugin activate osint-deck
==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/globe.svg" width="16"> Validación de dominios y TLDs
==================================================

Lista IANA local en assets/data/tlds-alpha-by-domain.txt.

Tarea semanal: osd_refresh_tlds_weekly.

Validación offline mediante osd_is_valid_domain() (sin llamadas externas).

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/shield.svg" width="16"> Rate limiting
==================================================

Implementado en osd_user_event:

60 acciones por minuto por IP

1 reporte por herramienta por día

Respuestas JSON consistentes (códigos como rate_limited, report_limit)

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/settings.svg" width="16"> Administración
==================================================

Funcionalidades del panel de administración:

Configuración de límites y opciones de seguridad

CRUD de herramientas

Importación y exportación en JSON

Logs de usuario y logs administrativos

Métricas resumidas por herramienta

Ajustes de badges y ventanas de tiempo

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/clock.svg" width="16"> Hooks y tareas programadas
==================================================

Activación
Inicialización de opciones básicas

Carga de TLD iniciales

Programación de tareas para métricas y actualización de TLD

Desactivación
Limpieza de cron jobs creados por el plugin

Cron jobs principales
osd_metrics_daily

osd_refresh_tlds_weekly

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/wifi.svg" width="16"> AJAX
==================================================

Públicos
osd_user_event

osd_check_tld

Administrativos
osd_metrics_summary

osd_tools_*

osd_logs_*

Endpoints para exportación CSV/JSON

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/map.svg" width="16"> Roadmap inicial
==================================================

Soporte para nuevos tipos de input avanzados

Exportación de mazos personalizados

Dashboard ampliado con comparativas de métricas

Integración con fuentes externas OSINT

Publicación en WordPress Plugin Directory

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/users.svg" width="16"> Autores
==================================================

Equipo de desarrollo:

Sebastián Cendra

Claudio Pandelo

Paolo Peña Ramírez

Guillermo Quintana

Damián Radiminsky

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/file-text.svg" width="16"> Licencia
==================================================

MIT.
Este software es gratuito para uso personal y profesional.

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/git-pull-request.svg" width="16"> Contribuciones
==================================================

Se aceptan pull requests y reportes de issues.
La guía básica de colaboración está en CONTRIBUTING.md.

==================================================

<img src="https://unpkg.com/feather-icons/dist/icons/tag.svg" width="16"> Versión
==================================================

1.0.0 – Primera versión pública del plugin.
