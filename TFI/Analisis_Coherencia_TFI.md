# Análisis de Coherencia y Log de Cambios: TFI vs. Plugin Real (v1.3.7)

Este documento detalla la revisión exhaustiva realizada sección por sección del TFI original, contrastándolo con el código fuente actual del plugin.

---

## 1. Revisión Seccional (Coherencia Técnica)

### Sección: Resumen del Proyecto (Líneas 36-39)
- **Estado Original:** Describe una herramienta de búsqueda centralizada basada en "mazos".
- **Observación:** Es coherente, pero omite el salto tecnológico hacia la Inteligencia Artificial.
- **Cambio Sugerido:** Incorporar el concepto de **Motor de Decisión Híbrido**. El plugin ya no solo detecta datos por patrones (Regex), sino que interpreta intenciones (IA Naive Bayes), lo cual eleva el nivel técnico del proyecto para una licenciatura.

### Sección: Justificación (Líneas 40-45)
- **Estado Original:** Habla de la dispersión de herramientas y enlaces rotos.
- **Observación:** Menciona la "posibilidad de reportar herramientas inactivas". 
- **Verificación:** Coherente. El sistema de reportes está implementado en `src/Presentation/Api/UserEvents.php`. Sin embargo, se puede fortalecer mencionando el **TLDManager**, que valida dominios contra la lista oficial de IANA, reduciendo falsos positivos.

### Sección: Objetivos (Líneas 58-67)
- **Estado Original:** "Diseñar una arquitectura modular".
- **Observación:** El plugin ha superado la modularidad simple para adoptar una **Arquitectura de Capas (Clean Architecture)**.
- **Cambio Sugerido:** Redefinir el Objetivo Específico 2 para reflejar la separación de responsabilidades (Domain, Infrastructure, Presentation), lo cual es un estándar de oro en ciberseguridad para reducir la superficie de ataque.

### Sección: Proceso de Desarrollo - Arquitectura (Líneas 112-116)
- **Estado Original:** Enfoque modular basado en componentes.
- **Observación:** **Inconsistente con el código actual.** 
- **Cambio Sugerido:** Reemplazar por la descripción de la arquitectura de capas. 
    - **Capa de Dominio:** Donde reside la lógica de investigación y clasificación.
    - **Capa de Infraestructura:** Gestión de base de datos SQL personalizada y servicios externos.
    - **Capa de Presentación:** Interfaz de usuario y API AJAX.

### Sección: Programación y Tecnologías (Líneas 117-123 / 130-136)
- **Estado Original:** Menciona JSON para el catálogo y PHP/JS/AJAX.
- **Observación:** **Parcialmente desactualizado.**
- **Cambio Sugerido:** El plugin ahora utiliza **Tablas SQL Personalizadas** (`wp_osint_tools`, `wp_osint_categories`) para manejar el catálogo. Esto es un cambio crítico ya que mejora el rendimiento y la escalabilidad (Big Data OSINT). Se debe mencionar el uso de `$wpdb->prepare` para la seguridad contra inyecciones SQL.

### Sección: Pruebas y Validación (Líneas 137-167)
- **Estado Original:** Pruebas manuales y funcionales.
- **Observación:** Falta la validación de seguridad bajo estándares internacionales.
- **Cambio Sugerido:** Incorporar las auditorías realizadas bajo el estándar **OWASP Top 10 2021**, demostrando que el plugin es resistente a ataques comunes de la web.

---

## 2. Nueva Sección: Cumplimiento de Seguridad (OWASP Top 10)

Para el perfil de Ciberseguridad, es vital demostrar cómo el desarrollo mitiga riesgos. Se ha analizado el plugin bajo la versión 2021:

1. **A01:2021-Control de Acceso Quebrado**: Mitigado mediante el uso riguroso de `current_user_can('manage_options')` en todos los endpoints administrativos y el uso de **Nonces** de WordPress para evitar ataques CSRF.
2. **A03:2021-Inyección**: Mitigado mediante el uso de consultas preparadas (`$wpdb->prepare`) para prevenir SQL Injection y funciones de escape (`esc_html`, `esc_attr`) para prevenir XSS (Cross-Site Scripting).
3. **A04:2021-Diseño Inseguro**: Mitigado mediante una arquitectura de capas que aísla la lógica de negocio de la interfaz, facilitando auditorías de código.
4. **A09:2021-Fallas en el Registro y Monitoreo**: Implementado un sistema de logging persistente (`LogsTable`) que registra eventos críticos y errores, permitiendo la trazabilidad forense de las investigaciones.
5. **A10:2021-Falsificación de Solicitudes del Lado del Servidor (SSRF)**: El plugin valida las URLs externas (Google, IANA) antes de realizar peticiones mediante `wp_remote_get`.

---

## 3. Hoja de Ruta de Implementación en el Documento Final

1. **Actualizar el Resumen y la Introducción** para incluir el componente de IA.
2. **Reescribir la sección de Arquitectura** para detallar las capas Domain/Infrastructure/Presentation.
3. **Actualizar la sección de Base de Datos** mencionando el paso de JSON a SQL personalizado.
4. **Insertar la sección de Seguridad OWASP** antes de la Conclusión.
5. **Añadir la Guía de Instalación** como anexo técnico.

*Este análisis garantiza que el TFI no sea solo una descripción de una herramienta, sino un documento técnico sólido que respalda tu formación en Ciberseguridad.*
