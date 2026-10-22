<?php
namespace App\Models\M5_Pedidos;
use Illuminate\Database\Eloquent\Model;

class PedidoMaestro extends Model
{
    protected $table = 'pedidos_maestros';
    protected $primaryKey = 'id_pedido';
    protected $fillable = ['id_cliente', 'monto_total', 'estado_pedido', 'cupon_descuento', 'tarjeta_regalo', 'fecha_creacion'];

    public function calcularMontoTotal(): float
    {
        // Lógica pendiente
    }

    public function actualizarEstado(string $nuevoEstado): void
    {
        // Lógica pendiente
    }

    public function agregarSubpedido(Subpedido $subpedido): void
    {
        // Lógica pendiente
    }

    public function crearPedidoMaestro(float $montoTotal): int
    {
        // Lógica pendiente
    }

    // Relación: Agrupa Subpedido (1..*)
    public function subpedidos()
    {
        return $this->hasMany(Subpedido::class, 'id_pedido');
    }
}