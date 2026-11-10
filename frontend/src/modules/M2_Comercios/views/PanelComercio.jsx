import { useCallback, useEffect, useState } from 'react';
import { Clock, Image as ImageIcon, Info, Phone, Plus, ScrollText, Trash2 } from 'lucide-react';
import {
  actualizarInformacion, consultarMicrositio, gestionarBanners, gestionarGaleria,
  guardarContactos, guardarHorarios, guardarLogotipo, guardarPoliticas,
} from '../services/m2Api';
import {
  AreaTexto, Aviso, AvisoError, Boton, C, Campo, Cargando, MensajeCampo, Seleccion, Tarjeta,
  erroresDeCampo, mensajeDe, textoDeError, useSesion,
} from '../components/comunes';

// "Mi comercio": el encargado del comercio edita su micrositio (CU-0001 a CU-0007).
// Cada sección guarda por separado contra su ruta y muestra sus propios errores.

const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
const TIPOS_CONTACTO = [
  ['telefono', 'Teléfono'], ['correo', 'Correo'], ['direccion', 'Dirección'], ['whatsapp', 'WhatsApp'],
  ['facebook', 'Facebook'], ['instagram', 'Instagram'], ['tiktok', 'TikTok'], ['sitio_web', 'Sitio web'],
];
const TIPOS_POLITICA = [
  ['envio', 'Envío'], ['devolucion', 'Devolución'], ['garantia', 'Garantía'], ['privacidad', 'Privacidad'], ['otra', 'Otra'],
];

// Los 7 días siempre presentes: el backend exige la semana completa.
const semanaCompleta = (horarios = []) =>
  DIAS.map((_, i) => {
    const h = horarios.find((x) => Number(x.dia_semana) === i + 1);
    return {
      dia_semana: i + 1,
      cerrado: h ? Boolean(h.cerrado) : false,
      hora_apertura: h?.hora_apertura || '',
      hora_cierre: h?.hora_cierre || '',
    };
  });

// Estado de guardado de una sección: mensaje general, errores por campo y aviso de éxito.
function useGuardado(mensajeDeError) {
  const [estado, setEstado] = useState({ guardando: false, error: '', errores: {}, aviso: '' });
  const guardar = useCallback(async (accion, textoExito, alTerminar) => {
    setEstado({ guardando: true, error: '', errores: {}, aviso: '' });
    const r = await accion();
    if (r.ok) {
      setEstado({ guardando: false, error: '', errores: {}, aviso: textoExito });
      alTerminar?.(r.datos);
      return;
    }
    const errores = erroresDeCampo(r);
    const general = Object.keys(errores).length ? 'Revisá los datos marcados.' : (mensajeDeError?.(r) || textoDeError(r));
    setEstado({ guardando: false, errores, aviso: '', error: general });
  }, [mensajeDeError]);
  return [estado, guardar];
}

const Seccion = ({ icono: Icono, titulo, descripcion, children }) => (
  <Tarjeta className="mt-4">
    <h2 className={`flex items-center gap-2 text-lg font-extrabold ${C.marinoTexto}`}><Icono size={20} /> {titulo}</h2>
    {descripcion && <p className="mt-1 text-sm text-slate-500">{descripcion}</p>}
    {children}
  </Tarjeta>
);

const PieGuardado = ({ estado, onGuardar, texto = 'Guardar', deshabilitado = false }) => (
  <div className="mt-4">
    <Boton onClick={onGuardar} disabled={estado.guardando || deshabilitado}>{estado.guardando ? 'Guardando…' : texto}</Boton>
    <AvisoError mensaje={estado.error} />
    <Aviso mensaje={estado.aviso} />
  </div>
);

// Utilidades para listas editables.
const cambiarEn = (lista, i, campo, valor) => lista.map((x, j) => (j === i ? { ...x, [campo]: valor } : x));
const quitarDe = (lista, i) => lista.filter((_, j) => j !== i);

// Banners y galería: los existentes van con su id (y sin archivo si no cambió la foto);
// los nuevos van con su imagen. El orden es el de la lista en pantalla.
const paraEnviar = (lista, campoId) =>
  lista.map(({ ruta, imagen, ...resto }, i) => {
    const elemento = { ...resto, orden: i + 1 };
    if (imagen) elemento.imagen = imagen;
    if (!elemento[campoId]) delete elemento[campoId];
    return elemento;
  });

export default function PanelComercio() {
  const { usuario, mensajeDeError } = useSesion();
  const idComercio = usuario?.id_comercio;

  const [cargando, setCargando] = useState(true);
  const [errorCarga, setErrorCarga] = useState('');
  const [nombre, setNombre] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [logotipo, setLogotipo] = useState(null);
  const [archivoLogo, setArchivoLogo] = useState(null);
  const [horarios, setHorarios] = useState(semanaCompleta());
  const [contactos, setContactos] = useState([]);
  const [politicas, setPoliticas] = useState([]);
  const [banners, setBanners] = useState([]);
  const [galeria, setGaleria] = useState([]);

  const [estInfo, guardarInfo] = useGuardado(mensajeDeError);
  const [estLogo, guardarLogo] = useGuardado(mensajeDeError);
  const [estHorarios, guardarHor] = useGuardado(mensajeDeError);
  const [estContactos, guardarCont] = useGuardado(mensajeDeError);
  const [estPoliticas, guardarPol] = useGuardado(mensajeDeError);
  const [estBanners, guardarBan] = useGuardado(mensajeDeError);
  const [estGaleria, guardarGal] = useGuardado(mensajeDeError);

  const aplicar = (d) => {
    setNombre(d.nombre_comercial || '');
    setDescripcion(d.descripcion || '');
    setLogotipo(d.logotipo || null);
    setHorarios(semanaCompleta(d.horarios));
    setContactos((d.contactos || []).map(({ tipo, valor }) => ({ tipo, valor: valor || '' })));
    setPoliticas((d.politicas || []).map(({ tipo, contenido, configuracion_envio: tarifas }) => ({
      tipo, contenido: contenido || '', configuracion_envio: tarifas || [],
    })));
    setBanners((d.banners || []).map((b) => ({
      id_banner: b.id_banner, ruta: b.ruta, texto_alternativo: b.texto_alternativo || '', activo: Boolean(b.activo), imagen: null,
    })));
    setGaleria((d.galeria || []).map((g) => ({
      id_imagen: g.id_imagen, ruta: g.ruta, texto_alternativo: g.texto_alternativo || '', imagen: null,
    })));
  };

  const cargar = useCallback(async () => {
    if (!idComercio) return;
    setCargando(true);
    setErrorCarga('');
    const r = await consultarMicrositio(idComercio);
    if (r.ok) aplicar(r.datos);
    else setErrorCarga(mensajeDeError?.(r) || textoDeError(r));
    setCargando(false);
  }, [idComercio, mensajeDeError]);

  useEffect(() => { cargar(); }, [cargar]);

  if (!idComercio) {
    return <Tarjeta><AvisoError mensaje="Tu usuario no tiene un comercio asociado." /></Tarjeta>;
  }
  if (cargando) return <Tarjeta><Cargando texto="Cargando tu micrositio…" /></Tarjeta>;
  if (errorCarga) {
    return (
      <Tarjeta>
        <AvisoError mensaje={errorCarga} />
        <Boton variante="neutro" className="mt-3" onClick={cargar}>Reintentar</Boton>
      </Tarjeta>
    );
  }

  const guardarSemana = () => guardarHor(() => guardarHorarios(idComercio, horarios.map((h) => (h.cerrado
    ? { dia_semana: h.dia_semana, cerrado: true, hora_apertura: null, hora_cierre: null }
    : { dia_semana: h.dia_semana, cerrado: false, hora_apertura: h.hora_apertura || null, hora_cierre: h.hora_cierre || null }
  ))), 'Horario guardado.');

  const guardarListaPoliticas = () => guardarPol(() => guardarPoliticas(idComercio, politicas.map((p) => (p.tipo === 'envio'
    ? {
      tipo: p.tipo,
      contenido: p.contenido,
      configuracion_envio: (p.configuracion_envio || []).map((t) => ({ zona: t.zona, costo: Number(t.costo), moneda: 'CRC' })),
    }
    : { tipo: p.tipo, contenido: p.contenido }
  ))), 'Políticas guardadas.');

  const cambiarTarifa = (i, k, campo, valor) => setPoliticas(cambiarEn(politicas, i, 'configuracion_envio',
    politicas[i].configuracion_envio.map((t, m) => (m === k ? { ...t, [campo]: valor } : t))));

  const listasDeImagenes = [
    { clave: 'banners', titulo: 'Banners', lista: banners, setLista: setBanners, campoId: 'id_banner', estado: estBanners, guardar: guardarBan, enviar: gestionarBanners, conActivo: true },
    { clave: 'galeria', titulo: 'Galería de imágenes', lista: galeria, setLista: setGaleria, campoId: 'id_imagen', estado: estGaleria, guardar: guardarGal, enviar: gestionarGaleria, conActivo: false },
  ];

  return (
    <div>
      <Tarjeta>
        <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>Mi comercio</h1>
        <p className="mt-1 text-sm text-slate-500">Editá la información de {nombre || 'tu comercio'} que ven los clientes en su micrositio.</p>
      </Tarjeta>

      {/* CU-0001 y CU-0002: información empresarial */}
      <Seccion icono={Info} titulo="Información del comercio" descripcion="Texto de presentación que aparece en el micrositio.">
        <AreaTexto etiqueta="Descripción" value={descripcion} maxLength={2000}
          onChange={(e) => setDescripcion(e.target.value)} error={mensajeDe(estInfo.errores, 'descripcion')} />
        <PieGuardado estado={estInfo}
          onGuardar={() => guardarInfo(() => actualizarInformacion(idComercio, descripcion), 'Información guardada.')} />
      </Seccion>

      {/* CU-0003: logotipo */}
      <Seccion icono={ImageIcon} titulo="Logotipo" descripcion="JPG, PNG o WEBP de hasta 2 MB.">
        {logotipo && <img src={logotipo} alt={`Logotipo de ${nombre}`} className="mt-3 h-20 w-20 rounded-lg border border-slate-200 object-contain" />}
        <Campo etiqueta="Nuevo logotipo" type="file" accept="image/jpeg,image/png,image/webp"
          onChange={(e) => setArchivoLogo(e.target.files?.[0] || null)} error={mensajeDe(estLogo.errores, 'logotipo')} />
        <PieGuardado estado={estLogo} texto="Subir logotipo" deshabilitado={!archivoLogo}
          onGuardar={() => guardarLogo(() => guardarLogotipo(idComercio, archivoLogo), 'Logotipo actualizado.', () => { setArchivoLogo(null); cargar(); })} />
      </Seccion>

      {/* CU-0006: horarios */}
      <Seccion icono={Clock} titulo="Horario de atención" descripcion="Marcá los días cerrados; los demás necesitan hora de apertura y de cierre.">
        <div className="mt-2 overflow-x-auto">
          <table className="w-full min-w-[480px] text-sm">
            <thead>
              <tr className="text-left text-slate-500"><th className="py-2">Día</th><th>Cerrado</th><th>Apertura</th><th>Cierre</th></tr>
            </thead>
            <tbody>
              {horarios.map((h, i) => (
                <tr key={h.dia_semana} className="border-t border-slate-100 align-top">
                  <td className="py-2 font-semibold text-slate-700">{DIAS[i]}</td>
                  <td className="py-2">
                    <input type="checkbox" aria-label={`${DIAS[i]} cerrado`} checked={h.cerrado}
                      onChange={(e) => setHorarios(cambiarEn(horarios, i, 'cerrado', e.target.checked))} />
                  </td>
                  {['hora_apertura', 'hora_cierre'].map((campo) => (
                    <td key={campo} className="py-2 pr-2">
                      <input type="time" className="rounded-lg border border-slate-300 p-1.5" disabled={h.cerrado} value={h[campo] || ''}
                        aria-label={`${DIAS[i]}, hora de ${campo === 'hora_apertura' ? 'apertura' : 'cierre'}`}
                        onChange={(e) => setHorarios(cambiarEn(horarios, i, campo, e.target.value))} />
                      <MensajeCampo mensaje={mensajeDe(estHorarios.errores, `horarios.${i}.${campo}`)} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <MensajeCampo mensaje={mensajeDe(estHorarios.errores, 'horarios')} />
        <PieGuardado estado={estHorarios} onGuardar={guardarSemana} />
      </Seccion>

      {/* CU-0007: contactos y redes sociales */}
      <Seccion icono={Phone} titulo="Contacto y redes sociales"
        descripcion="Para correo usá una dirección válida; para redes y sitio web, el enlace completo (https://…).">
        {contactos.map((c, i) => (
          <div key={i} className="mt-2 grid gap-2 sm:grid-cols-[180px_1fr_auto] sm:items-end">
            <Seleccion etiqueta="Tipo" value={c.tipo} error={mensajeDe(estContactos.errores, `contactos.${i}.tipo`)}
              onChange={(e) => setContactos(cambiarEn(contactos, i, 'tipo', e.target.value))}>
              {TIPOS_CONTACTO.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
            </Seleccion>
            <Campo etiqueta="Valor" value={c.valor} error={mensajeDe(estContactos.errores, `contactos.${i}.valor`)}
              onChange={(e) => setContactos(cambiarEn(contactos, i, 'valor', e.target.value))} />
            <Boton variante="neutro" aria-label="Quitar contacto" onClick={() => setContactos(quitarDe(contactos, i))}><Trash2 size={16} /></Boton>
          </div>
        ))}
        <Boton variante="neutro" className="mt-3" onClick={() => setContactos([...contactos, { tipo: 'telefono', valor: '' }])}>
          <Plus size={16} /> Agregar contacto
        </Boton>
        <PieGuardado estado={estContactos}
          onGuardar={() => guardarCont(() => guardarContactos(idComercio, contactos), 'Contactos guardados.')} />
      </Seccion>

      {/* Políticas: las de envío llevan tarifas por zona, que consumen M5 y M6. */}
      <Seccion icono={ScrollText} titulo="Políticas" descripcion="La política de envío puede incluir tarifas por zona, en colones.">
        {politicas.map((p, i) => (
          <div key={i} className="mt-3 rounded-xl border border-slate-200 p-3">
            <div className="grid gap-2 sm:grid-cols-[180px_auto] sm:items-end sm:justify-between">
              <Seleccion etiqueta="Tipo" value={p.tipo} error={mensajeDe(estPoliticas.errores, `politicas.${i}.tipo`)}
                onChange={(e) => setPoliticas(cambiarEn(politicas, i, 'tipo', e.target.value))}>
                {TIPOS_POLITICA.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
              </Seleccion>
              <Boton variante="neutro" aria-label="Quitar política" onClick={() => setPoliticas(quitarDe(politicas, i))}><Trash2 size={16} /></Boton>
            </div>
            <AreaTexto etiqueta="Contenido" value={p.contenido} error={mensajeDe(estPoliticas.errores, `politicas.${i}.contenido`)}
              onChange={(e) => setPoliticas(cambiarEn(politicas, i, 'contenido', e.target.value))} />
            {p.tipo === 'envio' && (
              <div className="mt-3">
                <p className="text-sm font-semibold text-slate-700">Tarifas de envío</p>
                {(p.configuracion_envio || []).map((t, k) => (
                  <div key={k} className="grid gap-2 sm:grid-cols-[1fr_160px_auto] sm:items-end">
                    <Campo etiqueta="Zona" value={t.zona || ''} onChange={(e) => cambiarTarifa(i, k, 'zona', e.target.value)}
                      error={mensajeDe(estPoliticas.errores, `politicas.${i}.configuracion_envio.${k}.zona`)} />
                    <Campo etiqueta="Costo (₡)" type="number" min="0" step="0.01" value={t.costo ?? ''}
                      onChange={(e) => cambiarTarifa(i, k, 'costo', e.target.value)}
                      error={mensajeDe(estPoliticas.errores, `politicas.${i}.configuracion_envio.${k}.costo`)} />
                    <Boton variante="neutro" aria-label="Quitar tarifa"
                      onClick={() => setPoliticas(cambiarEn(politicas, i, 'configuracion_envio', quitarDe(p.configuracion_envio, k)))}>
                      <Trash2 size={16} />
                    </Boton>
                  </div>
                ))}
                <Boton variante="neutro" className="mt-2"
                  onClick={() => setPoliticas(cambiarEn(politicas, i, 'configuracion_envio', [...(p.configuracion_envio || []), { zona: '', costo: '', moneda: 'CRC' }]))}>
                  <Plus size={16} /> Agregar tarifa
                </Boton>
              </div>
            )}
          </div>
        ))}
        <Boton variante="neutro" className="mt-3" onClick={() => setPoliticas([...politicas, { tipo: 'envio', contenido: '', configuracion_envio: [] }])}>
          <Plus size={16} /> Agregar política
        </Boton>
        <PieGuardado estado={estPoliticas} onGuardar={guardarListaPoliticas} />
      </Seccion>

      {/* CU-0004 y CU-0005: banners y galería */}
      {listasDeImagenes.map(({ clave, titulo, lista, setLista, campoId, estado, guardar, enviar, conActivo }) => (
        <Seccion key={clave} icono={ImageIcon} titulo={titulo}
          descripcion="Cada imagen necesita un texto alternativo. Las que quités de la lista se borran al guardar.">
          {lista.map((x, i) => (
            <div key={x[campoId] ?? `nuevo-${i}`} className="mt-3 grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[96px_1fr_auto] sm:items-end">
              {x.ruta
                ? <img src={x.ruta} alt={x.texto_alternativo} className="h-20 w-24 rounded-lg object-cover" />
                : <span className="flex h-20 w-24 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-500">Nueva</span>}
              <div>
                <Campo etiqueta="Texto alternativo" value={x.texto_alternativo}
                  onChange={(e) => setLista(cambiarEn(lista, i, 'texto_alternativo', e.target.value))}
                  error={mensajeDe(estado.errores, `${clave}.${i}.texto_alternativo`)} />
                <Campo etiqueta={x.ruta ? 'Reemplazar imagen (opcional)' : 'Imagen'} type="file" accept="image/jpeg,image/png,image/webp"
                  onChange={(e) => setLista(cambiarEn(lista, i, 'imagen', e.target.files?.[0] || null))}
                  error={mensajeDe(estado.errores, `${clave}.${i}.imagen`)} />
                {conActivo && (
                  <label className="mt-2 flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={x.activo} onChange={(e) => setLista(cambiarEn(lista, i, 'activo', e.target.checked))} />
                    Visible en el micrositio
                  </label>
                )}
              </div>
              <Boton variante="neutro" aria-label="Quitar imagen" onClick={() => setLista(quitarDe(lista, i))}><Trash2 size={16} /></Boton>
            </div>
          ))}
          <Boton variante="neutro" className="mt-3"
            onClick={() => setLista([...lista, conActivo ? { texto_alternativo: '', activo: true, imagen: null } : { texto_alternativo: '', imagen: null }])}>
            <Plus size={16} /> Agregar imagen
          </Boton>
          <MensajeCampo mensaje={mensajeDe(estado.errores, clave)} />
          <PieGuardado estado={estado} onGuardar={() => guardar(() => enviar(idComercio, paraEnviar(lista, campoId)), `${titulo}: cambios guardados.`, cargar)} />
        </Seccion>
      ))}
    </div>
  );
}
