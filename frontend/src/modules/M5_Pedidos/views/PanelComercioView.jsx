import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Ban, CircleCheck, CreditCard, FlaskConical, LoaderCircle, PackageCheck, RefreshCw, Store, Truck,
} from 'lucide-react';
import { Alerta, Boton, Encabezado, ModalConCampo } from '../components/componentes';
import { colones } from '../services/calculos';
import {
  cancelarSubpedido, confirmarPago, despacharSubpedido, entregarSubpedido, listarPedidos,
} from '../services/m5Api';
import { EtiquetaEstado } from './MisPedidosView';

// Copia de Subpedido::TRANSICIONES_PERMITIDAS (app/Models/M5_Pedidos/Subpedido.php).
// Si el backend cambia la máquina de estados, hay que actualizar esta tabla.
const TRANSICIONES_PERMITIDAS = {
  pendiente: ['pagado', 'cancelado'],
  confirmado: ['pagado', 'cancelado'],
  pagado: ['en_preparacion', 'despachado', 'cancelado'],
  en_preparacion: ['despachado'],
  despachado: ['entregado'],
  entregado: [],
  cancelado: [],
};
const normalizar = (estado) => String(estado ?? '').toLowerCase();
const puede = (estado, destino) => (TRANSICIONES_PERMITIDAS[normalizar(estado)] ?? []).includes(destino);
const textoError = (e) => `${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`;

// Aplana pedidos → subpedidos (con datos del pedido) y los agrupa por comercio.
function agruparSubpedidos(pedidos) {
  const grupos = new Map();
  pedidos.forEach((p) => {
    (p.subpedidos ?? []).forEach((s) => {
      const id = Number(s.id_comercio);
      if (!grupos.has(id)) grupos.set(id, { id_comercio: id, nombre: s.nombre_comercio ?? `Comercio #${id}`, subpedidos: [] });
      grupos.get(id).subpedidos.push({ ...s, pedido: p });
    });
  });
  return [...grupos.values()]
    .sort((a, b) => a.id_comercio - b.id_comercio)
    .map((g) => ({ ...g, subpedidos: g.subpedidos.sort((a, b) => Number(b.id_subpedido) - Number(a.id_subpedido)) }));
}

// Pantalla de demostración: simula al comercio (despachar, cancelar), al repartidor (entregar) y a M6 (pago confirmado).
export default function PanelComercioView({ onVolver }) {
  const [pedidos, setPedidos] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [recargando, setRecargando] = useState(false);
  const [error, setError] = useState(null);
  const [filtro, setFiltro] = useState('todos');

  // Peticiones en curso por subpedido (id_subpedido → acción) y mensajes por subpedido.
  const enCurso = useRef(new Set());
  const [pendientes, setPendientes] = useState({});
  const [mensajes, setMensajes] = useState({});
  const [modal, setModal] = useState(null); // { tipo: 'despachar' | 'cancelar', subpedido }

  const cargar = useCallback(async ({ silencioso = false } = {}) => {
    if (silencioso) setRecargando(true); else setCargando(true);
    setError(null);
    try {
      const r = await listarPedidos();
      setPedidos(r.pedidos ?? []);
    } catch (e) {
      setError(e);
    } finally {
      setCargando(false);
      setRecargando(false);
    }
  }, []);

  useEffect(() => { cargar(); }, [cargar]);

  const grupos = useMemo(() => agruparSubpedidos(pedidos), [pedidos]);
  const gruposVisibles = filtro === 'todos' ? grupos : grupos.filter((g) => String(g.id_comercio) === filtro);

  // Ejecuta una acción sobre un subpedido: bloquea sus botones, guarda el mensaje y recarga la lista.
  async function ejecutar(subpedido, accion, llamada) {
    const id = subpedido.id_subpedido;
    if (enCurso.current.has(id)) return;
    enCurso.current.add(id);
    setPendientes((p) => ({ ...p, [id]: accion }));
    setMensajes((m) => ({ ...m, [id]: null }));
    try {
      const r = await llamada();
      setMensajes((m) => ({ ...m, [id]: { tipo: 'exito', texto: r?.mensaje || 'Acción realizada.' } }));
    } catch (e) {
      // 409: transición inválida (ERR-M5-002 / ERR-M5-003); se muestra el mensaje de la API.
      setMensajes((m) => ({ ...m, [id]: { tipo: 'error', texto: textoError(e) } }));
    } finally {
      enCurso.current.delete(id);
      setPendientes((p) => { const n = { ...p }; delete n[id]; return n; });
      setModal(null);
      cargar({ silencioso: true });
    }
  }

  const cerrarModal = useCallback(() => setModal(null), []);

  function renderSubpedido(s) {
    const estado = s.estado_subpedido;
    const pedidoPendiente = ['pendiente', 'confirmado'].includes(normalizar(s.pedido.estado_pedido));
    const accion = pendientes[s.id_subpedido];
    const ocupado = Boolean(accion);
    const mensaje = mensajes[s.id_subpedido];

    const botones = [
      {
        clave: 'pago', texto: 'Simular pago confirmado (M6)', icono: CreditCard,
        habilitado: pedidoPendiente && puede(estado, 'pagado'),
        onClick: () => ejecutar(s, 'pago', () => confirmarPago(s.pedido.id_pedido)),
      },
      {
        clave: 'despachar', texto: 'Despachar', icono: Truck,
        habilitado: puede(estado, 'despachado'),
        onClick: () => setModal({ tipo: 'despachar', subpedido: s }),
      },
      {
        clave: 'entregar', texto: 'Marcar entregado', icono: PackageCheck,
        habilitado: puede(estado, 'entregado'),
        onClick: () => ejecutar(s, 'entregar', () => entregarSubpedido(s.id_subpedido)),
      },
      {
        clave: 'cancelar', texto: 'Cancelar subpedido', icono: Ban, peligro: true,
        habilitado: puede(estado, 'cancelado'),
        onClick: () => setModal({ tipo: 'cancelar', subpedido: s }),
      },
    ];

    return (
      <li key={s.id_subpedido} className="py-4 first:pt-0 last:pb-0 space-y-3">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            <p className="text-sm font-bold text-gray-900">Pedido #{s.pedido.id_pedido} · Subpedido #{s.id_subpedido}</p>
            <p className="text-xs text-gray-500">Total: <span className="font-semibold text-gray-800">{colones(Number(s.total ?? 0))}</span></p>
            {s.guia_seguimiento && (
              <p className="text-xs text-gray-600 mt-0.5 flex items-center gap-1">
                <Truck className="w-3.5 h-3.5 text-brandBlue" aria-hidden="true" />
                Guía: <span className="font-mono font-semibold break-all">{s.guia_seguimiento}</span>
              </p>
            )}
          </div>
          <EtiquetaEstado estado={estado} />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
          {botones.map((b) => {
            const enEste = accion === b.clave;
            const Icono = enEste ? LoaderCircle : b.icono;
            return (
              <button
                key={b.clave}
                type="button"
                disabled={!b.habilitado || ocupado}
                onClick={b.onClick}
                className={`min-h-11 px-3 rounded-lg border text-xs sm:text-sm font-semibold inline-flex items-center justify-center gap-2 transition disabled:bg-gray-100 disabled:text-gray-400 disabled:border-gray-200 disabled:cursor-not-allowed ${
                  b.peligro ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-gray-200 text-brandDarkBlue hover:bg-blue-50'
                }`}
              >
                <Icono className={`w-4 h-4 shrink-0 ${enEste ? 'animate-spin' : ''}`} aria-hidden="true" />
                {b.texto}
              </button>
            );
          })}
        </div>

        {mensaje && (
          <p role={mensaje.tipo === 'error' ? 'alert' : 'status'}
            className={`text-xs font-medium flex items-start gap-1.5 ${mensaje.tipo === 'error' ? 'text-red-700' : 'text-green-700'}`}>
            {mensaje.tipo === 'error'
              ? <Ban className="w-3.5 h-3.5 mt-0.5 shrink-0" aria-hidden="true" />
              : <CircleCheck className="w-3.5 h-3.5 mt-0.5 shrink-0" aria-hidden="true" />}
            {mensaje.texto}
          </p>
        )}
      </li>
    );
  }

  let contenido;
  if (cargando) {
    contenido = (
      <div className="py-16 flex flex-col items-center gap-3 text-gray-500" aria-live="polite">
        <LoaderCircle className="w-8 h-8 animate-spin text-brandBlue" aria-hidden="true" />
        <p className="text-sm font-medium">Cargando subpedidos…</p>
      </div>
    );
  } else if (error) {
    contenido = (
      <div className="space-y-4">
        <Alerta tipo="error" titulo="No se pudieron cargar los pedidos">{textoError(error)}</Alerta>
        <Boton variante="oscuro" icono={RefreshCw} onClick={() => cargar()}>Reintentar</Boton>
      </div>
    );
  } else if (grupos.length === 0) {
    contenido = <Alerta tipo="info" titulo="Sin subpedidos">Todavía no hay pedidos para gestionar.</Alerta>;
  } else {
    contenido = (
      <>
        <div className="flex flex-col sm:flex-row sm:items-end gap-3 justify-between">
          <div className="sm:w-72">
            <label htmlFor="filtro-comercio" className="text-xs font-medium text-gray-600 block mb-1">Comercio</label>
            <select id="filtro-comercio" value={filtro} onChange={(e) => setFiltro(e.target.value)}
              className="w-full min-h-11 border border-gray-200 rounded-lg px-3 text-sm bg-white focus:outline-none focus:border-brandBlue">
              <option value="todos">Todos los comercios</option>
              {grupos.map((g) => <option key={g.id_comercio} value={String(g.id_comercio)}>{g.nombre}</option>)}
            </select>
          </div>
          <Boton variante="oscuro" icono={recargando ? LoaderCircle : RefreshCw} disabled={recargando} onClick={() => cargar({ silencioso: true })}>
            {recargando ? 'Actualizando…' : 'Actualizar'}
          </Boton>
        </div>

        {gruposVisibles.map((g) => (
          <section key={g.id_comercio} aria-labelledby={`comercio-${g.id_comercio}`} className="bg-white rounded-xl shadow-xs border border-gray-200 p-4 sm:p-5">
            <div className="flex items-center justify-between gap-2 pb-3 mb-4 border-b border-gray-100">
              <h2 id={`comercio-${g.id_comercio}`} className="text-sm sm:text-base font-bold text-gray-900 flex items-center gap-2">
                <Store className="w-4 h-4 text-brandDarkBlue" aria-hidden="true" /> {g.nombre}
              </h2>
              <span className="text-xs font-semibold text-gray-500">
                {g.subpedidos.length} {g.subpedidos.length === 1 ? 'subpedido' : 'subpedidos'}
              </span>
            </div>
            <ul className="divide-y divide-gray-100">{g.subpedidos.map(renderSubpedido)}</ul>
          </section>
        ))}
      </>
    );
  }

  const enModal = modal ? pendientes[modal.subpedido.id_subpedido] : null;

  return (
    <>
      <Encabezado titulo="Panel del comercio (demo)" onVolver={onVolver} />
      <main className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-dashed border-amber-300 bg-amber-50/60 p-4">
          <FlaskConical className="w-5 h-5 shrink-0 text-amber-700 mt-0.5" aria-hidden="true" />
          <p className="text-sm font-semibold text-amber-900">
            Pantalla de demostración: simula las acciones del comercio, del repartidor y la confirmación de pago de M6
          </p>
        </div>
        {contenido}
      </main>

      <ModalConCampo
        abierto={modal?.tipo === 'despachar'}
        titulo={`Despachar subpedido #${modal?.subpedido.id_subpedido ?? ''}`}
        descripcion="Registra la salida del subpedido. La guía de seguimiento es opcional."
        etiquetaCampo="Guía de seguimiento"
        placeholder="Ej.: CR123456789"
        textoConfirmar="Despachar"
        textoOcupado="Despachando…"
        icono={Truck}
        ocupado={enModal === 'despachar'}
        onCerrar={cerrarModal}
        onConfirmar={(guia) => ejecutar(modal.subpedido, 'despachar',
          () => despacharSubpedido(modal.subpedido.id_subpedido, { guia_transporte: guia || null }))}
      />
      <ModalConCampo
        abierto={modal?.tipo === 'cancelar'}
        titulo={`Cancelar subpedido #${modal?.subpedido.id_subpedido ?? ''}`}
        descripcion="Se liberan las reservas de este subpedido y, si estaba pagado, se solicita el reembolso parcial (SC-02)."
        etiquetaCampo="Motivo"
        placeholder="Ej.: Producto sin existencias en bodega"
        obligatorio
        multilinea
        peligro
        textoConfirmar="Cancelar subpedido"
        textoOcupado="Cancelando…"
        icono={Ban}
        ocupado={enModal === 'cancelar'}
        onCerrar={cerrarModal}
        onConfirmar={(motivo) => ejecutar(modal.subpedido, 'cancelar',
          () => cancelarSubpedido(modal.subpedido.id_subpedido, {
            motivo,
            requiere_reembolso: normalizar(modal.subpedido.estado_subpedido) === 'pagado',
          }))}
      />
    </>
  );
}
