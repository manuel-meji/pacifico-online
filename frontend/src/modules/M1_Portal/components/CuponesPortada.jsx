import { useEffect, useRef, useState } from 'react';
import Icono from './IconoComercio';
import { colones, fechaCorta } from '../services/formato';

// Cupones globales vigentes de la plataforma (GET /cupones/vigentes, contrato M1 1.6.0).
// Cada cupón trae { codigo, descripcion, tipo_descuento, valor, monto_minimo_compra, fecha_fin, limite_usos_por_cliente }.
// El código se copia con un botón para pegarlo en el checkout de M5.

export const textoValorCupon = (cupon) => (cupon.tipo_descuento === 'porcentaje' ? `${Number(cupon.valor)} %` : colones(cupon.valor));

function TarjetaCupon({ cupon }) {
  const [copiado, setCopiado] = useState(false);
  const temporizador = useRef(null);

  useEffect(() => () => clearTimeout(temporizador.current), []);

  async function copiar() {
    try {
      await navigator.clipboard.writeText(cupon.codigo);
      setCopiado(true);
      clearTimeout(temporizador.current);
      temporizador.current = setTimeout(() => setCopiado(false), 2000);
    } catch {
      // Sin permiso para el portapapeles: el código queda visible para copiarlo a mano.
    }
  }

  return (
    <article className="flex h-full flex-col rounded-xl border-2 border-dashed border-brandGreen/60 bg-white p-4 shadow-sm">
      <div className="flex items-start gap-3">
        <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-green-50 text-brandGreen"><Icono nombre="cupon" className="h-6 w-6" /></span>
        <div className="min-w-0">
          <p className="text-2xl font-extrabold leading-tight text-brandDarkBlue">{textoValorCupon(cupon)}<span className="ml-1 text-sm font-semibold text-gray-600">de descuento</span></p>
          {cupon.descripcion && <p className="mt-0.5 line-clamp-2 text-sm text-gray-700">{cupon.descripcion}</p>}
        </div>
      </div>

      <ul className="mt-3 space-y-1 text-xs text-gray-600">
        {cupon.monto_minimo_compra != null && <li>Compra mínima de {colones(cupon.monto_minimo_compra)}</li>}
        <li className="flex items-center gap-1"><Icono nombre="reloj" className="h-3.5 w-3.5" /> Válido hasta el {fechaCorta(cupon.fecha_fin)}</li>
        {cupon.limite_usos_por_cliente != null && (
          <li>{cupon.limite_usos_por_cliente === 1 ? 'Un uso por cliente' : `Hasta ${cupon.limite_usos_por_cliente} usos por cliente`}</li>
        )}
      </ul>

      <div className="mt-auto flex items-center gap-2 pt-4">
        <code className="min-w-0 flex-1 truncate rounded-lg bg-gray-100 px-3 py-2 text-center font-mono text-sm font-bold tracking-wider text-gray-800">{cupon.codigo}</code>
        <button type="button" onClick={copiar} aria-label={`Copiar el código ${cupon.codigo}`}
          className="flex min-h-[40px] shrink-0 items-center gap-1.5 rounded-lg bg-brandDarkBlue px-3 text-sm font-semibold text-white transition hover:bg-brandBlue">
          <Icono nombre={copiado ? 'check' : 'copiar'} className="h-4 w-4" />
          {copiado ? 'Copiado' : 'Copiar'}
        </button>
      </div>
      <span role="status" className="sr-only">{copiado ? `Código ${cupon.codigo} copiado` : ''}</span>
    </article>
  );
}

export default function CuponesPortada({ cupones }) {
  return (
    <section className="mt-10" aria-labelledby="titulo-cupones">
      <h2 id="titulo-cupones" className="mb-1 flex items-center gap-2 text-2xl font-bold text-brandDarkBlue">
        <Icono nombre="cupon" className="h-6 w-6 text-brandGreen" /> Cupones disponibles
      </h2>
      <p className="mb-4 text-sm text-gray-600">Copiá el código y aplicalo en el carrito al pagar.</p>
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {cupones.map((cupon) => (
          <li key={cupon.codigo} className="min-w-0"><TarjetaCupon cupon={cupon} /></li>
        ))}
      </ul>
    </section>
  );
}
