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

/* =========================================================
 * NAIVE BAYES CLASSIFIER
 * ========================================================= */
class NaiveBayesClassifier {
  constructor() {
    this.tokenizer = (text) => text.toLowerCase().replace(/[^\w\s]/g, '').split(/\s+/).filter(x => x.length > 2);
    this.wordCounts = {};
    this.classCounts = {};
    this.vocab = new Set();
    this.totalDocuments = 0;
  }

  learn(text, category) {
    const tokens = this.tokenizer(text);
    if (!this.classCounts[category]) {
      this.classCounts[category] = 0;
      this.wordCounts[category] = {};
    }
    this.classCounts[category]++;
    this.totalDocuments++;

    tokens.forEach(token => {
      this.vocab.add(token);
      this.wordCounts[category][token] = (this.wordCounts[category][token] || 0) + 1;
    });
  }

  categorize(text) {
    const tokens = this.tokenizer(text);
    let maxProb = -Infinity;
    let bestCategory = null;

    const categories = Object.keys(this.classCounts);
    categories.forEach(category => {
      const classProb = Math.log(this.classCounts[category] / this.totalDocuments);
      let logProb = classProb;

      tokens.forEach(token => {
        const wordCount = this.wordCounts[category][token] || 0;
        const totalWordsInClass = Object.values(this.wordCounts[category]).reduce((a, b) => a + b, 0);
        const vocabSize = this.vocab.size;
        const tokenProb = Math.log((wordCount + 1) / (totalWordsInClass + vocabSize));
        logProb += tokenProb;
      });

      if (logProb > maxProb) {
        maxProb = logProb;
        bestCategory = category;
      }
    });

    return bestCategory;
  }
}

let nbClassifier = new NaiveBayesClassifier();
let nbReady = false;

if (typeof osintDeckAjax !== 'undefined' && osintDeckAjax.trainingDataUrl) {
    fetch(osintDeckAjax.trainingDataUrl)
        .then(r => r.json())
        .then(data => {
            if (Array.isArray(data)) {
                data.forEach(item => {
                    if (item.text && item.category) {
                        nbClassifier.learn(item.text, item.category);
                    }
                });
                nbReady = true;
            }
        })
        .catch(err => console.error("OSINT Deck: Failed to load training data", err));
}

/* =========================================================
 * CLOUDFLARE TURNSTILE (token nuevo por petición AJAX)
 * ========================================================= */
(function () {
  let apiPromise = null;
  let gate = Promise.resolve();
  let hiddenHost = null;
  let hiddenWidgetId = null;
  let hiddenPending = null;
  let hiddenHasRun = false;

  function tsCfg() {
    const a = typeof osintDeckAjax !== "undefined" ? osintDeckAjax : {};
    const t = a.turnstile;
    if (t && t.enabled && t.siteKey) return t;
    return null;
  }

  function loadTurnstileApi() {
    if (apiPromise) return apiPromise;
    if (window.turnstile && typeof window.turnstile.render === "function") {
      apiPromise = Promise.resolve();
      return apiPromise;
    }
    apiPromise = new Promise((resolve, reject) => {
      const s = document.createElement("script");
      s.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
      s.async = true;
      s.defer = true;
      s.dataset.osdTurnstileApi = "1";
      s.onload = () => resolve();
      s.onerror = () => reject(new Error("turnstile_load"));
      document.head.appendChild(s);
    });
    return apiPromise;
  }

  function ensureTurnstileHiddenWidget(siteKey) {
    if (hiddenWidgetId !== null) return;
    if (!hiddenHost) {
      hiddenHost = document.createElement("div");
      hiddenHost.className = "osd-turnstile-host osd-turnstile-host--hidden";
      hiddenHost.setAttribute("aria-hidden", "true");
      document.body.appendChild(hiddenHost);
    }
    hiddenWidgetId = window.turnstile.render(hiddenHost, {
      sitekey: siteKey,
      size: "invisible",
      appearance: "interaction-only",
      callback(token) {
        if (!hiddenPending) return;
        const p = hiddenPending;
        hiddenPending = null;
        p.resolve(token);
      },
      "error-callback"() {
        if (!hiddenPending) return;
        const p = hiddenPending;
        hiddenPending = null;
        p.reject(new Error("turnstile_hidden_error"));
      },
      "expired-callback"() {
        if (!hiddenPending) return;
        const p = hiddenPending;
        hiddenPending = null;
        p.reject(new Error("turnstile_expired"));
      },
    });
  }

  function runHiddenTurnstile(siteKey) {
    return new Promise((resolve, reject) => {
      hiddenPending = { resolve, reject };
      try {
        ensureTurnstileHiddenWidget(siteKey);
        if (hiddenHasRun) {
          window.turnstile.reset(hiddenWidgetId);
        }
        window.turnstile.execute(hiddenWidgetId);
        hiddenHasRun = true;
      } catch (e) {
        hiddenPending = null;
        reject(e);
      }
    });
  }

  function openTurnstileModal(siteKey, copy) {
    return new Promise((resolve, reject) => {
      let overlay = document.querySelector(".osd-turnstile-modal");
      if (!overlay) {
        overlay = document.createElement("div");
        overlay.className = "osd-turnstile-modal";
        overlay.setAttribute("role", "dialog");
        overlay.setAttribute("aria-modal", "true");
        overlay.innerHTML =
          '<div class="osd-turnstile-modal__backdrop" tabindex="-1"></div>' +
          '<div class="osd-turnstile-modal__panel">' +
          '<h3 class="osd-turnstile-modal__title"></h3>' +
          '<p class="osd-turnstile-modal__intro"></p>' +
          '<div class="osd-turnstile-modal__widget"></div>' +
          '<button type="button" class="osd-turnstile-modal__close"></button>' +
          "</div>";
        document.body.appendChild(overlay);
      }

      const titleEl = overlay.querySelector(".osd-turnstile-modal__title");
      const introEl = overlay.querySelector(".osd-turnstile-modal__intro");
      const mount = overlay.querySelector(".osd-turnstile-modal__widget");
      const closeBtn = overlay.querySelector(".osd-turnstile-modal__close");
      const backdrop = overlay.querySelector(".osd-turnstile-modal__backdrop");

      titleEl.textContent = copy.modalTitle || "Verificación";
      introEl.textContent = copy.modalIntro || "";
      closeBtn.textContent = copy.close || "Cerrar";

      let modalWidgetId = null;
      let onKey = null;

      const cleanup = () => {
        if (onKey) {
          document.removeEventListener("keydown", onKey);
          onKey = null;
        }
        if (modalWidgetId !== null) {
          try {
            window.turnstile.remove(modalWidgetId);
          } catch (err) {
            /* ignore */
          }
          modalWidgetId = null;
        }
        mount.innerHTML = "";
        overlay.classList.remove("osd-turnstile-modal--open");
        document.body.style.overflow = "";
      };

      const finishOk = (token) => {
        cleanup();
        resolve(token);
      };
      const finishCancel = () => {
        cleanup();
        reject(new Error("turnstile_cancel"));
      };

      closeBtn.onclick = () => finishCancel();
      backdrop.onclick = () => finishCancel();

      onKey = (ev) => {
        if (ev.key === "Escape") {
          ev.preventDefault();
          finishCancel();
        }
      };
      document.addEventListener("keydown", onKey);

      overlay.classList.add("osd-turnstile-modal--open");
      document.body.style.overflow = "hidden";

      try {
        modalWidgetId = window.turnstile.render(mount, {
          sitekey: siteKey,
          appearance: "interaction-only",
          callback(token) {
            finishOk(token);
          },
          "error-callback"() {
            finishCancel();
          },
          "expired-callback"() {
            finishCancel();
          },
        });
      } catch (err) {
        cleanup();
        reject(err);
        return;
      }

      try {
        closeBtn.focus();
      } catch (e) {
        /* ignore */
      }
    });
  }

  async function acquireTurnstileToken() {
    const c = tsCfg();
    if (!c) return "";
    try {
      await loadTurnstileApi();
    } catch (e) {
      if (c.loadFailed) window.alert(c.loadFailed);
      throw e;
    }
    if (!window.turnstile || typeof window.turnstile.render !== "function") {
      if (c.loadFailed) window.alert(c.loadFailed);
      throw new Error("turnstile_api_missing");
    }
    const copy = {
      modalTitle: c.modalTitle,
      modalIntro: c.modalIntro,
      close: c.close,
      loadFailed: c.loadFailed,
    };
    let timeoutId;
    try {
      const token = await new Promise((resolve, reject) => {
        timeoutId = window.setTimeout(() => {
          hiddenPending = null;
          reject(new Error("turnstile_timeout"));
        }, 14000);
        runHiddenTurnstile(c.siteKey).then(
          (t) => {
            if (timeoutId) window.clearTimeout(timeoutId);
            resolve(t);
          },
          (e) => {
            if (timeoutId) window.clearTimeout(timeoutId);
            reject(e);
          }
        );
      });
      if (token) return String(token);
    } catch (e) {
      if (timeoutId) window.clearTimeout(timeoutId);
    }
    return openTurnstileModal(c.siteKey, copy);
  }

  window.osintDeckTurnstileAppendToParams = function (params) {
    const c = tsCfg();
    if (!c || !params) return Promise.resolve();
    const op = gate.then(() =>
      acquireTurnstileToken()
        .then((token) => {
          if (token) params.append("cf_turnstile_response", token);
        })
        .catch(() => {})
    );
    gate = op.catch(() => {});
    return op;
  };
})();

// TLD Validation - Extended List
const COMMON_TLDS = new Set([
  "com", "org", "net", "edu", "gov", "mil", "int", "arpa", "biz", "info", "name", "pro", "aero", "coop", "museum", "mobi", "asia", "tel", "travel", "jobs", "cat", "io", "co", "ai", "app", "dev", "tech", "cloud", "xyz", "site", "online", "store", "shop", "club", "space", "fun", "me", "tv", "cc", "ws", "bz", "nu", "us", "uk", "ca", "au", "de", "fr", "jp", "cn", "ru", "br", "in", "it", "nl", "es", "se", "no", "fi", "dk", "pl", "ch", "be", "at", "cz", "hu", "ro", "gr", "pt", "ie", "il", "za", "mx", "ar", "cl", "co", "pe", "ve", "ec", "bo", "uy", "py", "cr", "pa", "do", "pr", "cu", "gt", "sv", "hn", "ni", "kr", "tw", "hk", "sg", "my", "th", "id", "ph", "vn", "pk", "bd", "tr", "ir", "sa", "ae", "qa", "kw", "om", "bh", "lb", "jo", "eg", "ma", "dz", "tn", "ly", "sd", "ke", "ng", "gh", "tz", "ug", "et", "nz",
  "eu", "asia", "global", "link", "click", "website", "news", "blog", "guru", "photography", "tips", "today", "agency", "center", "digital", "studio", "life", "world", "solutions", "services", "media", "design", "network", "company", "group", "team", "guide", "money", "cash", "business", "market", "trade", "sale", "legal", "law", "attorney", "doctor", "dental", "clinic", "health", "fitness", "care", "foundation", "charity", "school", "university", "college", "academy", "education", "courses", "training", "coach", "fan", "wiki", "forum", "chat", "game", "games", "play", "win", "bet", "casino", "poker", "video", "movie", "music", "radio", "audio", "band", "show", "theater", "art", "gallery", "style", "fashion", "beauty", "salon", "spa", "hair", "diet", "yoga", "run", "bike", "car", "cars", "auto", "parts", "repair", "taxi", "cab", "limo", "bus", "flight", "fly", "plane", "hotel", "hostel", "motel", "camp", "cruise", "tour", "vacation", "holiday", "trip", "voyage", "map", "earth", "city", "town", "village", "house", "home", "apt", "condo", "estate", "land", "farm", "garden", "flower", "florist", "tree", "forest", "park", "green", "bio", "eco", "solar", "energy", "power", "oil", "gas", "water", "ice", "snow", "weather", "sun", "moon", "star", "sky", "planet", "universe", "gal", "systems", "software", "hardware", "computer", "web", "internet", "host", "hosting", "server", "domain", "email", "support", "help", "faq", "feedback", "review", "search", "find", "login", "register", "signin", "signup", "download", "upload", "stream", "watch", "listen", "read", "learn", "teach", "book", "books", "kindle", "library", "page", "pages", "paper", "papers", "news", "report", "daily", "weekly", "monthly", "year", "time", "date", "now", "soon", "later", "never", "always", "forever", "love", "hate", "like", "dislike", "friend", "friends", "family", "baby", "kid", "kids", "teen", "adult", "man", "woman", "boy", "girl", "gay", "lesbian", "trans", "sex", "xxx", "porn", "adult", "fetish", "dating", "meet", "match", "single", "couple", "wedding", "marriage", "divorce", "lawyer"
]);

function isValidTLD(tld) {
    // Basic heuristic: 2+ chars, no numbers, OR in the common list
    // The user requested IANA TLD validation. Since we cannot embed the full dynamic list,
    // we use a large common list + a permissive heuristic for unknown new gTLDs.
    const t = tld.toLowerCase();
    return COMMON_TLDS.has(t) || (t.length >= 2 && !/\d/.test(t) && /^[a-z]+$/.test(t)); 
}

// Detección extendida accesible globalmente (coherencia: node scripts/verify-all-intents.mjs)
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
  geo: ["geo", "gps", "coordenadas", "location", "map", "geolocation"],
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
  toxic: ["toxic", "abuso", "moderacion", "moderación"],
  crisis: ["help", "ayuda", "soporte"],
  community: ["community", "deck", "ideas", "sugerencias"],
  easter_egg: ["help", "ayuda", "soporte"],
};

const KEYWORD_INTENTS = [
    { key: "toxic", words: ["puto", "mierda", "idiota", "imbecil", "estupido", "basura", "inutil", "maldito", "cabron", "verga", "pene", "sexo", "porno", "xxx", "concha", "pajeros", "chupa"], intent: "toxic", msg: "Lenguaje ofensivo detectado." },
    /** Desambigua con ayuda (“ayuda mental”, “salud mental”) antes de la fila help; el resto lo refuerza Naive Bayes (training crisis). */
    { key: "crisis", words: ["suicidio", "matarme", "morir", "depresion", "depresión", "ansiedad", "salud mental", "ayuda mental", "quiero morir", "no quiero vivir"], intent: "crisis", msg: "Si necesitas apoyo emocional, por favor busca ayuda profesional o llama a una línea de emergencia." },
    {
        key: "community",
        words: [
            "sugerir herramienta",
            "sugerencia de herramienta",
            "proponer herramienta",
            "herramienta que falta",
            "agregar herramienta",
            "falta en el deck",
            "crecer el deck",
            "sugerencias para el deck",
            "propongo una herramienta",
            "nueva herramienta para el deck",
            "feature request",
            "pedido de funcion",
            "mejora del deck",
            "ideas para osint deck",
        ],
        intent: "community",
        msg: "Podés proponer herramientas o ideas en la comunidad.",
    },
    { key: "geo", words: ["geo", "geolocaliz", "gps", "coordenadas", "mapa", "location", "ubicacion", "ubicación", "satelite", "satélite"], intent: "geo", msg: "He detectado palabras clave de geolocalización." },
    { key: "username", words: ["user", "usuario", "perfil", "username", "nickname", "apodo"], intent: "username", msg: "He detectado palabras clave de búsqueda de usuarios." },
    { key: "email", words: ["correo", "email", "mail", "gmail", "hotmail", "outlook"], intent: "email", msg: "He detectado palabras clave de correo electrónico." },
    { key: "domain", words: ["dominio", "dominios", "domain", "dns", "whois", "subdominio", "nameserver"], intent: "domain", msg: "He detectado palabras clave de dominios." },
    { key: "ipv6", words: ["ipv6"], intent: "ipv6", msg: "He detectado palabras clave sobre IPv6." },
    { key: "ipv4", words: ["ipv4", "direccion ip", "dirección ip", "direccion de ip"], intent: "ipv4", msg: "He detectado palabras clave de direcciones IP." },
    { key: "leak", words: ["leak", "breach", "filtr", "dump", "database", "passwords"], intent: "leaks", msg: "He detectado palabras clave relacionadas con leaks o filtraciones." },
    { key: "reputation", words: ["reput", "blacklist", "spam", "score", "risk"], intent: "reputation", msg: "He detectado intención de reputación / listas negras." },
    { key: "vuln", words: ["vuln", "cve", "bug", "exploit", "poc", "security"], intent: "vuln", msg: "He detectado palabras clave de vulnerabilidades." },
    { key: "fraud", words: ["fraud", "scam", "fraude", "tarjeta", "carding", "bin", "cc"], intent: "fraud", msg: "He detectado contexto de fraude/finanzas." },
    { key: "help", words: ["ayuda", "necesito", "help", "soporte", "asistencia", "instrucciones", "como usar", "no entiendo", "no comprendo", "no lo entiendo", "no me queda claro", "como funciona", "no se como", "no sé como", "no entendi", "no entendí", "explicame", "explícame"], intent: "help", msg: "He detectado una solicitud de ayuda. Te muestro recursos disponibles." },
    { key: "greeting", words: ["hola", "buenos dias", "buenas tardes", "buenas noches", "que tal", "como estas", "saludos", "buen dia", "hey"], intent: "greeting", msg: "¡Hola! Estoy aquí para ayudarte con tus investigaciones OSINT." },
];

/** Palabras clave muy cortas que son subcadena de términos comunes (p. ej. "cc" en "deck"). */
const KEYWORD_BOUNDARY_SHORT = new Set(["cc", "bin", "poc", "asn"]);

function keywordMatchesInPhrase(phraseLower, w) {
  const needle = String(w || "").toLowerCase();
  if (!needle) return false;
  if (KEYWORD_BOUNDARY_SHORT.has(needle)) {
    const esc = needle.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const re = new RegExp(`(?:^|[^a-z0-9áéíóúüñ])${esc}(?:[^a-z0-9áéíóúüñ]|$)`, "i");
    return re.test(phraseLower);
  }
  return phraseLower.includes(needle);
}

function keywordIntentFromText(v) {
  const tokens = v.split(/[\s,.;!?]+/);

  for (const item of KEYWORD_INTENTS) {
    if (item.words.some((w) => keywordMatchesInPhrase(v, w))) return item;

    for (const w of item.words) {
      let tolerance = 1;
      if (w.length < 4) tolerance = 0;
      else if (w.length > 6) tolerance = 2;

      if (tolerance === 0) continue;

      for (const token of tokens) {
        if (KEYWORD_BOUNDARY_SHORT.has(String(w).toLowerCase())) continue;
        if (Math.abs(token.length - w.length) > tolerance) continue;

        if (levenshtein(token, w) <= tolerance) {
          console.log(`[OSINT Deck] Fuzzy match: "${token}" ~ "${w}" (dist: ${levenshtein(token, w)})`);
          return item;
        }
      }
    }
  }
  return null;
}

function detectIntent(value) {
  const v = (value || "").toLowerCase().trim();

  if (v === "hola p") {
      return { intent: "easter_egg", msg: "hoy no hay una columna" };
  }

  const kw = keywordIntentFromText(v);
  if (kw) return kw;

  if (nbReady && v.length > 2) {
      const category = nbClassifier.categorize(v);
      if (category) {
          if (category === "greeting") {
             return { intent: "greeting", msg: "¡Hola! Estoy aquí para ayudarte." };
          }
          if (category === "help") {
             const helpIcon = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-left: 4px;"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
             return { intent: "help", msg: "Puedes escribir 'ayuda' o pulsar el icono " + helpIcon + " para ver las opciones." };
          }
          if (category === "community") {
             return { intent: "community", msg: "Podés proponer herramientas o ideas en la comunidad." };
          }
          if (category === "crisis") {
             return { intent: "crisis", msg: "Si necesitas apoyo emocional, por favor busca ayuda profesional o llama a una línea de emergencia." };
          }
          if (category === "toxic") {
             return { intent: "toxic", msg: "Lenguaje no permitido detectado." };
          }
          if (TYPE_MAP[category] && category !== "none") {
             return { intent: category, msg: `He detectado que buscas sobre ${category}. Aquí tienes algunas opciones.` };
          }
      }
  }

  return null;
}

/**
 * Texto libre que pide ubicación / mapa / país de una IP u host (no regex de entidad).
 */
function hasGeoInvestigationContext(s) {
  const v = String(s || "").toLowerCase();
  if (!v.trim()) return false;
  const markers = [
    "geolocaliz",
    "localiz",
    "localización",
    "ubicac",
    "coordenad",
    " en el mapa",
    " en mapa",
    "mapa de",
    "ver en mapa",
    "gps",
    "latitud",
    "longitud",
    "where is ",
    "location of",
    "country of",
    "país de la ip",
    "pais de la ip",
    "país de la direccion",
    "pais de la direccion",
    "país de ip",
    "pais de ip",
    "ciudad de la ip",
    "ciudad de ip",
    "origen de la ip",
    "origen ip",
    "ubicación de la ip",
    "ubicacion de la ip",
    "de qué país",
    "de que país",
    "de que pais",
    "qué país",
    "que país",
    "que pais",
    "donde está",
    "donde esta",
    "dónde está",
    "donde queda",
    "en que país",
    "en que pais",
    "ubicar ip",
    "rastrear ip",
    "trazado de ip",
  ];
  return markers.some((m) => v.includes(m));
}

const IPV6_LITERAL_STRICT =
  /^((?:[0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4}|([0-9A-Fa-f]{1,4}:){1,7}:|([0-9A-Fa-f]{1,4}:){1,6}:[0-9A-Fa-f]{1,4}|([0-9A-Fa-f]{1,4}:){1,5}(:[0-9A-Fa-f]{1,4}){1,2}|([0-9A-Fa-f]{1,4}:){1,4}(:[0-9A-Fa-f]{1,4}){1,3}|([0-9A-Fa-f]{1,4}:){1,3}(:[0-9A-Fa-f]{1,4}){1,4}|([0-9A-Fa-f]{1,4}:){1,2}(:[0-9A-Fa-f]{1,4}){1,5}|[0-9A-Fa-f]{1,4}:((:[0-9A-Fa-f]{1,4}){1,6})|:((:[0-9A-Fa-f]{1,4}){1,7}|:))$/i;

function normalizeIPv6Candidate(raw) {
  let x = String(raw || "").trim();
  if (x.startsWith("[") && x.endsWith("]")) {
    x = x.slice(1, -1);
  }
  const pct = x.indexOf("%");
  if (pct !== -1) {
    x = x.slice(0, pct).trim();
  }
  return x;
}

/**
 * Primera IPv6 válida en texto libre (antes que IPv4 para evitar ::ffff:x.x.x.x → solo IPv4).
 */
function extractIPv6FromText(str) {
  const s = String(str || "");
  const seen = new Set();
  const tryOne = (raw) => {
    let x = normalizeIPv6Candidate(
      String(raw || "").replace(/^[^0-9a-fA-F:[\]]+|[^0-9a-fA-F:%\]]+$/gi, "")
    );
    if (!x || !x.includes(":") || seen.has(x)) return null;
    seen.add(x);
    return IPV6_LITERAL_STRICT.test(x) ? x : null;
  };
  const chunks = s.split(/[\s,;<>()"'{}[\]]+/);
  for (let i = 0; i < chunks.length; i++) {
    const hit = tryOne(chunks[i]);
    if (hit) return hit;
  }
  const bracketed = s.match(/\[[0-9a-fA-F:.]+\]/gi);
  if (bracketed) {
    for (const b of bracketed) {
      const hit = tryOne(b);
      if (hit) return hit;
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

  const ipv6Found = extractIPv6FromText(s);
  if (ipv6Found) {
    if (hasGeoInvestigationContext(s)) {
      return {
        type: "ipv6",
        value: ipv6Found,
        msg: `He encontrado una IPv6: ${ipv6Found}. Priorizando herramientas de geolocalización.`,
        filterIntent: "geo",
      };
    }
    return ret("ipv6", ipv6Found, `He encontrado una IPv6: ${ipv6Found}.`);
  }

  // Explicit IP prefix handling (Safety net)
  if (/^ip\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i.test(s)) {
      const manualIp = s.match(/^ip\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i)[1];
      if (hasGeoInvestigationContext(s)) {
        return {
          type: "ipv4",
          value: manualIp,
          msg: `He encontrado una IP: ${manualIp}. Priorizando herramientas de geolocalización.`,
          filterIntent: "geo",
        };
      }
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
        if (hasGeoInvestigationContext(s)) {
          return {
            type: "ipv4",
            value: cand,
            msg: `He encontrado una IP: ${cand}. Priorizando herramientas de geolocalización.`,
            filterIntent: "geo",
          };
        }
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
     const tld = domainMatch[0].split('.').pop();
     if (isValidTLD(tld)) {
         return ret("domain", domainMatch[0], `He encontrado un dominio: ${domainMatch[0]}.`);
     }
  }

  // 2. Local Intent Detection (Conversational) - ONLY if no entity extracted
  const intentMatch = detectIntent(s);
  if (intentMatch) {
    // For intent matches (keywords), we do NOT set 'value' so that cards don't auto-fill "dominios" as a domain to analyze.
    return { type: "keyword", intent: intentMatch.intent, msg: intentMatch.msg, value: null };
  }

  // 3. Strict matches (Full string matches) - Fallback for other types not covered by extraction
  // Note: Extraction logic above covers many of these, but we keep specific ones that are harder to extract reliably without false positives in free text


  // 3. Fallback to existing strict checks if extraction failed but strict matched (unlikely but safe)
  if (/^com\.[a-z0-9_-]+\.[a-z0-9_.-]+$/i.test(s)) {
    return ret("package", s, `Detecto un paquete de aplicacion ${s}. Te muestro recursos asociados.`);
  }
  if (IPV6_LITERAL_STRICT.test(s)) {
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

function toast(text, durationMs, variant) {
    if (!text || !toastEl) return;
    const el = toastEl;
    const ms = typeof durationMs === "number" && durationMs > 0 ? durationMs : 3800;
    el.textContent = text;
    el.classList.toggle("osint-toast--google", variant === "google");
    el.classList.remove("show");
    void el.offsetWidth;
    el.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        el.classList.remove("show");
        el.classList.remove("osint-toast--google");
    }, ms);
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
 * Favoritos por usuario (meta + localStorage; deduplicado en servidor)
 * ========================================================= */
const LOCAL_FAV_STORAGE_KEY = "osint_deck_favorite_ids_v1";
let userFavoriteIdSet = new Set();
/** Alineado con la cookie SSO / osintDeckAjax.deckLoggedIn (favoritos solo con sesión). */
let osintDeckAuthLoggedIn = false;

function initFavoriteIdSet() {
  userFavoriteIdSet.clear();
  const loggedIn =
    typeof osintDeckAjax !== "undefined" && !!osintDeckAjax.deckLoggedIn;
  osintDeckAuthLoggedIn = loggedIn;
  const fromAjax =
    typeof osintDeckAjax !== "undefined" && Array.isArray(osintDeckAjax.favoriteToolIds)
      ? osintDeckAjax.favoriteToolIds
      : [];
  if (loggedIn) {
    fromAjax.map(Number).filter(Boolean).forEach((id) => userFavoriteIdSet.add(id));
  }
  try {
    localStorage.removeItem(LOCAL_FAV_STORAGE_KEY);
  } catch (e) {
    /* ignore */
  }
}

function mergeServerFavoriteIds(ids) {
  if (!Array.isArray(ids)) return;
  ids.map(Number).filter(Boolean).forEach((id) => userFavoriteIdSet.add(id));
}

function syncFavoriteButtonsInDom() {
  document.querySelectorAll(".osint-wrap .osint-act-favorite[data-tool-id]").forEach((btn) => {
    const id = Number(btn.getAttribute("data-tool-id"));
    if (!id) return;
    const on = osintDeckAuthLoggedIn && userFavoriteIdSet.has(id);
    btn.classList.toggle("favorited", on);
    const icon = btn.querySelector("i");
    if (icon) icon.className = on ? "ri-star-fill" : "ri-star-line";
  });
  const vis = osintDeckAuthLoggedIn && userFavoriteIdSet.size > 0;
  document.querySelectorAll(".osint-favorites-clear-all").forEach((btn) => {
    btn.hidden = !vis;
    btn.setAttribute("aria-hidden", vis ? "false" : "true");
  });
}

initFavoriteIdSet();

/* =========================================================
 * Me gusta (1 por herramienta: meta usuario o transient por fp; toggle quita)
 * ========================================================= */
const LOCAL_LIKED_STORAGE_KEY = "osint_deck_liked_tool_ids_v1";
let userLikedIdSet = new Set();

function readLocalLikedIds() {
  try {
    const raw = localStorage.getItem(LOCAL_LIKED_STORAGE_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr.map(Number).filter((n) => n > 0) : [];
  } catch (e) {
    return [];
  }
}

function writeLocalLikedIdsFromSet() {
  try {
    localStorage.setItem(LOCAL_LIKED_STORAGE_KEY, JSON.stringify([...userLikedIdSet]));
  } catch (e) {
    /* ignore */
  }
}

function mergeServerLikedIds(ids) {
  if (!Array.isArray(ids)) return;
  ids.map(Number).filter(Boolean).forEach((id) => userLikedIdSet.add(id));
}

function initLikedIdSet() {
  userLikedIdSet.clear();
  if (osintDeckAuthLoggedIn) {
    const fromAjax =
      typeof osintDeckAjax !== "undefined" && Array.isArray(osintDeckAjax.likedToolIds)
        ? osintDeckAjax.likedToolIds
        : [];
    fromAjax.map(Number).filter(Boolean).forEach((id) => userLikedIdSet.add(id));
  } else {
    readLocalLikedIds().forEach((id) => userLikedIdSet.add(id));
  }
}

function syncLikeButtonsInDom() {
  document.querySelectorAll(".osint-wrap .osint-act-like[data-tool-id]").forEach((btn) => {
    const id = Number(btn.getAttribute("data-tool-id"));
    if (!id) return;
    const on = userLikedIdSet.has(id);
    btn.classList.toggle("liked", on);
    const icon = btn.querySelector("i");
    if (icon) icon.className = on ? "ri-heart-fill" : "ri-heart-line";
  });
}

initLikedIdSet();

/* =========================================================
 * Reportes (toggle, comentario con sesión, gracias tras reparar)
 * ========================================================= */
const LOCAL_REPORTED_STORAGE_KEY = "osint_deck_reported_tools_v1";
let userReportedIdSet = new Set();
let reportThanksToolIdsQueue = [];

function readLocalReportedIds() {
  try {
    const raw = localStorage.getItem(LOCAL_REPORTED_STORAGE_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr.map(Number).filter((n) => n > 0) : [];
  } catch (e) {
    return [];
  }
}

function writeLocalReportedIdsFromSet() {
  try {
    localStorage.setItem(LOCAL_REPORTED_STORAGE_KEY, JSON.stringify([...userReportedIdSet]));
  } catch (e) {
    /* ignore */
  }
}

function initReportThanksFromAjax() {
  reportThanksToolIdsQueue = [];
  if (typeof osintDeckAjax !== "undefined" && Array.isArray(osintDeckAjax.reportThanksToolIds)) {
    reportThanksToolIdsQueue = osintDeckAjax.reportThanksToolIds.map(Number).filter((n) => n > 0);
  }
}

function initReportedIdSet() {
  userReportedIdSet.clear();
  const loggedIn = typeof osintDeckAjax !== "undefined" && !!osintDeckAjax.deckLoggedIn;
  if (loggedIn) {
    const fromAjax =
      typeof osintDeckAjax !== "undefined" && Array.isArray(osintDeckAjax.reportedToolIds)
        ? osintDeckAjax.reportedToolIds
        : [];
    fromAjax.map(Number).filter(Boolean).forEach((id) => userReportedIdSet.add(id));
  } else {
    readLocalReportedIds().forEach((id) => userReportedIdSet.add(id));
  }
}

initReportThanksFromAjax();
initReportedIdSet();

function osintDeckGetFingerprint() {
  try {
    const parts = [
      navigator.userAgent || "",
      navigator.language || "",
      (Intl.DateTimeFormat().resolvedOptions().timeZone || ""),
      String(new Date().getTimezoneOffset()),
      `${(screen || {}).width || ""}x${(screen || {}).height || ""}`,
    ];
    return btoa(unescape(encodeURIComponent(parts.join("|"))));
  } catch (e) {
    return "";
  }
}

function syncReportButtonsInDom() {
  const reportMsgs =
    typeof osintDeckAjax !== "undefined" && osintDeckAjax.reports ? osintDeckAjax.reports : {};
  document.querySelectorAll(".osint-wrap .osint-report[data-tool-id]").forEach((btn) => {
    const id = Number(btn.getAttribute("data-tool-id"));
    if (!id) return;
    const on = userReportedIdSet.has(id);
    btn.classList.toggle("reported", on);
    const icon = btn.querySelector("i");
    if (icon) icon.className = on ? "ri-flag-fill" : "ri-flag-line";
    btn.setAttribute(
      "data-title",
      on ? reportMsgs.tooltipOn || "Quitar reporte" : reportMsgs.tooltipOff || "Reportar herramienta"
    );
  });
}

async function osintDeckDismissReportThanks(ids) {
  const _raw = window.osintDeckAjax || {};
  const url = _raw.url || _raw.ajaxUrl || "";
  const nonce = _raw.nonce || "";
  if (!url || !nonce || !ids.length) return;
  const data = new URLSearchParams();
  data.append("action", "osint_deck_dismiss_report_thanks");
  data.append("nonce", nonce);
  data.append("tool_ids", JSON.stringify(ids));
  const fp = osintDeckGetFingerprint();
  if (fp) data.append("fp", fp);
  await window.osintDeckTurnstileAppendToParams(data);
  await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: data.toString(),
    credentials: "same-origin",
  }).catch(() => {});
}

let reportThanksNoticeEl = null;

function initReportThanksNoticeShell() {
  if (reportThanksNoticeEl && document.body.contains(reportThanksNoticeEl)) {
    return;
  }
  const el = document.createElement("div");
  el.className = "osint-thanks-notice";
  el.setAttribute("role", "status");
  el.setAttribute("aria-live", "polite");
  el.setAttribute("aria-atomic", "true");
  el.hidden = true;

  const closeBtn = document.createElement("button");
  closeBtn.type = "button";
  closeBtn.className = "osint-thanks-notice__close";
  const m0 =
    typeof osintDeckAjax !== "undefined" && osintDeckAjax.reports ? osintDeckAjax.reports : {};
  closeBtn.setAttribute("aria-label", m0.thanksCloseAria || "Cerrar aviso");
  closeBtn.innerHTML = '<i class="ri-close-line" aria-hidden="true"></i>';

  const textEl = document.createElement("p");
  textEl.className = "osint-thanks-notice__text";

  el.appendChild(closeBtn);
  el.appendChild(textEl);
  document.body.appendChild(el);
  reportThanksNoticeEl = el;

  closeBtn.addEventListener("click", () => {
    let ids = [];
    try {
      ids = reportThanksNoticeEl.dataset.pendingIds
        ? JSON.parse(reportThanksNoticeEl.dataset.pendingIds)
        : [];
    } catch (err) {
      ids = [];
    }
    ids = [...new Set(ids.map(Number).filter((n) => n > 0))];
    delete reportThanksNoticeEl.dataset.pendingIds;
    reportThanksNoticeEl.hidden = true;
    reportThanksNoticeEl.classList.remove("osint-thanks-notice--visible");
    if (ids.length) {
      osintDeckDismissReportThanks(ids);
    }
  });
}

function showReportThanksNotice(message, ids) {
  initReportThanksNoticeShell();
  const textEl = reportThanksNoticeEl.querySelector(".osint-thanks-notice__text");
  if (textEl) {
    textEl.textContent = message;
  }
  const incoming = [...new Set((ids || []).map(Number).filter((n) => n > 0))];
  let merged = incoming;
  if (reportThanksNoticeEl.dataset.pendingIds) {
    try {
      const prev = JSON.parse(reportThanksNoticeEl.dataset.pendingIds);
      merged = [
        ...new Set(
          [...(Array.isArray(prev) ? prev : []), ...incoming]
            .map(Number)
            .filter((n) => n > 0)
        ),
      ];
    } catch (err) {
      merged = incoming;
    }
  }
  reportThanksNoticeEl.dataset.pendingIds = JSON.stringify(merged);
  reportThanksNoticeEl.hidden = false;
  void reportThanksNoticeEl.offsetWidth;
  reportThanksNoticeEl.classList.add("osint-thanks-notice--visible");
}

function osintDeckFlushReportThanksToasts() {
  if (!reportThanksToolIdsQueue.length) return;
  const reportMsgs =
    typeof osintDeckAjax !== "undefined" && osintDeckAjax.reports ? osintDeckAjax.reports : {};
  const msg =
    reportMsgs.thanks || "Ya revisamos lo que reportaste. ¡Gracias por ayudarnos a mejorar OSINT Deck!";
  const ids = [...reportThanksToolIdsQueue];
  reportThanksToolIdsQueue = [];
  showReportThanksNotice(msg, ids);
}

async function osintDeckFetchReportStateAndMerge() {
  const _raw = window.osintDeckAjax || {};
  const url = _raw.url || _raw.ajaxUrl || "";
  const nonce = _raw.nonce || "";
  if (!url || !nonce) return null;
  const data = new URLSearchParams();
  data.append("action", "osint_deck_report_state");
  data.append("nonce", nonce);
  const fp = osintDeckGetFingerprint();
  if (fp) data.append("fp", fp);
  const r = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: data.toString(),
    credentials: "same-origin",
  })
    .then((x) => x.json())
    .catch(() => null);
  if (!r || !r.success || !r.data) return null;
  return r.data;
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
    const resolved = mode === "dark" ? "dark" : "light";
    wrap.setAttribute("data-theme", resolved);
    const ajax = typeof osintDeckAjax !== "undefined" ? osintDeckAjax : {};
    const brand = document.getElementById(`${uid}-brand`);
    if (brand && ajax.brandUrl) {
      brand.setAttribute("href", ajax.brandUrl);
    }
    const logoImg = document.getElementById(`${uid}-brandLogo`);
    if (logoImg && ajax.logoOnLightBg && ajax.logoOnDarkBg) {
      logoImg.src = resolved === "dark" ? ajax.logoOnDarkBg : ajax.logoOnLightBg;
    }
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

  const _ajaxForTpl = typeof osintDeckAjax !== "undefined" ? osintDeckAjax : {};
  const _authForTpl = _ajaxForTpl.auth || {};
  const googleSsoMarkUrlRaw = String(
    (_authForTpl.googleMarkUrl && _authForTpl.googleMarkUrl.trim()) ||
      "https://ssl.gstatic.com/images/branding/googleg/1x/googleg_standard_color_128dp.png"
  );
  const googleSsoMarkSrc = googleSsoMarkUrlRaw
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;");

  /* =========================================================
   * MARKUP PRINCIPAL
   * ========================================================= */
  wrap.innerHTML = `
    <!-- Barra + filtros: mismo bloque sticky (mismo offset que admin) -->
    <div class="osint-sticky-head">
    <!-- 🔹 Barra de búsqueda -->
    <div class="osint-chatbar" role="search">
      <div class="osint-detected" id="${uid}-detected" aria-live="polite"></div>
      <a class="osint-brand" id="${uid}-brand" href="https://osintdeck.github.io" target="_blank" rel="noopener noreferrer">
        <img class="osint-brand__logo" id="${uid}-brandLogo" src="" alt="OSINT Deck" width="184" height="52" decoding="async" loading="eager" />
      </a>
      <div class="osint-input-group">
        <div class="osint-input-row-main">
          <span class="osint-icon-static">
            <span class="dashicons dashicons-search"></span>
          </span>
          <input type="text"
                 class="osint-input"
                 id="${uid}-q"
                 autocomplete="off"
                 enterkeyhint="search"
                 placeholder="Buscar o pegar datos…">
          <button type="button"
                  class="osint-btn-ghost osint-chatbar-inline-btn"
                  id="${uid}-smart"
                  aria-label="Pegar o limpiar">
            <span class="osint-icon" data-mode="paste">
              <span class="dashicons dashicons-admin-page"></span>
            </span>
          </button>
        </div>
        <div class="osint-input-row-actions">
        <button type="button"
                class="osint-btn-ghost"
                id="${uid}-communityBtn"
                aria-label="Comunidad y sugerencias"
                data-title="Comunidad y sugerencias">
          <i class="ri-team-line" aria-hidden="true" style="font-size:20px;line-height:1"></i>
        </button>

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
                id="${uid}-helpBtn"
                aria-label="Ayuda"
                data-title="Ayuda">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </button>

        <button type="button"
                class="osint-btn-ghost osint-sso-btn"
                id="${uid}-ssoBtn"
                aria-label=""
                data-title="">
          <img class="osint-sso-google-mark"
               src="${googleSsoMarkSrc}"
               width="30"
               height="30"
               alt=""
               decoding="async"
               loading="eager" />
        </button>
        </div>
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
      
      <!-- GRUPO CENTRAL (Counter + Star + Vaciar favoritos) -->
      <div style="display:flex; align-items:center; gap:12px; margin: 0 auto;">
        <div class="osint-counter" id="${uid}-counter" style="font-size: 0.85em; opacity: 0.7;"></div>
        <button type="button" class="osint-action-btn osint-popular-toggle" id="${uid}-popular-btn" aria-label="Tus favoritos" data-title="Tus favoritos">
          <i class="ri-star-line"></i>
        </button>
        <button type="button" class="osint-action-btn osint-favorites-clear-all" id="${uid}-fav-clear-all" hidden aria-hidden="true" aria-label="Vaciar favoritos" data-title="Vaciar todos los favoritos">
          <i class="ri-delete-bin-line"></i>
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
    </div>

    <!-- 🔹 Grilla -->
    <div class="osint-grid" id="${uid}-grid"></div>

    <!-- 🔹 Modal -->
    <div class="osint-overlay" id="${uid}-overlay" aria-hidden="true">
      <div class="osint-sheet" id="${uid}-sheet" role="dialog" aria-modal="true">
        <header class="osint-sheet-hdr">
          <button class="osint-sheet-close" type="button" aria-label="Cerrar" id="${uid}-sheetClose">×</button>
          <div class="osint-sheet-hero">
            <div class="osint-sheet-top-row">
              <div class="osint-fav osint-sheet-fav">
                <img id="${uid}-sheetFav" src="" alt="">
              </div>
              <div class="osint-sheet-hero-text">
                <h2 class="osint-ttl osint-sheet-ttl" id="${uid}-sheetTitle"></h2>
                <div class="osint-sheet-subrow" id="${uid}-sheetSub"></div>
                <div class="osint-sheet-desc osint-sheet-desc--hero" id="${uid}-sheetDesc"></div>
                <div class="osint-sheet-meta-host" id="${uid}-sheetMeta"></div>
              </div>
            </div>
          </div>
        </header>
        <div class="osint-sheet-grid" id="${uid}-sheetGrid"></div>
      </div>
    </div>

    <div class="osint-report-dlg osint-hidden" id="${uid}-report-dlg" aria-hidden="true">
      <div class="osint-report-dlg__backdrop" tabindex="-1"></div>
      <div class="osint-report-dlg__panel" role="dialog" aria-modal="true" aria-labelledby="${uid}-report-dlg-title">
        <button type="button" class="osint-report-dlg__close" aria-label="Cerrar">
          <i class="ri-close-line" aria-hidden="true"></i>
        </button>
        <h3 class="osint-report-dlg__title" id="${uid}-report-dlg-title"></h3>
        <p class="osint-report-dlg__desc"></p>
        <div class="osint-report-dlg__field osint-hidden">
          <label class="osint-report-dlg__label">
            <span class="osint-report-dlg__label-text"></span>
            <textarea class="osint-report-dlg__textarea" maxlength="2000" rows="5" autocomplete="off" spellcheck="true"></textarea>
          </label>
          <div class="osint-report-dlg__counter"><span class="osint-report-dlg__count">0</span>/2000</div>
        </div>
        <div class="osint-report-dlg__footer">
          <button type="button" class="osint-report-dlg__btn osint-report-dlg__btn--secondary" data-osint-report-act="cancel"></button>
          <button type="button" class="osint-report-dlg__btn osint-report-dlg__btn--secondary osint-hidden" data-osint-report-act="secondary"></button>
          <button type="button" class="osint-report-dlg__btn osint-report-dlg__btn--primary" data-osint-report-act="primary"></button>
        </div>
      </div>
    </div>
  `;

  setContainerTheme(wrap.getAttribute("data-theme") === "dark" ? "dark" : "light");

  /* =========================================================
   * UTILIDADES LOCALES
   * ========================================================= */
  const esc = (s) =>
    String(s || "").replace(/[&<>"]/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;" }[m] || m));

  function sanitizeReportMessageClient(raw) {
    let s = String(raw || "").replace(/\u0000/g, "");
    s = s.replace(/[\u0001-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, "");
    if (s.length > 2000) {
      s = s.slice(0, 2000);
    }
    return s.trim();
  }

  const reportDlgState = {
    onPrimary: null,
    onSecondary: null,
  };

  function closeReportDialog() {
    const root = document.getElementById(`${uid}-report-dlg`);
    if (!root) return;
    root.classList.add("osint-hidden");
    root.setAttribute("aria-hidden", "true");
    const ta = root.querySelector(".osint-report-dlg__textarea");
    if (ta) ta.value = "";
    const cnt = root.querySelector(".osint-report-dlg__count");
    if (cnt) cnt.textContent = "0";
    reportDlgState.onPrimary = null;
    reportDlgState.onSecondary = null;
  }

  function openReportDialog(opts) {
    const root = document.getElementById(`${uid}-report-dlg`);
    if (!root) return;
    const m =
      typeof osintDeckAjax !== "undefined" && osintDeckAjax.reports ? osintDeckAjax.reports : {};
    const titleEl = root.querySelector(".osint-report-dlg__title");
    const descEl = root.querySelector(".osint-report-dlg__desc");
    const field = root.querySelector(".osint-report-dlg__field");
    const labelText = root.querySelector(".osint-report-dlg__label-text");
    const ta = root.querySelector(".osint-report-dlg__textarea");
    const btnCancel = root.querySelector('[data-osint-report-act="cancel"]');
    const btnSec = root.querySelector('[data-osint-report-act="secondary"]');
    const btnPri = root.querySelector('[data-osint-report-act="primary"]');
    if (!titleEl || !descEl || !btnCancel || !btnPri || !field || !btnSec) return;

    titleEl.textContent = opts.title || "";
    descEl.textContent = opts.desc || "";
    const hasDesc = !!(opts.desc && String(opts.desc).trim());
    descEl.classList.toggle("osint-hidden", !hasDesc);

    const showTa = !!opts.showTextarea;
    field.classList.toggle("osint-hidden", !showTa);
    if (labelText) {
      labelText.textContent = opts.textareaLabel || "";
      labelText.classList.toggle("osint-hidden", !(opts.textareaLabel && showTa));
    }
    if (ta) {
      ta.value = "";
      const cnt = root.querySelector(".osint-report-dlg__count");
      if (cnt) cnt.textContent = "0";
    }

    btnCancel.textContent = opts.cancelText || m.btnCancel || "Cancelar";
    btnPri.textContent = opts.primaryText || "OK";

    if (opts.showSecondary) {
      btnSec.classList.remove("osint-hidden");
      btnSec.textContent = opts.secondaryText || "";
    } else {
      btnSec.classList.add("osint-hidden");
    }

    reportDlgState.onPrimary = opts.onPrimary || null;
    reportDlgState.onSecondary = opts.onSecondary || null;

    root.classList.remove("osint-hidden");
    root.setAttribute("aria-hidden", "false");
    if (showTa && ta) {
      setTimeout(() => ta.focus(), 60);
    }
  }

  function initReportDialogBindings() {
    const root = document.getElementById(`${uid}-report-dlg`);
    if (!root || root.dataset.osintReportBound === "1") return;
    root.dataset.osintReportBound = "1";
    const m =
      typeof osintDeckAjax !== "undefined" && osintDeckAjax.reports ? osintDeckAjax.reports : {};
    const closer = root.querySelector(".osint-report-dlg__close");
    if (closer) closer.setAttribute("aria-label", m.dlgCloseAria || "Cerrar");

    const backdrop = root.querySelector(".osint-report-dlg__backdrop");
    if (backdrop) {
      backdrop.addEventListener("click", () => closeReportDialog());
    }
    if (closer) closer.addEventListener("click", () => closeReportDialog());

    const btnCancel = root.querySelector('[data-osint-report-act="cancel"]');
    if (btnCancel) btnCancel.addEventListener("click", () => closeReportDialog());

    const btnSec = root.querySelector('[data-osint-report-act="secondary"]');
    if (btnSec) {
      btnSec.addEventListener("click", () => {
        const fn = reportDlgState.onSecondary;
        closeReportDialog();
        if (fn) fn();
      });
    }

    const btnPri = root.querySelector('[data-osint-report-act="primary"]');
    const field = root.querySelector(".osint-report-dlg__field");
    const ta = root.querySelector(".osint-report-dlg__textarea");
    if (btnPri) {
      btnPri.addEventListener("click", () => {
        const msg =
          !field || field.classList.contains("osint-hidden") || !ta
            ? ""
            : sanitizeReportMessageClient(ta.value);
        const fn = reportDlgState.onPrimary;
        closeReportDialog();
        if (fn) fn(msg);
      });
    }

    if (ta) {
      const cnt = root.querySelector(".osint-report-dlg__count");
      ta.addEventListener("input", () => {
        if (cnt) cnt.textContent = String((ta.value || "").length);
      });
    }

    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      if (!root || root.classList.contains("osint-hidden")) return;
      closeReportDialog();
    });
  }

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

  const _rawAjax = window.osintDeckAjax || window.OSINT_DECK_AJAX || {};
  const ajaxCfg = {
    ..._rawAjax,
    url: _rawAjax.url || _rawAjax.ajaxUrl || "",
    nonce: _rawAjax.nonce || "",
  };
  const ajaxUrl = ajaxCfg.url;
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

  async function sendEvent(event, payload = {}) {
    if (!ajaxCfg.url || !ajaxCfg.nonce) return Promise.resolve({ ok: false });
    const data = new URLSearchParams();
    data.append("action", "osd_user_event");
    data.append("nonce", ajaxCfg.nonce);
    data.append("event", event);
    if (fpCache) data.append("fp", fpCache);
    Object.entries(payload).forEach(([k, v]) => data.append(k, v || ""));

    await window.osintDeckTurnstileAppendToParams(data);

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

  async function clearAllFavorites() {
    if (!ssoCfg.enabled) {
      toast(favMsgs.ssoDisabled);
      return;
    }
    if (!osintDeckAuthLoggedIn) {
      toast(favMsgs.needLogin);
      return;
    }
    const idsBefore = [...userFavoriteIdSet];
    if (!idsBefore.length) {
      toast(favMsgs.clearAllEmpty || "No hay favoritos");
      return;
    }
    if (!window.confirm(favMsgs.clearAllConfirm)) return;

    const applyServerSyncUi = () => {
      idsBefore.forEach((tid) => {
        document.querySelectorAll(".osint-act-favorite[data-tool-id]").forEach((btn) => {
          if (Number(btn.getAttribute("data-tool-id")) !== tid) return;
          btn.classList.remove("favorited");
          const icon = btn.querySelector("i");
          if (icon) icon.className = "ri-star-line";
        });
      });
    };

    if (!ajaxUrl || !ajaxCfg.nonce) {
      toast(favMsgs.clearAllFailed || "Error");
      return;
    }

    const data = new URLSearchParams();
    data.append("action", "osint_deck_clear_favorites");
    data.append("nonce", ajaxCfg.nonce);
    await window.osintDeckTurnstileAppendToParams(data);

    const r = await fetch(ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    })
      .then((x) => x.json())
      .catch(() => null);

    const ok = r && r.success;
    if (!ok) {
      toast((r && r.data && r.data.message) || favMsgs.clearAllFailed || "Error");
      return;
    }

    applyServerSyncUi();
    userFavoriteIdSet.clear();
    syncFavoriteButtonsInDom();
    document.dispatchEvent(new CustomEvent("osint-deck-favorites-changed"));
    toast(favMsgs.clearAllDone || "Listo");
  }

  document.addEventListener("osint-deck-favorites-changed", () => {
    if (filterPopularOnly) applyFilters();
  });

  /** ID numérico de herramienta para AJAX (siempre _db_id en WordPress). */
  function toolPrimaryId(tool) {
    if (!tool) return "";
    if (tool._db_id != null && tool._db_id !== "") {
      return String(tool._db_id);
    }
    if (tool.id != null && tool.id !== "") {
      return String(tool.id);
    }
    return "";
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
  const sheetFav = document.getElementById(`${uid}-sheetFav`);
  const sheetGrid = document.getElementById(`${uid}-sheetGrid`);
  const sheetClose = document.getElementById(`${uid}-sheetClose`);
  /* Modal a document.body: evita recortes si el tema/Elementor usa overflow/transform en contenedores */
  if (overlay && document.body && overlay.parentElement !== document.body) {
    document.body.appendChild(overlay);
  }
  const reportDlgRoot = document.getElementById(`${uid}-report-dlg`);
  if (reportDlgRoot && document.body && reportDlgRoot.parentElement !== document.body) {
    document.body.appendChild(reportDlgRoot);
  }
  initReportDialogBindings();
  if (sheet) {
    sheet.setAttribute("aria-labelledby", `${uid}-sheetTitle`);
  }
  if (sheetClose) {
    sheetClose.addEventListener("click", closeModal);
  }
  const focusableSelector = 'a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])';
  let lastFocused = null;
  const toolByDeck = new WeakMap();

  if (grid) {
    grid.addEventListener("click", (e) => {
      const deck = e.target.closest(".osint-deck");
      if (!deck || !grid.contains(deck)) return;
      if (deck.classList.contains("osint-help-deck")) return;
      if (
        e.target.closest(
          "a, button, .osd-filter, .osint-share-wrapper, .osint-share-menu"
        )
      ) {
        return;
      }
      const toolRef = toolByDeck.get(deck);
      if (toolRef) openModal(toolRef);
    });
  }

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
       if (typeof gsap !== 'undefined') {
          // If open and not animating, ensure height matches content
          gsap.set(filterWrapRef, { height: "auto" });
       } else {
          // Include margin-bottom (approx 24px) for shadow
          filterWrapRef.style.height = (filtersBarRef.scrollHeight + 24) + "px";
       }
    }
  });
  if (filtersBarRef) ro.observe(filtersBarRef);

  // Initialize state
  if (filterWrapRef) {
      if (typeof gsap !== 'undefined') {
          gsap.set(filterWrapRef, { height: 0 });
          gsap.set(filtersBarRef, { y: -16, opacity: 0, scale: 0.99 });
      } else {
          filterWrapRef.style.height = "0px";
      }
  }

  function ensureFiltersVisible() {
    if (!showFilters) openFilters();
  }

  function openFilters() {
     if (showFilters || isAnimating || !filterWrapRef) return;
     isAnimating = true;
     showFilters = true;

     // Check for GSAP
    if (typeof gsap !== 'undefined') {
         gsap.set(filterWrapRef, { overflow: "hidden" });
         filterWrapRef.classList.add("is-open");
         if (chatBarRef) chatBarRef.classList.add("has-filters-open");
         filterWrapRef.setAttribute("aria-hidden", "false");

         const tl = gsap.timeline({
             onComplete: () => {
                 isAnimating = false;
                 // Ensure height is set to auto for responsiveness
                 gsap.set(filterWrapRef, { height: "auto", overflow: "visible" });
             }
         });

          // Animate Search Bar Margin
          if (chatBarRef) {
              tl.to(chatBarRef, {
                  marginBottom: 0,
                  duration: 0.62,
                  ease: "power2.out"
              }, 0);
          }
          
          // Animate Wrapper Height
          tl.to(filterWrapRef, {
              height: "auto",
              duration: 0.64,
              ease: "power2.out"
          }, "<");

          // Animate Filter Bar
          tl.to(filtersBarRef, {
              y: 0,
              opacity: 1,
              scale: 1,
              duration: 0.6,
              ease: "power2.out"
          }, "<0.1"); 

     } else {
         // Fallback
         filterWrapRef.classList.add("is-open");
         if (chatBarRef) chatBarRef.classList.add("has-filters-open");
         filterWrapRef.setAttribute("aria-hidden", "false");
    
         filterWrapRef.style.height = "0px";
         void filterWrapRef.getBoundingClientRect();
    
         filterWrapRef.style.height = (filtersBarRef.scrollHeight + 24) + "px";
    
         const onEnd = (e) => {
           if (e.propertyName !== "height") return;
           filterWrapRef.removeEventListener("transitionend", onEnd);
           filterWrapRef.style.height = "auto";
           filterWrapRef.style.overflow = "visible";
           isAnimating = false;
         };
         filterWrapRef.addEventListener("transitionend", onEnd);
     }
  }

  function closeFilters() {
     if (!showFilters || isAnimating || !filterWrapRef) return;
     isAnimating = true;
     showFilters = false;

     if (typeof gsap !== 'undefined') {
         // Immediately set overflow hidden to ensure clean closing animation
         gsap.set(filterWrapRef, { overflow: "hidden" });

         const tl = gsap.timeline({
             onComplete: () => {
                 isAnimating = false;
                 filterWrapRef.classList.remove("is-open");
                 if (chatBarRef) chatBarRef.classList.remove("has-filters-open");
                 filterWrapRef.setAttribute("aria-hidden", "true");
                 // Clear inline styles for margin-bottom to revert to CSS
                 if (chatBarRef) gsap.set(chatBarRef, { clearProps: "marginBottom" });
             }
         });

        tl.to(filtersBarRef, {
            y: -16,
            opacity: 0,
            scale: 0.99,
            duration: 0.42,
            ease: "power2.in"
        });

        tl.to(filterWrapRef, {
            height: 0,
            duration: 0.48,
            ease: "power2.in"
        }, "-=0.2");

        if (chatBarRef) {
            tl.to(chatBarRef, {
                marginBottom: "20px",
                duration: 0.48,
                ease: "power2.in"
            }, "<");
        }

     } else {
         filterWrapRef.style.overflow = "hidden";
          filterWrapRef.classList.add("is-closing");
          filterWrapRef.classList.remove("is-open");
          if (chatBarRef) chatBarRef.classList.remove("has-filters-open");
          filterWrapRef.setAttribute("aria-hidden", "true");
     
          filterWrapRef.style.height = (filtersBarRef.scrollHeight + 24) + "px";
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
    if (!btnSmart) return;
    const icon = btnSmart.querySelector(".osint-icon");
    if (!icon) return;
    icon.setAttribute("data-mode", mode);

    btnSmart.setAttribute("data-title", mode === "paste" ? "Pegar desde portapapeles" : "Limpiar búsqueda");

    icon.innerHTML =
      mode === "paste"
        ? `<span class="dashicons dashicons-admin-page"></span>`
        : `<span class="dashicons dashicons-no-alt"></span>`;
  }

  function toggleSmart() {
    if (!btnSmart) return;
    const mode =
      (q.value || "").trim() === "" ? "paste" : "clear";
    setSmartIcon(mode);
  }

  if (btnSmart) {
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
  }

  /* =========================================================
   * BOTÓN COMUNIDAD / SUGERENCIAS (misma carta que intención community)
   * ========================================================= */
  const COMMUNITY_QUICK_PHRASE = "sugerir herramienta";
  const btnCommunity = wrap.querySelector(`#${uid}-communityBtn`);
  if (btnCommunity) {
    btnCommunity.addEventListener("click", () => {
      const cur = (q.value || "").trim().toLowerCase();
      if (cur === COMMUNITY_QUICK_PHRASE) {
        q.value = "";
      } else {
        q.value = COMMUNITY_QUICK_PHRASE;
      }
      onInput();
    });
  }

  /* =========================================================
   * BOTÓN AYUDA
   * ========================================================= */
  const btnHelp = wrap.querySelector(`#${uid}-helpBtn`);
  if (btnHelp) {
    btnHelp.addEventListener("click", () => {
      if ((q.value || "").trim().toLowerCase() === "ayuda") {
        q.value = "";
      } else {
        q.value = "ayuda";
      }
      onInput();
    });
  }

  const ssoCfg = (typeof osintDeckAjax !== "undefined" && osintDeckAjax.auth) ? osintDeckAjax.auth : { enabled: false, googleClientId: "" };
  const SSO_TOAST_MS = 5200;
  function ssoMsg(key) {
    const v = ssoCfg && ssoCfg[key];
    return v != null && String(v) !== "" ? String(v) : "";
  }
  function formatSsoTemplate(str, name) {
    const n = String(name || "").trim() || ssoMsg("fallbackName") || "usuario";
    return String(str || "").replace(/%s/g, n);
  }
  function authResponseMessage(out, fallbackKey) {
    if (out && out.data && out.data.message) return String(out.data.message);
    return ssoMsg(fallbackKey || "loginFailed") || "";
  }
  const favMsgs =
    typeof osintDeckAjax !== "undefined" && osintDeckAjax.favorites
      ? osintDeckAjax.favorites
      : {
          needLogin: "Iniciá sesión con tu cuenta (Google) para guardar y usar favoritos.",
          needLoginFilter: "Iniciá sesión para ver solo tus favoritos.",
          ssoDisabled:
            "Los favoritos no están disponibles porque el acceso con cuenta no está habilitado en este sitio.",
          clearAllConfirm: "¿Vaciar todos tus favoritos?",
          clearAllFailed: "No se pudieron vaciar los favoritos.",
          clearAllDone: "Favoritos vaciados.",
          clearAllEmpty: "No tenés favoritos guardados.",
          favUpdateError: "Error al actualizar favoritos.",
          menuShowFavorites: "Ver solo favoritos",
          menuShowAllDeck: "Ver todas las herramientas",
        };
  const likeMsgs =
    typeof osintDeckAjax !== "undefined" && osintDeckAjax.likes
      ? osintDeckAjax.likes
      : {
          on: "Te gusta esta herramienta.",
          off: "Quitaste tu me gusta.",
          error: "No se pudo actualizar el me gusta.",
        };
  const privacy = (typeof osintDeckAjax !== "undefined" && osintDeckAjax.privacy) ? osintDeckAjax.privacy : {
    historyTitle: "Tu actividad en OSINT Deck",
    historyIntro:
      "Solo vos ves este listado. Podés borrar el historial o eliminar tu cuenta de acceso a OSINT Deck (Google en este sitio). El registro es para tu referencia (origen o dispositivo cuando se muestre). OSINT Deck no comparte, no vende ni usa esos datos con fines comerciales y respeta tu privacidad. Los datos los aloja quien administra el sitio.",
    historyMenu: "Mi actividad y privacidad",
    historyEmpty: "No hay registros todavía.",
    clearHistory: "Borrar todo mi historial",
    clearConfirm: "¿Borrar por completo tu historial? No se puede deshacer.",
    clearFailed: "No se pudo borrar el historial.",
    deleteAccount: "Eliminar mi cuenta",
    deleteWarn:
      "Se eliminarán tu cuenta del deck (favoritos, historial y datos asociados), no tu usuario de WordPress. Luego aceptarás los términos y escribirás DELETE.",
    deleteFailed: "No se pudo eliminar la cuenta.",
    deleteBlocked: "No se pudo completar la baja. Probá de nuevo.",
    termsUrl: "https://osintdeck.github.io/docs.html",
    deleteTermsIntro: "La baja es definitiva. Los términos y la privacidad están en la documentación oficial de OSINT Deck.",
    deleteTermsCheckbox: "Leí y acepto los términos y condiciones de uso y la política de privacidad de esa documentación.",
    deleteTermsLink: "Abrir documentación",
    deleteTermsContinue: "Continuar",
    deleteTermsCancel: "Cancelar",
    deleteTermsRequired: "Tenés que marcar la casilla para confirmar que aceptás los términos.",
    close: "Cerrar",
    loadError: "No se pudo cargar el historial. Iniciá sesión de nuevo.",
    typeSearch: "Búsqueda",
    typeOpen: "Abrir herramienta",
    typeLike: "Me gusta",
    typeFavorite: "Favorito",
    typeReport: "Reporte",
    historyOpenWithQuery: "Buscaste «%1$s» · abriste %2$s",
  };
  let currentUser = null;
  const btnSso = wrap.querySelector(`#${uid}-ssoBtn`);
  let ssoMenu = null;
  let ssoMenuCloser = null;
  let historyPanel = null;
  function decodeJwt(t) {
    try {
      const p = t.split(".")[1];
      const json = atob(p.replace(/-/g, "+").replace(/_/g, "/"));
      return JSON.parse(json);
    } catch (e) {
      return {};
    }
  }
  function ensureGis(cb) {
    if (window.google && window.google.accounts && window.google.accounts.id) {
      cb();
      return;
    }
    const existingGsi = document.querySelector('script[src*="accounts.google.com/gsi/client"]');
    if (existingGsi) {
      if (window.google && window.google.accounts && window.google.accounts.id) {
        cb();
        return;
      }
      existingGsi.addEventListener("load", cb, { once: true });
      return;
    }
    const s = document.createElement("script");
    s.src = "https://accounts.google.com/gsi/client";
    s.async = true;
    s.onload = cb;
    document.head.appendChild(s);
  }

  function closeGisFallback() {
    const el = document.getElementById(`${uid}-gis-fallback`);
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }

  async function handleGoogleCredentialResponse(resp) {
    closeGisFallback();
    showSsoSpinner(true);
    const prevId =
      currentUser && currentUser.id != null ? Number(currentUser.id) : null;
    const data = new URLSearchParams();
    data.append("action", "osint_deck_auth_google");
    data.append("nonce", ajaxCfg.nonce);
    data.append("id_token", (resp && resp.credential) || "");
    try {
      await window.osintDeckTurnstileAppendToParams(data);
    } catch (e) {
      showSsoSpinner(false);
      toast(ssoMsg("loginNetworkError"), SSO_TOAST_MS, "google");
      renderSsoMenu();
      return;
    }
    fetch(ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    })
      .then((r) => r.json())
      .then((out) => {
        showSsoSpinner(false);
        if (out && out.success && out.data) {
          const newId = Number(out.data.id);
          const dispName = out.data.name || "";
          setUserUI(out.data);
          if (prevId === null) {
            toast(
              formatSsoTemplate(
                ssoMsg("loginWelcome") || "Sesión iniciada. Hola, %s.",
                dispName
              ),
              SSO_TOAST_MS,
              "google"
            );
          } else if (prevId !== newId) {
            toast(
              formatSsoTemplate(
                ssoMsg("accountSwitched") || "Cambiaste de cuenta. Ahora sos %s.",
                dispName
              ),
              SSO_TOAST_MS,
              "google"
            );
          }
        } else {
          toast(authResponseMessage(out, "loginFailed"), SSO_TOAST_MS, "google");
        }
        renderSsoMenu();
      })
      .catch(() => {
        showSsoSpinner(false);
        toast(ssoMsg("loginNetworkError"), SSO_TOAST_MS, "google");
        renderSsoMenu();
      });
  }

  function initGisOnce() {
    if (!ssoCfg.enabled || !ssoCfg.googleClientId) return;
    if (window._osdGisDeckInited) return;
    if (!window.google || !window.google.accounts || !window.google.accounts.id) {
      return;
    }
    window.google.accounts.id.initialize({
      client_id: ssoCfg.googleClientId,
      callback: handleGoogleCredentialResponse,
      auto_select: false,
      cancel_on_tap_outside: true,
    });
    window._osdGisDeckInited = true;
  }

  function showGoogleSignInFallback() {
    closeGisFallback();
    if (!window.google || !window.google.accounts || !window.google.accounts.id) return;
    const shell = document.createElement("div");
    shell.id = `${uid}-gis-fallback`;
    shell.className = "osd-gis-fallback";
    shell.setAttribute("role", "dialog");
    shell.setAttribute("aria-modal", "true");
    shell.innerHTML =
      '<div class="osd-gis-fallback__backdrop" tabindex="-1"></div>' +
      '<div class="osd-gis-fallback__panel">' +
      '<button type="button" class="osd-gis-fallback__close" aria-label="Cerrar">×</button>' +
      '<p class="osd-gis-fallback__hint"></p>' +
      '<div class="osd-gis-fallback__host"></div>' +
      "</div>";
    const hint = shell.querySelector(".osd-gis-fallback__hint");
    if (hint) hint.textContent = ssoMsg("signInTitle") || "";
    document.body.appendChild(shell);
    const host = shell.querySelector(".osd-gis-fallback__host");
    const close = () => closeGisFallback();
    shell.querySelector(".osd-gis-fallback__backdrop").addEventListener("click", close);
    shell.querySelector(".osd-gis-fallback__close").addEventListener("click", close);
    window.google.accounts.id.renderButton(host, {
      type: "standard",
      theme: "outline",
      size: "large",
      text: "signin_with",
      width: 320,
    });
  }
  function showSsoSpinner(show) {
    if (!btnSso) return;
    btnSso.classList.toggle("loading", !!show);
  }

  function historyEventLabel(type) {
    const m = {
      search: privacy.typeSearch,
      open_tool: privacy.typeOpen,
      like: privacy.typeLike,
      favorite: privacy.typeFavorite,
      report: privacy.typeReport,
    };
    return m[type] || type || "—";
  }

  function formatHistoryRowDetail(row) {
    const q = String((row && row.query_snapshot) || "").trim();
    const t = String((row && row.tool_name) || "").trim();
    if ((row && row.event_type) === "search" && q) {
      return q;
    }
    if ((row && row.event_type) === "open_tool" && q && t) {
      const tpl = privacy.historyOpenWithQuery || "Buscaste «%1$s» · abriste %2$s";
      return tpl.replace("%1$s", q).replace("%2$s", t);
    }
    if (t) return t;
    if (q) return q;
    return "—";
  }

  function closeSsoMenuOnly() {
    if (ssoMenu) {
      if (ssoMenuCloser) document.removeEventListener("click", ssoMenuCloser);
      ssoMenu.remove();
      ssoMenu = null;
      ssoMenuCloser = null;
    }
  }

  function closeHistoryPanel() {
    if (historyPanel) {
      historyPanel.remove();
      historyPanel = null;
      document.body.style.overflow = "";
    }
  }

  function privacyTermsHref() {
    const u = String((privacy && privacy.termsUrl) || "").trim();
    if (/^https?:\/\//i.test(u)) return u;
    return "https://osintdeck.github.io/docs.html";
  }

  function showDeleteAccountTermsStep() {
    return new Promise((resolve) => {
      const card = historyPanel && historyPanel.querySelector(".osint-history-card");
      if (!card) {
        resolve(false);
        return;
      }
      const shell = document.createElement("div");
      shell.className = "osint-history-delete-terms";
      shell.setAttribute("role", "dialog");
      shell.setAttribute("aria-modal", "true");
      shell.setAttribute("aria-labelledby", "osint-deck-delete-terms-h");

      const introEl = document.createElement("p");
      introEl.className = "osint-history-delete-terms-intro";
      introEl.id = "osint-deck-delete-terms-h";
      introEl.textContent = privacy.deleteTermsIntro || "";

      const label = document.createElement("label");
      label.className = "osint-history-delete-terms-label";

      const ck = document.createElement("input");
      ck.type = "checkbox";
      ck.className = "osint-history-delete-terms-input";

      const span = document.createElement("span");
      span.className = "osint-history-delete-terms-text";
      const link = document.createElement("a");
      link.href = privacyTermsHref();
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.className = "osint-history-delete-terms-link";
      link.textContent = privacy.deleteTermsLink || "Documentación";
      span.append(document.createTextNode((privacy.deleteTermsCheckbox || "") + " "), link);

      label.append(ck, span);

      const row = document.createElement("div");
      row.className = "osint-history-delete-terms-actions";

      const btnCancel = document.createElement("button");
      btnCancel.type = "button";
      btnCancel.className = "osint-history-btn osint-history-btn--secondary osint-history-delete-terms-footer-btn";
      btnCancel.textContent = privacy.deleteTermsCancel || "Cancelar";

      const btnGo = document.createElement("button");
      btnGo.type = "button";
      btnGo.className = "osint-history-btn osint-history-btn--danger osint-history-delete-terms-footer-btn";
      btnGo.textContent = privacy.deleteTermsContinue || "Continuar";
      btnGo.disabled = true;

      ck.addEventListener("change", () => {
        btnGo.disabled = !ck.checked;
      });

      let onKey = null;
      const done = (ok) => {
        if (onKey) document.removeEventListener("keydown", onKey);
        shell.remove();
        resolve(ok);
      };

      btnCancel.addEventListener("click", () => done(false));
      btnGo.addEventListener("click", () => {
        if (!ck.checked) {
          window.alert(privacy.deleteTermsRequired || "");
          return;
        }
        done(true);
      });

      onKey = (e) => {
        if (e.key === "Escape") {
          e.preventDefault();
          done(false);
        }
      };
      document.addEventListener("keydown", onKey);

      row.append(btnCancel, btnGo);
      shell.append(introEl, label, row);
      card.appendChild(shell);
      try {
        ck.focus();
      } catch (e2) { /* ignore */ }
    });
  }

  async function clearHistoryFromPanel() {
    if (!window.confirm(privacy.clearConfirm)) return;
    const data = new URLSearchParams();
    data.append("action", "osint_deck_clear_history");
    data.append("nonce", ajaxCfg.nonce);
    await window.osintDeckTurnstileAppendToParams(data);
    const r = await fetch(ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    }).then((x) => x.json()).catch(() => ({}));
    if (r && r.success) {
      closeHistoryPanel();
      openHistoryPanel();
      return;
    }
    const msg = (r && r.data && r.data.message) ? r.data.message : privacy.clearFailed;
    window.alert(msg);
  }

  async function deleteMyAccountFlow() {
    if (!window.confirm(privacy.deleteWarn)) return;
    const termsOk = await showDeleteAccountTermsStep();
    if (!termsOk) return;
    const typed = window.prompt("DELETE", "");
    if (typed !== "DELETE") return;
    const data = new URLSearchParams();
    data.append("action", "osint_deck_delete_my_account");
    data.append("nonce", ajaxCfg.nonce);
    data.append("confirm", "DELETE");
    data.append("terms_accepted", "1");
    await window.osintDeckTurnstileAppendToParams(data);
    const r = await fetch(ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    }).then((x) => x.json()).catch(() => ({}));
    if (r && r.success) {
      closeHistoryPanel();
      setUserUI(null);
    } else {
      const msg = (r && r.data && r.data.message) ? r.data.message : privacy.deleteFailed;
      window.alert(msg);
    }
  }

  async function openHistoryPanel() {
    closeSsoMenuOnly();
    closeHistoryPanel();

    const backdrop = document.createElement("div");
    backdrop.className = "osint-history-backdrop";
    backdrop.setAttribute("role", "dialog");
    backdrop.setAttribute("aria-modal", "true");
    backdrop.setAttribute("aria-label", privacy.historyTitle);

    const card = document.createElement("div");
    card.className = "osint-history-card";
    const hdr = document.createElement("header");
    hdr.className = "osint-history-hdr";
    const btnX = document.createElement("button");
    btnX.type = "button";
    btnX.className = "osint-sheet-close";
    btnX.setAttribute("aria-label", privacy.close || "Cerrar");
    btnX.textContent = "×";
    btnX.addEventListener("click", () => closeHistoryPanel());
    const title = document.createElement("h3");
    title.className = "osint-history-title";
    title.textContent = privacy.historyTitle;
    hdr.append(btnX, title);
    const intro = document.createElement("p");
    intro.className = "osint-history-intro";
    intro.textContent = privacy.historyIntro || "";
    const listWrap = document.createElement("div");
    listWrap.className = "osint-history-list-wrap";
    listWrap.innerHTML = `<p class="osint-history-status osint-history-status--loading">…</p>`;

    const actions = document.createElement("div");
    actions.className = "osint-history-actions";
    const actionsMain = document.createElement("div");
    actionsMain.className = "osint-history-actions-main";
    const btnClear = document.createElement("button");
    btnClear.type = "button";
    btnClear.className = "osint-history-btn osint-history-btn--primary";
    btnClear.textContent = privacy.clearHistory;
    btnClear.addEventListener("click", () => clearHistoryFromPanel());
    const btnDelete = document.createElement("button");
    btnDelete.type = "button";
    btnDelete.className = "osint-history-btn osint-history-btn--danger";
    btnDelete.textContent = privacy.deleteAccount;
    btnDelete.addEventListener("click", () => deleteMyAccountFlow());
    actionsMain.append(btnClear, btnDelete);
    actions.append(actionsMain);

    card.append(hdr, intro, listWrap, actions);
    backdrop.appendChild(card);
    backdrop.addEventListener("click", (ev) => {
      if (ev.target === backdrop) closeHistoryPanel();
    });

    document.body.appendChild(backdrop);
    historyPanel = backdrop;
    document.body.style.overflow = "hidden";

    const data = new URLSearchParams();
    data.append("action", "osint_deck_get_history");
    data.append("nonce", ajaxCfg.nonce);
    try {
      const res = await fetch(ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: data.toString(),
        credentials: "same-origin",
      });
      const out = await res.json();
      listWrap.innerHTML = "";
      if (!out.success || !out.data || !Array.isArray(out.data.items)) {
        const p = document.createElement("p");
        p.className = "osint-history-status osint-history-status--error";
        const errMsg = (out && out.data && out.data.message) ? out.data.message : privacy.loadError;
        p.textContent = errMsg;
        listWrap.appendChild(p);
        return;
      }
      if (out.data.items.length === 0) {
        const p = document.createElement("p");
        p.className = "osint-history-status osint-history-status--muted";
        p.textContent = privacy.historyEmpty;
        listWrap.appendChild(p);
        return;
      }
      const ul = document.createElement("ul");
      ul.className = "osint-history-list";
      out.data.items.forEach((row) => {
        const li = document.createElement("li");
        li.className = "osint-history-item";
        const meta = document.createElement("span");
        meta.className = "osint-history-item-type";
        meta.textContent = historyEventLabel(row.event_type);
        const detail = document.createElement("span");
        detail.className = "osint-history-item-detail";
        detail.textContent = formatHistoryRowDetail(row);
        const when = document.createElement("time");
        when.className = "osint-history-item-time";
        const iso = (row.created_at || "").replace(" ", "T");
        const d = iso ? new Date(iso) : null;
        when.textContent = d && !isNaN(d.getTime()) ? d.toLocaleString("es-AR", { dateStyle: "short", timeStyle: "short" }) : (row.created_at || "");
        when.dateTime = row.created_at || "";
        li.append(meta, detail, when);
        ul.appendChild(li);
      });
      listWrap.appendChild(ul);
    } catch (e) {
      listWrap.innerHTML = "";
      const p = document.createElement("p");
      p.className = "osint-history-status osint-history-status--error";
      p.textContent = privacy.loadError;
      listWrap.appendChild(p);
    }
  }

  function renderSsoMenu() {
    if (ssoMenu) ssoMenu.remove();
    ssoMenu = document.createElement("div");
    ssoMenu.className = "osint-sso-menu";
    const name = (currentUser && currentUser.name) || "";
    const email = (currentUser && currentUser.email) || "";
    ssoMenu.innerHTML = `
      <div class="osint-sso-hdr">
        <div class="osint-sso-name">${name}</div>
        <div class="osint-sso-email">${email}</div>
      </div>
      <button type="button" class="osint-sso-act" data-act="history"></button>
      <button type="button" class="osint-sso-act" data-act="favorites"></button>
      <button type="button" class="osint-sso-act" data-act="switch">${ssoMsg("switchAccount") || "Cambiar de cuenta"}</button>
      <button type="button" class="osint-sso-act" data-act="logout">${ssoMsg("logOut") || "Desconectar"}</button>
    `;
    const historyBtn = ssoMenu.querySelector('[data-act="history"]');
    if (historyBtn) historyBtn.textContent = privacy.historyMenu || privacy.historyTitle;
    const favoritesBtn = ssoMenu.querySelector('[data-act="favorites"]');
    if (favoritesBtn) {
      favoritesBtn.textContent = filterPopularOnly
        ? (favMsgs && favMsgs.menuShowAllDeck) || "Ver todas las herramientas"
        : (favMsgs && favMsgs.menuShowFavorites) || "Ver solo favoritos";
    }
    ssoMenu.style.position = "absolute";
    ssoMenu.style.right = "10px";
    ssoMenu.style.top = "56px";
    ssoMenu.style.zIndex = "200";
    wrap.querySelector(".osint-chatbar").appendChild(ssoMenu);
    ssoMenu.addEventListener("click", (e) => {
      const act = e.target.getAttribute("data-act");
      if (act === "history") {
        e.stopPropagation();
        openHistoryPanel();
      } else if (act === "favorites") {
        e.stopPropagation();
        if (!ssoCfg.enabled) {
          toast(favMsgs.ssoDisabled);
          return;
        }
        if (!osintDeckAuthLoggedIn) {
          toast(favMsgs.needLoginFilter);
          return;
        }
        filterPopularOnly = !filterPopularOnly;
        const pt = document.getElementById(`${uid}-popular-btn`);
        if (pt) {
          pt.classList.toggle("active", filterPopularOnly);
          const icon = pt.querySelector("i");
          if (icon) {
            icon.className = filterPopularOnly ? "ri-star-fill" : "ri-star-line";
          }
        }
        if (filterPopularOnly) {
          ensureFiltersVisible();
        }
        if (ssoMenuCloser) document.removeEventListener("click", ssoMenuCloser);
        if (ssoMenu) {
          ssoMenu.remove();
          ssoMenu = null;
        }
        ssoMenuCloser = null;
        applyFilters();
      } else if (act === "switch") {
        startLogin(true);
      } else if (act === "logout") {
        logout();
      }
    });

    // Close on click outside
    if (ssoMenuCloser) document.removeEventListener("click", ssoMenuCloser);
    ssoMenuCloser = (ev) => {
      if (!ssoMenu) {
        document.removeEventListener("click", ssoMenuCloser);
        return;
      }
      // If click is NOT inside menu AND NOT inside button
      if (!ssoMenu.contains(ev.target) && !btnSso.contains(ev.target)) {
        ssoMenu.remove();
        ssoMenu = null;
        document.removeEventListener("click", ssoMenuCloser);
        ssoMenuCloser = null;
      }
    };
    // Delay to avoid immediate close
    setTimeout(() => {
        document.addEventListener("click", ssoMenuCloser);
    }, 0);
  }
  function setUserUI(u) {
    currentUser = u;
    if (u) {
      osintDeckAuthLoggedIn = true;
      userFavoriteIdSet.clear();
      if (Array.isArray(u.favorite_tool_ids)) {
        mergeServerFavoriteIds(u.favorite_tool_ids);
      }
      userLikedIdSet.clear();
      if (Array.isArray(u.liked_tool_ids)) {
        mergeServerLikedIds(u.liked_tool_ids);
      }
      if (Array.isArray(u.reported_tool_ids)) {
        userReportedIdSet.clear();
        u.reported_tool_ids.forEach((id) => userReportedIdSet.add(Number(id)));
      }
      syncFavoriteButtonsInDom();
      syncLikeButtonsInDom();
      syncReportButtonsInDom();
      if (Array.isArray(u.report_thanks_tool_ids) && u.report_thanks_tool_ids.length) {
        u.report_thanks_tool_ids.forEach((id) => {
          const n = Number(id);
          if (n > 0) reportThanksToolIdsQueue.push(n);
        });
        reportThanksToolIdsQueue = [...new Set(reportThanksToolIdsQueue)];
        osintDeckFlushReportThanksToasts();
      }
      document.dispatchEvent(new CustomEvent("osint-deck-favorites-changed"));
    } else {
      osintDeckAuthLoggedIn = false;
      userFavoriteIdSet.clear();
      try {
        localStorage.removeItem(LOCAL_FAV_STORAGE_KEY);
      } catch (e) {
        /* ignore */
      }
      userLikedIdSet.clear();
      initLikedIdSet();
      reportThanksToolIdsQueue = [];
      initReportedIdSet();
      syncFavoriteButtonsInDom();
      syncLikeButtonsInDom();
      syncReportButtonsInDom();
      osintDeckFetchReportStateAndMerge().then((data) => {
        if (data && Array.isArray(data.reported_tool_ids)) {
          userReportedIdSet.clear();
          data.reported_tool_ids.forEach((id) => userReportedIdSet.add(Number(id)));
          writeLocalReportedIdsFromSet();
        }
        syncReportButtonsInDom();
      });
      document.dispatchEvent(
        new CustomEvent("osint-deck-session", { detail: { loggedIn: false } })
      );
      document.dispatchEvent(new CustomEvent("osint-deck-favorites-changed"));
    }
    if (!btnSso) return;
    btnSso.classList.toggle("osint-sso-btn--authed", !!u);
    if (u && u.name) {
      const initials = u.name.split(" ").map(x => x[0]).join("").substring(0,2).toUpperCase();
      if (u.avatar) {
        btnSso.innerHTML = `<img src="${u.avatar}" alt="${u.name}" width="34" height="34">`;
      } else {
        btnSso.innerHTML = `<span class="osint-sso-initials">${initials}</span>`;
      }
      btnSso.setAttribute("aria-label", u.name);
      btnSso.setAttribute("data-title", formatSsoTemplate(ssoMsg("welcomeTitle") || "%s", u.name));
    } else {
      btnSso.innerHTML = `<img class="osint-sso-google-mark" src="${googleSsoMarkSrc}" width="30" height="30" alt="" decoding="async" loading="eager" />`;
      btnSso.setAttribute("aria-label", ssoMsg("signInAria") || "Acceder con Google");
      btnSso.setAttribute("data-title", ssoMsg("signInTitle") || "Iniciar sesión");
    }
  }
  function startLogin(force) {
    closeReportDialog();
    closeGisFallback();
    if (!ssoCfg.enabled || !btnSso) return;
    const cid = ssoCfg.googleClientId && String(ssoCfg.googleClientId).trim();
    if (!cid) {
      toast(ssoMsg("loginFailed"), SSO_TOAST_MS, "google");
      return;
    }

    const runPrompt = () => {
      initGisOnce();
      if (!window.google || !window.google.accounts || !window.google.accounts.id) {
        return;
      }
      try {
        window.google.accounts.id.prompt((notification) => {
          try {
            if (!notification) return;
            if (notification.isNotDisplayed && notification.isNotDisplayed()) {
              showGoogleSignInFallback();
              return;
            }
            if (notification.isSkippedMoment && notification.isSkippedMoment()) {
              showGoogleSignInFallback();
            }
          } catch (e) {
            showGoogleSignInFallback();
          }
        });
      } catch (e) {
        showGoogleSignInFallback();
      }
    };

    if (window.google && window.google.accounts && window.google.accounts.id) {
      runPrompt();
    } else {
      ensureGis(() => {
        runPrompt();
      });
    }
  }
  async function logout() {
    const data = new URLSearchParams();
    data.append("action", "osint_deck_logout");
    data.append("nonce", ajaxCfg.nonce);
    await window.osintDeckTurnstileAppendToParams(data);
    fetch(ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    })
      .then((r) => r.json())
      .then((out) => {
        if (out && out.success) {
          setUserUI(null);
          if (ssoMenu) {
            ssoMenu.remove();
            ssoMenu = null;
          }
          toast(ssoMsg("loggedOut") || "Te desconectaste.", SSO_TOAST_MS, "google");
        } else {
          toast(authResponseMessage(out, "logoutFailed"), SSO_TOAST_MS, "google");
        }
      })
      .catch(() => {
        toast(ssoMsg("logoutFailed"), SSO_TOAST_MS, "google");
      });
  }
  if (btnSso && ssoCfg.enabled) {
    btnSso.setAttribute("aria-label", ssoMsg("signInAria") || "Acceder con Google");
    btnSso.setAttribute("data-title", ssoMsg("signInTitle") || "");
    const warmGis = () => initGisOnce();
    if (window.google && window.google.accounts && window.google.accounts.id) {
      warmGis();
    } else {
      ensureGis(warmGis);
    }
    btnSso.addEventListener("click", () => {
      if (currentUser) {
        if (ssoMenu) {
          if (ssoMenuCloser) document.removeEventListener("click", ssoMenuCloser);
          ssoMenu.remove();
          ssoMenu = null;
          ssoMenuCloser = null;
        } else {
          renderSsoMenu();
        }
      } else {
        startLogin(false);
      }
    });
    const data = new URLSearchParams();
    data.append("action", "osint_deck_get_user");
    data.append("nonce", ajaxCfg.nonce);
    fetch(ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    }).then(r => r.json()).then((out) => {
      if (out && out.success && out.data && out.data.logged_in) {
        setUserUI(out.data);
      }
    });
  }

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
  function foldAccentsForSearch(str) {
    const s = String(str == null ? "" : str);
    try {
      return s
        .normalize("NFD")
        .replace(/\p{M}/gu, "")
        .toLowerCase();
    } catch (e) {
      return s.toLowerCase();
    }
  }

  function applyFilters() {
    const text = foldAccentsForSearch((q.value || "").trim());
    const detection = detectRichInput(q.value || "");
    updateDetectedMessage(detection.msg || "");

    let filtered = tools.filter((t) => {
      const tipo = foldAccentsForSearch(
        (t.info && t.info.tipo ? t.info.tipo : "").trim()
      );
      const licencia = foldAccentsForSearch(
        (t.info && t.info.licencia ? t.info.licencia : "").trim()
      );
      const acceso = foldAccentsForSearch(
        (t.info && t.info.acceso ? t.info.acceso : "").trim()
      );

      if (currentType && !tipo.includes(foldAccentsForSearch(currentType)))
        return false;
      if (currentLicense && !licencia.includes(foldAccentsForSearch(currentLicense)))
        return false;
      if (currentAccess && !acceso.includes(foldAccentsForSearch(currentAccess)))
        return false;

      // Categoría: se mira en el tool y en las cards
      if (currentCat) {
        let match = false;
        const curNorm = normalizeCategory(currentCat.toLowerCase());

        // 1. Check tool category
        if (t.category) {
          const tCat = t.category.toLowerCase();
          if (
            tCat === currentCat ||
            foldAccentsForSearch(tCat) === foldAccentsForSearch(currentCat)
          ) {
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
            if (
              cat === currentCat ||
              foldAccentsForSearch(cat) === foldAccentsForSearch(currentCat)
            )
              return true;
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
        const lowTag = foldAccentsForSearch(currentTag);
        const toolTags = (t.tags || []).map((x) => foldAccentsForSearch(x));
        const cardTags = (t.cards || []).flatMap((c) =>
          (c.tags || []).map((x) => foldAccentsForSearch(x))
        );
        const allTags = [...toolTags, ...cardTags];
        if (!allTags.some((tg) => tg.includes(lowTag))) return false;
      }

      // Filtrado por detección o búsqueda de texto
      let wanted = [];
      if (detection && detection.type && detection.type !== "none") {
        if (detection.filterIntent && TYPE_MAP[detection.filterIntent]) {
          wanted = TYPE_MAP[detection.filterIntent];
        } else if (detection.type === "keyword" && detection.intent) {
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

        const bag = foldAccentsForSearch(bagParts.join(" "));
        if (!bag.includes(text)) return false;
      }

      return true;
    });

    // Prevent "No results" message if intent is help, because we will show the Help Card. Also skip for greetings.
    const _softIntents = ["help", "greeting", "easter_egg", "crisis", "community", "toxic"];
    if (filtered.length === 0 && (q.value || "").trim().length > 0 && (!detection || !_softIntents.includes(detection.intent))) {
      showDetectedMessage("No se encontraron herramientas para esa consulta. Si crees que es un error, escribi 'ayuda' para reportarlo/notificarnos.");
    }

    // Special logic for Help Card (solo la configurada en admin; no mezclar con mazos por tags ayuda/soporte)
    if (detection && detection.intent === "help") {
       filtered = [];
       const helpData = (typeof osintDeckAjax !== 'undefined' && osintDeckAjax.helpCard) ? osintDeckAjax.helpCard : {};
       const helpCard = {
           isHelpCard: true,
           name: helpData.title || "Soporte OSINT Deck",
           desc: helpData.desc || "¿Encontraste un error o necesitas reportar algo? Contactanos directamente.",
           url: "#", 
           buttons: helpData.buttons || [{ label: "Contactar Soporte", url: "https://osint.com.ar/contacto", icon: "ri-customer-service-2-fill" }],
           category: "Soporte",
           info: { tipo: "Sistema", acceso: "Public", licencia: "Free" },
           cards: [] // Empty cards array to satisfy potential checks elsewhere
       };
       // Ensure it's the only thing or at top
       filtered.unshift(helpCard);
    }

    if (detection && detection.intent === "community") {
      filtered = [];
      const commData =
        typeof osintDeckAjax !== "undefined" && osintDeckAjax.communityCard
          ? osintDeckAjax.communityCard
          : {};
      const defaultCommUrl = "https://osintdeck.github.io/discussions.html";
      const commCard = {
        isHelpCard: true,
        isCommunityCard: true,
        name: commData.title || "Sugerencias y comunidad",
        desc:
          commData.desc ||
          "Proponé herramientas nuevas, ideas de mejora o participá en la comunidad.",
        url: "#",
        buttons:
          Array.isArray(commData.buttons) && commData.buttons.length > 0
            ? commData.buttons
            : [
                { label: "Sugerir una herramienta", url: defaultCommUrl, icon: "ri-add-box-line" },
                { label: "💡 Compartir ideas", url: defaultCommUrl, icon: "ri-lightbulb-line" },
                { label: "❓ Hacer preguntas", url: defaultCommUrl, icon: "ri-question-answer-line" },
                { label: "🤝 Colaborar", url: defaultCommUrl, icon: "ri-team-line" },
              ],
        category: "Comunidad",
        info: { tipo: "Sistema", acceso: "Public", licencia: "Free" },
        cards: [],
      };
      filtered.unshift(commCard);
    }

    if (detection && detection.intent === "crisis") {
      filtered = [];
      const crisisData =
        typeof osintDeckAjax !== "undefined" && osintDeckAjax.crisisCard ? osintDeckAjax.crisisCard : {};
      const defaultCrisisBtns = [
        { label: "Línea 135 — Salud mental y adicciones", url: "tel:135", icon: "ri-phone-line" },
        { label: "Línea 144 — Violencia de género", url: "tel:144", icon: "ri-phone-line" },
        { label: "Emergencias sanitarias (107)", url: "tel:107", icon: "ri-phone-line" },
        {
          label: "Argentina.gob — Salud mental",
          url: "https://www.argentina.gob.ar/salud/mental",
          icon: "ri-health-book-line",
        },
      ];
      const crisisCard = {
        isHelpCard: true,
        isCrisisCard: true,
        name: crisisData.title || "Apoyo emocional",
        desc:
          crisisData.desc ||
          "Si estás en crisis o con ideas de hacerte daño, no estás solo/a. Estos recursos suelen ser gratuitos y confidenciales.",
        url: "#",
        buttons:
          Array.isArray(crisisData.buttons) && crisisData.buttons.length > 0
            ? crisisData.buttons
            : defaultCrisisBtns,
        category: "Recursos",
        info: { tipo: "Sistema", acceso: "Public", licencia: "Free" },
        cards: [],
      };
      filtered.unshift(crisisCard);
    }

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
    if (t.isHelpCard) {
        const crisis = Boolean(t.isCrisisCard);
        const community = Boolean(t.isCommunityCard);
        const deck = document.createElement("div");
        deck.className = crisis
          ? "osint-deck osint-help-deck osint-crisis-deck"
          : community
            ? "osint-deck osint-help-deck osint-community-deck"
            : "osint-deck osint-help-deck";
        
        const card = document.createElement("div");
        card.className = "osint-card layer-0 osint-assist-card";
        card.style.zIndex = "20";
        card.style.border = crisis
          ? "2px solid #c2410c"
          : community
            ? "2px solid #0d9488"
            : "1px solid var(--osint-accent)";
        
        let buttonsHtml = '';
        if (t.buttons && Array.isArray(t.buttons)) {
            buttonsHtml = t.buttons.map(btn => {
                const iconClass = btn.icon || 'ri-external-link-line';
                const href = btn.url || "#";
                const blank = /^tel:/i.test(href) ? "" : ` target="_blank" rel="noopener"`;
                
                let iconHtml = '';
                if (iconClass.includes('dashicons')) {
                    iconHtml = `<span class="${esc(iconClass)} osint-btn-icon"></span>`;
                } else {
                    iconHtml = `<i class="${esc(iconClass)} osint-btn-icon"></i>`;
                }

                return `
                    <a href="${esc(href)}" class="osint-btn-animated"${blank} style="width:100%; justify-content: center; margin-bottom: 8px;">
                        <span class="text">${esc(btn.label)}</span>
                        ${iconHtml}
                    </a>
                `;
            }).join('');
        }

        const catIcon = crisis ? "ri-heart-pulse-line" : community ? "ri-discuss-line" : "ri-question-line";
        const catLabel = crisis ? "Apoyo" : community ? "Comunidad" : "Soporte";
        const favIcon = crisis ? "ri-heart-pulse-fill" : community ? "ri-lightbulb-flash-line" : "ri-customer-service-2-fill";
        const favBg = crisis ? "#c2410c" : community ? "#0d9488" : "var(--osint-accent)";

        card.innerHTML = `
            <button class="osint-card-close" type="button" aria-label="Cerrar" style="position: absolute; top: 12px; right: 12px; background: transparent; border: none; color: var(--osint-ink-sub); cursor: pointer; padding: 4px; z-index: 30;">
                <i class="ri-close-line" style="font-size: 20px;"></i>
            </button>
            <div class="osint-card-hdr">
                 <div class="osint-top-row">
                    <div class="osint-fav">
                        <div style="width:32px;height:32px;border-radius:50%;background:${favBg};display:flex;align-items:center;justify-content:center;color:#fff;">
                            <i class="${favIcon}"></i>
                        </div>
                    </div>
                    <div class="osint-title-wrap">
                        <h4 class="osint-ttl">${esc(t.name)}</h4>
                        <div class="osint-category-label"><i class="${catIcon}"></i> ${catLabel}</div>
                    </div>
                 </div>
            </div>
            <div class="osint-sub osint-main-desc">
                ${esc(t.desc)}
            </div>
            <div class="osint-deck-footer">
                <div class="osint-deck-footer-actions" style="flex-direction: column; width: 100%;">
                     ${buttonsHtml}
                </div>
            </div>
        `;
        deck.appendChild(card);
        
        // Add event listener to close button
        const closeBtn = deck.querySelector('.osint-card-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                q.value = "";
                onInput();
            });
        }

        return deck;
    }

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
      deck.appendChild(card);
    }

    /* Máx. capas visibles: debe alinearse con las clases .layer-1 … .layer-8 en CSS */
    const edgeLayers = Math.min(stackCount - 1, 8);
    for (let i = 1; i <= edgeLayers; i++) {
      const edge = document.createElement("div");
      edge.className = `osint-card layer-${i}`;
      edge.style.zIndex = String(10 + stackCount - i);
      deck.appendChild(edge);
    }

    toolByDeck.set(deck, t);

    return deck;
  }

  function pickPrimaryCard(cards, detection) {
    if (!Array.isArray(cards) || !cards.length) return cards && cards[0];

    let type = "";
    if (detection && detection.filterIntent) {
      type = detection.filterIntent;
    } else if (detection && detection.type) {
      type = detection.type;
      if (type === "keyword" && detection.intent) {
        type = detection.intent;
      }
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
    const stats = (t && t.stats) || {};
    const likes = parseInt(stats.likes || 0);
    const clicks = parseInt(stats.clicks || 0);
    const tid = Number(toolPrimaryId(t)) || 0;
    const favOn = tid > 0 && ssoCfg.enabled && osintDeckAuthLoggedIn && userFavoriteIdSet.has(tid);
    const likeOn = tid > 0 && userLikedIdSet.has(tid);
    const repOn = tid > 0 && userReportedIdSet.has(tid);
    const reportMsgs =
      typeof osintDeckAjax !== "undefined" && osintDeckAjax.reports ? osintDeckAjax.reports : {};
    const repTitle = repOn
      ? reportMsgs.tooltipOn || "Quitar reporte"
      : reportMsgs.tooltipOff || "Reportar herramienta";

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
           <button type="button" class="osint-act-like${likeOn ? " liked" : ""}" data-tool-id="${tid || ""}" data-title="Me gusta">
              <i class="${likeOn ? "ri-heart-fill" : "ri-heart-line"}"></i> <span class="count">${likes}</span>
           </button>
           <button type="button" class="osint-act-favorite${favOn ? " favorited" : ""}" data-tool-id="${tid || ""}" data-title="Favorito">
              <i class="${favOn ? "ri-star-fill" : "ri-star-line"}"></i>
           </button>
           <span class="osint-stat-clicks" data-title="Usos">
              <i class="ri-bar-chart-line"></i> <span class="count">${clicks}</span>
           </span>
           <button type="button" class="osint-report${repOn ? " reported" : ""}" data-tool-id="${tid || ""}" data-title="${repTitle.replace(/"/g, "&quot;")}">
              <i class="${repOn ? "ri-flag-fill" : "ri-flag-line"}"></i>
           </button>
        </div>
      </div>
    `;
  }

  function attachActionEvents(card, urlTemplate, tool, detection) {
    const isKeyword = (detection && detection.type === 'keyword');
    // If it's a keyword intent, we ignore the value unless specifically set (which we set to null above).
    // If detection.value is present (e.g. extracted entity), we use it.
    // Fallback to q.value ONLY if it's not a keyword intent (to avoid "Analizar dominios").
    let inputVal = (detection && detection.value) ? detection.value : "";
    
    if (!inputVal && !isKeyword) {
        inputVal = (q.value || "").trim();
    }
    
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
            
            sendEvent("like", { tool_id: toolPrimaryId(tool) }).then((res) => {
                const tid = Number(toolPrimaryId(tool)) || 0;
                if (res && res.ok) {
                    const cnt = likeBtn.querySelector(".count");
                    if (cnt && res.count !== undefined) cnt.textContent = res.count;
                    const icon = likeBtn.querySelector("i");
                    if (res.liked) {
                      if (tid) userLikedIdSet.add(tid);
                      likeBtn.classList.add("liked");
                      if (icon) icon.className = "ri-heart-fill";
                      toast(likeMsgs.on || "Te gusta esta herramienta");
                    } else {
                      if (tid) userLikedIdSet.delete(tid);
                      likeBtn.classList.remove("liked");
                      if (icon) icon.className = "ri-heart-line";
                      toast(likeMsgs.off || "Quitaste tu me gusta");
                    }
                    if (!osintDeckAuthLoggedIn) {
                      writeLocalLikedIdsFromSet();
                    }
                    syncLikeButtonsInDom();
                } else {
                    toast((res && res.message) || likeMsgs.error || "Error al registrar like");
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
            if (!ssoCfg.enabled) {
              toast(favMsgs.ssoDisabled);
              return;
            }
            if (!osintDeckAuthLoggedIn) {
              toast(favMsgs.needLogin);
              return;
            }
            favBtn.disabled = true;

            sendEvent("favorite", { tool_id: toolPrimaryId(tool) }).then((res) => {
                const tid = Number(toolPrimaryId(tool)) || 0;
                if (res && res.ok) {
                    const icon = favBtn.querySelector("i");
                    if (res.favorited) {
                      if (tid) userFavoriteIdSet.add(tid);
                      favBtn.classList.add("favorited");
                      if (icon) icon.className = "ri-star-fill";
                      toast("Añadido a favoritos");
                    } else {
                      if (tid) userFavoriteIdSet.delete(tid);
                      favBtn.classList.remove("favorited");
                      if (icon) icon.className = "ri-star-line";
                      toast("Quitado de favoritos");
                    }
                    syncFavoriteButtonsInDom();
                } else {
                    toast((res && res.message) || favMsgs.favUpdateError || "Error al actualizar favoritos");
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
            textSpan.textContent = "Abrir";
            goBtn.title = "Abrir herramienta en nueva pestaña";
        }
      }

      const url = builtUrl();
      if (url && url !== "#") {
        goBtn.href = url;
        goBtn.classList.remove("is-disabled");

        goBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          const fromSheet = card.classList.contains("osint-card--sheet");
          if (fromSheet) {
            return;
          }
          sendEvent("click_tool", {
            tool_id: toolPrimaryId(tool),
            input_type: (detection && detection.type) || "",
            input_value: inputVal || (q.value || "").trim(),
          }).then(res => {
            if (res.ok && res.count !== undefined) {
                 const clickCountEl = card.querySelector(".osint-stat-clicks .count");
                 if (clickCountEl) {
                     clickCountEl.textContent = res.count;
                 }
            }
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
      const isSheetCard = card.classList.contains("osint-card--sheet");
      const shareWrap = card.querySelector(".osint-share-wrapper");

      const clearSheetShareMenuFixed = () => {
        if (!isSheetCard) return;
        shareMenu.style.removeProperty("position");
        shareMenu.style.removeProperty("top");
        shareMenu.style.removeProperty("left");
        shareMenu.style.removeProperty("right");
        shareMenu.style.removeProperty("bottom");
        shareMenu.style.removeProperty("z-index");
        shareMenu.style.removeProperty("visibility");
        shareMenu.style.removeProperty("display");
      };

      const repositionSheetShareMenu = () => {
        if (!isSheetCard || disableActions) return;
        const open =
          shareMenu.classList.contains("active") ||
          (shareWrap && shareWrap.matches(":hover"));
        if (!open) {
          clearSheetShareMenuFixed();
          return;
        }
        const r = shareBtn.getBoundingClientRect();
        const prevVis = shareMenu.style.visibility;
        shareMenu.style.visibility = "hidden";
        shareMenu.style.display = "flex";
        const mw = Math.max(shareMenu.offsetWidth, 140);
        const mh = Math.max(shareMenu.offsetHeight, 72);
        if (prevVis) shareMenu.style.visibility = prevVis;
        else shareMenu.style.removeProperty("visibility");
        const gap = 8;
        let left = r.right - mw;
        let top = r.top - mh - gap;
        left = Math.min(Math.max(8, left), window.innerWidth - mw - 8);
        top = Math.min(Math.max(8, top), window.innerHeight - mh - 8);
        shareMenu.style.position = "fixed";
        shareMenu.style.left = `${Math.round(left)}px`;
        shareMenu.style.top = `${Math.round(top)}px`;
        shareMenu.style.right = "auto";
        shareMenu.style.bottom = "auto";
        shareMenu.style.zIndex = "2147483646";
      };

      const syncShareSheetLift = () => {
        if (!isSheetCard) return;
        const open =
          shareMenu.classList.contains("active") ||
          (shareWrap && shareWrap.matches(":hover"));
        card.classList.toggle("osint-card--share-lift", open);
        window.requestAnimationFrame(() => {
          window.requestAnimationFrame(repositionSheetShareMenu);
        });
      };

      if (isSheetCard) {
        const onScrollResize = () => window.requestAnimationFrame(repositionSheetShareMenu);
        const ov = card.closest(".osint-overlay");
        window.addEventListener("resize", onScrollResize);
        if (ov) {
          ov.addEventListener("scroll", onScrollResize, true);
        }
        if (typeof card._osdSheetShareCleanup === "function") {
          card._osdSheetShareCleanup();
        }
        card._osdSheetShareCleanup = () => {
          window.removeEventListener("resize", onScrollResize);
          if (ov) {
            ov.removeEventListener("scroll", onScrollResize, true);
          }
          clearSheetShareMenuFixed();
        };
      }

      shareBtn.classList.toggle("is-disabled", disableActions);
      if (disableActions) {
        shareMenu.classList.remove("active");
        card.classList.remove("osint-card--share-lift");
        clearSheetShareMenuFixed();
        if (typeof card._osdSheetShareCleanup === "function") {
          card._osdSheetShareCleanup();
          delete card._osdSheetShareCleanup;
        }
      }

      shareBtn.addEventListener("click", (e) => {
        if (disableActions) return;
        e.stopPropagation();
        shareMenu.classList.toggle("active");
        syncShareSheetLift();
      });

      if (shareWrap && isSheetCard) {
        shareWrap.addEventListener("mouseenter", syncShareSheetLift);
        shareWrap.addEventListener("mouseleave", () => {
          window.requestAnimationFrame(syncShareSheetLift);
        });
      }

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
          syncShareSheetLift();
        });
      });

      document.addEventListener("click", (e) => {
        if (!card.contains(e.target)) {
          shareMenu.classList.remove("active");
          syncShareSheetLift();
        }
      });
    }

    const reportBtn = card.querySelector(".osint-report");
    if (reportBtn) {
      reportBtn.classList.toggle("is-disabled", disableActions);
      reportBtn.classList.toggle("osint-hidden", disableActions);
      const reportMsgs =
        typeof osintDeckAjax !== "undefined" && osintDeckAjax.reports ? osintDeckAjax.reports : {};

      reportBtn.addEventListener("click", (e) => {
        if (disableActions) return;
        e.stopPropagation();
        const tid = Number(toolPrimaryId(tool)) || 0;
        if (!tid) return;

        const isOn = userReportedIdSet.has(tid);

        const runSubmit = (messageRaw) => {
          if (reportBtn.disabled) return;
          reportBtn.disabled = true;
          const payload = {
            tool_id: tid,
            input_type: (detection && detection.type) || "",
            input_value: inputVal,
          };
          const safeMsg = sanitizeReportMessageClient(messageRaw);
          if (safeMsg) payload.report_message = safeMsg;

          sendEvent("report_tool", payload)
            .then((res) => {
              if (res && res.ok) {
                if (res.reported) {
                  userReportedIdSet.add(tid);
                  toast(reportMsgs.reportedOn || "Reporte registrado.");
                  if (!osintDeckAuthLoggedIn) writeLocalReportedIdsFromSet();
                } else if (res.removed) {
                  userReportedIdSet.delete(tid);
                  toast(reportMsgs.reportedOff || "Quitaste el reporte.");
                  if (!osintDeckAuthLoggedIn) writeLocalReportedIdsFromSet();
                }
                syncReportButtonsInDom();
              } else {
                toast((res && res.message) || reportMsgs.error || "No se pudo actualizar el reporte.");
              }
            })
            .finally(() => {
              reportBtn.disabled = false;
            });
        };

        if (isOn) {
          openReportDialog({
            title: reportMsgs.dlgTitleRemove || "Quitar reporte",
            desc: reportMsgs.confirmToggleOff,
            showTextarea: false,
            showSecondary: false,
            primaryText: reportMsgs.btnRemoveConfirm || "Sí, quitar reporte",
            onPrimary: () => runSubmit(""),
          });
          return;
        }

        openReportDialog({
          title: reportMsgs.dlgTitleReport || "Reportar herramienta",
          desc: reportMsgs.confirmReport,
          showTextarea: false,
          showSecondary: false,
          primaryText: reportMsgs.btnContinue || "Continuar",
          onPrimary: () => {
            if (osintDeckAuthLoggedIn) {
              openReportDialog({
                title: reportMsgs.dlgTitleComment || "Comentario opcional",
                desc: reportMsgs.commentHint,
                showTextarea: true,
                textareaLabel: reportMsgs.commentLabel || reportMsgs.askComment,
                showSecondary: true,
                secondaryText: reportMsgs.btnNoComment || "Sin comentario",
                primaryText: reportMsgs.btnSend || "Enviar reporte",
                onSecondary: () => runSubmit(""),
                onPrimary: (msg) => runSubmit(msg),
              });
            } else {
              openReportDialog({
                title: reportMsgs.dlgTitleReport || "Reportar herramienta",
                desc: reportMsgs.askComment,
                showTextarea: false,
                showSecondary: true,
                secondaryText: reportMsgs.btnNeedLoginForComment || "Quiero dejar un comentario",
                primaryText: reportMsgs.btnAnonOnlyReport || "Solo reportar",
                onSecondary: () => {
                  openReportDialog({
                    title: reportMsgs.dlgTitleNeedLogin || "Comentario con cuenta",
                    desc: reportMsgs.loginForComment,
                    showTextarea: false,
                    showSecondary: false,
                    primaryText: reportMsgs.btnLogin || "Iniciar sesión",
                    onPrimary: () => {
                      if (ssoCfg.enabled) {
                        startLogin(false);
                      }
                    },
                  });
                },
                onPrimary: () => runSubmit(""),
              });
            }
          },
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

    filteredCache =
      filterPopularOnly && !ignoreFilters && osintDeckAuthLoggedIn
      ? (list || []).filter((t) => {
          if (t.isHelpCard) return true;
          const id = Number(toolPrimaryId(t));
          return id > 0 && userFavoriteIdSet.has(id);
        })
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
      (async () => {
        const data = new URLSearchParams();
        data.append("action", "osint_deck_report_block");
        data.append("nonce", ajaxCfg.nonce || "");
        data.append("url", url);
        await window.osintDeckTurnstileAppendToParams(data);
        fetch(ajaxCfg.url, {
            method: "POST",
            body: data
        }).catch(err => console.error("Report block failed", err));
      })();
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
    data.append("nonce", ajaxCfg.nonce || "");
    data.append("urls", JSON.stringify(uniqueUrls));

    (async () => {
      await window.osintDeckTurnstileAppendToParams(data);
      fetch(ajaxUrl, {
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
    })();
  }

  /* =========================================================
   * MODAL (SHEET)
   * ========================================================= */
  function cardCategoryLine(c) {
    const raw = c && c.category && String(c.category).trim();
    if (raw) return raw;
    const code = (c && c.category_code) || "";
    if (!code || typeof code !== "string") return "";
    return code
      .split("__")
      .filter(Boolean)
      .map((part) => part.replace(/_/g, " "))
      .join(" / ");
  }

  function buildCardRichDescription(c, parentTool) {
    const parts = [];
    const base = ((c && c.desc) || "").trim();
    if (base) parts.push(base);
    const ctx = (c && c.osint_context) || {};
    const up = (ctx.uso_principal || "").trim();
    if (up && up !== base) parts.push(up);
    if (Array.isArray(ctx.fase_osint) && ctx.fase_osint.length) {
      parts.push(
        "Fases OSINT recomendadas: " +
          ctx.fase_osint.map((x) => String(x)).join(", ")
      );
    }
    const nivel = (ctx.nivel_tecnico || "").trim();
    if (nivel) parts.push("Nivel técnico: " + nivel + ".");
    const input = c && c.input;
    if (input && Array.isArray(input.types) && input.types.length) {
      const types = input.types.filter((x) => x && x !== "none");
      if (types.length) {
        let line =
          "Entradas admitidas: " + types.map((x) => String(x)).join(", ");
        if (input.example) line += ". Ejemplo: " + String(input.example);
        if (input.mode === "manual")
          line +=
            ". Esta acción se usa sobre todo de forma manual en el sitio del proveedor.";
        parts.push(line);
      }
    }
    if (!parts.length && parentTool) {
      const pctx = parentTool.osint_context || {};
      const pup = (pctx.uso_principal || "").trim();
      if (pup) parts.push(pup);
    }
    return parts.join("\n\n");
  }

  function buildToolHeaderDescription(tool) {
    const parts = [];
    const base = ((tool && tool.desc) || "").trim();
    if (base) parts.push(base);
    const ctx = (tool && tool.osint_context) || {};
    const up = (ctx.uso_principal || "").trim();
    if (up && up !== base) parts.push(up);
    if (Array.isArray(ctx.fase_osint) && ctx.fase_osint.length) {
      parts.push(
        "Ideal para las fases " + ctx.fase_osint.join(", ") + " del ciclo OSINT."
      );
    }
    const nivel = (ctx.nivel_tecnico || "").trim();
    if (nivel) parts.push("Audiencia: " + nivel + ".");
    return parts.join("\n\n");
  }

  function openModal(tool) {
    try {
      if (!overlay || !sheet || !sheetTitle || !sheetGrid) {
        console.error("[OSINT Deck] Faltan nodos del modal");
        return;
      }

    const detOpen = detectRichInput(q.value || "");
    const inputOpen = (detOpen && detOpen.value) ? detOpen.value : (q.value || "").trim();
    sendEvent("click_tool", {
      tool_id: toolPrimaryId(tool),
      input_type: (detOpen && detOpen.type) || "",
      input_value: inputOpen,
    }).then((res) => {
      if (res && res.ok && res.count !== undefined && grid) {
        grid.querySelectorAll(".osint-deck").forEach((deck) => {
          if (toolByDeck.get(deck) === tool) {
            const clickCountEl = deck.querySelector(".osint-stat-clicks .count");
            if (clickCountEl) clickCountEl.textContent = res.count;
          }
        });
      }
    });

    sheetTitle.textContent = tool.name || "";

    if (sheetFav) {
      const firstCardUrl =
        (tool.cards && tool.cards[0] && tool.cards[0].url) || "";
      const favUrl =
        tool.favicon ||
        "https://www.google.com/s2/favicons?sz=64&domain=" +
          domainFromUrl(firstCardUrl);
      sheetFav.src = favUrl;
      sheetFav.alt = tool.name || "Logo";
    }

    if (sheetSub) {
      const cat = (tool.category || "").trim();
      const acc =
        tool.info && tool.info.acceso
          ? String(tool.info.acceso).trim()
          : "";
      const crumbs = [];
      if (cat) {
        crumbs.push(
          `<span class="osint-sheet-crumb"><i class="ri-bookmark-3-line" aria-hidden="true"></i>${esc(cat)}</span>`
        );
      }
      if (acc) {
        crumbs.push(
          `<span class="osint-sheet-crumb"><i class="ri-door-open-line" aria-hidden="true"></i>${esc(acc)}</span>`
        );
      }
      sheetSub.innerHTML = crumbs.length
        ? `<div class="osint-category-label osint-sheet-crumbs">${crumbs.join("")}</div>`
        : "";
    }

    if (sheetDesc) {
      const hdrDesc = buildToolHeaderDescription(tool);
      const blocks = hdrDesc
        .split(/\n\n+/)
        .filter(Boolean)
        .map((p) => `<p class="osint-sheet-desc-block">${esc(p.trim())}</p>`)
        .join("");
      sheetDesc.innerHTML =
        blocks ||
        `<p class="osint-sheet-desc-block">${esc(tool.desc || "")}</p>`;
    }

    if (sheetMeta) {
      let accessIcon = "";
      let accClass = "osd-badge-red";
      if (tool.info && tool.info.acceso) {
        const accLow = String(tool.info.acceso).toLowerCase();
        if (accLow.includes("gratis") || accLow.includes("free")) {
          accessIcon = '<i class="ri-lock-unlock-line"></i>';
          accClass = "osd-badge-green";
        } else if (accLow.includes("pago") || accLow.includes("paid")) {
          accessIcon = '<i class="ri-vip-crown-line"></i>';
        } else {
          accessIcon = '<i class="ri-key-2-line"></i>';
        }
      }

      const badgeParts = [];
      if (tool.info && tool.info.tipo) {
        badgeParts.push(
          `<span class="osd-badge osd-badge-blue osd-filter" data-key="type" data-value="${esc(tool.info.tipo)}"><i class="ri-global-line"></i> ${esc(tool.info.tipo)}</span>`
        );
      }
      if (tool.info && tool.info.licencia) {
        badgeParts.push(
          `<span class="osd-badge osd-badge-yellow osd-filter" data-key="license" data-value="${esc(tool.info.licencia)}"><i class="ri-code-box-line"></i> ${esc(tool.info.licencia)}</span>`
        );
      }
      if (tool.info && tool.info.acceso) {
        badgeParts.push(
          `<span class="osd-badge ${accClass} osd-filter" data-key="access" data-value="${esc(tool.info.acceso)}">${accessIcon} ${esc(tool.info.acceso)}</span>`
        );
      }

      const tags =
        Array.isArray(tool.tags) && tool.tags.length
          ? `<div class="osint-tags osint-sheet-tool-tags"><span class="osint-sheet-tags-label">Etiquetas</span>${tool.tags
              .map(
                (x) =>
                  `<span class="osd-chip osd-tag" data-tag="${esc(x)}"><i class="ri-price-tag-3-line"></i>${esc(x)}</span>`
              )
              .join("")}</div>`
          : "";

      const badgesWrap = badgeParts.length
        ? `<div class="osd-meta-badges osint-sheet-meta-badges">${badgeParts.join("")}</div>`
        : "";

      sheetMeta.innerHTML = badgesWrap + tags;

      sheetMeta.querySelectorAll(".osd-filter").forEach((badge) => {
        badge.addEventListener("click", (e) => {
          e.stopPropagation();
          const key = badge.dataset.key || "";
          const val = badge.dataset.value || "";
          setFilter(key, val, true);
          closeModal();
        });
      });

      sheetMeta.querySelectorAll(".osd-tag").forEach((tagEl) => {
        tagEl.addEventListener("click", (e) => {
          e.stopPropagation();
          const tg = tagEl.dataset.tag || "";
          setFilter("tag", tg, false);
          closeModal();
        });
      });
    }

    sheetGrid.querySelectorAll(".osint-card--sheet").forEach((c) => {
      if (typeof c._osdSheetShareCleanup === "function") {
        try {
          c._osdSheetShareCleanup();
        } catch (e) {
          /* ignore */
        }
        delete c._osdSheetShareCleanup;
      }
    });
    sheetGrid.innerHTML = "";

    const cards = Array.isArray(tool.cards) ? tool.cards : [];
    let stack = [];
    if (cards.length) {
      const primaryCard = pickPrimaryCard(cards, detOpen);
      stack = [primaryCard, ...cards.filter((c) => c !== primaryCard)].filter(
        Boolean
      );
    } else if (tool.primary && typeof tool.primary === "object") {
      stack = [tool.primary];
    }
    const primary = stack[0] || {};

    stack.forEach((c, i) => {
      const card = document.createElement("div");
      card.className = "osint-card osint-card--sheet";
      /* Evita que el tema u otras hojas dejen .osint-card en absolute y colapsen el grid */
      card.style.setProperty("position", "relative", "important");
      card.style.setProperty("top", "auto", "important");
      card.style.setProperty("left", "auto", "important");
      card.style.setProperty("right", "auto", "important");
      card.style.setProperty("bottom", "auto", "important");
      card.style.setProperty("inset", "auto", "important");
      /* No fijar z-index inline: impide elevar la carta con CSS cuando el menú compartir está abierto. */
      card.style.removeProperty("z-index");
      card.style.setProperty("transform", "none", "important");

      const subFav =
        tool.favicon ||
        "https://www.google.com/s2/favicons?sz=64&domain=" +
          domainFromUrl(c.url || primary.url || "");

      const catLine = cardCategoryLine(c);
      const catHtml = catLine
        ? `<div class="osint-category-label"><i class="ri-global-line"></i> ${esc(catLine)}</div>`
        : "";

      const richDesc = buildCardRichDescription(c, tool);
      const descHtml = richDesc
        .split(/\n\n+/)
        .filter(Boolean)
        .map((p) => `<p class="osint-sheet-desc-block">${esc(p.trim())}</p>`)
        .join("");

      const tagList = Array.isArray(c.tags) ? c.tags : [];
      const tagsChips =
        tagList.length > 0
          ? `<div class="osint-mini-meta osint-mini-meta--sheet-tags">${tagList
              .slice(0, 8)
              .map(
                (x) =>
                  `<span class="osd-chip osd-tag" data-tag="${esc(String(x))}"><i class="ri-price-tag-3-line"></i> ${esc(String(x))}</span>`
              )
              .join("")}</div>`
          : "";

      card.innerHTML = `
        <div class="osint-card-hdr">
          <div class="osint-top-row">
            <div class="osint-fav">
              <img alt="" src="${esc(subFav)}">
            </div>
            <div class="osint-title-wrap">
              <h4 class="osint-ttl">${esc(c.title || "Acción")}</h4>
              ${catHtml}
            </div>
          </div>
          ${tagsChips}
        </div>
        <div class="osint-sub osint-main-desc osint-main-desc--sheet">${
          descHtml ||
          `<p class="osint-sheet-desc-block">${esc((c.desc || "").trim())}</p>`
        }</div>
        <div class="osint-deck-footer">
          <div class="osint-deck-footer-actions">
            ${renderActions(tool)}
          </div>
        </div>
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

    overlay.classList.add("osint-overlay--open");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    sheet.style.opacity = "1";
    sheet.style.transform = "translateY(0)";
    lastFocused = document.activeElement;
    const focusables = sheet.querySelectorAll(focusableSelector);
    if (focusables.length) {
      focusables[0].focus();
    }
    } catch (err) {
      console.error("[OSINT Deck] openModal:", err);
    }
  }

  function closeModal() {
    if (!overlay || !overlay.classList.contains("osint-overlay--open")) return;
    overlay.classList.remove("osint-overlay--open");
    overlay.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
    sheet.style.opacity = "";
    sheet.style.transform = "";
    if (sheetGrid) {
      sheetGrid.querySelectorAll(".osint-card--sheet").forEach((c) => {
        if (typeof c._osdSheetShareCleanup === "function") {
          try {
            c._osdSheetShareCleanup();
          } catch (e) {
            /* ignore */
          }
          delete c._osdSheetShareCleanup;
        }
      });
    }
    if (lastFocused && typeof lastFocused.focus === "function") {
      lastFocused.focus();
    }
  }

  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) closeModal();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeModal();
    if (e.key === "Tab" && overlay && overlay.classList.contains("osint-overlay--open")) {
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
    el.innerHTML = msg || "";
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

  async function searchBackend(query) {
    if (!ajaxCfg.url || !ajaxCfg.nonce) {
        console.warn("OSINT Deck: Missing AJAX config", ajaxCfg);
        return Promise.resolve({ success: false });
    }
    const data = new URLSearchParams();
    data.append("action", "osint_deck_search");
    data.append("nonce", ajaxCfg.nonce);
    data.append("query", query);

    await window.osintDeckTurnstileAppendToParams(data);

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

    // No llamar searchBackend para help/crisis/community/easter_egg: applyFilters ya dejó la tarjeta correcta; el AJAX pisa la UI con mazos del catálogo.
    const isAmbiguous =
      d.type === "none" ||
      d.type === "generic" ||
      (d.type === "keyword" &&
        (!d.intent || ["greeting", "toxic"].includes(d.intent)) &&
        !["help", "crisis", "community", "easter_egg"].includes(d.intent)) ||
      d.type === "fullname";

    if (val.length > 2 && isAmbiguous) {
      const snapshot = val;
      try {
        const res = await searchBackend(snapshot);
        if ((q.value || "").trim() !== snapshot) {
          return;
        }
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
      if (!ssoCfg.enabled) {
        toast(favMsgs.ssoDisabled);
        return;
      }
      if (!osintDeckAuthLoggedIn) {
        toast(favMsgs.needLoginFilter);
        return;
      }
      filterPopularOnly = !filterPopularOnly;
      popularToggle.classList.toggle("active", filterPopularOnly);
      const icon = popularToggle.querySelector("i");
      if (icon) {
        icon.className = filterPopularOnly ? "ri-star-fill" : "ri-star-line";
      }
      applyFilters();
    });
  }

  document.addEventListener("osint-deck-session", (ev) => {
    if (ev.detail && ev.detail.loggedIn) return;
    if (!filterPopularOnly) return;
    filterPopularOnly = false;
    if (popularToggle) {
      popularToggle.classList.remove("active");
      const icon = popularToggle.querySelector("i");
      if (icon) icon.className = "ri-star-line";
    }
    applyFilters();
  });

  const favClearBtn = document.getElementById(`${uid}-fav-clear-all`);
  if (favClearBtn) {
    favClearBtn.addEventListener("click", () => clearAllFavorites());
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
  syncFavoriteButtonsInDom();
  syncLikeButtonsInDom();
  syncReportButtonsInDom();
  q.addEventListener("input", debounce(onInput, 500));
  toggleSmart();
}

function bootOsintDecks() {
  console.log("OSINT Deck v0.1.1 loaded (Multi-instance supported)");
  initToast();
  document.querySelectorAll(".osint-wrap[id]").forEach(initOsintDeck);
  if (!osintDeckAuthLoggedIn) {
    osintDeckFetchReportStateAndMerge().then((data) => {
      if (data && Array.isArray(data.reported_tool_ids)) {
        userReportedIdSet.clear();
        data.reported_tool_ids.forEach((id) => userReportedIdSet.add(Number(id)));
        writeLocalReportedIdsFromSet();
      }
      if (data && Array.isArray(data.thanks_tool_ids) && data.thanks_tool_ids.length) {
        data.thanks_tool_ids.forEach((id) => {
          const n = Number(id);
          if (n > 0) reportThanksToolIdsQueue.push(n);
        });
        reportThanksToolIdsQueue = [...new Set(reportThanksToolIdsQueue)];
      }
      syncReportButtonsInDom();
      osintDeckFlushReportThanksToasts();
    });
  } else {
    osintDeckFlushReportThanksToasts();
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", bootOsintDecks);
} else {
  bootOsintDecks();
}
