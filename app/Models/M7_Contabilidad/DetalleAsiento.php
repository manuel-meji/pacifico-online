<?php

declare(strict_types=1);

namespace App\Models\M7_Contabilidad;

use App\Services\M7_Contabilidad\ValidadorPartidaDoble;

final readonly class DetalleAsiento
{
    public function __construct(
        public int $cuentaId,
        public string $debe,
        public string $haber,
    ) {}

    public function esDebito(): bool
    {
        return (new ValidadorPartidaDoble)->aCentimos($this->debe) > 0;
    }

    public function esCredito(): bool
    {
        return (new ValidadorPartidaDoble)->aCentimos($this->haber) > 0;
    }
}
