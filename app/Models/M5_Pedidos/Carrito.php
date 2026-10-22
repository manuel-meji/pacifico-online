<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Carrito extends Model
{
    protected $table = 'pedidos_carritos';

    protected $primaryKey = 'id_carrito';

    protected $fillable = ['id_cliente'];

    protected $casts = [
        'id_cliente' => 'integer',
    ];

    public function obtenerOCrearCarrito(int $clienteId): self
    {
        return self::firstOrCreate(
            ['id_cliente' => $clienteId]
        );
    }

    public function recalcularTotales(float $montoCalculado = 0.00): void
    {
        $monto = $montoCalculado > 0.00 ? $montoCalculado : (float) $this->items()->sum('cantidad');

        if (Schema::hasColumn('pedidos_carritos', 'monto_estimado')) {
            $this->monto_estimado = $monto;
            $this->save();
        } else {
            $this->setAttribute('monto_estimado', $monto);
        }
    }

    public function vaciar(): void
    {
        $this->items()->delete();

        if (Schema::hasColumn('pedidos_carritos', 'monto_estimado')) {
            $this->monto_estimado = 0.00;
            $this->save();
        } else {
            $this->setAttribute('monto_estimado', 0.00);
        }
    }

    public function actualizarTotales(): void
    {
        $this->recalcularTotales();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemCarrito::class, 'id_carrito');
    }
}
