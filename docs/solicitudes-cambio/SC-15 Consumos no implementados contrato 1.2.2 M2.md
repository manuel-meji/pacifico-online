# Solicitud de Cambio (SC)
**Anexo B - IF6100 Pacíficos Online**

---

### 1. Identificación
- **Código de Solicitud:** SC-15
- **Fecha:** 2026-11-14
- **Módulo Solicitante:** M2 (Micrositios y administración de comercios)
- **Persona Solicitante:** Diego Alfaro Alvarado, coordinador de SG2

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [ ] Especificación de Requerimientos de Software (ERS)
- [ ] Modelo de Casos de Uso
- [x] Contrato de Módulo (OpenAPI / PHP Interface)
- [ ] Modelo de Diseño / Base de Datos
- [x] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  Retirar de la sección de dependencias consumidas del contrato de M2 (`contracts/m2-comercios/v1/openapi.yaml`, v1.2.1 → v1.2.2) dos consumos que se declararon en el diseño pero no forman parte del alcance implementado del PC4:
  1. El evento `AlertaStockMinimo` de M4, declarado para mostrar la alerta de reposición en el panel del comercio. M4 lo publica, pero M2 no tiene listener.
  2. La operación `obtenerResumenFinancieroComercio` de M7 (`GET /api/v1/m7/comercios/{id_comercio}/resumen-financiero`), declarada para mostrar el resumen financiero en el panel del comercio. El frontend de M2 no la invoca y M7 no publica esa ruta.

  En ambos casos la necesidad la cubre el módulo dueño del dato, y M2 solo enlaza a su pantalla desde «Mi comercio», igual que con Pedidos (M5):
  - La alerta de reposición la muestra M4 en «Existencias y mínimos» (estados «Bajo mínimo» y «Agotado»), a la que el encargado llega desde la pestaña **Inventario** (`/m4.html`).
  - El resumen financiero lo muestra M7 en su tablero ejecutivo (`/m7.html`), con el reporte gerencial (`GET /api/m7/v1/reporte-gerencial`) filtrado por comercio con `EstablecerComercio`. El encargado llega desde la pestaña **Finanzas**.

  Además se retiran los dobles `App\Services\M2_Comercios\InventarioStub` y `App\Services\M2_Comercios\ContabilidadStub`, que simulaban esos consumos y no los usa ningún archivo ni prueba.

- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [x] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

  Lo detectó M4 durante las pruebas de integración M2–M4 (verificado por Abril Chacón, SG2) y, al revisar el contrato, M2 encontró el mismo caso con M7.

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - M4 (consultado por medio de Abril Chacón; de acuerdo): debe retirar a M2 como consumidor de `AlertaStockMinimo` en `openapi_M4_v1.3.0.yaml` y en el comentario de la clase del evento.
  - M7 (consultado): no requiere cambios; su reporte gerencial ya filtra por comercio.
  - Coordinadores (reunión del 13/11): acordaron que el resumen financiero se consulte en la pantalla de M7 enlazada desde «Mi comercio».
- **Contratos afectados:** contrato de M2, nueva versión menor 1.2.2 compatible: no cambia ninguna operación ni evento publicado por M2, solo la lista de dependencias consumidas. Contrato de M4: cambio de documentación en los consumidores de un evento.
- **Artefactos por actualizar:** contrato de M2 (v1.2.2), contrato de M4, documento de artefactos del PC4 de M2 (secciones 3 y 9.1) y bitácora de defectos.
- **Esfuerzo estimado:** 2 horas (sin cambios de base de datos; la pestaña Finanzas va en el PR `feature/M2-mejoras-reunion`).

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-[XX]
- **Fecha de Resolución:** [YYYY-MM-DD]
