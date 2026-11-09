# Solicitud de Cambio (SC-08 - Productos destacados, promociones y noticias del portal, contrato M1 1.4.0)

---

### 1. Identificación
- **Código de Solicitud:** SC-08
- **Fecha:** 2026.11.08
- **Módulo Solicitante:** M1
- **Persona Solicitante:** [Nombre de la persona solicitante]

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [ ] Especificación de Requerimientos de Software (ERS)
- [ ] Modelo de Casos de Uso
- [x] Contrato de Módulo (OpenAPI / PHP Interface)
- [x] Modelo de Diseño / Base de Datos
- [x] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  Se publica la versión 1.4.0 del contrato de M1 (contracts/m1-portal/v1/openapi.yaml y PortalPublicoContract) con las reglas de productos destacados, las promociones de los comercios y las noticias de la plataforma.

  1. **Productos destacados en la página principal.**
     - La página principal muestra hasta 12 productos destacados. Si hay más vigentes, se rotan por turnos de 15 minutos: cada turno avanza 12 posiciones, así todos aparecen el mismo tiempo.
     - Cada comercio tiene como máximo 3 productos destacados activos al mismo tiempo, contando los que solicita el comercio y los que pone la administración.
     - Un destacado deja de mostrarse solo si el producto se agota, si el comercio lo desactiva o si el comercio es suspendido. Vuelve a aparecer solo si recupera existencias dentro del periodo. El periodo sigue corriendo y no se reembolsa.
  2. **Solicitud, aprobación y cobro de destacados.**
     - El comercio solicita destacar un producto por 7, 15 o 30 días. La administración de la plataforma aprueba o rechaza la solicitud.
     - La administración también puede destacar productos por su cuenta, por ejemplo para una campaña, sin costo para el comercio.
     - Cada periodo tiene una tarifa fija que define la administración. El monto no se paga aparte: se descuenta de la liquidación del comercio. M1 registra un cargo por cada destacado aprobado con costo y publica la consulta de cargos por comercio y periodo.
  3. **Promociones de los comercios (M1 es el dueño de los datos).**
     - Solo hay dos tipos: porcentaje de descuento y precio fijo rebajado.
     - La fecha y hora de inicio y de fin son obligatorias. Una promoción se puede programar por adelantado y se activa y desactiva sola.
     - Se aplica al producto completo (todas sus variaciones) o solo a variaciones específicas. Un mismo producto o variación no puede tener dos promociones activas que se traslapen.
     - Cada comercio crea y gestiona sus propias promociones. La administración decide el orden en que se muestran en el portal y puede ocultar una promoción que incumpla las políticas. El portal muestra automáticamente las vigentes.
     - El descuento lo asume el comercio. La comisión se calcula sobre el precio que realmente pagó el cliente, con el descuento aplicado.
  4. **Noticias de la plataforma.** Contenido informativo que publica únicamente la administración (comercios nuevos, campañas de temporada, avisos de mantenimiento, cambios en políticas), con título, contenido, imagen y fecha de publicación. Una noticia puede enlazar promociones, pero es una entidad aparte. Los comercios no publican noticias.

  **Operaciones nuevas del contrato (nombres por confirmar en la publicación 1.4.0):**
  - API HTTP `/api/m1/v1`, con la sesión y los roles del núcleo:
    - Rol `encargado_comercio`, con aislamiento por comercio (RN-12): solicitar destacado, listar sus solicitudes, crear, actualizar, desactivar y listar sus promociones.
    - Rol `encargado_plataforma`: resolver solicitudes, destacar productos sin costo, definir tarifas, ordenar y ocultar promociones, y publicar, editar y archivar noticias.
    - Públicas: promociones vigentes y noticias publicadas. El precio con promoción se agrega a los listados de productos y a la ficha.
  - Interfaz PHP `PortalPublicoContract`, para los backends de otros módulos:
    - `obtenerPreciosVigentes(array $idsVariacion)`: la usa M5.
    - `listarCargosDestacado(int $idComercio, desde, hasta)`: la usa quien liquida al comercio.

  **Base de datos de M1:**
  - Tablas nuevas para las solicitudes de destacado, las tarifas, los cargos, las promociones y las variaciones de cada promoción.
  - `productos_destacados` agrega el comercio, el origen (solicitud o administración) y la solicitud de la que viene.
  - La tabla `promociones_portal` (pieza de carrusel sin descuento) queda en desuso. No se elimina porque la base de datos es compartida.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M2:**
    - Su panel del comercio y de la administración llama a la API HTTP de M1 para destacados y promociones.
    - Quien liquide al comercio (M2 o M7, por confirmar) descuenta los cargos por destacados que publica M1.
    - La comisión se calcula sobre el subtotal ya rebajado que envía M5.
  - **M3:**
    - M1 necesita el estado del producto (activo o desactivado), el precio por variación y el `id_comercio` en las operaciones de consulta que ya consume.
    - M3 no cambia sus precios.
  - **M5:**
    - Consulta a M1 el precio vigente de cada variación al agregar al carrito y al confirmar el pedido.
    - Guarda el precio pagado y el descuento por línea.
    - Envía a M2 el subtotal rebajado.
  - **M7:** registro contable de los cargos por destacados, si le corresponde.
- **Contratos afectados:**
  - Contrato de M1, de 1.3.0 a 1.4.0 (versión menor). Solo agrega operaciones y campos opcionales, así que no rompe a los consumidores actuales.
  - El valor por defecto y el máximo del parámetro `limite` de `GET /destacados` pasan a 12. Un valor mayor se sigue ajustando al máximo, sin error.
  - Se registra en `x-historial-versiones`.
- **Artefactos por actualizar:**
  - contracts/m1-portal/v1/openapi.yaml y app/Contracts/M1_Portal/PortalPublicoContract.php
  - Migraciones de database/migrations/M1_Portal_y_Busqueda
  - Servicios, controladores y rutas de M1, y sus dobles y pruebas
  - frontend/src/modules/M1_Portal
- **Esfuerzo estimado:** [Por estimar]

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-08
- **Fecha de Resolución:** [YYYY-MM-DD]
