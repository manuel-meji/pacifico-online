# Solicitud de Cambio (SC)
**Anexo B - IF6100 Pacíficos Online**

---

### 1. Identificación
- **Código de Solicitud:** SC-09
- **Fecha:** 2026-11-10
- **Módulo Solicitante:** M5 (Carrito y Pedidos)
- **Persona Solicitante:** Manuel Mejicano (Coordinador SG5)

---

### 2. Artefacto Afectado
- [x] Especificación de Requerimientos de Software (ERS)
- [x] Modelo de Casos de Uso (CU-001 y CU-004 de M5)
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [x] Contrato de Módulo (OpenAPI / PHP Interface de M5)
- [x] Modelo de Diseño / Base de Datos
- [x] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  Incorporación de las capacidades pendientes del alcance mínimo formal del Módulo M5:
  1. **Lista de Deseos (Wishlist):** Se habilita el almacenamiento de artículos de interés por cliente mediante la tabla autocontenida `pedidos_deseos`, permitiendo su consulta (`GET /api/m5/v1/deseos`), agregado (`POST /api/m5/v1/deseos`), eliminación y transferencia directa al carrito de compras (`POST /api/m5/v1/deseos/{id}/mover-al-carrito`).
  2. **Solicitud de Devolución de Subpedidos Entregados:** En el ciclo de vida del subpedido, una vez alcanzado el estado terminal `entregado`, el cliente puede emitir una solicitud formal de devolución (`POST /api/m5/v1/subpedidos/{id}/devolucion`) indicando el motivo respectivo. Esta acción genera un registro con bandera de requerimiento de reembolso hacia Pagos (M6) y asienta el evento en la bitácora de estados.
  3. **Enlace de Navegación Retorno:** El logotipo oficial de Pacíficos Online en el encabezado de M5 opera como enlace directo de retorno hacia el portal principal (`/app.html`).

- **Origen del cambio:**
  - [x] Hallazgo en sesión de validación y alineación de alcance mínimo (Tabla 5.1 ERS)
  - [x] Petición del cliente / persona docente
  - [ ] Defecto detectado en integración
  - [x] Necesidad interna del equipo

- **Justificación:**
  Garantiza el cumplimiento total del alcance funcional asignado a SG5 en el documento de especificación sin alterar ni comprometer contratos, tablas o servicios de los módulos consumidores (M1, M2, M3, M4, M6 y M7).

---

### 4. Análisis de Impacto
- **Módulos Afectados:**
  - M5: Nuevas rutas, controlador `ControladorDeseos`, método `solicitarDevolucionSubpedido` en `ControladorPedido`, migración `create_pedidos_deseos_table` y vista React `ListaDeseosView.jsx`.
  - Otros módulos: **Impacto Cero.** No modifica firmas de contratos OpenAPI v1.1.0 existentes ni altera tablas de otros subgrupos.
- **Riesgo técnico:** Bajo. Las 64 pruebas automatizadas de M5 (`tests/Feature/M5_Pedidos`) aprueban satisfactoriamente.

---

### 5. Estado de la Solicitud
- **Estado:** Implementada y validada en rama `feature/integracion-general`. Lista para revisión y aprobación por el Comité de Arquitectura.
