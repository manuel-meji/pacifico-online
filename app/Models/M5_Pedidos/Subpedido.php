<?php
namespace App\Models\M5_Pedidos;
use Illuminate\Database\Eloquent\Model;

class Subpedido extends Model
{
    protected $table = 'pedidos_subpedidos';
    protected $primaryKey = 'id_subpedido';
    protected $fillable = ['id_pedido', 'id_comercio', 'subtotal', 'costo_envio', 'impuesto', 'estado_subpedido'];

    public function calcularSubtotal(): float
    {
        // Lógica pendiente
    }

    public function cambiarEstado(string $nuevoEstado): void
    {
        // Lógica pendiente
    }

    public function cancelar(string $motivo): void
    {
        // Lógica pendiente
    }

    public function crearSubpedido(array $comerciosInvolucrados): array
    {
        // Lógica pendiente
    }

    // Relación: Pertenece a PedidoMaestro (1)
    public function pedidoMaestro()
    {
        return $this->belongsTo(PedidoMaestro::class, 'id_pedido');
    }

    // Relación: Contiene PedidoLinea (1..*)
    public function lineas()
    {
        return $this->hasMany(PedidoLinea::class, 'id_subpedido');
    }
}
