
import { useEffect, useState } from 'react';
import {
  agregarImagen,
  crearCategoria,
  crearMarca,
  crearProducto,
  crearVariacion,
  listarCategorias,
  listarMarcas,
  publicarProducto,
  textoDeError,
  usuarioActual,
} from './services/m3Api';

const ROL_ENCARGADO = 'encargado_comercio';
const MAX_IMAGENES = 8;

const CAMPO =
  'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-[#1E3A8A] focus:ring-2 focus:ring-[#1E3A8A]/15 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';

const ETIQUETA = 'block text-sm font-semibold text-slate-700';

const BOTON_PRIMARIO =
  'inline-flex items-center justify-center gap-2 rounded-lg bg-[#9DBB45] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#8AA83A] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

const BOTON_SECUNDARIO =
  'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-[#1E3A8A] transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

const TARJETA =
  'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6';

const variacionVacia = () => ({
  id: crypto.randomUUID(),
  sku: '',
  atributos: '',
  precio: '',
  tarifa: '13',
  disponible: 'si',
});

const sinAvance = () => ({
  idProducto: null,
  variaciones: 0,
  imagenes: 0,
});

function leerAtributos(texto) {
  return texto
    .split(',')
    .map((parte) => parte.trim())
    .filter(Boolean)
    .map((parte) => {
      const corte = parte.indexOf(':');
      if (corte < 1 || corte === parte.length - 1) {
        throw new Error('formato');
      }

      return {
        tipo_atributo: parte.slice(0, corte).trim(),
        valor: parte.slice(corte + 1).trim(),
      };
    });
}

function CrearRapido({ etiqueta, crear, alCrear, deshabilitado }) {
  const [abierto, setAbierto] = useState(false);
  const [nombre, setNombre] = useState('');
  const [error, setError] = useState(null);
  const [guardando, setGuardando] = useState(false);

  async function guardar() {
    if (!nombre.trim()) return;

    setGuardando(true);
    setError(null);

    try {
      alCrear(await crear({ nombre: nombre.trim() }));
      setNombre('');
      setAbierto(false);
    } catch (falla) {
      setError(textoDeError(falla));
    } finally {
      setGuardando(false);
    }
  }

  if (!abierto) {
    return (
      <button
        type="button"
        onClick={() => setAbierto(true)}
        disabled={deshabilitado}
        className="mt-2 text-sm font-semibold text-[#1E3A8A] underline decoration-[#9DBB45] decoration-2 underline-offset-4 transition hover:text-[#9DBB45] disabled:opacity-50"
      >
        + Crear {etiqueta}
      </button>
    );
  }

  return (
    <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
      <label className={ETIQUETA}>
        Nombre de la {etiqueta}
        <input
          value={nombre}
          onChange={(e) => setNombre(e.target.value)}
          maxLength={100}
          aria-label={`Nombre de la ${etiqueta}`}
          placeholder={`Nombre de la ${etiqueta}`}
          className={CAMPO}
        />
      </label>

      <div className="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          onClick={guardar}
          disabled={guardando || !nombre.trim()}
          className={BOTON_PRIMARIO}
        >
          {guardando ? 'Guardando...' : 'Agregar'}
        </button>

        <button
          type="button"
          onClick={() => {
            setAbierto(false);
            setError(null);
          }}
          className={BOTON_SECUNDARIO}
        >
          Cancelar
        </button>
      </div>

      {error && (
        <p role="alert" className="mt-2 text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}

export default function M3App() {
  const [usuario, setUsuario] = useState(undefined);
  const [categorias, setCategorias] = useState([]);
  const [marcas, setMarcas] = useState([]);

  const [nombre, setNombre] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [idMarca, setIdMarca] = useState('');
  const [idCategoria, setIdCategoria] = useState('');
  const [variaciones, setVariaciones] = useState([variacionVacia()]);
  const [imagenes, setImagenes] = useState([]);
  const [publicar, setPublicar] = useState(false);

  const [mensaje, setMensaje] = useState(null);
  const [guardando, setGuardando] = useState(false);
  const [paso, setPaso] = useState('');
  const [avance, setAvance] = useState(sinAvance());

  useEffect(() => {
    usuarioActual()
      .then(setUsuario)
      .catch((falla) => {
        setUsuario(null);
        setMensaje({
          tipo: 'error',
          texto: textoDeError(falla),
        });
      });

    listarCategorias()
      .then(setCategorias)
      .catch(() => setCategorias([]));

    listarMarcas()
      .then(setMarcas)
      .catch(() => setMarcas([]));
  }, []);

  const puedeRegistrar = usuario?.rol === ROL_ENCARGADO;
  const productoGuardado = avance.idProducto !== null;

  function actualizarVariacion(id, campo, valor) {
    setVariaciones((actuales) =>
      actuales.map((v) =>
        v.id === id ? { ...v, [campo]: valor } : v
      )
    );
  }

  function agregarVariacion() {
    setVariaciones((actuales) => [
      ...actuales,
      variacionVacia(),
    ]);
  }

  function quitarVariacion(id) {
    setVariaciones((actuales) =>
      actuales.length > 1
        ? actuales.filter((v) => v.id !== id)
        : actuales
    );
  }

  function seleccionarImagenes(evento) {
    const archivos = Array.from(evento.target.files || []);
    const combinadas = [...imagenes, ...archivos];

    const permitidas = combinadas.filter(
      (archivo) =>
        ['image/jpeg', 'image/png'].includes(archivo.type) &&
        archivo.size <= 5 * 1024 * 1024
    );

    if (permitidas.length !== combinadas.length) {
      setMensaje({
        tipo: 'error',
        texto: 'Solo se permiten imágenes JPG o PNG de hasta 5 MB cada una.',
      });
    } else if (
      combinadas.length + avance.imagenes > MAX_IMAGENES
    ) {
      setMensaje({
        tipo: 'error',
        texto: `Podés seleccionar un máximo de ${MAX_IMAGENES} imágenes.`,
      });
    } else {
      setImagenes(permitidas);
      setMensaje(null);
    }

    evento.target.value = '';
  }

  function limpiarFormulario() {
    setNombre('');
    setDescripcion('');
    setIdMarca('');
    setIdCategoria('');
    setVariaciones([variacionVacia()]);
    setImagenes([]);
    setPublicar(false);
    setAvance(sinAvance());
  }

  async function guardarProducto(evento) {
    evento.preventDefault();

    const skus = variaciones.map((v) =>
      v.sku.trim().toLowerCase()
    );

    if (
      skus.some(
        (sku) => skus.filter((otro) => otro === sku).length > 1
      )
    ) {
      setMensaje({
        tipo: 'error',
        texto: 'Hay SKU duplicados. Cada variación debe tener un SKU diferente.',
      });
      return;
    }

    let atributosPorVariacion;

    try {
      atributosPorVariacion = variaciones.map((v) =>
        leerAtributos(v.atributos)
      );
    } catch {
      setMensaje({
        tipo: 'error',
        texto: 'Escribí los atributos como Tipo: valor, separados por coma. Por ejemplo: Color: azul, Talla: M.',
      });
      return;
    }

    if (publicar && imagenes.length + avance.imagenes === 0) {
      setMensaje({
        tipo: 'error',
        texto: 'Para publicar el producto agregá al menos una imagen, o guardalo como borrador.',
      });
      return;
    }

    setGuardando(true);
    setMensaje(null);

    let hecho = avance;

    try {
      if (hecho.idProducto === null) {
        setPaso('Creando el producto...');

        const producto = await crearProducto({
          id_categoria: Number(idCategoria),
          id_marca: idMarca ? Number(idMarca) : null,
          nombre: nombre.trim(),
          descripcion: descripcion.trim() || null,
        });

        hecho = {
          ...hecho,
          idProducto: producto.id_producto,
        };

        setAvance(hecho);
      }

      for (
        let i = hecho.variaciones;
        i < variaciones.length;
        i += 1
      ) {
        setPaso(
          `Guardando la variación ${i + 1} de ${variaciones.length}...`
        );

        const variacion = variaciones[i];

        await crearVariacion(hecho.idProducto, {
          sku: variacion.sku.trim(),
          precio: Number(variacion.precio),
          tarifa_impuesto: Number(variacion.tarifa),
          disponible: variacion.disponible === 'si',
          atributos: atributosPorVariacion[i],
        });

        hecho = {
          ...hecho,
          variaciones: i + 1,
        };

        setAvance(hecho);
      }

      const pendientes = [...imagenes];

      while (pendientes.length > 0) {
        setPaso(`Subiendo la imagen ${hecho.imagenes + 1}...`);

        await agregarImagen(
          hecho.idProducto,
          pendientes[0]
        );

        pendientes.shift();

        hecho = {
          ...hecho,
          imagenes: hecho.imagenes + 1,
        };

        setAvance(hecho);
        setImagenes([...pendientes]);
      }

      if (publicar) {
        setPaso('Publicando el producto...');
        await publicarProducto(hecho.idProducto);
      }

      const numero = hecho.idProducto;

      limpiarFormulario();

      setMensaje({
        tipo: 'exito',
        texto: publicar
          ? `El producto #${numero} se guardó y quedó publicado.`
          : `El producto #${numero} se guardó como borrador. Para publicarlo necesita al menos una variación y una imagen.`,
      });
    } catch (falla) {
      if (falla.status === 401) {
        setUsuario(null);
      }

      const parcial =
        hecho.idProducto !== null
          ? ` El producto #${hecho.idProducto} ya quedó guardado como borrador: corregí el dato y volvé a guardar para continuar desde donde quedó.`
          : '';

      setMensaje({
        tipo: 'error',
        texto: `${textoDeError(falla)}${parcial}`,
      });
    } finally {
      setGuardando(false);
      setPaso('');
    }
  }

  return (
    <section className="mx-auto max-w-6xl px-4 pb-8 text-slate-800">
      <div className="max-w-4xl">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-[#1E3A8A] sm:text-3xl">
            Registrar producto
          </h1>

          <p className="mt-2 text-sm text-slate-600">
            Completá los datos del producto y agregá sus variaciones.
          </p>
        </div>

        {usuario === undefined && (
          <div
            role="status"
            className="mb-6 flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
          >
            <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-[#1E3A8A]" />
            Comprobando tu sesión...
          </div>
        )}

        {usuario === null && (
          <div
            role="status"
            className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
          >
            <p className="font-bold">
              Necesitás iniciar sesión
            </p>

            <p className="mt-1">
              Para registrar productos iniciá sesión como Encargado del comercio.
            </p>

            <a
              href="/app.html?pantalla=ingresar"
              className="mt-2 inline-block font-bold text-[#1E3A8A] underline underline-offset-4"
            >
              Ir a iniciar sesión
            </a>
          </div>
        )}

        {usuario && !puedeRegistrar && (
          <div
            role="status"
            className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
          >
            <p className="font-bold">
              Permisos insuficientes
            </p>

            <p className="mt-1">
              Tu cuenta ({usuario.nombre}) no es de Encargado del comercio, así que no puede registrar productos.
            </p>
          </div>
        )}

        {puedeRegistrar && (
          <div className="mb-6 rounded-xl border border-[#9DBB45]/30 bg-[#9DBB45]/10 p-4 text-sm text-slate-700">
            Sesión de{' '}
            <span className="font-bold text-[#1E3A8A]">
              {usuario.nombre}
            </span>
            . El producto se registra en tu comercio.
          </div>
        )}

        <form
          onSubmit={guardarProducto}
          className="space-y-6"
        >
          <fieldset
            disabled={!puedeRegistrar || guardando}
            className="space-y-6"
          >
            <section className={TARJETA}>
              <div className="mb-5 border-b border-slate-100 pb-4">
                <h2 className="text-lg font-bold text-[#1E3A8A]">
                  Información general
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                  Datos principales del producto que aparecerán en el catálogo.
                </p>
              </div>

              <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <label className={`${ETIQUETA} sm:col-span-2`}>
                  Nombre del producto *
                  <input
                    value={nombre}
                    onChange={(e) => setNombre(e.target.value)}
                    required
                    maxLength={150}
                    disabled={productoGuardado}
                    className={CAMPO}
                    placeholder="Ej. Camiseta deportiva"
                  />
                </label>

                <div>
                  <label
                    htmlFor="m3-categoria"
                    className={ETIQUETA}
                  >
                    Categoría *
                  </label>

                  <select
                    id="m3-categoria"
                    value={idCategoria}
                    onChange={(e) => setIdCategoria(e.target.value)}
                    required
                    disabled={productoGuardado}
                    className={CAMPO}
                  >
                    <option value="">Elegí una categoría</option>

                    {categorias.map((c) => (
                      <option
                        key={c.id_categoria}
                        value={c.id_categoria}
                      >
                        {c.nombre}
                      </option>
                    ))}
                  </select>

                  <CrearRapido
                    etiqueta="categoría"
                    crear={crearCategoria}
                    deshabilitado={productoGuardado}
                    alCrear={(categoria) => {
                      setCategorias((actuales) => [
                        ...actuales,
                        categoria,
                      ]);

                      setIdCategoria(
                        String(categoria.id_categoria)
                      );
                    }}
                  />
                </div>

                <div>
                  <label
                    htmlFor="m3-marca"
                    className={ETIQUETA}
                  >
                    Marca
                  </label>

                  <select
                    id="m3-marca"
                    value={idMarca}
                    onChange={(e) => setIdMarca(e.target.value)}
                    disabled={productoGuardado}
                    className={CAMPO}
                  >
                    <option value="">Sin marca</option>

                    {marcas.map((m) => (
                      <option
                        key={m.id_marca}
                        value={m.id_marca}
                      >
                        {m.nombre}
                      </option>
                    ))}
                  </select>

                  <CrearRapido
                    etiqueta="marca"
                    crear={crearMarca}
                    deshabilitado={productoGuardado}
                    alCrear={(marca) => {
                      setMarcas((actuales) => [
                        ...actuales,
                        marca,
                      ]);

                      setIdMarca(String(marca.id_marca));
                    }}
                  />
                </div>

                <label className={`${ETIQUETA} sm:col-span-2`}>
                  Descripción
                  <textarea
                    value={descripcion}
                    onChange={(e) => setDescripcion(e.target.value)}
                    rows={4}
                    disabled={productoGuardado}
                    className={`${CAMPO} resize-y`}
                    placeholder="Describí el producto, sus características y beneficios."
                  />
                </label>
              </div>
            </section>

            <section className={TARJETA}>
              <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                  <h2 className="text-lg font-bold text-[#1E3A8A]">
                    Variaciones del producto
                  </h2>

                  <p className="mt-1 text-sm text-slate-500">
                    Agregá al menos una variación y asignale un SKU único.
                  </p>
                </div>

                <button
                  type="button"
                  onClick={agregarVariacion}
                  className={BOTON_SECUNDARIO}
                >
                  + Agregar variación
                </button>
              </div>

              <div className="space-y-4">
                {variaciones.map((variacion, indice) => {
                  const guardada = indice < avance.variaciones;

                  return (
                    <div
                      key={variacion.id}
                      className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5"
                    >
                      <div className="mb-4 flex items-center justify-between gap-3">
                        <h3 className="font-bold text-[#1E3A8A]">
                          Variación {indice + 1}

                          {guardada && (
                            <span className="ml-2 inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                              Guardada
                            </span>
                          )}
                        </h3>

                        {variaciones.length > 1 && !guardada && (
                          <button
                            type="button"
                            onClick={() =>
                              quitarVariacion(variacion.id)
                            }
                            className="text-sm font-semibold text-red-700 underline underline-offset-4 hover:text-red-900"
                          >
                            Eliminar
                          </button>
                        )}
                      </div>

                      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label className={ETIQUETA}>
                          SKU *
                          <input
                            required
                            maxLength={100}
                            disabled={guardada}
                            value={variacion.sku}
                            onChange={(e) =>
                              actualizarVariacion(
                                variacion.id,
                                'sku',
                                e.target.value
                              )
                            }
                            className={CAMPO}
                            placeholder="Ej. CAM-AZU-M"
                          />
                        </label>

                        <label className={ETIQUETA}>
                          Atributos
                          <input
                            disabled={guardada}
                            value={variacion.atributos}
                            onChange={(e) =>
                              actualizarVariacion(
                                variacion.id,
                                'atributos',
                                e.target.value
                              )
                            }
                            className={CAMPO}
                            placeholder="Ej. Color: azul, Talla: M"
                          />
                          <span className="mt-1 block text-xs font-normal text-slate-500">
                            Escribí cada atributo como Tipo: valor.
                          </span>
                        </label>

                        <label className={ETIQUETA}>
                          Precio (₡) *
                          <input
                            required
                            type="number"
                            min="0"
                            step="0.01"
                            disabled={guardada}
                            value={variacion.precio}
                            onChange={(e) =>
                              actualizarVariacion(
                                variacion.id,
                                'precio',
                                e.target.value
                              )
                            }
                            className={CAMPO}
                            placeholder="15000"
                          />
                        </label>

                        <label className={ETIQUETA}>
                          Impuesto (%) *
                          <input
                            required
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            disabled={guardada}
                            value={variacion.tarifa}
                            onChange={(e) =>
                              actualizarVariacion(
                                variacion.id,
                                'tarifa',
                                e.target.value
                              )
                            }
                            className={CAMPO}
                            placeholder="13"
                          />
                        </label>

                        <label className={ETIQUETA}>
                          Disponibilidad *
                          <select
                            disabled={guardada}
                            value={variacion.disponible}
                            onChange={(e) =>
                              actualizarVariacion(
                                variacion.id,
                                'disponible',
                                e.target.value
                              )
                            }
                            className={CAMPO}
                          >
                            <option value="si">Disponible</option>
                            <option value="no">No disponible</option>
                          </select>
                        </label>
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>

            <section className={TARJETA}>
              <div className="mb-5 border-b border-slate-100 pb-4">
                <h2 className="text-lg font-bold text-[#1E3A8A]">
                  Imágenes del producto
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                  Hasta {MAX_IMAGENES} imágenes JPG o PNG, máximo 5 MB por imagen.
                </p>
              </div>

              <label className={ETIQUETA}>
                Seleccionar imágenes
                <input
                  type="file"
                  accept="image/jpeg,image/png"
                  multiple
                  onChange={seleccionarImagenes}
                  aria-label="Seleccionar imágenes del producto"
                  className="mt-2 block w-full max-w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#1E3A8A] file:px-3 file:py-2 file:font-semibold file:text-white hover:file:bg-[#294b9b]"
                />
              </label>

              {avance.imagenes > 0 && (
                <p className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">
                  {avance.imagenes === 1
                    ? '1 imagen ya subida.'
                    : `${avance.imagenes} imágenes ya subidas.`}
                </p>
              )}

              {imagenes.length > 0 && (
                <div className="mt-4 rounded-lg border border-slate-200 p-3">
                  <p className="mb-2 text-sm font-bold text-slate-700">
                    Archivos seleccionados
                  </p>

                  <ul className="space-y-1 text-sm text-slate-600">
                    {imagenes.map((imagen, indice) => (
                      <li
                        key={`${imagen.name}-${indice}`}
                        className="break-all"
                      >
                        {imagen.name}
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              <label className="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={publicar}
                  onChange={(e) => setPublicar(e.target.checked)}
                  className="mt-0.5 h-4 w-4 accent-[#1E3A8A]"
                />

                <span>
                  <span className="block font-bold text-[#1E3A8A]">
                    Publicar el producto al guardar
                  </span>

                  <span className="mt-1 block text-slate-500">
                    Sin publicar, el producto queda como borrador y no aparece en el catálogo público. Para publicarlo necesita al menos una variación y una imagen.
                  </span>
                </span>
              </label>
            </section>
          </fieldset>

          {paso && (
            <div
              role="status"
              aria-live="polite"
              className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm font-medium text-blue-900"
            >
              <span className="mr-2 inline-block h-2 w-2 animate-pulse rounded-full bg-[#1E3A8A]" />
              {paso}
            </div>
          )}

          {mensaje && (
            <div
              role={mensaje.tipo === 'error' ? 'alert' : 'status'}
              className={`rounded-xl border p-4 text-sm ${
                mensaje.tipo === 'error'
                  ? 'border-red-200 bg-red-50 text-red-800'
                  : 'border-emerald-200 bg-emerald-50 text-emerald-800'
              }`}
            >
              <p className="font-bold">
                {mensaje.tipo === 'error' ? 'No se pudo completar la operación' : 'Operación completada'}
              </p>

              <p className="mt-1">{mensaje.texto}</p>
            </div>
          )}

          <div className="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
            <button
              type="button"
              disabled={guardando}
              onClick={() => {
                limpiarFormulario();
                setMensaje(null);
              }}
              className={BOTON_SECUNDARIO}
            >
              {productoGuardado
                ? 'Empezar otro producto'
                : 'Limpiar formulario'}
            </button>

            <button
              type="submit"
              disabled={!puedeRegistrar || guardando}
              className={BOTON_PRIMARIO}
            >
              {guardando
                ? 'Guardando...'
                : productoGuardado
                  ? 'Continuar guardando'
                  : 'Guardar producto'}
            </button>
          </div>
        </form>

        <p className="mt-6 text-xs leading-relaxed text-slate-500">
          Los datos se guardan en el servicio de catálogo de M3. El servidor valida cada dato y asigna el comercio de tu sesión.
        </p>
      </div>
    </section>
  );
}
