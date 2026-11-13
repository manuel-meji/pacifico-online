<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class LibroPlataformaService
{
    public function __construct(
        private readonly ValidadorPartidaDoble $validador,
        private readonly ContextoComercio $contexto,
    ) {}

    /**
     * La transacción y la idempotencia pertenecen al servicio que invoca el registro.
     *
     * @param  list<array{codigo: string, debe: int, haber: int}>  $movimientos
     */
    public function registrar(
        string $eventoId,
        string $tipoEvento,
        string $tipo,
        int $pagoId,
        int $pedidoId,
        ?int $comercioId,
        ?int $subpedidoId,
        string $fecha,
        array $movimientos,
    ): int {
        if (! $this->contexto->esModoSistema()) {
            throw new LogicException('El libro de plataforma requiere modo sistema.');
        }

        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('El registro de plataforma requiere una transacción activa del invocador.');
        }

        if (! Str::isUuid($eventoId)
            || $pagoId <= 0
            || $pedidoId <= 0
            || ($comercioId !== null && $comercioId <= 0)
            || ($subpedidoId !== null && $subpedidoId <= 0)) {
            throw new InvalidArgumentException('El evento y los identificadores del asiento de plataforma deben ser válidos.');
        }

        if (trim($tipoEvento) === '' || mb_strlen($tipoEvento) > 80
            || trim($tipo) === '' || mb_strlen($tipo) > 40
            || trim($fecha) === '') {
            throw new InvalidArgumentException('El tipo de evento, el tipo de asiento y la fecha deben ser válidos.');
        }

        $fechaNormalizada = Carbon::parse($fecha)->utc()->format('Y-m-d H:i:s');

        if (! array_is_list($movimientos)) {
            throw new InvalidArgumentException('Los movimientos de plataforma deben ser una lista.');
        }

        $movimientosDecimales = [];
        $codigos = [];

        foreach ($movimientos as $movimiento) {
            if (! is_array($movimiento)
                || ! isset($movimiento['codigo'], $movimiento['debe'], $movimiento['haber'])
                || ! is_string($movimiento['codigo'])
                || trim($movimiento['codigo']) === ''
                || ! is_int($movimiento['debe'])
                || ! is_int($movimiento['haber'])) {
                throw new InvalidArgumentException('Cada movimiento requiere un código de cuenta e importes enteros en céntimos.');
            }

            $codigos[] = $movimiento['codigo'];
            $movimientosDecimales[] = [
                'debe' => $this->aDecimal($movimiento['debe']),
                'haber' => $this->aDecimal($movimiento['haber']),
            ];
        }

        $this->validador->validar($movimientosDecimales);

        $codigos = array_values(array_unique($codigos));
        sort($codigos, SORT_STRING);

        $cuentas = DB::table('cuentas_plataforma_m7')
            ->whereIn('codigo', $codigos)
            ->orderBy('codigo')
            ->lockForUpdate()
            ->get()
            ->keyBy('codigo');

        foreach ($codigos as $codigo) {
            $cuenta = $cuentas->get($codigo);

            if ($cuenta === null) {
                throw new LogicException("La cuenta de plataforma {$codigo} no existe.");
            }

            if (! $cuenta->activa) {
                throw new LogicException("La cuenta de plataforma {$codigo} está inactiva.");
            }
        }

        $ahora = now();
        $asientoId = (int) DB::table('asientos_plataforma_m7')->insertGetId([
            'id_pago' => $pagoId,
            'id_pedido' => $pedidoId,
            'id_comercio' => $comercioId,
            'id_subpedido' => $subpedidoId,
            'evento_id' => $eventoId,
            'tipo_evento' => $tipoEvento,
            'tipo' => $tipo,
            'fecha' => $fechaNormalizada,
            'estado' => 'REGISTRADO',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], 'id_asiento');

        $detalles = [];

        foreach ($movimientos as $indice => $movimiento) {
            $detalles[] = [
                'id_asiento' => $asientoId,
                'id_cuenta' => (int) $cuentas->get($movimiento['codigo'])->id_cuenta,
                'numero_linea' => $indice + 1,
                'debe' => $movimientosDecimales[$indice]['debe'],
                'haber' => $movimientosDecimales[$indice]['haber'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('detalles_plataforma_m7')->insert($detalles);

        return $asientoId;
    }

    private function aDecimal(int $centimos): string
    {
        if ($centimos < 0) {
            throw new InvalidArgumentException('Los importes en céntimos no pueden ser negativos.');
        }

        return intdiv($centimos, 100).'.'.str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT);
    }
}
