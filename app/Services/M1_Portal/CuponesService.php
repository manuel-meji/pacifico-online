<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoAccesibleException;
use App\Contracts\M1_Portal\Exceptions\CuponDuplicadoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M1_Portal\Cupon;
use App\Models\M1_Portal\CuponUso;
use App\Services\Nucleo\ContextoComercio;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cupones de descuento (contrato M1 1.5.0, SC-11). M1 es el dueño de los
 * datos; M5 los valida en el checkout y registra su uso al confirmar el
 * pedido, por la interfaz PHP de M1 (ComercialPortalContract).
 *
 * - Cada comercio crea cupones para sus productos; la administración crea
 *   los globales de la plataforma (id_comercio null).
 * - Dos tipos: porcentaje o monto fijo en colones.
 * - Un cupón global descuenta sobre todo el subtotal. Uno de comercio
 *   descuenta solo sobre el subtotal de los productos de ese comercio.
 * - Fecha y hora de inicio y de fin obligatorias; monto mínimo de compra,
 *   límite de usos total y límite por cliente opcionales.
 * - El código es único en toda la plataforma y se guarda en mayúsculas.
 * - Un cupón no se edita: se desactiva y se crea otro.
 * - El descuento va a 2 decimales y nunca supera la base sobre la que aplica.
 */
class CuponesService
{
    private const LARGO_MINIMO_CODIGO = 3;

    private const LARGO_CODIGO = 50;

    private const LARGO_DESCRIPCION = 255;

    private const MONTO_MAXIMO = 99999999.99;

    private const LIMITE_MAXIMO = 2147483647;

    public function __construct(private ContextoComercio $contexto) {}

    // ---------------------------------------------------------------
    // Comercio
    // ---------------------------------------------------------------

    /**
     * El comercio de la sesión crea un cupón para sus productos.
     *
     * @param  array<string, mixed>  $datos  Esquema DatosCupon.
     * @return array<string, mixed> Esquema Cupon.
     *
     * @throws DatosInvalidosException ERR-005
     * @throws CuponDuplicadoException ERR-M1-007
     */
    public function crearDelComercio(array $datos, int $idUsuario): array
    {
        return $this->crear($datos, $this->comercioDeSesion(), $idUsuario);
    }

    /**
     * Cupones del comercio de la sesión, del más reciente al más antiguo.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005 si el estado no es válido.
     */
    public function listarDelComercio(?string $estado): array
    {
        $this->comercioDeSesion();

        return $this->listar($estado, null, false);
    }

    // ---------------------------------------------------------------
    // Administración
    // ---------------------------------------------------------------

    /**
     * La administración crea un cupón global de la plataforma.
     *
     * @param  array<string, mixed>  $datos  Esquema DatosCupon.
     * @return array<string, mixed> Esquema Cupon.
     *
     * @throws DatosInvalidosException ERR-005
     * @throws CuponDuplicadoException ERR-M1-007
     */
    public function crearGlobal(array $datos, int $idUsuario): array
    {
        return $this->crear($datos, null, $idUsuario);
    }

    /**
     * Todos los cupones. $soloGlobales deja solo los de la plataforma;
     * $idComercio, los de ese comercio.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function listarAdministracion(?string $estado, ?int $idComercio, bool $soloGlobales = false): array
    {
        return $this->listar($estado, $idComercio, $soloGlobales);
    }

    // ---------------------------------------------------------------
    // Comercio y administración
    // ---------------------------------------------------------------

    /**
     * Desactiva un cupón del ámbito actual (el comercio, los suyos; la
     * administración, cualquiera). Deja de aceptarse en el checkout y no se
     * reactiva. Desactivar uno ya desactivado no cambia nada.
     *
     * @return array<string, mixed> Esquema Cupon.
     *
     * @throws RecursoNoEncontradoException ERR-004 si no existe o es de otro comercio.
     */
    public function desactivar(int $idCupon): array
    {
        $cupon = Cupon::query()->find($idCupon) ?? throw new RecursoNoEncontradoException('El cupón no existe.');

        if ($cupon->activo) {
            $cupon->forceFill(['activo' => false])->save();
        }

        return $cupon->aRespuesta(Carbon::now());
    }

    // ---------------------------------------------------------------
    // Checkout (M5)
    // ---------------------------------------------------------------

    /**
     * Indica si el cupón aplica al carrito y cuánto descuenta. No registra el
     * uso: eso lo hace registrarUso al confirmarse el pedido.
     *
     * Un cupón que no aplica responde valido=false con el motivo; solo los
     * parámetros mal formados lanzan ERR-005.
     *
     * @param  list<int>  $idsComercios  Comercios presentes en el carrito.
     * @param  array<int, float|int>  $subtotalesPorComercio  Subtotal de cada comercio del carrito, por id_comercio.
     * @return array{valido: bool, id_cupon: int|null, id_comercio: int|null, tipo_descuento: string|null, valor: float|null, monto_descuento: float, motivo: string|null, mensaje: string} Esquema ResultadoCupon.
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function validar(string $codigo, int $idCliente, float $subtotal, array $idsComercios = [], array $subtotalesPorComercio = []): array
    {
        $codigo = mb_strtoupper(trim($codigo));
        $errores = [];

        if ($codigo === '' || mb_strlen($codigo) > self::LARGO_CODIGO) {
            $errores['codigo'] = 'Es obligatorio y debe tener entre 1 y '.self::LARGO_CODIGO.' caracteres.';
        }

        if ($idCliente < 1) {
            $errores['id_cliente'] = 'Debe ser un entero mayor o igual a 1.';
        }

        if (! is_finite($subtotal) || $subtotal < 0) {
            $errores['subtotal'] = 'Debe ser un número mayor o igual a 0.';
        }

        if (! array_is_list($idsComercios) || array_filter($idsComercios, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            $errores['ids_comercios'] = 'Debe ser una lista de enteros mayores o iguales a 1.';
        }

        foreach ($subtotalesPorComercio as $idComercio => $monto) {
            if (! is_int($idComercio) || $idComercio < 1 || (! is_int($monto) && ! is_float($monto)) || ! is_finite((float) $monto) || $monto < 0) {
                $errores['subtotales_por_comercio'] = 'Cada comercio (entero mayor o igual a 1) lleva su subtotal, un número mayor o igual a 0.';
                break;
            }
        }

        $this->exigirValidos($errores);

        $ahora = Carbon::now();
        $cupon = $this->comoSistema(fn (): ?Cupon => Cupon::query()->where('codigo', $codigo)->first());

        if ($cupon === null) {
            return $this->rechazo('no_disponible');
        }

        $motivo = $this->comoSistema(fn (): ?string => $this->motivoNoUsable($cupon, $idCliente, $ahora));

        if ($motivo !== null) {
            return $this->rechazo($motivo);
        }

        if ($cupon->esGlobal()) {
            $base = $subtotal;
        } else {
            $comercios = array_values(array_unique([...$idsComercios, ...array_keys($subtotalesPorComercio)]));

            if (! in_array($cupon->id_comercio, $comercios, true)) {
                return $this->rechazo('comercio_no_aplica');
            }

            if (array_key_exists($cupon->id_comercio, $subtotalesPorComercio)) {
                $base = (float) $subtotalesPorComercio[$cupon->id_comercio];
            } elseif ($comercios === [$cupon->id_comercio]) {
                $base = $subtotal;
            } else {
                // Carrito de varios comercios sin el desglose: no se sabe cuánto es de este comercio.
                return $this->rechazo('requiere_subtotal_comercio');
            }
        }

        if ($cupon->monto_minimo_compra !== null && $base < (float) $cupon->monto_minimo_compra) {
            return $this->rechazo('monto_minimo', 'El cupón aplica a compras desde ₡'.$this->colones((float) $cupon->monto_minimo_compra).'.');
        }

        $descuento = $cupon->descuentoSobre($base);

        if ($descuento <= 0) {
            return $this->rechazo('sin_descuento');
        }

        return [
            'valido' => true,
            'id_cupon' => $cupon->id_cupon,
            'id_comercio' => $cupon->id_comercio,
            'tipo_descuento' => $cupon->tipo_descuento,
            'valor' => (float) $cupon->valor,
            'monto_descuento' => $descuento,
            'motivo' => null,
            'mensaje' => $cupon->tipo_descuento === Cupon::TIPO_PORCENTAJE
                ? 'Cupón de '.rtrim(rtrim(number_format((float) $cupon->valor, 2, '.', ''), '0'), '.').' % aplicado.'
                : 'Cupón de ₡'.$this->colones((float) $cupon->valor).' aplicado.',
        ];
    }

    /**
     * Valida un cupón desde el cuerpo de POST /cupones/validar (esquema
     * ValidarCuponRequest). El cliente es el de la sesión; si el cuerpo trae
     * id_cliente, debe ser el mismo.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed> Esquema ResultadoCupon.
     *
     * @throws DatosInvalidosException ERR-005
     * @throws ClienteNoAccesibleException ERR-003 si id_cliente no es el de la sesión.
     */
    public function validarDesdeCheckout(array $datos, int $idClienteDeSesion): array
    {
        $errores = [];

        $codigo = $datos['codigo'] ?? null;
        if (! is_string($codigo)) {
            $errores['codigo'] = 'Es obligatorio y debe ser texto.';
        }

        $subtotal = $datos['subtotal'] ?? null;
        if (! is_int($subtotal) && ! is_float($subtotal)) {
            $errores['subtotal'] = 'Es obligatorio y debe ser un número mayor o igual a 0.';
        }

        $idCliente = $datos['id_cliente'] ?? null;
        if ($idCliente !== null && ! is_int($idCliente)) {
            $errores['id_cliente'] = 'Debe ser un entero mayor o igual a 1.';
        }

        $idsComercios = $datos['ids_comercios'] ?? [];
        if (! is_array($idsComercios)) {
            $errores['ids_comercios'] = 'Debe ser una lista de enteros mayores o iguales a 1.';
        }

        $subtotales = [];
        $lista = $datos['subtotales_por_comercio'] ?? [];
        if (! is_array($lista) || ! array_is_list($lista)) {
            $errores['subtotales_por_comercio'] = 'Debe ser una lista de {id_comercio, subtotal}.';
        } else {
            foreach ($lista as $fila) {
                $idComercio = is_array($fila) ? ($fila['id_comercio'] ?? null) : null;
                $monto = is_array($fila) ? ($fila['subtotal'] ?? null) : null;

                if (! is_int($idComercio) || (! is_int($monto) && ! is_float($monto)) || array_key_exists($idComercio, $subtotales)) {
                    $errores['subtotales_por_comercio'] = 'Debe ser una lista de {id_comercio, subtotal}, sin comercios repetidos.';
                    break;
                }

                $subtotales[$idComercio] = $monto;
            }
        }

        $this->exigirValidos($errores);

        if ($idCliente !== null && $idCliente !== $idClienteDeSesion) {
            throw new ClienteNoAccesibleException;
        }

        return $this->validar($codigo, $idClienteDeSesion, (float) $subtotal, $idsComercios, $subtotales);
    }

    /**
     * Registra el uso del cupón en un pedido confirmado y suma el uso. Es
     * idempotente por pedido: repetir la llamada con el mismo pedido responde
     * true sin sumar otro uso. Responde false si el cupón no existe o ya no se
     * puede usar (desactivado, fuera de fechas, agotado o límite del cliente).
     *
     * El cupón se bloquea dentro de la transacción para que dos pedidos
     * simultáneos no superen el límite de usos.
     *
     * @throws DatosInvalidosException ERR-005 si un identificador es menor que 1.
     */
    public function registrarUso(int $idCupon, int $idCliente, int $idPedido): bool
    {
        $errores = [];

        foreach (['id_cupon' => $idCupon, 'id_cliente' => $idCliente, 'id_pedido' => $idPedido] as $campo => $valor) {
            if ($valor < 1) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 1.';
            }
        }

        $this->exigirValidos($errores);

        return $this->comoSistema(fn (): bool => DB::transaction(function () use ($idCupon, $idCliente, $idPedido): bool {
            $cupon = Cupon::query()->lockForUpdate()->find($idCupon);

            if ($cupon === null) {
                return false;
            }

            if (CuponUso::query()->where('id_cupon', $idCupon)->where('id_pedido', $idPedido)->exists()) {
                return true;
            }

            if ($this->motivoNoUsable($cupon, $idCliente, Carbon::now()) !== null) {
                return false;
            }

            CuponUso::query()->create(['id_cupon' => $idCupon, 'id_cliente' => $idCliente, 'id_pedido' => $idPedido]);
            $cupon->increment('usos_actuales');

            return true;
        }));
    }

    // ---------------------------------------------------------------
    // Internos
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function crear(array $datos, ?int $idComercio, int $idUsuario): array
    {
        $campos = $this->validarDatos($datos);

        // El código es único en toda la plataforma: se revisa sin el filtro por comercio.
        if (Cupon::query()->withoutGlobalScopes()->where('codigo', $campos['codigo'])->exists()) {
            throw new CuponDuplicadoException;
        }

        try {
            $cupon = Cupon::query()->create([
                'id_comercio' => $idComercio,
                ...$campos,
                'id_usuario_crea' => $idUsuario,
            ]);
        } catch (UniqueConstraintViolationException $error) {
            // Dos creaciones simultáneas con el mismo código: la base deja pasar solo una.
            throw new CuponDuplicadoException(null, $error);
        }

        return $cupon->aRespuesta(Carbon::now());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listar(?string $estado, ?int $idComercio, bool $soloGlobales): array
    {
        if ($estado !== null && ! in_array($estado, Cupon::ESTADOS, true)) {
            $this->exigirValidos(['estado' => 'Debe ser '.implode(', ', Cupon::ESTADOS).'.']);
        }

        if ($idComercio !== null && $idComercio < 1) {
            $this->exigirValidos(['id_comercio' => 'Debe ser un entero mayor o igual a 1.']);
        }

        $ahora = Carbon::now();

        $cupones = Cupon::query()
            ->when($soloGlobales, fn (Builder $consulta) => $consulta->whereNull('id_comercio'))
            ->when(! $soloGlobales && $idComercio !== null, fn (Builder $consulta) => $consulta->where('id_comercio', $idComercio))
            ->orderByDesc('id_cupon')
            ->get()
            ->map(fn (Cupon $cupon): array => $cupon->aRespuesta($ahora))
            ->when($estado !== null, fn ($lista) => $lista->where('estado', $estado));

        return $cupones->values()->all();
    }

    /**
     * Motivo por el que el cupón no se puede usar ahora, o null si se puede.
     * No revisa el carrito (comercio, monto mínimo): eso es de validar().
     */
    private function motivoNoUsable(Cupon $cupon, int $idCliente, CarbonInterface $ahora): ?string
    {
        if (! $cupon->activo) {
            return 'no_disponible';
        }

        if ($cupon->fecha_inicio->greaterThan($ahora)) {
            return 'no_iniciado';
        }

        if ($cupon->fecha_fin->lessThan($ahora)) {
            return 'vencido';
        }

        if ($cupon->agotado()) {
            return 'agotado';
        }

        $usosDelCliente = CuponUso::query()->where('id_cupon', $cupon->id_cupon)->where('id_cliente', $idCliente)->count();

        return $usosDelCliente >= $cupon->limite_usos_por_cliente ? 'limite_cliente' : null;
    }

    /**
     * Respuesta de un cupón que no aplica. Un código inexistente y uno
     * desactivado responden igual, para no revelar qué códigos existen.
     *
     * @return array{valido: bool, id_cupon: int|null, id_comercio: int|null, tipo_descuento: string|null, valor: float|null, monto_descuento: float, motivo: string|null, mensaje: string}
     */
    private function rechazo(string $motivo, ?string $mensaje = null): array
    {
        return [
            'valido' => false,
            'id_cupon' => null,
            'id_comercio' => null,
            'tipo_descuento' => null,
            'valor' => null,
            'monto_descuento' => 0.0,
            'motivo' => $motivo,
            'mensaje' => $mensaje ?? match ($motivo) {
                'no_iniciado' => 'El cupón todavía no está vigente.',
                'vencido' => 'El cupón ya venció.',
                'agotado' => 'El cupón alcanzó su límite de usos.',
                'limite_cliente' => 'Ya usó este cupón el máximo de veces permitido.',
                'comercio_no_aplica' => 'El cupón no aplica a los productos del carrito.',
                'requiere_subtotal_comercio' => 'El cupón solo aplica a los productos de un comercio y falta el subtotal de ese comercio.',
                'sin_descuento' => 'El cupón no genera descuento para este carrito.',
                default => 'El cupón no existe o no está disponible.',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{codigo: string, descripcion: string|null, tipo_descuento: string, valor: float, monto_minimo_compra: float|null, fecha_inicio: CarbonInterface, fecha_fin: CarbonInterface, limite_usos_total: int|null, limite_usos_por_cliente: int}
     *
     * @throws DatosInvalidosException ERR-005 con el detalle por campo.
     */
    private function validarDatos(array $datos): array
    {
        $errores = [];

        $codigo = is_string($datos['codigo'] ?? null) ? mb_strtoupper(trim($datos['codigo'])) : '';
        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{'.(self::LARGO_MINIMO_CODIGO - 1).','.(self::LARGO_CODIGO - 1).'}$/', $codigo)) {
            $errores['codigo'] = 'Es obligatorio: de '.self::LARGO_MINIMO_CODIGO.' a '.self::LARGO_CODIGO.' letras sin tilde, números, guion o guion bajo, sin espacios.';
        }

        $descripcion = $datos['descripcion'] ?? null;
        if (is_string($descripcion)) {
            $descripcion = trim($descripcion) === '' ? null : trim($descripcion);
        }
        if ($descripcion !== null && (! is_string($descripcion) || mb_strlen($descripcion) > self::LARGO_DESCRIPCION)) {
            $errores['descripcion'] = 'Debe ser texto de hasta '.self::LARGO_DESCRIPCION.' caracteres.';
        }

        $tipo = $datos['tipo_descuento'] ?? null;
        if (! in_array($tipo, [Cupon::TIPO_PORCENTAJE, Cupon::TIPO_MONTO_FIJO], true)) {
            $errores['tipo_descuento'] = 'Debe ser porcentaje o monto_fijo.';
        }

        $valor = $datos['valor'] ?? null;
        if (! $this->esMonto($valor) || $valor <= 0) {
            $errores['valor'] = 'Es obligatorio: un número mayor que 0 con máximo 2 decimales.';
        } elseif ($tipo === Cupon::TIPO_PORCENTAJE && $valor >= 100) {
            $errores['valor'] = 'Un porcentaje debe ser menor que 100.';
        }

        $minimo = $datos['monto_minimo_compra'] ?? null;
        if ($minimo !== null && ! $this->esMonto($minimo)) {
            $errores['monto_minimo_compra'] = 'Debe ser un número mayor o igual a 0 con máximo 2 decimales, o null si no hay mínimo.';
        }

        $inicio = $this->fechaHora($datos['fecha_inicio'] ?? null);
        $fin = $this->fechaHora($datos['fecha_fin'] ?? null);
        if ($inicio === null) {
            $errores['fecha_inicio'] = 'Es obligatoria, con fecha y hora (ISO 8601).';
        }
        if ($fin === null) {
            $errores['fecha_fin'] = 'Es obligatoria, con fecha y hora (ISO 8601).';
        } elseif ($inicio !== null && $fin->lessThanOrEqualTo($inicio)) {
            $errores['fecha_fin'] = 'Debe ser posterior a fecha_inicio.';
        } elseif ($fin->lessThanOrEqualTo(Carbon::now())) {
            $errores['fecha_fin'] = 'Debe ser posterior al momento actual.';
        }

        $limiteTotal = $datos['limite_usos_total'] ?? null;
        if ($limiteTotal !== null && ! $this->esLimite($limiteTotal)) {
            $errores['limite_usos_total'] = 'Debe ser un entero mayor o igual a 1, o null si no hay límite.';
        }

        $limiteCliente = $datos['limite_usos_por_cliente'] ?? 1;
        if (! $this->esLimite($limiteCliente)) {
            $errores['limite_usos_por_cliente'] = 'Debe ser un entero mayor o igual a 1.';
        } elseif ($this->esLimite($limiteTotal) && $limiteCliente > $limiteTotal) {
            $errores['limite_usos_por_cliente'] = 'No puede ser mayor que limite_usos_total.';
        }

        $this->exigirValidos($errores);

        return [
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'tipo_descuento' => $tipo,
            'valor' => (float) $valor,
            'monto_minimo_compra' => $minimo === null ? null : (float) $minimo,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'limite_usos_total' => $limiteTotal,
            'limite_usos_por_cliente' => $limiteCliente,
        ];
    }

    /**
     * Número mayor o igual a 0, con máximo 2 decimales y dentro de la columna decimal(10,2).
     */
    private function esMonto(mixed $valor): bool
    {
        return (is_int($valor) || is_float($valor))
            && is_finite((float) $valor)
            && $valor >= 0
            && $valor <= self::MONTO_MAXIMO
            && abs(round((float) $valor, 2) - (float) $valor) < 0.000001;
    }

    private function esLimite(mixed $valor): bool
    {
        return is_int($valor) && $valor >= 1 && $valor <= self::LIMITE_MAXIMO;
    }

    private function colones(float $monto): string
    {
        return number_format($monto, fmod($monto, 1.0) === 0.0 ? 0 : 2, '.', ' ');
    }

    /**
     * Lectura o escritura sin el filtro por comercio: quien valida o usa un
     * cupón es una persona compradora, no el comercio dueño.
     *
     * @template T
     *
     * @param  callable(): T  $operacion
     * @return T
     */
    private function comoSistema(callable $operacion): mixed
    {
        return $this->contexto->ejecutarComoSistema($operacion(...));
    }

    private function comercioDeSesion(): int
    {
        return $this->contexto->idActual() ?? throw ErrorNucleoException::recursoNoAccesible();
    }

    private function fechaHora(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $valor)) {
            return null;
        }

        // Se pasa a la zona de la aplicación (UTC) antes de guardar: las columnas timestamp no guardan el desfase.
        try {
            return Carbon::parse($valor)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $errores
     */
    private function exigirValidos(array $errores): void
    {
        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }
    }
}
