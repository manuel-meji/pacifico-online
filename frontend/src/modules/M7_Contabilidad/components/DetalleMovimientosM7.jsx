import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { consultarLibroMayor } from '../services/libroMayorApi';
import { consultarMovimientosCentro } from '../services/consultasOperativasApi';
import DetalleAsientoM7, { numeroAsiento } from './DetalleAsientoM7';

function moneda(valor) {
  return new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'CRC' }).format(Number(valor));
}

function fecha(valor) {
  const [anio, mes, dia] = valor.slice(0, 10).split('-');
  return `${dia}/${mes}/${anio}`;
}

export default function DetalleMovimientosM7({ seleccion, onCerrar }) {
  const dialogo = useRef(null);
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');
  const [pagina, setPagina] = useState(1);
  const [intento, setIntento] = useState(0);
  const [asientoAbierto, setAsientoAbierto] = useState(null);
  const esCentro = seleccion.tipo === 'centro';
  const { desde, hasta } = seleccion.periodo ?? {};
  const periodoCentro = desde && hasta ? `del ${desde} al ${hasta}` : desde ? `desde ${desde}` : hasta ? `hasta ${hasta}` : '';

  useLayoutEffect(() => {
    const elemento = dialogo.current;
    elemento.showModal();
    return () => elemento.close();
  }, []);

  useEffect(() => {
    let activo = true;
    const consulta = esCentro
      ? consultarMovimientosCentro(seleccion.id, seleccion.idComercio, seleccion.periodo)
      : consultarLibroMayor({ id_cuenta_contable: seleccion.id, id_comercio: seleccion.idComercio });
    consulta.then((resultado) => {
      if (!resultado || !Array.isArray(resultado.movimientos)) {
        throw new Error('El servidor no devolvió el detalle contable esperado.');
      }
      if (activo) setDatos(resultado);
    }).catch((fallo) => {
      if (activo) setError(fallo.message || 'No fue posible consultar los movimientos.');
    });
    return () => { activo = false; };
  }, [seleccion, esCentro, intento]);

  const paginas = Math.max(1, Math.ceil((datos?.movimientos.length ?? 0) / 10));
  const movimientos = datos?.movimientos.slice((pagina - 1) * 10, pagina * 10) ?? [];

  return (
    <dialog ref={dialogo} onCancel={onCerrar} onKeyDown={(evento) => {
      if (evento.key === 'Escape') {
        evento.preventDefault();
        onCerrar();
      }
    }} aria-labelledby="titulo-detalle-m7"
      className="m-auto max-h-[90vh] w-[calc(100%_-_2rem)] max-w-6xl overflow-y-auto rounded-xl bg-white p-5 text-gray-800 shadow-xl backdrop:bg-black/50">
      <header className="mb-4 flex items-start justify-between gap-4">
        <div>
          <h2 id="titulo-detalle-m7" className="text-xl font-bold text-[#1e3a8a]">Movimientos de {seleccion.titulo}</h2>
          <p className="mt-2 text-sm text-gray-600">{esCentro
            ? `${periodoCentro ? `Período ${periodoCentro}` : 'Acumulado histórico'} de asientos registrados en cuentas de ingresos, costos y gastos ${seleccion.id === null ? 'sin centro de costo asignado' : 'imputados a este centro'}.`
            : 'Acumulado histórico de asientos registrados de esta cuenta.'}</p>
        </div>
        <button autoFocus onClick={onCerrar} className="rounded border p-2">Cerrar detalle</button>
      </header>
      {error ? (
        <div>
          <p role="alert" className="text-red-700">{error}</p>
          <button onClick={() => { setError(''); setDatos(null); setIntento(intento + 1); }} className="mt-3 rounded border p-2">Reintentar detalle</button>
        </div>
      ) : !datos ? <p role="status">Cargando movimientos...</p> : (
        <>
          <section aria-label="Totales del detalle" className="mb-4 grid gap-3 rounded-lg bg-gray-50 p-4 sm:grid-cols-3">
            {esCentro ? (
              <>
                <p>Ingresos: <strong>{moneda(datos.ingresos)}</strong></p>
                <p>Costos: <strong>{moneda(datos.costos)}</strong></p>
                <p>Utilidad: <strong className={Number(datos.utilidad) < 0 ? 'text-red-700' : undefined}>{moneda(datos.utilidad)}</strong></p>
              </>
            ) : (
              <>
                <p>Total debe: <strong>{moneda(datos.total_debe)}</strong></p>
                <p>Total haber: <strong>{moneda(datos.total_haber)}</strong></p>
                <p>Saldo final: <strong>{moneda(datos.saldo_final)}</strong></p>
              </>
            )}
          </section>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#1e3a8a] text-white"><tr>
                <th className="p-3">Fecha</th><th className="p-3">Asiento</th><th className="p-3">Tipo</th>
                {esCentro && <th className="p-3">Cuenta</th>}
                <th className="p-3">Descripción</th>
                {esCentro && <th className="p-3">Referencias</th>}
                <th className="p-3 text-right">Debe</th><th className="p-3 text-right">Haber</th><th className="p-3 text-right">{esCentro ? 'Utilidad acumulada' : 'Acumulado'}</th>
              </tr></thead>
              <tbody>{movimientos.map((movimiento) => <tr key={`${movimiento.id_asiento}-${movimiento.numero_linea}`} className="border-b">
                <td className="whitespace-nowrap p-3">{fecha(movimiento.fecha)}</td>
                <td className="p-3">
                  <button type="button" onClick={() => setAsientoAbierto(movimiento.id_asiento)}
                    aria-label={`Ver asiento ${movimiento.asiento || numeroAsiento(movimiento.id_asiento)}`}
                    className="font-semibold text-[#1e3a8a] underline underline-offset-2 hover:text-blue-700">
                    {movimiento.asiento || numeroAsiento(movimiento.id_asiento)}
                  </button>
                </td>
                <td className="p-3">{movimiento.tipo}</td>
                {esCentro && <td className="p-3">{movimiento.codigo_cuenta} · {movimiento.nombre_cuenta}</td>}
                <td className="p-3">{movimiento.descripcion || 'Sin descripción'}</td>
                {esCentro && <td className="p-3">{
                  [['Pedido', movimiento.id_pedido], ['Subpedido', movimiento.id_subpedido], ['Pago', movimiento.id_pago]]
                    .filter(([, id]) => id !== null && id !== undefined)
                    .map(([nombre, id]) => `${nombre} #${id}`).join(' · ') || 'Sin referencias'
                }</td>}
                <td className="p-3 text-right">{moneda(movimiento.debe)}</td><td className="p-3 text-right">{moneda(movimiento.haber)}</td>
                <td className="p-3 text-right">{moneda(movimiento.saldo)}</td>
              </tr>)}</tbody>
            </table>
          </div>
          {datos.movimientos.length === 0 && <p className="py-4">{esCentro && periodoCentro ? 'Sin movimientos en el período consultado.' : `No hay movimientos registrados para ${esCentro ? 'este centro' : 'esta cuenta'}.`}</p>}
          <nav aria-label="Paginación del detalle" className="mt-4 flex items-center gap-3">
            <button disabled={pagina === 1} onClick={() => setPagina(pagina - 1)} className="rounded border p-2 disabled:opacity-50">Anterior</button>
            <span>Página {pagina} de {paginas}</span>
            <button disabled={pagina === paginas} onClick={() => setPagina(pagina + 1)} className="rounded border p-2 disabled:opacity-50">Siguiente</button>
          </nav>
        </>
      )}
      {asientoAbierto !== null && (
        <DetalleAsientoM7 idAsiento={asientoAbierto} idComercio={seleccion.idComercio} onCerrar={() => setAsientoAbierto(null)} />
      )}
    </dialog>
  );
}
