<?php

declare(strict_types=1);

namespace Tests\Doubles\M6;

use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;

/**
 * Doble en memoria de ConsultaEventosPagosInterface (contrato M6 v1.4.0) para
 * que los consumidores (M7) prueben su recuperación de eventos sin las tablas de M6.
 *
 * Uso: $fake = new ConsultaEventosPagosFake(pagos: [PagoConfirmado->payload(), ...]);
 *      app()->instance(ConsultaEventosPagosInterface::class, $fake);
 *
 * Filtra por el campo "fecha" del payload (inclusive) y, cuando trae
 * id_comercio, por comercio. Los pagos no tienen comercio: se devuelven todos.
 */
class ConsultaEventosPagosFake implements ConsultaEventosPagosInterface
{
    /**
     * @param  list<array<string, mixed>>  $pagos
     * @param  list<array<string, mixed>>  $reembolsos
     * @param  list<array<string, mixed>>  $facturas
     */
    public function __construct(
        public array $pagos = [],
        public array $reembolsos = [],
        public array $facturas = [],
    ) {}

    public function listarPagosConfirmados(string $desde, string $hasta, ?int $idComercio = null): array
    {
        return $this->filtrar($this->pagos, $desde, $hasta, null);
    }

    public function listarReembolsosProcesados(string $desde, string $hasta, ?int $idComercio = null): array
    {
        return $this->filtrar($this->reembolsos, $desde, $hasta, $idComercio);
    }

    public function listarFacturasGeneradas(string $desde, string $hasta, ?int $idComercio = null): array
    {
        return $this->filtrar($this->facturas, $desde, $hasta, $idComercio);
    }

    /**
     * @param  list<array<string, mixed>>  $eventos
     * @return list<array<string, mixed>>
     */
    private function filtrar(array $eventos, string $desde, string $hasta, ?int $idComercio): array
    {
        $inicio = strtotime($desde);
        $fin = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) === 1 ? strtotime($hasta.' 23:59:59') : strtotime($hasta);

        return array_values(array_filter($eventos, fn (array $e): bool => strtotime((string) $e['fecha']) >= $inicio
            && strtotime((string) $e['fecha']) <= $fin
            && ($idComercio === null || ($e['id_comercio'] ?? null) === $idComercio)));
    }
}
