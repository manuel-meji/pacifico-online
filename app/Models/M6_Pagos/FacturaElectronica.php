<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Factura electrónica de un subpedido (tabla facturas_electronicas). Privada de M6.
 *
 * Una factura por subpedido/comercio (decisión 3.1 del Comité): la columna
 * id_subpedido es UNIQUE y eso hace idempotente la facturación.
 * id_subpedido, id_comercio e id_cliente son referencias a M5, M2 y M1: se
 * guardan como número y se leen por contrato, nunca con JOIN.
 * El id del pedido no se guarda aquí: se obtiene del pago (tabla propia de M6).
 */
class FacturaElectronica extends Model
{
    protected $table = 'facturas_electronicas';

    protected $primaryKey = 'id_factura_electronica';

    public $timestamps = false;

    protected $fillable = [
        'id_subpedido',
        'id_comercio',
        'id_pago',
        'id_cliente',
        'id_estado_factura_hacienda',
        'consecutivo',
        'xml_documento',
        'monto_total',
        'razon_social_emisor',
        'cedula_juridica_emisor',
        'identificacion_receptor',
        'fecha_emision',
    ];

    protected function casts(): array
    {
        return [
            'id_subpedido' => 'integer',
            'id_comercio' => 'integer',
            'id_pago' => 'integer',
            'id_cliente' => 'integer',
            'monto_total' => 'decimal:2',
            'fecha_emision' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'id_pago', 'id_pago');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoFacturaHacienda::class, 'id_estado_factura_hacienda', 'id_estado_factura_hacienda');
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(NotaCredito::class, 'id_factura_electronica', 'id_factura_electronica');
    }

    public function notasDebito(): HasMany
    {
        return $this->hasMany(NotaDebito::class, 'id_factura_electronica', 'id_factura_electronica');
    }

    public function codigoEstado(): string
    {
        return $this->estado->codigo;
    }

    public function estaAceptada(): bool
    {
        return $this->codigoEstado() === EstadoFacturaHacienda::ACEPTADA;
    }

    /** Clave numérica de 50 dígitos guardada dentro del XML del comprobante. */
    public function claveNumerica(): ?string
    {
        return preg_match('/<Clave>(\d{50})<\/Clave>/', (string) $this->xml_documento, $m) === 1 ? $m[1] : null;
    }
}
