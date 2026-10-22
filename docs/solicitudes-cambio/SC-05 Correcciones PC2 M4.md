# Solicitud de Cambio (SC-05 - Correcciones del PC2_SG4 (M4))

---

### 1. Identificación
- **Código de Solicitud:** SC-05
- **Fecha:** 2026.10.21
- **Módulo Solicitante:** M4
- **Persona Solicitante:** Jorge Alvarado Moreno

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [x] Especificación de Requerimientos de Software (ERS)
- [x] Modelo de Casos de Uso
- [x] Contrato de Módulo (OpenAPI / PHP Interface)
- [ ] Modelo de Diseño / Base de Datos
- [ ] Código Fuente / Pruebas
- [x] Artefacto de subgrupo

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  Se atendieron las observaciones de la rúbrica del PC2 y los resultados de la encuesta de validación al cliente del 18 de setiembre de 2026. Correcciones realizadas:
  - **Requerimientos:** se retiró el «stock defectuoso» de RN-M4-005 y RF-M4-010 por estar fuera del alcance de M4 (SC-M4-02). Se reescribieron los RF en lenguaje formal con criterio de verificación, se agregó RNF-M4-010 (idempotencia) y se ajustó RNF-M4-007 a un ciclo de 60 s (SC-M4-05).
  - **Casos de uso:** se eliminó el <<extend>> entre CU-M4-06 y CU-M4-07, y se sustituyó el caso crítico «Consultar Kardex» por CU-M4-07 (SC-M4-03).
  - **Contratos:** se nombró ObtenerSedesComercio la operación requerida de M2 y se agregaron los eventos del catálogo: publicados ExistenciaReservada y ExistenciaAgotada, consumidos PedidoConfirmado y ComercioSuspendido (SC-M4-04). Se agregó la operación consultarInventarioAuditoria para el rol de Soporte/Auditoría (SC-M4-13).
  - **Encuesta al cliente:** se incorporaron las reglas RN-M4-011 a RN-M4-016, RF-M4-012, CU-M4-12, los actores Administrador del comercio y Soporte/Auditoría de la plataforma, los reportes ampliados de RF-M4-009, la cantidad exacta en RF-M4-010 y ajustes en RF-M4-002, RF-M4-004, RF-M4-005 y RF-M4-007 (SC-M4-06).
  - **Acta de validación:** se completó la sección 8.3, que había quedado con el texto de la plantilla.
- **Origen del cambio:**
  - [x] Petición del cliente / persona docente
  - [x] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:** M2 (nombre de la operación ObtenerSedesComercio y evento ComercioSuspendido), M5 (eventos PedidoConfirmado y ExistenciaReservada), M1 y M3 (evento ExistenciaAgotada) y núcleo compartido (rol de Soporte/Auditoría).
- **Contratos afectados:** Contrato preliminar de M4 del PC2. No rompe compatibilidad: se agregan eventos y una operación, y el nombre ObtenerSedesComercio se alinea con el de M2. No requiere versión v2.
- **Artefactos por actualizar:** PC2_SG4 (secciones 1, 3, 4, 5, 6, 7 y 8, y matriz de trazabilidad).
- **Esfuerzo estimado:** [confirmar horas]

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-[XX]
- **Fecha de Resolución:** [YYYY-MM-DD]
