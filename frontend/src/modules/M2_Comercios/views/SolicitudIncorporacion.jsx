import { useState } from 'react';
import { FilePlus2, FileText, PencilLine, Plus, Send, Trash2 } from 'lucide-react';
import { corregirSolicitud, registrarSolicitud } from '../services/m2Api';
import {
  Aviso, AvisoError, Boton, C, Campo, Seleccion, Tarjeta, erroresDeCampo, mensajeDe, textoDeError, useSesion,
} from '../components/comunes';

// Solicitud de incorporación de un comercio (CU-0012) y corrección de una solicitud rechazada (CU-0013).
// Es pública: el interesado todavía no tiene cuenta.

const TIPOS_DOCUMENTO = [
  ['cedula_juridica', 'Cédula jurídica'], ['patente', 'Patente comercial'],
  ['permiso_sanitario', 'Permiso sanitario'], ['otro', 'Otro'],
];

const COMERCIO_VACIO = {
  nombre_comercial: '', cedula_juridica: '', razon_social: '', direccion_fiscal: '',
  inscrito_como_contribuyente: false, nombre_encargado: '', correo_encargado: '', telefono_encargado: '',
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
        {/* En la corrección, la cédula y el correo solo identifican la solicitud (arriba): no se repiten aquí. */}
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
      </div>
    </>
  );
}

function NuevaSolicitud() {
  const { mensajeDeError } = useSesion();
  const [datos, setDatos] = useState(COMERCIO_VACIO);
  const [documentos, setDocumentos] = useState([]);
  const [estado, enviar] = useEnvio(mensajeDeError);

  const cambiarDocumento = (i, campo, valor) => setDocumentos(documentos.map((d, j) => (j === i ? { ...d, [campo]: valor } : d)));

  const alEnviar = async (e) => {
    e.preventDefault();
    const datosLimpios = { ...datos, telefono_encargado: datos.telefono_encargado || null };
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
          Guardalo: lo vas a necesitar si la plataforma te pide corregir algo. Cuando la solicitud sea aprobada,
          te llegará un correo a {estado.respuesta.correo_encargado} para que definás tu contraseña.
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
        {documentos.map((d, i) => (
          <div key={i} className="mt-3 grid gap-x-4 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
            <Seleccion etiqueta="Tipo" value={d.tipo} onChange={(e) => cambiarDocumento(i, 'tipo', e.target.value)}
              error={mensajeDe(estado.errores, `documentos.${i}.tipo`)}>
              {TIPOS_DOCUMENTO.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
            </Seleccion>
            <Campo etiqueta="Vence el" type="date" min={manana()} value={d.fecha_vencimiento}
              onChange={(e) => cambiarDocumento(i, 'fecha_vencimiento', e.target.value)}
              error={mensajeDe(estado.errores, `documentos.${i}.fecha_vencimiento`)} />
            <Campo etiqueta="Archivo" type="file" accept="application/pdf,image/jpeg,image/png"
              onChange={(e) => cambiarDocumento(i, 'archivo', e.target.files?.[0] || null)}
              error={mensajeDe(estado.errores, `documentos.${i}.archivo`)} />
            <Boton variante="neutro" aria-label="Quitar documento" onClick={() => setDocumentos(documentos.filter((_, j) => j !== i))}>
              <Trash2 size={16} />
            </Boton>
          </div>
        ))}
        <Boton variante="neutro" className="mt-3" onClick={() => setDocumentos([...documentos, { tipo: 'cedula_juridica', fecha_vencimiento: '', archivo: null }])}>
          <Plus size={16} /> Agregar documento
        </Boton>

        <div className="mt-6">
          <Boton type="submit" disabled={estado.enviando}><Send size={16} /> {estado.enviando ? 'Enviando…' : 'Enviar solicitud'}</Boton>
          <AvisoError mensaje={estado.error} />
        </div>
      </form>
    </Tarjeta>
  );
}

function CorregirSolicitud() {
  const { mensajeDeError } = useSesion();
  const [idSolicitud, setIdSolicitud] = useState('');
  // inscrito_como_contribuyente arranca en null: solo se envía si la persona marca o desmarca la casilla.
  const [datos, setDatos] = useState({ ...COMERCIO_VACIO, inscrito_como_contribuyente: null });
  const [estado, enviar] = useEnvio(mensajeDeError);

  const alEnviar = async (e) => {
    e.preventDefault();
    // Solo se envían los campos que la persona llenó; cédula y correo identifican la solicitud.
    const cambios = Object.fromEntries(Object.entries(datos).filter(([clave, valor]) =>
      ['cedula_juridica', 'correo_encargado'].includes(clave)
      || typeof valor === 'boolean'
      || (typeof valor === 'string' && valor.trim() !== '')));
    await enviar(() => corregirSolicitud(Number(idSolicitud), cambios));
  };

  return (
    <Tarjeta>
      <form onSubmit={alEnviar} noValidate>
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>Corregir una solicitud rechazada</h2>
        <p className="mt-1 text-sm text-slate-500">
          Identificate con el número de solicitud, la cédula jurídica y el correo de la persona encargada.
          Llená solo los datos que querés corregir; al enviarla vuelve a quedar pendiente de revisión.
        </p>
        <div className="grid gap-x-4 sm:grid-cols-3">
          <Campo etiqueta="Número de solicitud" type="number" min="1" value={idSolicitud}
            onChange={(e) => setIdSolicitud(e.target.value)} />
          <Campo etiqueta="Cédula jurídica" placeholder="3-101-123456" value={datos.cedula_juridica}
            onChange={(e) => setDatos({ ...datos, cedula_juridica: e.target.value })} error={mensajeDe(estado.errores, 'cedula_juridica')} />
          <Campo etiqueta="Correo de la persona encargada" type="email" value={datos.correo_encargado}
            onChange={(e) => setDatos({ ...datos, correo_encargado: e.target.value })} error={mensajeDe(estado.errores, 'correo_encargado')} />
        </div>
        <CamposComercio datos={datos} setDatos={setDatos} errores={estado.errores} bloquearIdentidad />
        <div className="mt-6">
          <Boton type="submit" disabled={estado.enviando || !idSolicitud}>
            <PencilLine size={16} /> {estado.enviando ? 'Enviando…' : 'Enviar corrección'}
          </Boton>
          <AvisoError mensaje={estado.error} />
          <Aviso mensaje={estado.respuesta ? `Solicitud #${estado.respuesta.id_solicitud} corregida: volvió a quedar pendiente de revisión.` : ''} />
        </div>
      </form>
    </Tarjeta>
  );
}

const PESTANAS = [
  { clave: 'nueva', nombre: 'Nueva solicitud', icono: FilePlus2, Vista: NuevaSolicitud },
  { clave: 'corregir', nombre: 'Corregir solicitud', icono: FileText, Vista: CorregirSolicitud },
];

export default function SolicitudIncorporacion() {
  const [pestana, setPestana] = useState('nueva');
  const { Vista } = PESTANAS.find((p) => p.clave === pestana);

  return (
    <div>
      <div role="tablist" aria-label="Solicitud de incorporación" className="mb-4 inline-flex rounded-xl border border-slate-200 bg-white p-1">
        {PESTANAS.map(({ clave, nombre, icono: Icono }) => (
          <button key={clave} type="button" role="tab" aria-selected={pestana === clave} onClick={() => setPestana(clave)}
            className={`flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-bold transition-colors ${
              pestana === clave ? 'bg-[#1E3A8A] text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
            <Icono size={16} /> {nombre}
          </button>
        ))}
      </div>
      <Vista />
    </div>
  );
}
