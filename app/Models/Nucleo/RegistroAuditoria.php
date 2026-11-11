<?php

declare(strict_types=1);

namespace App\Models\Nucleo;

use Database\Factories\Nucleo\RegistroAuditoriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Registro de solo inserción (sección 5.4).
 * El trigger PostgreSQL protege también las escrituras masivas y SQL directo.
 */
class RegistroAuditoria extends Model
{
    /** @use HasFactory<RegistroAuditoriaFactory> */
    use HasFactory;

    protected $table = 'bitacora_auditoria';

    protected $primaryKey = 'id_bitacora';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'id_usuario', 'id_comercio', 'fecha_hora', 'accion', 'modulo',
        'entidad', 'entidad_id', 'resultado', 'motivo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime'];
    }

    /** @param Builder<static> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('La bitácora de auditoría es inmutable: UPDATE y DELETE no están permitidos.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new LogicException('La bitácora de auditoría es inmutable: UPDATE y DELETE no están permitidos.');
    }
}
