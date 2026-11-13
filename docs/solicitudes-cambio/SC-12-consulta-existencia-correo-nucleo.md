# Solicitud de Cambio (SC)
**Anexo B - IF6100 Pacíficos Online**

---

### 1. Identificación
- **Código de Solicitud:** SC-12
- **Fecha:** 2026-11-12
- **Módulo Solicitante:** M2 (Comercios)
- **Persona Solicitante:** Equipo M2 (Comercios) / Coordinación Núcleo

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
  Incorporar en la interfaz del Núcleo (`App\Contracts\Nucleo\ServicioIdentidadInterface`) el método de consulta:
  ```php
  public function existeCorreo(string $correo): bool;
  ```
  junto con su implementación en `App\Services\Nucleo\ServicioIdentidad`.
  El método normaliza el correo idéntico a `crearCuenta` (a minúsculas y sin espacios al inicio o al final), evalúa la existencia en la tabla `usuarios` considerando tanto cuentas activas como inactivas (dado que la restricción de unicidad del correo aplica en toda la tabla sin importar el estado), no abre una transacción propia al ser una consulta de solo lectura, y devuelve `false` sin lanzar excepciones si el correo viene vacío o con formato inválido.

- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [x] Defecto detectado en integración
  - [x] Necesidad interna del equipo

- **Justificación:**
  Durante el registro de una solicitud de comercio (`registrarSolicitud`), M2 requiere verificar de forma temprana que el correo del encargado comercial no se encuentre ya asociado a una cuenta existente en el sistema. Al estar prohibido el acceso directo a la tabla `usuarios` de otros módulos por reglas de aislamiento arquitectónico, el Núcleo debe proveer este mecanismo en su contrato oficial. Esta validación previene registrar solicitudes cuyos encargados no podrán crearse al momento de la aprobación (`aprobarSolicitud`), evitando inconsistencias posteriores.

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M1 (Portal):** Se actualiza su doble de pruebas `tests/Doubles/M1/IdentidadDoble.php` incorporando la implementación en memoria de `existeCorreo()`, asegurando que ninguna prueba de M1 se vea afectada.
  - **M2 (Comercios):** Se habilita el método para ser consumido en la validación de `ComerciosService::registrarSolicitud`.
- **Contratos afectados:** Cambio retrocompatible (no requiere versión mayor v2). Es una adición limpia que amplía el contrato sin modificar los métodos preexistentes (`crearCuenta`, `actualizarCorreo`).
- **Artefactos por actualizar:**
  - `app/Contracts/Nucleo/ServicioIdentidadInterface.php`
  - `app/Services/Nucleo/ServicioIdentidad.php`
  - `tests/Doubles/M1/IdentidadDoble.php`
  - `tests/Feature/Nucleo/ServicioIdentidadTest.php`
- **Esfuerzo estimado:** 1 hora.

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** Aprobada
- **Justificación de la resolución:** El cambio respeta estrictamente los límites modulares y las reglas de diseño (contratos públicos, no acceso directo a persistencia ajena). Su implementación es compatible, de bajo riesgo y resuelve la necesidad de integración entre M2 y Núcleo.
- **Número de Acta del Comité:** ACTA-COMITE-12
- **Fecha de Resolución:** 2026-11-12

