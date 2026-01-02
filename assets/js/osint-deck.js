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

document.addEventListener("DOMContentLoaded", function () {
  const wrap = document.querySelector(".osint-wrap[id]");
  if (!wrap) return;

  const FILTER_LABELS = {
    type: "Tipo",
    access: "Acceso",
    license: "Licencia",
    category: "Categoría",
    tag: "Tag"
  };

  const uid = wrap.getAttribute("id") || "";
  const cfg =
    (window.OSINT_DECK_DATA && window.OSINT_DECK_DATA[uid]) || {};

  let tools = [];
  try {
    tools = JSON.parse(wrap.getAttribute("data-tools") || "[]");
  } catch (e) {
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
                aria-label="Mostrar/ocultar filtros">
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
      
      <!-- CONTADOR -->
      <div class="osint-counter" id="${uid}-counter" style="margin-left: auto; font-size: 0.85em; opacity: 0.7; align-self: center;"></div>
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
   * UTILIDADES
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

  const ajaxCfg = window.OSINT_DECK_AJAX || {};
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
   * POBLAR MENÚS DE FILTRO (TIPO / ACCESO / LICENCIA / CATEGORÍA)
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
  const filtersBarRef = document.getElementById(`${uid}-filters`);
  const counterRef = document.getElementById(`${uid}-counter`);

  let showFilters = false;

  /* Toast */
  const toastEl = document.createElement("div");
  toastEl.className = "osint-toast";
  document.body.appendChild(toastEl);
  let toastTimer;

  function toast(text) {
    if (!text) return;
    const el = toastEl;
    el.textContent = text;
    el.classList.remove("show");
    void el.offsetWidth;
    el.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove("show"), 1800);
  }

  /* Tooltip detección de entrada */
  let tooltipEl, tooltipTimer;

  function showTooltip(text) {
    if (!text) return;
    if (!tooltipEl) {
      tooltipEl = document.createElement("div");
      tooltipEl.className = "osd-tooltip";
      document.body.appendChild(tooltipEl);
    }
    tooltipEl.innerHTML = `<span>${text}</span>`;
    tooltipEl.classList.add("show");
    clearTimeout(tooltipTimer);
    tooltipTimer = setTimeout(() => {
      if (!tooltipEl.matches(":hover")) tooltipEl.classList.remove("show");
    }, 3000);
    tooltipEl.onmouseenter = () => clearTimeout(tooltipTimer);
    tooltipEl.onmouseleave = () => {
      tooltipTimer = setTimeout(() => tooltipEl.classList.remove("show"), 1000);
    };
  }

  /* =========================================================
   * MOSTRAR / OCULTAR FILTROS
   * ========================================================= */
  filtersBarRef.style.display = "none";

  function ensureFiltersVisible() {
    showFilters = true;
    filtersBarRef.style.display = "flex";
  }

  toggleFiltersBtn.addEventListener("click", () => {
    showFilters = !showFilters;
    filtersBarRef.style.display = showFilters ? "flex" : "none";
  });

  /* =========================================================
   * DETECCIÓN DE TIPO DE ENTRADA
   * ========================================================= */
  function detect(v) {
    const s = (v || "").trim();
    if (!s)
      return {
        type: "none",
        msg: ""
      };

    if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(s))
      return {
        type: "email",
        msg: "📧 Análisis de correos y correlación disponible."
      };

    if (
      /^https?:\/\//i.test(s) ||
      /^(?=.{1,253}$)(?!-)(?:[a-z0-9-]+\.)+[a-z]{2,}$/i.test(s)
    )
      return {
        type: "domain",
        msg: "🌐 Herramientas para DNS/WHOIS y vínculos asociados."
      };

    if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(s))
      return {
        type: "ipv4",
        msg: "🔢 Infraestructura y proveedores."
      };

    if (/^[a-f0-9]{32,64}$/i.test(s))
      return {
        type: "hash",
        msg: "🧩 Verificación y análisis forense."
      };

    if (
      /^@[a-z0-9_.-]{2,32}$/i.test(s) ||
      /^[a-z0-9_.-]{3,32}$/i.test(s)
    )
      return {
        type: "username",
        msg: "👤 Investigación de usuarios."
      };

    return {
      type: "generic",
      msg: "🔎 Búsqueda general."
    };
  }

  /* =========================================================
   * BOTÓN PEGAR / LIMPIAR
   * ========================================================= */
  function setSmartIcon(mode) {
    const icon = btnSmart.querySelector(".osint-icon");
    icon.setAttribute("data-mode", mode);
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

      // Categoría: se mira en las cards
      if (currentCat) {
        const matchCat = (t.cards || []).some((c) => {
          const cat = (c.category || "").toLowerCase();
          if (cat === currentCat) return true;
          const norm = normalizeCategory(cat.toLowerCase());
          const curNorm = normalizeCategory(currentCat.toLowerCase());
          if (norm.parent && norm.parent === curNorm.parent && !curNorm.child)
            return true;
          return false;
        });
        if (!matchCat) return false;
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

      // Búsqueda libre por texto
      if (text) {
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
   * SET FILTER (USADO POR MENÚS Y BADGES)
   * ========================================================= */
  function setFilter(key, rawValue, resetAll = false) {
    // Reset total (por botón limpiar o badge con reset)
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
   * MENÚS DESPLEGABLES (TIPO / ACCESO / LICENCIA)
   * ========================================================= */
  document.addEventListener("click", (e) => {
    // Cerrar todos los dropdowns si el click es fuera
    document
      .querySelectorAll(".osint-dropdown-menu")
      .forEach((menu) => {
        if (
          !menu.contains(e.target) &&
          !e.target.closest(".osint-filter-btn")
        ) {
          menu.classList.remove("active");
        }
      });

    // Abrir/cerrar el seleccionado
    const btn = e.target.closest(".osint-filter-btn");
    if (btn && btn.dataset.filter) {
      wrap
        .querySelectorAll(".osint-dropdown-menu")
        .forEach((m) => m.classList.remove("active"));
      const menu = wrap.querySelector(
        `.osint-dropdown-menu[data-for="${btn.dataset.filter}"]`
      );
      if (menu) menu.classList.toggle("active");
    }

    // Opción clickeada en menú genérico (type/access/license)
    const itemBtn = e.target.closest(".osint-dropdown-menu button");
    if (itemBtn) {
      const menu = itemBtn.closest(".osint-dropdown-menu");
      const val = itemBtn.dataset.value || "";
      const forKey = menu.dataset.for || "";
      setFilter(forKey, val ? itemBtn.textContent.trim() : "", false);
    }
  });

  /* =========================================================
   * CLICK EN CATEGORÍAS (PADRE / SUBCATEGORÍA)
   * ========================================================= */
  document.addEventListener("click", function (e) {
    const item = e.target.closest(
      ".osint-cat-item, .osint-cat-parent"
    );
    if (!item) return;

    const menu = item.closest(
      '.osint-dropdown-menu[data-for="category"]'
    );
    if (!menu) return;

    const rawValue = item.dataset.value || item.textContent.trim();
    setFilter("category", rawValue, false);
    menu.classList.remove("active");
  });

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

      // Categoría: se mira en las cards
      if (currentCat) {
        const matchCat = (t.cards || []).some((c) => {
          const cat = (c.category || "").toLowerCase();
          if (cat === currentCat) return true;
          const norm = normalizeCategory(cat.toLowerCase());
          const curNorm = normalizeCategory(currentCat.toLowerCase());
          if (norm.parent && norm.parent === curNorm.parent && !curNorm.child)
            return true;
          return false;
        });
        if (!matchCat) return false;
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

      // Búsqueda libre por texto
      if (text) {
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
   * SET FILTER (USADO POR MENÚS Y BADGES)
   * ========================================================= */
  function setFilter(key, rawValue, resetAll = false) {
    // Reset total (por botón limpiar o badge con reset)
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
   * MENÚS DESPLEGABLES (TIPO / ACCESO / LICENCIA)
   * ========================================================= */
  document.addEventListener("click", (e) => {
    // Cerrar todos los dropdowns si el click es fuera
    document
      .querySelectorAll(".osint-dropdown-menu")
      .forEach((menu) => {
        if (
          !menu.contains(e.target) &&
          !e.target.closest(".osint-filter-btn")
        ) {
          menu.classList.remove("active");
        }
      });

    // Abrir/cerrar el seleccionado
    const btn = e.target.closest(".osint-filter-btn");
    if (btn && btn.dataset.filter) {
      wrap
        .querySelectorAll(".osint-dropdown-menu")
        .forEach((m) => m.classList.remove("active"));
      const menu = wrap.querySelector(
        `.osint-dropdown-menu[data-for="${btn.dataset.filter}"]`
      );
      if (menu) menu.classList.toggle("active");
    }

    // Opción clickeada en menú genérico (type/access/license)
    const itemBtn = e.target.closest(".osint-dropdown-menu button");
    if (itemBtn) {
      const menu = itemBtn.closest(".osint-dropdown-menu");
      const val = itemBtn.dataset.value || "";
      const forKey = menu.dataset.for || "";
      setFilter(forKey, val ? itemBtn.textContent.trim() : "", false);
    }
  });

  /* =========================================================
   * CLICK EN CATEGORÍAS (PADRE / SUBCATEGORÍA)
   * ========================================================= */
  document.addEventListener("click", function (e) {
    const item = e.target.closest(
      ".osint-cat-item, .osint-cat-parent"
    );
    if (!item) return;

    const menu = item.closest(
      '.osint-dropdown-menu[data-for="category"]'
    );
    if (!menu) return;

    const rawValue = item.dataset.value || item.textContent.trim();
    setFilter("category", rawValue, false);
    menu.classList.remove("active");
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

    // Logic: Max 1 New, Max 1 Recommended
    let hasNew = false;
    let hasRec = false;

    if (recent) {
      badges.push('<span class="osint-badge osint-badge-new" title="Nueva"><i class="ri-flashlight-fill"></i></span>');
      hasNew = true;
    }

    if (recommended) {
      badges.push('<span class="osint-badge osint-badge-tip" title="Recomendada"><i class="ri-heart-3-fill"></i></span>');
      hasRec = true;
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

    let primaryCard = pickPrimaryCard(cards, detected.type || "");

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

    // Render main card (layer-0)
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


      // Chip Icons Logic
      const typeIcon = t.info && t.info.tipo ? '<i class="ri-filter-3-line"></i>' : '';
      const licIcon = t.info && t.info.licencia ? '<i class="ri-shield-check-line"></i>' : '';

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
            ${renderActions()}
            <button class="osint-report" title="Reportar herramienta">
              <i class="ri-flag-line"></i>
            </button>
          </div>
        </div>
      `;

      // Filtros por badge
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

    // Render edge layers (up to 5 edges for visual stack effect - Option B)
    const edgeLayers = Math.min(stackCount - 1, 5); // -1 because main card is already rendered
    for (let i = 1; i <= edgeLayers; i++) {
      const edge = document.createElement("div");
      edge.className = `osint-card layer-${i}`;
      edge.style.zIndex = String(10 + stackCount - i);
      deck.appendChild(edge);
    }

    return deck;
  }

  function pickPrimaryCard(cards, detectedType) {
    if (!Array.isArray(cards) || !cards.length) return cards && cards[0];

    const typeMap = {
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
      eth: ["wallet", "eth", "ethereum"],
      btc: ["wallet", "btc", "bitcoin"],
      file: ["archivo", "file", "malware"]
    };

    const wanted = typeMap[detectedType] || [];
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
   * RENDER DECKS
   * ========================================================= */
  function renderActions() {
    return `
      <div class="osint-actions">
        <a href="#" class="osint-btn-animated osint-act-go" target="_blank" rel="noopener">
          <span class="text">Analizar</span>
          <span class="icon">🡭</span>
        </a>

        <div class="osint-share-wrapper">
          <span class="osint-act-share" title="Compartir">
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
    `;
  }

  function attachActionEvents(card, urlTemplate, tool, detection) {
    const inputVal = (q.value || "").trim();
    // Si no hay input, reemplazamos {input} por vacío para que la URL sea funcional (aunque sea genérica)
    const builtUrl = () =>
      inputVal ? build(urlTemplate, inputVal) : build(urlTemplate, "");
      
    const needsInput = (urlTemplate || "").includes("{input}");
    const hasInput = !!inputVal;
    
    // Modificado: Ya no ocultamos si falta input. Solo si no hay URL.
    // El usuario quiere poder ir a la "url primaria" si no hay búsqueda.
    const disableActions = !urlTemplate || urlTemplate === "#"; 
    
    // Modo Manual: Hay input (busqueda), pero la herramienta no tiene template (no soporta {input})
    const isManualMode = hasInput && !needsInput;

    const actionsWrap = card.querySelector(".osint-actions");
    const goBtn = card.querySelector(".osint-act-go");
    const shareBtn = card.querySelector(".osint-act-share");
    const shareMenu = card.querySelector(".osint-share-menu");

    if (actionsWrap) {
      actionsWrap.classList.toggle("osint-hidden", disableActions);
    }

    if (goBtn) {
      const textSpan = goBtn.querySelector(".text");
      
      // Actualización de texto del botón
      if (textSpan) {
        if (hasInput) {
            if (isManualMode) {
                // Caso: Buscando algo, pero la herramienta es manual
                textSpan.textContent = "Uso Manual";
                goBtn.title = "Esta herramienta requiere uso manual. No admite consulta directa.";
            } else {
                // Caso: Buscando algo y la herramienta lo soporta
                textSpan.textContent = `Analizar ${inputVal}`;
                goBtn.title = `Analizar ${inputVal} con esta herramienta`;
            }
        } else {
            // Caso: Sin búsqueda (estado inicial)
            textSpan.textContent = "Analizar"; // O "Ir a la herramienta"
            goBtn.title = "Abrir herramienta";
        }
      }

      // Set href directly (like legacy code) instead of using event listener
      const url = builtUrl();
      if (url && url !== "#") {
        goBtn.href = url;
        goBtn.classList.remove("is-disabled");

        // Track click event (non-blocking)
        goBtn.addEventListener("click", () => {
          sendEvent("click_tool", {
            tool_id: (tool && tool.id) || "",
            input_type: (detection && detection.type) || "",
            input_value: inputVal,
          });
        });
      } else {
        goBtn.href = "#";
        goBtn.classList.add("is-disabled");
        goBtn.addEventListener("click", (e) => e.preventDefault());
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
              `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(
                u
              )}`
            );
          }
          if (act === "whatsapp") {
            window.open(
              `https://api.whatsapp.com/send?text=${encodeURIComponent(
                u
              )}`
            );
          }
          if (act === "twitter") {
            window.open(
              `https://twitter.com/intent/tweet?url=${encodeURIComponent(
                u
              )}`
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
    counterRef.textContent = `${shown} / ${total}`;
  }

  function renderNextChunk() {
    if (!filteredCache.length || renderedCount >= filteredCache.length) return;

    const slice = filteredCache.slice(renderedCount, renderedCount + chunkSize);
    renderedCount += slice.length;

    const appended = [];
    slice.forEach((t) => {
      const deck = renderDeckElement(t);
      if (deck) {
        grid.appendChild(deck);
        appended.push(deck);
      }
    });

    updateCounter();
    // Animaciones desactivadas para evitar desalineaciones
  }

  function renderDecks(list, detection) {
    const oldPagination = document.getElementById(`${uid}-pagination`);
    if (oldPagination) oldPagination.remove();
    grid.innerHTML = "";

    filteredCache = filterPopularOnly
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
  }

  // Infinite scroll: cargar más mazos al acercarse al final
  window.addEventListener("scroll", () => {
    if (!filteredCache.length) return;
    
    // Usar document.documentElement.scrollHeight para mejor compatibilidad
    const scrollHeight = document.documentElement.scrollHeight || document.body.scrollHeight;
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const clientHeight = window.innerHeight || document.documentElement.clientHeight;
    
    const nearBottom = scrollTop + clientHeight >= scrollHeight - 400;
    
    if (nearBottom) {
      renderNextChunk();
    }
  });

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
  function onInput() {
    const d = detectRichInput(q.value);
    if (d.msg) showTooltip(d.msg);
    applyFilters();
    toggleSmart();
  }

  // Contenedor de acciones de filtro (separado de los chips)
  const filterBar = document.getElementById(`${uid}-filters`);
  const actionsContainer = document.createElement("div");
  actionsContainer.className = "osint-filter-actions";
  filterBar.appendChild(actionsContainer);

  // Botón "Limpiar filtros"
  const clearBtn = document.createElement("button");
  clearBtn.className = "osint-action-btn osint-clear-filters";
  clearBtn.innerHTML = '<i class="ri-close-line"></i> Limpiar filtros';
  actionsContainer.appendChild(clearBtn);

  // Botón "Solo populares"
  const popularToggle = document.createElement("button");
  popularToggle.className = "osint-action-btn osint-popular-toggle";
  popularToggle.innerHTML = '<i class="ri-star-line"></i> Solo populares';
  popularToggle.addEventListener("click", () => {
    filterPopularOnly = !filterPopularOnly;
    popularToggle.classList.toggle("active", filterPopularOnly);
    const icon = popularToggle.querySelector("i");
    if (icon) {
      icon.className = filterPopularOnly ? "ri-star-fill" : "ri-star-line";
    }
    applyFilters();
  });
  actionsContainer.appendChild(popularToggle);

  clearBtn.addEventListener("click", () => {
    setFilter("", "", true);
  });

  // Inicializar menús
  populateTypeMenu();
  populateAccessMenu();
  populateLicenseMenu();
  populateCategoryMenu();

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

  // Render inicial
  renderDecks(tools, detectRichInput(q.value || ""));
  q.addEventListener("input", onInput);
  toggleSmart();
});

// Detecci�n extendida accesible globalmente
function detectIntent(value) {
  const v = (value || "").toLowerCase();
  const intents = [
    { key: "leak", words: ["leak", "breach", "filtr", "dump"], intent: "leaks", msg: "He detectado palabras clave relacionadas con leaks o filtraciones." },
    { key: "reputation", words: ["reput", "blacklist", "spam"], intent: "reputation", msg: "He detectado intenci\u00f3n de reputaci\u00f3n / listas negras." },
    { key: "vuln", words: ["vuln", "cve", "bug", "exploit", "poc"], intent: "vuln", msg: "He detectado palabras clave de vulnerabilidades." },
    { key: "fraud", words: ["fraud", "scam", "fraude", "tarjeta", "carding"], intent: "fraud", msg: "He detectado contexto de fraude/finanzas." }
  ];
  for (const item of intents) {
    if (item.words.some((w) => v.includes(w))) return item;
  }
  return null;
}

function detectRichInput(value) {
  const cleanText = (s) => String(s || "").trim();
  const s = cleanText(value);
  if (!s) return { type: "none", msg: "" };

  if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(s)) {
    return { type: "email", msg: `Has ingresado el correo ${s}. Aqui encontraras herramientas para investigarlo.` };
  }
  const socialRe = /^(https?:\/\/)?(www\.)?(facebook\.com|x\.com|twitter\.com|instagram\.com|linkedin\.com|tiktok\.com|github\.com|gitlab\.com|threads\.net)\/[^\s/]+/i;
  if (socialRe.test(s)) {
    return { type: "username", msg: `Detecto un perfil social: ${s}. Te muestro herramientas asociadas.` };
  }
  if (/^https?:\/\//i.test(s)) {
    return { type: "url", msg: `Has ingresado la URL ${s}. Te muestro herramientas asociadas.` };
  }
  if (/^(?=.{1,253}$)(?!-)(?:[a-z0-9-]+\.)+[a-z]{2,}$/i.test(s)) {
    return { type: "domain", msg: `Detecto que ingresaste el dominio ${s}. Estas son las herramientas disponibles.` };
  }
  if (/^com\.[a-z0-9_-]+\.[a-z0-9_.-]+$/i.test(s)) {
    return { type: "package", msg: `Detecto un paquete de aplicacion ${s}. Te muestro recursos asociados.` };
  }
  if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(s)) {
    return { type: "ipv4", msg: `Ingresaste una direccion IP: ${s}. Te muestro herramientas relacionadas.` };
  }
  if (/^(?:[a-f0-9]{1,4}:){7}[a-f0-9]{1,4}$/i.test(s)) {
    return { type: "ipv6", msg: `Detecto la IP ${s}. Aqui tienes utilidades que pueden ayudarte a investigarla.` };
  }
  if (/^AS\d{1,10}$/i.test(s)) {
    return { type: "asn", msg: `Detecto un ASN: ${s}. Aqui tienes herramientas relacionadas.` };
  }
  if (/^[0-9A-Fa-f]{2}([:-][0-9A-Fa-f]{2}){5}$/.test(s)) {
    return { type: "mac", msg: `Ingresaste una direccion MAC: ${s}. Estas son las utilidades que pueden ayudarte.` };
  }
  if (/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(s)) {
    return { type: "uuid", msg: `Has ingresado un UUID: ${s}. Estas son las herramientas que puedes usar.` };
  }
  if (/^[a-f0-9]{32}$/i.test(s)) {
    return { type: "md5", msg: `Detecto un hash: ${s}. Aqui tienes herramientas compatibles.` };
  }
  if (/^[a-f0-9]{40}$/i.test(s)) {
    return { type: "sha1", msg: `Has ingresado el hash ${s}. Te muestro las utilidades relacionadas.` };
  }
  if (/^[a-f0-9]{64}$/i.test(s)) {
    return { type: "sha256", msg: `Has ingresado el hash ${s}. Te muestro las utilidades relacionadas.` };
  }
  if (/^\d{4,10}$/.test(s)) {
    return { type: "zip", msg: `Detecto un codigo postal: ${s}. Te muestro herramientas relacionadas.` };
  }
  if (/^\+?[0-9][0-9\s().-]{6,}$/i.test(s)) {
    return { type: "phone", msg: `Has ingresado el numero telefonico ${s}. Estas son las herramientas disponibles.` };
  }
  if (/^-?\d{1,3}\.\d+,\s*-?\d{1,3}\.\d+$/.test(s)) {
    return { type: "geo", msg: `Detecto coordenadas: ${s}. Aqui tienes recursos que trabajan con este tipo de datos.` };
  }
  if (/^0x[a-fA-F0-9]{40}$/.test(s)) {
    return { type: "eth", msg: `Detecto una direccion de criptomoneda: ${s}. Aqui tienes las herramientas asociadas.` };
  }
  if (/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,39}$/.test(s)) {
    return { type: "btc", msg: `Has ingresado una wallet: ${s}. Te muestro recursos para investigarla.` };
  }
  if (/(\.zip|\.rar|\.7z|\.pdf|\.docx?|\.xlsx?|\.pptx?|\.exe|\.dll|\.apk|\.ipa|\.jpg|\.jpeg|\.png|\.gif)$/i.test(s)) {
    return { type: "file", msg: `Archivo detectado: ${s}. Te muestro utilidades disponibles.` };
  }
  if (/BEGIN PGP PUBLIC KEY BLOCK/i.test(s)) {
    return { type: "pgp", msg: "Has ingresado una clave PGP. Aqui tienes herramientas compatibles." };
  }
  if (/^@[a-z0-9_.-]{2,32}$/i.test(s) || /^[a-z0-9_.-]{3,32}$/i.test(s)) {
    return { type: "username", msg: `Detecto un nombre de usuario: ${s}. Estas son las herramientas disponibles.` };
  }
  if (/^[a-záéíóúüñ][a-záéíóúüñ'`-]+\s+[a-záéíóúüñ][a-záéíóúüñ'`-]+(\s+[a-záéíóúüñ][a-záéíóúüñ'`-]+)*$/i.test(s) && s.length > 8) {
    return { type: "fullname", msg: `Has ingresado un nombre: ${s}. Te muestro herramientas relacionadas.` };
  }

  const intent = detectIntent(s);
  if (intent) {
    return { type: "keyword", intent: intent.intent, msg: intent.msg };
  }

  if (s.split(" ").length > 2) {
    return { type: "keyword", msg: `He detectado palabras clave en tu busqueda (${s}). Te muestro herramientas generales asociadas.` };
  }
  return { type: "generic", msg: `He recibido tu busqueda: ${s}. Te muestro herramientas generales que pueden ser utiles.` };
}



