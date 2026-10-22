<?php
namespace App\Models\M5_Pedidos;
use Illuminate\Database\Eloquent\Model;

class Carrito extends Model
{
    protected $table = 'pedidos_carritos';
    protected $primaryKey = 'id_carrito';
    protected $fillable = ['id_cliente', 'monto_estimado'];

    public function obtenerOCrearCarrito(int $clienteId): self
    {
        // Lógica pendiente
    }

    public function recalcularTotales(): void
    {
        // Lógica pendiente
    }

    public function vaciar(): void
    {
        // Lógica pendiente
    }

    public function actualizarTotales(): void
    {
        // Lógica pendiente
    }

    // Relación: Contiene ItemCarrito (1..*)
    public function items()
    {
        return $this->hasMany(ItemCarrito::class, 'id_carrito');
    }
}