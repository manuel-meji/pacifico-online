<?php

declare(strict_types=1);

namespace App\Models\M7_Contabilidad;

use App\Services\M7_Contabilidad\ValidadorPartidaDoble;
use InvalidArgumentException;

final class AsientoContable
{
    /** @var list<DetalleAsiento> */
    private array $detalles = [];

    public function __construct(
        public readonly int $id,
        public readonly int $comercioId,
        public readonly int $pedidoId,
        public readonly int $subpedidoId,
        public readonly string $eventoId,
        public readonly string $tipoEvento,
        public readonly string $tipo,
        public readonly string $fecha,
    ) {}

    public function agregarDetalle(DetalleAsiento $detalle): void
    {
        $this->detalles[] = $detalle;
    }

    /** @return list<DetalleAsiento> */
    public function detalles(): array
    {
        return $this->detalles;
    }

    public function totalDebe(): string
    {
        return $this->total('debe');
    }

    public function totalHaber(): string
    {
        return $this->total('haber');
    }

    public function estaBalanceado(): bool
    {
        try {
            (new ValidadorPartidaDoble)->validar(array_map(
                fn (DetalleAsiento $detalle): array => ['debe' => $detalle->debe, 'haber' => $detalle->haber],
                $this->detalles,
            ));

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function total(string $lado): string
    {
        $validador = new ValidadorPartidaDoble;
        $total = 0;
        foreach ($this->detalles as $detalle) {
            $importe = $validador->aCentimos($detalle->{$lado});
            if ($importe > PHP_INT_MAX - $total) {
                throw new InvalidArgumentException('El total del asiento excede el rango permitido.');
            }
            $total += $importe;
        }

        return intdiv($total, 100).'.'.str_pad((string) ($total % 100), 2, '0', STR_PAD_LEFT);
    }
}
