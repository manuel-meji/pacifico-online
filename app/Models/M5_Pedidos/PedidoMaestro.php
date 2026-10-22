<?php
namespace App\Models\M5_Pedidos;
use Illuminate\Database\Eloquent\Model;

class Subpedido extends Model
{
    protected $table = 'pedidos_subpedidos';
    protected $primaryKey = 'id_subpedido';
    protected $fillable = ['id_pedido', 'id_comercio', 'subtotal', 'costo_envio', 'impuesto', 'estado_subpedido'];

    // Aplicación del Patrón Composite: Nodo intermedio
    public function calcularSubtotal(): float
    {
        $subtotalLineas = 0;
        $totalImpuestos = 0;
        
        foreach ($this->lineas as $linea) {
            $subtotalLineas += $linea->calcularSubtotalLinea(); //[cite: 27]
            $totalImpuestos += $linea->obtenerMontoImpuesto(); //[cite: 27]
        }
        
        $this->subtotal = $subtotalLineas;
        $this->impuesto = $totalImpuestos;
        $this->save(); //[cite: 14]
        
        return $this->subtotal + $this->impuesto + $this->costo_envio; 
    }

    public function cambiarEstado(string $nuevoEstado): void
    {
        $this->estado_subpedido = $nuevoEstado;
        $this->save(); //[cite: 14]
    }

    public function cancelar(string $motivo): void
    {
        $this->estado_subpedido = 'cancelado';
        $this->save(); //[cite: 14]
    }

    public function crearSubpedidos(array $comerciosInvolucrados): array
    {
        $creados = [];
        foreach ($comerciosInvolucrados as $comercioId) {
            $creados[] = self::create([
                'id_pedido' => $this->id_pedido,
                'id_comercio' => $comercioId,
                'estado_subpedido' => 'pendiente'
            ]); //[cite: 14]
        }
        return $creados;
    }

    public function pedidoMaestro()
    {
        return $this->belongsTo(PedidoMaestro::class, 'id_pedido'); //[cite: 13]
    }

    public function lineas()
    {
        return $this->hasMany(PedidoLinea::class, 'id_subpedido'); //[cite: 13]
    }
}