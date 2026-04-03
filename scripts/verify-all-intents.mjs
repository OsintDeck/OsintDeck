/**
 * Verificación integral:
 * - Cada intent declarado en KEYWORD_INTENTS tiene entrada en TYPE_MAP.
 * - Categorías de training_data.json tienen TYPE_MAP (salvo lista permitida).
 * - Lista de intents “blandos” (sin mensaje agresivo si no hay mazos) alineada con el JS.
 *
 * Uso: node scripts/verify-all-intents.mjs
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, "..");
const deckJs = path.join(root, "assets", "js", "osint-deck.js");
const trainingPath = path.join(root, "data", "training_data.json");

const src = fs.readFileSync(deckJs, "utf8");

function parseTypeMapKeys(js) {
  const start = js.indexOf("const TYPE_MAP = {");
  if (start === -1) throw new Error("TYPE_MAP no encontrado");
  const slice = js.slice(start);
  const endRel = slice.indexOf("\n};");
  const block = slice.slice(0, endRel + 1);
  const keys = new Set();
  const re = /^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*\[/gm;
  let m;
  while ((m = re.exec(block)) !== null) keys.add(m[1]);
  return keys;
}

function parseKeywordIntents(js) {
  const start = js.indexOf("const KEYWORD_INTENTS = [");
  if (start === -1) throw new Error("KEYWORD_INTENTS no encontrado");
  const slice = js.slice(start);
  const end = slice.indexOf("];");
  const block = slice.slice(0, end + 2);
  const intents = new Set();
  const re = /intent:\s*"([^"]+)"/g;
  let m;
  while ((m = re.exec(block)) !== null) intents.add(m[1]);
  return intents;
}

function parseSoftIntents(js) {
  const m = js.match(/const _softIntents = \[([^\]]+)\]/s);
  if (!m) return null;
  return m[1]
    .split(",")
    .map((s) => s.replace(/["'\s]/g, ""))
    .filter(Boolean);
}

const TYPE_KEYS = parseTypeMapKeys(src);
const KW_INTENTS = parseKeywordIntents(src);
const training = JSON.parse(fs.readFileSync(trainingPath, "utf8"));
const trainCats = new Set(
  training.map((r) => r.category).filter((c) => c && String(c).trim())
);

const ALLOW_UNMAPPED = new Set(["none", "promo_news"]);

const SPECIAL_DETECT_INTENTS = new Set(["easter_egg"]);

let exit = 0;

for (const intent of KW_INTENTS) {
  if (!TYPE_KEYS.has(intent)) {
    console.error(`[ERROR] intent de KEYWORD_INTENTS "${intent}" sin clave en TYPE_MAP`);
    exit = 1;
  }
}

for (const i of SPECIAL_DETECT_INTENTS) {
  if (!TYPE_KEYS.has(i)) {
    console.error(`[ERROR] intent especial detectIntent "${i}" sin TYPE_MAP`);
    exit = 1;
  }
}

for (const c of trainCats) {
  if (ALLOW_UNMAPPED.has(c)) continue;
  if (!TYPE_KEYS.has(c)) {
    console.error(`[ERROR] categoría training "${c}" sin TYPE_MAP`);
    exit = 1;
  }
}

const soft = parseSoftIntents(src);
const expectedSoft = ["help", "greeting", "easter_egg", "crisis", "community", "toxic"];
if (soft && JSON.stringify(soft) !== JSON.stringify(expectedSoft)) {
  console.warn("[ADVISO] _softIntents en JS difiere de la lista esperada del verificador:", soft);
}

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

function keywordSubstringIntent(phrase, keywordIntents) {
  const v = String(phrase || "").toLowerCase().trim();
  for (const item of keywordIntents) {
    if (item.words.some((w) => keywordMatchesInPhrase(v, w))) return item.intent;
  }
  return null;
}

const kwBlock = src.match(/const KEYWORD_INTENTS = (\[[\s\S]*?\n\]);/);
if (!kwBlock) {
  console.error("[ERROR] no se pudo aislar KEYWORD_INTENTS para humo");
  exit = 1;
} else {
  let keywordIntents;
  try {
    keywordIntents = (0, eval)(kwBlock[1]);
  } catch (e) {
    console.error("[ERROR] eval KEYWORD_INTENTS:", e.message);
    exit = 1;
  }
  if (keywordIntents) {
    const smoke = [
      ["texto toxico con puto en el mensaje", "toxic"],
      ["no quiero morir solo ansiedad", "crisis"],
      ["quiero sugerir herramienta nueva al deck", "community"],
      ["mapa y coordenadas gps", "geo"],
      ["buscar usuario de twitter apodo", "username"],
      ["correo gmail filtrado", "email"],
      ["whois dominio dns", "domain"],
      ["ruta ipv6 y trazas", "ipv6"],
      ["filtracion breach de datos", "leaks"],
      ["blacklist spam reputacion", "reputation"],
      ["cve exploit vulnerabilidad", "vuln"],
      ["fraude tarjeta bin 4242", "fraud"],
      ["necesito instrucciones del deck", "help"],
      ["no entiendo nada", "help"],
      ["hola buenos dias", "greeting"],
    ];
    for (const [phrase, want] of smoke) {
      const got = keywordSubstringIntent(phrase, keywordIntents);
      if (got !== want) {
        console.error(`[HUMO] "${phrase}" → esperado intent "${want}", obtuve "${got}"`);
        exit = 1;
      }
    }
  }
}

if (exit === 0) {
  console.log("OK — Intenciones keyword:", [...KW_INTENTS].sort().join(", "));
  console.log("OK — TYPE_MAP:", TYPE_KEYS.size, "claves");
  console.log("OK — Training categories cubiertas (excepto none, promo_news)");
  console.log("OK — Humo substring (orden KEYWORD_INTENTS):", 15, "frases");
}

process.exit(exit);
