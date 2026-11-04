<?php

namespace App\Models\M1_Portal;

use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Database\Factories\M1_Portal\DireccionClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla direcciones_cliente: direcciones de envío; una sola principal por cliente.
 *
 * @property int $id_direccion_cliente
 * @property int $id_cliente
 * @property string $alias_direccion
 * @property string $nombre_destinatario
 * @property string $telefono_destinatario
 * @property string $provincia
 * @property string $canton
 * @property string $distrito
 * @property string $otras_senas
 * @property bool $es_principal
 */
class DireccionCliente extends Model
{
    /** @use HasFactory<DireccionClienteFactory> */
    use HasFactory;

    protected $table = 'direcciones_cliente';

    protected $primaryKey = 'id_direccion_cliente';

    protected $fillable = [
        'id_cliente',
        'alias_direccion',
        'nombre_destinatario',
        'telefono_destinatario',
        'provincia',
        'canton',
        'distrito',
        'otras_senas',
        'es_principal',
    ];

    protected $attributes = [
        'es_principal' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_direccion_cliente' => 'integer',
            'id_cliente' => 'integer',
            'es_principal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function aDTO(): DireccionClienteDTO
    {
        return new DireccionClienteDTO(
            idDireccionCliente: $this->id_direccion_cliente,
            idCliente: $this->id_cliente,
            aliasDireccion: $this->alias_direccion,
            nombreDestinatario: $this->nombre_destinatario,
            telefonoDestinatario: $this->telefono_destinatario,
            provincia: $this->provincia,
            canton: $this->canton,
            distrito: $this->distrito,
            otrasSenas: $this->otras_senas,
            esPrincipal: $this->es_principal,
        );
    }
}
