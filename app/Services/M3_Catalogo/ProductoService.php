<?php

declare(strict_types=1);

namespace App\Services\M3_Catalogo;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Events\M3_Catalogo\ProductoPublicado;
use App\Exceptions\M3_Catalogo\ErrorCatalogoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M3_Catalogo\AtributoVariacion;
use App\Models\M3_Catalogo\Categoria;
use App\Models\M3_Catalogo\ImagenProducto;
use App\Models\M3_Catalogo\Marca;
use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoVariacion;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Escritura del catálogo de M3: alta de categorías, marcas, productos,
 * variaciones e imágenes, relacionados y publicación.
 *
 * Toda la lógica de negocio vive aquí; los controladores solo reciben, llaman
 * y responden. El comercio sale siempre de la sesión (ContextoComercio), nunca
 * del cuerpo de la petición (RN-12).
 */
class ProductoService
{
    private const MAX_IMAGENES = 8;

    public function __construct(
        private readonly ComerciosServiceInterface $comercios,
        private readonly ContextoComercio $contexto,
    ) {}

    /**
     * @param  array{nombre: string, descripcion?: ?string, id_categoria_padre?: ?int}  $datos
     * @return array<string, mixed>
     */
    public function crearCategoria(array $datos): array
    {
        $categoria = (new Categoria)->forceFill([
            'id_categoria_padre' => $datos['id_categoria_padre'] ?? null,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
        ]);
        $categoria->save();

        return [
            'id_categoria' => (int) $categoria->getKey(),
            'nombre' => $categoria->nombre,
            'descripcion' => $categoria->descripcion,
            'id_categoria_padre' => $categoria->id_categoria_padre === null ? null : (int) $categoria->id_categoria_padre,
        ];
    }

    /**
     * @param  array{nombre: string, logotipo_url?: ?string, destacada?: bool}  $datos
     * @return array<string, mixed>
     */
    public function crearMarca(array $datos): array
    {
        $marca = (new Marca)->forceFill([
            'nombre' => $datos['nombre'],
            'logotipo_url' => $datos['logotipo_url'] ?? null,
            'destacada' => (bool) ($datos['destacada'] ?? false),
        ]);
        $marca->save();

        return [
            'id_marca' => (int) $marca->getKey(),
            'nombre' => $marca->nombre,
            'logotipo_url' => $marca->logotipo_url,
            'destacada' => (bool) $marca->destacada,
        ];
    }

    /**
     * Crea el producto en borrador. El comercio debe estar activo en M2.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function crearProducto(array $datos): array
    {
        $idComercio = $this->idComercioDeLaSesion();
        if (
            array_key_exists('id_comercio', $datos)
            && (int) $datos['id_comercio'] !== $idComercio
        ) {
            throw ErrorNucleoException::rolSinPermiso();
        }
        $this->exigirComercioActivo($idComercio);

        $producto = (new Producto)->forceFill([
            'id_comercio' => $idComercio,
            'id_categoria' => $datos['id_categoria'],
            'id_marca' => $datos['id_marca'] ?? null,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'video_url' => $datos['video_url'] ?? null,
            'destacado' => (bool) ($datos['destacado'] ?? false),
            'estado_producto' => 'borrador',
        ]);
        $producto->save();

        return $this->productoComoArray($producto);
    }

    /**
     * Agrega una variación a un producto propio. SKU único por comercio (RN-M3-01).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function crearVariacion(int $idProducto, array $datos): array
    {
        $producto = $this->productoDelComercio($idProducto);
        $idComercio = (int) $producto->id_comercio;
        $sku = (string) $datos['sku'];
        $atributos = $datos['atributos'] ?? [];

        $yaExiste = ProductoVariacion::query()
            ->where('id_comercio', $idComercio)
            ->where('sku', $sku)
            ->exists();

        if ($yaExiste) {
            throw ErrorCatalogoException::skuDuplicado($sku);
        }

        try {
            $variacion = DB::transaction(function () use ($producto, $idComercio, $sku, $datos, $atributos): ProductoVariacion {
                $variacion = (new ProductoVariacion)->forceFill([
                    'id_producto' => $producto->getKey(),
                    'id_comercio' => $idComercio,
                    'sku' => $sku,
                    'precio' => number_format((float) $datos['precio'], 2, '.', ''),
                    'tarifa_impuesto' => number_format((float) $datos['tarifa_impuesto'], 2, '.', ''),
                    'codigo_barras' => $datos['codigo_barras'] ?? null,
                    'codigo_fabricante' => $datos['codigo_fabricante'] ?? null,
                    'disponible' => (bool) ($datos['disponible'] ?? true),
                ]);
                $variacion->save();

                foreach ($atributos as $atributo) {
                    (new AtributoVariacion)->forceFill([
                        'id_variacion' => $variacion->getKey(),
                        'tipo_atributo' => $atributo['tipo_atributo'],
                        'valor' => $atributo['valor'],
                    ])->save();
                }

                return $variacion;
            });
        } catch (UniqueConstraintViolationException) {
            // Otra petición creó el mismo SKU entre la consulta y el guardado.
            throw ErrorCatalogoException::skuDuplicado($sku);
        }

        return [
            'id_variacion' => (int) $variacion->getKey(),
            'id_producto' => (int) $producto->getKey(),
            'sku' => $sku,
            'precio' => (float) $variacion->precio,
            'tarifa_impuesto' => (float) $variacion->tarifa_impuesto,
            'codigo_barras' => $variacion->codigo_barras,
            'codigo_fabricante' => $variacion->codigo_fabricante,
            'disponible' => (bool) $variacion->disponible,
            'atributos' => array_values(array_map(
                fn (array $a): array => ['tipo_atributo' => $a['tipo_atributo'], 'valor' => $a['valor']],
                $atributos
            )),
        ];
    }

    /**
     * Agrega una imagen. Llega como archivo (se guarda con Storage) o como URL.
     * Máximo 8 por producto y el orden no se repite (RN-M3-09).
     *
     * @param  array{archivo?: ?UploadedFile, imagen_url?: ?string, id_variacion?: ?int, orden?: ?int}  $datos
     * @return array<string, mixed>
     */
    public function agregarImagen(int $idProducto, array $datos): array
    {
        $producto = $this->productoDelComercio($idProducto);
        $idProducto = (int) $producto->getKey();

        if (ImagenProducto::query()->where('id_producto', $idProducto)->count() >= self::MAX_IMAGENES) {
            throw ErrorCatalogoException::limiteImagenes();
        }

        $idVariacion = $datos['id_variacion'] ?? null;
        if ($idVariacion !== null) {
            $pertenece = ProductoVariacion::query()
                ->where('id_producto', $idProducto)
                ->whereKey($idVariacion)
                ->exists();

            if (! $pertenece) {
                throw ErrorNucleoException::validacionFallida([
                    'id_variacion' => ['Esa variación no pertenece a este producto.'],
                ]);
            }
        }

        $ocupadas = ImagenProducto::query()
            ->where('id_producto', $idProducto)
            ->pluck('orden')
            ->map(fn ($orden): int => (int) $orden)
            ->all();

        $orden = isset($datos['orden']) ? (int) $datos['orden'] : $this->primerOrdenLibre($ocupadas);

        if (in_array($orden, $ocupadas, true)) {
            throw ErrorNucleoException::validacionFallida([
                'orden' => ['Ya hay una imagen en esa posición.'],
            ]);
        }

        [$url, $formato, $tamano] = $this->guardarArchivo($datos, $producto);

        $imagen = (new ImagenProducto)->forceFill([
            'id_producto' => $idProducto,
            'id_variacion' => $idVariacion,
            'imagen_url' => $url,
            'formato' => $formato,
            'tamano_bytes' => $tamano,
            'orden' => $orden,
        ]);
        $imagen->save();

        return [
            'id_imagen' => (int) $imagen->getKey(),
            'id_producto' => $idProducto,
            'id_variacion' => $idVariacion === null ? null : (int) $idVariacion,
            'imagen_url' => $url,
            'orden' => $orden,
        ];
    }

    /**
     * Relaciona dos productos del mismo comercio (RN-M3-12).
     *
     * @return array{id_producto: int, id_producto_relacionado: int}
     */
    public function asociarRelacionado(int $idProducto, int $idRelacionado): array
    {
        $producto = $this->productoDelComercio($idProducto);
        $idComercio = (int) $producto->id_comercio;

        if ($idRelacionado === (int) $producto->getKey()) {
            throw ErrorCatalogoException::relacionadoInvalido();
        }

        $existeEnElComercio = Producto::query()
            ->where('id_comercio', $idComercio)
            ->whereKey($idRelacionado)
            ->exists();

        if (! $existeEnElComercio) {
            throw ErrorCatalogoException::relacionadoInvalido();
        }

        DB::table('productos_relacionados')->insertOrIgnore([
            'id_producto' => (int) $producto->getKey(),
            'id_producto_relacionado' => $idRelacionado,
            'id_comercio' => $idComercio,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id_producto' => (int) $producto->getKey(),
            'id_producto_relacionado' => $idRelacionado,
        ];
    }

    /**
     * Cambia el estado de publicación y/o destacado. Al pasar a "publicado" exige
     * al menos 1 variación y 1 imagen (RN-M3-09) y despacha un ProductoPublicado
     * por cada variación, después de confirmar la transacción.
     *
     * @param  array{estado_producto?: string, destacado?: bool}  $cambios
     * @return array<string, mixed>
     */
    public function cambiarEstado(int $idProducto, array $cambios): array
    {
        $producto = $this->productoDelComercio($idProducto);

        $estadoActual = (string) $producto->estado_producto;
        $estadoNuevo = $cambios['estado_producto'] ?? $estadoActual;
        $pasaAPublicado = $estadoNuevo === 'publicado' && $estadoActual !== 'publicado';

        if ($pasaAPublicado) {
            $this->exigirComercioActivo((int) $producto->id_comercio);
            $this->exigirListoParaPublicar((int) $producto->getKey());
        }

        DB::transaction(function () use ($producto, $cambios, $estadoNuevo, $pasaAPublicado): void {
            $producto->forceFill(['estado_producto' => $estadoNuevo]);

            if (array_key_exists('destacado', $cambios)) {
                $producto->forceFill(['destacado' => (bool) $cambios['destacado']]);
            }

            $producto->save();

            if ($pasaAPublicado) {
                $variaciones = ProductoVariacion::query()
                    ->where('id_producto', $producto->getKey())
                    ->orderBy('id_variacion')
                    ->pluck('id_variacion');

                foreach ($variaciones as $idVariacion) {
                    ProductoPublicado::dispatch(
                        (int) $producto->id_comercio,
                        (int) $producto->getKey(),
                        (int) $idVariacion
                    );
                }
            }
        });

        return $this->productoComoArray($producto->refresh());
    }

    private function idComercioDeLaSesion(): int
    {
        $idComercio = $this->contexto->idActual();

        if ($idComercio === null) {
            throw ErrorNucleoException::rolSinPermiso();
        }

        return $idComercio;
    }

    /**
     * RN-12: un producto se busca siempre filtrando por el comercio de la sesión.
     * Si es de otro comercio responde 404 (ERR-004), no 403, para no revelar que existe.
     * Cuando Jeaustin entregue delComercio() en el modelo, se puede cambiar esta línea.
     */
    private function productoDelComercio(int $idProducto): Producto
    {
        return Producto::query()
            ->where('id_comercio', $this->idComercioDeLaSesion())
            ->findOrFail($idProducto);
    }

    /**
     * Actualiza exclusivamente la disponibilidad de una variación.
     * La variación debe pertenecer al comercio de la sesión (RN-12).
     *
     * @return array<string, mixed>
     */
    public function actualizarDisponibilidadVariacion(
        int $idVariacion,
        bool $disponible
    ): array {
        $variacion = ProductoVariacion::query()
            ->delComercio($this->idComercioDeLaSesion())
            ->findOrFail($idVariacion);

        $variacion->forceFill([
            'disponible' => $disponible,
        ]);

        $variacion->save();

        return [
            'id_variacion' => (int) $variacion->getKey(),
            'id_producto' => (int) $variacion->id_producto,
            'sku' => $variacion->sku,
            'precio' => (float) $variacion->precio,
            'tarifa_impuesto' => (float) $variacion->tarifa_impuesto,
            'codigo_barras' => $variacion->codigo_barras,
            'codigo_fabricante' => $variacion->codigo_fabricante,
            'disponible' => (bool) $variacion->disponible,
            'atributos' => $variacion->atributos()
                ->get()
                ->map(fn (AtributoVariacion $atributo): array => [
                    'tipo_atributo' => $atributo->tipo_atributo,
                    'valor' => $atributo->valor,
                ])
                ->values()
                ->all(),
        ];
    }

    /** RN-M3-07: un comercio suspendido no crea ni publica productos. */
    private function exigirComercioActivo(int $idComercio): void
    {
        $comercio = $this->comercios->obtenerComercioActivo($idComercio);

        if (($comercio['activo'] ?? false) !== true) {
            throw ErrorCatalogoException::comercioSuspendido();
        }
    }

    /** RN-M3-09: para publicar se necesita al menos 1 variación y 1 imagen. */
    private function exigirListoParaPublicar(int $idProducto): void
    {
        $faltantes = [];

        if (! ProductoVariacion::query()->where('id_producto', $idProducto)->exists()) {
            $faltantes['variaciones'] = ['Agrega al menos una variación antes de publicar.'];
        }

        if (! ImagenProducto::query()->where('id_producto', $idProducto)->exists()) {
            $faltantes['imagenes'] = ['Agrega al menos una imagen antes de publicar.'];
        }

        if ($faltantes !== []) {
            throw ErrorNucleoException::validacionFallida($faltantes);
        }
    }

    /** @param  list<int>  $ocupadas */
    private function primerOrdenLibre(array $ocupadas): int
    {
        for ($orden = 1; $orden <= self::MAX_IMAGENES; $orden++) {
            if (! in_array($orden, $ocupadas, true)) {
                return $orden;
            }
        }

        throw ErrorCatalogoException::limiteImagenes();
    }

    /**
     * Guarda el archivo con Storage (disco public) y devuelve [url, formato, tamaño].
     * Si llega una URL en vez de archivo, solo se guarda la URL y su formato.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: string, 1: string, 2: ?int}
     */
    private function guardarArchivo(array $datos, Producto $producto): array
    {
        $archivo = $datos['archivo'] ?? null;

        if ($archivo instanceof UploadedFile) {
            $disco = Storage::disk('public');
            $ruta = $disco->putFile(
                sprintf('catalogo/comercio-%d/producto-%d', (int) $producto->id_comercio, (int) $producto->getKey()),
                $archivo
            );

            return [
                $disco->url($ruta),
                $this->formatoDe((string) ($archivo->guessExtension() ?? $archivo->getClientOriginalExtension())),
                $archivo->getSize() === false ? null : (int) $archivo->getSize(),
            ];
        }

        $url = (string) ($datos['imagen_url'] ?? '');
        $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

        return [$url, $this->formatoDe($extension), null];
    }

    private function formatoDe(string $extension): string
    {
        return strtolower($extension) === 'png' ? 'png' : 'jpg';
    }

    /** @return array<string, mixed> */
    private function productoComoArray(Producto $producto): array
    {
        return [
            'id_producto' => (int) $producto->getKey(),
            'id_comercio' => (int) $producto->id_comercio,
            'id_categoria' => (int) $producto->id_categoria,
            'id_marca' => $producto->id_marca === null ? null : (int) $producto->id_marca,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'video_url' => $producto->video_url,
            'destacado' => (bool) $producto->destacado,
            'estado_producto' => $producto->estado_producto,
            'created_at' => $producto->created_at?->toIso8601String(),
        ];
    }
}
