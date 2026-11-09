# PC4 · Sección 4 — Plan de pruebas del módulo M6 (Pagos y Facturación)

| Dato | Valor |
|---|---|
| Módulo | M6 Pagos y Facturación Electrónica (SG6) |
| Responsable del plan | Félix Luna (C34434), Ingeniero de Pruebas / QA |
| Ejecutan la automatización | Anthony (servicios de pago), Justin (contratos y dobles), con directrices de este plan |
| Versión del plan | 1.0 (PC4, noviembre 2026) |
| Rama base | `desarrollo` |

## 4.1 Objetivo

Comprobar que M6 cumple su **alcance mínimo del PC4**. Ese alcance es un medio de pago simulado de extremo a extremo, con confirmación idempotente y conciliación, más la generación del documento de factura. M6 debe responder según el contrato publicado en el PC3 y no debe tocar datos de otros módulos por fuera de su contrato.

## 4.2 Alcance

**Dentro del alcance de esta fase**

| Elemento | Casos de uso / reglas | Dónde vive el código |
|---|---|---|
| Procesar pago del pedido | CU-001, RF-M6-01, RN-M6-01 | `app/Services/M6_Pagos/ProcesamientoPagosService.php` |
| Confirmar pago recibido (webhook idempotente) | CU-002, RF-M6-02, RN-M6-02 | `ConfirmacionPagoService.php`, `VerificarFirmaPasarela` |
| Publicación del resultado hacia M5, M7 y Núcleo | CU-002 pasos 4–6 | `PublicadorResultadoPago.php` |
| Fachada que consume M5 (`PagosServiceInterface`) | Contrato M5–M6 | `PagosService.php` |
| Puertos y adaptadores hacia M1, M2, M5, M7 | Contratos consumidos | `app/Services/M6_Pagos/Adaptadores/` |
| Aislamiento por cliente/comercio | RN-12, RN-M6-08 | `ProcesamientoPagosService::exigirMismoCliente` |
| Frontera de datos | Reglas 1 y 2 del proyecto | Todo `app/*/M6_Pagos` |
| Formulario de pago (React) | CU-001 paso 1 | `frontend/src/modules/M6_Pagos/` (Ian y Juan José) |

**Fuera del alcance de esta fase** (sin código todavía; se registran como riesgo en 4.10)

- CU-003 Conciliar pagos (RN-M6-07), que forma parte del alcance mínimo del PC4.
- CU-004 Generar factura electrónica (RN-M6-03). Hoy `PagosService::generarFactura()` devuelve una respuesta provisional fija.
- CU-005 a CU-008: notas de crédito/débito, reembolsos e historial (RN-M6-04 a RN-M6-06, RN-M6-08 en historial).

## 4.3 Niveles y tipos de prueba

| Nivel | Qué verifica | Herramienta | Ubicación |
|---|---|---|---|
| Unitaria | Lógica de negocio sin BD ni HTTP: simulador de pasarela, catálogo de errores, adaptadores contra el doble del contrato ajeno | PHPUnit | `tests/Unit/M6_Pagos/` |
| Integración (feature) | Servicio + BD en memoria + API: CU-001, CU-002, flujos alternos, bindings de puertos | PHPUnit + `RefreshDatabase` (trait `PreparaM6`) | `tests/Feature/M6_Pagos/` |
| Contrato como proveedor | Las respuestas de `/api/m6/v1` y de `PagosServiceInterface` cumplen lo publicado (códigos HTTP, `ERR-M6-nnn`, forma del JSON) | PHPUnit | `ProcesarPagoTest`, `ConfirmacionWebhookTest`, `ReglasNegocioM6Test` |
| Contrato como consumidor | Cada adaptador se prueba contra el doble del contrato publicado del otro módulo | PHPUnit + `tests/Doubles/M1, M2, M5, M7` | `AdaptadoresM6Test`, `DoblesM6Test` |
| Aceptación | Criterios de aceptación de punta a punta por la API pública (Dado / Cuando / Entonces) | PHPUnit | `AceptacionM6Test` |
| Arquitectura | M6 no importa modelos ajenos ni consulta tablas ajenas | PHPUnit (análisis estático del código) | `FronterasModuloM6Test` |
| Interfaz | Validación del formulario de pago y matriz de tokens | Vitest | `frontend/src/modules/M6_Pagos/__tests__/` |
| Sistema (manual) | Recorrido transversal en el ambiente de integración (paso 5: pago) | Guion manual + evidencias | Lo coordina Justin (Sección 1.5) |

## 4.4 Ambiente de prueba

- **Automatizadas:** SQLite en memoria (`phpunit.xml`). El trait `PreparaM6` migra solo las tablas de M6 y la bitácora del Núcleo. En SQLite se omiten las sentencias exclusivas de PostgreSQL (`BaseSinSentenciasPostgres`). **Nunca** se prueba contra la base compartida de Supabase.
- **Dependencias externas:** `M6_USAR_DOBLES=true` (valor por defecto) inyecta los dobles de `tests/Doubles`. Con `false` se usan los adaptadores reales. La pasarela siempre es `PasarelaPagoSimulador`.
- **Datos de prueba fijos** (dobles de M5): pedido 8942 = confirmado, cliente 77, ₡59 840,00. Pedido 8943 = cancelado. Cualquier otro pedido no existe.
- **Tokens del simulador:** `tok_sim_aprobada`, `tok_sim_sinpe` y `tok_sim_paypal` aprueban. `tok_sim_rechazada` y `tok_rechazado` rechazan. `tok_sim_timeout` simula que la pasarela no responde.
- **Webhook:** se firma con `hash_hmac('sha256', cuerpo, M6_WEBHOOK_SECRETO)` en la cabecera `X-Firma-Pasarela`.

## 4.5 Criterios de entrada

Una ronda de pruebas del módulo empieza solo si se cumple **todo** lo siguiente:

1. El código está integrado en una rama que parte de `desarrollo` actualizado, y `composer install` termina sin errores.
2. `vendor/bin/pint --test` no reporta archivos de M6 sin formato.
3. Los contratos de M6 (`contracts/m6-pagos/v1/`) y los dobles de M1, M2, M5 y M7 en `tests/Doubles/` están publicados en la rama.
4. Las migraciones de `database/migrations/M6_Pagos/` corren sobre SQLite mediante `PreparaM6`.
5. Cada caso de uso dentro del alcance (4.2) tiene al menos un caso de prueba diseñado y trazado a su regla de negocio.
6. Para la prueba manual del recorrido, el ambiente de integración está arriba y M5 expone pedidos en estado `confirmado`.

## 4.6 Criterios de salida

Las pruebas del módulo se dan por terminadas y el módulo se considera listo para el PC4 cuando:

1. El 100 % de las pruebas de `tests/Unit/M6_Pagos` y `tests/Feature/M6_Pagos` pasa (`php artisan test --compact --filter=M6_Pagos`).
2. Existen como mínimo **10 pruebas unitarias de lógica de negocio** y **2 de aceptación** (requisito del enunciado). Hoy hay 34 unitarias y 2 de aceptación; ver Sección 6.
3. Toda regla de negocio **implementada** (RN-M6-01, RN-M6-02, RN-12/RN-M6-08) tiene al menos un caso de prueba que la verifica.
4. La verificación "Idempotencia del pago" del PC4 pasa: dos confirmaciones iguales no duplican ni el pedido pagado ni el asiento contable (CA-M6-02).
5. **No quedan defectos de severidad crítica abiertos.** Los de severidad alta quedan resueltos o tienen plan aprobado por el subgrupo.
6. Hay un reporte de cobertura de M6, con análisis de las zonas no cubiertas (Sección 8).
7. Las pruebas de Vitest del formulario de pago pasan (`cd frontend && npm test`).

## 4.7 Criterios de suspensión

La ejecución se **suspende** (no se siguen registrando resultados) si ocurre cualquiera de estos hechos:

1. Falla la preparación de la BD de prueba: una migración de M6 no corre en SQLite y ninguna prueba de feature puede arrancar.
2. Un cambio en un contrato ajeno (M1, M2, M5, M7) rompe más del 30 % de las pruebas de M6. Se suspende hasta aclarar la versión del contrato con el Comité.
3. Se detecta un defecto **crítico** que bloquea CU-001 (no se puede registrar ningún pago). Seguir probando los flujos que dependen de él no aporta información.
4. Para pruebas manuales, el ambiente de integración o la BD compartida no están disponibles, o alguien ejecutó un comando destructivo (`migrate:fresh`, `db:wipe`).
5. Alguna prueba apunta por error a la BD compartida de Supabase en lugar de SQLite en memoria.

## 4.8 Criterios de reanudación

Se reanuda cuando se cumple la condición que corresponde a la causa de la suspensión:

| Causa (4.7) | Condición para reanudar |
|---|---|
| 1. Migración rota | La migración se corrige y `php artisan test --filter=M6_Pagos` vuelve a arrancar |
| 2. Contrato ajeno cambiado | Hay una nueva versión del contrato aprobada (SC o ADR) y los dobles de `tests/Doubles/` están actualizados |
| 3. Defecto crítico en CU-001 | El defecto está corregido en la rama y su prueba de regresión pasa |
| 4. Ambiente caído | El Comité confirma que el ambiente está arriba. Se repite el recorrido desde el paso 1 |
| 5. Prueba contra BD compartida | Se corrige la configuración (`phpunit.xml` / `.env.testing`) y se avisa al Comité por si hubo datos afectados |

Al reanudar se vuelve a correr **toda** la suite de M6, no solo la prueba que falló.

## 4.9 Clasificación de severidad de defectos

| Severidad | Criterio | Ejemplo en M6 |
|---|---|---|
| Crítica | Pérdida de dinero o datos, cobro duplicado, ruptura del recorrido transversal o fuga entre clientes/comercios | Dos cobros para el mismo pedido; un cliente ve el pago de otro |
| Alta | Un caso de uso del alcance mínimo no funciona y no hay forma de evitarlo | El webhook válido no confirma el pago |
| Media | Un flujo alterno falla o deja datos en un estado del que el usuario no puede salir solo | Pedido bloqueado tras un timeout (DEF-M6-01) |
| Baja | Dato incorrecto o cosmético sin efecto en el flujo de negocio | Mensaje de aprobación enviado como "motivo" (DEF-M6-02) |

Cada defecto se registra con: ID `DEF-M6-nn`, severidad, pasos para reproducir, resultado esperado y obtenido, causa raíz, responsable y estado (Abierto, En corrección, Resuelto, Verificado). Ver Sección 9.1.

## 4.10 Riesgos del plan

| Riesgo | Efecto | Mitigación |
|---|---|---|
| CU-003 (conciliación) y CU-004 (factura) aún no implementados | Falta parte del alcance mínimo del PC4. RN-M6-03 y RN-M6-07 no tendrán prueba | Priorizarlos en backend. Agregar sus casos de prueba al implementarlos |
| No hay driver de cobertura en todas las máquinas | No se puede medir el % en cada equipo | Medir en Laragon (trae Xdebug) o instalar PCOV (Sección 8) |
| SQLite no reproduce bloqueos de PostgreSQL (`lockForUpdate`) | La concurrencia real no se ejecuta en la suite | Prueba manual de doble envío en el ambiente de integración |
| M7 aún responde con el adaptador provisional (ERR-006) | El aviso contable real no se prueba de punta a punta | Prueba de contrato con el doble + recorrido transversal cuando M7 publique |

## 4.11 Herramientas y comandos

| Herramienta | Uso |
|---|---|
| PHPUnit 12 (`php artisan test`) | Unitarias, integración, contrato y aceptación |
| SQLite en memoria | BD aislada de cada prueba |
| Dobles de `tests/Doubles/` | Sustituyen a M1, M2, M5 y M7 |
| Xdebug o PCOV | Reporte de cobertura |
| Laravel Pint | Estándar de código antes de probar |
| Vitest | Pruebas del formulario React |
| GitHub (PR a `desarrollo`) | Revisión de código y registro de defectos |

```bash
php artisan test --compact --filter=M6_Pagos                 # suite completa de M6
php artisan test --compact tests/Unit/M6_Pagos               # solo unitarias
php artisan test --compact tests/Feature/M6_Pagos/AceptacionM6Test.php   # solo aceptación
XDEBUG_MODE=coverage php artisan test --filter=M6_Pagos --coverage       # cobertura (Sección 8)
vendor/bin/pint --dirty --format agent                       # formato
cd frontend && npm install && npm test                       # Vitest
```
