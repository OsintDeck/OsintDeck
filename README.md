
---

```markdown
# OSINT Deck

OSINT Deck es un plugin de WordPress diseñado para centralizar y organizar herramientas OSINT en un mazo dinámico. El sistema detecta automáticamente el tipo de dato ingresado y activa las herramientas correspondientes, permitiendo análisis rápidos, ordenados y reproducibles en un entorno unificado.

Este repositorio contiene:
- `osint-deck/` → el plugin completo  
- `template-de-herramientas/` → archivos JSON de referencia para importar herramientas  

[IMAGEN...]

---

## Características principales

### <img src="https://unpkg.com/feather-icons/dist/icons/search.svg" width="16"> Detección automática del tipo de dato
El plugin identifica automáticamente el contenido ingresado, soportando:
- Email  
- Dominio / subdominio  
- URL  
- IP v4/v6  
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
- Reporte de herramientas inactivas con 1 clic  

### <img src="https://unpkg.com/feather-icons/dist/icons/sliders.svg" width="16"> Filtros dinámicos del frontend
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

Endpoint admin: `osd_metrics_summary`

---

## Shortcode

```

[osint_deck]
[osint_deck category="dominios" access="gratuito" limit="20"]

```

Parámetros:
- `category`: filtra por categoría  
- `access`: tipo de acceso  
- `limit`: cantidad inicial de resultados (por defecto 20)

---

## Instalación

### <img src="https://unpkg.com/feather-icons/dist/icons/download.svg" width="16"> Instalación manual
1. Descargar el repositorio.  
2. Copiar la carpeta `osint-deck` dentro de:  
```

wp-content/plugins/

```
3. Activar el plugin desde  
```

WordPress → Plugins → Activar

```
4. (Opcional) Importar herramientas desde:  
```

template-de-herramientas/*.json

```

### WP-CLI
```

wp plugin activate osint-deck

```

---

## Validación de dominios y TLDs

- Lista IANA local en `assets/data/tlds-alpha-by-domain.txt`  
- Cron semanal: `osd_refresh_tlds_weekly`  
- Validación offline mediante `osd_is_valid_domain()`

---

## Rate limiting

Implementado en `osd_user_event`:
- 60 acciones por minuto por IP  
- 1 reporte por herramienta por día  
- Respuestas JSON estandarizadas  

---

## Administración

Incluye:
- Configuración de límites y seguridad  
- CRUD de herramientas  
- Import/export en JSON  
- Logs de usuario y logs administrativos  
- Métricas resumidas  
- Ajustes para badges y ventanas de tiempo  

---

## Hooks y cron

### Activación
- Semillas de TLD  
- Cron para métricas y actualización IANA  

### Desactivación
- Limpieza de cron jobs  

### Cron jobs
- `osd_metrics_daily`  
- `osd_refresh_tlds_weekly`

---

## AJAX

### Públicos
- `osd_user_event`  
- `osd_check_tld`

### Administrativos
- `osd_metrics_summary`  
- `osd_tools_*`  
- `osd_logs_*`  
- Exportación CSV/JSON  

---

## Roadmap inicial (sujeto a revisión)
- Nuevos tipos de input avanzados  
- Exportación de mazos personalizados  
- Dashboard ampliado con métricas comparativas  
- Integración con fuentes externas OSINT  
- Publicación simultánea en WordPress Plugin Directory  

---

## Autores

Sebastián Cendra  
Claudio Pandelo  
Paolo Peña Ramírez  
Guillermo Quintana  
Damián Radiminsky

---

## Licencia

MIT  
Este software es gratuito para uso personal y profesional.

---

## Contribuciones

Se aceptan pull requests y reportes de issues.  
Mantener un estilo de código consistente y documentar los cambios relevantes.

---

## Versión

**1.0.0** — Primera versión pública del plugin.
```

---
