// Existencias por variación y ubicación (listarExistencias) con el aviso de stock mínimo (RN-M4-004).
// El encargado ve física − reservada = disponible, filtra las que están bajo el mínimo y fija el mínimo de cada
// existencia (fijarStockMinimo). Si el disponible queda en o bajo el mínimo, el servidor publica AlertaStockMinimo.
import { useEffect, useState } from 'react';
import { Aviso, Tarjeta, claseEntrada, formatoColones } from '../components/componentes';
import { IconoCargando, IconoCirculoCheck, IconoTrianguloAlerta } from '../components/iconos';
import { disponible, nombreSede, nombreVariacion } from '../services/inventarioApi';
import { erroresPorCampo } from '../services/errores';

function Insignia({ existencia }) {
  const d = disponible(existencia);
  if (d === 0) return <span className="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700"><IconoTrianguloAlerta className="h-3.5 w-3.5" />Agotado</span>;
  if (existencia.bajo_minimo) return <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800"><IconoTrianguloAlerta className="h-3.5 w-3.5" />Bajo mínimo ({existencia.stock_minimo})</span>;
  return <span className="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700"><IconoCirculoCheck className="h-3.5 w-3.5" />Disponible</span>;
}

function FilaExistencia({ api, existencia, sedes, alCambiar }) {
  const [minimo, setMinimo] = useState(String(existencia.stock_minimo));
  const [estado, setEstado] = useState(null);
  const [guardando, setGuardando] = useState(false);
  useEffect(() => setMinimo(String(existencia.stock_minimo)), [existencia.stock_minimo]);

  async function guardar(ev) {
    ev.preventDefault();
    if (!/^\d+$/.test(minimo.trim())) {
      setEstado({ tipo: 'error', texto: 'Escriba un número entero, 0 o mayor.' });
      return;
    }
    setGuardando(true);
    try {
      const actualizada = await api.fijarStockMinimo(existencia.id_existencia, Number(minimo.trim()));
      setEstado(actualizada.bajo_minimo
        ? { tipo: 'aviso', texto: `Mínimo guardado. El disponible (${actualizada.cantidad_disponible}) ya está en o bajo el mínimo: se envió el aviso.` }
        : { tipo: 'exito', texto: 'Mínimo guardado.' });
      alCambiar();
    } catch (falla) {
      const campos = erroresPorCampo(falla.error);
      setEstado({ tipo: 'error', texto: campos.stock_minimo ?? falla.error?.mensaje });
    } finally {
      setGuardando(false);
    }
  }

  return (
    <li className="flex flex-col rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="min-w-0 break-words font-semibold text-gray-900">{nombreVariacion(existencia)}</span>
        <Insignia existencia={existencia} />
      </div>
      <p className="mt-1 text-sm text-gray-600">{nombreSede(sedes, existencia.id_ubicacion)}</p>
      <p className="mt-1 text-sm text-gray-600">
        Física {existencia.cantidad_fisica} − reservada {existencia.cantidad_reservada} = <span className="font-bold text-gray-900">{disponible(existencia)} disponibles</span>
      </p>
      <p className="mt-1 text-xs text-gray-500">Valor del inventario {formatoColones(existencia.valor_inventario)}</p>
      <form onSubmit={guardar} className="mt-auto flex items-end gap-2 pt-2">
        <label className="min-w-0 flex-1 text-xs font-semibold text-gray-700" htmlFor={`minimo-${existencia.id_existencia}`}>
          Stock mínimo
          <input id={`minimo-${existencia.id_existencia}`} inputMode="numeric" className={claseEntrada(estado?.tipo === 'error')} value={minimo} onChange={(e) => { setMinimo(e.target.value); setEstado(null); }} />
        </label>
        <button type="submit" disabled={guardando || minimo === String(existencia.stock_minimo)}
          className="min-h-[44px] shrink-0 rounded-lg bg-brandDarkBlue px-3 text-sm font-bold text-white hover:bg-brandBlue disabled:bg-gray-300 disabled:text-gray-500">
          {guardando ? <IconoCargando className="h-4 w-4 animate-spin" /> : 'Guardar'}
        </button>
      </form>
      {estado && <p role={estado.tipo === 'error' ? 'alert' : 'status'} className={`mt-1 text-xs ${estado.tipo === 'error' ? 'text-red-600' : estado.tipo === 'aviso' ? 'text-amber-800' : 'text-green-700'}`}>{estado.texto}</p>}
    </li>
  );
}

export default function ExistenciasMinimos({ api, existencias, sedes, alCambiar }) {
  const [soloBajoMinimo, setSoloBajoMinimo] = useState(false);
  const bajoMinimo = existencias.filter((e) => e.bajo_minimo);
  const filas = soloBajoMinimo ? bajoMinimo : existencias;

  return (
    <div className="space-y-4">
      {bajoMinimo.length > 0 && (
        <Aviso tipo="aviso" titulo={`${bajoMinimo.length} ${bajoMinimo.length === 1 ? 'existencia está' : 'existencias están'} en o bajo el mínimo`}>
          Conviene reponer: {bajoMinimo.map((e) => `${nombreVariacion(e)} en ${nombreSede(sedes, e.id_ubicacion)}`).join('; ')}.
        </Aviso>
      )}
      <Tarjeta>
        <label className="flex items-center gap-2 text-sm font-semibold text-gray-800">
          <input type="checkbox" className="h-5 w-5" checked={soloBajoMinimo} onChange={(e) => setSoloBajoMinimo(e.target.checked)} />
          Mostrar solo las que están bajo el mínimo
        </label>
      </Tarjeta>
      {filas.length === 0 && <Tarjeta><p className="text-sm text-gray-600">{soloBajoMinimo ? 'Ninguna existencia está bajo el mínimo.' : 'Todavía no hay existencias. Registre una entrada en Operaciones.'}</p></Tarjeta>}
      <ul className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
        {filas.map((e) => <FilaExistencia key={e.id_existencia} api={api} existencia={e} sedes={sedes} alCambiar={alCambiar} />)}
      </ul>
    </div>
  );
}
