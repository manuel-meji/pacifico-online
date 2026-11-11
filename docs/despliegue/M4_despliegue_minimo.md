# M4 Inventario: despliegue mínimo

Despliegue mínimo de M4 en el ambiente de integración, con cambio de doble a implementación real por configuración (contrato `openapi` v1.3.0).

## Cómo se usa M4

M4 no publica la API `/api/m4/v1` del contrato. El frontend usa Inertia: el controlador de cada pantalla llama a `InventarioModuleInterface` en el servidor y le pasa los datos a la página. Los demás módulos también llaman a M4 por esa interfaz, dentro del mismo proceso (sección 5.1 del PC3). El contrato OpenAPI documenta las operaciones; cada `operationId` corresponde a un método de la interfaz.

## Qué incluye

- `InventarioService` real sobre las tablas de las migraciones `2026_10_21_00000x_m4_*`:
  - Consultas de disponibilidad: individual, en lote, por producto y por producto en lote. Disponible = `cantidad_fisica − cantidad_reservada` (no se maneja stock defectuoso; migración `2026_11_02_000008`).
  - Reserva atómica con `SELECT … FOR UPDATE` (ADR-002), con clave de idempotencia y vencimiento de las reservas activas al reservar de nuevo.
  - Liberación de reservas, idempotente.
- `InventarioService` tiene las 20 operaciones del contrato (x-interfaz-php v1.3.0); las que no son del despliegue mínimo lanzan `LogicException` hasta que se implementen en el PC4. `InventarioModuleInterface` todavía declara 6: pasa a 20 cuando los dobles de M1 y M5 extiendan `InventarioDobleBase` (`app/Services/M4_Inventario/Dobles`), que ya declara las 20 firmas. `InventarioModuleInterface` extiende `InventarioServiceInterface` (interfaz anterior), que se conserva para que M1 y M5 compilen; `AppServiceProvider` la enlaza con `InventarioService`. `confirmarSalidaPorDespacho` lanza `LogicException` y el recorrido integrado de M1 y M5 con M4 no se ha probado.
- Interfaces de lo que M4 necesita de otros módulos, en `app/Contracts/M4_Inventario/Dependencias`: `ComerciosDependencia` (M2), `CatalogoDependencia` (M3) y `NucleoDependencia`.
- Eventos `ExistenciaReservada`, `ExistenciaAgotada` y `ExistenciaRepuesta` (v1.0.0), en `app/Events/M4_Inventario`, publicados después del commit.
- Errores con el formato del núcleo (`ErrorInventarioException`, códigos `ERR-M4-nnn`). Quien llama a M4 la captura; `toArray()` da el cuerpo `{error: {codigo, mensaje, detalles}}`.

## Cambio de doble a real

Cada dependencia se elige por separado en `.env`. El valor por defecto es `doble`:

| Variable | Dependencia | En modo `real` llama a |
|---|---|---|
| `M4_M2_MODO` | M2: estado y sedes del comercio | `ComerciosServiceInterface` (`obtenerComercioActivo`, `obtenerSedesComercio`) |
| `M4_M3_MODO` | M3: variación y su comercio | `CatalogoServiceInterface::obtenerVariacion` |
| `M4_NUCLEO_MODO` | Núcleo: comercio de la sesión y bitácora | `NucleoServiceInterface` |
| `M4_RESERVA_MINUTOS` | Vigencia de la reserva si no se indica otra (15) | — |

Si el proveedor falla o todavía no publica la operación, M4 lanza ERR-006 (503) y dice qué módulo y qué operación faltan.

Para usar otro doble (por ejemplo, los dobles de prueba de M2, M3 y núcleo), cambia la clase `doble` de esa dependencia en `config/integraciones.php`, clave `m4`.

## Verificación

```bash
php artisan test --filter=DespliegueMinimoM4Test
php artisan test --filter=ConcurrenciaReservaStockTest   # requiere DB_CONNECTION=pgsql en el .env
```

## Fuera de este despliegue

- Confirmación de reserva, salida por despacho, movimientos, kardex y ajustes.
- Pantallas de M4 con Inertia (prototipo de Maikol en el PC3; pantallas reales en el PC4).
- Eventos `ReservaVencida`, `AlertaStockMinimo` y `SalidaInventarioConfirmada`.
- Tarea programada que vence reservas cada 60 s. Hoy las reservas vencidas se cierran cuando alguien vuelve a reservar esa variación.
