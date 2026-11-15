<?php

declare(strict_types=1);

namespace Database\Seeders\Nucleo;

use App\Services\Nucleo\ServicioIdentidad;
use RuntimeException;

/**
 * Contraseña de las cuentas del escenario de demostración (DEMO_CONTRASENA del .env).
 *
 * La validan DatabaseSeeder antes de llamar a ningún seeder, y CuentasDemoSeeder y
 * ClientesDemoSeeder cuando se ejecutan sueltos, para que una contraseña ausente o corta
 * detenga la carga antes de la primera escritura y la base no quede a medias.
 */
final class ContrasenaDemo
{
    /**
     * @param  string  $seeder  Nombre del seeder que la pide, para el mensaje de error.
     *
     * @throws RuntimeException si falta o tiene menos caracteres que el mínimo del núcleo.
     */
    public static function exigir(string $seeder): string
    {
        $contrasena = config('nucleo.demo.contrasena');

        if (! is_string($contrasena) || trim($contrasena) === '') {
            throw new RuntimeException(
                "{$seeder}: no se escribió nada. Falta la variable DEMO_CONTRASENA en el .env; "
                .'defínala con la contraseña de las cuentas de demostración (no hay contraseña por defecto) '
                .'y, si la configuración está en caché, ejecute php artisan config:clear.'
            );
        }

        $minimo = ServicioIdentidad::LARGO_MINIMO_CONTRASENA;

        if (mb_strlen($contrasena) < $minimo) {
            throw new RuntimeException(
                "{$seeder}: no se escribió nada. DEMO_CONTRASENA debe tener al menos {$minimo} caracteres, "
                .'el mínimo que exige el núcleo al crear una cuenta; corríjala en el .env '
                .'y, si la configuración está en caché, ejecute php artisan config:clear.'
            );
        }

        return $contrasena;
    }
}
