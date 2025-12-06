# 📘 **OSINT Deck — Biblia Interna del Proyecto (Diseño, Lógica y Funcionamiento del Sistema)**

> *El documento que guía el desarrollo presente y futuro. Ninguna implementación debe hacerse sin respetarlo.*

---

## 1. Propósito del Deck

OSINT Deck no es solo un catálogo de herramientas OSINT:
Su meta es transformar un input del usuario (dominio, IP, email, hash…) en **acciones concretas representadas como cards**, para acelerar investigaciones.

El sistema debe ser capaz de:

1. Detectar automáticamente qué tipo de dato ingresó el usuario
2. Filtrar herramientas/cards compatibles
3. Resolver cómo usar cada una (URL directa, input manual, API, etc.)
4. Evitar ambigüedades (ej. dominio + IP simultáneos)
5. Asistir al analista mostrando la opción más correcta para ese dato

La lógica debe ser determinística, sin suposiciones mágicas del programador.

---

## 2. Componentes principales del ecosistema

| Capa             | Entidad                          | Descripción                              |
| ---------------- | -------------------------------- | ---------------------------------------- |
| **Herramienta**  | Mazo (ej: MxToolBox)             | Contenedor general de funciones          |
| **Card**         | Acción operativa (ej: MX Lookup) | Elemento que ejecuta una consulta real   |
| **Input OSINT**  | Detección automática             | El valor que dispara la investigación    |
| **Categoría**    | Taxonomía                        | Orden visual y conceptual                |
| **Motor Lógico** | Resolución de decisiones         | Decide qué mostrar, cómo actuar y cuándo |

**Regla base:**

> *La herramienta agrupa, la card actúa.*

---

## 3. Tipos de input reconocidos por el sistema

### Valores esperados como indicadores OSINT

* dominio
* ip
* url
* email
* hash
* headers
* phone
* username
* wallet
* file (futuro con binarios/metadata)
* **none** (solo catálogo, no investigación)

**Todo input válido debe desencadenar cards compatibles.**

---

## 4. Contexto de uso del Deck

> El usuario escribe algo en el buscador.
> El sistema interpreta si está consultando *herramientas* o *quiere investigar un dato real*.

### Comportamientos posibles

| Caso                 | Ejemplo usuario                      | Motor decide que hacer                                                     |
| -------------------- | ------------------------------------ | -------------------------------------------------------------------------- |
| Solo texto genérico  | `herramientas DNS`                   | Mostrar mazos/cards sin input (`none`)                                     |
| Un input OSINT       | `osint.com.ar`                       | Mostrar solo cards compatibles con `domain`                                |
| Múltiples inputs     | `osint.com.ar 8.8.8.8`               | Mostrar cards compatibles con ambos → si en conflicto, solicitar selección |
| Mezcla texto + input | `chequear blacklist de osint.com.ar` | Extraer input → modo investigación                                         |
| No hay input válido  | `como investigo un dominio?`         | Catálogo educativo + guía                                                  |

---

## 5. Cómo se muestran las cards según el input

### Sin input detectado → MODO CATÁLOGO

El usuario está explorando herramientas.

Se mostrarán:

* Cards cuyo input es `none`
* Cards buscadas por tag, categoría o coincidencia textual
* Información de contexto OSINT, documentación, tutoriales

### Con input detectado → MODO INVESTIGACIÓN

Se mostrarán únicamente cards donde:

* el input requerido incluye el tipo detectado
* o que puedan procesarlo indirectamente

**Ejemplo:**
Si se detecta IP → WHOIS DNS no se muestra (solo domain)
Si se detecta dominio → MX Lookup sí, Email headers no (si requiere headers)

---

## 6. Ambigüedad entre múltiples inputs o múltiples modos

### Si una card acepta varios tipos posibles

Caso típico:

* Blacklist acepta dominio o IP

Si el usuario ingresa **solo uno**, ejecución directa.
Si ingresa **varios**, el sistema no decide por él.

Se abre selector:

> ¿Con cuál desea analizar?
> ▢ Dominio → osint.com.ar
> ▢ IP → 192.168.0.10

Esto evita errores y mantiene control investigativo.

---

### Regla definitiva de ambigüedad

| Situación                             | Resultado                     |
| ------------------------------------- | ----------------------------- |
| Una sola coincidencia posible         | Ejecutar directo              |
| Varias coincidencias en la misma card | Pedir selección               |
| Varias cards posibles para un input   | Mostrar todas                 |
| Ninguna card soporta ese input        | Sugerir herramientas externas |

---

## 7. Rol de las Categorías vs Tags

**Categoría = organización estructural (para UI y navegación)**
**Tags = palabras clave para búsqueda y relación semántica**

| Categoría responde a               | Ejemplo               |
| ---------------------------------- | --------------------- |
| ¿Dónde pertenece?                  | Seguridad / Blacklist |
| ¿Qué tipo de card es?              | Infraestructura / DNS |
| ¿Qué funcionalidad vertical tiene? | Correo / SPF          |

| Tag responde a                     | Ejemplo                 |
| ---------------------------------- | ----------------------- |
| ¿Qué conceptos toca?               | dns, correo, reputación |
| ¿Cómo se filtra rápidamente?       | malware, hash, header   |
| Es un vocabulario OSINT controlado | para evitar duplicados  |

**Nunca usar tags para sustituir categorías.**
La categoría clasifica, el tag conecta.

---

## 8. Modo de uso del atributo `mode` (clave principal del diseño)

`mode` no significa qué input acepta sino **cómo se ejecuta**.

### Modos previstos del sistema

| mode   | Uso                                             | Ejemplo                 |
| ------ | ----------------------------------------------- | ----------------------- |
| manual | El usuario escribe manualmente dentro de la web | MxToolbox dashboard     |
| url    | Se construye link con el input                  | blacklist:{input}       |
| api    | Consulta via API propia/externa                 | VirusTotal JSON         |
| auto   | El sistema reconoce mejor ruta en runtime       | futuro modo inteligente |

**Separación importante:**
`types` define *qué acepta*.
`mode` define *cómo lo usa*.

---

## 9. Comportamientos avanzados del motor (todos los casos cubiertos)

1. **Usuario ingresa dominio**

   * Mostrar cards que acepten dominio
   * Ocultar las que requieren IP/email/hash
   * No mostrar `none` salvo que el usuario cambie manualmente a *explorar*

2. **Usuario ingresa IP**

   * Mostrar IP Info, PTR, Blacklist, GeoIP, ASN

3. **Usuario ingresa email**

   * Mostrar Headers, Breach lookup, Email reputation

4. **Usuario ingresa hash**

   * Mostrar Hash cracking, Malware DB lookup

5. **Usuario ingresa URL**

   * Mostrar WebTech fingerprint, TLS, WebScanner

6. **Usuario ingresa múltiples inputs distintos**

   * Filtrar tarjetas que soporten ambos (raro)
   * Si no → ofrecer selección del input primario

7. **Usuario usa buscador semántico (texto sin OSINT)**

   * Buscar por tags/categorías
   * Mostrar dashboards + cards informativas

8. **Usuario busca una card por nombre**

   * Coincidencia directa textual
   * UI muestra card aunque no tenga input válido

9. **El mazo no tiene cards con input**

   * Mostrar solo la principal
   * Adjuntar documentación y tutorial

📌 Nada queda librado a intuición. Todo comportamiento ya está predicho.

---

## 10. Reglas de estandarización para mantener escalabilidad

### Cuando se agrega una nueva herramienta:

📍 Debe incluir:

* Card principal sin input (dashboard/landing)
* Cards separadas por función (NO mezclar todo en una sola)
* Categoría correcta basada en taxonomía global
* Tags limitados a conceptos reales
* Tipos de input precisos sin supuestos

📍 Debe evitar:

* Cards con más de 1 propósito
* Tags inventados que dupliquen semántica
* Mezclar manual y url sin estrategia declarada
* Tools ambiguas sin pattern/decisión definida

---

## 11. Taxonomía oficial de categorías (resumen)

**Estructura conceptual**

```
AREA → MÓDULO → ACCIÓN
Formato code: AREA__MODULO__ACCION (opcional)
```

Ejemplos:

* CORREO / MX
* CORREO / SPF
* SEGURIDAD / BLACKLIST
* INFRA / DNS
* WEB / FINGERPRINT
* SOCMINT / USER LOOKUP
* MALWARE / SANDBOX
* CRYPTO / HASH
* BLOCKCHAIN / WALLET

Las categorías nunca se inventan en una card. Deben existir en el catálogo global.

---

## 12. Resumen operativo del flujo lógico del sistema

```
Entrada del usuario →
Detectar indicadores →
Determinar modo catálogo o investigación →
Filtrar cards compatibles →
Resolver ambigüedades si existen →
Construir patrón URL/API o solicitar input manual →
Ejecutar acción o mostrar vista correspondiente
```

Esto resuelve **todas las situaciones posibles** del usuario.

---

## 13. Filosofía base del proyecto

📌 *La herramienta no piensa por el usuario. Lo asiste.*
📌 *El sistema no adivina; decide basado en reglas.*
📌 *Cada card es una acción singular y específica.*
📌 *El JSON es la fuente de verdad del ecosistema.*
📌 *Escalabilidad > improvisación.*

---
