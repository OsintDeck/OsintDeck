/*
 * OSINT Deck - UI Script (v3.0)
 * --------------------------------
 * - Dashicons
 * - Filtros dinámicos (tipo, acceso, licencia, categoría, tag)
 * - Botón inteligente (pegar / limpiar)
 * - Tema claro/oscuro sincronizado con el sitio
 * - GSAP opcional para animaciones
 * - Grid responsivo con CSS Grid (sin col/row de Bootstrap)
 */

/* =========================================================
 * GLOBAL UTILS & STATE
 * ========================================================= */

// Levenshtein distance for fuzzy matching
function levenshtein(a, b) {
  if (a.length === 0) return b.length;
  if (b.length === 0) return a.length;
  const matrix = [];
  for (let i = 0; i <= b.length; i++) matrix[i] = [i];
  for (let j = 0; j <= a.length; j++) matrix[0][j] = j;
  for (let i = 1; i <= b.length; i++) {
    for (let j = 1; j <= a.length; j++) {
      if (b.charAt(i - 1) === a.charAt(j - 1)) {
        matrix[i][j] = matrix[i - 1][j - 1];
      } else {
        matrix[i][j] = Math.min(
          matrix[i - 1][j - 1] + 1, // substitution
          Math.min(
            matrix[i][j - 1] + 1,   // insertion
            matrix[i - 1][j] + 1    // deletion
          )
        );
      }
    }
  }
  return matrix[b.length][a.length];
}

// Detección extendida accesible globalmente
function detectIntent(value) {
  const v = (value || "").toLowerCase().trim();
  const tokens = v.split(/[\s,.;!?]+/); // Simple tokenization

  const intents = [
    { key: "leak", words: ["leak", "breach", "filtr", "dump"], intent: "leaks", msg: "He detectado palabras clave relacionadas con leaks o filtraciones." },
    { key: "reputation", words: ["reput", "blacklist", "spam"], intent: "reputation", msg: "He detectado intención de reputación / listas negras." },
    { key: "vuln", words: ["vuln", "cve", "bug", "exploit", "poc"], intent: "vuln", msg: "He detectado palabras clave de vulnerabilidades." },
    { key: "fraud", words: ["fraud", "scam", "fraude", "tarjeta", "carding"], intent: "fraud", msg: "He detectado contexto de fraude/finanzas." },
    { key: "help", words: ["ayuda", "necesito", "help", "soporte", "asistencia"], intent: "help", msg: "He detectado una solicitud de ayuda. Te muestro recursos disponibles." },
    { key: "greeting", words: ["hola", "buenos dias", "buenas tardes", "buenas noches", "que tal", "como estas", "saludos", "buen dia"], intent: "greeting", msg: "¡Hola! Estoy aquí para ayudarte con tus investigaciones OSINT." },
    { key: "toxic", words: ["puto", "mierda", "idiota", "imbecil", "estupido", "basura", "inutil", "maldito", "cabron", "verga", "pene", "sexo", "porno", "xxx"], intent: "toxic", msg: "Lenguaje no permitido detectado." }
  ];

  for (const item of intents) {
    // 1. Exact/Substring match (existing logic)
    if (item.words.some((w) => v.includes(w))) return item;

    // 2. Fuzzy match (Levenshtein)
    for (const w of item.words) {
      // Determine tolerance based on word length
      // < 4 chars: 0 tolerance (strict) - avoided by optimization check below unless logic changes
      // 4-6 chars: 1 typo allowed
      // > 6 chars: 2 typos allowed
      let tolerance = 1;
      if (w.length < 4) tolerance = 0;
      else if (w.length > 6) tolerance = 2;

      if (tolerance === 0) continue; // Skip short words for fuzzy matching to avoid false positives

      for (const token of tokens) {
        // Optimization: length diff check
        if (Math.abs(token.length - w.length) > tolerance) continue;
        
        if (levenshtein(token, w) <= tolerance) {
            // Log fuzzy match for debugging
            console.log(`[OSINT Deck] Fuzzy match: "${token}" ~ "${w}" (dist: ${levenshtein(token, w)})`);
            return item;
        }
      }
    }
  }
  return null;
}

function detectRichInput(value) {
  const cleanText = (s) => String(s || "").trim();
  const s = cleanText(value);
  if (!s) return { type: "none", msg: "" };

  const ret = (type, val, msg) => ({ type, value: val, msg });

  // 1. Extraction (Find entity within string)
  // URL
  const urlMatch = s.match(/https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)/i);
  if (urlMatch) return ret("url", urlMatch[0], `He encontrado una URL: ${urlMatch[0]}.`);

  // Explicit IP prefix handling (Safety net)
  if (/^ip\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i.test(s)) {
      const manualIp = s.match(/^ip\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i)[1];
      return ret("ipv4", manualIp, `He encontrado una IP: ${manualIp}.`);
  }

  // IPv4 - Improved regex for extraction (Robust: finds IP anywhere in string)
  const ipv4Candidates = s.match(/(?:\d{1,3}\.){3}\d{1,3}/g);
  if (ipv4Candidates) {
    for (const cand of ipv4Candidates) {
      // Validate segments
      const parts = cand.split('.');
      if (parts.every(p => {
        const n = parseInt(p, 10);
        return n >= 0 && n <= 255;
      })) {
        return ret("ipv4", cand, `He encontrado una IP: ${cand}.`);
      }
    }
  }

  // Email
  const emailMatch = s.match(/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/);
  if (emailMatch) return ret("email", emailMatch[0], `He encontrado un correo: ${emailMatch[0]}.`);

  // Hash (MD5, SHA1, SHA256)
  const hashMatch = s.match(/\b([a-fA-F0-9]{32}|[a-fA-F0-9]{40}|[a-fA-F0-9]{64})\b/);
  if (hashMatch) {
      const len = hashMatch[0].length;
      let type = "md5";
      if (len === 40) type = "sha1";
      if (len === 64) type = "sha256";
      return ret(type, hashMatch[0], `He encontrado un hash ${type.toUpperCase()}.`);
  }

  // Domain (Stricter regex for extraction to avoid false positives)
  const domainMatch = s.match(/\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]\b/i);
  if (domainMatch && domainMatch[0].includes(".")) {
     // Validate TLD roughly and avoid common non-domains if needed
     const tld = domainMatch[0].split('.').pop();
     if (!/^\d+$/.test(tld) && tld.length >= 2) {
         return ret("domain", domainMatch[0], `He encontrado un dominio: ${domainMatch[0]}.`);
     }
  }

  // 2. Local Intent Detection (Conversational) - ONLY if no entity extracted
  const intentMatch = detectIntent(s);
  if (intentMatch) {
    return { type: "keyword", intent: intentMatch.intent, msg: intentMatch.msg, value: s };
  }

  // 3. Strict matches (Full string matches) - Fallback for other types not covered by extraction
  // Note: Extraction logic above covers many of these, but we keep specific ones that are harder to extract reliably without false positives in free text


  // 3. Fallback to existing strict checks if extraction failed but strict matched (unlikely but safe)
  if (/^com\.[a-z0-9_-]+\.[a-z0-9_.-]+$/i.test(s)) {
    return ret("package", s, `Detecto un paquete de aplicacion ${s}. Te muestro recursos asociados.`);
  }
  if (/^((?:[0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4}|([0-9A-Fa-f]{1,4}:){1,7}:|([0-9A-Fa-f]{1,4}:){1,6}:[0-9A-Fa-f]{1,4}|([0-9A-Fa-f]{1,4}:){1,5}(:[0-9A-Fa-f]{1,4}){1,2}|([0-9A-Fa-f]{1,4}:){1,4}(:[0-9A-Fa-f]{1,4}){1,3}|([0-9A-Fa-f]{1,4}:){1,3}(:[0-9A-Fa-f]{1,4}){1,4}|([0-9A-Fa-f]{1,4}:){1,2}(:[0-9A-Fa-f]{1,4}){1,5}|[0-9A-Fa-f]{1,4}:((:[0-9A-Fa-f]{1,4}){1,6})|:((:[0-9A-Fa-f]{1,4}){1,7}|:))$/i.test(s)) {
    return ret("ipv6", s, `Detecto la IP ${s}. Aqui tienes utilidades que pueden ayudarte a investigarla.`);
  }
  if (/^AS\d{1,10}$/i.test(s)) {
    return ret("asn", s, `Detecto un ASN: ${s}. Aqui tienes herramientas relacionadas.`);
  }
  if (/^[0-9A-Fa-f]{2}([:-][0-9A-Fa-f]{2}){5}$/.test(s)) {
    return ret("mac", s, `Ingresaste una direccion MAC: ${s}. Estas son las utilidades que pueden ayudarte.`);
  }
  if (/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(s)) {
    return ret("uuid", s, `Has ingresado un UUID: ${s}. Estas son las herramientas que puedes usar.`);
  }
  if (/^\d{4,10}$/.test(s)) {
    return ret("zip", s, `Detecto un codigo postal: ${s}. Te muestro herramientas relacionadas.`);
  }
  if (/^\+?[0-9][0-9\s().-]{6,}$/i.test(s)) {
    return ret("phone", s, `Has ingresado el numero telefonico ${s}. Estas son las herramientas disponibles.`);
  }
  if (/^-?\d{1,3}\.\d+,\s*-?\d{1,3}\.\d+$/.test(s)) {
    return ret("geo", s, `Detecto coordenadas: ${s}. Aqui tienes recursos que trabajan con este tipo de datos.`);
  }
  if (/^0x[a-fA-F0-9]{40}$/.test(s)) {
    return ret("eth", s, `Detecto una direccion de criptomoneda: ${s}. Aqui tienes las herramientas asociadas.`);
  }
  if (/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,39}$/.test(s)) {
    return ret("btc", s, `Has ingresado una wallet: ${s}. Te muestro recursos para investigarla.`);
  }
  if (/(\.zip|\.rar|\.7z|\.pdf|\.docx?|\.xlsx?|\.pptx?|\.exe|\.dll|\.apk|\.ipa|\.jpg|\.jpeg|\.png|\.gif)$/i.test(s)) {
    return ret("file", s, `Archivo detectado: ${s}. Te muestro utilidades disponibles.`);
  }
  if (/BEGIN PGP PUBLIC KEY BLOCK/i.test(s)) {
    return ret("pgp", s, "Has ingresado una clave PGP. Aqui tienes herramientas compatibles.");
  }
  if (/^@[a-z0-9_.-]{2,32}$/i.test(s)) {
    return ret("username", s, `Detecto un nombre de usuario: ${s}. Estas son las herramientas disponibles.`);
  }

  const fallbackIntent = detectIntent(s);
  if (fallbackIntent) {
    return { type: "keyword", intent: fallbackIntent.intent, msg: fallbackIntent.msg, value: s };
  }

  if (s.split(" ").length > 2) {
    return { type: "keyword", msg: `He detectado palabras clave en tu busqueda (${s}). Te muestro herramientas generales asociadas.`, value: s };
  }
  return { type: "generic", msg: `He recibido tu busqueda: ${s}. Te muestro herramientas generales que pueden ser utiles. [v2]`, value: s };
}

/* Toast (Singleton) */
let toastEl, toastTimer;
function initToast() {
    if (document.querySelector('.osint-toast')) {
        toastEl = document.querySelector('.osint-toast');
        return;
    }
    toastEl = document.createElement("div");
    toastEl.className = "osint-toast";
    document.body.appendChild(toastEl);
}

function toast(text) {
    if (!text || !toastEl) return;
    const el = toastEl;
    el.textContent = text;
    el.classList.remove("show");
    void el.offsetWidth;
    el.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove("show"), 1800);
}

/* Tooltip detección de entrada (Singleton) */
let tooltipEl, tooltipTimer;
function showTooltip(text) {
    if (!text) return;
    if (!tooltipEl) {
        tooltipEl = document.createElement("div");
        tooltipEl.className = "osd-tooltip";
        document.body.appendChild(tooltipEl);
        tooltipEl.onmouseenter = () => clearTimeout(tooltipTimer);
        tooltipEl.onmouseleave = () => {
            tooltipTimer = setTimeout(() => tooltipEl.classList.remove("show"), 1000);
        };
    }
    tooltipEl.innerHTML = `<span>${text}</span>`;
    tooltipEl.classList.add("show");
    clearTimeout(tooltipTimer);
    tooltipTimer = setTimeout(() => {
        if (!tooltipEl.matches(":hover")) tooltipEl.classList.remove("show");
    }, 3000);
}

/* =========================================================
 * INSTANCE INIT
 * ========================================================= */
function initOsintDeck(wrap) {
  if (!wrap) return;
  const uid = wrap.getAttribute("id") || "";
  const cfg = (window.OSINT_DECK_DATA && window.OSINT_DECK_DATA[uid]) || {};

  const FILTER_LABELS = {
    type: "Tipo",
    access: "Acceso",
    license: "Licencia",
    category: "Categoría",
    tag: "Tag"
  };

  const TYPE_MAP = {
    email: ["correo", "email"],
    domain: ["dominio", "dns", "domain"],
    url: ["url", "web"],
    ipv4: ["ip", "ipv4"],
    ipv6: ["ip", "ipv6"],
    hash: ["hash", "archivo", "file"],
    sha256: ["hash", "sha256", "file"],
    sha1: ["hash", "sha1", "file"],
    md5: ["hash", "md5", "file"],
    uuid: ["uuid", "guid"],
    phone: ["telefono", "phone"],
    geo: ["geo", "gps", "coordenadas"],
    username: ["user", "usuario", "perfil"],
    asn: ["asn"],
    mac: ["mac"],
    wallet: ["wallet", "crypto", "blockchain"],
    eth: ["wallet", "eth", "ethereum"],
    btc: ["wallet", "btc", "bitcoin"],
    file: ["archivo", "file", "malware"],
    package: ["apk", "android", "mobile", "app"],
    zip: ["geo", "postal", "zip"],
    pgp: ["pgp", "key", "crypto"],
    fullname: ["persona", "people", "name", "nombre"],
    leaks: ["leak", "breach", "password", "credential"],
    reputation: ["reputation", "score", "blacklist", "spam"],
    vuln: ["cve", "exploit", "vuln", "vulnerability"],
    fraud: ["fraud", "scam", "carding", "finance"],
    help: ["help", "ayuda", "soporte", "asistencia", "support"],
    greeting: ["greeting", "hola", "saludo", "assistant"],
    toxic: ["warning", "filter", "security"]
  };

  let tools = [];
  try {
    const rawTools = wrap.getAttribute("data-tools") || "[]";
    tools = JSON.parse(rawTools);
    console.log(`[OSINT Deck] Loaded ${tools.length} tools for ${uid}`);
  } catch (e) {
    console.error("[OSINT Deck] Error parsing tools JSON:", e);
    tools = [];
  }

  /* =========================================================
   * TEMA (LIGHT / DARK / AUTO)
   * ========================================================= */
  const themeMode = cfg.themeMode || "auto";
  const themeSelector = cfg.themeSelector || "[data-site-skin]";
  const tokenLight = cfg.tokenLight || "light";
  const tokenDark = cfg.tokenDark || "dark";

  function setContainerTheme(mode) {
    wrap.setAttribute("data-theme", mode === "dark" ? "dark" : "light");
  }

  function readSiteTheme() {
    const el = document.querySelector(themeSelector);
    if (!el) return null;
    const attrName = themeSelector.replace(/^\[|\]$/g, "");
    const val = el.getAttribute(attrName);
    if (!val) return null;
    const lower = val.toLowerCase();
    if (lower === tokenDark.toLowerCase()) return "dark";
    if (lower === tokenLight.toLowerCase()) return "light";
    return null;
  }

  function observeSiteTheme() {
    const el = document.querySelector(themeSelector);
    if (!el) return;
    const attrName = themeSelector.replace(/^\[|\]$/g, "");
    const obs = new MutationObserver(() => {
      const current = readSiteTheme();
      if (current) setContainerTheme(current);
    });
    obs.observe(el, {
      attributes: true,
      attributeFilter: [attrName]
    });
  }

  (function initTheme() {
    if (themeMode === "auto") {
      const siteTheme = readSiteTheme();
      setContainerTheme(siteTheme || "light");
      observeSiteTheme();
    } else {
      setContainerTheme(themeMode);
    }
  })();

  /* =========================================================
   * MARKUP PRINCIPAL
   * ========================================================= */
  wrap.innerHTML = `
    <!-- 🔹 Barra de búsqueda -->
    <div class="osint-chatbar" role="search">
      <div class="osint-detected" id="${uid}-detected" aria-live="polite"></div>
      <div class="osint-input-group">
        <span class="osint-icon-static">
          <span class="dashicons dashicons-search"></span>
        </span>

        <input type="text"
               class="osint-input"
               id="${uid}-q"
               placeholder="Buscar o pegar una entrada...">

        <button type="button"
                class="osint-btn-ghost osint-toggle-filters"
                id="${uid}-toggleFilters"
                aria-label="Mostrar/ocultar filtros"
                data-title="Ajustar filtros">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
            <line x1="4" y1="21" x2="4" y2="14"></line>
            <line x1="4" y1="10" x2="4" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12" y2="3"></line>
            <line x1="20" y1="21" x2="20" y2="16"></line>
            <line x1="20" y1="12" x2="20" y2="3"></line>
            <line x1="1"  y1="14" x2="7"  y2="14"></line>
            <line x1="9"  y1="8"  x2="15" y2="8"></line>
            <line x1="17" y1="16" x2="23" y2="16"></line>
          </svg>
        </button>

        <button type="button"
                class="osint-btn-ghost"
                id="${uid}-smart"
                aria-label="Pegar o limpiar">
          <span class="osint-icon" data-mode="paste">
            <span class="dashicons dashicons-admin-page"></span>
          </span>
        </button>
      </div>
    </div>

    <!-- 🔹 Filtros -->
    <div class="osint-filter-wrap" id="${uid}-filterWrap" aria-hidden="true">
    <div class="osint-filter-bar" id="${uid}-filters">
      <!-- TIPO -->
      <div class="osint-filter-dropdown">
        <button class="osint-filter-btn" data-filter="type">Tipo ▼</button>
        <div class="osint-dropdown-menu" data-for="type"></div>
      </div>

      <!-- ACCESO -->
      <div class="osint-filter-dropdown">
        <button class="osint-filter-btn" data-filter="access">Acceso ▼</button>
        <div class="osint-dropdown-menu" data-for="access"></div>
      </div>

      <!-- LICENCIA -->
      <div class="osint-filter-dropdown">
        <button class="osint-filter-btn" data-filter="license">Licencia ▼</button>
        <div class="osint-dropdown-menu" data-for="license"></div>
      </div>

      <!-- CATEGORÍA -->
      <div class="osint-filter-dropdown">
        <button class="osint-filter-btn" data-filter="category">Categoría ▼</button>
        <div class="osint-dropdown-menu" data-for="category"></div>
      </div>
      
      <!-- GRUPO CENTRAL (Counter + Star) -->
      <div style="display:flex; align-items:center; gap:12px; margin: 0 auto;">
        <div class="osint-counter" id="${uid}-counter" style="font-size: 0.85em; opacity: 0.7;"></div>
        <button class="osint-action-btn osint-popular-toggle" id="${uid}-popular-btn" aria-label="Solo populares" data-title="Solo populares">
          <i class="ri-star-line"></i>
        </button>
      </div>

      <!-- SEPARADOR -->
      <div style="width:1px; height:24px; background:var(--osint-border); align-self:center;"></div>

      <!-- BOTÓN LIMPIAR -->
      <button class="osint-action-btn osint-clear-filters" id="${uid}-clear-btn" aria-label="Limpiar filtros" data-title="Limpiar filtros">
        <i class="ri-close-line"></i>
      </button>
    </div>
    </div>

    <!-- 🔹 Grilla -->
    <div class="osint-grid" id="${uid}-grid"></div>

    <!-- 🔹 Modal -->
    <div class="osint-overlay" id="${uid}-overlay" aria-hidden="true">
      <div class="osint-sheet" id="${uid}-sheet" role="dialog" aria-modal="true">
        <header class="osint-sheet-hdr">
            <button class="osint-sheet-close" type="button" aria-label="Cerrar" id="${uid}-sheetClose">×</button><div class="osint-sheet-title" id="${uid}-sheetTitle"></div>
          <div class="osint-sheet-sub" id="${uid}-sheetSub"></div>
          <div class="osint-sheet-desc" id="${uid}-sheetDesc"></div>
          <div class="osint-sheet-meta" id="${uid}-sheetMeta"></div>
        </header>
        <div class="osint-sheet-grid" id="${uid}-sheetGrid"></div>
      </div>
    </div>
  `;

  /* =========================================================
   * UTILIDADES LOCALES
   * ========================================================= */
  const esc = (s) =>
    String(s || "").replace(/[&<>"]/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;" }[m] || m));

  const domainFromUrl = (u) =>
    ((u || "").match(/https?:\/\/([^/]+)/i) || [])[1] || "";

  const build = (tpl, val) =>
    String(tpl || "").replace("{input}", encodeURIComponent(val || ""));

  const cleanText = (s) => String(s || "").trim();
  const chunkSize = 12;
  let renderedCount = 0;
  let filteredCache = [];
  let currentDetection = null;
  let filterPopularOnly = false;

  const ajaxCfg = window.osintDeckAjax || window.OSINT_DECK_AJAX || {};
  const fpCache = (() => {
    try {
      const parts = [
        navigator.userAgent || '',
        navigator.language || '',
        (Intl.DateTimeFormat().resolvedOptions().timeZone || ''),
        String(new Date().getTimezoneOffset()),
        `${(screen || {}).width || ''}x${(screen || {}).height || ''}`
      ];
      return btoa(unescape(encodeURIComponent(parts.join('|'))));
    } catch (e) {
      return '';
    }
  })();

  function sendEvent(event, payload = {}) {
    if (!ajaxCfg.url || !ajaxCfg.nonce) return Promise.resolve({ ok: false });
    const data = new URLSearchParams();
    data.append("action", "osd_user_event");
    data.append("nonce", ajaxCfg.nonce);
    data.append("event", event);
    if (fpCache) data.append("fp", fpCache);
    Object.entries(payload).forEach(([k, v]) => data.append(k, v || ""));

    return fetch(ajaxCfg.url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    })
      .then(async (r) => {
        const payload = await r.json().catch(() => ({}));
        if (typeof payload.ok === "undefined") {
          payload.ok = r.ok;
        }
        payload.status = r.status;
        return payload;
      })
      .catch(() => ({ ok: false, status: 0 }));
  }

  /* =========================================================
   * NORMALIZACIÓN DE CATEGORÍAS padre / hijo
   * ========================================================= */
  function normalizeCategory(cat) {
    if (!cat) return { parent: "", child: "" };
    const parts = String(cat)
      .split("/")
      .map((s) => s.trim())
      .filter(Boolean);
    return {
      parent: parts[0] || "",
      child: parts[1] || ""
    };
  }

  function buildCategoryTree(list) {
    const tree = {};
    list.forEach((tool) => {
      // 1. Check tool top-level category
      if (tool.category) {
          const cat = normalizeCategory(tool.category);
          if (cat.parent) {
            if (!tree[cat.parent]) tree[cat.parent] = new Set();
            if (cat.child) tree[cat.parent].add(cat.child);
          }
      }

      // 2. Check cards categories
      (tool.cards || []).forEach((card) => {
        const cat = normalizeCategory(card.category);
        if (!cat.parent) return;
        if (!tree[cat.parent]) {
          tree[cat.parent] = new Set();
        }
        if (cat.child) {
          tree[cat.parent].add(cat.child);
        }
      });
    });
    Object.keys(tree).forEach((parent) => {
      tree[parent] = Array.from(tree[parent]);
    });
    return tree;
  }

  /* =========================================================
   * POBLAR MENÚS DE FILTRO
   * ========================================================= */
  function populateTypeMenu() {
    const menu = wrap.querySelector('.osint-dropdown-menu[data-for="type"]');
    if (!menu) return;

    const tipos = new Set();
    tools.forEach((t) => {
      if (t.info && t.info.tipo) {
        tipos.add(String(t.info.tipo).trim());
      }
    });

    const sorted = [...tipos].sort((a, b) => a.localeCompare(b));
    menu.innerHTML =
      '<button data-value="">Todos</button>' +
      sorted
        .map(
          (t) => `
        <button data-value="${esc(t.toLowerCase())}">
          ${esc(t)}
        </button>`
        )
        .join("");
  }

  function populateAccessMenu() {
    const menu = wrap.querySelector('.osint-dropdown-menu[data-for="access"]');
    if (!menu) return;

    const accesos = new Set();
    tools.forEach((t) => {
      if (t.info && t.info.acceso) {
        accesos.add(String(t.info.acceso).trim());
      }
    });

    const sorted = [...accesos].sort((a, b) => a.localeCompare(b));
    menu.innerHTML =
      '<button data-value="">Todos</button>' +
      sorted
        .map(
          (a) => `
        <button data-value="${esc(a.toLowerCase())}">
          ${esc(a)}
        </button>`
        )
        .join("");
  }

  function populateLicenseMenu() {
    const menu = wrap.querySelector(
      '.osint-dropdown-menu[data-for="license"]'
    );
    if (!menu) return;

    const licencias = new Set();
    tools.forEach((t) => {
      if (t.info && t.info.licencia) {
        licencias.add(String(t.info.licencia).trim());
      }
    });

    const sorted = [...licencias].sort((a, b) => a.localeCompare(b));
    menu.innerHTML =
      '<button data-value="">Todas</button>' +
      sorted
        .map(
          (l) => `
        <button data-value="${esc(l.toLowerCase())}">
          ${esc(l)}
        </button>`
        )
        .join("");
  }

  function populateCategoryMenu() {
    const menu = wrap.querySelector(
      '.osint-dropdown-menu[data-for="category"]'
    );
    if (!menu) return;

    const tree = buildCategoryTree(tools);
    let html = `
      <div class="osint-cat-item" data-value="">Todas</div>
    `;

    Object.entries(tree).forEach(([parent, subs]) => {
      if (!subs.length) {
        html += `
          <div class="osint-cat-item" data-value="${esc(
          parent.toLowerCase()
        )}">
            ${esc(parent)}
          </div>
        `;
        return;
      }

      html += `
        <div class="osint-cat-parent">
          <span>${esc(parent)}</span>
          <div class="osint-submenu">
            ${subs
          .map(
            (s) => `
              <div class="osint-cat-item"
                   data-value="${esc((parent + " / " + s).toLowerCase())}">
                ${esc(s)}
              </div>`
          )
          .join("")}
          </div>
        </div>
      `;
    });

    menu.innerHTML = html;
  }

  /* =========================================================
   * REFERENCIAS DOM
   * ========================================================= */
  const q = document.getElementById(`${uid}-q`);
  const btnSmart = document.getElementById(`${uid}-smart`);
  const grid = document.getElementById(`${uid}-grid`);
  const overlay = document.getElementById(`${uid}-overlay`);
  const sheet = document.getElementById(`${uid}-sheet`);
  const sheetTitle = document.getElementById(`${uid}-sheetTitle`);
  const sheetSub = document.getElementById(`${uid}-sheetSub`);
  const sheetDesc = document.getElementById(`${uid}-sheetDesc`);
  const sheetMeta = document.getElementById(`${uid}-sheetMeta`);
  const sheetGrid = document.getElementById(`${uid}-sheetGrid`);
  const sheetClose = document.getElementById(`${uid}-sheetClose`);
  if (sheet) {
    sheet.setAttribute("aria-labelledby", `${uid}-sheetTitle`);
  }
  if (sheetClose) {
    sheetClose.addEventListener("click", closeModal);
  }
  const focusableSelector = 'a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])';
  let lastFocused = null;

  const toggleFiltersBtn = document.getElementById(`${uid}-toggleFilters`);
  const filterWrapRef = document.getElementById(`${uid}-filterWrap`);
  const filtersBarRef = document.getElementById(`${uid}-filters`);
  const chatBarRef = filterWrapRef ? filterWrapRef.previousElementSibling : null;
  const counterRef = document.getElementById(`${uid}-counter`);

  let showFilters = false;
  let isAnimating = false;

  /* =========================================================
   * MOSTRAR / OCULTAR FILTROS
   * ========================================================= */
   
  // ResizeObserver for dynamic height
  const ro = new ResizeObserver(() => {
    if (showFilters && !isAnimating && filterWrapRef && filterWrapRef.style.height !== "auto") {
      filterWrapRef.style.height = filtersBarRef.scrollHeight + "px";
    }
  });
  if (filtersBarRef) ro.observe(filtersBarRef);

  // Initialize state
  if (filterWrapRef) filterWrapRef.style.height = "0px";

  function ensureFiltersVisible() {
    if (!showFilters) openFilters();
  }

  function openFilters() {
     if (showFilters || isAnimating || !filterWrapRef) return;
     isAnimating = true;
     showFilters = true;

     filterWrapRef.classList.add("is-open");
     if (chatBarRef) chatBarRef.classList.add("has-filters-open");
     filterWrapRef.setAttribute("aria-hidden", "false");

     filterWrapRef.style.height = "0px";
     // Force reflow
     void filterWrapRef.getBoundingClientRect();

     filterWrapRef.style.height = filtersBarRef.scrollHeight + "px";

     const onEnd = (e) => {
       if (e.propertyName !== "height") return;
       filterWrapRef.removeEventListener("transitionend", onEnd);
       filterWrapRef.style.height = "auto";
       isAnimating = false;
     };
     filterWrapRef.addEventListener("transitionend", onEnd);
  }

  function closeFilters() {
     if (!showFilters || isAnimating || !filterWrapRef) return;
     isAnimating = true;
     showFilters = false;

     filterWrapRef.classList.add("is-closing");
     filterWrapRef.classList.remove("is-open");
     if (chatBarRef) chatBarRef.classList.remove("has-filters-open");
     filterWrapRef.setAttribute("aria-hidden", "true");

     filterWrapRef.style.height = filtersBarRef.scrollHeight + "px";
     void filterWrapRef.getBoundingClientRect();

     requestAnimationFrame(() => {
       filterWrapRef.style.height = "0px";
     });

     const onEnd = (e) => {
       if (e.propertyName !== "height") return;
       filterWrapRef.removeEventListener("transitionend", onEnd);
       filterWrapRef.classList.remove("is-closing");
       isAnimating = false;
     };
     filterWrapRef.addEventListener("transitionend", onEnd);
  }

  if (toggleFiltersBtn) {
    toggleFiltersBtn.addEventListener("click", () => {
      showFilters ? closeFilters() : openFilters();
    });
  }

  /* =========================================================
   * BOTÓN PEGAR / LIMPIAR
   * ========================================================= */
  function setSmartIcon(mode) {
    const icon = btnSmart.querySelector(".osint-icon");
    icon.setAttribute("data-mode", mode);
    
    // Update tooltip
    btnSmart.setAttribute("data-title", mode === "paste" ? "Pegar desde portapapeles" : "Limpiar búsqueda");

    icon.innerHTML =
      mode === "paste"
        ? `<span class="dashicons dashicons-admin-page"></span>`
        : `<span class="dashicons dashicons-no-alt"></span>`;
  }

  function toggleSmart() {
    const mode =
      (q.value || "").trim() === "" ? "paste" : "clear";
    setSmartIcon(mode);
  }

  btnSmart.addEventListener("click", () => {
    const mode = btnSmart
      .querySelector(".osint-icon")
      .getAttribute("data-mode");
    if (mode === "paste") {
      if (navigator.clipboard && navigator.clipboard.readText) {
        navigator.clipboard.readText().then((text) => {
          q.value = text || "";
          onInput();
        });
      }
    } else {
      q.value = "";
      onInput();
    }
  });

  /* =========================================================
   * ESTADO DE FILTROS
   * ========================================================= */
  let currentType = "";
  let currentAccess = "";
  let currentLicense = "";
  let currentCat = "";
  let currentTag = "";

  /* =========================================================
   * APLICAR FILTROS
   * ========================================================= */
  function applyFilters() {
    const text = (q.value || "").toLowerCase().trim();
    const detection = detectRichInput(q.value || "");
    updateDetectedMessage(detection.msg || "");

    const filtered = tools.filter((t) => {
      const tipo = (t.info && t.info.tipo
        ? t.info.tipo
        : ""
      )
        .toLowerCase()
        .trim();
      const licencia = (t.info && t.info.licencia
        ? t.info.licencia
        : ""
      )
        .toLowerCase()
        .trim();
      const acceso = (t.info && t.info.acceso
        ? t.info.acceso
        : ""
      )
        .toLowerCase()
        .trim();

      if (currentType && !tipo.includes(currentType)) return false;
      if (currentLicense && !licencia.includes(currentLicense)) return false;
      if (currentAccess && !acceso.includes(currentAccess)) return false;

      // Categoría: se mira en el tool y en las cards
      if (currentCat) {
        let match = false;
        const curNorm = normalizeCategory(currentCat.toLowerCase());

        // 1. Check tool category
        if (t.category) {
          const tCat = t.category.toLowerCase();
          if (tCat === currentCat) {
            match = true;
          } else {
            const tNorm = normalizeCategory(tCat);
            if (tNorm.parent && tNorm.parent === curNorm.parent && !curNorm.child) {
              match = true;
            }
          }
        }

        // 2. Check cards categories
        if (!match) {
          match = (t.cards || []).some((c) => {
            const cat = (c.category || "").toLowerCase();
            if (cat === currentCat) return true;
            const norm = normalizeCategory(cat);
            if (norm.parent && norm.parent === curNorm.parent && !curNorm.child)
              return true;
            return false;
          });
        }

        if (!match) return false;
      }

      // Tag: se mira en tags del tool + tags de las cards
      if (currentTag) {
        const lowTag = currentTag.toLowerCase();
        const toolTags = (t.tags || []).map((x) => String(x).toLowerCase());
        const cardTags = (t.cards || []).flatMap((c) =>
          (c.tags || []).map((x) => String(x).toLowerCase())
        );
        const allTags = [...toolTags, ...cardTags];
        if (!allTags.some((tg) => tg.includes(lowTag))) return false;
      }

      // Filtrado por detección o búsqueda de texto
      let wanted = [];
      if (detection && detection.type && detection.type !== "none") {
        if (detection.type === "keyword" && detection.intent) {
          wanted = TYPE_MAP[detection.intent] || [];
        } else {
          wanted = TYPE_MAP[detection.type] || [];
        }
      }

      if (wanted.length > 0) {
        // Filtrado por tags de detección
        const toolTags = (t.tags || []).map(x => String(x).toLowerCase());
        const matchInCards = (t.cards || []).some(c => {
             const cTags = (c.tags || []).map(x => String(x).toLowerCase());
             const cCat = (c.category || "").toLowerCase();
             return cTags.some(tag => wanted.includes(tag)) || wanted.includes(cCat);
        });
        const matchInTool = toolTags.some(tag => wanted.includes(tag));
        
        if (!matchInCards && !matchInTool) return false;

      } else if (text) {
        // Fallback: Búsqueda libre por texto
        const bagParts = [
          t.name || "",
          t.category || "",
          tipo,
          licencia,
          acceso,
          ...(t.tags || [])
        ];

        (t.cards || []).forEach((c) => {
          bagParts.push(c.title || "", c.desc || "", c.category || "");
        });

        const bag = bagParts.join(" ").toLowerCase();
        if (!bag.includes(text)) return false;
      }

      return true;
    });

    renderDecks(filtered, detection);
  }

  /* =========================================================
   * SET FILTER
   * ========================================================= */
  function setFilter(key, rawValue, resetAll = false) {
    if (resetAll) {
      currentType = "";
      currentAccess = "";
      currentLicense = "";
      currentCat = "";
      currentTag = "";
      q.value = "";

      wrap
        .querySelectorAll(".osint-filter-btn")
        .forEach((btn) => {
          const f = btn.dataset.filter;
          if (FILTER_LABELS[f]) {
            btn.textContent = `${FILTER_LABELS[f]} ▼`;
            btn.classList.remove("active");
          }
        });
    }

    if (!key) {
      applyFilters();
      toggleSmart();
      return;
    }

    const value = (rawValue || "").toLowerCase().trim();

    if (key === "type") currentType = value;
    if (key === "access") currentAccess = value;
    if (key === "license") currentLicense = value;
    if (key === "category") currentCat = value;
    if (key === "tag") currentTag = value;

    const btn = wrap.querySelector(
      `.osint-filter-btn[data-filter="${key}"]`
    );
    if (btn && FILTER_LABELS[key]) {
      if (value) {
        btn.textContent = `${FILTER_LABELS[key]}: ${rawValue} ▼`;
        btn.classList.add("active");
      } else {
        btn.textContent = `${FILTER_LABELS[key]} ▼`;
        btn.classList.remove("active");
      }
    }

    ensureFiltersVisible();
    applyFilters();
    toggleSmart();
  }

  /* =========================================================
   * GLOBAL LISTENERS (DELEGATED BUT SCOPED TO INSTANCE VIA LOGIC)
   * ========================================================= */
  document.addEventListener("click", (e) => {
    // Dropdowns de este instance
    const menus = wrap.querySelectorAll(".osint-dropdown-menu");
    menus.forEach((menu) => {
      if (!menu.contains(e.target) && !e.target.closest(".osint-filter-btn")) {
        menu.classList.remove("show");
      }
    });

    // Toggle menu
    const btn = e.target.closest(".osint-filter-btn");
    // Only if button belongs to this wrapper
    if (btn && wrap.contains(btn) && btn.dataset.filter) {
       const menu = wrap.querySelector(`.osint-dropdown-menu[data-for="${btn.dataset.filter}"]`);
       
       menus.forEach(m => {
           if (m !== menu) m.classList.remove("show");
       });
       
       if (menu) menu.classList.toggle("show");
    }

    // Select option
    const itemBtn = e.target.closest(".osint-dropdown-menu button");
    // Only if item belongs to this wrapper
    if (itemBtn && wrap.contains(itemBtn)) {
      const menu = itemBtn.closest(".osint-dropdown-menu");
      const val = itemBtn.dataset.value || "";
      const forKey = menu.dataset.for || "";
      setFilter(forKey, val ? itemBtn.textContent.trim() : "", false);
    }

    // Category click
    const catItem = e.target.closest(".osint-cat-item, .osint-cat-parent");
    if (catItem && wrap.contains(catItem)) {
       const menu = catItem.closest('.osint-dropdown-menu[data-for="category"]');
       if (menu) {
           const rawValue = catItem.dataset.value || catItem.textContent.trim();
           // Only leaf items trigger filter, parents might toggle submenu (CSS handles hover, JS not strictly needed for toggle unless mobile)
           if (catItem.classList.contains("osint-cat-item")) {
               setFilter("category", rawValue, false);
               menu.classList.remove("show");
           }
       }
    }
  });

  function renderBadges(tool) {
    const badges = [];
    const metaBadges = (tool.meta && tool.meta.badges) || tool.badges || [];
    const recent = tool.meta && tool.meta.is_new;
    const reported = tool.meta && tool.meta.reported;
    const detectedType = currentDetection && currentDetection.type;
    const recommended =
      detectedType &&
      tool.meta &&
      tool.meta.last_input_type &&
      tool.meta.last_input_type === detectedType;

    if (recent) {
      badges.push('<span class="osint-badge osint-badge-new" title="Nueva"><i class="ri-flashlight-fill"></i></span>');
    }

    if (recommended) {
      badges.push('<span class="osint-badge osint-badge-tip" title="Recomendada"><i class="ri-heart-3-fill"></i></span>');
    }

    if (reported) {
      badges.push('<span class="osint-badge osint-badge-warn" title="Reportada"><i class="ri-alert-fill"></i></span>');
    }

    return badges;
  }

  function renderDeckElement(t) {
    const detected = currentDetection || detectRichInput(q.value || "");
    const deck = document.createElement("div");
    deck.className = "osint-deck";

    const cards = Array.isArray(t.cards) ? t.cards : [];
    if (!cards.length) return null;

    let primaryCard = pickPrimaryCard(cards, detected);

    if (currentCat) {
      const byCat = cards.find(
        (c) =>
          (c.category || "").toLowerCase() === currentCat.toLowerCase()
      );
      if (byCat) primaryCard = byCat;
    }

    const orderedCards = [
      primaryCard,
      ...cards.filter((c) => c !== primaryCard)
    ];
    const stackCount = Array.isArray(cards) ? cards.length : 0;

    const mainCard = orderedCards[0];
    if (mainCard) {
      const card = document.createElement("div");
      card.className = "osint-card layer-0";
      card.style.zIndex = String(10 + stackCount);

      const fav =
        t.favicon ||
        ("https://www.google.com/s2/favicons?sz=64&domain=" +
          domainFromUrl(mainCard.url || ""));

      const desc = mainCard.desc || "";
      const url = mainCard.url || "#";
      const cat = (mainCard.category || "").trim();

      let accessIcon = '';
      if (t.info && t.info.acceso) {
        const acc = t.info.acceso.toLowerCase();
        if (acc.includes('gratis') || acc.includes('free')) accessIcon = '<i class="ri-lock-unlock-line"></i>';
        else if (acc.includes('pago') || acc.includes('paid')) accessIcon = '<i class="ri-vip-crown-line"></i>';
        else accessIcon = '<i class="ri-key-2-line"></i>';
      }

      card.innerHTML = `
        <div class="osint-card-hdr">
          <div class="osint-top-row">
            <div class="osint-fav">
              <img alt="${esc(t.name || 'Logo')}" src="${esc(fav)}">
            </div>
            <div class="osint-title-wrap">
              <h4 class="osint-ttl">${esc(t.name)}</h4>
              ${cat
          ? `<div class="osint-category-label"><i class="ri-global-line"></i> ${esc(cat)}</div>`
          : ""
        }
            </div>
            <div class="osint-status">${renderBadges(t).join("")}</div>
          </div>

          <div class="osint-mini-meta">
            <span class="osd-chip"><i class="ri-stack-line"></i> ${stackCount} deck</span>
          </div>
        </div>

        <div class="osint-sub osint-main-desc">
          ${esc(desc)}
        </div>

        <div class="osint-deck-footer">
          <div class="osd-meta-badges">
            ${t.info && t.info.tipo
          ? `<span class="osd-badge osd-badge-blue osd-filter"
                         data-key="type"
                         data-value="${esc(t.info.tipo)}">
                         <i class="ri-global-line"></i>
                    ${esc(t.info.tipo)}
                   </span>`
          : ""
        }

            ${t.info && t.info.licencia
          ? `<span class="osd-badge osd-badge-yellow osd-filter"
                         data-key="license"
                         data-value="${esc(t.info.licencia)}">
                         <i class="ri-code-box-line"></i>
                    ${esc(t.info.licencia)}
                   </span>`
          : ""
        }

            ${t.info && t.info.acceso
          ? `<span class="osd-badge ${String(t.info.acceso)
            .toLowerCase()
            .includes("gratis")
            ? "osd-badge-green"
            : "osd-badge-red"
          } osd-filter"
                         data-key="access"
                         data-value="${esc(t.info.acceso)}">
                         ${accessIcon}
                    ${esc(t.info.acceso)}
                   </span>`
          : ""
        }
          </div>

          <div class="osint-deck-footer-actions">
            ${renderActions(t)}
          </div>
        </div>
      `;

      card.querySelectorAll(".osd-filter").forEach((badge) => {
        badge.addEventListener("click", (e) => {
          e.stopPropagation();
          const key = badge.dataset.key || "";
          const val = badge.dataset.value || "";
          setFilter(key, val, true);
        });
      });

      attachActionEvents(card, url, t, detected);
      card.addEventListener("click", () => openModal(t));
      deck.appendChild(card);
    }

    const edgeLayers = Math.min(stackCount - 1, 5);
    for (let i = 1; i <= edgeLayers; i++) {
      const edge = document.createElement("div");
      edge.className = `osint-card layer-${i}`;
      edge.style.zIndex = String(10 + stackCount - i);
      deck.appendChild(edge);
    }

    return deck;
  }

  function pickPrimaryCard(cards, detection) {
    if (!Array.isArray(cards) || !cards.length) return cards && cards[0];

    let type = detection && detection.type ? detection.type : "";
    if (type === "keyword" && detection.intent) {
        type = detection.intent;
    }

    const wanted = TYPE_MAP[type] || [];
    if (wanted.length) {
      const matchCard = cards.find((c) => {
        const tags = (c.tags || []).map((x) => String(x).toLowerCase());
        const cat = (c.category || "").toLowerCase();
        return tags.some((t) => wanted.includes(t)) || wanted.includes(cat);
      });
      if (matchCard) return matchCard;
    }
    return cards[0];
  }

  /* =========================================================
   * PREVIEW MODAL
   * ========================================================= */
  let previewOverlay = null;

  function createPreviewModal() {
    if (document.getElementById('osint-preview-overlay')) {
        previewOverlay = document.getElementById('osint-preview-overlay');
        return;
    }

    const div = document.createElement('div');
    div.id = 'osint-preview-overlay';
    div.className = 'osint-overlay';
    div.style.zIndex = '10000';
    div.innerHTML = `
      <div class="osint-sheet" style="width:95%; max-width:1400px; height:90vh; max-height:90vh; display:flex; flex-direction:column;">
        <div class="osint-sheet-hdr" style="padding:12px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--osint-border);">
           <div style="display:flex; flex-direction:column;">
             <h3 class="osint-sheet-title" id="osint-preview-title" style="margin:0; font-size:16px;">Vista Previa</h3>
             <span style="font-size:11px; color:var(--osint-muted);">Algunos sitios bloquean la vista previa (X-Frame-Options).</span>
           </div>
           <div style="display:flex; gap:12px; align-items:center;">
               <button id="osint-preview-report" class="osint-btn-ghost" title="Reportar que no carga (Marcar como bloqueado)" style="width:auto; padding:0 8px; font-size:12px; color:var(--osint-muted); display:flex; gap:4px;">
                   <i class="ri-error-warning-line"></i> <span style="display:none; @media(min-width:600px){display:inline;}">¿No carga?</span>
               </button>
               <div style="width:1px; height:20px; background:var(--osint-border);"></div>
               <a href="#" target="_blank" id="osint-preview-ext" class="osint-btn-ghost" title="Abrir en nueva pestaña" style="width:32px; height:32px;"><i class="ri-external-link-line"></i></a>
               <button id="osint-preview-close" class="osint-btn-ghost" style="width:32px; height:32px;"><i class="ri-close-line"></i></button>
           </div>
        </div>
        <div class="osint-sheet-body" style="flex:1; position:relative; background:#fff; overflow:hidden;">
           <iframe id="osint-preview-frame" src="" style="width:100%; height:100%; border:none; display:block;" sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
           
           <div id="osint-preview-blocked" style="position:absolute; top:0; left:0; width:100%; height:100%; display:none; flex-direction:column; align-items:center; justify-content:center; background:var(--osint-bg); z-index:10; padding:20px; text-align:center;">
              <div style="font-size:48px; color:var(--osint-muted); margin-bottom:16px;"><i class="ri-eye-off-line"></i></div>
              <h3 style="margin:0 0 8px 0; color:var(--osint-ink);">Vista previa no disponible</h3>
              <p style="margin:0 0 24px 0; color:var(--osint-ink-sub); max-width:400px;">
                Este sitio web no permite ser incrustado en otras páginas (política de seguridad X-Frame-Options).
              </p>
              <a href="#" target="_blank" id="osint-preview-blocked-btn" class="osint-btn-animated" style="max-width:200px;">
                 <span class="text">Abrir en nueva pestaña</span>
                 <span class="icon">🡭</span>
              </a>
           </div>

           <div id="osint-preview-loader" style="position:absolute; top:0; left:0; width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:var(--osint-bg); z-index:5;">
              <div class="spinner" style="border: 2px solid var(--osint-border); border-top: 2px solid var(--osint-accent); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite;"></div>
           </div>
        </div>
      </div>
      <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
    `;
    document.body.appendChild(div);
    previewOverlay = div;

    const closeBtn = div.querySelector('#osint-preview-close');
    closeBtn.addEventListener('click', closePreviewModal);
    div.addEventListener('click', (e) => {
        if (e.target === div) closePreviewModal();
    });
    
    // Report block
    const reportBtn = div.querySelector('#osint-preview-report');
    if (reportBtn) {
        reportBtn.addEventListener('click', () => {
             // 1. Show blocked UI
             const frame = div.querySelector('#osint-preview-frame');
             const blockedDiv = div.querySelector('#osint-preview-blocked');
             const loader = div.querySelector('#osint-preview-loader');
             
             if(frame) frame.style.display = 'none';
             if(loader) loader.style.display = 'none';
             if(blockedDiv) blockedDiv.style.display = 'flex';
             
             // 2. Report to backend
             const currentUrl = div.querySelector('#osint-preview-ext').href;
             if (currentUrl && currentUrl !== "#") {
                 reportUrlBlocked(currentUrl);
             }
        });
    }

    // Hide loader on load
    const frame = div.querySelector('#osint-preview-frame');
    frame.addEventListener('load', () => {
        const loader = div.querySelector('#osint-preview-loader');
        if(loader) loader.style.display = 'none';
    });
  }

  function openPreviewModal(url, title, isBlocked = false) {
    createPreviewModal();
    const frame = previewOverlay.querySelector('#osint-preview-frame');
    const extLink = previewOverlay.querySelector('#osint-preview-ext');
    const loader = previewOverlay.querySelector('#osint-preview-loader');
    const titleEl = previewOverlay.querySelector('#osint-preview-title');
    const blockedDiv = previewOverlay.querySelector('#osint-preview-blocked');
    const blockedBtn = previewOverlay.querySelector('#osint-preview-blocked-btn');
    
    if(titleEl) titleEl.textContent = title || "Vista Previa";
    
    extLink.href = url;
    if (blockedBtn) blockedBtn.href = url;

    if (isBlocked) {
        // Show blocked UI
        if (blockedDiv) blockedDiv.style.display = 'flex';
        if (loader) loader.style.display = 'none';
        frame.style.display = 'none';
        frame.src = 'about:blank';
    } else {
        // Show iframe
        if (blockedDiv) blockedDiv.style.display = 'none';
        frame.style.display = 'block';
        if(loader) loader.style.display = 'flex';
        frame.src = url;
    }

    previewOverlay.classList.add('active');
    previewOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closePreviewModal() {
    if(!previewOverlay) return;
    previewOverlay.classList.remove('active');
    previewOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    const frame = previewOverlay.querySelector('#osint-preview-frame');
    frame.src = 'about:blank'; // Stop loading
  }

  /* =========================================================
   * RENDER DECKS
   * ========================================================= */
  function renderActions(t) {
    const isBlocked = t && t.preview_status === 'blocked';
    const stats = t.stats || {};
    const likes = parseInt(stats.likes || 0);
    const favorites = parseInt(stats.favorites || 0);
    const clicks = parseInt(stats.clicks || 0);

    return `
      <div class="osint-actions-wrapper">
        <div class="osint-actions-primary">
            <a href="#" class="osint-btn-animated osint-act-go" target="_blank" rel="noopener">
              <span class="text">Analizar</span>
              <span class="icon">🡭</span>
            </a>

            ${!isBlocked ? `<button class="osint-act-preview" data-title="Vista Previa">
              <i class="ri-eye-line"></i>
            </button>` : ''}

            <div class="osint-share-wrapper">
              <span class="osint-act-share">
                <span class="dashicons dashicons-share"></span>
              </span>
              <div class="osint-share-menu">
                <button class="osint-share-item" data-action="copy">
                  <span class="dashicons dashicons-admin-page"></span> Copiar
                </button>
                <button class="osint-share-item" data-action="linkedin">
                  <span class="dashicons dashicons-linkedin"></span> LinkedIn
                </button>
                <button class="osint-share-item" data-action="whatsapp">
                  <span class="dashicons dashicons-format-chat"></span> WhatsApp
                </button>
                <button class="osint-share-item" data-action="twitter">
                  <span class="dashicons dashicons-twitter"></span> Twitter/X
                </button>
              </div>
            </div>
        </div>

        <div class="osint-actions-secondary">
           <button class="osint-act-like" data-title="Me gusta">
              <i class="ri-heart-line"></i> <span class="count">${likes}</span>
           </button>
           <button class="osint-act-favorite" data-title="Favorito">
              <i class="ri-star-line"></i> <span class="count">${favorites}</span>
           </button>
           <span class="osint-stat-clicks" data-title="Usos">
              <i class="ri-bar-chart-line"></i> <span class="count">${clicks}</span>
           </span>
           <button class="osint-report" data-title="Reportar herramienta">
              <i class="ri-flag-line"></i>
           </button>
        </div>
      </div>
    `;
  }

  function attachActionEvents(card, urlTemplate, tool, detection) {
    const inputVal = (detection && detection.value) ? detection.value : (q.value || "").trim();
    const builtUrl = () =>
      inputVal ? build(urlTemplate, inputVal) : build(urlTemplate, "");
      
    const needsInput = (urlTemplate || "").includes("{input}");
    const hasInput = !!inputVal;
    const disableActions = !urlTemplate || urlTemplate === "#"; 
    const isManualMode = hasInput && !needsInput;

    const actionsWrap = card.querySelector(".osint-actions");
    const goBtn = card.querySelector(".osint-act-go");
    const previewBtn = card.querySelector(".osint-act-preview");
    const shareBtn = card.querySelector(".osint-act-share");
    const shareMenu = card.querySelector(".osint-share-menu");

    if (actionsWrap) {
      actionsWrap.classList.toggle("osint-hidden", disableActions);
    }

    const likeBtn = card.querySelector(".osint-act-like");
    if (likeBtn) {
        likeBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            if (likeBtn.disabled) return;
            likeBtn.disabled = true;
            
            sendEvent("like", { tool_id: tool.id || tool._db_id }).then(res => {
                if(res.ok && res.count !== undefined) {
                    const cnt = likeBtn.querySelector(".count");
                    if(cnt) cnt.textContent = res.count;
                    toast("Te gusta esta herramienta");
                } else {
                    toast("Error al registrar like");
                }
            }).finally(() => {
                likeBtn.disabled = false;
            });
        });
    }

    const favBtn = card.querySelector(".osint-act-favorite");
    if (favBtn) {
        favBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            if (favBtn.disabled) return;
            favBtn.disabled = true;

            sendEvent("favorite", { tool_id: tool.id || tool._db_id }).then(res => {
                if(res.ok && res.count !== undefined) {
                    const cnt = favBtn.querySelector(".count");
                    if(cnt) cnt.textContent = res.count;
                    toast("Añadido a favoritos");
                } else {
                    toast("Error al añadir a favoritos");
                }
            }).finally(() => {
                favBtn.disabled = false;
            });
        });
    }

    if (goBtn) {
      const textSpan = goBtn.querySelector(".text");
      
      if (textSpan) {
        if (hasInput) {
            if (isManualMode) {
                textSpan.textContent = "Uso Manual";
                goBtn.title = "Esta herramienta requiere uso manual. No admite consulta directa.";
            } else {
                textSpan.textContent = `Analizar ${inputVal}`;
                goBtn.title = `Analizar ${inputVal} con esta herramienta`;
            }
        } else {
            textSpan.textContent = "Analizar";
            goBtn.title = "Abrir herramienta";
        }
      }

      const url = builtUrl();
      if (url && url !== "#") {
        goBtn.href = url;
        goBtn.classList.remove("is-disabled");

        goBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          sendEvent("click_tool", {
            tool_id: (tool && tool.id) || "",
            input_type: (detection && detection.type) || "",
            input_value: inputVal,
          });
        });

        if (previewBtn) {
            previewBtn.classList.remove("is-disabled");

            // Check admin status
            if (tool && tool.preview_status === 'blocked') {
                previewBtn.classList.add('is-blocked');
                previewBtn.dataset.blocked = "true";
                previewBtn.title = "Vista previa bloqueada (Admin)";
            } else if (tool && tool.preview_status === 'ok') {
                previewBtn.dataset.verified = "true";
                previewBtn.title = "Vista previa verificada";
            }

            // --- HOVER PREVIEW START ---
            let previewPopup = null;
            let previewTimer = null;

            previewBtn.addEventListener('mouseenter', () => {
                if (previewBtn.classList.contains('is-disabled')) return;
                const finalUrl = goBtn.href;
                
                // Clear any existing timer
                if (previewTimer) clearTimeout(previewTimer);

                previewTimer = setTimeout(() => {
                    // Check if popup already exists in DOM and is linked to this closure
                    if (!previewPopup) {
                        previewPopup = document.createElement('div');
                        previewPopup.className = 'osint-preview-hover-popup';
                        previewPopup.innerHTML = `
                            <div class="osint-preview-loader">Cargando vista previa...</div>
                            <iframe src="${finalUrl}" scrolling="no" frameborder="0" onload="this.previousElementSibling.style.display='none'"></iframe>
                        `;
                        document.body.appendChild(previewPopup);
                    }

                    // Update position
                    const rect = previewBtn.getBoundingClientRect();
                    const popupWidth = 240;
                    const popupHeight = 180;
                    
                    let top = rect.top - popupHeight - 12;
                    let left = rect.left + (rect.width / 2) - (popupWidth / 2);
                    
                    if (left < 10) left = 10;
                    if (left + popupWidth > window.innerWidth) left = window.innerWidth - popupWidth - 10;
                    if (top < 10) top = rect.bottom + 12;
                    
                    previewPopup.style.top = `${top + window.scrollY}px`;
                    previewPopup.style.left = `${left + window.scrollX}px`;
                    
                    requestAnimationFrame(() => previewPopup.classList.add('active'));
                }, 600); 
            });

            previewBtn.addEventListener('mouseleave', () => {
                if (previewTimer) clearTimeout(previewTimer);
                if (previewPopup) {
                    previewPopup.classList.remove('active');
                    // Removed destruction logic for caching
                }
            });
            // --- HOVER PREVIEW END ---

            previewBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                e.preventDefault(); // Prevent bubbling
                if (previewBtn.classList.contains('is-disabled')) return;
                const isBlocked = previewBtn.dataset.blocked === "true";
                openPreviewModal(url, (tool && tool.name) || "Vista Previa", isBlocked);
            });
        }
      } else {
        goBtn.href = "#";
        goBtn.classList.add("is-disabled");
        goBtn.addEventListener("click", (e) => e.preventDefault());

        if (previewBtn) {
            previewBtn.classList.add("is-disabled");
        }
      }
    }

    if (shareBtn && shareMenu) {
      shareBtn.classList.toggle("is-disabled", disableActions);
      if (disableActions) shareMenu.classList.remove("active");
      shareBtn.addEventListener("click", (e) => {
        if (disableActions) return;
        e.stopPropagation();
        shareMenu.classList.toggle("active");
      });

      shareMenu.querySelectorAll(".osint-share-item").forEach((item) => {
        item.addEventListener("click", (e) => {
          if (disableActions) return;
          e.stopPropagation();
          const u = builtUrl();
          const act = item.dataset.action;
          if (act === "copy" && navigator.clipboard) {
            navigator.clipboard.writeText(u || "");
          }
          if (act === "linkedin") {
            window.open(
              `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(u)}`
            );
          }
          if (act === "whatsapp") {
            window.open(
              `https://api.whatsapp.com/send?text=${encodeURIComponent(u)}`
            );
          }
          if (act === "twitter") {
            window.open(
              `https://twitter.com/intent/tweet?url=${encodeURIComponent(u)}`
            );
          }
          toast("✅ Acción realizada");
          shareMenu.classList.remove("active");
        });
      });

      document.addEventListener("click", (e) => {
        if (!card.contains(e.target)) {
          shareMenu.classList.remove("active");
        }
      });
    }

    const reportBtn = card.querySelector(".osint-report");
    if (reportBtn) {
      reportBtn.classList.toggle("is-disabled", disableActions);
      reportBtn.classList.toggle("osint-hidden", disableActions);
      reportBtn.addEventListener("click", (e) => {
        if (disableActions) return;
        e.stopPropagation();
        reportBtn.disabled = true;
        sendEvent("report_tool", {
          tool_id: (tool && tool.id) || "",
          input_type: (detection && detection.type) || "",
          input_value: inputVal,
        }).then((res) => {
          if (res && res.ok) {
            toast("Herramienta reportada correctamente.");
          } else if (res && res.msg) {
            toast(res.msg);
          } else if (res && res.data && res.data.msg) {
            toast(res.data.msg);
          } else {
            toast("Solo puedes reportar esta herramienta una vez al dia.");
          }
        }).finally(() => {
          reportBtn.classList.add("sent");
        });
      });
    }
  }

  function updateCounter() {
    if (!counterRef) return;
    const total = filteredCache.length;
    const shown = Math.min(renderedCount, total);
    counterRef.textContent = `${shown} visibles de ${total}`;
  }

  function renderNextChunk() {
    if (!filteredCache.length || renderedCount >= filteredCache.length) return;

    const slice = filteredCache.slice(renderedCount, renderedCount + chunkSize);
    renderedCount += slice.length;

    const appended = [];
    slice.forEach((t, i) => {
      const deck = renderDeckElement(t);
      if (deck) {
        deck.classList.add("osint-animate-in");
        deck.style.animationDelay = `${i * 0.1}s`;
        grid.appendChild(deck);
        appended.push(deck);
      }
    });

    updateCounter();
  }

  function renderDecks(list, detection, ignoreFilters = false) {
    const oldPagination = document.getElementById(`${uid}-pagination`);
    if (oldPagination) oldPagination.remove();
    grid.innerHTML = "";

    filteredCache = (filterPopularOnly && !ignoreFilters)
      ? (list || []).filter((t) => (t.meta && t.meta.badges || t.badges || []).join(" ").includes("Popular"))
      : (list || []);

    currentDetection = detection || detectRichInput(q.value || "");
    renderedCount = 0;

    if (!filteredCache.length) {
      grid.innerHTML =
        '<p style="color:var(--osint-ink-sub);text-align:center;width:100%;">No se encontraron herramientas.</p>';
      return;
    }

    renderNextChunk();
    
    // Check iframes for the first chunk
    const firstChunk = filteredCache.slice(0, chunkSize);
    checkIframeAvailability(firstChunk);
  }

  // Infinite scroll
  window.addEventListener("scroll", () => {
    if (!filteredCache.length) return;
    
    const scrollHeight = document.documentElement.scrollHeight || document.body.scrollHeight;
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const clientHeight = window.innerHeight || document.documentElement.clientHeight;
    
    const nearBottom = scrollTop + clientHeight >= scrollHeight - 400;
    
    if (nearBottom) {
      const start = renderedCount;
      renderNextChunk();
      // Check newly rendered items
      const newItems = filteredCache.slice(start, renderedCount);
      checkIframeAvailability(newItems);
    }
  });

  /* =========================================================
   * IFRAME CHECKER & REPORTING
   * ========================================================= */
  function reportUrlBlocked(url) {
      if (!ajaxCfg.url) return;
      
      // Update UI immediately for all buttons with this URL
      const links = grid.querySelectorAll(`.osint-act-go[href="${url.replace(/"/g, '\\"')}"]`);
      links.forEach(link => {
          const card = link.closest('.osint-card');
          if (card) {
              const previewBtn = card.querySelector('.osint-act-preview');
              if (previewBtn) {
                  previewBtn.classList.remove('is-disabled');
                  previewBtn.classList.add('is-blocked');
                  previewBtn.setAttribute('title', 'Vista previa bloqueada (Click para abrir externo)');
                  previewBtn.dataset.blocked = "true";
              }
          }
      });

      // Send report
      const data = new URLSearchParams();
      data.append("action", "osint_deck_report_block");
      if (ajaxCfg.nonce) data.append("nonce", ajaxCfg.nonce);
      data.append("url", url);

      fetch(ajaxCfg.url, {
          method: "POST",
          body: data
      }).catch(err => console.error("Report block failed", err));
  }

  function checkIframeAvailability(toolsToCheck) {
    if (!toolsToCheck || !toolsToCheck.length) return;
    if (!ajaxCfg.url) return;

    // Filter out tools that already have a decisive status
    const toolsToQuery = toolsToCheck.filter(t => {
        return !t.preview_status || t.preview_status === 'unaudited';
    });
    
    if (!toolsToQuery.length) return;

    // Collect URLs to check
    const urlMap = {}; // url -> [cardElements]
    const d = detectRichInput(q.value || "");
    const inputVal = (d && d.value) ? d.value : (q.value || "").trim();

    toolsToQuery.forEach(t => {
        const cards = Array.isArray(t.cards) ? t.cards : [];
        if (!cards.length) return;
        
        // Find the primary card for this tool instance
        let primaryCard = pickPrimaryCard(cards, d);
        if (currentCat) {
            const byCat = cards.find(c => (c.category || "").toLowerCase() === currentCat.toLowerCase());
            if (byCat) primaryCard = byCat;
        }

        const urlTemplate = primaryCard.url || t.url || "#";
        if (!urlTemplate || urlTemplate === "#") return;

        // Build the actual URL that would be previewed
        const builtUrl = inputVal ? build(urlTemplate, inputVal) : build(urlTemplate, "");
        
        // Find the DOM element for this tool/card
        // This is tricky because we don't have direct ref here easily unless we stored it.
        // But we can find it by looking for the link with this href in the grid.
        // A better way is to query selector by tool name or some ID.
        // For now, let's look for .osint-act-go[href="builtUrl"]'s parent card.
        // Actually, we can just find all preview buttons that are currently disabled/waiting?
        
        if (!urlMap[builtUrl]) urlMap[builtUrl] = [];
        
        // We need to find the specific DOM elements corresponding to this URL.
        // Since builtUrl might be common, we add to array.
        // We can search the grid for links matching this href.
        const links = grid.querySelectorAll(`.osint-act-go[href="${builtUrl.replace(/"/g, '\\"')}"]`);
        links.forEach(link => {
            const card = link.closest('.osint-card');
            if (card) {
                const previewBtn = card.querySelector('.osint-act-preview');
                if (previewBtn) {
                    urlMap[builtUrl].push(previewBtn);
                    // Set initial state? Maybe a loading spinner or just hidden.
                    // Currently they are visible by default from renderDeckElement logic if URL is valid.
                    // Let's hide them initially or show a spinner if we wanted to be fancy.
                    // For now, let's just let them be and hide them if check fails.
                }
            }
        });
    });

    const uniqueUrls = Object.keys(urlMap);
    if (!uniqueUrls.length) return;

    // Send batch request
    const data = new URLSearchParams();
    data.append("action", "osint_deck_check_iframe");
    if (ajaxCfg.nonce) data.append("nonce", ajaxCfg.nonce);
    data.append("urls", JSON.stringify(uniqueUrls));

    fetch(ajaxCfg.url, {
      method: "POST",
      body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data) {
            Object.entries(res.data).forEach(([url, canEmbed]) => {
                const btns = urlMap[url];
                if (btns) {
                    btns.forEach(btn => {
                        if (!canEmbed) {
                            btn.classList.remove('is-disabled');
                            btn.classList.add('is-blocked');
                            btn.setAttribute('title', 'Vista previa bloqueada (Click para abrir externo)');
                            btn.dataset.blocked = "true";
                        } else {
                            btn.classList.remove('is-disabled');
                            btn.classList.remove('is-blocked');
                            btn.setAttribute('title', 'Vista Previa');
                            btn.dataset.blocked = "false";
                        }
                    });
                }
            });
        }
    })
    .catch(err => console.error("Iframe check failed", err));
  }

  /* =========================================================
   * MODAL (SHEET)
   * ========================================================= */
  function openModal(tool) {
    sheetTitle.textContent = tool.name || "";
    sheetSub.textContent =
      (tool.category || "") +
      (tool.info && tool.info.acceso ? " • " + tool.info.acceso : "");

    if (sheetDesc) {
      sheetDesc.textContent = tool.desc || "";
    }

    if (sheetMeta) {
      const info =
        tool.info && typeof tool.info === "object"
          ? `
        <ul class="osint-kv">
          ${tool.info.tipo
            ? `<li><span>Tipo:</span>${esc(tool.info.tipo)}</li>`
            : ""
          }
          ${tool.info.licencia
            ? `<li><span>Licencia:</span>${esc(
              tool.info.licencia
            )}</li>`
            : ""
          }
          ${tool.info.acceso
            ? `<li><span>Acceso:</span>${esc(tool.info.acceso)}</li>`
            : ""
          }
        </ul>
      `
          : "";

      const tags =
        Array.isArray(tool.tags) && tool.tags.length
          ? `<div class="osint-tags">
              ${tool.tags
            .map(
              (x) =>
                `<span class="osint-chip osd-tag" data-tag="${esc(
                  x
                )}">${esc(x)}</span>`
            )
            .join("")}
             </div>`
          : "";

      sheetMeta.innerHTML = info + tags;

      sheetMeta.querySelectorAll(".osd-tag").forEach((tagEl) => {
        tagEl.addEventListener("click", (e) => {
          e.stopPropagation();
          const tg = tagEl.dataset.tag || "";
          setFilter("tag", tg, false);
          closeModal();
        });
      });
    }

    sheetGrid.innerHTML = "";

    const primary =
      (tool.cards || [])[0] ||
      tool.primary ||
      {};
    const cards = tool.cards || [];
    const stack = [primary, ...cards.filter((c) => c !== primary)].slice(
      0,
      5
    );

    stack.forEach((c, i) => {
      const card = document.createElement("div");
      card.className = "osint-sheet-card";
      card.style.zIndex = String(10 + i);

      const tagsHTML =
        Array.isArray(c.tags) && c.tags.length
          ? `<div class="osint-card-tags">
              ${c.tags
            .map(
              (x) =>
                `<span class="osint-chip osd-tag" data-tag="${esc(
                  x
                )}">${esc(x)}</span>`
            )
            .join("")}
            </div>`
          : "";

      card.innerHTML = `
        <div class="osint-mtitle">${esc(c.title || "Acción")}</div>
        <div class="osint-mdesc">${esc(c.desc || "")}</div>
        ${tagsHTML}
        ${renderActions()}
      `;

      card.querySelectorAll(".osd-tag").forEach((tagEl) => {
        tagEl.addEventListener("click", (e) => {
          e.stopPropagation();
          const tg = tagEl.dataset.tag || "";
          setFilter("tag", tg, false);
          closeModal();
        });
      });

      const url = c.url || primary.url || "#";
      const det = detectRichInput(q.value || "");
      attachActionEvents(card, url, tool, det);
      sheetGrid.appendChild(card);
    });

    overlay.classList.add("active");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    sheet.style.opacity = "1";
    sheet.style.transform = "translateY(0)";
    lastFocused = document.activeElement;
    const focusables = sheet.querySelectorAll(focusableSelector);
    if (focusables.length) {
      focusables[0].focus();
    }
  }

  function closeModal() {
    if (!overlay.classList.contains("active")) return;
    overlay.classList.remove("active");
    overlay.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
    sheet.style.opacity = "";
    sheet.style.transform = "";
    if (lastFocused && typeof lastFocused.focus === "function") {
      lastFocused.focus();
    }
  }

  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) closeModal();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeModal();
    if (e.key === "Tab" && overlay.classList.contains("active")) {
      const focusables = Array.from(sheet.querySelectorAll(focusableSelector)).filter(
        (el) => !el.hasAttribute("disabled") && el.offsetParent !== null
      );
      if (!focusables.length) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else if (document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  function updateDetectedMessage(msg) {
    const el = document.getElementById(`${uid}-detected`);
    if (!el) return;
    el.textContent = msg || "";
  }

  /* =========================================================
   * INPUT & SEARCH INIT
   * ========================================================= */
  function debounce(func, wait) {
    let timeout;
    return function (...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), wait);
    };
  }

  function searchBackend(query) {
    if (!ajaxCfg.url || !ajaxCfg.nonce) {
        console.warn("OSINT Deck: Missing AJAX config", ajaxCfg);
        return Promise.resolve({ success: false });
    }
    const data = new URLSearchParams();
    data.append("action", "osint_deck_search");
    data.append("nonce", ajaxCfg.nonce);
    data.append("query", query);

    return fetch(ajaxCfg.url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    })
      .then(async (r) => {
        const payload = await r.json().catch(() => ({}));
        return payload;
      })
      .catch(() => ({ success: false }));
  }

  /* =========================================================
   * DETECCIÓN VISUAL (TOOLTIP PINNABLE)
   * ========================================================= */
  const detectedEl = document.getElementById(`${uid}-detected`);
  let detectedTimer;

  if (detectedEl) {
    detectedEl.addEventListener("click", () => {
       const wasPinned = detectedEl.classList.contains("is-pinned");
       if (wasPinned) {
         detectedEl.classList.remove("is-pinned");
         // Resume auto-hide
         clearTimeout(detectedTimer);
         detectedTimer = setTimeout(() => {
           detectedEl.classList.remove("active");
         }, 3000);
       } else {
         detectedEl.classList.add("is-pinned");
         clearTimeout(detectedTimer);
       }
    });
  }

  function showDetectedMessage(text) {
    if (!detectedEl) return;
    
    if (!text) {
      detectedEl.classList.remove("active");
      return;
    }
    
    // Update content
    detectedEl.innerHTML = `<span>${text}</span>`;
    detectedEl.classList.add("active");
    
    // If pinned, stay visible
    if (detectedEl.classList.contains("is-pinned")) return;
    
    // Auto-hide
    clearTimeout(detectedTimer);
    detectedTimer = setTimeout(() => {
      if (!detectedEl.classList.contains("is-pinned")) {
        detectedEl.classList.remove("active");
      }
    }, 3000);
  }

  async function onInput() {
    const val = (q.value || "").trim();
    const d = detectRichInput(val);
    
    // Usar nuevo tooltip dedicado
    showDetectedMessage(d.msg);

    applyFilters();
    toggleSmart();

    const isAmbiguous = d.type === "none" || 
                        d.type === "generic" || 
                        (d.type === "keyword" && (!d.intent || ["help", "greeting", "toxic"].includes(d.intent))) || 
                        d.type === "fullname";

    if (val.length > 2 && isAmbiguous) {
      try {
        const res = await searchBackend(val);
        if (res.success && res.data && res.data.results && res.data.results.length > 0) {
          const backendTools = res.data.results.map(item => {
            const t = { ...item.tool };
            t.cards = item.cards;
            return t;
          });
          renderDecks(backendTools, d, true);
        }
      } catch (e) {
        console.error(e);
      }
    }
  }

  // Botón Populares (Seleccionado del DOM)
  const popularToggle = document.getElementById(`${uid}-popular-btn`);
  if (popularToggle) {
    popularToggle.addEventListener("click", () => {
      filterPopularOnly = !filterPopularOnly;
      popularToggle.classList.toggle("active", filterPopularOnly);
      const icon = popularToggle.querySelector("i");
      if (icon) {
        icon.className = filterPopularOnly ? "ri-star-fill" : "ri-star-line";
      }
      applyFilters();
    });
  }

  // Botón Limpiar (Seleccionado del DOM)
  const clearBtn = document.getElementById(`${uid}-clear-btn`);
  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      setFilter("", "", true);
    });
  }

  // Inicializar menús
  populateTypeMenu();
  populateAccessMenu();
  populateLicenseMenu();
  populateCategoryMenu();

  // Render inicial
  renderDecks(tools, detectRichInput(q.value || ""));
  q.addEventListener("input", debounce(onInput, 500));
  toggleSmart();
}

document.addEventListener("DOMContentLoaded", function () {
  console.log("OSINT Deck v0.1.1 loaded (Multi-instance supported)");
  initToast();
  document.querySelectorAll(".osint-wrap[id]").forEach(initOsintDeck);
});
