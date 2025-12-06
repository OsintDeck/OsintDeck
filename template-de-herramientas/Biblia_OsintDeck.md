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



Esta seccion define estándares **para evitar ambigüedad futura**, facilitar catalogación masiva y permitir integración automática.

---

# 📘 **OSINT Deck — Biblia de Inputs y Uso Operativo**

---

## 1. Rol del input en el sistema

El *input* es la señal que activa **modo investigación**, habilita análisis y filtra herramientas específicas.

```
Sin input → Catálogo/Exploración
Con input → Investigación/Acción
```

El input determina:

| Área impactada | Función                                            |
| -------------- | -------------------------------------------------- |
| Parser         | Detecta el tipo de dato ingresado                  |
| Motor          | Decide qué cards habilitar                         |
| UI             | Decide si mostrar búsqueda simple o flujos guiados |
| Integración    | Construcción de URL/API/pattern                    |
| Resultados     | Cómo se visualiza el output                        |

---

## 2. **Input Types disponibles**

Listado oficial del sistema (estándar interno, extensible):

```
domain      → example.com
ip          → 8.8.8.8
url         → https://site.com
email       → user@mail.com
hash        → sha256:..., md5:...
asn         → AS15169
host        → hostname.ej
headers     → Raw email header
phone       → +54 911...
username    → handle en redes
wallet      → dirección cripto
tx_hash     → hash de transacción
image       → jpeg/png con EXIF
file        → binario subido
none        → dashboard/landing
```

Cada herramienta soportará **uno o varios**, pero cada card decidirá **exactamente cuáles acepta.**

---

## 3. **Reglas de visualización según input**

### 📌 En el **MAZO/Herramienta principal**

(Modo exploración)

| Input                        | Se muestra en Mazo | Cuándo                                  |
| ---------------------------- | ------------------ | --------------------------------------- |
| none                         | ✔ SIEMPRE          | Landing, overview, docs                 |
| domain/ip/url/email/hash/etc | ❌ NO               | No mostrar inputs analíticos en el mazo |

> El MAZO **no realiza análisis directo**, solo presenta la herramienta.

**Destino del Mazo:**
Dashboard → info general, documentación, enlaces, logo, features.

---

### 📌 En **CARDS**

(Modo acción)

| Input                       | Se muestra en Card                       | Uso              |
| --------------------------- | ---------------------------------------- | ---------------- |
| domain,ip,url,email,hash... | ✔ SI                                     | Cards operativas |
| none                        | ✔ Sí, pero solo cards dashboard/manuales | No analíticas    |
| file,image,username,phone   | ✔ Si aplica la herramienta               | Soporte opcional |

📍 **Todas las operaciones se realizan en cards.**

---

## 4. Política de detección de inputs en búsqueda

### 1 input detectado → ejecutar directo

### 2+ inputs detectados → aplicar `resolve_strategy`

Ejemplo:

```
usuario escribe: osint.com.ar 8.8.8.8

→ detecta domain + ip
→ si card soporta ambos → modal/auto
→ si solo soporta 1 → filtra automático
```

---

## 5. Cómo debe configurarse input dentro del JSON

### A nivel herramienta (Mazo)

```
types=["none"]     → No acción automática
mode="manual"      → siempre
```

### A nivel cards

```
Debes definir:
✔ qué input acepta
✔ cómo se ejecuta (manual/url/api)
✔ si existe ambigüedad o auto-resolución
```

---

## 6. Tipos de ejecución y su contexto

| mode   | Se muestra input UI | Se usa URL con pattern | Se usa API | Caso típico                                      |
| ------ | ------------------- | ---------------------- | ---------- | ------------------------------------------------ |
| manual | Sí                  | No                     | No         | Page tools donde el usuario escribe internamente |
| url    | Sí                  | ✔                      | No         | Lookups web automáticos                          |
| api    | Sí                  | No                     | ✔          | VirusTotal, Shodan, Censys…                      |
| none   | No                  | No                     | No         | Dashboards                                       |

---

## 7. Reglas generales para evitar confusión futura

### 🔥 INPUT SOLO ES VISIBLE EN CARDS O ACCIONES

**Nunca en mazo** (solo descripción)

### 🔥 Si `types=["none"]` la card es informativa

### 🔥 Cards que aceptan múltiples tipos deben definir estrategia

```
resolve_strategy: ask | auto | prefer-domain | prefer-ip
```

### 🔥 Un input solo dispara cards relacionadas

No se deben mostrar herramientas sin soporte para ese input.

---

## 8. Casos complejos contemplados

| Caso                                         | Resultado                             |
| -------------------------------------------- | ------------------------------------- |
| Usuario ingresa dominio e IP                 | Modal si soporta ambos                |
| Card soporta dominio pero usuario puso email | No se muestra esa card                |
| Mazo se abre desde búsqueda con input        | NO analiza → muestra panel general    |
| Herramienta tiene URL diferente por input    | Se usan patterns específicos por tipo |

Esto permite hacer:

```
"pattern_domain": "...{domain}"
"pattern_ip": "...{ip}"
```

Si querés, lo extendemos.

---

## 9. Reglas de diseño visual para UI

### MAZO:

```
Logo + descripción
Botón "Abrir"
Documentación
Tags globales / fase OSINT
```

### CARD:

```
Título de acción
Descripción clara
Campo input (si aplica)
Botón Ejecutar
Estado, resultados, logs futuros
```

---

# 🧾 Resumen mental del sistema

```
MAZO = catálogo
CARD = acción
INPUT = llave del motor
strategy = evita ambigüedad
mode = define ejecución
```

---

Si querés, puedo crear **el documento en PDF/Markdown completo** para el repositorio interno.

## Próximo paso sugerido

📌 Crear **tabla de soporte por input para cada categoría**
📌 Definir `pattern_by_type` en JSON para casos mixtos
📌 Armar UI del modal selector

---


# 📘 OSINT Deck – Biblia del JSON (con interpretación y lógica automática)

---

## 1. `metadata` — Versión, verificación y procedencia

Ejemplo base:

```jsonc
"metadata": {
  "version": "v1.0",
  "last_update": "2025-01-20",
  "verified": false,
  "source": "Carga manual"
}
```

### 1.1. `version`

* **Quién lo escribe:**

  * Inicialmente: la persona que carga el mazo.
  * Después: el **admin** o un proceso de sincronización con la fuente (si hay).

* **Cómo lo interpreta el sistema:**

  * Texto opaco: no se parsea (no se asume semver, solo se muestra).
  * Puede usarse para:

    * Mostrar en la ficha avanzada del mazo.
    * Comparar con `admin.revision` para saber si la definición JSON está atrasada respecto a la versión “real”.

* **Cuándo se modifica automáticamente:**

  * **Nunca automáticamente** (por diseño).
  * Si querés automatizarlo, se requeriría un sincronizador que lea de la fuente (ej: GitHub tag), no del runtime de OSINT Deck.

➡️ **Regla de Biblia:**
Si `version` está vacío, el sistema no muestra versión.
Si está presente, se muestra solo a modo informativo, NO afecta lógica.

---

### 1.2. `last_update`

* **Quién lo escribe:**

  * Cuando se edita el mazo en el panel admin → el backend debe setear `last_update` con la fecha y hora actual.

* **Cómo lo interpreta el sistema:**

  * Para:

    * Ordenar herramientas por “recientes”.
    * Mostrar “Actualizado hace X días”.
    * Desactivar el badge `new` pasado cierto tiempo.

* **Actualización automática:**

  * **Sí, debería actualizarse siempre que se modifica el JSON** de ese mazo:

    * Cambio en cards.
    * Cambio en info.
    * Cambio en tags, etc.
  * No depende de la salud técnica de la herramienta, solo de la metadata interna.

➡️ **Regla de Biblia:**
`last_update` es **propiedad del catálogo**, no del sitio externo.
Cada vez que se cambia algo del JSON → el backend pisa `last_update`.

---

### 1.3. `verified`

* **Quién lo escribe:**

  * Inicialmente: `false`.
  * Después:

    * Automáticamente por un “health checker” (ping a URL / comprobación básica).
    * O manualmente por un moderador (“esta herramienta funciona bien y fue revisada”).

* **Cómo lo interpreta el sistema:**

  * Filtrado:

    * Mostrar solo herramientas `verified=true` si el usuario activa un filtro tipo “Solo verificadas”.
  * UI:

    * Badge tipo “Verificada” o “Calidad confirmada”.

* **Actualización automática:**

  * Health-check programado:

    * Si el checker detecta que la URL 200 + responde razonable → puede marcar `verified=true`.
    * Si falla N veces seguidas → puede marcar `verified=false` y aumentar `stats.reports` auto.

➡️ **Regla de Biblia:**
`verified` es **un semáforo de confianza**, nunca debe ser seteado “a mano” por el código de UI; es cosa del checker o del moderador.

---

### 1.4. `source`

* **Quién lo escribe:**

  * Únicamente la capa que **crea** el mazo:

    * “Carga manual”
    * “Import GitHub”
    * “Script de migración v1”
    * “OSINT Deck Core”

* **Cómo lo interpreta el sistema:**

  * No afecta lógica.
  * Se puede usar en el panel admin para entender de dónde salió ese mazo.

* **Actualización automática:**

  * **Nunca.**
  * Una vez seteado, queda como historial de origen (como “autoría”).

➡️ **Regla de Biblia:**
`source` es puramente forense / documental. No se usa para filtros ni ejecución.

---

## 2. `status` — Estado técnico de la herramienta

```jsonc
"status": {
  "state": "up",
  "http_code": 200,
  "last_check": "2025-01-20 10:35"
}
```

### 2.1. `state` (`up | down | maintenance`)

* **Quién lo escribe:**

  * Un proceso de **monitor** que intenta acceder a `cards[0].url` o a una URL específica de status.

* **Interpretación:**

  * `up` → se puede usar normalmente.
  * `down` → mostrar aviso “Esta herramienta parece caída” o bajarla en el ranking.
  * `maintenance` → configuración manual para indicar mantenimiento conocido.

* **Uso en UI:**

  * Badge con color.
  * Tooltip “Última vez verificada hace X minutos”.

---

### 2.2. `http_code` y `last_check`

* Se actualizan **solo** cuando el health-check corre:

  * `http_code`: último código HTTP.
  * `last_check`: timestamp de ese test.

* **Interpretación:**

  * Si `http_code >= 500` o timeout → se puede marcar `state = "down"`.
  * Si `http_code` es 200/302 → `up`.

➡️ **Regla:**
`status` no condiciona qué cards se muestran, pero sí puede afectar si las mostramos con alerta o bajar prioridad.

---

## 3. `stats` — Uso y feedback

```jsonc
"stats": {
  "clicks": 0,
  "likes": 0,
  "reports": 0
}
```

### 3.1. `clicks`

* Incrementa **automáticamente** cada vez que:

  * El usuario abre una card.
  * O abre el portal principal desde OSINT Deck.

* Se usa para:

  * Ordenar por popularidad.
  * Activar `badges.popular` cuando supere cierto umbral.

---

### 3.2. `likes`

* Cambia cuando el usuario marca “me gusta” o “favorito”:

  * Puede ser por usuario logueado o de forma agregada.

* Use cases:

  * Ranking “Mejor valoradas”.
  * Badge `recommended` (si likes altos + pocos reports + verified).

---

### 3.3. `reports`

* Se incrementa cuando:

  * Un usuario reporta: “No funciona / obsoleta / mal link”.

* Efectos:

  * UI puede mostrar un warning.
  * Moderador puede revisar mazo.
  * Podés decidir ocultarla por encima de cierto umbral.

---

## 4. `admin` — Ciclo de vida interno

```jsonc
"admin": {
  "created_at": "",
  "updated_at": "",
  "revision": 0
}
```

* **`created_at`**: set una vez, al crear el mazo.
* **`updated_at`**: se pisa SIEMPRE que se guarda cambios desde el panel admin.
* **`revision`**: entero que se incrementa +1 cada vez que se modifica algo del JSON.

**Interpretación:**

* Se puede usar para:

  * Saber si un mazo está “viejo” (mucha distancia entre created y updated).
  * Mostrar historial de cambios.
  * Forzar el reseteo de cachés: si `revision` cambia, se recarga el mazo completo.

---

## 5. `badges` — Derivados de estado y stats

```jsonc
"badges": {
  "popular": false,
  "new": false,
  "reported": false,
  "recommended": false
}
```

Estos **NO deberían ser llenados a mano**.
La Biblia propone reglas:

* `popular = true` si:

  * `clicks >= X` y `likes >= Y` y `reports == 0`

* `new = true` si:

  * `today - admin.created_at <= 30 días`

* `reported = true` si:

  * `reports > 0`

* `recommended = true` si:

  * `verified == true`
  * `likes ≥ umbral`
  * `reports == 0`

➡️ El motor puede recalcular estos badges en un cron y pisarlos automáticamente.

---

## 6. `tags_global` — Cómo se interpretan

```jsonc
"tags_global": ["dns","correo","infraestructura","dominio"]
```

* Son **palabras clave macro** a nivel Tool:

  * Para filtrar herramientas en el catálogo.
  * Para mostrar chips tipo “DNS / Infraestructura”.

* **No afectan lógica de ejecución** (no deciden qué card usar).

* No se modifican automáticamente (salvo una futura normalización).

---

## 7. `osint_context` — Interpretación operativa

```jsonc
"osint_context": {
  "uso_principal": "Recolección de infraestructura y análisis de correo",
  "fase_osint": ["Footprinting","Discovery"],
  "nivel_tecnico": "Básico / Intermedio"
}
```

* **Uso en UI:**

  * Mostrar en ficha avanzada del mazo.
  * Permitir filtros del tipo:

    * “Mostrar herramientas para Footprinting”.
    * “Mostrar solo básico”.

* **No se modifica automáticamente**.

* Es parte de la documentación conceptual.

---

## 8. `cards` e `input` — Cómo se modifican en runtime

Esta es la parte donde entra lo que preguntabas:

> “¿Cómo vamos a interpretar/modificar automáticamente los input?”

Ejemplo:

```jsonc
"input": {
  "types": ["domain","ip"],
  "example": "example.com",
  "mode": "url",
  "pattern": "https://mxtoolbox.com/SuperTool.aspx?action=blacklist:{input}",
  "resolve_strategy": "ask"
}
```

### 8.1. `types`

* Lista de tipos de indicadores que esa card acepta.
* El motor:

  * Detecta los tipos presentes en el texto del usuario (domain, ip, etc.).
  * Cruza con `types` para saber si la card es relevante.

**No se modifica en runtime.**
Es el contrato de la card.

---

### 8.2. `mode`

* Decide cómo el sistema **ejecuta** la acción:

| mode     | Acción del sistema                                                    |
| -------- | --------------------------------------------------------------------- |
| `manual` | Solo abre la URL base, el usuario escribe todo.                       |
| `url`    | Reemplaza `{input}` en `pattern` y abre esa URL.                      |
| `api`    | Llama a un endpoint configurado y muestra el resultado en OSINT Deck. |

Tampoco se modifica solo, pero:

* Si en el futuro detectás que una tool pasa de web manual a API, se puede cambiar a `api` en una revisión del mazo.

---

### 8.3. `pattern` + `{input}`

* El motor:

  1. Recibe el input elegido (ej. `osint.com.ar`).
  2. Lo normaliza por tipo (sin espacios, sin protocolo si es dominio).
  3. Reemplaza `{input}` dentro de `pattern`.
  4. Abre la URL resultante.

Ejemplo:

```
pattern = "https://mxtoolbox.com/SuperTool.aspx?action=blacklist:{input}"
input detectado = "8.8.8.8"
→ URL final = https://mxtoolbox.com/SuperTool.aspx?action=blacklist:8.8.8.8
```

**No se modifica solo**; pero el motor **usa** el valor dinámicamente para construir la URL.

---

### 8.4. `resolve_strategy`

Es donde el motor realmente toma decisiones automáticas:

| Estrategia      | Comportamiento                                                               |
| --------------- | ---------------------------------------------------------------------------- |
| `ask`           | Abre modal y deja elegir al usuario qué input usar (dominio/IP/etc.).        |
| `auto`          | El motor decide según reglas globales (DNS → domain, Reputación → ip, etc.). |
| `prefer-domain` | Si hay dominio, se usa ese aunque también exista IP.                         |
| `prefer-ip`     | Si hay IP, se usa ese aunque también exista dominio.                         |

Acá sí hay lógica:

* El valor de `resolve_strategy` es fijo en el JSON, pero
* Las decisiones que toma el motor varían según:

  * Cuántos inputs detectó el parser.
  * De qué tipo.
  * Qué `types` soporta la card.

No se modifica el JSON, pero **la ejecución cambia** dependiendo de los inputs detectados en cada consulta.

---

## 9. ¿Qué sí se va a cambiar automáticamente?

Para cerrar, resumen de los campos que el sistema va a pisar / recalcular:

### Se modifican automáticamente:

* `metadata.last_update` → al editar mazo.
* `status.state`, `status.http_code`, `status.last_check` → health-check.
* `stats.clicks`, `stats.likes`, `stats.reports` → interacción usuario.
* `admin.updated_at`, `admin.revision` → cambios desde admin.
* `badges.*` → proceso que corre reglas en base a stats + admin + metadata.verified.

### Nunca se modifican automáticamente (solo humanos/scripts de carga):

* `metadata.version`
* `metadata.source`
* `info.*`
* `tags_global`
* `osint_context`
* `cards[*].input.*` (contratos de comportamiento)

---

# 📑 Anexo – Reglas del Motor de OSINT Deck

**(Detección de input, selección de cards, resolución de ambigüedad y construcción de acciones)**

---

## 1. Flujo general del motor

Mentalmente, el motor hace siempre esto:

1. Recibe **texto del usuario** (desde buscador o desde un campo input).
2. **Detecta indicadores OSINT** (domain, ip, email, hash, etc.).
3. Decide si está en:

   * MODO CATÁLOGO (sin input válido)
   * MODO INVESTIGACIÓN (con uno o más inputs válidos)
4. Según el modo:

   * Filtra **Tools** y/o **Cards**.
   * Aplica reglas de `input.types`, `mode`, `pattern`, `resolve_strategy`.
5. Ejecuta:

   * Abrir dashboard
   * Abrir URL templada
   * O llamar a API (futuro)

---

## 2. Paso 1 – Detección de inputs

La entrada del usuario es un string crudo.
El motor debe intentar detectar todos los indicadores posibles.

### 2.1. Conjunto base de tipos soportados

* `domain`
* `ip`
* `url`
* `email`
* `hash`
* `asn`
* `host`
* `headers`
* `phone`
* `username`
* `wallet`
* `tx_hash`
* `file` / `image` (cuando haya upload)
* (y `none` → reservado para cards que no usan input)

### 2.2. Resultado esperado

El parser debe devolver algo así (conceptualmente):

```text
inputs_detectados = [
  { tipo: "domain", valor: "osintdeck.com" },
  { tipo: "ip", valor: "8.8.8.8" }
]
```

* Si no detecta nada → `inputs_detectados` está vacío.
* Si detecta múltiples (dominio + ip + hash…) → se almacenan todos.

---

## 3. Paso 2 – Elegir modo: Catálogo vs Investigación

### 3.1. Regla

```pseudo
si inputs_detectados está vacío:
    modo = "CATALOGO"
sino:
    modo = "INVESTIGACION"
```

---

## 4. Paso 3 – Selección de herramientas y cards

### 4.1. Si MODO = CATÁLOGO

Objetivo: **explorar herramientas, no analizarlas aún.**

* Mostrar:

  * Lista de **Tools** filtrados por:

    * `name`
    * `tags_global`
    * `osint_context.uso_principal`
    * `osint_context.fase_osint`
  * **Cards** que tengan:

    ```json
    "input": { "types": ["none"] }
    ```

* NO mostrar cards con `types` que requieran inputs (domain/ip/etc).

#### Pseudoregla:

```pseudo
para cada tool en catalogo:
    si coincide con búsqueda semántica:
        incluir en resultados

    para cada card del tool:
        si card.input.types contiene "none":
            mostrar como acción de acceso (dashboard, docs, portal)
```

---

### 4.2. Si MODO = INVESTIGACIÓN

Objetivo: **usar el input como combustible.**

* Para cada `card` de cada `tool`:

  1. Ver si **algún** tipo en `inputs_detectados` coincide con `card.input.types`.
  2. Si **no coincide ninguno** → la card se descarta.
  3. Si **coincide al menos uno** → la card es candidata.

#### Pseudoregla:

```pseudo
cards_candidatas = []

para cada tool:
    para cada card en tool.cards:
        si "none" en card.input.types:
            continuar (esta card no se usa en modo investigación)

        tipos_soportados = card.input.types
        tipos_detectados = [ tipos en inputs_detectados ]

        si intersección(tipos_soportados, tipos_detectados) no está vacía:
            agregar card a cards_candidatas
```

---

## 5. Paso 4 – Para cada card candidata: resolución de input

Cada card candidata puede tener 3 situaciones:

1. Coincide con **1 solo tipo** de input.
2. Coincide con **más de un tipo** (ej: domain + ip).
3. Está mal configurada (no coincide con nada, debería haber sido filtrada antes).

Nos importa la **situación 2**.

---

### 5.1. Caso 1 – Solo 1 input compatible

```pseudo
si card coincide solo con un tipo de input:
    input_elegido = ese input
    (no hay ambigüedad)
```

No se abre modal, ni se pregunta al usuario.

---

### 5.2. Caso 2 – Varios inputs compatibles

Ejemplo:
`Blacklist Check` soporta `domain` y `ip`.

Usuario escribe:

```text
"ver blacklist de osint.com.ar 8.8.8.8"
```

Parser devuelve:

```text
inputs_detectados = [domain(osint.com.ar), ip(8.8.8.8)]
```

La card soporta ambos:

```json
"input.types": ["domain","ip"]
```

Entonces entramos en la lógica de `resolve_strategy`.

---

## 6. `resolve_strategy` – Cómo decidir qué input usar

Los valores posibles:

* `"ask"`
* `"auto"`
* `"prefer-domain"`
* `"prefer-ip"`

---

### 6.1. `ask` — Modal obligatorio

```pseudo
si card.input.resolve_strategy == "ask" y hay >1 input compatible:
    abrir modal:
        listar opciones:
            - Analizar dominio: osint.com.ar
            - Analizar IP: 8.8.8.8
    esperar selección del usuario
    input_elegido = opción elegida
```

El **modal solo aparece si hay ambigüedad real** (más de un input del tipo soportado).

---

### 6.2. `prefer-domain`

```pseudo
si "domain" en inputs_detectados compatibles:
    input_elegido = ese domain
sino si "ip" en inputs_detectados compatibles:
    input_elegido = esa ip
```

No hay modal, es automático.

---

### 6.3. `prefer-ip`

Igual que el anterior, pero al revés.

---

### 6.4. `auto` – Inteligencia futura

`auto` deja que el sistema use reglas globales dependiendo de:

* Código de categoría (`SEC_BLACKLIST`, `INF_DNS`, etc.).
* Tipo de card.

Ejemplo de reglas globales:

```pseudo
si card.category.code empieza con "INF_":
    preferir domain sobre ip

si card.category.code == "SEC_BLACKLIST":
    preferir ip sobre domain

si card.category.code == "MAIL_MX":
    solo domain (ya lo filtra input.types)
```

`auto` es un “modo inteligente” configurable en otro JSON de reglas (por ejemplo `rules.json`).

---

## 7. Paso 5 – Construcción de la acción según `mode`

Una vez elegido `input_elegido`, se ejecuta la card de acuerdo al `mode`.

### 7.1. `mode = "manual"`

* Comportamiento:

  * El sistema **solo abre** `card.url`.
  * No intenta aplicar `pattern`.
  * El usuario debe escribir todo a mano en la web de la herramienta.

```pseudo
si card.input.mode == "manual":
    abrir card.url en nueva pestaña/iframe
```

---

### 7.2. `mode = "url"`

* Aquí sí se usa `pattern`.

Regla:

1. Tomar `input_elegido.valor`.
2. Normalizar (según tipo: quitar `http://` si es dominio, etc., si así se define en las reglas).
3. Reemplazar `{input}` dentro de `pattern`.
4. Abrir la URL resultante.

```pseudo
si card.input.mode == "url":
    valor = normalizar(input_elegido)
    url_final = reemplazar("{input}", valor, card.input.pattern)
    abrir url_final
```

Ejemplo:

```json
"pattern": "https://mxtoolbox.com/SuperTool.aspx?action=blacklist:{input}"
input_elegido = ip "8.8.8.8"
→ "https://mxtoolbox.com/SuperTool.aspx?action=blacklist:8.8.8.8"
```

---

### 7.3. `mode = "api"` (futuro)

* El motor en lugar de abrir una URL, hace:

```pseudo
llamar API correspondiente con el input_elegido.valor
recibir JSON
renderizar resultado en el panel de resultados de OSINT Deck
```

* `pattern` podría ser usado como endpoint base:

  * `https://api.ejemplo.com/v1/lookup?value={input}`

---

### 7.4. `mode = "none"`

* Caso especial para cards meramente informativas.

```pseudo
si card.input.types = ["none"]:
    card no participa en modo investigación
    solo se muestra en modo catálogo o detalle del mazo
```

---

## 8. Lógica del modal (resumen)

El modal solo aparece cuando:

1. Estamos en **Modo Investigación**.
2. Una card candidata:

   * Soporta más de un tipo de input (`input.types` > 1).
   * Y hay más de un input detectado compatible.
   * Y `resolve_strategy == "ask"`.

Contenido del modal:

* Listado de opciones posibles:

```text
Analizar dominio: osint.com.ar
Analizar IP: 8.8.8.8
Analizar email: test@dominio.com (si aplica)
...
```

* Una vez elegido:

  * Se ejecuta paso 5 (según mode: manual/url/api).

---

## 9. Ejemplo completo MXToolbox – Blacklist

Usuario escribe:

> “Quiero revisar blacklist de osint.com.ar 8.8.8.8”

1. Parser:

   * `domain` → osint.com.ar
   * `ip` → 8.8.8.8

2. Modo:

   * inputs_detectados no vacío → MODO INVESTIGACIÓN.

3. Card `Blacklist Check`:

```json
"input": {
  "types": ["domain","ip"],
  "mode": "url",
  "pattern": "https://mxtoolbox.com/SuperTool.aspx?action=blacklist:{input}",
  "resolve_strategy": "ask"
}
```

4. Coincidencias:

   * types = domain, ip
   * detectados = domain, ip → card candidata, con ambigüedad.

5. `resolve_strategy = "ask"` → aparece modal:

```text
Seleccioná el valor a analizar:
[ Analizar dominio osint.com.ar ]
[ Analizar IP 8.8.8.8 ]
```

6. Usuario elige IP.

7. Motor construye:

```text
url_final = "https://mxtoolbox.com/SuperTool.aspx?action=blacklist:8.8.8.8"
```

8. Abre esa URL.

---

## 10. Resumen de decisiones importantes

* **Tool/mazo nunca procesa input**, solo describe y enlaza.
* **Cards son las únicas que tienen lógica de input.**
* `input.types` decide **qué cards se activan** según lo que escribió el usuario.
* `resolve_strategy` decide **qué hacer con múltiples inputs válidos**.
* `mode` decide **cómo ejecutar** (manual / url / api).
* `pattern` se usa **solo** si `mode="url"` (o `api` si se extiende).
* Modal solo aparece cuando:

  * +1 input compatible
  * y `resolve_strategy="ask"`.

---

flowchart TD

    %% =========================
    %% 1. ENTRADA Y PARSING
    %% =========================
    A[Usuario escribe en el buscador] --> B[Parser de texto]
    B --> C{¿Hay indicadores<br/>OSINT válidos?}

    %% ---------- MODO CATÁLOGO ----------
    C -- No --> C1[Modo CATÁLOGO]
    C1 --> C2[Filtrar Tools por texto,<br/>tags_global, categoría]
    C2 --> C3[Mostrar solo Cards con<br/>input.types = ['none']]
    C3 --> Z[Fin]

    %% ---------- MODO INVESTIGACIÓN ----------
    C -- Sí --> D[Modo INVESTIGACIÓN]
    D --> D1[Extraer lista de inputs<br/>detectados (domain, ip, url, email...)]
    D1 --> D2[Recorrer Tools y sus Cards]

    D2 --> D3{card.input.types<br/>coincide con algún tipo<br/>detectado?}
    D3 -- No --> D2
    D3 -- Sí --> D4[Añadir card a<br/>cards_candidatas]

    D4 --> E{¿cards_candidatas<br/>está vacía?}
    E -- Sí --> E1[No hay herramientas<br/>compatibles con el input] --> Z
    E -- No --> F[Procesar cada card<br/>candidata]

    %% =========================
    %% 2. RESOLVER INPUT POR CARD
    %% =========================
    F --> G{¿Cuántos inputs<br/>compatibles tiene<br/>la card?}

    G -- 1 --> H[Elegir ese input<br/>directamente]
    G -- >1 --> I{resolve_strategy}

    %% resolve_strategy = ask
    I -- ask --> I1[Mostrar modal para que<br/>el usuario elija el input]
    I1 --> H

    %% resolve_strategy = prefer-domain
    I -- prefer-domain --> I2[Si hay domain usar domain,<br/>si no otro tipo compatible]
    I2 --> H

    %% resolve_strategy = prefer-ip
    I -- prefer-ip --> I3[Si hay ip usar ip,<br/>si no otro tipo compatible]
    I3 --> H

    %% resolve_strategy = auto
    I -- auto --> I4[Usar reglas globales<br/>(según category.code)]
    I4 --> H

    %% =========================
    %% 3. EJECUCIÓN SEGÚN mode
    %% =========================
    H --> J{card.input.mode}

    J -- manual --> J1[Abrir card.url<br/>el usuario escribe el dato<br/>en la web de la herramienta]
    J -- url --> J2[Construir URL final<br/>reemplazando {input}<br/>en pattern]
    J -- api --> J3[Llamar API con el input<br/>y mostrar resultado<br/>en OSINT Deck]
    J -- none --> J4[Card informativa,<br/>sin uso de input]

    J1 --> K[Actualizar stats<br/>(clicks, last_use...)]
    J2 --> K
    J3 --> K
    J4 --> K

    K --> Z[Fin]

    %% =========================
    %% ESTILOS BÁSICOS
    %% =========================
    style A fill:#ffd700,stroke:#333,stroke-width:2px
    style C1 fill:#b3d9ff,stroke:#333,stroke-width:1px
    style D fill:#baffc9,stroke:#333,stroke-width:1px
    style F fill:#e5e7eb,stroke:#333,stroke-width:1px
    style J2 fill:#fff8c2,stroke:#333,stroke-width:1px
    style J3 fill:#ffe4b5,stroke:#333,stroke-width:1px


