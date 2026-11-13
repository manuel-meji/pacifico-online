# Solicitud de Cambio (SC-14 - Identificación tributaria del cliente para la factura de M6, contrato M1 1.7.0)

---

### 1. Identificación
- **Código de Solicitud:** SC-14
- **Fecha:** 2026.11.12
- **Módulo Solicitante:** M6 Pagos (el cambio lo hace M1 Portal público y búsqueda)
- **Persona Solicitante:** Subgrupo de M6; lo implementa Daniel Gatgens (M1)

> M6 pidió el cambio como paso de la versión 1.4.0 a la 1.5.0, pero el contrato de M1 ya va en la 1.6.0 (SC-11 y SC-13). Por eso se publica como 1.7.0.

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
  M1 publica la versión 1.7.0 de su contrato (`contracts/m1-portal/v1/openapi.yaml`). El esquema `Cliente` agrega el campo de salida `identificacion_tributaria`, que es la cédula del cliente (`clientes.cedula`).

  1. **Por qué.** M6 emite la factura con `PortalPublicoContract::obtenerCliente()`. M1 ya guarda la cédula (es obligatoria al registrarse), pero no la devolvía, así que la factura salía sin la identificación del comprador. El adaptador de M6 (`AdaptadorM1`) ya lee `identificacion_tributaria` y deja una cadena vacía si no viene.
  2. **Qué cambia.**
     - `PortalPublicoService` agrega `identificacion_tributaria` al arreglo del esquema `Cliente`.
     - `PortalPublicoContract::obtenerCliente` documenta el campo en su `@return`. La firma no cambia.
     - El doble `tests/Doubles/M1/PortalPublicoFake.php` también lo devuelve. El cliente 77 lleva la cédula `116540892`, la misma que usa el `FakeClienteClient` de M6, para que los dos dobles coincidan.
  3. **Privacidad.** Las rutas web que devuelven el esquema `Cliente` son `GET /clientes/{id_cliente}` (obtenerCliente) y `GET /clientes/sesion` (obtenerClienteSesion). Las dos exigen sesión y solo responden a la propia persona (RN-M1-16, `AccesoCliente`); a cualquier otro, incluido un comercio, le responden 404 ERR-003. Además van con `Cache-Control: no-store`. Por eso la cédula solo la ve su dueño. Una prueba nueva lo verifica.
  4. **Nombre del campo.** Se usa `identificacion_tributaria` y no `cedula` porque es el nombre que pide la factura y el que ya consume M6. En la entrada (`RegistroCliente`) el dato se sigue llamando `cedula`.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [x] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M6:** recibe la cédula en `obtenerCliente`. La prueba `AdaptadoresM6Test::test_m1_la_identificacion_tributaria_aun_no_viaja_en_el_contrato` espera una cadena vacía, así que M6 debe actualizarla para que espere `116540892`. Es un archivo de M6 y M1 no lo modifica.
  - **M5:** ninguno. Lee los mismos campos de antes, y su doble `PortalStub` no necesita cambios porque el campo es opcional.
- **Contratos afectados:**
  - Contrato de M1, de 1.6.0 a 1.7.0 (versión menor). Agrega un campo opcional de salida al esquema `Cliente`. Ningún campo existente cambia ni se quita. Se registra en `x-historial-versiones`.
- **Artefactos por actualizar en la documentación:**
  - PC3: el esquema Cliente del modelo de datos de intercambio incluye la identificación tributaria.
- **Artefactos actualizados en el repositorio:**
  - `contracts/m1-portal/v1/openapi.yaml`.
  - `app/Contracts/M1_Portal/PortalPublicoContract.php` y `app/Services/M1_Portal/PortalPublicoService.php`.
  - `tests/Doubles/M1/PortalPublicoFake.php`.
  - Pruebas `PortalPublicoContractTest`, `ClienteServiceTest` y `AceptacionPerfilClienteTest`.
  - `docs/Reglas_modulos/M1.md`.
- **Esfuerzo estimado:** 0,5 días

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-14
- **Fecha de Resolución:** [YYYY-MM-DD]
