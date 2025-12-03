# OSINT Deck

OSINT Deck es un plugin de WordPress diseñado para centralizar y organizar herramientas OSINT en un mazo dinámico. El sistema detecta automáticamente el tipo de dato ingresado y activa las herramientas correspondientes, permitiendo análisis rápidos, ordenados y reproducibles en un entorno unificado.

Este repositorio contiene:

* `osint-deck/` → el plugin completo
* `template-de-herramientas/` → archivos JSON de referencia para importar herramientas

[IMAGEN...]

---

## Características principales

### <img src="https://unpkg.com/feather-icons/dist/icons/search.svg" width="16"> Detección automática del tipo de dato

El plugin identifica automáticamente el contenido ingresado, soportando:

* Email
* Dominio / subdominio
* URL
* IP v4/v6
* ASN
* MAC
* UUID
* Hashes (MD5, SHA1, SHA256)
* Teléfonos
* Coordenadas
* Wallets BTC / ETH
* Claves PGP
* Archivos conocidos
* Username / alias
* Nombre completo
* ZIP / Código postal
* Paquetes `com.*.*`
* Palabras clave / intención de búsqueda

### <img src="https://unpkg.com/feather-icons/dist/icons/layers.svg" width="16"> Mazo dinámico de herramientas

* Cartas individuales por herramienta
* Botón principal “Analizar {input}”
* Acciones extra: copiar, abrir, compartir
* Filtros por categoría, licencia y tipo de acceso
* Badges automáticos: Nueva, Popular, Reportada, Recomendada
* Reporte de herramientas inactivas con 1 clic

### <img src="https://unpkg.com/feather-icons/dist/icons/sliders.svg" width="16"> Filtros dinámicos del frontend

* Categoría
* Acceso (gratuito / registro / pago)
* Tipo de herramienta
* Licencia
* Último tipo de input utilizado

### <img src="https://unpkg.com/feather-icons/dist/icons/bar-chart-2.svg" width="16"> Métricas internas

Las métricas se generan diariamente y se almacenan en `osd_tool_metrics`.

Incluyen:

* `clicks_7d`
* `reports_7d`
* `created_at`
* `last_input_type`
* Badges calculados desde `OSD_Metrics::meta_for()`

Endpoint admin: `osd_metrics_summary`

---

## Shortcode

```
[osint_deck]
[osint_deck category="dominios" access="gratuito" limit="20"]
```

**Parámetros:**

* `category`: filtra por categoría de herramienta.
* `access`: tipo de acceso (gratuito, registro, pago).
* `limit`: cantidad inicial de resultados (por defecto 20).

---

## Shortcode

```
[osint_deck]
[osint_deck category="dominios" access="gratuito" limit="20"]
```

**Parámetros:**

* `category`: filtra por categoría de herramienta.
* `access`: tipo de acceso (gratuito, registro, pago).
* `limit`: cantidad inicial de resultados (por defecto 20).
