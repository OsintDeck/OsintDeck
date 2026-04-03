# Guía de Defensa para el TFI: OSINT Deck
### Licenciatura en Ciberseguridad - UNSO

Esta guía está diseñada para prepararte ante las posibles preguntas del jurado y ayudarte a defender el proyecto con un enfoque técnico y profesional.

---

## 1. Preguntas Técnicas Probables (Mesa Examinadora)

### P: "¿Por qué usaste Naive Bayes y no una red neuronal o GPT?"
**Respuesta sugerida:** "Elegimos Naive Bayes por su **eficiencia computacional** en entornos web distribuidos. A diferencia de un modelo pesado de Deep Learning, Naive Bayes permite clasificar intenciones en milisegundos sin necesidad de hardware especializado (GPUs). Además, al ser un modelo probabilístico, es ideal para la clasificación de texto técnico (Dorks) donde la frecuencia de términos es un indicador muy fuerte de la intención."

### P: "¿Cómo manejás la seguridad de los datos que ingresa el usuario?"
**Respuesta sugerida:** "Implementamos una estrategia de **Defensa en Profundidad**. Primero, sanitizamos el input para evitar XSS. Segundo, el motor de IA tiene un **Filtro de Toxicidad** para detectar consultas no éticas. Finalmente, en la capa de persistencia, usamos **consultas preparadas ($wpdb->prepare)** para mitigar cualquier intento de Inyección SQL (OWASP A05:2025)."

### P: "¿Qué pasa si una de las herramientas externas (ej. Shodan) cambia su URL o deja de funcionar?"
**Respuesta sugerida:** "La arquitectura es **modular**. Gracias al **Tool Manager** en el panel administrativo, el analista puede actualizar la URL o los placeholders sin tocar una sola línea de código. Además, el sistema registra fallos en los logs, permitiendo identificar rápidamente herramientas inactivas para su mantenimiento."

### P: "¿Por qué desarrollaron una librería core en lugar de meter todo en el `functions.php`?"
**Respuesta sugerida:** "Para cumplir con los principios de **Arquitectura Limpia (Clean Architecture)**. Al desarrollar la `OSINT Core Library` de forma desacoplada, garantizamos que la lógica de inteligencia sea portable, fácil de auditar y que no dependa de las vulnerabilidades del CMS WordPress. Esto reduce la superficie de ataque y mejora la mantenibilidad."

---

## 2. Puntos Fuertes para Destacar (Tu "Speech")

1.  **Orquestación Contextual:** "No somos un directorio estático; somos un motor que 'entiende' el dato y le ofrece al investigador la mejor herramienta en el momento justo."
2.  **Seguridad por Diseño:** "Auditamos el proyecto bajo **OWASP Top 10:2025** y aplicamos **Modelado de Amenazas STRIDE**, demostrando un enfoque profesional de ciberseguridad."
3.  **Librería Propietaria:** "Desarrollamos nuestra propia lógica de clasificación, lo que nos da independencia tecnológica y propiedad intelectual sobre el motor de decisión."

---

## 3. Consejos para la Defensa
- **Sé honesto con las limitaciones:** Si te preguntan sobre fallos en la detección de IA, respondé que el modelo está en fase de entrenamiento continuo y que por eso incluimos el **AI Trainer** en el panel admin.
- **Relacioná todo con la carrera:** Cada vez que hables de una función, mencioná palabras clave como: *Mitigación, Superficie de Ataque, Trazabilidad, Auditoría Forense.*
- **Tené el plugin a mano:** Si podés hacer una demo en vivo buscando una IP sospechosa y mostrando cómo se activan las "cartas" de VirusTotal o Shodan, tenés la aprobación asegurada.

---
*¡Mucho éxito, colega! Esta documentación te respalda totalmente.*
