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

// Registro de producto contra el servicio real de M3 (contrato v1.3.0, /api/m3/v1):
// crearProducto -> crearVariacion (una por variación) -> agregarImagen (una por archivo) -> publicar (opcional).
// Requiere la sesión del núcleo de un Encargado del comercio; el comercio sale de la sesión (RN-12).

const ROL_ENCARGADO = 'encargado_comercio';
const MAX_IMAGENES = 8;

const variacionVacia = () => ({
  id: crypto.randomUUID(),
  sku: '',
  atributos: '',
  precio: '',
  tarifa: '13',
  disponible: 'si',
});

const sinAvance = () => ({ idProducto: null, variaciones: 0, imagenes: 0 });

// "Color: azul, Talla: M" -> [{ tipo_atributo: 'Color', valor: 'azul' }, { tipo_atributo: 'Talla', valor: 'M' }]
function leerAtributos(texto) {
  return texto
    .split(',')
    .map((parte) => parte.trim())
    .filter(Boolean)
    .map((parte) => {
      const corte = parte.indexOf(':');
      if (corte < 1 || corte === parte.length - 1) throw new Error('formato');
      return { tipo_atributo: parte.slice(0, corte).trim(), valor: parte.slice(corte + 1).trim() };
    });
}

// Alta rápida de una categoría o una marca sin salir del formulario.
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
        className="mt-2 text-sm font-medium text-sky-700 underline disabled:opacity-50"
      >
        Crear {etiqueta}
      </button>
    );
  }

  return (
    <div className="mt-2">
      <div className="flex flex-wrap gap-2">
        <input
          value={nombre}
          onChange={(e) => setNombre(e.target.value)}
          maxLength={100}
          aria-label={`Nombre de la ${etiqueta}`}
          placeholder={`Nombre de la ${etiqueta}`}
          className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"
        />
        <button
          type="button"
          onClick={guardar}
          disabled={guardando || !nombre.trim()}
          className="rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-50"
        >
          {guardando ? 'Guardando...' : 'Agregar'}
        </button>
        <button
          type="button"
          onClick={() => { setAbierto(false); setError(null); }}
          className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50"
        >
          Cancelar
        </button>
      </div>
      {error && <p role="alert" className="mt-1 text-sm font-normal text-red-700">{error}</p>}
    </div>
  );
}

export default function M3App() {
  // undefined: comprobando la sesión; null: sin sesión; objeto: usuario del núcleo.
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
  // Lo que ya quedó guardado en el servidor, para continuar sin duplicar si un paso falla.
  const [avance, setAvance] = useState(sinAvance());

  useEffect(() => {
    usuarioActual()
      .then(setUsuario)
      .catch((falla) => {
        setUsuario(null);
        setMensaje({ tipo: 'error', texto: textoDeError(falla) });
      });
    listarCategorias().then(setCategorias).catch(() => setCategorias([]));
    listarMarcas().then(setMarcas).catch(() => setMarcas([]));
  }, []);

  const puedeRegistrar = usuario?.rol === ROL_ENCARGADO;
  const productoGuardado = avance.idProducto !== null;

  function actualizarVariacion(id, campo, valor) {
    setVariaciones((actuales) =>
      actuales.map((v) => v.id === id ? { ...v, [campo]: valor } : v)
    );
  }

  function agregarVariacion() {
    setVariaciones((actuales) => [...actuales, variacionVacia()]);
  }

  function quitarVariacion(id) {
    setVariaciones((actuales) =>
      actuales.length > 1 ? actuales.filter((v) => v.id !== id) : actuales
    );
  }

  function seleccionarImagenes(evento) {
    const archivos = Array.from(evento.target.files || []);
    const combinadas = [...imagenes, ...archivos];
    const permitidas = combinadas.filter((archivo) =>
      ['image/jpeg', 'image/png'].includes(archivo.type) &&
      archivo.size <= 5 * 1024 * 1024
    );

    if (permitidas.length !== combinadas.length) {
      setMensaje({
        tipo: 'error',
        texto: 'Solo se permiten imágenes JPG o PNG de hasta 5 MB cada una.',
      });
    } else if (combinadas.length + avance.imagenes > MAX_IMAGENES) {
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

    const skus = variaciones.map((v) => v.sku.trim().toLowerCase());
    if (skus.some((sku) => skus.filter((otro) => otro === sku).length > 1)) {
      setMensaje({
        tipo: 'error',
        texto: 'Hay SKU duplicados. Cada variación debe tener un SKU diferente.',
      });
      return;
    }

    let atributosPorVariacion;
    try {
      atributosPorVariacion = variaciones.map((v) => leerAtributos(v.atributos));
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
        hecho = { ...hecho, idProducto: producto.id_producto };
        setAvance(hecho);
      }

      for (let i = hecho.variaciones; i < variaciones.length; i += 1) {
        setPaso(`Guardando la variación ${i + 1} de ${variaciones.length}...`);
        const variacion = variaciones[i];
        await crearVariacion(hecho.idProducto, {
          sku: variacion.sku.trim(),
          precio: Number(variacion.precio),
          tarifa_impuesto: Number(variacion.tarifa),
          disponible: variacion.disponible === 'si',
          atributos: atributosPorVariacion[i],
        });
        hecho = { ...hecho, variaciones: i + 1 };
        setAvance(hecho);
      }

      const pendientes = [...imagenes];
      while (pendientes.length > 0) {
        setPaso(`Subiendo la imagen ${hecho.imagenes + 1}...`);
        await agregarImagen(hecho.idProducto, pendientes[0]);
        pendientes.shift();
        hecho = { ...hecho, imagenes: hecho.imagenes + 1 };
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
      if (falla.status === 401) setUsuario(null);
      const parcial = hecho.idProducto !== null
        ? ` El producto #${hecho.idProducto} ya quedó guardado como borrador: corregí el dato y volvé a guardar para continuar desde donde quedó.`
        : '';
      setMensaje({ tipo: 'error', texto: `${textoDeError(falla)}${parcial}` });
    } finally {
      setGuardando(false);
      setPaso('');
    }
  }

  return (
    <main className="min-h-screen bg-slate-100 px-4 py-6 text-slate-900 sm:px-6">
      <div className="mx-auto max-w-4xl">
        <header className="mb-6">
          <p className="text-sm font-semibold uppercase tracking-wide text-sky-700">
            Pacíficos Online · Módulo M3
          </p>
          <h1 className="mt-2 text-2xl font-bold sm:text-3xl">
            Registrar producto
          </h1>
          <p className="mt-2 text-sm text-slate-600">
            Completá los datos del producto y agregá sus variaciones.
          </p>
        </header>

        {usuario === undefined && (
          <p className="mb-6 text-sm text-slate-600">Comprobando tu sesión...</p>
        )}

        {usuario === null && (
          <div role="status" className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Para registrar productos iniciá sesión como Encargado del comercio.{' '}
            <a href="/app.html" className="font-semibold underline">Ir a iniciar sesión</a>
          </div>
        )}

        {usuario && !puedeRegistrar && (
          <div role="status" className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Tu cuenta ({usuario.nombre}) no es de Encargado del comercio, así que no puede registrar productos.
          </div>
        )}

        {puedeRegistrar && (
          <p className="mb-6 text-sm text-slate-600">
            Sesión de {usuario.nombre}. El producto se registra en tu comercio.
          </p>
        )}

        <form onSubmit={guardarProducto} className="space-y-6">
          <fieldset disabled={!puedeRegistrar || guardando} className="space-y-6">
            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
              <h2 className="mb-4 text-lg font-semibold">Información general</h2>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label className="text-sm font-medium sm:col-span-2">
                  Nombre del producto *
                  <input
                    value={nombre}
                    onChange={(e) => setNombre(e.target.value)}
                    required
                    maxLength={150}
                    disabled={productoGuardado}
                    className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                    placeholder="Ej. Camiseta deportiva"
                  />
                </label>

                <div className="text-sm font-medium">
                  <label htmlFor="m3-categoria">Categoría *</label>
                  <select
                    id="m3-categoria"
                    value={idCategoria}
                    onChange={(e) => setIdCategoria(e.target.value)}
                    required
                    disabled={productoGuardado}
                    className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                  >
                    <option value="">Elegí una categoría</option>
                    {categorias.map((c) => (
                      <option key={c.id_categoria} value={c.id_categoria}>{c.nombre}</option>
                    ))}
                  </select>
                  <CrearRapido
                    etiqueta="categoría"
                    crear={crearCategoria}
                    deshabilitado={productoGuardado}
                    alCrear={(categoria) => {
                      setCategorias((actuales) => [...actuales, categoria]);
                      setIdCategoria(String(categoria.id_categoria));
                    }}
                  />
                </div>

                <div className="text-sm font-medium">
                  <label htmlFor="m3-marca">Marca</label>
                  <select
                    id="m3-marca"
                    value={idMarca}
                    onChange={(e) => setIdMarca(e.target.value)}
                    disabled={productoGuardado}
                    className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                  >
                    <option value="">Sin marca</option>
                    {marcas.map((m) => (
                      <option key={m.id_marca} value={m.id_marca}>{m.nombre}</option>
                    ))}
                  </select>
                  <CrearRapido
                    etiqueta="marca"
                    crear={crearMarca}
                    deshabilitado={productoGuardado}
                    alCrear={(marca) => {
                      setMarcas((actuales) => [...actuales, marca]);
                      setIdMarca(String(marca.id_marca));
                    }}
                  />
                </div>

                <label className="text-sm font-medium sm:col-span-2">
                  Descripción
                  <textarea
                    value={descripcion}
                    onChange={(e) => setDescripcion(e.target.value)}
                    rows={3}
                    disabled={productoGuardado}
                    className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                    placeholder="Describí el producto"
                  />
                </label>
              </div>
            </section>

            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
              <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold">Variaciones del producto</h2>
                  <p className="mt-1 text-sm text-slate-600">
                    Agregá al menos una variación y asignale un SKU único.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={agregarVariacion}
                  className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-50"
                >
                  Agregar variación
                </button>
              </div>

              <div className="space-y-4">
                {variaciones.map((variacion, indice) => {
                  const guardada = indice < avance.variaciones;

                  return (
                    <div key={variacion.id} className="rounded-lg border border-slate-200 p-4">
                      <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold">
                          Variación {indice + 1}
                          {guardada && <span className="ml-2 text-sm font-medium text-emerald-700">Guardada</span>}
                        </h3>
                        {variaciones.length > 1 && !guardada && (
                          <button
                            type="button"
                            onClick={() => quitarVariacion(variacion.id)}
                            className="text-sm font-medium text-red-700 underline"
                          >
                            Eliminar
                          </button>
                        )}
                      </div>

                      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                          SKU *
                          <input
                            required
                            maxLength={100}
                            disabled={guardada}
                            value={variacion.sku}
                            onChange={(e) => actualizarVariacion(variacion.id, 'sku', e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                            placeholder="Ej. CAM-AZU-M"
                          />
                        </label>

                        <label className="text-sm font-medium">
                          Atributos
                          <input
                            disabled={guardada}
                            value={variacion.atributos}
                            onChange={(e) => actualizarVariacion(variacion.id, 'atributos', e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                            placeholder="Ej. Color: azul, Talla: M"
                          />
                        </label>

                        <label className="text-sm font-medium">
                          Precio (₡) *
                          <input
                            required
                            type="number"
                            min="0"
                            step="0.01"
                            disabled={guardada}
                            value={variacion.precio}
                            onChange={(e) => actualizarVariacion(variacion.id, 'precio', e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                            placeholder="15000"
                          />
                        </label>

                        <label className="text-sm font-medium">
                          Impuesto (%) *
                          <input
                            required
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            disabled={guardada}
                            value={variacion.tarifa}
                            onChange={(e) => actualizarVariacion(variacion.id, 'tarifa', e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
                            placeholder="13"
                          />
                        </label>

                        <label className="text-sm font-medium">
                          Disponibilidad *
                          <select
                            disabled={guardada}
                            value={variacion.disponible}
                            onChange={(e) => actualizarVariacion(variacion.id, 'disponible', e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal disabled:bg-slate-100"
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

            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
              <h2 className="text-lg font-semibold">Imágenes del producto</h2>
              <p className="mt-1 text-sm text-slate-600">
                Hasta {MAX_IMAGENES} imágenes JPG o PNG, máximo 5 MB por imagen.
              </p>

              <input
                type="file"
                accept="image/jpeg,image/png"
                multiple
                onChange={seleccionarImagenes}
                aria-label="Seleccionar imágenes del producto"
                className="mt-4 block w-full max-w-full text-sm"
              />

              {avance.imagenes > 0 && (
                <p className="mt-3 text-sm font-medium text-emerald-700">
                  {avance.imagenes === 1 ? '1 imagen ya subida.' : `${avance.imagenes} imágenes ya subidas.`}
                </p>
              )}

              {imagenes.length > 0 && (
                <ul className="mt-3 list-inside list-disc text-sm text-slate-700">
                  {imagenes.map((imagen, indice) => (
                    <li key={`${imagen.name}-${indice}`}>{imagen.name}</li>
                  ))}
                </ul>
              )}

              <label className="mt-4 flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={publicar}
                  onChange={(e) => setPublicar(e.target.checked)}
                  className="h-4 w-4"
                />
                Publicar el producto al guardar
              </label>
              <p className="mt-2 text-sm text-slate-600">
                Sin publicar, el producto queda como borrador y no aparece en el catálogo público.
                Para publicarlo necesita al menos una variación y una imagen.
              </p>
            </section>
          </fieldset>

          {paso && (
            <p role="status" className="text-sm text-slate-600">{paso}</p>
          )}

          {mensaje && (
            <div
              role={mensaje.tipo === 'error' ? 'alert' : 'status'}
              className={`rounded-lg border p-4 text-sm ${
                mensaje.tipo === 'error'
                  ? 'border-red-200 bg-red-50 text-red-800'
                  : 'border-emerald-200 bg-emerald-50 text-emerald-800'
              }`}
            >
              {mensaje.texto}
            </div>
          )}

          <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              disabled={guardando}
              onClick={() => {
                limpiarFormulario();
                setMensaje(null);
              }}
              className="rounded-lg border border-slate-300 bg-white px-5 py-3 font-semibold hover:bg-slate-50 disabled:opacity-50"
            >
              {productoGuardado ? 'Empezar otro producto' : 'Limpiar formulario'}
            </button>

            <button
              type="submit"
              disabled={!puedeRegistrar || guardando}
              className="rounded-lg bg-sky-700 px-5 py-3 font-semibold text-white hover:bg-sky-800 disabled:opacity-50"
            >
              {guardando ? 'Guardando...' : productoGuardado ? 'Continuar guardando' : 'Guardar producto'}
            </button>
          </div>
        </form>

        <p className="mt-6 text-xs text-slate-500">
          Los datos se guardan en el servicio de catálogo de M3. El servidor valida cada dato y asigna el comercio de tu sesión.
        </p>
      </div>
    </main>
  );
}
