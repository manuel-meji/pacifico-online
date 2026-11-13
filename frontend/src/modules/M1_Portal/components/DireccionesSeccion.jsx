import { useCallback, useEffect, useState } from 'react';
import { Aviso, BotonPrincipal, CampoDireccion } from './componentes';
import { cambiarCampo, camposDireccion, datosDireccion, validarDireccion } from '../services/camposDireccion';
import { actualizarDireccion, agregarDireccion, listarDirecciones } from '../services/m1Api';

const vacio = Object.fromEntries(camposDireccion.map((c) => [c.id, '']));

// ERR-005 trae `detalles` por campo (por ejemplo, alias repetido o la principal desmarcada).
function erroresDelServidor(error) {
  const errores = {};
  for (const [campo, mensaje] of Object.entries(error.detalles || {})) {
    errores[campo] = Array.isArray(mensaje) ? mensaje[0] : mensaje;
  }
  return Object.keys(errores).length ? errores : { general: error.mensaje };
}

const enTexto = (d) => `${d.otras_senas}, ${d.distrito}, ${d.canton}, ${d.provincia}`;

function BotonSecundario({ children, ...props }) {
  return (
    <button type="button" {...props}
      className="min-h-[44px] rounded-lg border border-brandDarkBlue px-3 py-2 text-sm font-medium text-brandDarkBlue transition duration-200 enabled:hover:bg-brandLightBg focus:outline-none focus:ring-2 focus:ring-brandBlue focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
      {children}
    </button>
  );
}

// Formulario para agregar o editar una dirección. `original` es null al agregar.
function FormularioDireccion({ original, onGuardar, onCancelar }) {
  const [f, setF] = useState(original ? { ...vacio, ...original } : vacio);
  const [principal, setPrincipal] = useState(original ? original.es_principal : false);
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const eraPrincipal = !!original?.es_principal;
  const cambiar = (k) => (valor) => setF((anterior) => cambiarCampo(anterior, k, valor));
  const prefijo = original ? `dir-${original.id_direccion_cliente}` : 'dir-nueva';
  const enfocarPrimerError = (errs) => {
    const campo = camposDireccion.find((c) => errs[c.id]);
    if (campo) document.getElementById(`${prefijo}-${campo.id}`)?.focus();
  };

  async function enviar(e) {
    e.preventDefault();
    const errs = validarDireccion(f);
    setErrores(errs);
    if (Object.keys(errs).length) { enfocarPrimerError(errs); return; }
    setEnviando(true);
    const respuesta = await onGuardar({ ...datosDireccion(f), es_principal: principal });
    setEnviando(false);
    if (respuesta.ok) return;
    const errsServidor = erroresDelServidor(respuesta.error);
    setErrores(errsServidor);
    enfocarPrimerError(errsServidor);
  }

  return (
    <form onSubmit={enviar} noValidate className="space-y-4 rounded-lg border border-gray-200 p-3 sm:p-4">
      <p className="text-sm font-bold text-gray-800">{original ? `Editar «${original.alias_direccion}»` : 'Nueva dirección'}</p>
      {errores.general && <Aviso>{errores.general}</Aviso>}
      <div className="grid gap-4 md:grid-cols-2">
        {camposDireccion.map((c) => (
          <CampoDireccion key={c.id} campo={c} id={`${prefijo}-${c.id}`} valor={f[c.id]} onCambio={cambiar(c.id)} error={errores[c.id]} formulario={f} />
        ))}
      </div>
      <div>
        <label className="flex min-h-[44px] items-center gap-2 text-sm text-gray-800">
          <input type="checkbox" checked={principal} disabled={eraPrincipal}
            onChange={(e) => setPrincipal(e.target.checked)} className="h-5 w-5 accent-brandDarkBlue" />
          Usar como dirección principal
        </label>
        {eraPrincipal && (
          <p className="text-xs text-gray-600">Es su dirección principal. Para cambiarla, marque otra como principal.</p>
        )}
        {errores.es_principal && <p className="mt-1 text-xs text-red-600 break-words">{errores.es_principal}</p>}
      </div>
      <div className="flex flex-col gap-2 sm:flex-row-reverse">
        <div className="sm:flex-1"><BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Guardando…' : 'Guardar dirección'}</BotonPrincipal></div>
        <BotonSecundario onClick={onCancelar} disabled={enviando}>Cancelar</BotonSecundario>
      </div>
    </form>
  );
}

// Direcciones de envío del cliente con sesión, dentro de Mi cuenta (tarea 17). La principal va primero y es la
// direccion_envio que usan M5 y M6.
export default function DireccionesSeccion({ idCliente }) {
  const [direcciones, setDirecciones] = useState([]);
  const [carga, setCarga] = useState({ estado: 'cargando' });
  const [editando, setEditando] = useState(null); // null, 'nueva' o id_direccion_cliente
  const [aviso, setAviso] = useState(null);
  const [marcando, setMarcando] = useState(null);

  const cargar = useCallback(async () => {
    const respuesta = await listarDirecciones(idCliente);
    if (respuesta.ok) {
      setDirecciones(respuesta.datos);
      setCarga({ estado: 'listo' });
    } else {
      setCarga({ estado: 'error', error: respuesta.error });
    }
  }, [idCliente]);

  useEffect(() => { cargar(); }, [cargar]);

  async function guardar(datos) {
    const respuesta = editando === 'nueva'
      ? await agregarDireccion(idCliente, datos)
      : await actualizarDireccion(idCliente, editando, datos);
    if (respuesta.ok) {
      setAviso(editando === 'nueva' ? 'Dirección agregada.' : 'Dirección actualizada.');
      setEditando(null);
      await cargar();
    }
    return respuesta;
  }

  async function marcarPrincipal(direccion) {
    setAviso(null);
    setMarcando(direccion.id_direccion_cliente);
    const { id_direccion_cliente: _id, id_cliente: _cliente, ...datos } = direccion;
    const respuesta = await actualizarDireccion(idCliente, direccion.id_direccion_cliente, { ...datos, es_principal: true });
    setMarcando(null);
    if (respuesta.ok) {
      setAviso(`«${direccion.alias_direccion}» es ahora su dirección principal.`);
      await cargar();
    } else {
      setAviso(null);
      setCarga({ estado: 'listo', error: respuesta.error });
    }
  }

  return (
    <section aria-labelledby="titulo-direcciones" className="space-y-4 border-t border-gray-200 pt-4">
      <h2 id="titulo-direcciones" className="text-base font-bold text-gray-800">Direcciones de envío</h2>

      {carga.estado === 'cargando' && <p role="status" className="text-sm text-gray-600">Cargando direcciones…</p>}
      {carga.estado === 'error' && <Aviso>{carga.error.mensaje}</Aviso>}
      {carga.error && carga.estado === 'listo' && <Aviso>{carga.error.mensaje}</Aviso>}
      {aviso && <Aviso tipo="exito">{aviso}</Aviso>}

      {carga.estado === 'listo' && (
        <ul className="space-y-3">
          {direcciones.map((d) => (
            <li key={d.id_direccion_cliente}>
              {editando === d.id_direccion_cliente ? (
                <FormularioDireccion original={d} onGuardar={guardar} onCancelar={() => setEditando(null)} />
              ) : (
                <div className="rounded-lg border border-gray-200 p-3">
                  <p className="flex flex-wrap items-center gap-2 text-sm font-bold text-gray-800">
                    {d.alias_direccion}
                    {d.es_principal && <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Principal</span>}
                  </p>
                  <p className="mt-1 text-sm text-gray-700 break-words">{enTexto(d)}</p>
                  <p className="text-xs text-gray-600 break-words">Recibe: {d.nombre_destinatario} · {d.telefono_destinatario}</p>
                  <div className="mt-2 flex flex-wrap gap-2">
                    <BotonSecundario onClick={() => { setAviso(null); setEditando(d.id_direccion_cliente); }} disabled={editando !== null}>
                      Editar
                    </BotonSecundario>
                    {!d.es_principal && (
                      <BotonSecundario onClick={() => marcarPrincipal(d)} disabled={editando !== null || marcando !== null}>
                        {marcando === d.id_direccion_cliente ? 'Marcando…' : 'Marcar como principal'}
                      </BotonSecundario>
                    )}
                  </div>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      {carga.estado === 'listo' && direcciones.length === 0 && (
        <p className="text-sm text-gray-600">Todavía no tiene direcciones de envío.</p>
      )}

      {carga.estado === 'listo' && (editando === 'nueva'
        ? <FormularioDireccion original={null} onGuardar={guardar} onCancelar={() => setEditando(null)} />
        : (
          <div className="flex items-center gap-3 flex-wrap">
            <BotonSecundario onClick={() => { setAviso(null); setEditando('nueva'); }} disabled={editando !== null}>
              Agregar dirección
            </BotonSecundario>
            {typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('motivo') === 'checkout_dir' && (
              <a
                href="/carrito.html"
                className="text-xs font-bold text-brandDarkBlue hover:text-brandBlue hover:underline transition"
              >
                ← Volver al checkout
              </a>
            )}
          </div>
        ))}
    </section>
  );
}
