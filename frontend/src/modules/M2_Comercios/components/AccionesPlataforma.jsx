import { useState } from 'react';
import { ArrowLeft, ChevronLeft, ChevronRight } from 'lucide-react';
import { AreaTexto, AvisoError, Boton, C, Seleccion, erroresDeCampo, mensajeDe, useSesion } from './comunes';

export const POR_PAGINA = 10;

export function explicar(resultado, mensajeDeError) {
  const base = mensajeDeError(resultado);
  if (resultado?.estado !== 422) return base;
  const detalles = Object.values(resultado.error?.detalles || {}).flat();
  return detalles.length ? detalles.join(' ') : base;
}

export const Volver = ({ onClick, texto = 'Volver al listado' }) => (
  <button type="button" onClick={onClick}
    className={`mb-4 inline-flex items-center gap-1 text-sm font-semibold ${C.marinoTexto} hover:underline`}>
    <ArrowLeft size={16} /> {texto}
  </button>
);

export const FiltroEstado = ({ etiqueta, valor, opciones, onCambiar }) => (
  <div className="w-full sm:w-56">
    <Seleccion etiqueta={etiqueta} value={valor} onChange={(e) => onCambiar(e.target.value)}>
      <option value="">Todos</option>
      {opciones.map((o) => <option key={o} value={o}>{o}</option>)}
    </Seleccion>
  </div>
);

export function Paginacion({ pagina, total, onCambiar }) {
  const paginas = Math.max(1, Math.ceil(total / POR_PAGINA));
  if (total <= POR_PAGINA) return null;
  return (
    <div className="mt-4 flex items-center justify-between text-sm text-slate-600">
      <Boton variante="neutro" className="py-2" disabled={pagina <= 1} onClick={() => onCambiar(pagina - 1)}>
        <ChevronLeft size={16} /> Anterior
      </Boton>
      <span>Página {pagina} de {paginas}</span>
      <Boton variante="neutro" className="py-2" disabled={pagina >= paginas} onClick={() => onCambiar(pagina + 1)}>
        Siguiente <ChevronRight size={16} />
      </Boton>
    </div>
  );
}


export function ConfirmarAccion({ pregunta, aviso, pideMotivo = false, variante = 'primario', textoConfirmar = 'Confirmar', onConfirmar, onCancelar }) {
  const { mensajeDeError } = useSesion();
  const [motivo, setMotivo] = useState('');
  const [error, setError] = useState('');
  const [errorMotivo, setErrorMotivo] = useState('');
  const [enviando, setEnviando] = useState(false);

  const confirmar = async () => {
    setError('');
    setErrorMotivo('');
    if (pideMotivo && !motivo.trim()) {
      setErrorMotivo('El motivo es obligatorio.');
      return;
    }
    setEnviando(true);
    const r = await onConfirmar(motivo.trim());
    setEnviando(false);
    if (r && !r.ok) {
      const motivoServidor = mensajeDe(erroresDeCampo(r), 'motivo');
      if (motivoServidor) setErrorMotivo(motivoServidor);
      else setError(explicar(r, mensajeDeError));
    }
  };

  return (
    <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
      <p className="text-sm font-semibold text-slate-800">{pregunta}</p>
      {aviso && <p className="mt-2 text-sm text-amber-800">{aviso}</p>}
      {pideMotivo && (
        <AreaTexto etiqueta="Motivo (obligatorio)" value={motivo} error={errorMotivo}
          onChange={(e) => setMotivo(e.target.value)} />
      )}
      <div className="mt-3 grid grid-cols-2 gap-2">
        <Boton variante={variante} onClick={confirmar} disabled={enviando}>{enviando ? 'Enviando…' : textoConfirmar}</Boton>
        <Boton variante="neutro" onClick={onCancelar} disabled={enviando}>Cancelar</Boton>
      </div>
      <AvisoError mensaje={error} />
    </div>
  );
}