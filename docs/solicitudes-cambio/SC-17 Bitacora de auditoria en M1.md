# Solicitud de Cambio (SC-17 - Las operaciones de M1 no se registran en la bitácora de auditoría)

---

### 1. Identificación
- **Código de Solicitud:** SC-17
- **Fecha:** 2026.11.19
- **Módulo Solicitante:** M1 Portal público y búsqueda
- **Persona Solicitante:** Subgrupo SG1 (redacta Benjamín Pizarro Chaves)

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [ ] Especificación de Requerimientos de Software (ERS)
- [ ] Modelo de Casos de Uso
- [ ] Contrato de Módulo (OpenAPI / PHP Interface)
- [x] Modelo de Diseño / Base de Datos
- [ ] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  Se corrige el diagrama de clases de diseño de M1 (sección 4 del PC3) para quitar el uso de la bitácora de auditoría del núcleo en los servicios de M1. Las operaciones propias de M1 no se registran en `bitacora_auditoria`.

  1. **Qué decía el diseño.** En el PC3, ClienteService y ContenidoPortadaService conocen a la clase «núcleo» Bitacora para registrar quién crea o modifica un cliente, una promoción o una noticia (tabla 4.1 y tabla 4.2).
  2. **Por qué no aplica.** La sección 5.4 del documento colectivo del PC3 define cuáles son las operaciones sensibles que van a la bitácora. Son aprobar, rechazar, suspender y reactivar un comercio, crear y desactivar un Encargado de la plataforma, cualquier cambio de rol o permiso, la confirmación de pago, la generación de una nota de crédito y cada uso del acceso auditado del Encargado de la plataforma. Ninguna operación de M1 está en esa lista. Registrar un cliente o actualizar sus datos, sus direcciones, las promociones, las noticias, los destacados y los cupones son operaciones de contenido o de datos de la persona compradora, no operaciones sensibles.
  3. **La única operación sensible ya queda registrada.** Cuando el Encargado de la plataforma entra a las rutas de administración de M1, el middleware `EstablecerComercio` del núcleo le concede el acceso auditado y lo registra con `NucleoServiceInterface::registrarAuditoria`. M1 no necesita registrarlo otra vez.
  4. **Lo que no cambia.** La creación de la cuenta del cliente la sigue haciendo el núcleo con `ServicioIdentidad::crearCuenta`, que asigna siempre el rol cliente, así que no es un cambio de rol ni de permiso.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [x] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:** Ninguno. La bitácora y el acceso auditado siguen como los define el núcleo.
- **Contratos afectados:** Ninguno. La bitácora no forma parte del contrato de M1.
- **Artefactos por actualizar en la documentación:**
  - PC3, sección 4. Quitar la relación con Bitacora de ClienteService y ContenidoPortadaService en la vista 4 y en la tabla 4.1, y la fila «núcleo» Bitacora de la tabla 4.2.
  - PC4, sección 2.3. Filas de ClienteService y ContenidoPortadaService.
- **Artefactos actualizados en el repositorio:** Ninguno. El código de M1 no registra en la bitácora, así que no se modifica.
- **Esfuerzo estimado:** Ninguno en el código. 0,5 días para actualizar los documentos.

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-17
- **Fecha de Resolución:** [YYYY-MM-DD]
