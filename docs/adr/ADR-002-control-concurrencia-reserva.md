# Registro de Decisión Arquitectónica (ADR)
**Pacíficos Online**

---

## ADR-002: Bloqueo Pesimista para el Control de Concurrencia en la Reserva de Existencias

- **Estado:** Aceptado
- **Fecha:** 2026-11-06
- **Decisores:** Comité de Arquitectura e Integración
- **Módulos Afectados:** M4 (Inventario), M5 (Carrito y Pedidos)

---

### 1. Contexto y Planteamiento del Problema
El ADR-001 define la reserva temporal de existencias para el checkout. Falta decidir cómo se garantiza que dos reservas simultáneas no se lleven la misma unidad. Con una sola unidad disponible y dos solicitudes casi al mismo tiempo, ambas podrían leer "hay stock" y descontarlo, produciendo sobreventa (regla RN-04). El atributo de calidad en juego es la consistencia estricta de las existencias bajo concurrencia, sin sacrificar el tiempo de respuesta del checkout.

---

### 2. Factores Decisores (Drivers Arquitectónicos)
- Consistencia estricta: nunca se reserva más de lo disponible (disponible = cantidad física − cantidad reservada).
- Cuando la solicitud no indica ubicación, M4 elige una ubicación con toda la cantidad disponible; esa elección debe ser atómica con el descuento.
- Reintentos seguros: una misma clave de idempotencia con los mismos datos devuelve la misma reserva.
- Un cambio de estado de la reserva no puede dejar el contador de reservado inconsistente.
- Las existencias de un comercio no deben bloquearse entre sí con las de otro.

---

### 3. Opciones Consideradas
1. **UPDATE condicional sin bloqueo previo:** descontar con `UPDATE existencias SET cantidad_reservada = cantidad_reservada + :n WHERE ... AND disponible >= :n` y revisar las filas afectadas. Es simple para una fila, pero la elección de ubicación y la actualización del historial de la reserva obligan a leer y decidir antes de escribir.
2. **Bloqueo pesimista (`SELECT ... FOR UPDATE`):** dentro de una transacción se bloquean las filas de existencias involucradas, se valida el disponible, se crea la reserva y se actualiza el contador; las solicitudes concurrentes esperan su turno.
3. **Bloqueo optimista con columna de versión:** leer, calcular y escribir si la versión no cambió; si cambió, reintentar. Bajo alta contención sobre la última unidad produce reintentos repetidos.

---

### 4. Decisión Tomada
Se elige la **Opción 2: bloqueo pesimista**, dentro de `DB::transaction`. En M4 (`InventarioService`):

- `reservarExistencias` crea la reserva y actualiza `cantidad_reservada` bajo bloqueo pesimista de las filas de `existencias`; la solicitud que llega segunda espera y ve el disponible ya descontado.
- `liberarReserva` bloquea con `lockForUpdate` la fila de `reservas` y la de `existencias` antes de cerrar la reserva, de modo que liberar dos veces no descuenta dos veces: una reserva ya liberada o vencida no vuelve a modificar el contador.
- La **idempotencia** se garantiza con la restricción única `uq_reservas_comercio_clave_idempotencia (id_comercio, clave_idempotencia)`. Si dos solicitudes con la misma clave compiten, la que pierde la carrera recibe la violación de unicidad y devuelve la reserva existente.
- La **base de datos protege lo que el código no debe romper**: el trigger `tr_reservas_transicion` solo admite las transiciones 1→2, 1→4, 1→5, 2→3 y 2→4; `tr_reservas_no_delete` impide borrar reservas; y la restricción `ck_existencias_disponible_no_negativo` impide un disponible negativo aunque falle el código.
- Los eventos (`ExistenciaReservada`, `ExistenciaAgotada`, `ExistenciaRepuesta`) se publican **después** de confirmar la transacción, para no emitir un evento de algo que se revirtió.
- La prueba `ConcurrenciaReservaStockTest` verifica el caso crítico: varias solicitudes simultáneas sobre el mismo stock, de las cuales solo se atienden las que caben en el disponible, sin sobreventa.

---

### 5. Consecuencias
- **Positivas:**
  - Se elimina la sobreventa de la última unidad; no hay ventana entre leer el disponible y descontarlo.
  - La elección de ubicación y el descuento son atómicos.
  - Los reintentos con la misma clave de idempotencia son seguros.
  - La base de datos actúa como segunda barrera (triggers y restricciones CHECK).
- **Negativas / Compromisos (Trade-offs):**
  - Las solicitudes sobre la misma existencia se serializan: bajo mucha contención sobre un mismo producto sube la latencia.
  - Si las transacciones se alargan, aumentan las esperas; por eso la transacción solo contiene la reserva y el descuento, y los eventos salen después.
  - Obliga a mantener un orden consistente de bloqueo si en el futuro una operación bloquea varias filas, para evitar interbloqueos.
  - Depende de PostgreSQL (`FOR UPDATE`, triggers); las pruebas de concurrencia requieren PostgreSQL local.
- **Impacto en Contratos / Otros Módulos:**
  - La interfaz `InventarioModuleInterface` documenta `reservarExistencias` como reserva atómica bajo bloqueo pesimista (ADR-002).
  - M5 solo usa la interfaz y no necesita conocer el mecanismo; recibe `ERR-M4-001` cuando no hay existencias suficientes y `ERR-006` si un proveedor no responde.
  - Supera el borrador anterior de UPDATE condicional del PC3; la idempotencia y las transiciones de estado se mantienen.
