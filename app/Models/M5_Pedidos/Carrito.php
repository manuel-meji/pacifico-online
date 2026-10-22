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
        return self::firstOrCreate(
            ['id_cliente' => $clienteId],
            ['monto_estimado' => 0.00]
        ); //[cite: 12]
    }

    public function recalcularTotales(): void
    {
        // Se asume que el monto estimado es la suma de cantidades por precio (manejado en conjunto con M3)
        $this->monto_estimado = $this->items()->sum('cantidad'); 
        $this->save(); //[cite: 12]
    }

    public function vaciar(): void
    {
        $this->items()->delete(); //[cite: 12]
        $this->monto_estimado = 0.00;
        $this->save();
    }

    public function actualizarTotales(): void
    {
        $this->recalcularTotales(); //[cite: 12]
    }

    public function items()
    {
        return $this->hasMany(ItemCarrito::class, 'id_carrito'); //[cite: 12]
    }
}