# PC4 · Secciones 6.1 y 6.2 — Pruebas unitarias y de aceptación de M6

Responsable de la estructura: Félix Luna (QA). Redacción conjunta con Anthony (servicios de pago) y Justin (contratos y dobles).

Requisito del enunciado: *mínimo diez pruebas unitarias de lógica de negocio y dos de aceptación.*

**Estado actual:** 34 pruebas unitarias y 34 de integración/aceptación en M6. Todas pasan (`php artisan test --compact --filter=M6_Pagos`).

## Convenciones para todo el subgrupo

1. **Ubicación:** las unitarias van en `tests/Unit/M6_Pagos/` y no usan BD ni HTTP. Las de integración y aceptación van en `tests/Feature/M6_Pagos/` y usan el trait `PreparaM6`.
2. **Nombre:** `test_<qué_pasa>_<en_qué_condición>` en español y snake_case. Ejemplo: `test_rechaza_monto_distinto_al_informado_por_m5`.
3. **Trazabilidad:** cada prueba nueva lleva en su docblock el ID del caso de prueba (`CP-M6-Unn`, `CP-M6-Fnn` o `CA-M6-nn`) y el CU o RN que verifica. Así Juan José la puede llevar a la matriz de la Sección 5.
4. **Estructura:** las unitarias siguen el patrón Preparar / Actuar / Verificar. Las de aceptación siguen Dado / Cuando / Entonces, escrito en el docblock y reflejado en comentarios.
5. **Dependencias:** nunca se llama a otro módulo real. Se usan los dobles de `tests/Doubles/` o un doble anónimo que implemente el puerto (`PedidoClient`, `ContabilidadClient`, …).
6. **Una regla por prueba:** si una prueba falla, su nombre debe decir qué regla se rompió.

## 6.1 Pruebas unitarias de lógica de negocio

### Nuevas en el PC4: `tests/Unit/M6_Pagos/ReglasNegocioM6Test.php`

| ID | Prueba | Verifica | Traza |
|---|---|---|---|
| CP-M6-U01 | `test_la_tarjeta_aprobada_devuelve_la_referencia_del_proveedor` | Cobro aprobado con referencia `PASARELA-SIM-*` | CU-001 paso 5 |
| CP-M6-U02 | `test_sinpe_y_paypal_simulados_se_aprueban` | Los tres medios simulados funcionan | CU-001 paso 1 |
| CP-M6-U03 | `test_el_token_de_rechazo_devuelve_fondos_insuficientes_con_referencia` | El rechazo trae referencia, para pasar por la ruta idempotente | CU-001 FA "proveedor rechaza" |
| CP-M6-U04 | `test_el_token_de_timeout_simula_que_la_pasarela_no_responde` | La pasarela lanza excepción ante timeout | CU-001 FA "no responde" |
| CP-M6-U05 | `test_un_monto_no_positivo_se_rechaza_sin_referencia` | Monto ≤ 0 nunca se cobra | RN-M6-01 |
| CP-M6-U06 | `test_la_referencia_del_proveedor_es_estable_para_el_mismo_cobro` | El mismo cobro siempre da la misma referencia | RN-M6-02 |
| CP-M6-U07 | `test_cada_error_del_catalogo_tiene_un_codigo_estandar_unico` | `ERR-M6-nnn` únicos y bien formados | Contrato / Núcleo 5.5 |
| CP-M6-U08 | `test_el_pago_rechazado_conserva_err_m6_001_y_http_402` | Alineado con `PagosStub` de M5 | Contrato M5–M6 |
| CP-M6-U09 | `test_las_validaciones_del_cobro_usan_el_http_del_contrato` | 400 / 401 / 404 / 409 / 422 según el contrato | RN-M6-01, RN-M6-02 |
| CP-M6-U10 | `test_la_excepcion_usa_el_formato_de_error_del_nucleo` | `{"error": {codigo, mensaje, detalles}}` con `motivo` | Núcleo 5.5 |
| CP-M6-U11 | `test_un_codigo_desconocido_cae_en_err_m6_000_y_422` | Un código fuera del catálogo no rompe la respuesta | Robustez |

### Ya existentes (Justin)

| Archivo | Pruebas | Qué cubren |
|---|---|---|
| `AdaptadoresM6Test.php` | 14 | Cada adaptador (M1, M2, M5, M7) contra el doble del contrato publicado del otro módulo: éxito, no encontrado y proveedor caído (ERR-006) |
| `DoblesM6Test.php` | 7 | Los dobles implementan los puertos de M6 y cubren éxito y errores del contrato |
| `FronterasModuloM6Test.php` | 2 | M6 no importa modelos Eloquent ajenos ni hace JOIN o `DB::table` sobre tablas ajenas |

## 6.2 Pruebas de aceptación

Archivo: `tests/Feature/M6_Pagos/AceptacionM6Test.php`. Recorren la API pública `/api/m6/v1` como lo harían el comprador y la pasarela, y verifican el efecto en M5 y M7 a través de sus dobles.

### CA-M6-01 · El comprador paga un pedido confirmado de extremo a extremo

- **Traza:** CU-001 + CU-002, RN-M6-01, RF-M6-01.
- **Dado** un pedido 8942 confirmado por M5 por ₡59 840,00.
- **Cuando** el comprador lo paga con tarjeta (`tok_sim_aprobada`) y luego consulta `GET /api/m6/v1/pagos/{id}`.
- **Entonces:**
  - responde 201 con estado `confirmado`;
  - la consulta devuelve el mismo estado y la misma referencia del proveedor;
  - se publica una sola vez el evento `PagoConfirmado`;
  - M5 recibe un aviso `confirmado` y M7 un hecho financiero `PagoConfirmado`;
  - la bitácora del Núcleo registra `confirmar_pago`.

### CA-M6-02 · La misma confirmación enviada dos veces no duplica el pedido ni el asiento

- **Traza:** CU-002 FA "notificación duplicada", RN-M6-02. Es la verificación "Idempotencia del pago" que hará el profesor.
- **Dado** un pago pendiente al que la pasarela ya asignó su referencia.
- **Cuando** la pasarela envía dos veces el mismo webhook firmado (`aprobado`).
- **Entonces:**
  - la primera respuesta trae `duplicada: false` y la segunda `duplicada: true`, ambas con estado `confirmado`;
  - hay un solo `PagoConfirmado`, un solo aviso a M5 y un solo evento a M7;
  - en BD queda una sola fila en `pagos` y una sola en `notificaciones_pago`.

### Pruebas de integración de apoyo (Anthony y Justin)

| Archivo | Pruebas | Qué cubren |
|---|---|---|
| `ProcesarPagoTest.php` | 13 | CU-001: cobro, reintento idempotente, conflicto de clave, pedido ya pagado, monto inconsistente, pedido cancelado o inexistente, medio no habilitado, validación, prohibición de PAN/CVV, rechazo, consulta, firma de la fachada |
| `ConfirmacionWebhookTest.php` | 7 | CU-002: confirmación, notificación repetida, rechazo tardío, firma inválida (401), referencia ajena, intento inexistente, estado inválido |
| `FlujosAlternosM6Test.php` | 5 | **Nuevas en el PC4.** Cierran zonas sin cubrir (ver Sección 8): timeout de la pasarela, RN-12 (otro cliente → 404, el dueño sí puede), fachada con rechazo → ERR-M6-001, M7 caído no deshace el cobro |
| `M6ContratoYDoblesTest.php` | 3 | Simulador y doble de M5 resueltos desde el contenedor |
| `PuertosM6BindingsTest.php` | 4 | `M6_USAR_DOBLES`: dobles por defecto, adaptadores reales en modo real |

## Cómo ejecutar

```bash
php artisan test --compact --filter=M6_Pagos
php artisan test --compact tests/Unit/M6_Pagos/ReglasNegocioM6Test.php
php artisan test --compact tests/Feature/M6_Pagos/AceptacionM6Test.php
```
