# Registro de Decisión Arquitectónica (ADR)
**Plantilla Estándar - Pacíficos Online**

---

## ADR-[NNN]: [Título breve en lenguaje imperativo, ej. Uso de Bloqueo Pesimista para Reserva de Existencias]

- **Estado:** [Propuesto | Aprobado | Rechazado | Superado por ADR-xxx]
- **Fecha:** [YYYY-MM-DD]
- **Decisores:** Comité de Arquitectura e Integración
- **Módulos Afectados:** [Todos | M4, M5, etc.]

---

### 1. Contexto y Planteamiento del Problema
[Describir la situación, el problema técnico o de negocio que motiva esta decisión. Mencionar los atributos de calidad involucrados (concurrencia, disponibilidad, aislamiento, rendimiento).]

---

### 2. Factores Decisores (Drivers Arquitectónicos)
- [Factor 1: e.g. Consistencia estricta de existencias bajo concurrencia (Regla RN-04)]
- [Factor 2: e.g. Rendimiento de respuesta < 2s en percentil 95]
- [Factor 3: e.g. Prohibición de joins directos entre módulos (Sección 5.4)]

---

### 3. Opciones Consideradas
1. **Opción 1:** [Descripción de la opción 1]
2. **Opción 2:** [Descripción de la opción 2]
3. **Opción 3:** [Descripción de la opción 3]

---

### 4. Decisión Tomada
[Explicar la opción elegida y el porqué. Incluir detalles de implementación técnica a nivel de Laravel/BD.]

---

### 5. Consecuencias
- **Positivas:**
  - [Beneficio 1]
  - [Beneficio 2]
- **Negativas / Compromisos (Trade-offs):**
  - [Desventaja o costo 1]
  - [Desventaja o costo 2]
- **Impacto en Contratos / Otros Módulos:**
  - [Descripción del impacto]
