# Solicitud de Cambio (SC-12 - Búsqueda de comercios y cupones globales en la portada, contrato M1 1.6.0)

---

### 1. Identificación
- **Código de Solicitud:** SC-12
- **Fecha:** 2026.11.12
- **Módulo Solicitante:** M1 Portal público y búsqueda
- **Persona Solicitante:** Roberth (M1)

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [x] Especificación de Requerimientos de Software (ERS)
- [x] Modelo de Casos de Uso
- [x] Contrato de Módulo (OpenAPI / PHP Interface)
- [ ] Modelo de Diseño / Base de Datos
- [x] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  M1 publica la versión 1.6.0 de su contrato (`contracts/m1-portal/v1/openapi.yaml`). El cambio cubre dos faltantes que salieron de la revisión del portal:

  1. **Búsqueda de comercios.** El alcance mínimo pide buscar comercios, pero la portada solo permitía buscar productos.
     - `GET /comercios` (listarComercios) acepta el parámetro opcional `q`, de 2 a 100 caracteres. Busca por nombre comercial sin distinguir mayúsculas ni tildes.
     - M2 no ofrece búsqueda por nombre, así que M1 recorre `listarComerciosHabilitados` en páginas de 50 (hasta 1000 comercios), deja los activos que contienen el término y pagina el resultado. Primero van los nombres que empiezan con el término y luego el resto, en orden alfabético.
     - Sin `q`, la ruta responde igual que en 1.5.0.
     - En el portal, el buscador del encabezado tiene un selector "Productos | Comercios". Con "Comercios" abre una pantalla de resultados con las tarjetas de los comercios, que llevan a su micrositio.
  2. **Cupones globales visibles para el público.** Los cupones que publica la administración (SC-11) no se mostraban en ningún lugar del portal.
     - Ruta pública nueva `GET /cupones/vigentes` (listarCuponesVigentes). Devuelve hasta 12 cupones globales activos, dentro de su vigencia y con usos disponibles; primero los que vencen antes.
     - Solo expone los datos que necesita el cliente (esquema `CuponPublico`: código, descripción, tipo y valor del descuento, monto mínimo, fecha de fin y límite por cliente). No incluye los cupones de los comercios ni los contadores de uso.
     - En el portal, la portada muestra la sección "Cupones disponibles" con el código para copiar.

  **Interfaces PHP:** `PortalPublicoContract` y `ComercialPortalContract` no cambian. La búsqueda de comercios y el listado de cupones existen solo en la API HTTP, para no obligar a los dobles de otros módulos (por ejemplo, el `PortalStub` de M5) a agregar métodos.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [x] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M2:** ninguno. M1 solo usa `listarComerciosHabilitados`, que ya consumía. Si más adelante M2 ofrece búsqueda por nombre, M1 puede usarla sin cambiar su contrato.
  - **M5:** ninguno. El checkout sigue validando los cupones con `POST /cupones/validar`.
- **Contratos afectados:**
  - Contrato de M1, de 1.5.0 a 1.6.0 (versión menor). Agrega un parámetro opcional y una ruta con sus esquemas (`ListadoCuponesVigentes` y `CuponPublico`). Ninguna operación existente cambia de comportamiento. Se registra en `x-historial-versiones`.
- **Artefactos por actualizar en la documentación:**
  - PC2: el caso de uso de búsqueda incluye la búsqueda de comercios por nombre; la portada muestra los cupones globales vigentes.
  - PC3: secuencia de la búsqueda de comercios (M1 filtra el listado de M2).
- **Artefactos actualizados en el repositorio:**
  - `contracts/m1-portal/v1/openapi.yaml`.
  - `MicrositioService::buscarComercios`, `PortalPublicoService::buscarComercios`, `ComercioController`, `CuponesService::listarGlobalesVigentes`, `CuponesController::vigentes` y `routes/modules/m1_api.php`.
  - Pruebas `BusquedaComerciosTest` y `CuponesTest`.
  - `frontend/src/modules/M1_Portal`: selector del buscador, pantalla de resultados de comercios y sección de cupones en la portada.
- **Esfuerzo estimado:** 1 día

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-12
- **Fecha de Resolución:** [YYYY-MM-DD]
