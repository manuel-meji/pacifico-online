# Solicitud de cambio: contrato HTTP de M7

**Anexo B - IF6100 Pacíficos Online**

> **Estado: pendiente de revisión.** El número SC-10 se propone para esta solicitud y debe confirmarse con el Comité; esta solicitud no constituye la aprobación de un contrato.

## 1. Identificación

- **Código de solicitud:** SC-10, sujeto a confirmación por el Comité.
- **Fecha de preparación:** 2026-11-11.
- **Módulo solicitante:** M7 - ERP contable y reportes gerenciales.
- **Persona solicitante:** David Ávila Torres.
- **Referencia técnica:** `desarrollo` en `d22f9c8`.

## 2. Artefactos afectados

- [x] Contrato de módulo HTTP/OpenAPI de M7.
- [ ] Contrato PHP de M7: no se propone cambiarlo en esta solicitud; sus consumidores deben revisarse por separado.
- [ ] Modelo de diseño o base de datos: no se propone modificarlo en esta solicitud.
- [ ] Código y pruebas: no se solicita su modificación automática. Se requiere planificar la implementación y la validación una vez se resuelva qué contrato debe ofrecerse.

## 3. Descripción y justificación

Se solicita al Comité revisar y decidir cómo alinear el [contrato OpenAPI detallado v1.0.0](../../contracts/m7-contabilidad/v1/openapi_M7_v1.0.0.yaml) con las rutas y respuestas observadas en M7. La matriz siguiente compara las 14 operaciones publicadas con las rutas registradas.

El [OpenAPI v1.1.0 de la implementación observada](../../contracts/m7-contabilidad/v1/openapi_M7_v1.1.0.yaml) describe las ocho rutas registradas, identifica las operaciones y eventos pendientes y conserva las definiciones de v1.0.0 que no se sustituyeron. Esta versión está pendiente de revisión de compatibilidad y aprobación; no reemplaza el contrato vigente ni elimina obligaciones pendientes.

Las principales discrepancias son:

1. El servidor publicado es `/api/v1/m7`; las rutas protegidas observadas usan `/api/m7/v1`, y la consulta de pago usa `/m7`.
2. El contrato publica JWT bearer; las rutas protegidas usan `auth:sanctum` y las pruebas observadas emplean sesión web. Se deben acordar y verificar los mecanismos válidos para cada consumidor.
3. Las respuestas y parámetros de cuentas y libros difieren de los publicados; varias operaciones publicadas no tienen ruta registrada.
4. La ruta web de consulta de pago no declara autenticación ni control por comercio y el servicio consulta por pago sin filtro de comercio. Se solicita una revisión prioritaria antes de autorizar su exposición.
5. El contrato de eventos y el registro actual de listeners divergen. El tratamiento de eventos fallidos no garantiza reintento durable.

### Comparación de operaciones de v1.0.0

Las rutas publicadas son relativas al servidor `/api/v1/m7`; las rutas observadas se muestran completas. **Sin ruta correspondiente** no implica que falte toda lógica interna. **Diferente** indica que la operación observada no es necesariamente compatible.

| Operación publicada | Ruta publicada | Situación observada |
|---|---|---|
| `consultarEstadoContablePedido` | `GET /pedidos/{id_pedido}/estado-contable` | Sin ruta correspondiente registrada. |
| `consultarEstadoContableSubpedido` | `GET /subpedidos/{id_subpedido}/estado-contable` | Sin ruta correspondiente registrada. |
| `consultarRegistroContablePago` | `GET /pagos/{id_pago}/registro-contable` | Diferente: `GET /m7/pagos/{idPago}/registro-contable`; retorna `registrado: false` si no encuentra asiento y carece de controles de autenticación y comercio declarados. |
| `obtenerResumenFinancieroComercio` | `GET /comercios/{id_comercio}/resumen-financiero` | Sin ruta exacta; el reporte gerencial ofrece ingresos, costos y utilidad bruta, no el esquema completo publicado. |
| `consultarRentabilidadComercio` | `GET /comercios/{id_comercio}/rentabilidad` | Sin ruta correspondiente para rentabilidad por centro de costo. |
| `consultarLibroDiario` | `GET /comercios/{id_comercio}/libro-diario` | Diferente: `GET /api/m7/v1/libro-diario`; utiliza `fecha_desde` y `fecha_hasta` opcionales, otros filtros y devuelve `success` y `data` con asientos y totales. |
| `consultarLibroMayor` | `GET /comercios/{id_comercio}/libro-mayor` | Diferente: `GET /api/m7/v1/libro-mayor`; utiliza `id_cuenta_contable`, fechas opcionales con otros nombres y devuelve `success` y `data`. |
| `consultarEstadoResultados` | `GET /comercios/{id_comercio}/estado-resultados` | Sin ruta correspondiente; el reporte gerencial no demuestra equivalencia con el esquema publicado. |
| `consultarBalanceGeneral` | `GET /comercios/{id_comercio}/balance-general` | Sin ruta correspondiente registrada. |
| `consultarTableroEjecutivo` | `GET /comercios/{id_comercio}/tablero` | Sin ruta exacta; el reporte gerencial no demuestra equivalencia con el esquema publicado. |
| `listarCuentasContables` | `GET /comercios/{id_comercio}/cuentas` | Diferente: `GET /api/m7/v1/cuentas`; utiliza contexto de comercio y responde con `success` y `data`, no un arreglo directo. |
| `crearCuentaContable` | `POST /comercios/{id_comercio}/cuentas` | Sin ruta correspondiente; existe el servicio interno `PlanCuentasService::crear`. |
| `consultarCuentaContable` | `GET /comercios/{id_comercio}/cuentas/{id_cuenta_contable}` | Diferente: `GET /api/m7/v1/cuentas/{idCuentaContable}`; utiliza contexto de comercio y envuelve la respuesta. |
| `actualizarCuentaContable` | `PATCH /comercios/{id_comercio}/cuentas/{id_cuenta_contable}` | Sin ruta correspondiente; existe el servicio interno `PlanCuentasService::actualizar`. |

Las rutas adicionales observadas son `GET /api/m7/v1/libro-mayor/cuentas`, `GET /api/m7/v1/reporte-gerencial` y `GET /m7`. El [OpenAPI básico alternativo](../../contracts/m7-contabilidad/v1/openapi.yaml) declara `/health` en otra ruta base; no se identificó esa ruta de salud de M7 entre las rutas registradas.

**Alcance de la comparación:** inspección de [rutas API](../../routes/modules/m7_contabilidad_api.php), [rutas web](../../routes/modules/m7_contabilidad.php), controladores y servicios M7, y `php artisan route:list --path=m7 --except-vendor -v`. No se verificó la conformidad integral de las respuestas con OpenAPI ni se consultó PostgreSQL o Supabase.

### Integración y errores observados

- M7 registra listeners de `PedidoConfirmado` y `SubpedidoDespachado` de M5. El primero origina venta por subtotal; el segundo, costo. El contrato enumera otros eventos y deja pendiente ratificar el reconocimiento de la venta.
- Los listeners registran los fallos sin interrumpir M5, pero no garantizan reintento durable. La consulta de costos de M4 usa una interfaz PHP en proceso, no una llamada HTTP.
- La operación PHP `registrarAsientoDespacho` devuelve temporalmente `true` y registra una advertencia, pero no crea asientos. Su reemplazo requiere coordinación con los consumidores.
- Para las rutas API, el manejador del Núcleo asigna `ERR-001`, `ERR-002`, `ERR-004` y `ERR-005` a errores de autenticación, permisos, recurso inexistente y validación, respectivamente. Algunas excepciones de contexto se procesan como error 500 `ERR-006`, no como 403. Debe acordarse la relación con los códigos `ERR-M7-001` a `ERR-M7-003` publicados.

**Origen del cambio:**

- [x] Hallazgo en revisión del contrato y de la integración.
- [x] Necesidad de alinear documentación, implementación y consumidores.
- [ ] Petición del cliente o persona docente: pendiente de identificar.

## 4. Análisis de impacto

- **M7:** revisar el alcance de las operaciones publicadas, las rutas observadas, sus errores y su seguridad antes de aprobar el contrato definitivo.
- **M5 y M6:** revisar dependencias y consumidores conocidos de estados contables y pagos; identificar cualquier cliente adicional antes de decidir una retirada. Los eventos y las interfaces PHP no deben confundirse con llamadas HTTP.
- **M4 y M2:** coordinar cualquier cambio que afecte dependencias contables o intercambio de información; no se propone modificar sus contratos aquí.
- **Núcleo:** acordar autenticación, permisos, aislamiento y formato de errores.
- **Compatibilidad:** eliminar o cambiar rutas, parámetros obligatorios, respuestas o seguridad puede romper a consumidores de v1.0.0. Se requiere revisar las 14 operaciones y acordar si se implementan, conservan mediante adaptación o sustituyen formalmente.
- **Versionado:** el contrato vigente pide versión mayor y período de convivencia para cambios incompatibles según acuerdo del Comité. No se propone publicar `v1.1.0` como compatible sin ese análisis y aprobación. La versión definitiva y su período de convivencia están pendientes.
- **Artefactos posteriores:** contrato aprobado, inventario de consumidores, plan de migración, pruebas de proveedor/consumidor y documentación de integración; código y pruebas cuando se asigne su implementación.
- **Esfuerzo y responsables:** pendientes de estimación por SG7 y los equipos participantes.

## 5. Decisiones solicitadas

1. Confirmar el alcance obligatorio de cada operación de v1.0.0 y el tratamiento de las que carecen de ruta o difieren.
2. Elegir rutas canónicas, autenticación, autorización, contexto de comercio, formatos y errores, incluyendo la protección de la consulta de pago.
3. Determinar la versión contractual, compatibilidad y convivencia, y el tratamiento del [OpenAPI básico alternativo](../../contracts/m7-contabilidad/v1/openapi.yaml).
4. Ratificar el reconocimiento de venta y coordinar con M4, M5, M6 y Núcleo el contrato de eventos, costo y recuperación de fallos.
5. Asignar responsables, pruebas y fecha de implementación antes de publicar o retirar cualquier operación.

## 6. Resolución del Comité de Arquitectura e Integración

- **Estado:** pendiente de presentación y revisión.
- **Resolución:** pendiente.
- **Justificación:** pendiente.
- **Número de acta:** pendiente.
- **Fecha de resolución:** pendiente.
