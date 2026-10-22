import { useState } from 'react';

const variacionVacia = () => ({
  id: crypto.randomUUID(),
  sku: '',
  atributos: '',
  precio: '',
  disponibilidad: 'Disponible',
});

export default function M3App() {
  const [nombre, setNombre] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [marca, setMarca] = useState('');
  const [categoria, setCategoria] = useState('');
  const [variaciones, setVariaciones] = useState([variacionVacia()]);
  const [imagenes, setImagenes] = useState([]);
  const [mensaje, setMensaje] = useState(null);

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
    } else if (combinadas.length > 8) {
      setMensaje({
        tipo: 'error',
        texto: 'Podés seleccionar un máximo de 8 imágenes.',
      });
    } else {
      setImagenes(permitidas);
      setMensaje(null);
    }

    evento.target.value = '';
  }

  function guardarProducto(evento) {
    evento.preventDefault();
    const skus = variaciones.map((v) => v.sku.trim().toLowerCase());

if (skus.some((sku) => skus.filter((otro) => otro === sku).length > 1)) {
  setMensaje({
    tipo: 'error',
    texto: 'Hay SKU duplicados. Cada variación debe tener un SKU diferente.',
  });
  return;
}

    if (!nombre.trim() || !marca.trim() || !categoria.trim()) {
      setMensaje({
        tipo: 'error',
        texto: 'Completá el nombre, la marca y la categoría.',
      });
      return;
    }

    const incompleta = imagenes.length === 0;

    setMensaje({
      tipo: 'exito',
      texto: incompleta
        ? 'Validación de prototipo: producto preparado como incompleto porque no tiene imágenes. No se guardó en el servidor.'
        : 'Validación de prototipo: los datos son válidos. No se guardaron en el servidor.',
    });

    console.log('Datos de prueba del producto:', {
      nombre,
      descripcion,
      marca,
      categoria,
      variaciones,
      imagenes: imagenes.map((imagen) => imagen.name),
      estado: incompleta ? 'Incompleto' : 'Pendiente de validación',
    });
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

        <form onSubmit={guardarProducto} className="space-y-6">
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 className="mb-4 text-lg font-semibold">Información general</h2>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <label className="text-sm font-medium">
                Nombre del producto *
                <input
                  value={nombre}
                  onChange={(e) => setNombre(e.target.value)}
                  required
                  maxLength={150}
                  className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
                  placeholder="Ej. Camiseta deportiva"
                />
              </label>

              <label className="text-sm font-medium">
                Marca *
                <input
                  value={marca}
                  onChange={(e) => setMarca(e.target.value)}
                  required
                  className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
                  placeholder="Nombre de la marca"
                />
              </label>

              <label className="text-sm font-medium sm:col-span-2">
                Categoría *
                <input
                  value={categoria}
                  onChange={(e) => setCategoria(e.target.value)}
                  required
                  className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
                  placeholder="Ej. Ropa"
                />
              </label>

              <label className="text-sm font-medium sm:col-span-2">
                Descripción
                <textarea
                  value={descripcion}
                  onChange={(e) => setDescripcion(e.target.value)}
                  rows={3}
                  className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
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
                className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800"
              >
                + Agregar variación
              </button>
            </div>

            <div className="space-y-4">
              {variaciones.map((variacion, indice) => (
                <div key={variacion.id} className="rounded-lg border border-slate-200 p-4">
                  <div className="mb-3 flex items-center justify-between">
                    <h3 className="font-semibold">Variación {indice + 1}</h3>
                    {variaciones.length > 1 && (
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
                        value={variacion.sku}
                        onChange={(e) => actualizarVariacion(variacion.id, 'sku', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
                        placeholder="Ej. CAM-AZU-M"
                      />
                    </label>

                    <label className="text-sm font-medium">
                      Atributos *
                      <input
                        required
                        value={variacion.atributos}
                        onChange={(e) => actualizarVariacion(variacion.id, 'atributos', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
                        placeholder="Ej. Color azul, talla M"
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
                        onChange={(e) => actualizarVariacion(variacion.id, 'precio', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
                        placeholder="15000"
                      />
                    </label>

                    <label className="text-sm font-medium">
                      Disponibilidad *
                      <select
                        value={variacion.disponibilidad}
                        onChange={(e) => actualizarVariacion(variacion.id, 'disponibilidad', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"
                      >
                        <option>Disponible</option>
                        <option>No disponible</option>
                      </select>
                    </label>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 className="text-lg font-semibold">Imágenes del producto</h2>
            <p className="mt-1 text-sm text-slate-600">
              Opcional: hasta 8 imágenes JPG o PNG, máximo 5 MB por imagen.
            </p>

            <input
              type="file"
              accept="image/jpeg,image/png"
              multiple
              onChange={seleccionarImagenes}
              className="mt-4 block w-full max-w-full text-sm"
            />

            {imagenes.length > 0 && (
              <ul className="mt-3 list-inside list-disc text-sm text-slate-700">
                {imagenes.map((imagen, indice) => (
                  <li key={`${imagen.name}-${indice}`}>{imagen.name}</li>
                ))}
              </ul>
            )}

            <p className="mt-3 text-sm text-slate-600">
              Sin imágenes, el producto se considera incompleto para publicación.
            </p>
          </section>

          {mensaje && (
            <div
              role="status"
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
              onClick={() => {
                setNombre('');
                setDescripcion('');
                setMarca('');
                setCategoria('');
                setVariaciones([variacionVacia()]);
                setImagenes([]);
                setMensaje(null);
              }}
              className="rounded-lg border border-slate-300 bg-white px-5 py-3 font-semibold hover:bg-slate-50"
            >
              Limpiar formulario
            </button>

            <button
              type="submit"
              className="rounded-lg bg-sky-700 px-5 py-3 font-semibold text-white hover:bg-sky-800"
            >
              Validar producto
            </button>
          </div>
        </form>

        <p className="mt-6 text-xs text-slate-500">
          Prototipo de interfaz M3. Los datos se validan localmente y no se envían
          a la base de datos ni al servidor.
        </p>
      </div>
    </main>
  );
}