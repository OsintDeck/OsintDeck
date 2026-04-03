# Manual Técnico y de Arquitectura: OSINT Deck (v1.3.7)

Este documento proporciona una visión profunda y detallada de todas las funcionalidades, componentes técnicos y decisiones de diseño de OSINT Deck. Está diseñado para ser utilizado como referencia técnica profesional y como contexto de alta fidelidad para LLMs (ChatGPT/Claude).

---

## 1. Visión General del Sistema
OSINT Deck es una plataforma de orquestación de inteligencia de fuentes abiertas (OSINT) integrada en WordPress. Su objetivo es transformar la investigación digital de un proceso manual fragmentado a un flujo de trabajo asistido por inteligencia artificial y una interfaz dinámica orientada al dato.

### 1.1 El Concepto "Deck-as-a-Tool"
- **Mazo (Deck):** Representa a la entidad proveedora o herramienta integral (ej. VirusTotal, Shodan). Es el contenedor lógico de capacidades.
- **Carta (Card):** Representa una funcionalidad atómica o tipo de análisis específico (ej. "Analizar IP en VirusTotal").
- **Lógica de Orquestación:** El sistema selecciona la "carta" pertinente según el tipo de dato detectado. Incluso herramientas únicas se tratan como un "mazo de una sola carta" para mantener la consistencia arquitectónica.

---

## 2. Arquitectura de Software (Clean Architecture)
El plugin sigue una **Arquitectura de Capas** para garantizar el desacoplamiento y la seguridad.

### 2.1 Capa de Dominio (Domain Layer) - `src/Domain/`
Contiene la lógica de negocio pura, independiente de WordPress.
- `InputParser`: Motor híbrido (Regex + IA).
- `NaiveBayesClassifier`: Clasificador probabilístico.
- `DecisionEngine`: Orquestador de resultados.

### 2.2 Capa de Infraestructura (Infrastructure Layer) - `src/Infrastructure/`
Gestión de persistencia y servicios externos.
- Repositorios SQL personalizados (`Tools`, `Categories`, `Logs`).
- `TLDManager`: Validación de dominios contra IANA.

### 2.3 Capa de Presentación (Presentation Layer) - `src/Presentation/`
Interfaz de usuario y API AJAX. Protegida por Nonces y verificación de capacidades.

---

## 3. Modelado de Amenazas (STRIDE) - [NUEVO]
Para un perfil de Ciberseguridad, se analizó el plugin bajo la metodología STRIDE:

| Amenaza | Mitigación en OSINT Deck |
| :--- | :--- |
| **Spoofing** (Suplantación) | Uso de WordPress Nonces para asegurar que las peticiones AJAX provengan de usuarios legítimos. |
| **Tampering** (Manipulación) | Validación y sanitización estricta de todos los inputs (`sanitize_text_field`, `prepare` en SQL). |
| **Repudiation** (Repudio) | Sistema de `LogsTable` que registra cada acción crítica, permitiendo auditoría forense. |
| **Information Disclosure** | Gestión de errores centralizada que evita mostrar trazas de PHP o rutas del servidor al usuario final. |
| **DoS** (Denegación de Servicio) | Implementación de limpieza automática de logs y validación de longitud de inputs para evitar desbordamientos. |
| **Elevation of Privilege** | Verificación estricta de `manage_options` en todos los controladores del panel administrativo. |

---

## 4. Auditoría de Seguridad (OWASP 2025)
- **A01:2025 - Broken Access Control:** Uso de `current_user_can` en endpoints administrativos.
- **A03:2025 - Software Supply Chain Failures:** Control de dependencias y auditoría de recursos externos.
- **A05:2025 - Injection:** Uso de `$wpdb->prepare` para prevenir SQLi.
- **A09:2025 - Security Logging and Alerting Failures:** Registro persistente de eventos críticos.
- **A10:2025 - Mishandling of Exceptional Conditions:** Gestión robusta de excepciones en peticiones remotas.

---

## 5. Estrategia de Hardening (Fortalecimiento) - [NUEVO]
Recomendaciones para el despliegue seguro de OSINT Deck:
1.  **Protección de Archivos:** Cada archivo PHP comienza con `defined('ABSPATH') || exit;` para evitar la ejecución directa.
2.  **Seguridad de Base de Datos:** Las tablas personalizadas utilizan prefijos de WordPress para integrarse con la seguridad nativa de la DB.
3.  **Principio de Menor Privilegio:** Solo los usuarios con rol de administrador pueden acceder al "Entrenador de IA" y al "Gestor de Herramientas".

---

## 6. Validación y QA
- **Pruebas de Clasificación:** El motor Bayesiano es validado mediante un set de entrenamiento de dorks conocidos.
- **Validación de TLDs:** El sistema sincroniza periódicamente con la IANA para evitar falsos negativos en la detección de dominios.

---

## 7. Limitaciones y Trabajo Futuro
- **Dependencia Externa:** La efectividad depende de la disponibilidad de las herramientas terceras.
- **Futuro:** Integración con LLMs para reportes forenses automatizados y expansión de la librería core a otros CMS.

---
*Documento profesional para defensa de TFI de Licenciatura en Ciberseguridad.*
