import { useState } from 'react';
import { PencilLine, Plus, Send, Trash2 } from 'lucide-react';
import { corregirMiSolicitud, registrarSolicitud } from '../services/m2Api';
import {
  Aviso, AvisoError, Boton, C, Campo, MensajeCampo, Seleccion, Tarjeta, erroresDeCampo, mensajeDe, textoDeError, useSesion,
} from '../components/comunes';

// Solicitud de incorporación de un comercio (CU-0012). Es pública: el interesado todavía no tiene cuenta
// y la crea al enviarla. La corrección de una solicitud rechazada (CU-0013) la hace la persona encargada
// desde su sesión, en «Estado de la solicitud» (CorregirMiSolicitud, al final de este archivo).

const TIPOS_DOCUMENTO = [
  ['cedula_juridica', 'Cédula jurídica'], ['patente', 'Patente comercial'],
  ['permiso_sanitario', 'Permiso sanitario'], ['otro', 'Otro'],
];
const NOMBRE_TIPO = Object.fromEntries(TIPOS_DOCUMENTO);

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

// Resultado de un envío: errores por campo, mensaje general y respuesta exitosa.
function useEnvio(mensajeDeError) {
  const [estado, setEstado] = useState({ enviando: false, errores: {}, error: '', respuesta: null });
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
  return [estado, enviar];
}

// Campos del comercio y del encargado, compartidos por el registro y la corrección.
function CamposComercio({ datos, setDatos, errores, bloquearIdentidad = false }) {
  const campo = (nombre) => ({
    value: datos[nombre] ?? '',
    error: mensajeDe(errores, nombre),
    onChange: (e) => setDatos({ ...datos, [nombre]: e.target.value }),
  });
  return (
    <>
      <h3 className="mt-5 text-sm font-extrabold uppercase tracking-wide text-slate-500">Datos del comercio</h3>
      <div className="grid gap-x-4 sm:grid-cols-2">
        <Campo etiqueta="Nombre comercial" autoComplete="organization" {...campo('nombre_comercial')} />
        {/* En la corrección, la cédula y el correo se muestran arriba (la cédula no se puede cambiar). */}
        {!bloquearIdentidad && <Campo etiqueta="Cédula jurídica (formato 3-101-123456)" placeholder="3-101-123456" {...campo('cedula_juridica')} />}
        <Campo etiqueta="Razón social" {...campo('razon_social')} />
        <Campo etiqueta="Dirección fiscal" {...campo('direccion_fiscal')} />
      </div>
      <label className="mt-4 flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="checkbox" checked={Boolean(datos.inscrito_como_contribuyente)}
          onChange={(e) => setDatos({ ...datos, inscrito_como_contribuyente: e.target.checked })} />
        El comercio está inscrito como contribuyente ante Hacienda
      </label>
      {mensajeDe(errores, 'inscrito_como_contribuyente') && (
        <p className="mt-1 text-xs text-red-700">{mensajeDe(errores, 'inscrito_como_contribuyente')}</p>
      )}

      <h3 className="mt-6 text-sm font-extrabold uppercase tracking-wide text-slate-500">Persona encargada</h3>
      <div className="grid gap-x-4 sm:grid-cols-2">
        <Campo etiqueta="Nombre completo" autoComplete="name" {...campo('nombre_encargado')} />
        {!bloquearIdentidad && <Campo etiqueta="Correo electrónico" type="email" autoComplete="email" {...campo('correo_encargado')} />}
        <Campo etiqueta="Teléfono (opcional)" type="tel" autoComplete="tel" {...campo('telefono_encargado')} />
        {/* Con la contraseña se crea la cuenta al enviar: la persona ingresa para ver el estado de la revisión. */}
        {!bloquearIdentidad && (
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

// Documentos por adjuntar (registro y corrección). Cada uno lleva tipo, vencimiento y archivo.
function EditorDocumentos({ documentos, setDocumentos, errores, textoAgregar = 'Agregar documento' }) {
  const cambiarDocumento = (i, campo, valor) => setDocumentos(documentos.map((d, j) => (j === i ? { ...d, [campo]: valor } : d)));
  return (
    <>
      {documentos.map((d, i) => (
        <div key={i} className="mt-3 grid gap-x-4 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
          <Seleccion etiqueta="Tipo" value={d.tipo} onChange={(e) => cambiarDocumento(i, 'tipo', e.target.value)}
            error={mensajeDe(errores, `documentos.${i}.tipo`)}>
            {TIPOS_DOCUMENTO.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
          </Seleccion>
          <Campo etiqueta="Vence el" type="date" min={manana()} value={d.fecha_vencimiento}
            onChange={(e) => cambiarDocumento(i, 'fecha_vencimiento', e.target.value)}
            error={mensajeDe(errores, `documentos.${i}.fecha_vencimiento`)} />
          <Campo etiqueta="Archivo" type="file" accept="application/pdf,image/jpeg,image/png"
            onChange={(e) => cambiarDocumento(i, 'archivo', e.target.files?.[0] || null)}
            error={mensajeDe(errores, `documentos.${i}.archivo`)} />
          <Boton variante="neutro" aria-label="Quitar documento" onClick={() => setDocumentos(documentos.filter((_, j) => j !== i))}>
            <Trash2 size={16} />
          </Boton>
        </div>
      ))}
      <Boton variante="neutro" className="mt-3" onClick={() => setDocumentos([...documentos, { tipo: 'cedula_juridica', fecha_vencimiento: '', archivo: null }])}>
        <Plus size={16} /> {textoAgregar}
      </Boton>
    </>
  );
}

function NuevaSolicitud() {
  const { mensajeDeError } = useSesion();
  const [datos, setDatos] = useState(COMERCIO_VACIO);
  const [documentos, setDocumentos] = useState([]);
  const [estado, enviar] = useEnvio(mensajeDeError);

  const alEnviar = async (e) => {
    e.preventDefault();
    const datosLimpios = { ...datos, telefono_encargado: datos.telefono_encargado || null };
    // Se revisa aquí para avisar sin esperar al servidor; el servidor vuelve a validar las dos reglas.
    const errorContrasena = datos.contrasena_encargado.length < 8
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
      <Tarjeta>
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>¡Solicitud enviada!</h2>
        <p className="mt-2 text-slate-700">Tu número de solicitud es</p>
        <p className={`mt-1 text-4xl font-extrabold ${C.verdeTexto}`}>#{estado.respuesta.id_solicitud}</p>
        <p className="mt-3 text-sm text-slate-600">
          Ya podés ingresar con {estado.respuesta.correo_encargado} y la contraseña que definiste para ver
          el estado de la revisión. Si la plataforma te pide corregir algo, lo corregís desde ahí mismo, y
          cuando apruebe tu comercio, desde ahí vas a poder administrarlo.
        </p>
        <Boton variante="neutro" className="mt-4" onClick={() => window.location.reload()}>Enviar otra solicitud</Boton>
      </Tarjeta>
    );
  }

  return (
    <Tarjeta>
      <form onSubmit={alEnviar} noValidate>
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>Afiliá tu comercio a Pacíficos Online</h2>
        <p className="mt-1 text-sm text-slate-500">
          Completá los datos y la plataforma revisará tu solicitud. Si adjuntás documentos, deben estar vigentes.
        </p>
        <CamposComercio datos={datos} setDatos={setDatos} errores={estado.errores} />

        <h3 className="mt-6 text-sm font-extrabold uppercase tracking-wide text-slate-500">Documentos (opcional)</h3>
        <p className="text-xs text-slate-500">PDF, JPG o PNG de hasta 5 MB. Se guardan cifrados.</p>
        <EditorDocumentos documentos={documentos} setDocumentos={setDocumentos} errores={estado.errores} />

        <div className="mt-6">
          <Boton type="submit" disabled={estado.enviando}><Send size={16} /> {estado.enviando ? 'Enviando…' : 'Enviar solicitud'}</Boton>
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
  const [estado, enviar] = useEnvio(mensajeDeError);

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
          <Campo etiqueta="Correo de la persona encargada" type="email" autoComplete="email" value={datos.correo_encargado}
            onChange={(e) => setDatos({ ...datos, correo_encargado: e.target.value })} error={mensajeDe(estado.errores, 'correo_encargado')} />
        </div>
        <p className="text-xs text-slate-500">Si cambiás el correo, también cambia el correo con el que ingresás.</p>
        <CamposComercio datos={datos} setDatos={setDatos} errores={estado.errores} bloquearIdentidad />

        <h3 className="mt-6 text-sm font-extrabold uppercase tracking-wide text-slate-500">Documentos</h3>
        {actuales.length === 0 && <p className="text-sm text-slate-500">Tu solicitud no tiene documentos.</p>}
        <ul className="mt-2 space-y-2">
          {actuales.map((d) => (
            <li key={d.id_documento_comercio}
              className={`rounded-lg border p-3 text-sm ${d.debe_reemplazarse ? 'border-red-200 bg-red-50 text-red-900' : 'border-slate-200 text-slate-700'}`}>
              <span className="font-bold">{NOMBRE_TIPO[d.tipo] ?? d.tipo}</span>
              {' · '}{d.debe_reemplazarse && d.resultado !== 'invalido' ? 'Vencido' : (ESTADO_DOCUMENTO[d.resultado] ?? d.resultado)}
              {d.fecha_vencimiento ? ` · vence el ${String(d.fecha_vencimiento).slice(0, 10)}` : ''}
              {d.observacion ? ` · ${d.observacion}` : ''}
              {d.debe_reemplazarse && <span className="block text-xs">Subí uno nuevo para reemplazarlo.</span>}
            </li>
          ))}
        </ul>
        <EditorDocumentos documentos={documentos} setDocumentos={setDocumentos} errores={estado.errores} textoAgregar="Adjuntar documento nuevo" />
        <MensajeCampo mensaje={mensajeDe(estado.errores, 'documentos')} />

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
