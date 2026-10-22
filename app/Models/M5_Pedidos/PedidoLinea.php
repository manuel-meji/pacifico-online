<?php
namespace App\Models\M5_Pedidos;
use Illuminate\Database\Eloquent\Model;

class PedidoLinea extends Model
{
    protected $table = 'pedidos_lineas';
    protected $primaryKey = 'id_linea';
    protected $fillable = ['id_subpedido', 'id_variacion', 'cantidad', 'precio_unitario', 'tarifa_impuesto'];

    public function calcularSubtotalLinea(): float
    {
        // Lógica pendiente
    }

    public function obtenerMontoImpuesto(): float
    {
        // Lógica pendiente
    }

    public function crearLinea(int $idSubpedido, int $idVariacion, int $cantidad, float $precio, float $tarifaIva): int
    {
        // Lógica pendiente
    }

    // Relación: Pertenece a Subpedido (1)
    public function subpedido()
    {
        return $this->belongsTo(Subpedido::class, 'id_subpedido');
    }
}