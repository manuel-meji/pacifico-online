
import { useState } from 'react';
import {
  AlertCircle,
  CheckCircle2,
  ImagePlus,
  Plus,
  Trash2,
  ArrowRight,
} from 'lucide-react';

// Datos de demostración. Sustituir por catálogos oficiales de M3
// cuando estén disponibles.
const CATEGORIAS = [
  { id: 'cat-ropa', nombre: 'Ropa' },
  { id: 'cat-accesorios', nombre: 'Accesorios' },
  { id: 'cat-hogar', nombre: 'Hogar' },
  { id: 'cat-deportes', nombre: 'Deportes' },
];

const MARCAS = [
  { id: 'marca-demo-1', nombre: 'Marca de demostración 1' },
  { id: 'marca-demo-2', nombre: 'Marca de demostración 2' },
  { id: 'marca-demo-3', nombre: 'Marca de demostración 3' },
];

// Las etiquetas se conservan en el catálogo porque RN-M3-13 las menciona.
// Su correspondencia con el modelo de datos queda pendiente de definición
// con el Comité; este prototipo no las persiste.
const CAMPO =
  'mt-1 block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal text-slate-900 focus:border-[#3b82f6] focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/20';

const BOTON_PRINCIPAL =
  'inline-flex items-center justify-center gap-2 rounded-lg bg-[#a3be4d] px-5 py-3 font-semibold text-slate-950 hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#3b82f6] focus:ring-offset-2';

const BOTON_SECUNDARIO =
  'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#3b82f6]';

const variacionVacia = () => ({
  id: crypto.randomUUID(),
  sku: '',
  precio: '',
  tarifa_impuesto: '13',
  disponibilidad: 'Disponible',
  atributos: [
    { id: crypto.randomUUID(), tipo_atributo: '', valor: '' },
  ],
});

export default function M3App() {
  const [nombre, setNombre] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [idMarca, setIdMarca] = useState('');
  const [idCategoria, setIdCategoria] = useState('');
  const [codigoBarras, setCodigoBarras] = useState('');
  const [codigoFabricante, setCodigoFabricante] = useState('');
  const [video, setVideo] = useState('');
  const [destacado, setDestacado] = useState(false);
  const [variaciones, setVariaciones] = useState([variacionVacia()]);
  const [imagenes, setImagenes] = useState([]);
  const [mensaje, setMensaje] = useState(null);

  function actualizarVariacion(id, campo, valor) {
    setVariaciones((actuales) =>
      actuales.map((variacion) =>
        variacion.id === id
          ? { ...variacion, [campo]: valor }
          : variacion
      )
    );
  }

  function actualizarAtributo(idVariacion, idAtributo, campo, valor) {
    setVariaciones((actuales) =>
      actuales.map((variacion) => {
        if (variacion.id !== idVariacion) return variacion;

        return {
          ...variacion,
          atributos: variacion.atributos.map((atributo) =>
            atributo.id === idAtributo
              ? { ...atributo, [campo]: valor }
              : atributo
          ),
        };
      })
    );
  }

  function agregarAtributo(idVariacion) {
    setVariaciones((actuales) =>
      actuales.map((variacion) =>
        variacion.id === idVariacion
          ? {
              ...variacion,
              atributos: [
                ...variacion.atributos,
                {
                  id: crypto.randomUUID(),
                  tipo_atributo: '',
                  valor: '',
                },
              ],
            }
          : variacion
      )
    );
  }

  function quitarAtributo(idVariacion, idAtributo) {
    setVariaciones((actuales) =>
      actuales.map((variacion) => {
        if (variacion.id !== idVariacion) return variacion;

        return {
          ...variacion,
          atributos:
            variacion.atributos.length > 1
              ? variacion.atributos.filter(
                  (atributo) => atributo.id !== idAtributo
                )
              : variacion.atributos,
        };
      })
    );
  }

  function agregarVariacion() {
    setVariaciones((actuales) => [...actuales, variacionVacia()]);
    setMensaje(null);
  }

  function quitarVariacion(id) {
    setVariaciones((actuales) =>
      actuales.length > 1
        ? actuales.filter((variacion) => variacion.id !== id)
        : actuales
    );
    setMensaje(null);
  }

  function seleccionarImagenes(evento) {
    const archivos = Array.from(evento.target.files || []);
    const combinadas = [...imagenes, ...archivos];

    if (combinadas.length > 8) {
      setMensaje({
        tipo: 'error',
        texto: 'Puedes seleccionar un máximo de 8 imágenes.',
      });
      evento.target.value = '';
      return;
    }

    const archivoInvalido = combinadas.some(
      (archivo) =>
        !['image/jpeg', 'image/png'].includes(archivo.type) ||
        archivo.size > 5 * 1024 * 1024
    );

    if (archivoInvalido) {
      setMensaje({
        tipo: 'error',
        texto: 'Solo se permiten imágenes JPG o PNG de hasta 5 MB cada una.',
      });
      evento.target.value = '';
      return;
    }

    setImagenes(combinadas);
    setMensaje(null);
    evento.target.value = '';
  }

  function eliminarImagen(indiceEliminar) {
    setImagenes((actuales) =>
      actuales.filter((_, indice) => indice !== indiceEliminar)
    );
  }

  function validarProducto(evento) {
    evento.preventDefault();
    setMensaje(null);

    if (!nombre.trim()) {
      setMensaje({
        tipo: 'error',
        texto: 'Escribe el nombre del producto.',
      });
      return;
    }

    if (!idCategoria) {
      setMensaje({
        tipo: 'error',
        texto: 'Selecciona una categoría.',
      });
      return;
    }

    if (imagenes.length < 1 || imagenes.length > 8) {
      setMensaje({
        tipo: 'error',
        texto: 'Agrega entre 1 y 8 imágenes JPG o PNG, de máximo 5 MB cada una.',
      });
      return;
    }

    const skus = variaciones.map((variacion) =>
      variacion.sku.trim().toLowerCase()
    );

    if (skus.some((sku) => !sku)) {
      setMensaje({
        tipo: 'error',
        texto: 'Completa el SKU de cada variación.',
      });
      return;
    }

    if (new Set(skus).size !== skus.length) {
      setMensaje({
        tipo: 'error',
        texto: 'Hay SKU duplicados. Cada variación debe tener un SKU diferente.',
      });
      return;
    }

    for (let indice = 0; indice < variaciones.length; indice += 1) {
      const variacion = variaciones[indice];
      const numeroPrecio = Number(variacion.precio);
      const numeroImpuesto = Number(variacion.tarifa_impuesto);

      if (
        variacion.precio === '' ||
        !Number.isFinite(numeroPrecio) ||
        numeroPrecio <= 0
      ) {
        setMensaje({
          tipo: 'error',
          texto: `Revisa el precio de la variación ${indice + 1}. Debe ser mayor que cero.`,
        });
        return;
      }

      if (
        variacion.tarifa_impuesto === '' ||
        !Number.isFinite(numeroImpuesto) ||
        numeroImpuesto < 0 ||
        numeroImpuesto > 100
      ) {
        setMensaje({
          tipo: 'error',
          texto: `La tarifa de impuesto de la variación ${indice + 1} debe estar entre 0 y 100.`,
        });
        return;
      }

      if (
        variacion.atributos.length === 0 ||
        variacion.atributos.some(
          (atributo) =>
            !atributo.tipo_atributo.trim() || !atributo.valor.trim()
        )
      ) {
        setMensaje({
          tipo: 'error',
          texto: `Completa el tipo y el valor de todos los atributos de la variación ${indice + 1}.`,
        });
        return;
      }
    }

    const productoPrueba = {
      nombre: nombre.trim(),
      descripcion: descripcion.trim(),
      id_categoria: idCategoria,
      id_marca: idMarca || null,
      codigo_barras: codigoBarras.trim() || null,
      codigo_fabricante: codigoFabricante.trim() || null,
      video: video.trim() || null,
      destacado,
      variaciones: variaciones.map((variacion) => ({
        sku: variacion.sku.trim(),
        precio: Number(variacion.precio),
        tarifa_impuesto: Number(variacion.tarifa_impuesto),
        atributos: variacion.atributos.map((atributo) => ({
          tipo_atributo: atributo.tipo_atributo.trim(),
          valor: atributo.valor.trim(),
        })),
        disponibilidad: variacion.disponibilidad,
      })),
      imagenes: imagenes.map((imagen) => imagen.name),
      estado: 'borrador',
    };

    console.log('Producto de prueba validado:', productoPrueba);

    setMensaje({
      tipo: 'exito',
      texto: 'La validación local fue correcta. El producto queda como borrador; no se guardó en el servidor.',
    });
  }

  function limpiarFormulario() {
    setNombre('');
    setDescripcion('');
    setIdMarca('');
    setIdCategoria('');
    setCodigoBarras('');
    setCodigoFabricante('');
    setVideo('');
    setDestacado(false);
    setVariaciones([variacionVacia()]);
    setImagenes([]);
    setMensaje(null);
  }

  return (
    <main className="min-h-screen bg-[#f8fafc] px-4 py-6 text-slate-900 sm:px-6">
      <div className="mx-auto max-w-4xl">
        <header className="mb-6">
          <p className="text-sm font-semibold uppercase tracking-wide text-[#1e3a8a]">
            Pacíficos Online · Módulo M3
          </p>

          <h1 className="mt-2 text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
            Registrar producto
          </h1>

          <p className="mt-2 text-sm text-slate-600">
            Completa los datos del producto y agrega sus variaciones.
          </p>

          <a
            href="/prototipos/M3_Catalogos/m3-catalogo.html"
            className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[#3b82f6] underline underline-offset-2 focus:outline-none focus:ring-2 focus:ring-[#3b82f6]"
          >
            Consultar catálogo
            <ArrowRight size={16} aria-hidden="true" />
          </a>
        </header>

        <form onSubmit={validarProducto} className="space-y-6">
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 className="mb-4 text-lg font-semibold text-[#1e3a8a]">
              Información general
            </h2>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <label className="text-sm font-medium">
                Nombre del producto *
                <input
                  value={nombre}
                  onChange={(evento) => setNombre(evento.target.value)}
                  required
                  maxLength={150}
                  className={CAMPO}
                  placeholder="Ej. Camiseta deportiva"
                />
              </label>

              <label className="text-sm font-medium">
                Categoría *
                <select
                  value={idCategoria}
                  onChange={(evento) => setIdCategoria(evento.target.value)}
                  required
                  className={CAMPO}
                >
                  <option value="">Selecciona una categoría</option>
                  {CATEGORIAS.map((categoria) => (
                    <option key={categoria.id} value={categoria.id}>
                      {categoria.nombre}
                    </option>
                  ))}
                </select>
                <span className="mt-1 block text-xs text-slate-500">
                  Opciones de demostración; sustituir por el catálogo oficial.
                </span>
              </label>

              <label className="text-sm font-medium">
                Marca (opcional)
                <select
                  value={idMarca}
                  onChange={(evento) => setIdMarca(evento.target.value)}
                  className={CAMPO}
                >
                  <option value="">Sin marca</option>
                  {MARCAS.map((marca) => (
                    <option key={marca.id} value={marca.id}>
                      {marca.nombre}
                    </option>
                  ))}
                </select>
                <span className="mt-1 block text-xs text-slate-500">
                  Opciones de demostración; sustituir por el catálogo oficial.
                </span>
              </label>

              <label className="text-sm font-medium">
                Código de barras (opcional)
                <input
                  value={codigoBarras}
                  onChange={(evento) => setCodigoBarras(evento.target.value)}
                  maxLength={100}
                  className={CAMPO}
                  placeholder="Código de barras"
                />
              </label>

              <label className="text-sm font-medium">
                Código de fabricante (opcional)
                <input
                  value={codigoFabricante}
                  onChange={(evento) => setCodigoFabricante(evento.target.value)}
                  maxLength={100}
                  className={CAMPO}
                  placeholder="Código del fabricante"
                />
              </label>

              <label className="text-sm font-medium">
                Video (opcional)
                <input
                  type="url"
                  value={video}
                  onChange={(evento) => setVideo(evento.target.value)}
                  className={CAMPO}
                  placeholder="https://ejemplo.com/video"
                />
              </label>

              <label className="text-sm font-medium sm:col-span-2">
                Descripción
                <textarea
                  value={descripcion}
                  onChange={(evento) => setDescripcion(evento.target.value)}
                  rows={3}
                  maxLength={2000}
                  className={CAMPO}
                  placeholder="Describe el producto"
                />
              </label>

              <label className="flex items-center gap-2 text-sm sm:col-span-2">
                <input
                  type="checkbox"
                  checked={destacado}
                  onChange={(evento) => setDestacado(evento.target.checked)}
                  className="h-4 w-4 accent-[#1e3a8a]"
                />
                Marcar como producto destacado (opcional)
              </label>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold text-[#1e3a8a]">
                  Variaciones del producto
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                  Agrega al menos una variación con un SKU único.
                </p>
              </div>

              <button
                type="button"
                onClick={agregarVariacion}
                className={BOTON_SECUNDARIO}
              >
                <Plus size={16} aria-hidden="true" />
                Agregar variación
              </button>
            </div>

            <div className="space-y-4">
              {variaciones.map((variacion, indice) => (
                <div
                  key={variacion.id}
                  className="rounded-lg border border-slate-200 p-4"
                >
                  <div className="mb-3 flex items-center justify-between gap-3">
                    <h3 className="font-semibold">
                      Variación {indice + 1}
                    </h3>

                    {variaciones.length > 1 && (
                      <button
                        type="button"
                        onClick={() => quitarVariacion(variacion.id)}
                        className="inline-flex items-center gap-1 text-sm font-medium text-red-700 underline"
                      >
                        <Trash2 size={16} aria-hidden="true" />
                        Eliminar variación
                      </button>
                    )}
                  </div>

                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label className="text-sm font-medium">
                      SKU *
                      <input
                        required
                        value={variacion.sku}
                        onChange={(evento) =>
                          actualizarVariacion(
                            variacion.id,
                            'sku',
                            evento.target.value
                          )
                        }
                        className={CAMPO}
                        placeholder="Ej. CAM-AZU-M"
                      />
                    </label>

                    <label className="text-sm font-medium">
                      Precio (₡) *
                      <input
                        required
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={variacion.precio}
                        onChange={(evento) =>
                          actualizarVariacion(
                            variacion.id,
                            'precio',
                            evento.target.value
                          )
                        }
                        className={CAMPO}
                        placeholder="15000"
                      />
                    </label>

                    <label className="text-sm font-medium">
                      IVA (%) *
                      <input
                        required
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={variacion.tarifa_impuesto}
                        onChange={(evento) =>
                          actualizarVariacion(
                            variacion.id,
                            'tarifa_impuesto',
                            evento.target.value
                          )
                        }
                        className={CAMPO}
                        placeholder="13"
                      />
                    </label>

                    <label className="text-sm font-medium">
                      Disponibilidad *
                      <select
                        value={variacion.disponibilidad}
                        onChange={(evento) =>
                          actualizarVariacion(
                            variacion.id,
                            'disponibilidad',
                            evento.target.value
                          )
                        }
                        className={CAMPO}
                      >
                        <option value="Disponible">Disponible</option>
                        <option value="No disponible">No disponible</option>
                      </select>
                    </label>
                  </div>

                  <div className="mt-5">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                      <h4 className="font-semibold">Atributos *</h4>

                      <button
                        type="button"
                        onClick={() => agregarAtributo(variacion.id)}
                        className={BOTON_SECUNDARIO}
                      >
                        <Plus size={16} aria-hidden="true" />
                        Agregar atributo
                      </button>
                    </div>

                    <div className="space-y-3">
                      {variacion.atributos.map((atributo, indiceAtributo) => (
                        <div
                          key={atributo.id}
                          className="grid grid-cols-1 gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-[1fr_1fr_auto]"
                        >
                          <label className="text-sm font-medium">
                            Tipo de atributo *
                            <input
                              required
                              value={atributo.tipo_atributo}
                              onChange={(evento) =>
                                actualizarAtributo(
                                  variacion.id,
                                  atributo.id,
                                  'tipo_atributo',
                                  evento.target.value
                                )
                              }
                              className={CAMPO}
                              placeholder="Ej. Color"
                            />
                          </label>

                          <label className="text-sm font-medium">
                            Valor *
                            <input
                              required
                              value={atributo.valor}
                              onChange={(evento) =>
                                actualizarAtributo(
                                  variacion.id,
                                  atributo.id,
                                  'valor',
                                  evento.target.value
                                )
                              }
                              className={CAMPO}
                              placeholder="Ej. Azul"
                            />
                          </label>

                          <div className="flex items-end">
                            {variacion.atributos.length > 1 && (
                              <button
                                type="button"
                                onClick={() =>
                                  quitarAtributo(variacion.id, atributo.id)
                                }
                                className="inline-flex items-center gap-1 pb-2 text-sm font-medium text-red-700 underline"
                                aria-label={`Eliminar atributo ${indiceAtributo + 1}`}
                              >
                                <Trash2 size={16} aria-hidden="true" />
                                Eliminar
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-[#1e3a8a]">
              <ImagePlus size={20} aria-hidden="true" />
              Imágenes del producto
            </h2>
            <p className="mt-1 text-sm text-slate-600">
              Obligatorio: agrega entre 1 y 8 imágenes JPG o PNG, máximo 5 MB por imagen.
            </p>

            <input
              type="file"
              accept="image/jpeg,image/png"
              multiple
              onChange={seleccionarImagenes}
              className="mt-4 block w-full max-w-full text-sm"
            />

            {imagenes.length > 0 && (
              <ul className="mt-3 space-y-2 text-sm text-slate-700">
                {imagenes.map((imagen, indice) => (
                  <li
                    key={`${imagen.name}-${indice}`}
                    className="flex flex-wrap items-center justify-between gap-2"
                  >
                    <span className="break-all">{imagen.name}</span>
                    <button
                      type="button"
                      onClick={() => eliminarImagen(indice)}
                      className="inline-flex items-center gap-1 text-red-700 underline"
                    >
                      <Trash2 size={16} aria-hidden="true" />
                      Quitar
                    </button>
                  </li>
                ))}
              </ul>
            )}

            <p className="mt-3 text-sm text-slate-600">
              El producto se mantiene como borrador durante esta validación local.
              Las reglas definitivas de publicación deben confirmarse con el contrato.
            </p>
          </section>

          {mensaje && (
            <div
              role={mensaje.tipo === 'error' ? 'alert' : 'status'}
              className={`flex items-start gap-2 rounded-lg border p-4 text-sm ${
                mensaje.tipo === 'error'
                  ? 'border-red-200 bg-red-50 text-red-800'
                  : 'border-emerald-200 bg-emerald-50 text-emerald-800'
              }`}
            >
              {mensaje.tipo === 'error' ? (
                <AlertCircle size={18} className="mt-0.5 shrink-0" aria-hidden="true" />
              ) : (
                <CheckCircle2 size={18} className="mt-0.5 shrink-0" aria-hidden="true" />
              )}
              <span>{mensaje.texto}</span>
            </div>
          )}

          <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              onClick={limpiarFormulario}
              className={BOTON_SECUNDARIO}
            >
              Limpiar formulario
            </button>

            <button type="submit" className={BOTON_PRINCIPAL}>
              <CheckCircle2 size={18} aria-hidden="true" />
              Validar producto
            </button>
          </div>
        </form>

        <p className="mt-6 text-xs text-slate-500">
          Prototipo de interfaz M3. Los datos se validan localmente; no se envían
          a la API ni se guardan en la base de datos.
        </p>
      </div>
    </main>
  );
}
