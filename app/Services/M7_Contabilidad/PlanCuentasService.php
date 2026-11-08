<?php

namespace App\Services\M7_Contabilidad;

use App\Models\M7_Contabilidad\CuentaContable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Gestión del plan de cuentas (RF-001 / CU-001).
 * El aislamiento por comercio (RS-002) se garantiza filtrando siempre por id_comercio;
 * la verificación de permisos del usuario la hace el controlador con el Núcleo (RS-003).
 */
class PlanCuentasService
{
    public function listar(int $idComercio, bool $soloActivas = false): Collection
    {
        $consulta = CuentaContable::query()->deComercio($idComercio)->orderBy('codigo');

        if ($soloActivas) {
            $consulta->activas();
        }

        return $consulta->get();
    }

    public function obtener(int $idComercio, int $idCuenta): CuentaContable
    {
        return CuentaContable::query()
            ->deComercio($idComercio)
            ->where('id_cuenta_contable', $idCuenta)
            ->firstOrFail();
    }

    public function crear(int $idComercio, array $datos): CuentaContable
    {
        $padre = null;

        if (! empty($datos['id_cuenta_padre'])) {
            $padre = $this->obtener($idComercio, (int) $datos['id_cuenta_padre']);

            if ($padre->tieneMovimientos()) {
                throw new InvalidArgumentException(
                    'No se pueden crear subcuentas bajo una cuenta que ya tiene movimientos.'
                );
            }
        }

        $normalizados = $this->validarDatos($idComercio, $datos, $padre);

        $existe = CuentaContable::query()
            ->deComercio($idComercio)
            ->where('codigo', $normalizados['codigo'])
            ->exists();

        if ($existe) {
            throw new InvalidArgumentException('Ya existe una cuenta con ese código en el comercio.');
        }

        return CuentaContable::create($normalizados);
    }

    public function actualizar(int $idComercio, int $idCuenta, array $datos): CuentaContable
    {
        $cuenta = $this->obtener($idComercio, $idCuenta);

        if ($cuenta->tieneMovimientos()) {
            foreach (['tipo', 'codigo', 'id_cuenta_padre'] as $campo) {
                if (array_key_exists($campo, $datos) && $datos[$campo] != $cuenta->{$campo}) {
                    throw new InvalidArgumentException(
                        "No se puede modificar '{$campo}' de una cuenta con movimientos."
                    );
                }
            }
        }

        $cuenta->fill(array_intersect_key($datos, array_flip(['nombre', 'activa'])));
        $cuenta->save();

        return $cuenta;
    }

    public function desactivar(int $idComercio, int $idCuenta): CuentaContable
    {
        $cuenta = $this->obtener($idComercio, $idCuenta);

        if ($cuenta->subcuentas()->where('activa', true)->exists()) {
            throw new InvalidArgumentException('La cuenta tiene subcuentas activas.');
        }

        $cuenta->activa = false;
        $cuenta->save();

        return $cuenta;
    }

    /**
     * Validación pura (sin base de datos).
     *
     * @return array{id_comercio:int,codigo:string,nombre:string,tipo:string,nivel:int,id_cuenta_padre:?int,activa:bool}
     */
    public function validarDatos(int $idComercio, array $datos, ?CuentaContable $padre = null): array
    {
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? '')));

        if ($codigo === '' || mb_strlen($codigo) > 30) {
            throw new InvalidArgumentException('El código es obligatorio y de máximo 30 caracteres.');
        }

        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw new InvalidArgumentException('El nombre es obligatorio y de máximo 150 caracteres.');
        }

        if (! in_array($tipo, CuentaContable::TIPOS, true)) {
            throw new InvalidArgumentException('Tipo de cuenta inválido.');
        }

        $nivel = 1;

        if ($padre !== null) {
            if ((int) $padre->id_comercio !== $idComercio) {
                throw new InvalidArgumentException('La cuenta padre pertenece a otro comercio.');
            }

            if ($padre->tipo !== $tipo) {
                throw new InvalidArgumentException('La subcuenta debe ser del mismo tipo que su cuenta padre.');
            }

            $nivel = ((int) $padre->nivel) + 1;
        }

        return [
            'id_comercio' => $idComercio,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'nivel' => $nivel,
            'id_cuenta_padre' => $padre?->getKey(),
            'activa' => (bool) ($datos['activa'] ?? true),
        ];
    }
}