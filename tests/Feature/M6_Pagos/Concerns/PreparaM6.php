<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos\Concerns;

use App\Models\M6_Pagos\EstadoFacturaHacienda;
use App\Models\M6_Pagos\EstadoNotificacionPago;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\MedioPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * Base de datos en memoria SOLO con las tablas de M6 (y la bitácora del Núcleo),
 * mismo patrón que PreparaM2: no depende de las migraciones de otros módulos.
 *
 * Datos de los dobles (tests/Doubles/M5/FakePedidoClient):
 *   pedido 8942 = confirmado, cliente 77, monto 59 840.00
 *   pedido 8943 = cancelado; cualquier otro = no existe.
 */
trait PreparaM6
{
    use RefreshDatabase {
        refreshDatabase as protected refrescarBaseDeLaravel;
    }

    protected const PEDIDO_CONFIRMADO = 8942;

    protected const PEDIDO_CANCELADO = 8943;

    protected const MONTO_PEDIDO = 59840.00;

    private const MIGRACIONES = [
        'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
        'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
        'database/migrations/M6_Pagos',
    ];

    public function refreshDatabase(): void
    {
        $this->olvidarBaseCompartida();
        $this->beforeApplicationDestroyed(fn () => $this->olvidarBaseCompartida());

        EstadoPago::olvidarCache();
        EstadoNotificacionPago::olvidarCache();
        MedioPago::olvidarCache();
        EstadoFacturaHacienda::olvidarCache();

        // Las migraciones de M6 se ejecutan sin modificarlas; en SQLite se omiten
        // solo sus sentencias exclusivas de PostgreSQL (ver BaseSinSentenciasPostgres).
        BaseSinSentenciasPostgres::durante(fn () => $this->refrescarBaseDeLaravel());
    }

    private function olvidarBaseCompartida(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
    }

    protected function migrateUsing(): array
    {
        return ['--path' => self::MIGRACIONES, '--seed' => false];
    }

    protected function migrateFreshUsing(): array
    {
        return ['--path' => self::MIGRACIONES, '--drop-views' => false, '--drop-types' => false, '--seed' => false];
    }

    /** @param array<string, mixed> $cuerpo */
    protected function procesarPago(array $cuerpo = [], string $clave = 'clave-prueba-1')
    {
        return $this->postJson('/api/m6/v1/pagos', $cuerpo + [
            'id_pedido' => self::PEDIDO_CONFIRMADO,
            'medio_pago' => 'tarjeta',
            'monto_total' => self::MONTO_PEDIDO,
            'token_medio_pago' => 'tok_visa_ok',
        ], ['Idempotency-Key' => $clave]);
    }

    /** Envía el webhook firmado exactamente como lo haría la pasarela. */
    protected function webhook(int $idPago, array $cuerpo, ?string $firma = null)
    {
        $json = json_encode($cuerpo, JSON_THROW_ON_ERROR);
        $firma ??= hash_hmac('sha256', $json, (string) config('m6.webhook.secreto'));

        return $this->call(
            'POST',
            "/api/m6/v1/pagos/{$idPago}/confirmaciones",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_FIRMA_PASARELA' => $firma],
            $json,
        );
    }
}
