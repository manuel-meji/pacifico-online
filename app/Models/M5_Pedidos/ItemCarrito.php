<?php
namespace App\Models\M5_Pedidos;
use Illuminate\Database\Eloquent\Model;

class ItemCarrito extends Model
{
    protected $table = 'items_carrito';
    protected $primaryKey = 'id_item_carrito';
    protected $fillable = ['id_carrito', 'id_variacion', 'cantidad', 'id_reserva'];

    public function crearItemCarrito(int $idCarrito, int $variacionId, int $cantidad): self
    {
        return self::create([
            'id_carrito' => $idCarrito,
            'id_variacion' => $variacionId,
            'cantidad' => $cantidad
        ]); //[cite: 12]
    }

    public function actualizarCantidad(int $cant): void
    {
        $this->cantidad = $cant;
        $this->save(); //[cite: 12]
    }

    public function carrito()
    {
        return $this->belongsTo(Carrito::class, 'id_carrito'); //[cite: 12]
    }
}