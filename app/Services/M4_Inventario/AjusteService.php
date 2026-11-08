<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\AjusteInventario;
use App\Models\M4_Inventario\MotivoAjuste;
use App\Services\M4_Inventario\DTO\AjusteDTO;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CU-M4-02 «Registrar conteo físico y ajuste» (RN-M4-011).
 *
 * Bajo bloqueo compara la cantidad que vio el encargado con la física actual
 * (ERR-M4-016). Sin diferencia registra el conteo sin movimiento. Con diferencia
 * exige la autorización previa del Administrador del comercio, no deja el físico
 * por debajo de lo comprometido (ERR-M4-017) y asienta el ajuste en el kardex.
 */
class AjusteService
{
    public function __construct(
        private readonly ValidadorPertenencia $validador,
        private readonly ExistenciaRepositoryInterface $existencias,
        private readonly KardexService $kardex,
        private readonly VerificadorAutorizacionesInterface $autorizaciones,
        private readonly PublicadorDisponibilidad $publicador,
        private readonly NucleoDependencia $nucleo,
    ) {}

    public function registrarAjuste(AjusteDTO $dto, int $idUsuario): AjusteInventario
    {
        $this->validador->validarComercioActivo($dto->idComercio);

        $ajuste = DB::transaction(function () use ($dto, $idUsuario): AjusteInventario {
            $existencia = $this->existencias->bloquear($dto->idComercio, $dto->idVariacion, $dto->idUbicacion)
                ?? throw ErrorInventarioException::existenciaNoEncontrada();

            if ($existencia->cantidad_fisica !== $dto->cantidadSistemaVista) {
                throw ErrorInventarioException::conteoDesactualizado();
            }

            $diferencia = $dto->cantidadContada - $existencia->cantidad_fisica;
            $datosAjuste = [
                'id_comercio' => $dto->idComercio,
                'id_existencia' => $existencia->id_existencia,
                'id_motivo_ajuste' => MotivoAjuste::query()->where('codigo', $dto->motivoAjuste)->value('id_motivo_ajuste'),
                'id_usuario' => $idUsuario,
                'cantidad_contada' => $dto->cantidadContada,
                'cantidad_sistema' => $existencia->cantidad_fisica,
                'justificacion' => $dto->justificacion,
                'created_at' => Carbon::now(),
            ];

            if ($diferencia === 0) {
                return AjusteInventario::query()->create($datosAjuste);
            }

            if ($dto->cantidadContada < $existencia->cantidad_reservada) {
                throw ErrorInventarioException::ajusteBajoComprometido();
            }

            if ($dto->idAutorizacion === null) {
                throw ErrorInventarioException::datosInvalidos([
                    'id_autorizacion' => 'Un ajuste con diferencia requiere la autorización del Administrador del comercio.',
                ]);
            }

            $autorizacion = $this->autorizaciones->verificar(
                $dto->idAutorizacion, 'ajuste', $dto->idComercio, $dto->idVariacion, [$dto->idUbicacion], abs($diferencia),
            );

            $disponibleAntes = $existencia->disponible();
            $totalAntes = $this->existencias->disponibleTotal($dto->idComercio, $dto->idVariacion);
            $costo = $this->kardex->costoSalida($existencia, abs($diferencia));

            if ($diferencia > 0) {
                $existencia->aplicarEntrada($diferencia, $existencia->costo_promedio);
            } else {
                $existencia->disminuirFisica(-$diferencia);
            }

            $this->existencias->guardar($existencia);

            $movimiento = $this->kardex->registrar($existencia, new DatosMovimiento(
                TipoMovimiento::Ajuste,
                $diferencia > 0 ? EfectoStock::Aumenta : EfectoStock::Disminuye,
                abs($diferencia),
                $costo,
                $idUsuario,
                "Ajuste por {$dto->motivoAjuste}: {$dto->justificacion}",
                null,
                $autorizacion['id_autorizador'],
                Carbon::parse($autorizacion['fecha_autorizacion']),
            ));

            $this->autorizaciones->marcarUsada($dto->idAutorizacion);
            $this->publicador->notificarCambio($existencia, $disponibleAntes, $totalAntes);

            return AjusteInventario::query()->create($datosAjuste + ['id_movimiento' => $movimiento->id_movimiento]);
        });

        $this->nucleo->registrarAuditoria('ajuste_inventario', 'ajustes_inventario', $ajuste->id_ajuste, [
            'modulo' => 'M4',
            'id_comercio' => $ajuste->id_comercio,
            'diferencia' => $ajuste->diferencia($ajuste->cantidad_sistema),
            'id_movimiento' => $ajuste->id_movimiento,
        ]);

        return $ajuste;
    }
}
