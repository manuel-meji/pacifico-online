<?php

namespace App\Models\M1_Portal;

use App\Services\M1_Portal\DTO\ClienteDTO;
use Database\Factories\M1_Portal\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla clientes: entidad canónica Cliente (propiedad de M1 según el PC2).
 * id_usuario enlaza con la cuenta del núcleo; la contraseña la guarda el núcleo.
 *
 * @property int $id_cliente
 * @property int $id_usuario
 * @property string $cedula
 * @property string $nombre
 * @property string $apellidos
 * @property string $correo_electronico
 * @property string $telefono
 * @property string $estado_cliente
 */
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_INACTIVO = 'inactivo';

    protected $table = 'clientes';

    protected $primaryKey = 'id_cliente';

    protected $fillable = [
        'id_usuario',
        'cedula',
        'nombre',
        'apellidos',
        'correo_electronico',
        'telefono',
        'estado_cliente',
    ];

    protected $attributes = [
        'estado_cliente' => self::ESTADO_ACTIVO,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_cliente' => 'integer',
            'id_usuario' => 'integer',
        ];
    }

    /**
     * Composición con DireccionCliente (1 a 0..*).
     *
     * @return HasMany<DireccionCliente, $this>
     */
    public function direcciones(): HasMany
    {
        return $this->hasMany(DireccionCliente::class, 'id_cliente', 'id_cliente');
    }

    /**
     * Dirección marcada como principal; la base de datos garantiza que haya
     * a lo sumo una por cliente (uq_direcciones_cliente_principal).
     */
    public function direccionPrincipal(): ?DireccionCliente
    {
        return $this->direcciones->firstWhere('es_principal', true);
    }

    /**
     * Datos que se exponen; direccionEnvio es la dirección principal en texto
     * o una cadena vacía si el cliente todavía no registra direcciones.
     */
    public function aDTO(): ClienteDTO
    {
        $principal = $this->direccionPrincipal();

        return new ClienteDTO(
            idCliente: $this->id_cliente,
            nombre: $this->nombre,
            apellidos: $this->apellidos,
            correoElectronico: $this->correo_electronico,
            telefono: $this->telefono,
            cedula: $this->cedula,
            direccionEnvio: $principal !== null ? $this->direccionEnTexto($principal) : '',
        );
    }

    /**
     * Dirección en una línea (otras señas, distrito, cantón, provincia), que es
     * el direccion_envio del esquema Cliente del contrato.
     */
    private function direccionEnTexto(DireccionCliente $direccion): string
    {
        return "{$direccion->otras_senas}, {$direccion->distrito}, {$direccion->canton}, {$direccion->provincia}";
    }
}
