
function detectRichInput(value) {
  const cleanText = (s) => String(s || "").trim();
  const s = cleanText(value);
  if (!s) return { type: "none", msg: "" };

  const ret = (type, val, msg) => ({ type, value: val, msg });

  // 1. Extraction (Find entity within string)
  // URL
  const urlMatch = s.match(/https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)/i);
  if (urlMatch) return ret("url", urlMatch[0], `He encontrado una URL: ${urlMatch[0]}.`);

  // IPv4 - Improved regex for extraction
  const ipv4Match = s.match(/\b(?:\d{1,3}\.){3}\d{1,3}\b/);
  if (ipv4Match) {
      // Validate segments
      const parts = ipv4Match[0].split('.');
      if (parts.every(p => parseInt(p) <= 255)) {
          return ret("ipv4", ipv4Match[0], `He encontrado una IP: ${ipv4Match[0]}.`);
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
  const intent = detectIntent(s);
  if (intent) {
    return { type: "keyword", intent: intent.intent, msg: intent.msg, value: s };
  }
  
  // ... fallback ...
  if (s.split(" ").length > 2) {
    return { type: "keyword", msg: `He detectado palabras clave en tu busqueda (${s}). Te muestro herramientas generales asociadas.`, value: s };
  }
  return { type: "generic", msg: `He recibido tu busqueda: ${s}. Te muestro herramientas generales que pueden ser utiles.`, value: s };
}

function detectIntent(value) {
  const v = (value || "").toLowerCase();
  const intents = [
    { key: "leak", words: ["leak", "breach", "filtr", "dump"], intent: "leaks", msg: "He detectado palabras clave relacionadas con leaks o filtraciones." },
    { key: "reputation", words: ["reput", "blacklist", "spam"], intent: "reputation", msg: "He detectado intenci\u00f3n de reputaci\u00f3n / listas negras." },
    { key: "vuln", words: ["vuln", "cve", "bug", "exploit", "poc"], intent: "vuln", msg: "He detectado palabras clave de vulnerabilidades." },
    { key: "fraud", words: ["fraud", "scam", "fraude", "tarjeta", "carding"], intent: "fraud", msg: "He detectado contexto de fraude/finanzas." },
    { key: "help", words: ["ayuda", "necesito", "help", "soporte", "asistencia"], intent: "help", msg: "He detectado una solicitud de ayuda. Te muestro recursos disponibles." },
    { key: "greeting", words: ["hola", "buenos dias", "buenas tardes", "buenas noches", "que tal", "como estas", "saludos", "buen dia"], intent: "greeting", msg: "¡Hola! Estoy aquí para ayudarte con tus investigaciones OSINT." },
    { key: "toxic", words: ["puto", "mierda", "idiota", "imbecil", "estupido", "basura", "inutil", "maldito", "cabron", "verga", "pene", "sexo", "porno", "xxx"], intent: "toxic", msg: "Lenguaje no permitido detectado." }
  ];
  for (const item of intents) {
    if (item.words.some((w) => v.includes(w))) return item;
  }
  return null;
}

// TEST CASES
const testCases = [
  "necesito saber la reputacion de una ip 208.8.8.8",
  "hola",
  "8.8.8.8",
  "example.com",
  "check google.com",
  "necesito ayuda"
];

testCases.forEach(t => {
  console.log(`Input: "${t}"`);
  console.log(JSON.stringify(detectRichInput(t), null, 2));
  console.log("---");
});
