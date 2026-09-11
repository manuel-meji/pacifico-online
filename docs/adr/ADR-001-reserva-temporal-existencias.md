# Registro de Decisión Arquitectónica (ADR)
**Pacíficos Online**

---

## ADR-001: Reserva Temporal de Existencias con Expiración (TTL) para el Checkout

- **Estado:** Aprobado
- **Fecha:** 2026-09-08
- **Decisores:** Comité de Arquitectura e Integración
- **Módulos Afectados:** M4 (Inventario), M5 (Carrito y Pedidos)

---

### 1. Contexto y Planteamiento del Problema
Cuando un cliente hace checkout, M5 necesita asegurarse de que el producto siga disponible mientras se procesa el pago. En PC1 no se definió ningún mecanismo para esto, lo que abre la puerta a que dos clientes compren la última unidad de un mismo producto al mismo tiempo (sobreventa). Además, la regla del proyecto es que M5 nunca debe consultar ni modificar directamente la tabla de inventario de M4 (cero joins entre módulos).

---

### 2. Factores Decisores (Drivers Arquitectónicos)
- Evitar sobreventa cuando dos clientes intentan comprar el mismo producto casi al mismo tiempo
- Respetar la frontera entre módulos: M5 no puede leer ni escribir directamente en las tablas de M4
- El checkout no puede bloquear el stock para siempre si el cliente abandona el pago

---

### 3. Opciones Consideradas
1. **Consultar disponibilidad en tiempo real sin reservar nada:** M5 le pregunta a M4 si hay stock justo antes de confirmar, pero sin bloquear nada mientras tanto.
2. **Reserva temporal con expiración (TTL):** M5 le pide a M4 que aparte una cantidad de producto por un tiempo limitado (ej. 15 minutos). Si el pago no se confirma en ese tiempo, la reserva se libera sola.
3. **Reserva indefinida:** M5 aparta el stock y solo se libera si el cliente cancela manualmente.

---

### 4. Decisión Tomada
Se elige la **Opción 2: Reserva temporal con expiración (TTL)**. Al iniciar el checkout, M5 solicita a M4 una reserva con un identificador único y un tiempo de vencimiento (15 minutos). Si M6 confirma el pago dentro de ese plazo, M5 le pide a M4 confirmar la salida definitiva del inventario. Si el plazo vence sin pago confirmado, la reserva se libera automáticamente y el producto vuelve a estar disponible.

---

### 5. Consecuencias
- **Positivas:**
  - Se elimina el riesgo de sobreventa en compras simultáneas
  - Se respeta la frontera entre M4 y M5 (M5 nunca toca el inventario directamente)
  - El stock no queda bloqueado indefinidamente si el cliente abandona el pago
- **Negativas / Compromisos (Trade-offs):**
  - Si el cliente tarda más de 15 minutos en pagar, pierde su reserva y debe reintentar
  - M4 necesita lógica adicional para expirar reservas automáticamente (tarea programada o similar)
- **Impacto en Contratos / Otros Módulos:**
  - M4 debe exponer las operaciones `ReservarStock`, `LiberarReservaStock` y `ConfirmarSalidaInventario`
  - M5 debe guardar el identificador de reserva junto al Pedido Maestro mientras está en estado "Pendiente de Pago"
