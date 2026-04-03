# Revisión y Actualización del Trabajo Final Integrador (TFI) - OSINT Deck

Este documento detalla los cambios sugeridos para el Trabajo Final de Licenciatura (TFI) basándose en las últimas actualizaciones del plugin OSINT Deck. Se incluyen explicaciones técnicas, una guía de instalación y el anexo de documentación solicitado.

---

## 1. Cambios Sugeridos y Justificación

### A. Arquitectura del Sistema (Sección: Diseño de la arquitectura del plugin)
**Cambio:** Actualizar la descripción de la arquitectura de "modular basada en componentes" a una **Arquitectura de Capas (Clean Architecture)**.
**Justificación:** El plugin ha evolucionado para separar claramente las responsabilidades en:
- **Dominio:** Lógica de negocio pura (Servicios como `DecisionEngine`, `InputParser`, `NaiveBayesClassifier`).
- **Infraestructura:** Persistencia y servicios externos (Repositorios en tablas personalizadas, `TLDManager`, `Logger`).
- **Presentación:** Interfaz de usuario (Admin, API/AJAX, Frontend/Shortcodes).
Esto garantiza que el sistema sea escalable y fácil de mantener, permitiendo cambiar la base de datos o la interfaz sin afectar la lógica de investigación.

### B. Motor de Detección (Sección: Programación del plugin)
**Cambio:** Incorporar el uso de **Inteligencia Artificial (Clasificador Naive Bayes)** junto con el análisis de expresiones regulares (Regex).
**Justificación:** La detección ya no es puramente estática. El nuevo `NaiveBayesClassifier` permite:
- Detectar intenciones abstractas (ayuda, noticias, saludos).
- Clasificar tipos de datos complejos que no siguen un patrón rígido.
- Aprender de nuevos ejemplos mediante el "Entrenador AI" en el panel de administración.

### C. Gestión de Datos (Sección: Implementación técnica)
**Cambio:** Reflejar el uso de **Tablas SQL Personalizadas** (`wp_osint_tools`, `wp_osint_categories`, `wp_osint_logs`) en lugar de opciones de WordPress o archivos JSON estáticos.
**Justificación:** El uso de tablas dedicadas mejora significativamente el rendimiento en búsquedas complejas y permite manejar grandes volúmenes de herramientas (Big Data OSINT) sin sobrecargar la tabla `wp_options` de WordPress.

### D. Seguridad y Filtrado (Sección: Medidas de seguridad)
**Cambio:** Añadir el módulo de **Detección de Toxicidad**.
**Justificación:** Se ha implementado un filtro para detectar lenguaje ofensivo o búsquedas no éticas, reforzando el carácter profesional de la herramienta y cumpliendo con estándares de seguridad en aplicaciones web.

---

## 2. Guía de Instalación (Nueva Sección)

Para incluir en el documento final o como anexo:

### Requisitos Previos
- WordPress 6.0 o superior.
- PHP 7.4 o superior (Recomendado PHP 8.2+).
- Base de datos MySQL 5.7 o MariaDB 10.3+.

### Pasos para la Instalación
1. **Descarga y Carga:**
   - Comprimir la carpeta del plugin `osint-deck` en un archivo `.zip`.
   - Ir al panel de WordPress: **Plugins > Añadir nuevo > Subir plugin**.
   - Seleccionar el archivo `.zip` e instalar.
2. **Activación:**
   - Una vez instalado, hacer clic en **Activar plugin**.
   - El sistema creará automáticamente las tablas necesarias en la base de datos y cargará los datos iniciales (categorías y herramientas base).
3. **Configuración Inicial:**
   - Navegar a **OSINT Deck > Ajustes** en el menú lateral.
   - Configurar el modo de tema (Claro/Oscuro) y las URLs de ayuda.
   - Verificar en **OSINT Deck > TLD Manager** que la lista de extensiones de dominio esté actualizada.
4. **Despliegue en el Sitio:**
   - Crear una nueva página o entrada.
   - Insertar el shortcode `[osint_deck]` donde se desee mostrar la herramienta.

---

## 3. Anexo: Documentación Técnica Unificada

Este anexo complementa la información técnica del proyecto:

### Estructura de Directorios
- `src/Core/`: Inicialización y coordinación (`Bootstrap`).
- `src/Domain/`: Lógica central (Clasificación, Detección, Decisiones).
- `src/Infrastructure/`: Persistencia (Tablas SQL) y servicios (Logs, TLDs).
- `src/Presentation/`: Interfaz administrativa, API AJAX y visualización frontend.
- `assets/`: Recursos estáticos (CSS, JS, Imágenes).

### Endpoints de la API (AJAX)
El plugin utiliza `admin-ajax.php` con las siguientes acciones principales:
- `osint_deck_search`: Procesa la consulta del usuario y devuelve las herramientas sugeridas.
- `osint_deck_log_event`: Registra clics y uso de herramientas para métricas.
- `osint_deck_train_ai`: Permite añadir muestras de entrenamiento al clasificador.

### Mantenimiento
- **Limpieza de Logs:** El plugin incluye una tarea programada (Cron) que limpia registros antiguos automáticamente cada 24 horas.
- **Actualización de TLDs:** Se recomienda actualizar periódicamente la lista de dominios desde el panel administrativo para garantizar la precisión en la detección de URLs.

---

## 4. Notas Finales para el Autor

He realizado estas sugerencias porque el trabajo actual (según el contenido extraído) describe una versión más estática del proyecto. Las actualizaciones recientes han convertido a OSINT Deck en una plataforma mucho más robusta y "con conciencia" del dato ingresado, lo cual le da un valor académico y técnico superior para un TFI de Licenciatura en Ciberseguridad.

*Este documento ha sido generado automáticamente para asistir en la revisión del TFI.*
