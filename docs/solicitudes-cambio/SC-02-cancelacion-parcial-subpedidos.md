# Solicitud de Cambio (SC)
**Anexo B - IF6100 Pacíficos Online**

---

### 1. Identificación
- **Código de Solicitud:** SC-02
- **Fecha:** 2026-09-11
- **Módulo Solicitante:** M5 (Carrito y Pedidos)
- **Persona Solicitante:** Manuel Mejicano

---

### 2. Artefacto Afectado
- [x] Especificación de Requerimientos de Software (ERS)
- [x] Modelo de Casos de Uso
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [ ] Contrato de Módulo (OpenAPI / PHP Interface)
- [ ] Modelo de Diseño / Base de Datos
- [ ] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  El Pedido Maestro puede tener varios subpedidos, uno por comercio. Hoy no está definido qué pasa cuando **un comercio rechaza su subpedido** (por ejemplo, se quedó sin stock) pero **los demás comercios del mismo pedido sí lo van a despachar**. Se solicita que el Comité defina y apruebe formalmente el protocolo de cancelación parcial: cómo queda el estado del Pedido Maestro, y si al cliente se le reembolsa solo la parte del subpedido rechazado o el pedido completo.

- **Origen del cambio:**
  - [x] Hallazgo en sesión de validación
  - [ ] Petición del cliente / persona docente
  - [ ] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:** M4 (liberar la reserva de stock del subpedido rechazado), M6 (reembolso parcial), M7 (ajuste contable de la comisión y el asiento)
- **Contratos afectados:** Ajuste en la operación `CancelarPedido` de M5 y en la lógica de reembolso de M6 (posible reembolso parcial, no solo total)
- **Artefactos por actualizar:** RF-012 (hoy incompleto en la tabla de requerimientos), RN-005 (agregar el caso del rechazo del comercio), CU-03 (flujo alterno de cancelación parcial)
- **Esfuerzo estimado:** 1 día

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** *(pendiente de sesión)*
- **Justificación de la resolución:** *(se completa después de la reunión con el Comité)*
- **Número de Acta del Comité:** *(pendiente)*
- **Fecha de Resolución:** *(pendiente)*
