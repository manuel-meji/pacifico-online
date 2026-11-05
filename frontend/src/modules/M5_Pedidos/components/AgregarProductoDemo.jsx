import { useState } from 'react';
import { FlaskConical, LoaderCircle, Plus } from 'lucide-react';
import { Alerta } from './componentes';

// HERRAMIENTA DE DEMOSTRACIÓN. El catálogo real pertenece a M3; aquí solo se listan
// id_variacion conocidos para probar POST /api/m5/v1/carrito/items.
// Los nombres vienen del doble de M3 de M5 (app/Services/M5_Pedidos/Dobles/CatalogoStub.php);
// con el CatalogoService actual de M3 cualquier id <= 1000 responde como "Producto Ejemplo".
const VARIACIONES_PRUEBA = [
  { id_variacion: 1, etiqueta: 'Variación 1 · Producto de prueba (SKU-001)' },
  { id_variacion: 2, etiqueta: 'Variación 2 · Café Tarrazú Reserva Especial' },
  { id_variacion: 3, etiqueta: 'Variación 3 · Miel Orgánica del Pacífico' },
  { id_variacion: 1001, etiqueta: 'Variación 1001 · Inexistente (prueba de error)' },
];

export default function AgregarProductoDemo({ ocupado, aviso, onAgregar }) {
  const [idVariacion, setIdVariacion] = useState(VARIACIONES_PRUEBA[0].id_variacion);
  const [cantidad, setCantidad] = useState('1');

  const cantidadNumero = Number(cantidad);
  const cantidadValida = Number.isInteger(cantidadNumero) && cantidadNumero >= 1;

  const enviar = (e) => {
    e.preventDefault();
    if (!cantidadValida || ocupado) return;
    onAgregar(idVariacion, cantidadNumero);
  };

  return (
    <section aria-labelledby="demo-titulo" className="bg-amber-50/60 border border-dashed border-amber-300 rounded-xl p-4 space-y-3">
      <div className="flex items-start gap-2">
        <FlaskConical className="w-4 h-4 mt-0.5 shrink-0 text-amber-700" aria-hidden="true" />
        <div>
          <h2 id="demo-titulo" className="text-sm font-bold text-amber-900">Productos de prueba</h2>
          <p className="text-xs text-amber-800">
            Herramienta de demostración: el catálogo real es de M3. Sirve para agregar ítems al carrito mientras se integra.
          </p>
        </div>
      </div>

      <form onSubmit={enviar} className="flex flex-col sm:flex-row sm:items-end gap-3">
        <div className="flex-1 min-w-0">
          <label htmlFor="demo-variacion" className="text-xs font-medium text-gray-600 block mb-1">Variación</label>
          <select id="demo-variacion" value={idVariacion} onChange={(e) => setIdVariacion(Number(e.target.value))}
            className="w-full min-h-11 border border-gray-200 rounded-lg px-3 text-sm bg-white focus:outline-none focus:border-brandBlue">
            {VARIACIONES_PRUEBA.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{v.etiqueta}</option>)}
          </select>
        </div>
        <div className="sm:w-24">
          <label htmlFor="demo-cantidad" className="text-xs font-medium text-gray-600 block mb-1">Cantidad</label>
          <input id="demo-cantidad" type="number" min="1" step="1" value={cantidad} onChange={(e) => setCantidad(e.target.value)}
            className={`w-full min-h-11 border rounded-lg px-3 text-sm bg-white focus:outline-none ${cantidadValida ? 'border-gray-200 focus:border-brandBlue' : 'border-red-400 focus:border-red-600'}`} />
        </div>
        <button type="submit" disabled={!cantidadValida || ocupado}
          className="min-h-11 px-5 rounded-lg bg-brandDarkBlue hover:bg-brandBlue text-white text-sm font-bold transition inline-flex items-center justify-center gap-2 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed">
          {ocupado ? <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Plus className="w-4 h-4" aria-hidden="true" />}
          {ocupado ? 'Agregando…' : 'Agregar'}
        </button>
      </form>
      {!cantidadValida && <p role="alert" className="text-xs text-red-600">La cantidad debe ser un número entero mayor o igual a 1.</p>}
      {aviso && <Alerta tipo={aviso.tipo} titulo={aviso.titulo}>{aviso.texto}</Alerta>}
    </section>
  );
}
