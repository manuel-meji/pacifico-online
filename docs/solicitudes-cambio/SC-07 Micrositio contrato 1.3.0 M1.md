# Solicitud de Cambio (SC-07 - Micrositio: aviso cuando M3 no responde y validación de la paginación, contrato M1 1.3.0)

---

### 1. Identificación
- **Código de Solicitud:** SC-07
- **Fecha:** 2026.11.07
- **Módulo Solicitante:** M1
- **Persona Solicitante:** Daniel Gatgens

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
  Se publica la versión 1.3.0 del contrato de M1 (contracts/m1-portal/v1/openapi.yaml) con dos cambios en la vista de micrositio (CU-009) y en la página de comercios:
  1. **Aviso cuando M3 no responde.** El esquema Micrositio agrega el campo opcional `productos_disponibles` (booleano). Si M3 no responde, el micrositio ya no falla: se muestra con la información del comercio que entregó M2, la lista de productos vacía y `productos_disponibles` en `false`, para que el portal muestre un aviso. Así se distingue de un comercio que no tiene productos publicados (flujo A2), que sigue con `productos_disponibles` en `true`.
  2. **Validación de la paginación.** `GET /comercios` y `GET /comercios/{id_comercio}/micrositio` responden 422 con ERR-005 y el detalle por campo (nueva respuesta `PaginacionInvalida`) cuando `pagina` no es un entero mayor o igual a 1 o `por_pagina` no es un entero de 1 a 50. En la versión 1.2.0 el contrato no declaraba respuesta para ese caso.

  En la interfaz PHP no cambia ninguna firma; solo se documenta `DatosInvalidosException` en `listarComercios` y `obtenerMicrositio` para la paginación fuera de rango.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [x] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:** Ninguno. Se revisó el repositorio: el único consumidor declarado de `listarComercios` y `obtenerMicrositio` es el frontend del portal (M1). M5 tiene un doble de M1 (`PortalStub`) que implementa `PortalPublicoContract`, pero no llama a estas dos operaciones y, como las firmas no cambian, sigue funcionando sin cambios. Las llamadas de M5 a un micrositio son a M2 (`ComerciosServiceInterface`), no a M1.
- **Contratos afectados:** Contrato de M1, de 1.2.0 a 1.3.0 (versión menor). No rompe compatibilidad: `productos_disponibles` es un campo opcional nuevo y la respuesta 422 solo aplica a dos rutas web cuyo único consumidor es el frontend de M1. Se registra en `x-historial-versiones`.
- **Artefactos por actualizar:** contracts/m1-portal/v1/openapi.yaml; código de M1 (MicrositioService, ComercioController, PortalPublicoService, MicrositioDTO) y sus pruebas (tests/Feature/M1_Portal/MicrositioServiceTest.php).
- **Esfuerzo estimado:** 3 h

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-07
- **Fecha de Resolución:** [YYYY-MM-DD]
