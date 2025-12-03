<div align="center">
  <img src="https://osint.com.ar/wp-content/uploads/2023/10/cropped-logo-osint-com-ar-1.png" alt="OSINT Deck Logo" width="200" />
  <h1>OSINT Deck</h1>
  <p><strong>Centraliza, Organiza y Acelera tus Investigaciones OSINT en WordPress</strong></p>

  <p>
    <a href="#-características">Características</a> •
    <a href="#-instalación">Instalación</a> •
    <a href="#-documentación">Documentación</a> •
    <a href="#-stack-tecnológico">Tech Stack</a>
  </p>

  <p>
    <img src="https://img.shields.io/badge/WordPress-Plugin-21759B?style=for-the-badge&logo=wordpress&logoColor=white" alt="WordPress Plugin" />
    <img src="https://img.shields.io/badge/Versión-1.0.0-blue?style=for-the-badge" alt="Version 1.0.0" />
    <img src="https://img.shields.io/badge/Licencia-MIT-green?style=for-the-badge" alt="License MIT" />
  </p>
</div>

---

## 🚀 Descripción

**OSINT Deck** es un plugin avanzado para WordPress diseñado para transformar tu sitio en una estación de trabajo de inteligencia. Centraliza tus herramientas favoritas en un mazo dinámico que reacciona inteligentemente a tus datos.

El sistema **detecta automáticamente** el tipo de dato ingresado (Email, IP, Dominio, etc.) y despliega instantáneamente las herramientas correspondientes, permitiendo análisis rápidos, ordenados y reproducibles.

---

## 🛠 Stack Tecnológico

Este proyecto ha sido construido utilizando tecnologías robustas y modernas para asegurar rendimiento y escalabilidad:

<div align="center">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript" />
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5" />
  <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3" />
  <img src="https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white" alt="WordPress API" />
  <img src="https://img.shields.io/badge/AJAX-000000?style=for-the-badge&logo=json&logoColor=white" alt="AJAX" />
</div>

---

## ✨ Características Principales

### 🧠 Detección Inteligente de Tipos de Dato
Olvídate de buscar la herramienta correcta. OSINT Deck identifica automáticamente:

| Categoría | Tipos Soportados |
|-----------|------------------|
| **Red** | IP (v4/v6), Dominios, Subdominios, ASN, MAC |
| **Identidad** | Emails, Usernames, Nombres Completos, Teléfonos |
| **Cripto** | Wallets BTC, ETH |
| **Forense** | Hashes (MD5, SHA1, SHA256), Archivos, UUIDs |
| **Geo** | Coordenadas, ZIP/Códigos Postales |

### 🎴 Mazo Dinámico de Herramientas
- **Cartas Interactivas:** Cada herramienta es una carta con acciones rápidas.
- **Acciones One-Click:** Analizar, Copiar, Abrir, Compartir.
- **Filtros en Tiempo Real:** Por categoría, licencia y tipo de acceso.
- **Badges Automáticos:** `Popular`, `Nueva`, `Recomendada`.

### 🛡️ Seguridad y Rate Limiting
- **Protección de Abuso:** 60 acciones/minuto por IP.
- **Validación Offline:** Verificación de dominios y TLDs sin latencia externa.
- **Reportes:** Sistema de reporte de herramientas caídas.

### 📊 Métricas Avanzadas
Panel administrativo con estadísticas detalladas:
- `clicks_7d`: Tendencias de uso semanal.
- `last_input_type`: Análisis de los tipos de datos más investigados.

---

## 📦 Instalación

### Método Manual
1.  Descarga el repositorio completo.
2.  Copia la carpeta `osint-deck` en tu directorio de plugins:
    ```bash
    /wp-content/plugins/osint-deck/
    ```
3.  Activa el plugin desde el panel de administración de WordPress.

### Vía WP-CLI
```bash
wp plugin activate osint-deck
```

> **Tip:** Puedes importar herramientas de ejemplo desde la carpeta `template-de-herramientas/`.

---

## 💻 Uso

Implementa OSINT Deck en cualquier página o entrada utilizando el shortcode:

```shortcode
[osint_deck]
```

O con parámetros personalizados:

```shortcode
[osint_deck category="dominios" access="gratuito" limit="20"]
```

---

## 🤝 Contribuciones

¡Las contribuciones son bienvenidas! Si tienes una idea para una nueva herramienta o una mejora en el código:

1.  Haz un Fork del proyecto.
2.  Crea tu rama de funcionalidad (`git checkout -b feature/AmazingFeature`).
3.  Haz Commit de tus cambios (`git commit -m 'Add some AmazingFeature'`).
4.  Push a la rama (`git push origin feature/AmazingFeature`).
5.  Abre un Pull Request.

---

## 📄 Licencia

Distribuido bajo la licencia **MIT**. Ver `LICENSE` para más información.

---

<div align="center">
  <p>Desarrollado con ❤️ por el equipo de OSINT Deck</p>
  <p>
    Sebastián Cendra • Claudio Pandelo • Paolo Peña Ramírez • Guillermo Quintana • Damián Radiminsky
  </p>
</div>
