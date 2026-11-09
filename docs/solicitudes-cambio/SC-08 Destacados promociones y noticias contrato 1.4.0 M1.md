# Solicitud de Cambio (SC-08 - Productos destacados, promociones y noticias del portal, contrato M1 1.4.0)

---

### 1. Identificación
- **Código de Solicitud:** SC-08
- **Fecha:** 2026.11.08 (actualizada el 2026.11.09 con lo construido en las fases 1 a 3)
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
  Se publica la versión 1.4.0 del contrato de M1 (`contracts/m1-portal/v1/openapi.yaml`, `PortalPublicoContract` y la interfaz nueva `ComercialPortalContract`) con las reglas de productos destacados, las promociones de los comercios y las noticias de la plataforma.

  1. **Productos destacados en la página principal** (construido).
     - La página principal muestra hasta 12 productos destacados. Si hay más vigentes, se rotan por turnos de 15 minutos: cada turno avanza 12 posiciones de la lista, tomada como circular, así todos aparecen el mismo tiempo y todas las personas ven lo mismo durante el turno.
     - Cada comercio tiene como máximo 3 productos destacados activos al mismo tiempo, contando los que solicita el comercio y los que pone la administración. Los agotados cuentan dentro de esos 3, porque siguen activos aunque no se muestren.
     - Un destacado deja de mostrarse solo si el producto se agota (M4), si el comercio lo desactiva (M3 deja de publicarlo) o si el comercio es suspendido (M2). Vuelve a aparecer solo si recupera existencias dentro del periodo. El periodo sigue corriendo y no se reembolsa.
     - Si M4 no responde, no se oculta ningún destacado: salen marcados como Agotado, para no vaciar la portada por una falla de otro módulo.
  2. **Solicitud, aprobación y cobro de destacados** (construido).
     - El comercio solicita destacar uno de sus productos por 7, 15 o 30 días. La administración de la plataforma aprueba o rechaza la solicitud; el rechazo lleva un motivo obligatorio que ve el comercio.
     - Se cobra la tarifa vigente al momento de solicitar (la que el comercio vio), aunque la administración la cambie antes de aprobar. El periodo empieza al aprobarse.
     - La administración también destaca productos por su cuenta, por ejemplo para una campaña, sin costo y con fecha y hora de inicio y fin libres.
     - Cada periodo tiene una tarifa fija que define la administración. El monto no se paga aparte: se descuenta de la liquidación del comercio. M1 registra un cargo por cada destacado aprobado con costo (una tarifa en 0 no genera cargo) y publica la consulta de cargos por comercio y rango de fechas.
     - Un producto no se destaca dos veces en fechas que se cruzan ni tiene dos solicitudes pendientes.
  3. **Promociones de los comercios; M1 es el dueño de los datos** (construido).
     - Solo hay dos tipos: porcentaje de descuento (mayor que 0 y menor que 100) y precio fijo rebajado (menor que el precio normal de cada variación afectada).
     - La fecha y hora de inicio y de fin son obligatorias. Una promoción se puede programar por adelantado y se activa y desactiva sola.
     - Se aplica al producto completo (todas sus variaciones) o solo a variaciones específicas. Un mismo producto o variación no puede tener dos promociones activas que se traslapen; variaciones distintas del mismo producto sí pueden tener promociones al mismo tiempo.
     - Cada comercio crea y gestiona sus propias promociones. Una programada se edita completa (sin cambiar el producto); una que ya empezó solo puede adelantar su fin o desactivarse; una terminada o desactivada ya no se modifica.
     - La administración decide el orden en que se muestran en el portal y puede ocultar una promoción que incumpla las políticas, con motivo. Mientras está oculta no se muestra ni rebaja el precio, y sigue contando para los traslapes. El portal muestra automáticamente las vigentes.
     - El precio con descuento se calcula a 2 decimales y nunca es mayor que el precio normal. El descuento lo asume el comercio. La comisión se calcula sobre el precio que realmente pagó el cliente, con el descuento aplicado.
  4. **Noticias de la plataforma** (en desarrollo). Contenido informativo que publica únicamente la administración (comercios nuevos, campañas de temporada, avisos de mantenimiento, cambios en políticas), con título, contenido, imagen y fecha de publicación. Una noticia puede enlazar promociones, pero es una entidad aparte. Los comercios no publican noticias. Esta sección se completa cuando se publique la parte de noticias del contrato.

  **Operaciones nuevas del contrato:**
  - **API HTTP `/api/m1/v1`**, con la sesión y los roles del núcleo:
    - Públicas:
      - `GET /destacados/tarifas` (listarTarifasDestacado)
      - `GET /promociones` (listarPromocionesVigentes)
      - En `ProductoResumen` (búsqueda, destacados, micrositio, relacionados) se agrega el campo opcional `promocion`, y en `VariacionFicha` los campos opcionales `precio_promocional`, `id_promocion` y `fin_promocion`. Solo vienen cuando hay una promoción vigente, así que las respuestas sin promoción no cambian.
    - Rol `encargado_comercio`, con aislamiento por comercio (RN-12; el comercio sale de la sesión, nunca del cuerpo):
      - `POST` y `GET /comercio/destacados/solicitudes` (solicitarDestacado, listarSolicitudesDestacado)
      - `POST` y `GET /comercio/promociones` (crearPromocion, listarPromocionesComercio)
      - `PUT /comercio/promociones/{id_promocion}` (actualizarPromocion)
      - `POST /comercio/promociones/{id_promocion}/desactivar` (desactivarPromocion)
    - Rol `encargado_plataforma`:
      - `GET /administracion/destacados/solicitudes` (listarSolicitudesDestacadoAdministracion)
      - `POST /administracion/destacados/solicitudes/{id}/aprobar` y `/rechazar`
      - `POST /administracion/destacados` (destacarProductoPlataforma)
      - `PUT /administracion/destacados/tarifas/{periodo_dias}` (definirTarifaDestacado)
      - `GET /administracion/promociones` (listarPromocionesAdministracion)
      - `PUT /administracion/promociones/{id}/orden`, `POST .../ocultar` y `POST .../mostrar`
  - **Interfaz PHP `PortalPublicoContract`**: agrega `obtenerPreciosVigentes(array $idsVariacion): array`, que usa M5 para cobrar el precio rebajado. Por cada variación devuelve `precio_base`, `precio_final`, `id_promocion` y `fin_promocion` (esquema `PrecioVigente`). Va en esta interfaz porque M5 ya la consume ahí y su doble `PortalStub` ya la implementa con la misma firma.
  - **Interfaz PHP nueva `ComercialPortalContract`** (`x-interfaz-php-comercial`), aparte de `PortalPublicoContract` para no obligar a sus implementaciones a agregar métodos:
    - `listarCargosDestacado(int $idComercio, string $desde, string $hasta): array`, para quien liquida al comercio.
  - **Códigos propios nuevos (409):**
    - `ERR-M1-002`: el comercio ya tiene 3 destacados activos en ese periodo.
    - `ERR-M1-003`: el producto ya está destacado o tiene una solicitud pendiente, o la solicitud ya fue resuelta.
    - `ERR-M1-004`: el producto o una de sus variaciones ya tiene otra promoción activa en fechas que se cruzan.
    - `ERR-M1-005`: la promoción ya terminó o fue desactivada y no se puede modificar.
    - Además, las rutas con rol responden 403 con `ERR-002` del núcleo (respuesta `RolSinPermiso`).

  **Base de datos de M1** (migraciones aplicadas en la base compartida):
  - `2026_11_08_000001` a `000004`: tablas `tarifas_destacado`, `solicitudes_destacado` y `cargos_destacado`; `productos_destacados` agrega `id_comercio`, `origen` (`solicitud` o `plataforma`) e `id_solicitud_destacado`.
  - `2026_11_09_000001` y `000002`: tablas `promociones` y `promocion_variaciones`.
  - Las aprobaciones de destacados de un mismo comercio y las promociones de un mismo producto se serializan con un bloqueo consultivo de PostgreSQL, para que dos operaciones simultáneas no pasen el máximo de 3 ni creen traslapes.
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
    - Quien liquide al comercio (M2 o M7, por confirmar) descuenta los cargos por destacados con `ComercialPortalContract::listarCargosDestacado`.
    - La comisión se calcula sobre el subtotal ya rebajado que envía M5.
  - **M3:**
    - M1 necesita el estado del producto (activo o desactivado), el precio por variación y el `id_comercio` en `buscarProductos` y `obtenerProducto`, que todavía no están en `CatalogoServiceInterface`.
    - M1 usa `obtenerVariacion` (sí publicada) para los precios vigentes; hoy la implementación real devuelve datos fijos de ejemplo.
    - M3 no cambia sus precios.
  - **M5:**
    - Consulta `obtenerPreciosVigentes` al agregar al carrito y al confirmar el pedido (ya implementado por M5).
    - Guarda el precio pagado y el descuento por línea (migración de M5 `2026_11_08_200000_add_promocion_fields_to_pedidos_lineas_table`, pendiente de aplicar por M5).
    - Envía a M2 el subtotal rebajado.
  - **M7:** registro contable de los cargos por destacados, si le corresponde.
- **Contratos afectados:**
  - Contrato de M1, de 1.3.0 a 1.4.0 (versión menor). Solo agrega rutas, campos opcionales, la operación `obtenerPreciosVigentes` (que el único implementador externo, `PortalStub` de M5, ya tiene) y una interfaz aparte, así que no rompe a los consumidores actuales.
  - El valor por defecto y el máximo del parámetro `limite` de `GET /destacados` pasan a 12. Un valor mayor se sigue ajustando al máximo, sin error; `listarDestacados` cambia el valor por defecto de 8 a 12.
  - Se registra en `x-historial-versiones`.
- **Artefactos actualizados:**
  - `contracts/m1-portal/v1/openapi.yaml`, `app/Contracts/M1_Portal/PortalPublicoContract.php`, `app/Contracts/M1_Portal/ComercialPortalContract.php` y las excepciones de `app/Contracts/M1_Portal/Exceptions/`.
  - Migraciones de `database/migrations/M1_Portal_y_Busqueda`.
  - Servicios (`ReglasDestacados`, `PortadaService`, `DestacadosService`, `PromocionesService`), controladores y rutas de M1, con sus dobles (`PortalPublicoFake`, `ComercialPortalFake`, `CatalogoFake`) y pruebas (`PortadaServiceTest`, `ReglasDestacadosTest`, `DestacadosPagadosTest`, `PromocionesTest`).
  - `frontend/src/modules/M1_Portal`: portada con 12 destacados, sección de promociones y precio rebajado en tarjetas y ficha.
- **Esfuerzo estimado:** [Por estimar]

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-08
- **Fecha de Resolución:** [YYYY-MM-DD]
