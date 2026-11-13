# PC4 - Matriz de trazabilidad de requisitos y casos de uso de M7

## 1. Alcance y criterio

Este documento relaciona los requisitos funcionales, las reglas de negocio y los casos de uso definidos para M7 en PC2 con las pruebas existentes en `tests/Feature/M7_Contabilidad`.

Se distingue entre:

- **Verificado:** existe una prueba cuyo propósito corresponde directamente al comportamiento.
- **Parcial:** existen pruebas relacionadas, pero no demuestran por sí solas todo el requisito.
- **Sin evidencia directa:** no se identificó una prueba específica que demuestre el requisito.

Los nombres de las pruebas se indican con su archivo y método exactos. La existencia de una prueba no significa necesariamente que se haya ejecutado durante esta revisión.

## 2. Diferencia de identificadores entre PC2 y PC3

PC2 identifica la generación automática de asientos como:

- **CU-010:** Generar asiento automático de venta.
- **CU-011:** Generar asiento automático de costo de ventas.

PC3 utiliza CU-002 y CU-003 para esos dos procesos. Para evitar confusiones, esta matriz conserva los identificadores de PC2 y señala la referencia de PC3 cuando corresponde.

También existe una diferencia entre los eventos descritos en los documentos de diseño y los registrados actualmente por `M7ContabilidadServiceProvider`: PC3 menciona `PedidoDespachado` y `PagoConfirmado`, mientras que el proveedor actual registra `PedidoConfirmado` y `SubpedidoDespachado`. Esta diferencia debe revisarse para mantener sincronizados diseño, contratos e implementación.

## 3. Trazabilidad de requisitos funcionales y casos de uso

| Requisito / caso de uso PC2 | Comportamiento esperado | Pruebas relacionadas | Estado de evidencia |
|---|---|---|---|
| RF-001 / CU-001 | Gestionar el plan de cuentas. | `PlanCuentasApiTest.php`: `test_lista_cuentas_del_comercio_autenticado`, `test_no_expone_cuentas_de_otro_comercio`, `test_puede_consultar_una_cuenta_del_propio_comercio`, `test_no_puede_consultar_cuenta_de_otro_comercio`. | Parcial: se verifican consultas y aislamiento; no se demuestra toda la gestión. |
| RF-002 / CU-002 | Consultar libro diario. | `LibroDiarioServiceTest.php`: `test_agrupa_lineas_y_calcula_totales_del_libro_diario`, `test_aplica_filtros_de_fecha_tipo_y_estado`, `test_consulta_solamente_los_asientos_del_comercio_activo`. `Contratos/M7_ProveedorContratoTest.php`: `test_libro_diario_cumple_el_esquema`. | Verificado para consulta, filtros y contrato. |
| RF-003 / CU-003 | Consultar libro mayor. | `LibroMayorServiceTest.php`: `test_calcula_saldo_inicial_y_saldo_acumulado`, `test_excluye_pendientes_y_anulados_por_defecto`, `test_no_permite_consultar_cuenta_de_otro_comercio`. `Contratos/M7_ProveedorContratoTest.php`: `test_libro_mayor_cumple_el_esquema`. | Verificado para cálculos, filtros y aislamiento. |
| RF-004 / CU-010 | Generar asiento automático de venta. | `PedidoConfirmadoListenerTest.php`: `test_pedido_confirmado_real_genera_asiento_de_venta`. `RegistroAsientosTest.php`: `test_registra_ventas_por_comercio_y_no_duplica_el_evento`. `DespachoAsientosAceptacionTest.php`: `test_confirmar_y_despachar_genera_asiento_de_venta_y_de_costo_cuadrados`. | Verificado para el flujo implementado; revisar diferencia de evento respecto a PC2/PC3. |
| RF-005 / CU-011 | Generar asiento automático de costo de ventas. | `SubpedidoDespachadoListenerTest.php`: `test_subpedido_despachado_genera_asiento_de_costo`. `RegistroAsientosTest.php`: `test_registra_costo_y_es_idempotente`. `DespachoAsientosAceptacionTest.php`: `test_confirmar_y_despachar_genera_asiento_de_venta_y_de_costo_cuadrados`. | Verificado para el flujo implementado. |
| RF-006 / CU-004 | Generar reporte gerencial. | `ReporteGerencialAceptacionTest.php`: `test_genera_reporte_con_ventas_y_costos_del_periodo`, `test_el_reporte_respeta_el_periodo_solicitado`. `Contratos/M7_ProveedorContratoTest.php`: `test_reporte_gerencial_cumple_el_esquema`. | Verificado para generación, periodo y contrato. |
| RF-007 / CU-005 | Consultar saldos. | `RegistroContablePagoTest.php`: `test_indica_que_un_pago_tiene_registro_contable`, `test_indica_que_un_pago_no_tiene_registro_contable`. | Parcial: verifica consulta de registro contable de pago, no todos los saldos descritos por el caso de uso. |
| RF-008 / CU-006 | Consultar balance general. | No se identificó una prueba específica con ese propósito en el inventario revisado. | Sin evidencia directa identificada. |
| RF-009 / CU-007 | Consultar estado de resultados. | `ReporteGerencialAceptacionTest.php`: `test_genera_reporte_con_ventas_y_costos_del_periodo`, `test_el_reporte_no_incluye_datos_de_otros_comercios`. | Parcial: el reporte verifica ventas y costos; no se identificó una prueba específica del estado de resultados. |
| RF-010 / CU-008 | Consultar rentabilidad por centro de costo. | No se identificó una prueba específica que verifique la rentabilidad por centro de costo. | Sin evidencia directa identificada. |
| RF-011 / CU-009 | Mostrar tablero e indicadores. | `ReporteGerencialAceptacionTest.php`: pruebas de generación y periodo. `Contratos/M7_ProveedorContratoTest.php`: `test_reporte_gerencial_cumple_el_esquema`. | Parcial: hay evidencia del reporte gerencial, no de todos los indicadores del tablero. |

## 4. Trazabilidad de reglas de negocio

| Regla | Comportamiento esperado | Pruebas relacionadas | Estado de evidencia |
|---|---|---|---|
| RN-001 | El evento de despacho descrito en PC2 debe iniciar el flujo contable correspondiente. | `PedidoConfirmadoListenerTest.php`: `test_pedido_confirmado_real_genera_asiento_de_venta`. `SubpedidoDespachadoListenerTest.php`: `test_subpedido_despachado_genera_asiento_de_costo`. | Parcial: los eventos implementados tienen nombres distintos a `PedidoDespachado`. |
| RN-002 | Restringir el procesamiento al evento de despacho definido por la especificación. | `Contratos/M7_ConsumidorContratoTest.php`: `test_subpedido_despachado_contiene_los_campos_que_m7_consume`, `test_pedido_confirmado_contiene_los_campos_que_m7_consume`. | Parcial: se comprueban contratos de eventos implementados; no se identificó una prueba directa de rechazo de cualquier otro tipo de evento. |
| RN-003 | El subpedido despachado genera el asiento de costo. | `SubpedidoDespachadoListenerTest.php`: `test_subpedido_despachado_genera_asiento_de_costo`. `RegistroAsientosTest.php`: `test_registra_costo_y_es_idempotente`. | Verificado para el flujo implementado. |
| RN-004 | M4 proporciona el costo por variación. | `Contratos/M7_ConsumidorM4ContratoTest.php`: `test_la_interfaz_declara_el_metodo_que_el_listener_invoca`, `test_el_doble_de_m4_cumple_la_interfaz`, `test_la_respuesta_trae_los_campos_que_el_listener_lee`. `CostosProductosVendidosGatewayDoubleTest.php`: `test_m7_valida_la_estructura_del_contrato_de_costos_de_m4`. | Parcial: se comprueba el contrato consumidor; revisar correspondencia exacta entre `id_variacion` de PC2 y la respuesta implementada por M4. |
| RN-005 | M2 determina la comisión y M7 la registra contablemente. | `M2ComerciosGatewayTest.php`: `test_m7_obtiene_datos_fiscales_desde_m2`, `test_m7_extrae_monto_de_comision_del_arreglo_de_m2`. `ComerciosM7GatewayDoubleTest.php`: `test_m7_puede_simular_comision_calculada_de_m2`. | Parcial: se comprueba obtención de comisión; no se identifica una prueba que demuestre por sí sola todo el asiento con esa comisión. |
| RN-006 | M6 es responsable de generar el registro de pago correspondiente. | `RegistroContablePagoTest.php`: `test_indica_que_un_pago_tiene_registro_contable`, `test_indica_que_un_pago_no_tiene_registro_contable`. `Contratos/M7_ProveedorContratoTest.php`: `test_registro_contable_de_pago_sin_asiento_devuelve_registrado_false`. | Parcial: se consulta el registro; no se demuestra generación de pagos por M7. |
| RN-007 | M7 no procesa ni concilia pagos. | `RegistroContablePagoTest.php`: pruebas de consulta de registro. `Contratos/M7_ProveedorContratoTest.php`: `test_registro_contable_de_pago_sin_asiento_devuelve_registrado_false`. | Parcial: las pruebas verifican consulta de registro, no toda la ausencia de procesamiento o conciliación. |
| RN-008 | No duplicar asientos de eventos ya procesados. | `PedidoConfirmadoListenerTest.php`: `test_repetir_el_mismo_evento_no_duplica_el_asiento`. `SubpedidoDespachadoListenerTest.php`: `test_repetir_subpedido_despachado_no_duplica_el_asiento`. `RegistroAsientosTest.php`: `test_registra_ventas_por_comercio_y_no_duplica_el_evento`, `test_registra_costo_y_es_idempotente`. `DespachoAsientosAceptacionTest.php`: `test_repetir_los_eventos_no_duplica_los_asientos`. | Verificado para los flujos probados. |
| RN-009 | Mantener trazabilidad de los registros contables. | `RegistroAsientosTest.php`: `test_registra_ventas_por_comercio_y_no_duplica_el_evento`, `test_registra_costo_y_es_idempotente`, `test_no_acepta_el_mismo_identificador_para_eventos_de_distinto_tipo`. | Parcial: se verifica registro, referencias e idempotencia; no se identificó una prueba que compruebe todos los campos y restricciones de trazabilidad descritos en PC3. |
| RN-010 | Aislar la información financiera por comercio. | `LibroDiarioServiceTest.php`: `test_consulta_solamente_los_asientos_del_comercio_activo`. `PlanCuentasApiTest.php`: `test_no_expone_cuentas_de_otro_comercio`. `LibroMayorServiceTest.php`: `test_no_permite_consultar_cuenta_de_otro_comercio`. `ReporteGerencialAceptacionTest.php`: `test_el_reporte_no_incluye_datos_de_otros_comercios`. `DespachoAsientosAceptacionTest.php`: `test_otro_comercio_no_ve_los_asientos_generados`. `RegistroAsientosTest.php`: `test_rechaza_comercio_distinto_al_contexto`. | Verificado en los flujos y consultas cubiertos por estas pruebas. |
| RN-011 | Un pago confirmado genera su registro contable. | `RegistroContablePagoTest.php`: `test_indica_que_un_pago_tiene_registro_contable`, `test_indica_que_un_pago_no_tiene_registro_contable`. | Parcial: verifica la consulta de existencia del registro, no un flujo completo de generación desde el evento de M6. |
| RN-012 | Auditar las acciones sobre datos contables. | No se identificó una prueba específica de bitácora o auditoría en el inventario revisado. | Sin evidencia directa identificada. |
| RN-013 a RN-015 | M6 genera la nota de crédito y M7 registra únicamente el asiento que corresponda. | No se identificó una prueba específica que cubra integralmente estas reglas. | Sin evidencia directa identificada. |

## 5. Observaciones finales

1. Las pruebas de aceptación de despacho cubren la generación de asientos de venta y costo, su balance y la no duplicación.
2. Las pruebas de aislamiento cubren consultas, plan de cuentas, reportes y registro de asientos por comercio.
3. Hay evidencia de adaptadores reales de M2 y M4, además de pruebas de sus contratos.
4. La auditoría RN-012 y las reglas RN-013 a RN-015 requieren evidencia adicional.
5. La diferencia entre los eventos de PC2/PC3 y los eventos registrados actualmente debe revisarse para mantener sincronizados diseño, contratos e implementación.
6. Los estados de esta matriz describen la evidencia encontrada en los archivos de prueba; no sustituyen la ejecución de la suite ni un resultado de CI.