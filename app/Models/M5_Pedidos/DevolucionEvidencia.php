<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo de evidencia (foto o PDF) adjunto a una solicitud de devolución.
 */
class DevolucionEvidencia extends Model
{
    protected $table = 'devoluciones_evidencias';

    protected $primaryKey = 'id_evidencia';

    protected $fillable = [
        'id_devolucion',
        'disco',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
    ];

    protected $casts = [
        'id_devolucion' => 'integer',
        'tamano' => 'integer',
    ];

    // La ruta y el disco son internos: se descargan por el endpoint autorizado.
    protected $hidden = ['disco', 'ruta'];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(DevolucionSubpedido::class, 'id_devolucion', 'id_devolucion');
    }
}
