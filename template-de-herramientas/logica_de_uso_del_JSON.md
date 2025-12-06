Esto será **la Biblia interna del proyecto** para que nadie improvise al desarrollar y el sistema sea escalable y mantenible.

---

# 📘 **OSINT Deck — Diseño de Lógica + Especificación del JSON**

---

## 1. Objetivo

> Convertir un *catálogo OSINT* en un *motor capaz de interpretar el input del usuario*, detectar indicadores digitales y ofrecer las cards correctas para analizarlos.

---

## 2. Elementos fundamentales del sistema

| Nivel         | Objeto                          | Rol                              |
| ------------- | ------------------------------- | -------------------------------- |
| ❶ Herramienta | **Tool/Mazo** (ej: MxToolBox)   | Contenedor principal             |
| ❷ Card        | Acción puntual (ej: MX Lookup)  | Unidad operativa                 |
| ❸ Input       | domain, ip, email, url, hash... | Lo que el usuario aporta         |
| ❹ Categoría   | Agrupador semántico             | UI, filtros, orden lógico        |
| ❺ Motor       | Lógica interna                  | Decide qué mostrar y cómo actuar |

---

## 3. Tipos de INPUT soportados en el JSON

```md
Tipos estándares para "input.types":

🔹 domain       → example.com
🔹 ip           → 8.8.8.8
🔹 url          → https://example.com
🔹 email        → contacto@example.com
🔹 hash         → sha256, md5, etc.
🔹 headers      → raw email headers
🔹 phone        → +54 911 2222 3333
🔹 username     → @usuario o string simple
🔹 file         → binarios/pdf/img (futuro)
🔹 none         → cuando no requiere input (dashboard, docs)
```

⭕ `none` **no aparece en modo investigación**, solo catálogo.

---

## 4. Campos clave del JSON que afectan la lógica

```jsonc
{
  "input": {
    "types": ["domain","ip"],         // Qué acepta
    "example": "example.com",         // Hint para UI
    "mode": "manual|url|api",         // Cómo se ejecuta
    "pattern": "blacklist:{input}",   // Si mode=url
    "resolve_strategy": "ask|auto|prefer-ip|prefer-domain"
  },
  "category": {
    "group": "Correo",
    "type": "MX",
    "code": "MAIL_MX"
  },
  "tags": ["mx","dns","correo"]
}
```

---

## 5. Casos de uso del usuario y decisiones del sistema

### 5.1 Usuario escribe algo en el buscador

| Caso              | Ejemplo usuario                   | Detecta inputs | Modo                         |
| ----------------- | --------------------------------- | -------------- | ---------------------------- |
| Solo texto        | `dns lookup tools`                | ❌ ninguno      | **Catálogo**                 |
| Un valor OSINT    | `osint.com.ar`                    | ✔ domain       | **Investigación**            |
| Multiple valores  | `osint.com.ar 8.8.8.8`            | ✔ domain + ip  | **Investigación + selector** |
| Mixto texto+OSINT | `chequear blacklist osint.com.ar` | ✔ domain       | **Investigación**            |
| Nada útil         | `como usar internet`              | ❌ ninguno      | **Catálogo**                 |

---

## 6. Comportamiento del motor

### 6.1 Si NO hay input válido → MODO CATÁLOGO

```md
Mostrar solo cards:
✔ que tengan input.types = ["none"]
✔ que coincidan con los tags o texto buscado
```

Uso típico: explorar herramientas.

---

### 6.2 Si hay un input → MODO INVESTIGACIÓN

```md
Mostrar cards donde input.types contenga el tipo detectado.
```

Ej:

```
domain detectado → mostrar WHOIS, MX Lookup, DNS, DMARC…
no mostrar Portal principal (none)
```

---

### 6.3 Si hay varios inputs compatibles con una misma card

```jsonc
"resolve_strategy": "ask"
```

Resultado sistema:

> abrir modal:
>
> * Analizar dominio osint.com.ar
> * Analizar IP 192.168.0.10

Opciones válidas para automatizar:

| resolve_strategy | comportamiento                                             |
| ---------------- | ---------------------------------------------------------- |
| `ask`            | Mostrar selector modal (recomendado)                       |
| `prefer-ip`      | Si hay IP usar IP primero                                  |
| `prefer-domain`  | Si hay domain usar domain                                  |
| `auto`           | Orden interno del sistema (dominio > IP > email > hash...) |

---

### 6.4 Si una card acepta múltiples tipos pero el usuario da solo uno

→ Ejecutar directo sin preguntar.

---

### 6.5 Si una herramienta tiene solo card principal sin input

Debe mostrar:

* botón **"Abrir herramienta"**
* link a docs/tutorial
* tags/fase OSINT

Sirve para herramientas tipo:

🟩 directorios
🟩 repositorios
🟩 herramientas web manuales

---

## 7. Flujo total del sistema (diagrama mental)

```
Usuario escribe texto
      ↓
Parser detecta indicadores OSINT
      ↓
¿Hay alguno?
 ┌───────────┐       ┌─────────────┐
 │    NO     │       │     SÍ      │
 └───────────┘       └─────────────┘
      ↓                    ↓
 MODO CATÁLOGO         MODO INVESTIGACIÓN
      ↓                    ↓
cards con none         cards que soporten input.type
      ↓                    ↓
mostrar lista          si 1 input → ejecutar directo
                        si 2+ → usar resolve_strategy
```

---

## 8. JSON final ejemplo documentado (base para desarrolladores)

```jsonc
{
  "name": "MxToolBox",
  "cards": [

    /* Card sin input → aparece solo en Modo Catálogo */
    {
      "title": "Portal principal",
      "desc": "Dashboard general. Entrada a todas las herramientas.",
      "category": { "group":"General", "type":"Dashboard", "code":"GEN_DASH" },
      "tags": ["dns","correo","infraestructura"],
      "input": {
        "types": ["none"],
        "mode": "manual"
      }
    },

    /* Card con input simple → ejecución directa */
    {
      "title": "MX Lookup",
      "desc": "Obtiene registros MX de un dominio.",
      "category": {"code":"MAIL_MX"},
      "tags": ["mx","correo","dns"],
      "input":{
        "types":["domain"],
        "example":"example.com",
        "mode":"url",
        "pattern":"mx:{input}"
      }
    },

    /* Card con input múltiple → ambigüedad controlada */
    {
      "title": "Blacklist Check",
      "desc": "Revisa si IP o dominio está listado.",
      "category": {"code":"SEC_BLACKLIST"},
      "tags":["blacklist","seguridad"],
      "input":{
        "types":["domain","ip"],
        "example":"8.8.8.8",
        "mode":"url",
        "pattern":"blacklist:{input}",
        "resolve_strategy":"ask"
      }
    }
  ]
}
```

---

## 9. Reglas importantes para evitar confusión futura

### 🔥 1. Si `types=["none"]` → solo modo catálogo

### 🔥 2. Si `types=["domain","ip"]` y hay ambos → modal con selección

### 🔥 3. Si `mode="url"` requiere `pattern` obligatorio

### 🔥 4. Tags son búsqueda semántica, no determinan ejecución

### 🔥 5. Category organiza la UI, Input organiza la lógica

---

## 10. Checklist para el equipo al crear nuevas herramientas

```
[ ] ¿Tiene dashboard? crear card none
[ ] ¿Tiene módulos con input? crear card por módulo
[ ] ¿Cada card define input.types correctamente?
[ ] ¿Puede recibir más de un input? asignar resolve_strategy
[ ] ¿Se puede inyectar input por URL? mode=url + pattern
[ ] Si no → mode=manual
[ ] ¿Categoría code existe en categorias.json?
[ ] ¿Tags tienen sentido?
```

---

