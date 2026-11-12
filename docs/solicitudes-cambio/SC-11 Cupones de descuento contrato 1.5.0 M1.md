# Solicitud de Cambio (SC-11 - Cupones de descuento, de M5 a M1, contrato M1 1.5.0)

---

### 1. Identificación
- **Código de Solicitud:** SC-11
- **Fecha:** 2026.11.12
- **Módulo Solicitante:** M5 y M1 (solicitud conjunta: M5 la propone y M1 la acepta y la construye)
- **Persona Solicitante:** Manuel Mejicano (coordinación de M5) y Roberth (M1)

> M5 propuso registrarla como SC-10, pero ese número ya lo usa `SC-10-contrato-http-m7.md`. Por eso se registra como SC-11.

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [x] Especificación de Requerimientos de Software (ERS)
- [x] Modelo de Casos de Uso
- [x] Contrato de Módulo (OpenAPI / PHP Interface)
- [x] Modelo de Diseño / Base de Datos
- [x] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  La gestión y la validación de los cupones de descuento pasan de M5 a M1, para dejarlas junto con las promociones de los comercios. M1 queda como dueño de los datos y publica la versión 1.5.0 de su contrato (`contracts/m1-portal/v1/openapi.yaml` y `ComercialPortalContract`). M5 sigue siendo dueño del pedido: guarda el código y el monto descontado (`cupon_descuento` y `descuento_maestro` de `pedidos_maestros`) y consulta a M1.

  1. **Quién crea los cupones.**
     - Cada comercio crea cupones para sus productos (rol `encargado_comercio`; el comercio sale de la sesión, RN-12).
     - La administración crea los cupones globales de la plataforma (rol `encargado_plataforma`; `id_comercio` en null) y puede desactivar cualquier cupón.
     - Un cupón no se edita: se desactiva y se crea otro. Desactivado, no se reactiva.
  2. **Datos de un cupón.**
     - Código único en toda la plataforma, de 3 a 50 caracteres (letras sin tilde, números, guion y guion bajo). Se guarda en mayúsculas y al validar no distingue mayúsculas.
     - Dos tipos: `porcentaje` (mayor que 0 y menor que 100) o `monto_fijo` en colones (mayor que 0).
     - Fecha y hora de inicio y de fin obligatorias: se puede programar por adelantado.
     - Opcionales: monto mínimo de compra, límite de usos total y límite de usos por cliente (1 por defecto).
  3. **Sobre qué monto descuenta.**
     - Un cupón global descuenta sobre todo el subtotal del carrito.
     - Un cupón de un comercio descuenta solo sobre el subtotal de los productos de ese comercio. Para eso M5 envía el subtotal de cada comercio del carrito (`subtotales_por_comercio`). Si el carrito tiene un solo comercio, basta el subtotal total.
     - El subtotal va sin envío ni impuesto, con los precios de promoción ya aplicados: un cupón se suma a las promociones.
     - El descuento va a 2 decimales y nunca supera la base. El monto mínimo se compara con esa misma base.
     - El descuento de un cupón de comercio lo asume ese comercio; el de un cupón global, la plataforma.
  4. **Validación y uso.**
     - `validarCupon` responde si el cupón aplica y cuánto descuenta, sin registrar nada. Un cupón que no aplica responde `valido=false` con un `motivo` (`no_disponible`, `no_iniciado`, `vencido`, `agotado`, `limite_cliente`, `comercio_no_aplica`, `requiere_subtotal_comercio`, `monto_minimo`, `sin_descuento`) y un mensaje para la persona compradora. Un código inexistente y uno desactivado responden igual, para no revelar qué códigos existen.
     - `registrarUsoCupon` registra el uso cuando M5 confirma el pedido. Es idempotente por pedido y bloquea el cupón dentro de una transacción, para que dos pedidos simultáneos no superen el límite de usos. Si responde `false`, M5 no aplica el descuento.

  **Operaciones nuevas del contrato:**
  - **API HTTP `/api/m1/v1`**, con la sesión y los roles del núcleo:
    - Cliente con sesión: `POST /cupones/validar` (validarCupon), para el botón "Aplicar" del checkout de M5. El cliente sale de la sesión; máximo 30 consultas por minuto.
    - Rol `encargado_comercio`: `POST` y `GET /comercio/cupones` (crearCuponComercio, listarCuponesComercio) y `POST /comercio/cupones/{id_cupon}/desactivar` (desactivarCuponComercio).
    - Rol `encargado_plataforma`: `POST` y `GET /administracion/cupones` (crearCuponGlobal, listarCuponesAdministracion) y `POST /administracion/cupones/{id_cupon}/desactivar` (desactivarCuponAdministracion).
  - **Interfaz PHP `ComercialPortalContract`** (consumidor: M5), sin sesión de usuario:
    - `validarCupon(string $codigo, int $idCliente, float $subtotal, array $idsComercios = [], array $subtotalesPorComercio = []): array` (esquema `ResultadoCupon`).
    - `registrarUsoCupon(int $idCupon, int $idCliente, int $idPedido): bool`.
    - Van en `ComercialPortalContract` y no en `PortalPublicoContract`, para no obligar al doble `PortalStub` de M5 a agregar métodos.
  - **Diferencias con la propuesta de M5:**
    - `validarCupon` agrega el quinto parámetro opcional `$subtotalesPorComercio` (y `subtotales_por_comercio` en la ruta), para que un cupón de comercio descuente solo sobre los productos de ese comercio.
    - La respuesta agrega `id_comercio` y `motivo`.
    - En la ruta, `id_cliente` es opcional y debe ser el de la sesión.
    - Se agregan las rutas de la administración para los cupones globales.
  - **Código propio nuevo (409):** `ERR-M1-007`, ya existe un cupón con ese código.

  **Base de datos de M1** (migraciones `2026_11_12_000001` y `000002`, pendientes de aplicar en la base compartida):
  - `cupones`: `id_cupon`, `id_comercio` (nullable, llave a `comercios`), `codigo` (único), `descripcion`, `tipo_descuento`, `valor`, `monto_minimo_compra`, `fecha_inicio`, `fecha_fin`, `limite_usos_total`, `usos_actuales`, `limite_usos_por_cliente`, `activo`, `id_usuario_crea`.
  - `cupon_usos`: un uso por pedido (`id_cupon`, `id_cliente`, `id_pedido`), único por cupón y pedido. `id_pedido` es una referencia lógica al pedido de M5, sin llave foránea.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [x] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M5:**
    - En el checkout, el botón "Aplicar" llama a `POST /api/m1/v1/cupones/validar` con el subtotal y, si el carrito tiene varios comercios, con `subtotales_por_comercio`.
    - Al confirmar el pedido, vuelve a validar con `ComercialPortalContract::validarCupon`, guarda `monto_descuento` en `descuento_maestro` y llama a `registrarUsoCupon` con el `id_cupon` y el id del pedido. Si `registrarUsoCupon` responde `false`, no aplica el descuento.
    - Decide cómo reparte el descuento entre los subpedidos: el de un cupón de comercio va al subpedido de ese comercio (`id_comercio` de la respuesta).
  - **M2:** la comisión se calcula sobre el subtotal del subpedido que envía M5. Falta definir con M5 y M2 si ese subtotal ya lleva restado el descuento de un cupón de comercio.
  - **M6 y M7:** cobran y registran el total que entrega M5, ya con el descuento. Sin cambios en sus contratos.
- **Contratos afectados:**
  - Contrato de M1, de 1.4.0 a 1.5.0 (versión menor). Solo agrega rutas, esquemas y dos métodos en `ComercialPortalContract`, que fuera de M1 solo implementa el doble `ComercialPortalFake`. No cambia ninguna operación existente. Se registra en `x-historial-versiones`.
  - Contrato de M5: el alcance de cupones (validación y reglas) sale de M5; `cupon_descuento` y `descuento_maestro` se mantienen.
- **Artefactos por actualizar en la documentación:**
  - PC1, ficha de M1: agregar al alcance "Administración y validación de cupones de descuento" y, en las salidas hacia M5, "Validación y registro de uso de cupones".
  - PC2: requerimientos "Gestionar cupones de descuento" y "Validar cupones en el checkout", y el caso de uso "Gestionar cupones de descuento" (actores: encargado de comercio y encargado de plataforma). El CU de M5 "Confirmar compra" incluye la validación en M1.
  - PC3: tablas `cupones` y `cupon_usos` en el modelo relacional de M1; clases `Cupon`, `CuponUso` y `CuponesService` en el diagrama de clases; llamada sincrónica de M5 a M1 en la secuencia del checkout.
  - Artefactos colectivos: fila M1 → M5 de la matriz de interconexión.
- **Artefactos actualizados en el repositorio:**
  - `contracts/m1-portal/v1/openapi.yaml`, `app/Contracts/M1_Portal/ComercialPortalContract.php` y `app/Contracts/M1_Portal/Exceptions/CuponDuplicadoException.php`.
  - Migraciones `2026_11_12_000001_create_cupones_table` y `2026_11_12_000002_create_cupon_usos_table`.
  - `CuponesService`, `CuponesController`, rutas de `routes/modules/m1_api.php`, modelos `Cupon` y `CuponUso`, el doble `ComercialPortalFake` y las pruebas `CuponesTest`.
  - `frontend/src/modules/M1_Portal`: pestaña "Cupones" del panel de gestión.
- **Esfuerzo estimado:** 1 día

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-11
- **Fecha de Resolución:** [YYYY-MM-DD]
