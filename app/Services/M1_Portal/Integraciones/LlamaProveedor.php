<?php

namespace App\Services\M1_Portal\Integraciones;

use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\PortalPublicoException;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Comportamiento común de los adaptadores de M1: llama al contrato del
 * proveedor, limita la espera (RNF-M1-04) y traduce sus errores a las
 * excepciones del contrato de M1.
 *
 * Los proveedores se llaman por su interfaz PHP dentro del mismo proceso, así
 * que la llamada no se puede interrumpir a la mitad: si tarda más que el
 * tiempo máximo, su respuesta se descarta y se informa ERR-006, igual que si
 * el proveedor no hubiera respondido.
 */
trait LlamaProveedor
{
    /**
     * Segundos de espera máxima por proveedor (RNF-M1-04: 3 s).
     */
    private float $tiempoMaximo = 3;

    private function cargarTiempoMaximo(): void
    {
        $this->tiempoMaximo = (float) config('integraciones.m1.tiempo_maximo', 3);
    }

    /**
     * @template T
     *
     * @param  string  $modulo  M2, M3, M4 o M5, para el mensaje y la bitácora.
     * @param  Closure(): T  $operacion
     * @param  (Closure(string|null, Throwable): ?PortalPublicoException)|null  $traducir  Excepción de M1 para un código del proveedor, o null para tratarlo como no disponible.
     * @return T
     *
     * @throws PortalPublicoException
     */
    private function llamar(string $modulo, Closure $operacion, ?Closure $traducir = null): mixed
    {
        $inicio = hrtime(true);

        try {
            $resultado = $operacion();
        } catch (PortalPublicoException $excepcion) {
            throw $excepcion;
        } catch (Throwable $error) {
            $traducida = $traducir?->__invoke($this->codigoDe($error), $error);

            if ($traducida !== null) {
                throw $traducida;
            }

            Log::warning("M1: {$modulo} no respondió correctamente.", ['error' => $error->getMessage()]);

            throw new DependenciaNoDisponibleException("{$modulo} no está disponible en este momento.", $error);
        }

        $segundos = (hrtime(true) - $inicio) / 1_000_000_000;

        if ($segundos > $this->tiempoMaximo) {
            Log::warning("M1: {$modulo} superó el tiempo máximo de espera.", ['segundos' => round($segundos, 3)]);

            throw new DependenciaNoDisponibleException("{$modulo} no respondió a tiempo.");
        }

        return $resultado;
    }

    /**
     * Código de error del proveedor con el formato del núcleo (ERR-004, ERR-005,
     * ERR-006, ERR-M5-001…), venga como propiedad codigo o como método codigo().
     */
    private function codigoDe(Throwable $error): ?string
    {
        if (isset($error->codigo) && is_string($error->codigo)) {
            return $error->codigo;
        }

        if (method_exists($error, 'codigo')) {
            $codigo = $error->codigo();

            return is_string($codigo) ? $codigo : null;
        }

        return null;
    }

    /**
     * Detalles campo → mensaje del error del proveedor, si los trae.
     *
     * @return array<string, string>
     */
    private function detallesDe(Throwable $error): array
    {
        $detalles = isset($error->detalles) && is_array($error->detalles) ? $error->detalles : [];

        return array_map(fn (mixed $mensaje): string => (string) $mensaje, $detalles);
    }
}
