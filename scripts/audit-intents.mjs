/**
 * Verifica que cada categoría usada en training_data.json tenga entrada en TYPE_MAP
 * (salvo "none", que es intencionalmente genérica para el clasificador).
 *
 * Uso: node scripts/audit-intents.mjs
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, "..");
const trainingPath = path.join(root, "data", "training_data.json");
const deckJsPath = path.join(root, "assets", "js", "osint-deck.js");

const training = JSON.parse(fs.readFileSync(trainingPath, "utf8"));
const cats = new Set(
  training.map((r) => r.category).filter((c) => c && String(c).trim())
);

const deckSrc = fs.readFileSync(deckJsPath, "utf8");
const mapMatch = deckSrc.match(/const TYPE_MAP = \{([\s\S]*?)\n\};/);
if (!mapMatch) {
  console.error("No se encontró TYPE_MAP en osint-deck.js");
  process.exit(1);
}

const TYPE_MAP_KEYS = new Set();
const block = mapMatch[1];
const keyRe = /^\s*([a-zA-Z0-9_]+):/gm;
let m;
while ((m = keyRe.exec(block)) !== null) {
  TYPE_MAP_KEYS.add(m[1]);
}

const ALLOW_UNMAPPED = new Set(["none", "promo_news"]);

let exit = 0;
for (const c of [...cats].sort()) {
  if (ALLOW_UNMAPPED.has(c)) continue;
  if (!TYPE_MAP_KEYS.has(c)) {
    console.error(`[FALTA TYPE_MAP]: categoría de entrenamiento "${c}" sin clave en TYPE_MAP`);
    exit = 1;
  }
}

if (exit === 0) {
  console.log(
    `OK: ${cats.size} categorías en training; todas mapean a TYPE_MAP o están en lista permitida (${[...ALLOW_UNMAPPED].join(", ")}).`
  );
}

process.exit(exit);
