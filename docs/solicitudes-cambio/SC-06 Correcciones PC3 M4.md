# Solicitud de Cambio (SC-06 - Correcciones del PC3 de M4)

---

### 1. Identificación
- **Código de Solicitud:** SC-06
- **Fecha:** 2026-11-06
- **Módulo Solicitante:** M4
- **Persona Solicitante:** Jorge Alvarado Moreno

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
  Se atienden las observaciones de la persona docente a la rúbrica del PC3 de M4:

  - **Contrato v1.3.0.** El contrato v1.2.0 publicaba 20 operaciones, pero la interfaz PHP declaraba solo 6. Se publica `contracts/m4-inventario/v1/openapi_M4_v1.3.0.yaml`, cuyo `x-interfaz-php` documenta las 20 operaciones con su firma, parámetros, retorno y errores. `InventarioService` declara los 20 métodos: 7 ya funcionan (`verificarEstado`, las cuatro consultas de disponibilidad, `reservarExistencias` y `liberarReserva`) y los otros 13 lanzan una excepción que indica que se construyen en el PC4.
  - **Interfaz PHP en dos pasos.** `InventarioModuleInterface` no se amplía todavía, porque los dobles de M1 y M5 la implementan directamente y se romperían. Se agrega `App\Services\M4_Inventario\Dobles\InventarioDobleBase`, que ya declara las 20 firmas. Cuando M1 y M5 cambien su doble a `extends InventarioDobleBase`, un PR de M4 pasa la interfaz a las 20 operaciones.
  - **Interfaz anterior.** Se documenta `InventarioServiceInterface` como interfaz anterior, que M1 y M5 siguen usando hasta migrar. No cambia ninguna firma.
  - **Stock defectuoso.** El contrato declaraba que M4 no maneja stock defectuoso (SC-M4-02), pero las tablas lo guardaban y lo restaban del disponible. La migración `2026_11_02_000008_m4_retirar_stock_defectuoso` quita `cantidad_defectuosa` y `saldo_defectuoso` y rehace el CHECK del disponible (física − reservada). Se ajustan el servicio, las pruebas, el SQL de referencia `db/M4_Inventario/M4_esquema_6_5.sql` y el prototipo.
  - **Controlador sin uso.** Se retira `DisponibilidadController`, que no tenía rutas ni consumidores y no formaba parte del diseño.
  - **Diseño.** En el documento del PC3 se completa la tabla 4.1, que tenía celdas truncadas, y se alinea con las figuras. La figura 4.8 y DS-04 se rehacen con los nombres y métodos del diseño (DS-04 usaba los del análisis), con mensajes numerados que la 4.8 referencia. La figura 4.2a usa las firmas reales de la fachada.
  - **ADR de concurrencia.** El Anexo A pasa de «Propuesto» con UPDATE condicionado a «Aceptado» con `SELECT … FOR UPDATE`, que es lo que implementa el código y lo que registra el ADR-002 del PC3 colectivo. Se agrega `docs/adr/ADR-002-control-concurrencia-reserva.md`.
  - **Prueba de concurrencia.** La prueba que cita el PC3 colectivo (ESC-01) no existía con ese nombre y la real se omitía por defecto. Se agrega `tests/Feature/M4_Inventario/ConcurrenciaReservaStockTest.php`, que lanza diez solicitudes simultáneas sobre la última unidad y corre siempre que el `.env` apunte a un PostgreSQL local. Contra la base compartida se omite, para no dejarle datos de prueba. Se elimina la prueba anterior, `ReservaUltimaUnidadConcurrencyTest.php`.
  - **Aislamiento por comercio.** `liberarReserva` ya no busca la reserva solo por su id: si la reserva es de otro comercio que el de la sesión, responde `ERR-003` (404). El paso de `DB::table` a modelos Eloquent con el Global Scope `PerteneceAComercio` del núcleo se hace en el PC4, coordinado con el núcleo.
  - **Ajuste a la base de M2.** M2 publicó `obtenerComercioActivo` (PR #120), y la prueba `test_si_m2_real_aun_no_publica_la_operacion_responde_err_006` comprobaba que no existía, así que fallaba en la suite de todos los módulos. Se reemplaza por `test_con_m2_real_m4_usa_el_estado_del_comercio`, que verifica `estado` y `activo` con M2 real. El doble `ComerciosContratoM2` pasa a `extends ComerciosDobleBase`, como pidió M2.
- **Origen del cambio:**
  - [x] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [x] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M1 y M5:** no cambian nada en este paso. Sus dobles siguen funcionando, porque `InventarioModuleInterface` y `InventarioServiceInterface` no cambian de firmas. En el paso siguiente deben cambiar su doble a `extends InventarioDobleBase`. M5 además debe llamar a `confirmarReserva` con `id_pedido` e `id_subpedido`, como dice el contrato.
  - **M2:** ninguno. M4 adapta su doble y su prueba a la base que M2 publicó.
  - **M7:** ninguno. `ObtenerCostoProductosVendidos` queda documentada en el contrato y se construye en el PC4.
  - **Todos:** la suite deja de fallar en la prueba de M4 que hoy aparece en los PR de cada módulo.
- **Contratos afectados:** contrato de M4, de v1.2.0 a v1.3.0 (MINOR). No rompe compatibilidad: no cambia ninguna operación, campo ni código de error; completa la documentación de la interfaz PHP y alinea el modelo de datos con lo que el contrato ya decía.
- **Artefactos por actualizar:** documento PC3 de SG4 (secciones 2.1, 2.5, 4.1 y 4.2; figuras 4.2a, 4.3a y 4.8; DS-04; secciones 6.1 a 6.5 y diagrama ER; anexos A, B y D); PC3 colectivo, ESC-01 (mecanismo sin `cantidad_defectuosa` y nombre de la prueba); `openapi_M4_v1.3.0.yaml`; ADR-002; código y pruebas de M4.
- **Esfuerzo estimado:** [confirmar horas]

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-[XX]
- **Fecha de Resolución:** [YYYY-MM-DD]
