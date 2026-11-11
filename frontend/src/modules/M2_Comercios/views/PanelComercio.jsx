import { useCallback, useEffect, useState } from 'react';
import { Clock, ExternalLink, Image as ImageIcon, Images, Mail, Phone, Plus, ScrollText, Sparkles, Store, Trash2 } from 'lucide-react';
import {
  actualizarInformacion, consultarMicrositio, consultarPublicidad, solicitarPublicidad, gestionarBanners, gestionarGaleria,
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

const Seccion = ({ titulo, descripcion, children, separada = false }) => (
  <section className={separada ? 'mt-8 border-t border-slate-200 pt-6' : ''}>
    <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>{titulo}</h2>
    {descripcion && <p className="mt-1 max-w-prose text-sm text-slate-500">{descripcion}</p>}
    {children}
  </section>
);

// Vista previa: cómo verá el cliente el micrositio con lo que se está editando.
const DIA_DE_HOY = ((new Date().getDay() + 6) % 7) + 1; // 1 = lunes … 7 = domingo

function VistaPrevia({ nombre, logotipo, descripcion, horarios, contactos }) {
  const hoy = horarios.find((h) => h.dia_semana === DIA_DE_HOY);
  const horarioHoy = !hoy || hoy.cerrado || !hoy.hora_apertura
    ? 'Cerrado hoy'
    : `Hoy de ${hoy.hora_apertura} a ${hoy.hora_cierre}`;
  const telefono = contactos.find((c) => c.tipo === 'telefono' && c.valor)?.valor;
  const correo = contactos.find((c) => c.tipo === 'correo' && c.valor)?.valor;
  const redes = contactos.filter((c) => !['telefono', 'correo', 'direccion'].includes(c.tipo) && c.valor).length;

  return (
    <aside aria-label="Vista previa del micrositio" className="sticky top-4">
      <p className="mb-2 text-sm font-semibold text-slate-500">Así lo ven tus clientes</p>
      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className={`h-16 ${C.marino}`} />
        <div className="-mt-8 px-5 pb-5">
          {logotipo
            ? <img src={logotipo} alt="" className="h-16 w-16 rounded-xl border-4 border-white bg-white object-contain shadow" />
            : <span className="flex h-16 w-16 items-center justify-center rounded-xl border-4 border-white bg-slate-100 text-slate-400 shadow"><Store size={26} /></span>}
          <p className={`mt-3 text-lg font-extrabold leading-tight ${C.marinoTexto}`}>{nombre || 'Tu comercio'}</p>
          <p className="mt-2 line-clamp-4 text-sm leading-relaxed text-slate-600">
            {descripcion?.trim() || 'Todavía no hay una descripción.'}
          </p>
          <dl className="mt-4 space-y-2 text-sm text-slate-700">
            <div className="flex items-center gap-2"><Clock size={15} className="shrink-0 text-slate-400" /><dd>{horarioHoy}</dd></div>
            {telefono && <div className="flex items-center gap-2"><Phone size={15} className="shrink-0 text-slate-400" /><dd>{telefono}</dd></div>}
            {correo && <div className="flex items-center gap-2"><Mail size={15} className="shrink-0 text-slate-400" /><dd className="truncate">{correo}</dd></div>}
          </dl>
          {redes > 0 && <p className="mt-3 text-xs text-slate-500">{redes === 1 ? '1 red social' : `${redes} redes sociales`}</p>}
        </div>
      </div>
      <a href="/m1.html" target="_blank" rel="noopener noreferrer"
        className={`mt-3 inline-flex items-center gap-1 text-sm font-bold ${C.marinoTexto} underline hover:no-underline`}>
        <ExternalLink size={14} /> Abrir el portal
      </a>
    </aside>
  );
}

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
  const [seccion, setSeccion] = useState('presentacion');
  const [publicidad, setPublicidad] = useState(null);
  const [diasDestacado, setDiasDestacado] = useState(7);

  const [estInfo, guardarInfo] = useGuardado(mensajeDeError);
  const [estLogo, guardarLogo] = useGuardado(mensajeDeError);
  const [estHorarios, guardarHor] = useGuardado(mensajeDeError);
  const [estContactos, guardarCont] = useGuardado(mensajeDeError);
  const [estPoliticas, guardarPol] = useGuardado(mensajeDeError);
  const [estBanners, guardarBan] = useGuardado(mensajeDeError);
  const [estGaleria, guardarGal] = useGuardado(mensajeDeError);
  const [estDestacar, guardarDestacar] = useGuardado(mensajeDeError);

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

  useEffect(() => {
    if (!idComercio) return;
    consultarPublicidad(idComercio).then((r) => { if (r.ok) setPublicidad(r.datos); });
  }, [idComercio]);

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

  const abiertos = horarios.filter((h) => !h.cerrado).length;
  const pendienteDestacado = publicidad?.publicidades?.find((p) => p.estado === 'Pendiente');
  const vigenteDestacado = publicidad?.publicidades?.find((p) => p.vigente);
  const secciones = [
    { clave: 'presentacion', nombre: 'Presentación', icono: Store, resumen: logotipo ? 'Con logotipo' : 'Sin logotipo' },
    { clave: 'horario', nombre: 'Horario', icono: Clock, resumen: abiertos === 7 ? 'Todos los días' : `${abiertos} días abierto` },
    { clave: 'contacto', nombre: 'Contacto', icono: Phone, resumen: contactos.length === 1 ? '1 medio' : `${contactos.length} medios` },
    { clave: 'politicas', nombre: 'Políticas', icono: ScrollText, resumen: politicas.length === 1 ? '1 política' : `${politicas.length} políticas` },
    { clave: 'banners', nombre: 'Banners', icono: ImageIcon, resumen: banners.length === 1 ? '1 imagen' : `${banners.length} imágenes` },
    { clave: 'galeria', nombre: 'Galería', icono: Images, resumen: galeria.length === 1 ? '1 imagen' : `${galeria.length} imágenes` },
    { clave: 'destacar', nombre: 'Destacar', icono: Sparkles, resumen: publicidad?.destacado ? 'Destacado' : (pendienteDestacado ? 'En revisión' : 'No destacado') },
  ];

  return (
    <div>
      <header className="mb-5">
        <h1 className={`text-3xl font-extrabold tracking-tight ${C.marinoTexto}`}>{nombre || 'Mi comercio'}</h1>
        <p className="mt-1 text-slate-600">Editá lo que ven los clientes en tu micrositio. Cada parte se guarda por separado.</p>
      </header>

      <div className="grid gap-5 lg:grid-cols-[210px_minmax(0,1fr)] xl:grid-cols-[210px_minmax(0,1fr)_270px]">
        {/* Navegación por secciones: lateral en escritorio, deslizable en celular */}
        <nav aria-label="Secciones del micrositio">
          <ul className="flex gap-2 overflow-x-auto pb-1 lg:sticky lg:top-4 lg:flex-col lg:overflow-visible lg:pb-0">
            {secciones.map(({ clave, nombre: titulo, icono: Icono, resumen }) => {
              const activa = seccion === clave;
              return (
                <li key={clave} className="shrink-0">
                  <button type="button" onClick={() => setSeccion(clave)} aria-current={activa ? 'page' : undefined}
                    className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] ${
                      activa ? `${C.marino} text-white` : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50'}`}>
                    <Icono size={18} className="shrink-0" />
                    <span>
                      <span className="block text-sm font-bold">{titulo}</span>
                      <span className={`block text-xs ${activa ? 'text-white/75' : 'text-slate-500'}`}>{resumen}</span>
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        </nav>

        <Tarjeta className="min-w-0">
          {seccion === 'presentacion' && (
            <>
              {/* CU-0001 y CU-0002: información empresarial */}
              <Seccion titulo="Presentación" descripcion="El texto con el que tu comercio se presenta en el micrositio.">
                <AreaTexto etiqueta="Descripción" value={descripcion} maxLength={2000}
                  onChange={(e) => setDescripcion(e.target.value)} error={mensajeDe(estInfo.errores, 'descripcion')} />
                <PieGuardado estado={estInfo}
                  onGuardar={() => guardarInfo(() => actualizarInformacion(idComercio, descripcion), 'Descripción guardada.')} />
              </Seccion>
              {/* CU-0003: logotipo */}
              <Seccion separada titulo="Logotipo" descripcion="JPG, PNG o WEBP de hasta 2 MB.">
                <div className="mt-3 flex flex-wrap items-end gap-4">
                  {logotipo
                    ? <img src={logotipo} alt={`Logotipo actual de ${nombre}`} className="h-20 w-20 rounded-xl border border-slate-200 object-contain" />
                    : <span className="flex h-20 w-20 items-center justify-center rounded-xl bg-slate-100 text-xs text-slate-500">Sin logotipo</span>}
                  <div className="min-w-[220px] flex-1">
                    <Campo etiqueta="Nuevo logotipo" type="file" accept="image/jpeg,image/png,image/webp"
                      onChange={(e) => setArchivoLogo(e.target.files?.[0] || null)} error={mensajeDe(estLogo.errores, 'logotipo')} />
                  </div>
                </div>
                <PieGuardado estado={estLogo} texto="Subir logotipo" deshabilitado={!archivoLogo}
                  onGuardar={() => guardarLogo(() => guardarLogotipo(idComercio, archivoLogo), 'Logotipo actualizado.', () => { setArchivoLogo(null); cargar(); })} />
              </Seccion>
            </>
          )}

          {seccion === 'horario' && (
            /* CU-0006: horarios */
            <Seccion titulo="Horario de atención" descripcion="Marcá los días cerrados; los demás necesitan hora de apertura y de cierre.">
              <div className="mt-3 overflow-x-auto">
                <table className="w-full min-w-[440px] text-sm">
                  <thead>
                    <tr className="text-left text-slate-500"><th className="py-2 font-semibold">Día</th><th className="font-semibold">Cerrado</th><th className="font-semibold">Apertura</th><th className="font-semibold">Cierre</th></tr>
                  </thead>
                  <tbody>
                    {horarios.map((h, i) => (
                      <tr key={h.dia_semana} className={`border-t border-slate-100 align-top ${h.dia_semana === DIA_DE_HOY ? 'bg-slate-50' : ''}`}>
                        <td className="py-2.5 pl-1 font-semibold text-slate-700">{DIAS[i]}</td>
                        <td className="py-2.5">
                          <input type="checkbox" aria-label={`${DIAS[i]} cerrado`} checked={h.cerrado}
                            onChange={(e) => setHorarios(cambiarEn(horarios, i, 'cerrado', e.target.checked))} />
                        </td>
                        {['hora_apertura', 'hora_cierre'].map((campo) => (
                          <td key={campo} className="py-2 pr-2">
                            <input type="time" className="rounded-lg border border-slate-300 p-1.5 disabled:bg-slate-100 disabled:text-slate-400" disabled={h.cerrado} value={h[campo] || ''}
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
          )}

          {seccion === 'contacto' && (
            /* CU-0007: contactos y redes sociales */
            <Seccion titulo="Contacto y redes sociales"
              descripcion="Para correo usá una dirección válida; para redes y sitio web, el enlace completo (https://…).">
              {contactos.length === 0 && <p className="mt-4 text-sm text-slate-500">Todavía no agregaste medios de contacto.</p>}
              {contactos.map((c, i) => (
                <div key={i} className="mt-3 grid gap-2 sm:grid-cols-[170px_1fr_auto] sm:items-end">
                  <Seleccion etiqueta="Tipo" value={c.tipo} error={mensajeDe(estContactos.errores, `contactos.${i}.tipo`)}
                    onChange={(e) => setContactos(cambiarEn(contactos, i, 'tipo', e.target.value))}>
                    {TIPOS_CONTACTO.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                  </Seleccion>
                  <Campo etiqueta="Valor" value={c.valor} error={mensajeDe(estContactos.errores, `contactos.${i}.valor`)}
                    onChange={(e) => setContactos(cambiarEn(contactos, i, 'valor', e.target.value))} />
                  <Boton variante="neutro" aria-label="Quitar contacto" onClick={() => setContactos(quitarDe(contactos, i))}><Trash2 size={16} /></Boton>
                </div>
              ))}
              <Boton variante="neutro" className="mt-4" onClick={() => setContactos([...contactos, { tipo: 'telefono', valor: '' }])}>
                <Plus size={16} /> Agregar contacto
              </Boton>
              <PieGuardado estado={estContactos}
                onGuardar={() => guardarCont(() => guardarContactos(idComercio, contactos), 'Contactos guardados.')} />
            </Seccion>
          )}

          {seccion === 'politicas' && (
            /* Políticas: las de envío llevan tarifas por zona, que consumen M5 y M6. */
            <Seccion titulo="Políticas" descripcion="La política de envío puede incluir tarifas por zona, en colones.">
              {politicas.length === 0 && <p className="mt-4 text-sm text-slate-500">Todavía no hay políticas.</p>}
              {politicas.map((p, i) => (
                <div key={i} className="mt-4 rounded-xl border border-slate-200 p-4">
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
                    <div className="mt-3 rounded-lg bg-slate-50 p-3">
                      <p className="text-sm font-semibold text-slate-700">Tarifas de envío</p>
                      {(p.configuracion_envio || []).map((t, k) => (
                        <div key={k} className="grid gap-2 sm:grid-cols-[1fr_150px_auto] sm:items-end">
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
              <Boton variante="neutro" className="mt-4" onClick={() => setPoliticas([...politicas, { tipo: 'envio', contenido: '', configuracion_envio: [] }])}>
                <Plus size={16} /> Agregar política
              </Boton>
              <PieGuardado estado={estPoliticas} onGuardar={guardarListaPoliticas} />
            </Seccion>
          )}

          {seccion === 'destacar' && (
            /* Destacar mi comercio: solicitud de publicidad que revisa la plataforma */
            <Seccion titulo="Destacar mi comercio"
              descripcion="Los comercios destacados aparecen en la portada del portal. Elegí por cuántos días; la plataforma revisa tu solicitud y, al aprobarla, el periodo empieza ese día.">
              {vigenteDestacado && (
                <p className="mt-4 rounded-xl bg-green-50 p-3 text-sm font-semibold text-green-800 ring-1 ring-green-200">
                  Tu comercio está destacado hasta el {new Date(`${vigenteDestacado.fin}T00:00:00`).toLocaleDateString('es-CR')}.
                </p>
              )}
              {pendienteDestacado ? (
                <p className="mt-4 rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-800 ring-1 ring-amber-200">
                  Tu solicitud de {pendienteDestacado.dias_solicitados} días está en revisión por la plataforma.
                </p>
              ) : (
                <>
                  <fieldset className="mt-4">
                    <legend className="text-sm font-semibold text-slate-700">Periodo</legend>
                    <div className="mt-2 grid gap-2 sm:grid-cols-3">
                      {[7, 15, 30].map((d) => (
                        <label key={d} className={`flex cursor-pointer items-center gap-2 rounded-xl px-4 py-3 text-sm font-bold ring-1 transition-colors ${
                          diasDestacado === d ? `${C.marino} text-white ring-transparent` : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'}`}>
                          <input type="radio" name="dias-destacado" value={d} checked={diasDestacado === d} className="sr-only"
                            onChange={() => setDiasDestacado(d)} />
                          {d} días
                        </label>
                      ))}
                    </div>
                  </fieldset>
                  <MensajeCampo mensaje={mensajeDe(estDestacar.errores, 'dias')} />
                  <PieGuardado estado={estDestacar} texto="Solicitar destacado"
                    onGuardar={() => guardarDestacar(() => solicitarPublicidad(idComercio, diasDestacado),
                      'Solicitud enviada: la plataforma la va a revisar.', (datos) => setPublicidad(datos))} />
                </>
              )}
              {publicidad?.publicidades?.length > 0 && (
                <div className="mt-6">
                  <p className="text-sm font-semibold text-slate-700">Historial</p>
                  <ul className="mt-2 divide-y divide-slate-100 text-sm">
                    {publicidad.publicidades.map((p) => (
                      <li key={p.id_publicidad_comercio} className="flex flex-wrap justify-between gap-2 py-2">
                        <span className="text-slate-700">{p.dias_solicitados ? `${p.dias_solicitados} días` : 'Publicidad'}{p.estado === 'Aprobada' ? ` · del ${new Date(`${p.inicio}T00:00:00`).toLocaleDateString('es-CR')} al ${new Date(`${p.fin}T00:00:00`).toLocaleDateString('es-CR')}` : ''}</span>
                        <span className="font-semibold text-slate-500">{p.vigente ? 'Vigente' : p.estado}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </Seccion>
          )}

          {listasDeImagenes.filter(({ clave }) => clave === seccion).map(({ clave, titulo, lista, setLista, campoId, estado, guardar, enviar, conActivo }) => (
            /* CU-0004 y CU-0005: banners y galería */
            <Seccion key={clave} titulo={titulo}
              descripcion="Cada imagen necesita un texto alternativo. Las que quités de la lista se borran al guardar.">
              {lista.length === 0 && <p className="mt-4 text-sm text-slate-500">Todavía no hay imágenes.</p>}
              <div className="mt-2 grid gap-3 md:grid-cols-2">
                {lista.map((x, i) => (
                  <div key={x[campoId] ?? `nuevo-${i}`} className="rounded-xl border border-slate-200 p-3">
                    <div className="flex items-start justify-between gap-2">
                      {x.ruta
                        ? <img src={x.ruta} alt={x.texto_alternativo} className="h-24 w-full max-w-[200px] rounded-lg object-cover" />
                        : <span className="flex h-24 w-full max-w-[200px] items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-500">Imagen nueva</span>}
                      <Boton variante="neutro" aria-label="Quitar imagen" onClick={() => setLista(quitarDe(lista, i))}><Trash2 size={16} /></Boton>
                    </div>
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
                ))}
              </div>
              <Boton variante="neutro" className="mt-4"
                onClick={() => setLista([...lista, conActivo ? { texto_alternativo: '', activo: true, imagen: null } : { texto_alternativo: '', imagen: null }])}>
                <Plus size={16} /> Agregar imagen
              </Boton>
              <MensajeCampo mensaje={mensajeDe(estado.errores, clave)} />
              <PieGuardado estado={estado} onGuardar={() => guardar(() => enviar(idComercio, paraEnviar(lista, campoId)), `${titulo}: cambios guardados.`, cargar)} />
            </Seccion>
          ))}
        </Tarjeta>

        <div className="hidden xl:block">
          <VistaPrevia nombre={nombre} logotipo={logotipo} descripcion={descripcion} horarios={horarios} contactos={contactos} />
        </div>
      </div>
    </div>
  );
}
