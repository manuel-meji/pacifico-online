<?php

declare(strict_types=1);

namespace App\Models\M7_Contabilidad;

final class EventoProcesado
{
    public function __construct(
        public readonly string $eventoId,
        public readonly string $tipo,
        private string $estado,
    ) {}

    public function marcarProcesado(): void
    {
        $this->estado = 'PROCESADO';
    }

    public function estaProcesado(): bool
    {
        return $this->estado === 'PROCESADO';
    }

    public function estado(): string
    {
        return $this->estado;
    }
}
