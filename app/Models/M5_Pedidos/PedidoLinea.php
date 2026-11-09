<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PedidoLinea extends Model
{
    protected $table = 'pedidos_lineas';

    protected $primaryKey = 'id_linea';

    protected $fillable = [
        'id_subpedido',
        'id_variacion',
        'cantidad',
        'precio_unitario',
        'tarifa_impuesto',
        'descuento',
        'id_promocion',
        'id_reserva',
    ];

    protected $casts = [
        'id_subpedido' => 'integer',
        'id_variacion' => 'integer',
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'tarifa_impuesto' => 'decimal:2',
        'descuento' => 'decimal:2',
        'id_promocion' => 'integer',
    ];

    // Aplicación del Patrón Composite: Nodo hoja
    public function calcularSubtotalLinea(): float
    {
        return (float) ($this->cantidad * (float) $this->precio_unitario);
    }

    public function obtenerMontoImpuesto(): float
    {
        return (float) ($this->calcularSubtotalLinea() * ((float) $this->tarifa_impuesto / 100));
    }

    public function crearLinea(
        int $idSubpedido,
        int $idVariacion,
        int $cantidad,
        float $precio,
        float $tarifaIva,
        ?string $idReserva = null,
        float $descuento = 0.00,
        ?int $idPromocion = null
    ): int {
        $datos = [
            'id_subpedido' => $idSubpedido,
            'id_variacion' => $idVariacion,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'tarifa_impuesto' => $tarifaIva,
            'id_reserva' => $idReserva,
        ];

        if (Schema::hasColumn('pedidos_lineas', 'descuento')) {
            $datos['descuento'] = round(max(0.0, $descuento), 2);
        }

        if (Schema::hasColumn('pedidos_lineas', 'id_promocion')) {
            $datos['id_promocion'] = $idPromocion;
        }

        $linea = self::create($datos);

        return (int) $linea->id_linea;
    }

    public function subpedido(): BelongsTo
    {
        return $this->belongsTo(Subpedido::class, 'id_subpedido');
    }
}
