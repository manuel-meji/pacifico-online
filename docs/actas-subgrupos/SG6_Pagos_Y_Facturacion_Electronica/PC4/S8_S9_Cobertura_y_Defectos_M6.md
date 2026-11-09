# PC4 · Sección 8 (apoyo: reporte de cobertura) y Sección 9.1 (defectos) — M6

Responsable: Félix Luna (QA).

## 8. Reporte de cobertura de M6

### 8.1 Cómo se genera

La cobertura necesita un driver: **Xdebug** (viene con Laragon) o **PCOV**. Sin uno de ellos, `--coverage` no muestra porcentajes.

```bash
# Laragon / Xdebug
XDEBUG_MODE=coverage php artisan test --filter=M6_Pagos --coverage

# Reporte HTML navegable (para las capturas del documento)
XDEBUG_MODE=coverage vendor/bin/phpunit --filter=M6_Pagos --coverage-html storage/cobertura-m6
```

`phpunit.xml` mide todo `app/`. Del reporte se toman solo las filas `M6_Pagos` (Services, Http, Models, Enums, Exceptions, Events) y se copian en la tabla 8.2.

### 8.2 Resultado medido

> **Pendiente de medir** en una máquina con Xdebug o PCOV. La máquina donde se preparó este documento no tiene driver de cobertura. Completar con la salida real; no estimar.

| Clase | % líneas | Observación |
|---|---|---|
| `Services/M6_Pagos/ProcesamientoPagosService` | | |
| `Services/M6_Pagos/ConfirmacionPagoService` | | |
| `Services/M6_Pagos/PublicadorResultadoPago` | | |
| `Services/M6_Pagos/PagosService` | | |
| `Services/M6_Pagos/Simuladores/PasarelaPagoSimulador` | | |
| `Services/M6_Pagos/Adaptadores/*` | | |
| `Http/Controllers/M6_Pagos/*` | | |
| `Http/Middleware/M6_Pagos/VerificarFirmaPasarela` | | |
| `Enums/M6_Pagos/CodigoErrorM6` | | |
| `Exceptions/M6_Pagos/ErrorContratoException` | | |
| **Total M6** | | |

### 8.3 Cobertura por rutas de código (análisis manual)

Este análisis se hizo leyendo cada rama del código y buscando qué prueba la ejecuta. Sirve para explicar el porcentaje de 8.2.

| Rama del código | ¿Cubierta? | Prueba |
|---|---|---|
| CU-001 cobro aprobado | Sí | `ProcesarPagoTest`, CA-M6-01 |
| CU-001 reintento con la misma `Idempotency-Key` | Sí | `test_reintento_con_la_misma_idempotency_key_no_cobra_dos_veces` |
| CU-001 misma clave con otro monto (409) | Sí | `test_misma_clave_con_otro_monto_es_conflicto` |
| CU-001 pedido con pago activo (409) | Sí | `test_un_pedido_ya_pagado_no_admite_un_segundo_cobro_con_otra_clave` |
| RN-M6-01 pedido no confirmado, monto distinto, pedido inexistente | Sí | `ProcesarPagoTest` |
| CU-001 FA proveedor rechaza | Sí | `test_la_pasarela_rechaza_y_publica_pago_rechazado`, CP-M6-F04 |
| CU-001 FA proveedor no responde (timeout) | Sí (nuevo) | CP-M6-F01 |
| RN-12 / RN-M6-08 otro cliente → 404 | Sí (nuevo) | CP-M6-F02, CP-M6-F03 |
| Fallo al avisar a M7 no deshace el cobro | Sí (nuevo) | CP-M6-F05 |
| CU-002 confirmación, duplicado, rechazo tardío, firma inválida, referencia ajena, inexistente | Sí | `ConfirmacionWebhookTest`, CA-M6-02 |
| Adaptadores M1, M2, M5, M7 (éxito, error, proveedor caído) | Sí | `AdaptadoresM6Test` |

### 8.4 Zonas no cubiertas y justificación

| Zona | Archivo | Por qué no se cubre | Acción |
|---|---|---|---|
| `catch (LockTimeoutException)`: otro proceso tiene el candado del pedido | `ProcesamientoPagosService::procesar` | Necesita dos procesos simultáneos. La suite corre en un solo proceso con caché `array` | Prueba manual de doble clic en el ambiente de integración (PostgreSQL) |
| `catch (UniqueConstraintViolationException)` en `registrarIntento` y en `confirmar` | `ProcesamientoPagosService`, `ConfirmacionPagoService` | Solo ocurre si dos peticiones insertan a la vez. SQLite en memoria no reproduce esa carrera | Igual que la anterior; la defensa real es el índice UNIQUE en BD |
| Rama `DUPLICADA` (pago ya resuelto con la misma referencia, sin notificación previa) | `ConfirmacionPagoService::aplicar` | No se alcanza por la API: un pago resuelto siempre tiene ya su notificación (capa 2) o su referencia (capa de autenticidad). Es código defensivo | Dejarla documentada; revisar si se puede simplificar |
| Respuesta exitosa sin `referencia_proveedor` | `ProcesamientoPagosService::cobrar` | El simulador siempre envía referencia, salvo con monto ≤ 0, y el `FormRequest` ya lo impide (`gt:0`) | Se cubrirá cuando exista una pasarela real |
| `generarFactura()` | `PagosService` | Es una respuesta provisional fija; CU-004 aún no está implementado | Escribir pruebas al implementar CU-004 (RN-M6-03) |
| Middleware con secreto vacío | `VerificarFirmaPasarela` | Solo pasa si `M6_WEBHOOK_SECRETO` queda vacío en `.env` | Prueba sencilla pendiente (prioridad baja) |
| Catálogo de errores de fases futuras (`ERR-M6-014` a `ERR-M6-020`) | `CodigoErrorM6` | Sus casos de uso (reembolsos, notas, conciliación, historial) no existen aún. Solo se verifica que el código sea único (CP-M6-U07) | Cubrir con cada caso de uso |
| Componentes React | `frontend/src/modules/M6_Pagos` | Los mide Vitest, no PHPUnit | Ian / Juan José: `npm test -- --coverage` |

## 9.1 Defectos encontrados en M6

Los dos defectos salieron al diseñar los casos de prueba de los flujos alternos de CU-001. Se reprodujeron con pruebas automatizadas sobre la rama `desarrollo` (commit `90d1c49`).

### DEF-M6-01 · Tras un timeout de la pasarela, el pedido queda bloqueado

| Campo | Valor |
|---|---|
| Severidad | **Media**: el comprador no puede terminar de pagar, pero no se cobra de más ni se pierden datos |
| Caso de uso / regla | CU-001 FA "El proveedor de pago no responde"; RN-M6-02 |
| Detectado por | Félix Luna (QA), caso CP-M6-F01 |
| Estado | Abierto |
| Responsable sugerido | Implementador backend (Anthony) |

**Pasos para reproducir**

1. `POST /api/m6/v1/pagos` con `Idempotency-Key: k1`, pedido 8942, monto 59840 y `token_medio_pago: tok_sim_timeout`. Responde **201** con `estado: pendiente` y `referencia_proveedor: null`.
2. Reintento con la **misma** clave `k1`. Responde **200** con el mismo pago pendiente, y **no se vuelve a llamar a la pasarela**.
3. Reintento con **otra** clave `k2` y un token válido. Responde **409 `ERR-M6-006` PEDIDO_CON_PAGO_ACTIVO**.

**Resultado esperado** (CU-001, flujo alterno 2): "El sistema marca el intento como pendiente de confirmación y **programa un reintento** a partir del paso 4". El comprador debería poder completar el pago.

**Resultado obtenido:** el pago queda `pendiente` para siempre. Ningún proceso vuelve a cobrar, y el simulador nunca envía webhook para ese cobro. El pedido no admite otro intento de pago.

**Causa raíz**

- `ProcesamientoPagosService::cobrar()` (línea ~159) atrapa la excepción, deja un log y devuelve el pago pendiente. No agenda ningún reintento.
- `ProcesamientoPagosService::procesar()` devuelve el pago existente cuando la clave se repite, sin revisar si el cobro llegó a la pasarela.
- `registrarIntento()` (línea ~128) cuenta los pagos `pendiente` como "activos", aunque no tengan `referencia_proveedor`.

**Corrección propuesta** (elegir una y agregar su prueba de regresión):

- (a) Si la clave se repite y el pago está `pendiente` **sin** `referencia_proveedor`, volver a llamar a la pasarela con el mismo pago. Es lo más cercano al CU-001.
- (b) Agendar un *job* de reintento con un número máximo de intentos. Al agotarlos, marcar el pago como `rechazado` para liberar el pedido.

### DEF-M6-02 · El mensaje de aprobación viaja como "motivo" del pago confirmado

| Campo | Valor |
|---|---|
| Severidad | **Baja**: no cambia el estado del pedido ni del pago |
| Caso de uso / regla | CU-002 pasos 4–6 (aviso a M5 y bitácora) |
| Detectado por | Félix Luna (QA), al escribir CA-M6-01 |
| Estado | Abierto |
| Responsable sugerido | Implementador backend (Anthony) |

**Pasos para reproducir:** pagar el pedido 8942 con `tok_sim_aprobada` y revisar lo que recibe el puerto `PedidoClient::notificarResultadoPago()` o la fila de `bitacora_auditoria`.

**Resultado esperado:** en un pago `confirmado`, `motivo = null`. El motivo solo explica un rechazo.

**Resultado obtenido:** `motivo = "Transacción aprobada por el simulador de pasarela"`, tanto en el aviso a M5 como en `detalles.motivo` de la bitácora (`confirmar_pago`).

**Causa raíz:** `ProcesamientoPagosService::cobrar()` (línea ~179) pasa `$respuesta['mensaje']` como motivo sin mirar si el cobro fue exitoso.

**Corrección propuesta:** pasar el mensaje solo cuando el resultado es rechazo:

```php
$motivo = ($respuesta['exitoso'] ?? false) ? null : ($respuesta['mensaje'] ?? null);
```

Al corregirlo, volver a la aserción completa en `AceptacionM6Test::test_ca_m6_01_…`, que verificaba `motivo => null`.
