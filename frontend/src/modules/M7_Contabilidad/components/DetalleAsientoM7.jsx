import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { consultarAsiento } from '../services/consultasOperativasApi';

function moneda(valor) {
  return new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'CRC' }).format(Number(valor));
}

function fecha(valor) {
  const [anio, mes, dia] = valor.slice(0, 10).split('-');
  return `${dia}/${mes}/${anio}`;
}

export function numeroAsiento(idAsiento) {
  return `ASI-${String(idAsiento).padStart(4, '0')}`;
}

const estilosEstado = {
  REGISTRADO: 'bg-green-100 text-green-800',
  PENDIENTE: 'bg-amber-100 text-amber-800',
  ANULADO: 'bg-red-100 text-red-800',
};

export default function DetalleAsientoM7({ idAsiento, idComercio, onCerrar }) {
  const dialogo = useRef(null);
  const [asiento, setAsiento] = useState(null);
  const [error, setError] = useState('');
  const [intento, setIntento] = useState(0);

  useLayoutEffect(() => {
    const elemento = dialogo.current;
    elemento.showModal();
    return () => elemento.close();
  }, []);

  useEffect(() => {
    let activo = true;
    consultarAsiento(idAsiento, idComercio).then((resultado) => {
      if (!resultado || !Array.isArray(resultado.lineas)) {
        throw new Error('El servidor no devolvió el asiento esperado.');
      }
      if (activo) setAsiento(resultado);
    }).catch((fallo) => {
      if (activo) setError(fallo.message || 'No fue posible consultar el asiento.');
    });
    return () => { activo = false; };
  }, [idAsiento, idComercio, intento]);

  const referencias = asiento ? [
    ['Pedido', asiento.id_pedido], ['Subpedido', asiento.id_subpedido], ['Pago', asiento.id_pago],
    ['Asiento original', asiento.id_asiento_original && numeroAsiento(asiento.id_asiento_original)],
  ].filter(([, valor]) => valor !== null && valor !== undefined) : [];

  return (
    <dialog ref={dialogo} onCancel={onCerrar} onKeyDown={(evento) => {
      if (evento.key === 'Escape') {
        evento.preventDefault();
        evento.stopPropagation();
        onCerrar();
      }
    }} aria-labelledby="titulo-asiento-m7"
      className="m-auto max-h-[90vh] w-[calc(100%_-_2rem)] max-w-5xl overflow-y-auto rounded-xl bg-white p-5 text-gray-800 shadow-xl backdrop:bg-black/50">
      <header className="mb-4 flex items-start justify-between gap-4">
        <div>
          <h2 id="titulo-asiento-m7" className="text-xl font-bold text-[#1e3a8a]">Asiento {numeroAsiento(idAsiento)}</h2>
          {asiento && <p className="mt-1 text-sm text-gray-600">{asiento.descripcion || 'Sin descripción'}</p>}
        </div>
        <button autoFocus onClick={onCerrar} className="rounded border p-2">Cerrar asiento</button>
      </header>
      {error ? (
        <div>
          <p role="alert" className="text-red-700">{error}</p>
          <button onClick={() => { setError(''); setAsiento(null); setIntento(intento + 1); }} className="mt-3 rounded border p-2">Reintentar asiento</button>
        </div>
      ) : !asiento ? <p role="status">Cargando asiento...</p> : (
        <>
          <dl aria-label="Datos del asiento" className="mb-4 grid gap-3 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-4">
            <div><dt className="text-gray-500">Fecha</dt><dd className="font-semibold">{fecha(asiento.fecha)}</dd></div>
            <div><dt className="text-gray-500">Tipo</dt><dd className="font-semibold">{asiento.tipo}</dd></div>
            <div><dt className="text-gray-500">Estado</dt><dd>
              <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${estilosEstado[asiento.estado] ?? 'bg-gray-100 text-gray-800'}`}>{asiento.estado}</span>
            </dd></div>
            <div><dt className="text-gray-500">Origen</dt><dd className="font-semibold">{asiento.tipo_evento || '—'}</dd></div>
            <div className="sm:col-span-4"><dt className="text-gray-500">Referencias</dt><dd className="font-semibold">
              {referencias.map(([nombre, valor]) => `${nombre} ${typeof valor === 'number' ? `#${valor}` : valor}`).join(' · ') || 'Sin referencias'}
            </dd></div>
          </dl>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#1e3a8a] text-white"><tr>
                <th className="p-3">Línea</th><th className="p-3">Cuenta</th><th className="p-3">Centro de costo</th>
                <th className="p-3">Descripción</th><th className="p-3 text-right">Debe</th><th className="p-3 text-right">Haber</th>
              </tr></thead>
              <tbody>{asiento.lineas.map((linea) => <tr key={linea.numero_linea} className="border-b">
                <td className="p-3">{linea.numero_linea}</td>
                <td className="p-3">{linea.codigo_cuenta} · {linea.nombre_cuenta}</td>
                <td className="p-3">{linea.centro ? `${linea.centro.codigo} · ${linea.centro.nombre}` : '—'}</td>
                <td className="p-3">{linea.descripcion || '—'}</td>
                <td className="p-3 text-right">{Number(linea.debe) ? moneda(linea.debe) : '—'}</td>
                <td className="p-3 text-right">{Number(linea.haber) ? moneda(linea.haber) : '—'}</td>
              </tr>)}</tbody>
              <tfoot><tr className="font-semibold">
                <td className="p-3" colSpan={4}>Totales</td>
                <td className="p-3 text-right">{moneda(asiento.total_debe)}</td>
                <td className="p-3 text-right">{moneda(asiento.total_haber)}</td>
              </tr></tfoot>
            </table>
          </div>
          <p className={`mt-3 text-sm font-semibold ${asiento.cuadrado ? 'text-green-700' : 'text-red-700'}`}>
            {asiento.cuadrado ? 'Asiento cuadrado: el debe y el haber coinciden.' : 'Asiento descuadrado: el debe y el haber no coinciden.'}
          </p>
        </>
      )}
    </dialog>
  );
}
