PC4 - Revisión de mocks, stubs, fakes y dobles de prueba de M7
1. Objetivo

En esta revisión se identifican los dobles de prueba que utiliza M7 y se analiza para qué sirve cada uno, qué escenarios permite probar y cómo se relaciona con las integraciones reales entre módulos.

La idea es distinguir cuáles pruebas utilizan simulaciones y cuáles comprueban la comunicación con los servicios reales de otros módulos.

2. Dobles de prueba encontrados
2.1. ComerciosM7Stub

Archivo: tests/Doubles/M7/ComerciosM7Stub.php

Este stub simula la respuesta del módulo M2 (Comercios). Permite trabajar con datos fiscales de los comercios y comisiones asociadas a los subpedidos sin depender directamente del servicio real.

También permite simular situaciones en las que los datos o las comisiones no están disponibles, o cuando un comercio no está configurado.

Pruebas relacionadas:

Archivo: tests/Feature/M7_Contabilidad/ComerciosM7GatewayDoubleTest.php

test_m7_puede_simular_datos_fiscales_de_m2
test_m7_puede_simular_comision_calculada_de_m2
test_m7_puede_simular_datos_fiscales_no_disponibles
test_m7_puede_simular_comision_no_disponible
test_m7_detecta_comercio_no_configurado
test_m7_detecta_subpedido_sin_comision_configurada

Integración real relacionada:

app/Services/M7_Contabilidad/Gateways/M2ComerciosGateway.php

Este gateway implementa ComerciosM7GatewayInterface y utiliza ComerciosServiceInterface para comunicarse con M2.

También encontramos pruebas en M2ComerciosGatewayTest.php que comprueban que M7 obtiene los datos fiscales y la comisión mediante el gateway real.

Conclusión:

El stub sigue siendo útil porque permite probar diferentes respuestas de M2 sin depender de ese módulo. El gateway real se encarga de la comunicación cuando se utiliza la integración normal.

2.2. CostosProductosVendidosStub

Archivo: tests/Doubles/M7/CostosProductosVendidosStub.php

Este stub simula las respuestas de M4 relacionadas con los costos de los productos vendidos.

Permite probar situaciones en las que M4 devuelve los costos correctamente, no tiene información disponible o todavía no se ha configurado el doble.

También permite comprobar que M7 no utilice costos correspondientes a otro subpedido.

Pruebas relacionadas:

Archivo: tests/Feature/M7_Contabilidad/CostosProductosVendidosGatewayDoubleTest.php

test_m7_puede_simular_costos_disponibles_de_m4
test_m7_puede_simular_costos_temporalmente_no_disponibles
test_m7_detecta_si_el_doble_no_fue_configurado
test_m7_no_devuelve_costos_de_otro_subpedido
test_m7_valida_la_estructura_del_contrato_de_costos_de_m4

Integración real relacionada:

app/Services/M7_Contabilidad/Gateways/M4CostosProductosVendidosGateway.php

Este gateway implementa CostosProductosVendidosGatewayInterface y utiliza InventarioModuleInterface para obtener los costos desde M4.

Además, encontramos pruebas de contrato en M7_ConsumidorM4ContratoTest.php, que comprueban que la interfaz y las respuestas tengan la estructura que M7 necesita.

Conclusión:

El stub permite probar distintos escenarios de respuesta de M4, mientras que el gateway real se encarga de obtener los costos mediante la integración con el módulo de inventario.

2.3. FakeContabilidadClient

Archivo: tests/Doubles/M7/FakeContabilidadClient.php

Este fake simula el servicio de contabilidad de M7 desde el punto de vista de M6 (Pagos).

Permite guardar los eventos enviados, simular la disponibilidad o indisponibilidad de M7 y consultar si un pago tiene un registro contable.

Aunque está ubicado dentro de tests/Doubles/M7, se utiliza principalmente en pruebas relacionadas con M6. Por eso, es importante distinguirlo de los dobles que utiliza M7 para probar sus propias dependencias.

Pruebas relacionadas:

tests/Feature/M6_Pagos/ConciliacionTest.php
test_m7_caido_deja_la_conciliacion_parcial
tests/Feature/M6_Pagos/PuertosM6BindingsTest.php
test_por_defecto_los_puertos_resuelven_a_los_dobles
tests/Unit/M6_Pagos/DoblesM6Test.php
test_los_dobles_implementan_los_puertos_de_m6
test_m7_no_disponible_simula_conciliacion_parcial

Integración real relacionada:

M6 cuenta con el adaptador AdaptadorM7, que permite comunicarse con el servicio de contabilidad de M7.

En las pruebas de PuertosM6BindingsTest.php también se revisa la resolución del adaptador, pero en el escenario real se utiliza una implementación anónima de ContabilidadServiceInterface.

Por eso, esta prueba permite comprobar el enlace con el adaptador, pero no demuestra por sí sola una comunicación completa entre M6 y una instancia real de M7.

Conclusión:

El fake sigue siendo útil para probar cómo responde M6 cuando M7 está disponible o presenta problemas. No sustituye las pruebas de integración entre ambos módulos.

2.4. Stub de ContabilidadServiceInterface

Archivo relacionado: tests/Feature/M7_Contabilidad/ContabilidadServiceDoubleTest.php

En esta prueba se utiliza un stub de PHPUnit para sustituir temporalmente la implementación de ContabilidadServiceInterface.

La prueba relacionada es:

test_m7_puede_sustituir_el_servicio_por_un_stub

El stub simula el retorno de registrarAsientoDespacho() y permite comprobar que el servicio puede sustituirse mediante el contenedor de dependencias.

Conclusión:

Este stub sirve para comprobar la inyección y sustitución de dependencias. Sin embargo, no demuestra que se estén generando asientos contables reales, porque el resultado se encuentra simulado.

3. Resumen de los dobles encontrados
Doble de prueba	Módulo que simula	¿Para qué se utiliza?	¿Debe conservarse?
ComerciosM7Stub	M2 - Comercios	Simular datos fiscales, comisiones y respuestas no disponibles.	Sí. Permite probar diferentes escenarios sin depender de M2.
CostosProductosVendidosStub	M4 - Inventario	Simular costos de productos vendidos y respuestas de M4.	Sí. Facilita las pruebas de costos y errores de comunicación.
FakeContabilidadClient	M7 desde M6	Simular la comunicación de M6 con M7 y sus posibles fallos.	Sí. Permite probar el comportamiento de M6 sin depender de M7.
Stub de ContabilidadServiceInterface	Servicio interno de M7	Sustituir el servicio para comprobar la inyección de dependencias.	Sí. Permite probar el servicio de forma aislada.
4. Integraciones reales identificadas

Durante la revisión también encontramos implementaciones que permiten la comunicación con otros módulos.

Módulo	Implementación encontrada	Interfaz utilizada
M2 - Comercios	M2ComerciosGateway.php	ComerciosM7GatewayInterface
M4 - Inventario	M4CostosProductosVendidosGateway.php	CostosProductosVendidosGatewayInterface
M6 - Pagos	AdaptadorM7	ContabilidadServiceInterface

Estas implementaciones permiten que M7 utilice los servicios de otros módulos sin depender directamente de sus clases internas.

Es importante aclarar que encontrar un gateway o adaptador real no significa que todas las pruebas estén realizando una integración completa. Algunas pruebas verifican los contratos, otras comprueban los bindings y otras utilizan dobles para simular respuestas.

5. Conclusiones

Después de revisar los dobles y las integraciones, encontramos lo siguiente:

M7 cuenta con dobles para simular las respuestas de M2 y M4. Estos permiten probar escenarios normales y situaciones en las que los servicios externos no están disponibles.
También existe un fake de contabilidad utilizado por M6 para probar su comunicación con M7 sin depender directamente del servicio real.
Se encontró un stub interno para ContabilidadServiceInterface, que permite comprobar que el servicio puede sustituirse mediante el contenedor de dependencias.
M7 tiene gateways reales para comunicarse con M2 y M4, y M6 cuenta con un adaptador para comunicarse con M7.
Los dobles no deberían eliminarse solamente porque ya existan integraciones reales. Ambos tienen propósitos diferentes: los dobles permiten probar escenarios controlados y los gateways o adaptadores permiten realizar la comunicación entre módulos.
La prueba del adaptador de M6 no debe confundirse con una prueba de extremo a extremo, ya que utiliza una implementación de la interfaz para verificar el binding y no una comunicación completa con M7.

6. Resultado de la revisión

En general, encontramos que el proyecto mantiene dobles de prueba para las dependencias externas y también cuenta con implementaciones para las integraciones reales identificadas.

Por lo tanto, los dobles siguen teniendo utilidad y no es necesario eliminarlos. Lo importante es mantener clara la diferencia entre las pruebas aisladas, las pruebas de contratos y las pruebas de integración para saber exactamente qué comportamiento está comprobando cada una.