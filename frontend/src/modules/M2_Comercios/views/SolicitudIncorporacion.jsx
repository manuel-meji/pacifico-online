import { useState } from 'react';
import { PencilLine, Plus, Send, Trash2 } from 'lucide-react';
import { corregirMiSolicitud, registrarSolicitud } from '../services/m2Api';
import {
  Aviso, AvisoError, Boton, C, Campo, MensajeCampo, Seleccion, TIPOS_DOCUMENTO, Tarjeta, erroresDeCampo, mensajeDe, nombreDocumento,
  textoDeError, useSesion,
} from '../components/comunes';

// Solicitud de incorporación de un comercio (CU-0012). Es pública: el interesado todavía no tiene cuenta
// y la crea al enviarla. La plataforma también la usa, con su sesión, para registrar un comercio en su
// nombre («Registrar comercio»): sin contraseña, y el núcleo le envía la invitación a la persona encargada.
// La corrección de una solicitud rechazada (CU-0013) la hace la persona encargada desde su sesión, en
// «Estado de la solicitud» (CorregirMiSolicitud, al final de este archivo).

const MAXIMO_DOCUMENTOS = 5;

// Escribe la cédula jurídica con sus guiones mientras se teclea: 3101123456 → 3-101-123456 (hallazgo 7).
export const formatearCedula = (texto) => {
  const d = String(texto).replace(/\D/g, '').slice(0, 10);
  return [d.slice(0, 1), d.slice(1, 4), d.slice(4)].filter(Boolean).join('-');
};

const COMERCIO_VACIO = {
  nombre_comercial: '', cedula_juridica: '', razon_social: '', direccion_fiscal: '',
  inscrito_como_contribuyente: false, nombre_encargado: '', correo_encargado: '', telefono_encargado: '',
  contrasena_encargado: '', contrasena_encargado_confirmation: '',
};

const manana = () => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
};

// Resultado de un envío: errores por campo, mensaje general y respuesta exitosa. limpiar(campo) quita el
// error de un campo cuando la persona lo corrige (hallazgo 8), sin esperar al próximo envío.
function useEnvio(mensajeDeError) {
  const [estado, setEstado] = useState({ enviando: false, errores: {}, error: '', respuesta: null });
  const limpiar = (campo) => setEstado((e) => {
    if (!e.errores[campo]) return e;
    const errores = { ...e.errores };
    delete errores[campo];
    return { ...e, errores, error: Object.keys(errores).length ? e.error : '' };
  });
  const enviar = async (accion) => {
    setEstado({ enviando: true, errores: {}, error: '', respuesta: null });
    const r = await accion();
    if (r.ok) {
      setEstado({ enviando: false, errores: {}, error: '', respuesta: r.datos });
      return true;
    }
    const errores = erroresDeCampo(r);
    const general = Object.keys(errores).length ? 'Revisá los datos marcados.' : (mensajeDeError?.(r) || textoDeError(r));
    setEstado({ enviando: false, errores, error: general, respuesta: null });
    return false;
  };
  return [estado, enviar, limpiar];
}

// Campos del comercio y del encargado, compartidos por el registro y la corrección. Los máximos son los
// mismos que valida el servidor (hallazgo 6).
function CamposComercio({ datos, setDatos, errores, onLimpiar, bloquearIdentidad = false, sinContrasena = false }) {
  const cambiar = (nombre, valor) => {
    setDatos({ ...datos, [nombre]: valor });
    onLimpiar?.(nombre);
  };
  const campo = (nombre) => ({
    value: datos[nombre] ?? '',
    error: mensajeDe(errores, nombre),
    onChange: (e) => cambiar(nombre, e.target.value),
  });
  const contribuyente = Boolean(datos.inscrito_como_contribuyente);
  return (
    <>
      <h3 className="mt-5 text-sm font-extrabold uppercase tracking-wide text-slate-500">Datos del comercio</h3>
      <div className="grid gap-x-4 sm:grid-cols-2">
        <Campo etiqueta="Nombre comercial" autoComplete="organization" maxLength={150} {...campo('nombre_comercial')}
          ayuda="El nombre con el que te conocen los clientes." />
        {/* En la corrección, la cédula y el correo se muestran arriba (la cédula no se puede cambiar). */}
        {!bloquearIdentidad && (
          <Campo etiqueta="Cédula jurídica" placeholder="3-101-123456" inputMode="numeric" maxLength={12}
            {...campo('cedula_juridica')} onChange={(e) => cambiar('cedula_juridica', formatearCedula(e.target.value))}
            ayuda="Formato 3-XXX-XXXXXX: escribí los 10 dígitos y los guiones se ponen solos." />
        )}
        <Campo etiqueta="Razón social" maxLength={150} {...campo('razon_social')} ayuda="Como aparece en el Registro Nacional, por ejemplo «Soda La Pacífica S.A.»." />
        <Campo etiqueta="Dirección fiscal" maxLength={255} {...campo('direccion_fiscal')} ayuda="Provincia, cantón y señas." />
      </div>
      <label className="mt-4 flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="checkbox" checked={contribuyente}
          onChange={(e) => cambiar('inscrito_como_contribuyente', e.target.checked)} />
        El comercio está inscrito como contribuyente ante Hacienda
      </label>
      {mensajeDe(errores, 'inscrito_como_contribuyente') && (
        <p className="mt-1 text-xs text-red-700">{mensajeDe(errores, 'inscrito_como_contribuyente')}</p>
      )}
      {/* Hallazgo 2: sin la casilla la solicitud se puede enviar, pero no aprobar (RN-001). */}
      {!contribuyente && (
        <p className="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-900">
          Para aprobar el comercio tiene que estar inscrito: Pacíficos Online emite la factura de cada venta a nombre del
          comercio. Podés enviar la solicitud igual y marcar la casilla cuando te inscribas.
        </p>
      )}

      <h3 className="mt-6 text-sm font-extrabold uppercase tracking-wide text-slate-500">Persona encargada</h3>
      <div className="grid gap-x-4 sm:grid-cols-2">
        <Campo etiqueta="Nombre completo" autoComplete="name" maxLength={150} {...campo('nombre_encargado')} />
        {!bloquearIdentidad && <Campo etiqueta="Correo electrónico" type="email" autoComplete="email" maxLength={150} {...campo('correo_encargado')} />}
        <Campo etiqueta="Teléfono (opcional)" type="tel" autoComplete="tel" inputMode="tel" maxLength={14} placeholder="8888-8888"
          {...campo('telefono_encargado')} />
        {/* Con la contraseña la persona ingresa apenas envía la solicitud para ver el estado de la revisión.
            Cuando registra la plataforma, no se pide: el núcleo le envía una invitación para definirla. */}
        {!bloquearIdentidad && !sinContrasena && (
          <>
            <Campo etiqueta="Contraseña para ingresar (mínimo 8 caracteres)" type="password" autoComplete="new-password"
              minLength={8} maxLength={72} required {...campo('contrasena_encargado')} />
            <Campo etiqueta="Confirmá la contraseña" type="password" autoComplete="new-password"
              minLength={8} maxLength={72} required {...campo('contrasena_encargado_confirmation')} />
          </>
        )}
      </div>
    </>
  );
}

// Documentos por adjuntar (registro y corrección). Cada uno lleva tipo, vencimiento y archivo; cada tipo va
// una sola vez (hallazgo 25), así que los tipos ya usados no se ofrecen de nuevo.
function EditorDocumentos({ documentos, setDocumentos, errores, onLimpiar, textoAgregar = 'Agregar documento' }) {
  const cambiarDocumento = (i, campo, valor) => {
    setDocumentos(documentos.map((d, j) => (j === i ? { ...d, [campo]: valor } : d)));
    onLimpiar?.(`documentos.${i}.${campo}`);
  };
  const usados = documentos.map((d) => d.tipo);
  const libre = TIPOS_DOCUMENTO.find(([v]) => !usados.includes(v))?.[0];
  const puedeAgregar = Boolean(libre) && documentos.length < MAXIMO_DOCUMENTOS;
  return (
    <>
      {documentos.map((d, i) => (
        // Hallazgo 3: los campos se alinean arriba, así un error en uno no desalinea la fila.
        <div key={i} className="mt-3 grid gap-x-4 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-start">
          <Seleccion etiqueta="Tipo" value={d.tipo} onChange={(e) => cambiarDocumento(i, 'tipo', e.target.value)}
            error={mensajeDe(errores, `documentos.${i}.tipo`)}>
            {TIPOS_DOCUMENTO.map(([v, t]) => (
              <option key={v} value={v} disabled={v !== d.tipo && usados.includes(v)}>{t}</option>
            ))}
          </Seleccion>
          <Campo etiqueta="Vence el" type="date" min={manana()} value={d.fecha_vencimiento}
            onChange={(e) => cambiarDocumento(i, 'fecha_vencimiento', e.target.value)}
            error={mensajeDe(errores, `documentos.${i}.fecha_vencimiento`)} ayuda="Tiene que estar vigente." />
          <Campo etiqueta="Archivo" type="file" accept="application/pdf,image/jpeg,image/png"
            onChange={(e) => cambiarDocumento(i, 'archivo', e.target.files?.[0] || null)}
            error={mensajeDe(errores, `documentos.${i}.archivo`)} />
          <Boton variante="neutro" className="sm:mt-[2.6rem]" aria-label="Quitar documento"
            onClick={() => setDocumentos(documentos.filter((_, j) => j !== i))}>
            <Trash2 size={16} />
          </Boton>
        </div>
      ))}
      <Boton variante="neutro" className="mt-3" disabled={!puedeAgregar}
        onClick={() => setDocumentos([...documentos, { tipo: libre, fecha_vencimiento: '', archivo: null }])}>
        <Plus size={16} /> {textoAgregar}
      </Boton>
      <MensajeCampo mensaje={mensajeDe(errores, 'documentos')} />
    </>
  );
}

// Hallazgo 4: para qué sirven los documentos.
const AYUDA_DOCUMENTOS = 'Respaldo legal que la plataforma revisa antes de aprobar tu comercio, como la cédula jurídica, '
  + 'la patente comercial, el permiso sanitario o la constancia de inscripción en Hacienda. Si adjuntás alguno, tiene que '
  + 'estar vigente. PDF, JPG o PNG de hasta 5 MB; se guardan cifrados.';

function NuevaSolicitud() {
  const { usuario, mensajeDeError } = useSesion();
  // Hallazgo 11: la plataforma registra el comercio en su nombre; la persona encargada define su contraseña
  // con la invitación del núcleo.
  const porPlataforma = usuario?.rol === 'encargado_plataforma';
  const [datos, setDatos] = useState(COMERCIO_VACIO);
  const [documentos, setDocumentos] = useState([]);
  const [estado, enviar, limpiar] = useEnvio(mensajeDeError);

  const alEnviar = async (e) => {
    e.preventDefault();
    const datosLimpios = { ...datos, telefono_encargado: datos.telefono_encargado || null };
    if (porPlataforma) {
      delete datosLimpios.contrasena_encargado;
      delete datosLimpios.contrasena_encargado_confirmation;
    }
    // Se revisa aquí para avisar sin esperar al servidor; el servidor vuelve a validar las dos reglas.
    const errorContrasena = porPlataforma ? null : datos.contrasena_encargado.length < 8
      ? 'La contraseña debe tener al menos 8 caracteres.'
      : datos.contrasena_encargado !== datos.contrasena_encargado_confirmation ? 'Las contraseñas no coinciden.' : null;
    if (errorContrasena) {
      await enviar(async () => ({ ok: false, estado: 422, error: { codigo: 'ERR-005', mensaje: 'Revisá los datos marcados.', detalles: { contrasena_encargado: [errorContrasena] } } }));
      return;
    }
    const ok = await enviar(() => registrarSolicitud(datosLimpios, documentos));
    if (ok) {
      setDatos(COMERCIO_VACIO);
      setDocumentos([]);
    }
  };

  if (estado.respuesta) {
    return (
      <Tarjeta className="mx-auto max-w-2xl">
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>{porPlataforma ? 'Comercio registrado' : '¡Solicitud enviada!'}</h2>
        <p className="mt-2 text-slate-700">{porPlataforma ? 'El número de la solicitud es' : 'Tu número de solicitud es'}</p>
        <p className={`mt-1 text-4xl font-extrabold ${C.verdeTexto}`}>#{estado.respuesta.id_solicitud}</p>
        <p className="mt-3 text-sm text-slate-600">
          {porPlataforma
            ? `La solicitud quedó Pendiente en la pestaña Solicitudes. ${estado.respuesta.correo_encargado} recibe una invitación para definir su contraseña y ver el estado de la revisión.`
            : `Ya podés ingresar con ${estado.respuesta.correo_encargado} y la contraseña que definiste para ver el estado de la revisión. Si la plataforma te pide corregir algo, lo corregís desde ahí mismo, y cuando apruebe tu comercio, desde ahí vas a poder administrarlo.`}
        </p>
        <Boton variante="neutro" className="mt-4" onClick={() => window.location.reload()}>
          {porPlataforma ? 'Registrar otro comercio' : 'Enviar otra solicitud'}
        </Boton>
      </Tarjeta>
    );
  }

  return (
    <Tarjeta className="mx-auto max-w-3xl">
      <form onSubmit={alEnviar} noValidate>
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>
          {porPlataforma ? 'Registrar un comercio' : 'Afiliá tu comercio a Pacíficos Online'}
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          {porPlataforma
            ? 'Registrás la solicitud en nombre del comercio: queda Pendiente para revisarla en la pestaña Solicitudes, y la persona encargada recibe una invitación para definir su contraseña.'
            : 'Completá los datos y la plataforma revisará tu solicitud. Si adjuntás documentos, deben estar vigentes.'}
        </p>
        <CamposComercio datos={datos} setDatos={setDatos} errores={estado.errores} onLimpiar={limpiar} sinContrasena={porPlataforma} />

        <h3 className="mt-6 text-sm font-extrabold uppercase tracking-wide text-slate-500">Documentos (opcional)</h3>
        <p className="max-w-prose text-xs text-slate-500">{AYUDA_DOCUMENTOS}</p>
        <EditorDocumentos documentos={documentos} setDocumentos={setDocumentos} errores={estado.errores} onLimpiar={limpiar} />

        <div className="mt-6">
          <Boton type="submit" disabled={estado.enviando}>
            <Send size={16} /> {estado.enviando ? 'Enviando…' : (porPlataforma ? 'Registrar comercio' : 'Enviar solicitud')}
          </Boton>
          <AvisoError mensaje={estado.error} />
        </div>
      </form>
    </Tarjeta>
  );
}

const ESTADO_DOCUMENTO = { valido: 'Válido', invalido: 'Rechazado', pendiente: 'Pendiente de revisión' };

// CU-0013 con sesión (DEF-34): la persona encargada corrige su solicitud rechazada. El número y la cédula
// vienen de su solicitud y no se pueden cambiar; el correo y los demás datos sí. Los documentos rechazados
// o vencidos se reemplazan subiendo uno nuevo.
export function CorregirMiSolicitud({ revision, onCorregida }) {
  const { mensajeDeError } = useSesion();
  const solicitud = revision?.solicitud ?? {};
  const comercio = revision?.comercio ?? {};
  const actuales = revision?.documentos ?? [];
  const porReemplazar = actuales.filter((d) => d.debe_reemplazarse);
  const [datos, setDatos] = useState({
    nombre_comercial: comercio.nombre_comercial ?? '', razon_social: comercio.razon_social ?? '',
    direccion_fiscal: comercio.direccion_fiscal ?? '', inscrito_como_contribuyente: Boolean(comercio.inscrito_como_contribuyente),
    nombre_encargado: solicitud.nombre_encargado ?? '', correo_encargado: solicitud.correo_encargado ?? '',
    telefono_encargado: solicitud.telefono_encargado ?? '',
  });
  const [documentos, setDocumentos] = useState([]);
  const [estado, enviar, limpiar] = useEnvio(mensajeDeError);

  const alEnviar = async (e) => {
    e.preventDefault();
    if (porReemplazar.length > 0 && documentos.length === 0) {
      await enviar(async () => ({ ok: false, estado: 422, error: { codigo: 'ERR-005', mensaje: 'Revisá los datos marcados.', detalles: {
        documentos: ['Tenés documentos rechazados o vencidos: subí uno nuevo para reemplazarlos antes de enviar la corrección.'] } } }));
      return;
    }
    const cuerpo = {
      ...datos, telefono_encargado: datos.telefono_encargado || null,
      id_solicitud: solicitud.id_solicitud, cedula_juridica: comercio.cedula_juridica,
    };
    const ok = await enviar(() => corregirMiSolicitud(cuerpo, documentos));
    if (ok) onCorregida?.();
  };

  return (
    <Tarjeta>
      <form onSubmit={alEnviar} noValidate>
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>Corregir tu solicitud</h2>
        <p className="mt-1 text-sm text-slate-500">
          Corregí lo que indicó la plataforma y enviala de nuevo: vuelve a quedar en revisión.
        </p>
        <div className="grid gap-x-4 sm:grid-cols-2">
          <Campo etiqueta="Número de solicitud" value={solicitud.id_solicitud ? `#${solicitud.id_solicitud}` : ''} readOnly
            error={mensajeDe(estado.errores, 'id_solicitud')} />
          <Campo etiqueta="Cédula jurídica" value={comercio.cedula_juridica ?? ''} readOnly
            error={mensajeDe(estado.errores, 'cedula_juridica')} />
        </div>
        <p className="text-xs text-slate-500">El número de solicitud y la cédula jurídica identifican tu solicitud y no se pueden cambiar.</p>
        <div className="grid gap-x-4 sm:grid-cols-2">
          <Campo etiqueta="Correo de la persona encargada" type="email" autoComplete="email" maxLength={150} value={datos.correo_encargado}
            onChange={(e) => { setDatos({ ...datos, correo_encargado: e.target.value }); limpiar('correo_encargado'); }}
            error={mensajeDe(estado.errores, 'correo_encargado')} ayuda="Si cambiás el correo, también cambia el correo con el que ingresás." />
        </div>
        <CamposComercio datos={datos} setDatos={setDatos} errores={estado.errores} onLimpiar={limpiar} bloquearIdentidad />

        <h3 className="mt-6 text-sm font-extrabold uppercase tracking-wide text-slate-500">Documentos</h3>
        {actuales.length === 0 && <p className="text-sm text-slate-500">Tu solicitud no tiene documentos.</p>}
        <ul className="mt-2 space-y-2">
          {actuales.map((d) => (
            <li key={d.id_documento_comercio}
              className={`rounded-lg border p-3 text-sm ${d.debe_reemplazarse ? 'border-red-200 bg-red-50 text-red-900' : 'border-slate-200 text-slate-700'}`}>
              <span className="font-bold">{nombreDocumento(d.tipo)}</span>
              {' · '}{d.debe_reemplazarse && d.resultado !== 'invalido' ? 'Vencido' : (ESTADO_DOCUMENTO[d.resultado] ?? d.resultado)}
              {d.fecha_vencimiento ? ` · vence el ${String(d.fecha_vencimiento).slice(0, 10)}` : ''}
              {d.observacion ? ` · ${d.observacion}` : ''}
              {d.debe_reemplazarse && <span className="block text-xs">Subí uno nuevo para reemplazarlo.</span>}
            </li>
          ))}
        </ul>
        <p className="mt-2 max-w-prose text-xs text-slate-500">Un documento nuevo reemplaza al del mismo tipo.</p>
        <EditorDocumentos documentos={documentos} setDocumentos={setDocumentos} errores={estado.errores} onLimpiar={limpiar} textoAgregar="Adjuntar documento nuevo" />

        <div className="mt-6">
          <Boton type="submit" disabled={estado.enviando}>
            <PencilLine size={16} /> {estado.enviando ? 'Enviando…' : 'Enviar corrección'}
          </Boton>
          <AvisoError mensaje={estado.error} />
          <Aviso mensaje={estado.respuesta ? `Solicitud #${estado.respuesta.id_solicitud} corregida: volvió a quedar en revisión.` : ''} />
        </div>
      </form>
    </Tarjeta>
  );
}

export default function SolicitudIncorporacion() {
  return <NuevaSolicitud />;
}
