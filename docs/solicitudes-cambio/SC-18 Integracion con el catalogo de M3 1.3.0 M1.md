# Solicitud de Cambio (SC-18 - Integración de M1 con las dos interfaces del catálogo de M3 1.3.0)

---

### 1. Identificación
- **Código de Solicitud:** SC-18
- **Fecha:** 2026.11.23
- **Módulo Solicitante:** M1 Portal público y búsqueda (a pedido de M3 Catálogo de productos)
- **Persona Solicitante:** Subgrupo SG1. En M1 lo implementó Roberth Ponce Juárez y redacta la solicitud Karilyn Cortés Espinoza

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [ ] Especificación de Requerimientos de Software (ERS)
- [ ] Modelo de Casos de Uso
- [ ] Contrato de Módulo (OpenAPI / PHP Interface)
- [x] Modelo de Diseño / Base de Datos
- [x] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  El cliente de integración de M1 con el catálogo (`CatalogoClient`) deja de depender solo de `CatalogoServiceInterface` y pasa a recibir un proveedor de M3 que cumpla las dos interfaces que publica M3 en su contrato 1.3.0: `CatalogoConsultasInterface` y `CatalogoServiceInterface`.

  1. **Qué decía el diseño.** En el PC3, `CatalogoClient` conoce a M3 solo por `CatalogoServiceInterface`. Las operaciones de consulta (`buscarProductos`, `listarCategorias`, `listarMarcas` y `listarRelacionados`) se llamaban con las firmas que M1 esperaba de M3 1.2.0, las mismas del doble `CatalogoFake`. Si el servicio real no las tenía, M1 informaba ERR-006.
  2. **Qué cambió en M3.** Con su contrato 1.3.0 (commit 11c657f, 10/11/2026), M3 separó las consultas en la interfaz `CatalogoConsultasInterface`. Así no obligó a cambiar a los módulos que implementan `CatalogoServiceInterface` en sus dobles (M1, M4 y M5). `CatalogoServiceInterface` se quedó con `obtenerDetalleProducto` y `obtenerVariacion`. Además, `listarMarcas` recibe ahora un filtro opcional `?bool $destacada`.
  3. **Por qué se pidió el cambio.** M3 advirtió que un doble o un proveedor que cumpliera una sola de las dos interfaces podía pasar la construcción del cliente y fallar después, al pedir una operación que no tiene. Para evitarlo, `CatalogoClient` tipa su proveedor con la intersección `CatalogoConsultasInterface&CatalogoServiceInterface`. Un proveedor incompleto se rechaza al construir el cliente y no en medio de una búsqueda.
  4. **Cómo se resolvió en el contenedor.** Laravel no resuelve un tipo de intersección por el nombre de la clase. En `M1IntegracionesServiceProvider` se vincula el parámetro `$contrato` de `CatalogoClient` por su nombre con la instancia que resuelve el modo (doble o real) de `config/integraciones`. El resto de las dependencias (`CachePortal`) se sigue autoconectando.
  5. **Estado en el código.** El cambio ya está en `desarrollo` (commits 4e1a969 y a7a4304, 14/11/2026, antes del congelamiento del 15/11). Esta solicitud regulariza el cambio en los artefactos.
  6. **Lo que no cambia.** El contrato de M1 sigue en la versión 1.7.0. `PortalPublicoContract` y `ComercialPortalContract` no agregan ni quitan operaciones ni campos, y las respuestas a la persona compradora son las mismas. Si M3 no responde, M1 sigue informando ERR-006 y mostrando la página sin esa sección.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [x] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M3:** ninguno. M1 se adapta a las interfaces que M3 ya publicó; M3 no cambia su código.
  - **M4 y M5:** ninguno. Siguen implementando `CatalogoServiceInterface` en sus dobles sin cambios, que es justo lo que buscaba M3 al separar las interfaces.
- **Contratos afectados:**
  - Ninguno. No se publica una versión nueva del contrato de M1, que sigue en 1.7.0. El cambio es interno a la capa de integración de M1 y no rompe a ningún consumidor.
- **Artefactos por actualizar en la documentación:**
  - PC3, sección 4. Diagrama de clases de diseño: `CatalogoClient` depende de `CatalogoConsultasInterface` y de `CatalogoServiceInterface` (antes solo de la segunda), y `CatalogoFake` implementa las dos.
  - PC3, matriz de dependencias de M1. Actualizar la referencia a M3 de la versión 1.2.0 a la 1.3.0 en `buscarProductos`, `listarCategorias`, `listarMarcas` y `listarRelacionados`.
  - `contracts/m1-portal/v1/openapi.yaml`. Actualizar las anotaciones `x-dependencias` que todavía citan M3 1.2.0. Es un cambio solo de documentación y no cambia la versión del contrato.
  - PC4, sección de pruebas. Agregar `CatalogoClientBindingTest` a la lista de pruebas de integración.
- **Artefactos ya actualizados en el repositorio:**
  - `app/Services/M1_Portal/Integraciones/CatalogoClient.php` (constructor tipado con la intersección).
  - `app/Providers/M1IntegracionesServiceProvider.php` (vínculo del parámetro `$contrato`).
  - `app/Services/M1_Portal/Dobles/CatalogoFake.php` (implementa las dos interfaces y `listarMarcas(?bool $destacada = null)`).
  - Dobles de prueba `tests/Doubles/M1/CatalogoContratoM3v130.php` y `CatalogoNoDisponible.php`, y el doble nuevo `CatalogoSoloDetalle.php`, que cumple una sola interfaz.
  - Pruebas `IntegracionesTest` (firma nueva de `listarMarcas`) y `CatalogoClientBindingTest` (`test_resuelve_el_cliente_con_un_doble_que_cumple_ambas_interfaces` y `test_rechaza_un_doble_que_solo_cumple_una_interfaz`).
- **Esfuerzo estimado:** Ninguno en el código, que ya está hecho. 0,5 días para actualizar los documentos.

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** Aprobada
- **Justificación de la resolución:** El cambio es interno a la capa de integración de M1, no modifica el contrato 1.7.0 ni obliga a otros módulos a cambiar su código (ACTA-COMITE-PC4-06, ACU-02). Responde a la advertencia de M3 y hace que un proveedor incompleto falle al construirse y no durante el uso del portal. Se hizo antes del congelamiento del 15 de noviembre.
- **Número de Acta del Comité:** ACTA-COMITE-SC-18
- **Fecha de Resolución:** 2026-11-23
