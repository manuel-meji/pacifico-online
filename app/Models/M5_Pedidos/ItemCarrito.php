<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCarrito extends Model
{
    protected $table = 'items_carrito';

    protected $primaryKey = 'id_item_carrito';

    protected $fillable = ['id_carrito', 'id_variacion', 'cantidad', 'id_reserva'];

    protected $casts = [
        'id_carrito' => 'integer',
        'id_variacion' => 'integer',
        'cantidad' => 'integer',
    ];

    public function crearItemCarrito(int $idCarrito, int $variacionId, int $cantidad, ?string $idReserva = null): self
    {
        $itemExistente = self::where('id_carrito', $idCarrito)
            ->where('id_variacion', $variacionId)
            ->first();

        if ($itemExistente) {
            $itemExistente->cantidad += $cantidad;
            if ($idReserva !== null) {
                $itemExistente->id_reserva = $idReserva;
            }
            $itemExistente->save();

            return $itemExistente;
        }

        return self::create([
            'id_carrito' => $idCarrito,
            'id_variacion' => $variacionId,
            'cantidad' => $cantidad,
            'id_reserva' => $idReserva,
        ]);
    }

    public function actualizarCantidad(int $cant): void
    {
        $this->cantidad = $cant;
        $this->save();
    }

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Carrito::class, 'id_carrito');
    }
}
