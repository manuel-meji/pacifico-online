<?php
namespace App\Models\M5_Pedidos;
use Illuminate\Database\Eloquent\Model;

class PedidoLinea extends Model
{
    protected $table = 'pedidos_lineas';
    protected $primaryKey = 'id_linea';
    protected $fillable = ['id_subpedido', 'id_variacion', 'cantidad', 'precio_unitario', 'tarifa_impuesto'];

    // Aplicación del Patrón Composite: Nodo hoja
    public function calcularSubtotalLinea(): float
    {
        return $this->cantidad * $this->precio_unitario; //[cite: 27]
    }

    public function obtenerMontoImpuesto(): float
    {
        return $this->calcularSubtotalLinea() * ($this->tarifa_impuesto / 100); //[cite: 27]
    }

    public function crearLinea(int $idSubpedido, int $idVariacion, int $cantidad, float $precio, float $tarifaIva): int
    {
        $linea = self::create([
            'id_subpedido' => $idSubpedido,
            'id_variacion' => $idVariacion,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'tarifa_impuesto' => $tarifaIva
        ]); //[cite: 14]
        
        return $linea->id_linea;
    }

    public function subpedido()
    {
        return $this->belongsTo(Subpedido::class, 'id_subpedido'); //[cite: 14]
    }
}