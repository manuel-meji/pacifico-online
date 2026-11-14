<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M7_Contabilidad\CuentaContable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanCuentasService
{
    public function __construct(
        private readonly NucleoServiceInterface $nucleo,
    ) {}

    public function listar(int $idComercio, bool $soloActivas = false): Collection
    {
        $consulta = CuentaContable::query()
            ->where('id_comercio', $idComercio)
            ->orderBy('codigo');

        if ($soloActivas) {
            $consulta->activas();
        }

        return $consulta->get();
    }

    public function obtener(int $idComercio, int $idCuenta): CuentaContable
    {
        $cuenta = CuentaContable::query()
            ->where('id_comercio', $idComercio)
            ->where('id_cuenta_contable', $idCuenta)
            ->first();

        if ($cuenta === null) {
            throw $this->errorM7(
                'ERR-M7-001',
                404,
                'La cuenta contable solicitada no existe.'
            );
        }

        return $cuenta;
    }

    public function crear(int $idComercio, array $datos): CuentaContable
    {
        return DB::transaction(function () use ($idComercio, $datos): CuentaContable {
            $padre = null;

            if (! empty($datos['id_cuenta_padre'])) {
                $padre = $this->obtener(
                    $idComercio,
                    (int) $datos['id_cuenta_padre']
                );

                if ($padre->tieneMovimientos()) {
                    throw $this->errorM7(
                        'ERR-M7-002',
                        409,
                        'No se pueden crear subcuentas bajo una cuenta que ya tiene movimientos.'
                    );
                }
            }

            $normalizados = $this->validarDatos(
                $idComercio,
                $datos,
                $padre
            );

            $existe = CuentaContable::query()
                ->where('id_comercio', $idComercio)
                ->where('codigo', $normalizados['codigo'])
                ->exists();

            if ($existe) {
                throw $this->errorM7(
                    'ERR-M7-003',
                    422,
                    'Ya existe una cuenta con ese código en el comercio.'
                );
            }

            $cuenta = CuentaContable::create($normalizados);

            $this->nucleo->registrarAuditoria(
                'cuenta_contable_creada',
                'cuenta_contable',
                (int) $cuenta->getKey(),
                [
                    'modulo' => 'M7_CONTABILIDAD',
                    'id_comercio' => $idComercio,
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'tipo' => $cuenta->tipo,
                    'id_cuenta_padre' => $cuenta->id_cuenta_padre,
                ]
            );

            return $cuenta;
        });
    }

    public function actualizar(
        int $idComercio,
        int $idCuenta,
        array $datos
    ): CuentaContable {
        return DB::transaction(function () use (
            $idComercio,
            $idCuenta,
            $datos
        ): CuentaContable {
            $cuenta = $this->obtener($idComercio, $idCuenta);

            if ($cuenta->tieneMovimientos()) {
                foreach (['tipo', 'codigo', 'id_cuenta_padre'] as $campo) {
                    if (
                        array_key_exists($campo, $datos)
                        && $datos[$campo] != $cuenta->{$campo}
                    ) {
                        throw $this->errorM7(
                            'ERR-M7-002',
                            409,
                            "No se puede modificar '{$campo}' de una cuenta con movimientos."
                        );
                    }
                }
            }

            $camposPermitidos = ['nombre', 'activa'];
            $antes = [];

            foreach ($camposPermitidos as $campo) {
                $antes[$campo] = $cuenta->{$campo};
            }

            $cuenta->fill(
                array_intersect_key($datos, array_flip($camposPermitidos))
            );

            if ($cuenta->isDirty()) {
                $cuenta->save();

                $despues = [];

                foreach ($camposPermitidos as $campo) {
                    $despues[$campo] = $cuenta->{$campo};
                }

                $this->nucleo->registrarAuditoria(
                    'cuenta_contable_actualizada',
                    'cuenta_contable',
                    (int) $cuenta->getKey(),
                    [
                        'modulo' => 'M7_CONTABILIDAD',
                        'id_comercio' => $idComercio,
                        'antes' => $antes,
                        'despues' => $despues,
                    ]
                );
            }

            return $cuenta;
        });
    }

    public function desactivar(
        int $idComercio,
        int $idCuenta
    ): CuentaContable {
        return DB::transaction(function () use (
            $idComercio,
            $idCuenta
        ): CuentaContable {
            $cuenta = $this->obtener($idComercio, $idCuenta);

            if ($cuenta->subcuentas()->where('activa', true)->exists()) {
                throw $this->errorM7(
                    'ERR-M7-002',
                    409,
                    'La cuenta tiene subcuentas activas.'
                );
            }

            if ($cuenta->tieneMovimientos()) {
                throw $this->errorM7(
                    'ERR-M7-002',
                    409,
                    'No se puede desactivar una cuenta que tiene movimientos.'
                );
            }

            if ($cuenta->activa) {
                $cuenta->activa = false;
                $cuenta->save();

                $this->nucleo->registrarAuditoria(
                    'cuenta_contable_desactivada',
                    'cuenta_contable',
                    (int) $cuenta->getKey(),
                    [
                        'modulo' => 'M7_CONTABILIDAD',
                        'id_comercio' => $idComercio,
                        'codigo' => $cuenta->codigo,
                        'nombre' => $cuenta->nombre,
                    ]
                );
            }

            return $cuenta;
        });
    }

    /**
     * Validación pura (sin base de datos).
     *
     * @return array{
     *     id_comercio:int,
     *     codigo:string,
     *     nombre:string,
     *     tipo:string,
     *     nivel:int,
     *     id_cuenta_padre:?int,
     *     activa:bool
     * }
     */
    public function validarDatos(
        int $idComercio,
        array $datos,
        ?CuentaContable $padre = null
    ): array {
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? '')));

        if ($codigo === '' || mb_strlen($codigo) > 30) {
            throw $this->errorM7(
                'ERR-M7-003',
                422,
                'El código es obligatorio y de máximo 30 caracteres.'
            );
        }

        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw $this->errorM7(
                'ERR-M7-003',
                422,
                'El nombre es obligatorio y de máximo 150 caracteres.'
            );
        }

        if (! in_array($tipo, CuentaContable::TIPOS, true)) {
            throw $this->errorM7(
                'ERR-M7-003',
                422,
                'Tipo de cuenta inválido.'
            );
        }

        $nivel = 1;

        if ($padre !== null) {
            if ((int) $padre->id_comercio !== $idComercio) {
                throw $this->errorM7(
                    'ERR-M7-003',
                    422,
                    'La cuenta padre pertenece a otro comercio.'
                );
            }

            if ($padre->tipo !== $tipo) {
                throw $this->errorM7(
                    'ERR-M7-003',
                    422,
                    'La subcuenta debe ser del mismo tipo que su cuenta padre.'
                );
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

    private function errorM7(
        string $codigo,
        int $http,
        string $mensaje
    ): ErrorNucleoException {
        return ErrorNucleoException::paraModulo(
            'M7_Contabilidad',
            $codigo,
            $http,
            $mensaje
        );
    }
}
