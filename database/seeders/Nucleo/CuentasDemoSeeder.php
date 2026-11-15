<?php

declare(strict_types=1);

namespace Database\Seeders\Nucleo;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Las seis cuentas del escenario de demostración (sección 2.2 del colectivo del PC4):
 * el encargado de la plataforma y un encargado por cada uno de los cinco comercios.
 * Todas quedan activas y con la contraseña de la variable DEMO_CONTRASENA del .env.
 *
 * Antes hay que sembrar los comercios de M2, porque el id_comercio de cada encargado
 * se busca por cédula jurídica con el contrato de M2:
 *     php artisan db:seed --class="Database\Seeders\M2_Comercios\ComerciosDemoSeeder"
 *     php artisan db:seed --class="Database\Seeders\Nucleo\CuentasDemoSeeder"
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar cuentas. Si la contraseña
 * falta o es más corta que el mínimo del núcleo (ver ContrasenaDemo), o falta algún
 * comercio, se detiene sin escribir nada.
 *
 * No usa ServicioIdentidad::crearCuenta porque a los encargados de comercio les asigna
 * una contraseña al azar y les envía la invitación por correo.
 */
class CuentasDemoSeeder extends Seeder
{
    /** @var list<array{nombre: string, correo: string, rol: RolUsuario, cedula: ?string}> */
    public const CUENTAS = [
        ['nombre' => 'Encargado de la Plataforma', 'correo' => 'plataforma.m2@prueba.cr', 'rol' => RolUsuario::EncargadoPlataforma, 'cedula' => null],
        ['nombre' => 'Encargado Soluciones del Milenio', 'correo' => 'encargado1.m2@prueba.cr', 'rol' => RolUsuario::EncargadoComercio, 'cedula' => '3-101-100001'],
        ['nombre' => 'Encargado Temulandia', 'correo' => 'encargado2.m2@prueba.cr', 'rol' => RolUsuario::EncargadoComercio, 'cedula' => '3-101-100002'],
        ['nombre' => 'Encargado Elegancia', 'correo' => 'encargado3.m2@prueba.cr', 'rol' => RolUsuario::EncargadoComercio, 'cedula' => '3-101-100003'],
        ['nombre' => 'Encargado Deportes Pacífico', 'correo' => 'encargado4.m2@prueba.cr', 'rol' => RolUsuario::EncargadoComercio, 'cedula' => '3-101-100004'],
        ['nombre' => 'Encargado Café Volcán', 'correo' => 'encargado5.m2@prueba.cr', 'rol' => RolUsuario::EncargadoComercio, 'cedula' => '3-101-100005'],
    ];

    /** Máximo que permite el contrato de M2 en listarComerciosHabilitados. */
    private const COMERCIOS_POR_PAGINA = 50;

    public function run(ComerciosServiceInterface $comercios, ContextoComercio $contexto): void
    {
        // Todas las validaciones van antes de la primera escritura.
        $contrasena = ContrasenaDemo::exigir('CuentasDemoSeeder');
        $idPorCedula = $this->idsDeComercioPorCedula($comercios, $contexto);

        DB::transaction(function () use ($contrasena, $idPorCedula): void {
            foreach (self::CUENTAS as $cuenta) {
                // El modelo cifra la contraseña con su cast 'hashed': aquí va en texto plano.
                Usuario::query()->updateOrCreate(
                    ['correo' => $cuenta['correo']],
                    [
                        'nombre' => $cuenta['nombre'],
                        'contrasena' => $contrasena,
                        'rol' => $cuenta['rol'],
                        'id_comercio' => $cuenta['cedula'] === null ? null : $idPorCedula[$cuenta['cedula']],
                        'activo' => true,
                    ],
                );
            }
        });

        $this->command?->info('Núcleo: '.count(self::CUENTAS).' cuentas del escenario de demostración listas.');
    }

    /**
     * id_comercio de cada cédula del escenario, leído por el contrato de M2.
     *
     * @return array<string, int>
     */
    private function idsDeComercioPorCedula(ComerciosServiceInterface $comercios, ContextoComercio $contexto): array
    {
        $esperadas = array_values(array_filter(array_column(self::CUENTAS, 'cedula')));

        // Sin usuario no hay ámbito de comercio: la consulta se hace en modo sistema.
        $idPorCedula = $contexto->ejecutarComoSistema(function () use ($comercios, $esperadas): array {
            $encontradas = [];
            $pagina = 1;

            do {
                $respuesta = $comercios->listarComerciosHabilitados($pagina, self::COMERCIOS_POR_PAGINA);

                foreach ($respuesta['comercios'] as $comercio) {
                    $idComercio = (int) $comercio['id_comercio'];
                    $cedula = (string) $comercios->obtenerDatosFiscalesComercio($idComercio)['cedula_juridica'];

                    if (in_array($cedula, $esperadas, true)) {
                        $encontradas[$cedula] = $idComercio;
                    }
                }

                $quedanPaginas = $respuesta['comercios'] !== []
                    && $pagina * self::COMERCIOS_POR_PAGINA < (int) $respuesta['total'];
                $pagina++;
            } while ($quedanPaginas && count($encontradas) < count($esperadas));

            return $encontradas;
        });

        $faltantes = array_values(array_diff($esperadas, array_keys($idPorCedula)));

        if ($faltantes !== []) {
            throw new RuntimeException(
                'CuentasDemoSeeder: no se creó ninguna cuenta. M2 no tiene habilitado el comercio con cédula jurídica '
                .implode(', ', $faltantes).'. Primero ejecute ComerciosDemoSeeder: '
                .'php artisan db:seed --class="Database\Seeders\M2_Comercios\ComerciosDemoSeeder"'
            );
        }

        return $idPorCedula;
    }
}
