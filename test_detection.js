const fs = require('fs');
const path = require('path');

// Read osint-deck.js content
const jsContent = fs.readFileSync(path.join(__dirname, 'assets/js/osint-deck.js'), 'utf8');

// Extract detectRichInput and detectIntent functions
// We need to be careful with extraction as they might depend on other variables or functions
// For this test, we'll try to extract them using regex or just copy-paste relevant parts if simple extraction fails
// But since we have the file, let's try to mock the environment and eval the code or just extract the function body.

// Simplified approach: Extract the functions text
const detectRichInputMatch = jsContent.match(/function detectRichInput[\s\S]*?^}/m);
// This regex is too simple for nested braces.

// Let's just manually copy the logic for the test since we have read the file.
// Or better, let's include the file if it was a module, but it's not.

// Let's implement a test that simulates the function based on what we read.
// Actually, I will write a temporary JS file that contains the necessary functions from osint-deck.js
// I'll read the file, extract the functions, and run them.

// Read the file content
const fileContent = fs.readFileSync('assets/js/osint-deck.js', 'utf8');

// We need detectRichInput and detectIntent and helper functions inside it or outside.
// detectIntent is at 1925
// detectRichInput is at 1939

// We can just append the test code to the end of a copy of osint-deck.js and run it with node?
// But osint-deck.js has DOM references (document, window).
// So we need to mock them.

// Mock DOM
const window = { OSINT_DECK_AJAX: {} };
const document = {
    createElement: () => ({ className: '', classList: { add:()=>{}, remove:()=>{} }, appendChild: ()=>{} }),
    body: { appendChild: ()=>{} },
    querySelector: () => ({ addEventListener: ()=>{} }),
    getElementById: () => ({})
};
const navigator = { userAgent: 'test', language: 'en', clipboard: {} };
const screen = { width: 1024, height: 768 };
const location = { hostname: 'localhost' };

// We need to strip out the DOM manipulation parts from osint-deck.js or just ignore errors.
// But osint-deck.js is likely wrapped in a DOMContentLoaded listener or similar?
// Let's check the start of the file.

// It seems osint-deck.js is just a script.
// It has `document.addEventListener("DOMContentLoaded", () => { ... });` likely?

// Let's try to extract just the functions we need.
// I will create a script that includes the functions directly since I just read them.

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
  // Removed IPv6 regex for stability in test environment as it was causing issues and not relevant for this test
  /*
  if (/^((?:[0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4}|([0-9A-Fa-f]{1,4}:){1,7}:|([0-9A-Fa-f]{1,4}:){1,6}:[0-9A-Fa-f]{1,4}|([0-9A-Fa-f]{1,4}:){1,5}(:[0-9A-Fa-f]{1,4}){1,2}|([0-9A-Fa-f]{1,4}:){1,4}(:[0-9A-Fa-f]{1,4}){1,3}|([0-9A-Fa-f]{1,4}:){1,3}(:[0-9A-Fa-f]{1,4}){1,4}|([0-9A-Fa-f]{1,4}:){1,2}(:[0-9A-Fa-f]{1,4}){1,5}|[0-9A-Fa-f]{1,4}:((:[0-9A-Fa-f]{1,4}){1,6})|:((:[0-9A-Fa-f]{1,4}){1,7}|:))$/i.test(s)) {
    return { type: "ipv6", msg: `Detecto la IP ${s}. Aqui tienes utilidades que pueden ayudarte a investigarla.` };
  }
  */
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
  if (/^@[a-z0-9_.-]{2,32}$/i.test(s)) {
    return { type: "username", msg: `Detecto un nombre de usuario: ${s}. Estas son las herramientas disponibles.` };
  }

  const conversationalStopwords = /^(necesito|quiero|como|donde|que|cual|quien|busca|encuentra|dame|mostrar|ver|ayuda|hola|buenos|buenas|gracias|sexo|porno|pack)/i;
  if (!conversationalStopwords.test(s) && /^[a-záéíóúüñ][a-záéíóúüñ'`-]+\s+[a-záéíóúüñ][a-záéíóúüñ'`-]+(\s+[a-záéíóúüñ][a-záéíóúüñ'`-]+)*$/i.test(s) && s.length > 8) {
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

// Test cases
const inputs = ["hola", "sexo", "papa", "necesito ayuda", "como se usa esta herramienta?", "ayuda"];
inputs.forEach(i => {
    console.log(`Input: "${i}" -> Type: ${detectRichInput(i).type}`);
});
