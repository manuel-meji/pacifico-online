import { useEffect, useRef, useState } from 'react';
import Icono from '../IconoComercio';
import { Aviso } from '../componentes';
import { CargandoTarjetas, ErrorVista, Vacio } from '../EstadosVista';
import useConsulta from '../../services/useConsulta';
import { colones } from '../../services/formato';
import { aIsoCostaRica, ahoraCampoCostaRica, fechaHoraCostaRica } from '../../services/horaCostaRica';
import {
  crearCuponComercio, crearCuponGlobal, desactivarCuponAdministracion, desactivarCuponComercio,
  listarCuponesAdministracion, listarCuponesComercio,
} from '../../services/cuponesApi';
import { Boton, Campo, Dialogo, erroresPorCampo, mensajeGeneral, useComercios } from './promocionesUi';

// Pestaña "Cupones" del panel de gestión (contrato M1 1.5.0, SC-11).
// El comercio crea cupones para sus productos; la plataforma crea los globales y puede desactivar cualquiera.

const ESTADOS_CUPON = {
  programado: { etiqueta: 'Programado', filtro: 'Programados', clases: 'border-blue-200 bg-blue-50 text-brandDarkBlue', icono: 'reloj' },
  vigente: { etiqueta: 'Vigente', filtro: 'Vigentes', clases: 'border-green-200 bg-green-50 text-green-700', icono: 'disponible' },
  agotado: { etiqueta: 'Agotado', filtro: 'Agotados', clases: 'border-amber-300 bg-amber-50 text-amber-900', icono: 'alerta' },
  vencido: { etiqueta: 'Vencido', filtro: 'Vencidos', clases: 'border-gray-200 bg-gray-100 text-gray-600', icono: 'reloj' },
  desactivado: { etiqueta: 'Desactivado', filtro: 'Desactivados', clases: 'border-gray-200 bg-gray-100 text-gray-600', icono: 'cerrar' },
};

const CAMPOS = ['codigo', 'descripcion', 'tipo_descuento', 'valor', 'monto_minimo_compra', 'fecha_inicio', 'fecha_fin', 'limite_usos_total', 'limite_usos_por_cliente'];
const FORMATO_CODIGO = /^[A-Z0-9][A-Z0-9_-]{2,49}$/;
const FORMATO_MONTO = /^\d+(\.\d{1,2})?$/;

// Texto claro para un error de la API de cupones (formato del núcleo).
function mensajeErrorCupon(respuesta) {
  switch (respuesta?.error?.codigo) {
    case 'ERR-M1-007':
      return 'Ya existe un cupón con ese código. Elija otro.';
    case 'ERR-004':
      return 'El cupón no existe o no pertenece a su comercio.';
    default:
      return mensajeGeneral(respuesta, CAMPOS);
  }
}

const textoDescuento = (cupon) =>
  cupon.tipo_descuento === 'porcentaje'
    ? `${Number(cupon.valor).toLocaleString('es-CR')} % de descuento`
    : `${colones(cupon.valor)} de descuento`;

const textoUsos = (cupon) =>
  cupon.limite_usos_total == null
    ? `${cupon.usos_actuales} ${cupon.usos_actuales === 1 ? 'uso' : 'usos'}, sin límite`
    : `${cupon.usos_actuales} de ${cupon.limite_usos_total} usos`;

function BadgeEstado({ estado }) {
  const info = ESTADOS_CUPON[estado] ?? ESTADOS_CUPON.vencido;
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold ${info.clases}`}>
      <Icono nombre={info.icono} className="h-3.5 w-3.5" /> {info.etiqueta}
    </span>
  );
}

// Filtro de estado como botones (en celular se desplazan de lado).
function FiltroEstado({ valor, onCambio }) {
  const opciones = [['', 'Todos'], ...Object.entries(ESTADOS_CUPON).map(([clave, info]) => [clave, info.filtro])];
  return (
    <div role="group" aria-label="Filtrar por estado" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
      {opciones.map(([clave, texto]) => (
        <button key={clave || 'todos'} type="button" aria-pressed={valor === clave} onClick={() => onCambio(clave)}
          className={`min-h-[44px] shrink-0 rounded-full border px-4 text-sm font-semibold transition ${valor === clave ? 'border-brandDarkBlue bg-brandDarkBlue text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-brandBlue'}`}>
          {texto}
        </button>
      ))}
    </div>
  );
}

// Un cupón del esquema Cupon. `ambito` es el texto de a quién aplica (solo en la vista de la plataforma).
function TarjetaCupon({ cupon, ambito, children }) {
  return (
    <article className="flex h-full min-w-0 flex-col gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h3 className="break-all font-mono text-lg font-bold tracking-wide text-gray-900">{cupon.codigo}</h3>
          {cupon.descripcion && <p className="mt-0.5 break-words text-sm text-gray-600">{cupon.descripcion}</p>}
        </div>
        <BadgeEstado estado={cupon.estado} />
      </div>

      <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
        <div className="flex items-center gap-2"><Icono nombre="cupon" className="h-4 w-4 shrink-0 text-red-600" /><dt className="sr-only">Descuento</dt><dd className="font-semibold text-gray-900">{textoDescuento(cupon)}</dd></div>
        <div className="flex items-center gap-2"><Icono nombre="usuario" className="h-4 w-4 shrink-0 text-brandBlue" /><dt className="sr-only">Usos</dt>
          <dd className="text-gray-700">{textoUsos(cupon)} · {cupon.limite_usos_por_cliente} por cliente</dd>
        </div>
        {ambito && (
          <div className="flex items-center gap-2 sm:col-span-2"><Icono nombre="tienda" className="h-4 w-4 shrink-0 text-brandBlue" /><dt className="sr-only">Aplica a</dt><dd className="break-words text-gray-700">{ambito}</dd></div>
        )}
        {cupon.monto_minimo_compra != null && (
          <div className="flex items-center gap-2 sm:col-span-2"><Icono nombre="carrito" className="h-4 w-4 shrink-0 text-brandBlue" /><dt className="sr-only">Compra mínima</dt><dd className="text-gray-700">Compra mínima de {colones(cupon.monto_minimo_compra)}</dd></div>
        )}
        <div className="flex items-start gap-2 sm:col-span-2"><Icono nombre="calendario" className="mt-0.5 h-4 w-4 shrink-0 text-brandBlue" /><dt className="sr-only">Vigencia</dt>
          <dd className="text-gray-700">{fechaHoraCostaRica(cupon.fecha_inicio)} a {fechaHoraCostaRica(cupon.fecha_fin)} <span className="text-xs text-gray-500">(hora de Costa Rica)</span></dd>
        </div>
      </dl>

      {children && <div className="mt-auto flex flex-wrap gap-2 border-t border-gray-100 pt-3">{children}</div>}
    </article>
  );
}

// Validación en pantalla con las mismas reglas del contrato (el backend vuelve a validar).
function validar(datos) {
  const errores = {};
  const valor = Number(datos.valor);

  if (!FORMATO_CODIGO.test(datos.codigo)) errores.codigo = 'De 3 a 50 letras sin tilde, números, guion o guion bajo, sin espacios.';

  if (datos.valor === '' || !Number.isFinite(valor) || valor <= 0) errores.valor = 'Debe ser un número mayor que 0.';
  else if (!FORMATO_MONTO.test(datos.valor)) errores.valor = 'Como máximo 2 decimales.';
  else if (datos.tipo_descuento === 'porcentaje' && valor >= 100) errores.valor = 'Un porcentaje debe ser menor que 100.';

  if (datos.monto_minimo_compra !== '' && !FORMATO_MONTO.test(datos.monto_minimo_compra)) errores.monto_minimo_compra = 'Debe ser un monto mayor o igual a 0, con máximo 2 decimales.';

  if (!aIsoCostaRica(datos.fecha_inicio)) errores.fecha_inicio = 'Elija la fecha y la hora de inicio.';
  if (!aIsoCostaRica(datos.fecha_fin)) errores.fecha_fin = 'Elija la fecha y la hora de fin.';
  else if (datos.fecha_inicio && datos.fecha_fin <= datos.fecha_inicio) errores.fecha_fin = 'Debe ser posterior al inicio.';
  else if (datos.fecha_fin <= ahoraCampoCostaRica()) errores.fecha_fin = 'Debe ser posterior al momento actual.';

  const total = datos.limite_usos_total === '' ? null : Number(datos.limite_usos_total);
  const porCliente = Number(datos.limite_usos_por_cliente);
  if (total !== null && (!Number.isInteger(total) || total < 1)) errores.limite_usos_total = 'Debe ser un entero mayor o igual a 1, o vacío si no hay límite.';
  if (datos.limite_usos_por_cliente === '' || !Number.isInteger(porCliente) || porCliente < 1) errores.limite_usos_por_cliente = 'Debe ser un entero mayor o igual a 1.';
  else if (total !== null && Number.isInteger(total) && porCliente > total) errores.limite_usos_por_cliente = 'No puede ser mayor que el límite total.';

  return errores;
}

// Crear un cupón (esquema DatosCupon). `global` indica que lo crea la plataforma, sin comercio.
function FormularioCupon({ global, onGuardado, onCerrar }) {
  const [datos, setDatos] = useState({
    codigo: '', descripcion: '', tipo_descuento: 'porcentaje', valor: '', monto_minimo_compra: '',
    fecha_inicio: '', fecha_fin: '', limite_usos_total: '', limite_usos_por_cliente: '1',
  });
  const [errores, setErrores] = useState({});
  const [errorGeneral, setErrorGeneral] = useState('');
  const [guardando, setGuardando] = useState(false);
  const [intentos, setIntentos] = useState(0);
  const formulario = useRef(null);

  // Tras un intento fallido, el error queda a la vista: en celular el botón está abajo y el aviso arriba.
  useEffect(() => {
    if (!intentos || !formulario.current) return;
    const invalido = formulario.current.querySelector('[aria-invalid="true"]');
    if (invalido) invalido.focus();
    else formulario.current.querySelector('[role="alert"]')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }, [intentos]);

  const cambiar = (campo, valor) => {
    setDatos((anterior) => ({ ...anterior, [campo]: valor }));
    setErrores((anterior) => ({ ...anterior, [campo]: undefined }));
  };

  const guardar = async (evento) => {
    evento.preventDefault();
    setErrorGeneral('');
    const locales = validar(datos);
    if (Object.keys(locales).length) { setErrores(locales); setIntentos((n) => n + 1); return; }

    const cuerpo = {
      codigo: datos.codigo,
      descripcion: datos.descripcion.trim() || null,
      tipo_descuento: datos.tipo_descuento,
      valor: Number(datos.valor),
      monto_minimo_compra: datos.monto_minimo_compra === '' ? null : Number(datos.monto_minimo_compra),
      fecha_inicio: aIsoCostaRica(datos.fecha_inicio),
      fecha_fin: aIsoCostaRica(datos.fecha_fin),
      limite_usos_total: datos.limite_usos_total === '' ? null : Number(datos.limite_usos_total),
      limite_usos_por_cliente: Number(datos.limite_usos_por_cliente),
    };

    setGuardando(true);
    const respuesta = global ? await crearCuponGlobal(cuerpo) : await crearCuponComercio(cuerpo);
    setGuardando(false);

    if (respuesta.ok) { onGuardado(respuesta.datos); return; }
    const porCampo = erroresPorCampo(respuesta);
    setErrores(respuesta.error?.codigo === 'ERR-M1-007' ? { codigo: 'Ya existe un cupón con ese código.' } : porCampo);
    setErrorGeneral(mensajeErrorCupon(respuesta));
    setIntentos((n) => n + 1);
  };

  const soloDigitos = (campo) => (e) => cambiar(campo, e.target.value.replace(/\D/g, ''));
  const monto = (campo) => (e) => cambiar(campo, e.target.value.replace(',', '.').replace(/[^\d.]/g, ''));

  return (
    <Dialogo titulo={global ? 'Nuevo cupón de la plataforma' : 'Nuevo cupón'} onCerrar={onCerrar}
      pie={<><Boton onClick={onCerrar}>Cancelar</Boton><Boton variante="principal" icono="check" type="submit" form="form-cupon" disabled={guardando}>{guardando ? 'Guardando...' : 'Crear cupón'}</Boton></>}>
      <form id="form-cupon" ref={formulario} onSubmit={guardar} noValidate className="space-y-4">
        {errorGeneral && <Aviso>{errorGeneral}</Aviso>}
        <p className="text-sm text-gray-700">
          {global
            ? 'Descuenta sobre todo el subtotal del carrito, con productos de cualquier comercio.'
            : 'Descuenta solo sobre los productos de su comercio que estén en el carrito.'}
          {' '}Un cupón no se edita después de crearlo: si necesita cambiarlo, desactívelo y cree otro.
        </p>

        <Campo id="cupon-codigo" etiqueta="Código" value={datos.codigo} error={errores.codigo} maxLength={50} autoCapitalize="characters" autoComplete="off" spellCheck={false}
          ayuda="Es lo que escribe la persona al pagar. Ejemplo: VERANO2026." onChange={(e) => cambiar('codigo', e.target.value.toUpperCase().replace(/\s/g, ''))} />
        <Campo id="cupon-descripcion" etiqueta="Descripción (opcional)" value={datos.descripcion} error={errores.descripcion} maxLength={255}
          onChange={(e) => cambiar('descripcion', e.target.value)} />

        <div className="grid gap-4 sm:grid-cols-2">
          <Campo id="cupon-tipo" como="select" etiqueta="Tipo de descuento" value={datos.tipo_descuento} error={errores.tipo_descuento} onChange={(e) => cambiar('tipo_descuento', e.target.value)}>
            <option value="porcentaje">Porcentaje</option>
            <option value="monto_fijo">Monto fijo en colones</option>
          </Campo>
          <Campo id="cupon-valor" etiqueta={datos.tipo_descuento === 'porcentaje' ? 'Porcentaje (%)' : 'Monto (₡)'} inputMode="decimal" value={datos.valor} error={errores.valor}
            ayuda={datos.tipo_descuento === 'porcentaje' ? 'Mayor que 0 y menor que 100.' : 'Nunca descuenta más que el subtotal.'} onChange={monto('valor')} />
          <Campo id="cupon-inicio" type="datetime-local" etiqueta="Inicio (hora de Costa Rica)" value={datos.fecha_inicio} error={errores.fecha_inicio}
            onChange={(e) => cambiar('fecha_inicio', e.target.value)} />
          <Campo id="cupon-fin" type="datetime-local" etiqueta="Fin (hora de Costa Rica)" value={datos.fecha_fin} error={errores.fecha_fin}
            min={datos.fecha_inicio || ahoraCampoCostaRica()} onChange={(e) => cambiar('fecha_fin', e.target.value)} />
          <Campo id="cupon-minimo" etiqueta="Compra mínima (₡, opcional)" inputMode="decimal" value={datos.monto_minimo_compra} error={errores.monto_minimo_compra}
            ayuda={global ? 'Subtotal mínimo del carrito.' : 'Subtotal mínimo en productos de su comercio.'} onChange={monto('monto_minimo_compra')} />
          <Campo id="cupon-limite-total" etiqueta="Límite de usos total (opcional)" inputMode="numeric" value={datos.limite_usos_total} error={errores.limite_usos_total}
            ayuda="Vacío: sin límite." onChange={soloDigitos('limite_usos_total')} />
          <Campo id="cupon-limite-cliente" etiqueta="Usos por cliente" inputMode="numeric" value={datos.limite_usos_por_cliente} error={errores.limite_usos_por_cliente}
            onChange={soloDigitos('limite_usos_por_cliente')} />
        </div>
      </form>
    </Dialogo>
  );
}

// Desactivar un cupón (no se puede deshacer).
function DialogoDesactivarCupon({ cupon, global, onListo, onCerrar }) {
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState('');

  const desactivar = async () => {
    setEnviando(true);
    setError('');
    const respuesta = global ? await desactivarCuponAdministracion(cupon.id_cupon) : await desactivarCuponComercio(cupon.id_cupon);
    setEnviando(false);
    if (respuesta.ok) onListo(respuesta.datos);
    else setError(mensajeErrorCupon(respuesta));
  };

  return (
    <Dialogo titulo="Desactivar cupón" onCerrar={onCerrar}
      pie={<><Boton onClick={onCerrar}>Cancelar</Boton><Boton variante="peligro" icono="prohibido" disabled={enviando} onClick={desactivar}>{enviando ? 'Desactivando...' : 'Desactivar'}</Boton></>}>
      <div className="space-y-3">
        {error && <Aviso>{error}</Aviso>}
        <p className="text-sm text-gray-700">
          El cupón <span className="font-mono font-semibold">{cupon.codigo}</span> dejará de aceptarse al pagar. Los pedidos que ya lo usaron no cambian. Esta acción no se puede deshacer.
        </p>
      </div>
    </Dialogo>
  );
}

// Panel de cupones. `plataforma`: vista del encargado de la plataforma (todos los cupones y los globales).
export default function PanelCupones({ plataforma = false }) {
  const [estado, setEstado] = useState('');
  const [ambito, setAmbito] = useState(''); // '' todos · 'global' · id de comercio (solo plataforma)
  const [dialogo, setDialogo] = useState(null); // { tipo: 'crear' | 'desactivar', cupon }
  const [aviso, setAviso] = useState('');
  const comercios = useComercios();
  const consulta = useConsulta(
    () => (plataforma
      ? listarCuponesAdministracion({ estado: estado || null, idComercio: ambito && ambito !== 'global' ? ambito : null, soloGlobales: ambito === 'global' })
      : listarCuponesComercio(estado || null)),
    [plataforma, estado, ambito],
  );

  const cerrar = () => setDialogo(null);
  const listo = (texto) => () => { setDialogo(null); setAviso(texto); consulta.reintentar(); };
  const textoAmbito = (cupon) => {
    if (!plataforma) return null;
    if (cupon.id_comercio == null) return 'Global de la plataforma';
    return comercios.find((c) => c.id_comercio === cupon.id_comercio)?.nombre_comercial ?? `Comercio ${cupon.id_comercio}`;
  };
  const cupones = consulta.datos ?? [];

  return (
    <section aria-labelledby="titulo-cupones" className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="titulo-cupones" className="text-xl font-bold text-brandDarkBlue">{plataforma ? 'Cupones de la plataforma y de los comercios' : 'Cupones de descuento'}</h2>
          <p className="text-sm text-gray-600">
            {plataforma
              ? 'Cree cupones globales para toda la plataforma y desactive los que incumplan las políticas.'
              : 'Códigos que sus clientes escriben al pagar para obtener un descuento en sus productos.'}
          </p>
        </div>
        <Boton variante="principal" icono="mas" onClick={() => { setAviso(''); setDialogo({ tipo: 'crear' }); }} className="w-full sm:w-auto">
          {plataforma ? 'Nuevo cupón global' : 'Nuevo cupón'}
        </Boton>
      </div>

      <FiltroEstado valor={estado} onCambio={setEstado} />
      {plataforma && (
        <Campo id="cupones-ambito" como="select" etiqueta="Mostrar" value={ambito} onChange={(e) => setAmbito(e.target.value)} clase="sm:max-w-sm">
          <option value="">Todos los cupones</option>
          <option value="global">Solo los globales de la plataforma</option>
          {comercios.map((c) => <option key={c.id_comercio} value={c.id_comercio}>{c.nombre_comercial}</option>)}
        </Campo>
      )}
      {aviso && <Aviso tipo="exito">{aviso}</Aviso>}

      {consulta.estado === 'cargando' && <CargandoTarjetas cantidad={3} etiqueta="Cargando cupones" />}
      {consulta.estado === 'error' && <ErrorVista resultado={consulta.resultado} onReintentar={consulta.reintentar} />}
      {consulta.estado !== 'cargando' && consulta.estado !== 'error' && cupones.length === 0 && (
        <Vacio icono="cupon" titulo={estado || ambito ? 'No hay cupones con ese filtro' : 'Todavía no hay cupones'}>
          {estado || ambito ? 'Pruebe con otro filtro.' : 'Cree uno para ofrecer un descuento al pagar.'}
        </Vacio>
      )}
      {consulta.estado !== 'cargando' && consulta.estado !== 'error' && cupones.length > 0 && (
        <ul className={`grid gap-3 lg:grid-cols-2 ${consulta.estado === 'actualizando' ? 'opacity-60' : ''}`} aria-busy={consulta.estado === 'actualizando'}>
          {cupones.map((cupon) => (
            <li key={cupon.id_cupon} className="min-w-0">
              <TarjetaCupon cupon={cupon} ambito={textoAmbito(cupon)}>
                {cupon.activo && cupon.estado !== 'vencido'
                  ? <Boton variante="peligro" icono="prohibido" onClick={() => setDialogo({ tipo: 'desactivar', cupon })}>Desactivar</Boton>
                  : null}
              </TarjetaCupon>
            </li>
          ))}
        </ul>
      )}

      {dialogo?.tipo === 'crear' && <FormularioCupon global={plataforma} onCerrar={cerrar} onGuardado={listo('Cupón creado.')} />}
      {dialogo?.tipo === 'desactivar' && <DialogoDesactivarCupon cupon={dialogo.cupon} global={plataforma} onCerrar={cerrar} onListo={listo('Cupón desactivado.')} />}
    </section>
  );
}
