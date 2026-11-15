import { useEffect, useState } from 'react';
import { consultarComercios } from '../services/consultasOperativasApi';

/**
 * Selector de comercio por nombre para el encargado de plataforma. Si la sesión no
 * puede listar comercios (encargado de comercio), no se muestra y se usa el de la sesión.
 */
export default function SelectorComercioM7({ valor, onCambiar, onCargados }) {
  const [comercios, setComercios] = useState(null);
  const [estado, setEstado] = useState('cargando');

  useEffect(() => {
    let activo = true;
    consultarComercios().then((lista) => {
      if (!activo) return;
      setComercios(lista);
      setEstado('listo');
      onCargados?.(lista);
    }).catch((fallo) => {
      if (activo) setEstado(fallo.estado === 401 || fallo.estado === 403 ? 'oculto' : 'error');
    });
    return () => { activo = false; };
    // Solo se consulta al montar; onCargados puede cambiar en cada render.
  }, []);

  if (estado === 'oculto') return null;

  if (estado === 'error') {
    return (
      <div className="flex flex-col gap-1 text-sm">
        <label className="flex flex-col gap-1">Número de comercio
          <input type="number" min="1" step="1" value={valor} onChange={(evento) => onCambiar(evento.target.value)} className="rounded border bg-white p-2" />
        </label>
        <span className="text-xs text-red-700">No fue posible cargar los nombres de los comercios.</span>
      </div>
    );
  }

  return (
    <label className="flex flex-col gap-1 text-sm">Comercio
      <select value={valor} disabled={estado === 'cargando'} onChange={(evento) => onCambiar(evento.target.value)} className="min-w-56 rounded border bg-white p-2">
        <option value="">{estado === 'cargando' ? 'Cargando comercios...' : 'Seleccione un comercio'}</option>
        {(comercios ?? []).map((comercio) => (
          <option key={comercio.id_comercio} value={String(comercio.id_comercio)}>{comercio.nombre}</option>
        ))}
      </select>
    </label>
  );
}
