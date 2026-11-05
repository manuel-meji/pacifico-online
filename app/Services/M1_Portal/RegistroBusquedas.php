<?php

namespace App\Services\M1_Portal;

use App\Models\M1_Portal\BusquedaUsuario;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\Nucleo\ContextoCliente;
use Illuminate\Support\Facades\DB;

/**
 * Guarda cada búsqueda en busquedas_usuario y sus filtros en filtros_aplicados.
 */
class RegistroBusquedas
{
    public function __construct(
        private ContextoCliente $contextoCliente,
    ) {}

    /**
     * @param  string|null  $tipoCategoria  'categoria' o 'subcategoria'.
     */
    public function registrar(CriterioBusquedaDTO $criterio, int $cantidadResultados, ?string $tipoCategoria): BusquedaUsuario
    {
        return DB::transaction(function () use ($criterio, $cantidadResultados, $tipoCategoria): BusquedaUsuario {
            $busqueda = BusquedaUsuario::create([
                'id_cliente' => $this->contextoCliente->idActual(),
                'identificador_sesion' => request()->hasSession() ? request()->session()->getId() : null,
                'termino_busqueda' => $criterio->termino ?? '',
                'cantidad_resultados' => $cantidadResultados,
                'fecha_busqueda' => now(),
            ]);

            foreach (self::filtros($criterio, $tipoCategoria) as $tipo => $valor) {
                $busqueda->filtros()->create(['tipo_filtro' => $tipo, 'valor_filtro' => $valor]);
            }

            return $busqueda;
        });
    }

    /**
     * Filtros del criterio como tipo_filtro → valor_filtro.
     *
     * @return array<string, string>
     */
    public static function filtros(CriterioBusquedaDTO $criterio, ?string $tipoCategoria): array
    {
        $valores = [
            $tipoCategoria ?? 'categoria' => $criterio->idCategoria,
            'comercio' => $criterio->idComercio,
            'marca' => $criterio->idMarca,
            'precio_minimo' => $criterio->precioMinimo,
            'precio_maximo' => $criterio->precioMaximo,
            'orden' => $criterio->orden,
        ];

        return array_map(
            fn (int|float|string $valor): string => (string) $valor,
            array_filter($valores, fn (mixed $valor): bool => $valor !== null),
        );
    }
}
