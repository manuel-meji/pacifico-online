# Acuerdo M2–M7: Cuenta por pagar al comercio

## 1. Antecedentes

El equipo de M2 comunicó las reglas acordadas para el registro de la cuenta por pagar al comercio. Estas reglas fueron sometidas al grupo de coordinadores y no se presentaron objeciones, por lo que se consideran el acuerdo funcional de referencia para la integración entre M2 y M7.

## 2. Reglas acordadas

### 2.1. Momento del registro

La cuenta por pagar al comercio se registra cuando el subpedido es entregado, mediante el evento `SubpedidoEntregado`.

### 2.2. Cálculo del monto

El monto de la cuenta por pagar corresponde al total del subpedido, considerando subtotal con descuento, IVA y envío, menos la comisión proporcionada por M2 mediante `obtenerComisionSubpedido`.

Los valores de referencia comunicados para el escenario 2.2 son:

- **Deportes Pacífico:** ₡24 816,33.
- **Café Volcán:** ₡24 757,46.

### 2.3. Cuentas contables

M7 seleccionará las cuentas contables correspondientes según su propio plan de cuentas. Como referencia, se contempla una cuenta por pagar a comercios dentro del pasivo y una cuenta de ingreso por comisiones.

### 2.4. Tratamiento del IVA de la comisión

Para el PC4, la comisión se registra sin IVA propio. Esta condición se documenta como una limitación conocida del alcance del proyecto.

## 3. Alcance y prioridad

El asiento de la cuenta por pagar al comercio no forma parte del escenario evaluado. Por tanto, su atención no debe retrasar ni bloquear la implementación de los asientos automáticos de venta y costo de ventas correspondientes al paso 8.

## 4. Respaldo del acuerdo

Este documento registra las reglas comunicadas por M2 y aprobadas sin objeciones por el grupo de coordinadores.

Se solicita que el acuerdo también quede incorporado en el acta del Comité, con el propósito de contar con respaldo formal de las decisiones tomadas.

## 5. Referencia de implementación

Este documento establece el acuerdo funcional de referencia para M2–M7. Su inclusión no implica que la funcionalidad de cuenta por pagar al comercio se encuentre implementada.
