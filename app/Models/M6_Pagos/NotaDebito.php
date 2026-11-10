<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nota de débito sobre una factura electrónica (tabla notas_debito). Privada de M6. */
class NotaDebito extends Model
{
    protected $table = 'notas_debito';

    protected $primaryKey = 'id_nota_debito';

    public $timestamps = false;

    protected $fillable = ['id_factura_electronica', 'monto', 'motivo', 'xml_documento', 'fecha_emision'];

    protected function casts(): array
    {
        return [
            'id_factura_electronica' => 'integer',
            'monto' => 'decimal:2',
            'fecha_emision' => 'datetime',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(FacturaElectronica::class, 'id_factura_electronica', 'id_factura_electronica');
    }
}
