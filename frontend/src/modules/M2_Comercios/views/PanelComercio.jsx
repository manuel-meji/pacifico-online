import { useCallback, useEffect, useState } from 'react';
import {
  ArrowDown, ArrowUp, Clock, Copy, Home, Image as ImageIcon, Images, Mail, Phone, Plus, ScrollText, Sparkles, Store, Trash2,
} from 'lucide-react';
import {
  actualizarInformacion, consultarMicrositio, consultarPublicidad, solicitarPublicidad, gestionarBanners, gestionarGaleria,
  guardarContactos, guardarHorarios, guardarLogotipo, guardarPoliticas, quitarLogotipo,
} from '../services/m2Api';
import {
  AreaTexto, Aviso, AvisoError, Boton, BotonQuitar, C, Campo, Cargando, MensajeCampo, Seleccion, Tarjeta,
  MAXIMO_ARCHIVO, MAXIMO_ENVIO, erroresDeCampo, fechaCorta, medirImagen, mensajeDe, pesoTexto, textoDeError, textoOpcion, useSesion,
} from '../components/comunes';

// "Mi comercio": el encargado del comercio edita su micrositio (CU-0001 a CU-0007).
// Cada sección guarda por separado contra su ruta y muestra sus propios errores. Las secciones con
// cambios sin guardar se marcan, y el navegador avisa antes de salir de la página (hallazgo 36).

const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
// Tipo de contacto, nombre, ejemplo y ayuda (hallazgo 32). El servidor completa y normaliza los
// enlaces: basta con el usuario de la red.
const TIPOS_CONTACTO = [
  ['telefono', 'Teléfono', '8888-8888', 'Número de Costa Rica de 8 dígitos.'],
  ['correo', 'Correo', 'contacto@tucomercio.com', ''],
  ['direccion', 'Dirección', 'Liberia, Guanacaste, 100 m norte del parque', 'Provincia, cantón y señas.'],
  ['whatsapp', 'WhatsApp', '8888-8888', 'El número; en el portal abre el chat.'],
  ['facebook', 'Facebook', 'facebook.com/tucomercio', 'Tu usuario o el enlace de tu página.'],
  ['instagram', 'Instagram', '@tucomercio', 'Tu usuario o el enlace de tu perfil.'],
  ['tiktok', 'TikTok', '@tucomercio', 'Tu usuario o el enlace de tu perfil.'],
  ['sitio_web', 'Sitio web', 'www.tucomercio.com', ''],
];
const DATOS_CONTACTO = Object.fromEntries(TIPOS_CONTACTO.map(([v, ...resto]) => [v, resto]));
const TIPOS_POLITICA = [
  ['envio', 'Envío'], ['devolucion', 'Devolución'], ['garantia', 'Garantía'], ['privacidad', 'Privacidad'], ['otra', 'Otra'],
];
// Zonas que reconoce M5 al calcular el envío (hallazgo 34).
const ZONAS_ENVIO = ['GAM', 'Resto del país'];

// Medidas que exige el servidor, revisadas al elegir la imagen para decir cuánto mide (hallazgo 28).
const REGLAS_IMAGEN = {
  logotipo: ({ ancho, alto }) => (ancho >= 200 && alto >= 200 && ancho / alto >= 0.75 && ancho / alto <= 4 / 3
    ? null : `Tu imagen mide ${ancho} × ${alto} px. El logotipo tiene que ser cuadrado o casi cuadrado y medir al menos 200 × 200 px.`),
  banners: ({ ancho, alto }) => (ancho >= 1200 && ancho >= 2 * alto
    ? null : `Tu imagen mide ${ancho} × ${alto} px. El banner necesita al menos 1200 px de ancho y ser el doble de ancho que de alto (recomendado: 1600 × 400 px).`),
  galeria: ({ ancho, alto }) => (ancho >= 600 && alto >= 400
    ? null : `Tu imagen mide ${ancho} × ${alto} px. Cada foto de la galería tiene que medir al menos 600 × 400 px.`),
};
const revisarImagen = async (tipo, archivo) => {
  if (!archivo) return null;
  // Hallazgo 48: más de 2 MB no llega al servidor; se avisa al elegirla.
  if (archivo.size > MAXIMO_ARCHIVO) return `Tu imagen pesa ${pesoTexto(archivo.size)}. Cada imagen puede pesar como máximo 2 MB.`;
  const medidas = await medirImagen(archivo);
  return medidas ? REGLAS_IMAGEN[tipo](medidas) : null;
};

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
  // Hallazgo 47: los errores del servidor vienen por posición (contactos.2.valor). Al quitar o mover una
  // fila ya no corresponden a la misma fila, así que se borran; el próximo guardado los vuelve a revisar.
  const limpiarErrores = useCallback(() => setEstado((e) => (
    Object.keys(e.errores).length || e.error ? { ...e, errores: {}, error: '' } : e)), []);
  return [estado, guardar, limpiarErrores];
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

function VistaPrevia({ idComercio, nombre, logotipo, descripcion, horarios, contactos }) {
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
      {idComercio && (
        <a href={`/m1.html?pantalla=comercio&id=${idComercio}&vista_previa=1`}
          className={`mt-3 inline-flex items-center gap-1 text-sm font-bold ${C.marinoTexto} underline hover:no-underline`}>
          <Home size={14} /> Abrir el portal
        </a>
      )}
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

// Vista previa de imágenes: muestra el archivo elegido antes de guardarlo (con la
// etiqueta «Sin guardar») y, si no hay archivo nuevo, la imagen ya guardada.
function useUrlLocal(archivo) {
  const [url, setUrl] = useState(null);
  useEffect(() => {
    if (!archivo) { setUrl(null); return undefined; }
    const local = URL.createObjectURL(archivo);
    setUrl(local);
    return () => URL.revokeObjectURL(local);
  }, [archivo]);
  return url;
}

function Miniatura({ ruta, archivo, alt, className, vacio }) {
  const local = useUrlLocal(archivo);
  const src = local || ruta;
  if (!src) return <span className={`flex items-center justify-center bg-slate-100 text-center text-xs text-slate-500 ${className}`}>{vacio}</span>;
  return (
    <span className="relative inline-block w-full max-w-[200px]">
      <img src={src} alt={alt} className={`border border-slate-200 ${className}`} />
      {local && <span className="absolute left-1 top-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sin guardar</span>}
    </span>
  );
}

// Utilidades para listas editables.
const cambiarEn = (lista, i, campo, valor) => lista.map((x, j) => (j === i ? { ...x, [campo]: valor } : x));
const quitarDe = (lista, i) => lista.filter((_, j) => j !== i);
// Hallazgo 27: mueve un elemento una posición arriba (-1) o abajo (+1).
const mover = (lista, i, paso) => {
  const j = i + paso;
  if (j < 0 || j >= lista.length) return lista;
  const copia = [...lista];
  [copia[i], copia[j]] = [copia[j], copia[i]];
  return copia;
};
let siguienteClave = 1;
const claveNueva = () => `nuevo-${siguienteClave++}`;

// Banners y galería: los existentes van con su id (y sin archivo si no cambió la foto);
// los nuevos van con su imagen. El orden es el de la lista en pantalla.
const paraEnviar = (lista, campoId) =>
  lista.map(({ ruta, imagen, clave, ...resto }, i) => {
    const elemento = { ...resto, orden: i + 1 };
    if (imagen) elemento.imagen = imagen;
    if (!elemento[campoId]) delete elemento[campoId];
    return elemento;
  });

// Lo guardado de cada sección, para saber si hay cambios sin guardar (hallazgo 36).
const firmaImagenes = (lista, campoId) => JSON.stringify(lista.map((x) => [x[campoId] ?? null, x.texto_alternativo, x.activo ?? null, Boolean(x.imagen)]));
const firmas = ({ descripcion, horarios, contactos, politicas, banners, galeria }) => ({
  presentacion: JSON.stringify(descripcion ?? ''),
  horario: JSON.stringify(horarios),
  contacto: JSON.stringify(contactos),
  politicas: JSON.stringify(politicas),
  banners: firmaImagenes(banners, 'id_banner'),
  galeria: firmaImagenes(galeria, 'id_imagen'),
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
  const [guardado, setGuardado] = useState({});
  const [horariosGuardados, setHorariosGuardados] = useState(semanaCompleta());
  const [erroresImagen, setErroresImagen] = useState({});
  const [confirmarQuitarLogo, setConfirmarQuitarLogo] = useState(false);

  const [estInfo, guardarInfo] = useGuardado(mensajeDeError);
  const [estLogo, guardarLogo] = useGuardado(mensajeDeError);
  const [estHorarios, guardarHor] = useGuardado(mensajeDeError);
  const [estContactos, guardarCont, limpiarContactos] = useGuardado(mensajeDeError);
  const [estPoliticas, guardarPol, limpiarPoliticas] = useGuardado(mensajeDeError);
  const [estBanners, guardarBan, limpiarBanners] = useGuardado(mensajeDeError);
  const [estGaleria, guardarGal, limpiarGaleria] = useGuardado(mensajeDeError);
  const [estDestacar, guardarDestacar] = useGuardado(mensajeDeError);

  const aplicar = (d) => {
    const datos = {
      descripcion: d.descripcion || '',
      horarios: semanaCompleta(d.horarios),
      contactos: (d.contactos || []).map(({ tipo, valor }) => ({ tipo, valor: valor || '' })),
      politicas: (d.politicas || []).map(({ tipo, contenido, configuracion_envio: tarifas }) => ({
        tipo, contenido: contenido || '', configuracion_envio: tarifas || [],
      })),
      banners: (d.banners || []).map((b) => ({
        id_banner: b.id_banner, ruta: b.ruta, texto_alternativo: b.texto_alternativo || '', activo: Boolean(b.activo), imagen: null,
      })),
      galeria: (d.galeria || []).map((g) => ({
        id_imagen: g.id_imagen, ruta: g.ruta, texto_alternativo: g.texto_alternativo || '', imagen: null,
      })),
    };
    setNombre(d.nombre_comercial || '');
    setDescripcion(datos.descripcion);
    setLogotipo(d.logotipo || null);
    setHorarios(datos.horarios);
    setHorariosGuardados(datos.horarios);
    setContactos(datos.contactos);
    setPoliticas(datos.politicas);
    setBanners(datos.banners);
    setGaleria(datos.galeria);
    setGuardado(firmas(datos));
    setErroresImagen({});
  };
  // Marca una sección como guardada con lo que tiene ahora.
  const marcarGuardado = (clave, firma) => setGuardado((g) => ({ ...g, [clave]: firma }));

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

  // Después de guardar imágenes o el logotipo se vuelve a leer solo esa parte, para no borrar lo que
  // la persona tenga sin guardar en otras secciones.
  const recargar = async (clave) => {
    const r = await consultarMicrositio(idComercio);
    if (!r.ok) return;
    const d = r.datos;
    if (clave === 'logotipo') setLogotipo(d.logotipo || null);
    if (clave === 'banners') {
      const lista = (d.banners || []).map((b) => ({
        id_banner: b.id_banner, ruta: b.ruta, texto_alternativo: b.texto_alternativo || '', activo: Boolean(b.activo), imagen: null,
      }));
      setBanners(lista);
      marcarGuardado('banners', firmaImagenes(lista, 'id_banner'));
    }
    if (clave === 'galeria') {
      const lista = (d.galeria || []).map((g) => ({ id_imagen: g.id_imagen, ruta: g.ruta, texto_alternativo: g.texto_alternativo || '', imagen: null }));
      setGaleria(lista);
      marcarGuardado('galeria', firmaImagenes(lista, 'id_imagen'));
    }
    setErroresImagen({});
  };

  useEffect(() => {
    if (!idComercio) return;
    consultarPublicidad(idComercio).then((r) => { if (r.ok) setPublicidad(r.datos); });
  }, [idComercio]);

  const actuales = firmas({ descripcion, horarios, contactos, politicas, banners, galeria });
  const sinGuardar = Object.keys(actuales).filter((clave) => guardado[clave] !== undefined && guardado[clave] !== actuales[clave]);
  if (archivoLogo) sinGuardar.push('presentacion');
  const haySinGuardar = sinGuardar.length > 0;

  // Hallazgo 36: el navegador pregunta antes de recargar o salir si quedó algo sin guardar.
  useEffect(() => {
    if (!haySinGuardar) return undefined;
    const avisar = (e) => { e.preventDefault(); e.returnValue = ''; };
    window.addEventListener('beforeunload', avisar);
    return () => window.removeEventListener('beforeunload', avisar);
  }, [haySinGuardar]);

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
  ))), 'Horario guardado.', () => { setHorariosGuardados(horarios); marcarGuardado('horario', actuales.horario); });

  // Copia el horario del lunes a los demás días abiertos (hallazgo 36).
  const copiarLunes = () => {
    const lunes = horarios[0];
    setHorarios(horarios.map((h, i) => (i === 0 || h.cerrado ? h : { ...h, hora_apertura: lunes.hora_apertura, hora_cierre: lunes.hora_cierre })));
  };

  const guardarListaPoliticas = () => guardarPol(() => guardarPoliticas(idComercio, politicas.map((p) => (p.tipo === 'envio'
    ? {
      tipo: p.tipo,
      contenido: p.contenido,
      configuracion_envio: (p.configuracion_envio || []).map((t) => ({ zona: t.zona, costo: t.costo === '' ? null : Number(t.costo), moneda: 'CRC' })),
    }
    : { tipo: p.tipo, contenido: p.contenido }
  ))), 'Políticas guardadas.', () => marcarGuardado('politicas', actuales.politicas));

  // Los contactos vuelven del servidor normalizados (por ejemplo, @usuario → https://instagram.com/usuario).
  const guardarListaContactos = () => guardarCont(() => guardarContactos(idComercio, contactos), 'Contactos guardados.', (datos) => {
    const normalizados = Array.isArray(datos) ? datos.map(({ tipo, valor }) => ({ tipo, valor: valor || '' })) : contactos;
    setContactos(normalizados);
    marcarGuardado('contacto', JSON.stringify(normalizados));
  });

  const guardarDescripcion = () => guardarInfo(() => actualizarInformacion(idComercio, descripcion.trim() || null),
    descripcion.trim() ? 'Descripción guardada.' : 'Descripción quitada. El micrositio no mostrará descripción.',
    () => marcarGuardado('presentacion', actuales.presentacion));

  const elegirLogotipo = async (archivo) => {
    const error = await revisarImagen('logotipo', archivo);
    setErroresImagen((e) => ({ ...e, logotipo: error || '' }));
    setArchivoLogo(error ? null : archivo);
  };

  // Hallazgo 28: la imagen se mide al elegirla; si no cumple, se dice cuánto mide y no se agrega.
  // Hallazgo 47: el error se guarda con la identidad de la fila (su id o su clave), no con su posición,
  // para que no pase a otra fila al quitarla, moverla o agregar una nueva.
  const elegirImagen = async (clave, setLista, lista, i, archivo, fila) => {
    const error = await revisarImagen(clave, archivo);
    setErroresImagen((e) => ({ ...e, [`${clave}.${fila}`]: error || '' }));
    if (!error) setLista(cambiarEn(lista, i, 'imagen', archivo || null));
  };
  const olvidarErrorImagen = (clave, fila) => setErroresImagen((e) => {
    if (!(`${clave}.${fila}` in e)) return e;
    const copia = { ...e };
    delete copia[`${clave}.${fila}`];
    return copia;
  });

  const cambiarTarifa = (i, k, campo, valor) => setPoliticas(cambiarEn(politicas, i, 'configuracion_envio',
    politicas[i].configuracion_envio.map((t, m) => (m === k ? { ...t, [campo]: valor } : t))));

  const listasDeImagenes = [
    { clave: 'banners', titulo: 'Banners', lista: banners, setLista: setBanners, campoId: 'id_banner', estado: estBanners, guardar: guardarBan, limpiar: limpiarBanners, enviar: gestionarBanners, conActivo: true,
      medidas: 'JPG, PNG o WEBP de hasta 2 MB, horizontal (el ancho al menos el doble del alto) y de al menos 1200 px de ancho. Recomendado: 1600 × 400 px.',
      uso: 'Solo el primer banner visible se muestra, como portada de tu micrositio. Para mostrar otro, subilo al primer lugar o desmarcá «Visible» en los de arriba.',
      vacio: <span className="flex aspect-[4/1] w-full max-w-[200px] items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 text-[11px] text-slate-500">Banner horizontal · 1600 × 400 px</span> },
    { clave: 'galeria', titulo: 'Galería de imágenes', lista: galeria, setLista: setGaleria, campoId: 'id_imagen', estado: estGaleria, guardar: guardarGal, limpiar: limpiarGaleria, enviar: gestionarGaleria, conActivo: false,
      medidas: 'JPG, PNG o WEBP de hasta 2 MB y de al menos 600 × 400 px.',
      uso: 'En el micrositio se muestran todas, en este orden.',
      vacio: 'Elegí una imagen' },
  ];

  // Resúmenes con lo guardado, no con lo que se está editando (hallazgo 36).
  const abiertos = horariosGuardados.filter((h) => !h.cerrado).length;
  const pendienteDestacado = publicidad?.publicidades?.find((p) => p.estado === 'Pendiente');
  const vigenteDestacado = publicidad?.publicidades?.find((p) => p.vigente);
  // Hallazgo 38: si hay un destacado aprobado que no terminó, uno nuevo lo extiende.
  const hoyIso = new Date().toISOString().slice(0, 10);
  const finUltimoDestacado = (publicidad?.publicidades || [])
    .filter((p) => p.estado === 'Aprobada' && !p.retirada && p.fin >= hoyIso).map((p) => p.fin).sort().pop();
  const inicioExtension = finUltimoDestacado
    ? (() => { const d = new Date(`${finUltimoDestacado}T00:00:00`); d.setDate(d.getDate() + 1); return d; })()
    : null;
  const estadoPublicidad = (p) => {
    if (p.retirada) return 'Retirado';
    if (p.vigente) return 'Vigente';
    if (p.estado === 'Aprobada') return p.inicio > hoyIso ? 'Programado' : 'Terminado';
    return p.estado;
  };
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

      {/* Hallazgo 45: grid-cols-1 y min-w-0 limitan la columna al ancho de la pantalla en celular; sin eso,
          la tira de secciones estiraba toda la página en vez de deslizarse sola. */}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-[210px_minmax(0,1fr)] xl:grid-cols-[210px_minmax(0,1fr)_270px]">
        {/* Navegación por secciones: lateral en escritorio, deslizable en celular */}
        <nav aria-label="Secciones del micrositio" className="min-w-0">
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
                      {sinGuardar.includes(clave) && (
                        <span className="mt-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">Sin guardar</span>
                      )}
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
              {/* Hallazgos 22 y 26: qué escribir, opcional, y qué pasa si se deja vacía. */}
              <Seccion titulo="Presentación"
                descripcion="Contale a tus clientes quién sos, qué vendés y qué te hace diferente. Aparece debajo del nombre de tu comercio en el micrositio.">
                <AreaTexto etiqueta="Descripción" value={descripcion} maxLength={2000} rows={4}
                  placeholder="Ej.: Somos una soda familiar en Liberia con desayunos típicos y almuerzos caseros desde 1998."
                  ayuda={`Opcional, de 10 a 2000 caracteres (${descripcion.length}/2000). Si la dejás vacía, el micrositio no muestra descripción.`}
                  onChange={(e) => setDescripcion(e.target.value)} error={mensajeDe(estInfo.errores, 'descripcion')} />
                <PieGuardado estado={estInfo} onGuardar={guardarDescripcion} />
              </Seccion>
              {/* CU-0003: logotipo */}
              <Seccion separada titulo="Logotipo" descripcion="JPG, PNG o WEBP de hasta 2 MB, cuadrado o casi cuadrado y de al menos 200 × 200 px. Se muestra en un círculo.">
                <div className="mt-3 flex flex-wrap items-end gap-4">
                  <span className="w-20 shrink-0">
                    <Miniatura ruta={logotipo} archivo={archivoLogo} alt={`Logotipo de ${nombre}`}
                      className="h-20 w-20 rounded-xl object-contain" vacio="Sin logotipo" />
                  </span>
                  <div className="min-w-[220px] flex-1">
                    <Campo etiqueta={logotipo ? 'Cambiar logotipo' : 'Subir logotipo'} type="file" accept="image/jpeg,image/png,image/webp"
                      onChange={(e) => elegirLogotipo(e.target.files?.[0] || null)}
                      error={erroresImagen.logotipo || mensajeDe(estLogo.errores, 'logotipo')} />
                  </div>
                </div>
                <div className="flex flex-wrap items-start gap-3">
                  <PieGuardado estado={estLogo} texto={logotipo ? 'Guardar logotipo nuevo' : 'Subir logotipo'} deshabilitado={!archivoLogo}
                    onGuardar={() => guardarLogo(() => guardarLogotipo(idComercio, archivoLogo), 'Logotipo actualizado.', () => { setArchivoLogo(null); recargar('logotipo'); })} />
                  {/* Hallazgo 21: quitar el logotipo; el portal muestra el ícono de tienda. */}
                  {logotipo && !archivoLogo && !confirmarQuitarLogo && (
                    <Boton variante="peligroBorde" className="mt-4" onClick={() => setConfirmarQuitarLogo(true)}><Trash2 size={16} /> Quitar logotipo</Boton>
                  )}
                </div>
                {confirmarQuitarLogo && (
                  <div className="mt-3 max-w-md rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p className="text-sm font-semibold text-slate-800">¿Quitar el logotipo? En el portal se va a ver el ícono de tienda.</p>
                    <div className="mt-3 flex gap-2">
                      <Boton variante="peligro" disabled={estLogo.guardando}
                        onClick={() => guardarLogo(() => quitarLogotipo(idComercio), 'Logotipo quitado.', () => { setConfirmarQuitarLogo(false); setLogotipo(null); })}>
                        Sí, quitar
                      </Boton>
                      <Boton variante="neutro" onClick={() => setConfirmarQuitarLogo(false)}>Cancelar</Boton>
                    </div>
                  </div>
                )}
              </Seccion>
            </>
          )}

          {seccion === 'horario' && (
            /* CU-0006: horarios */
            <Seccion titulo="Horario de atención" descripcion="Marcá los días cerrados; los demás necesitan hora de apertura y de cierre.">
              <Boton variante="neutro" className="mt-3 py-2" onClick={copiarLunes} disabled={!horarios[0].hora_apertura || !horarios[0].hora_cierre}>
                <Copy size={16} /> Copiar el horario del lunes a los demás días abiertos
              </Boton>
              {/* Hallazgo 31: anchos fijos, así un error no mueve las columnas. */}
              <div className="mt-3 overflow-x-auto">
                <table className="w-full min-w-[480px] table-fixed text-sm">
                  <colgroup><col className="w-28" /><col className="w-20" /><col /><col /></colgroup>
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
                            <input type="time" className="rounded-lg border border-slate-300 p-1.5 disabled:bg-slate-100 disabled:text-slate-400 aria-[invalid=true]:border-red-500"
                              disabled={h.cerrado} value={h[campo] || ''}
                              aria-invalid={mensajeDe(estHorarios.errores, `horarios.${i}.${campo}`) ? 'true' : undefined}
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
              descripcion="Uno de cada tipo. En las redes basta con tu usuario: el enlace se completa solo.">
              {contactos.length === 0 && <p className="mt-4 text-sm text-slate-500">Todavía no agregaste medios de contacto.</p>}
              {contactos.map((c, i) => {
                const [, ejemplo, ayuda] = DATOS_CONTACTO[c.tipo] ?? [];
                const usados = contactos.map((x) => x.tipo);
                return (
                  <div key={i} className="mt-3 grid gap-2 sm:grid-cols-[170px_1fr_auto] sm:items-start">
                    <Seleccion etiqueta="Tipo" value={c.tipo} error={mensajeDe(estContactos.errores, `contactos.${i}.tipo`)}
                      onChange={(e) => setContactos(cambiarEn(contactos, i, 'tipo', e.target.value))}>
                      {/* Hallazgo 33: uno por tipo; el portal muestra uno solo de cada uno. */}
                      {/* Hallazgo 42: la opción usada queda deshabilitada y dice por qué. */}
                      {TIPOS_CONTACTO.map(([v, t]) => {
                        const usada = v !== c.tipo && usados.includes(v);
                        return <option key={v} value={v} disabled={usada}>{textoOpcion(t, usada)}</option>;
                      })}
                    </Seleccion>
                    <Campo etiqueta="Valor" value={c.valor} placeholder={ejemplo ? `Ej.: ${ejemplo}` : ''} ayuda={ayuda} maxLength={255}
                      error={mensajeDe(estContactos.errores, `contactos.${i}.valor`)}
                      onChange={(e) => setContactos(cambiarEn(contactos, i, 'valor', e.target.value))} />
                    <BotonQuitar etiqueta="Quitar contacto" className="sm:mt-[2.6rem]"
                      onClick={() => { setContactos(quitarDe(contactos, i)); limpiarContactos(); }} />
                  </div>
                );
              })}
              {(() => {
                const libre = TIPOS_CONTACTO.find(([v]) => !contactos.some((c) => c.tipo === v))?.[0];
                return (
                  <Boton variante="neutro" className="mt-4" disabled={!libre} onClick={() => setContactos([...contactos, { tipo: libre, valor: '' }])}>
                    <Plus size={16} /> Agregar contacto
                  </Boton>
                );
              })()}
              <PieGuardado estado={estContactos} onGuardar={guardarListaContactos} />
            </Seccion>
          )}

          {seccion === 'politicas' && (
            /* Políticas: las de envío llevan tarifas por zona, que consumen M5 y M6. */
            <Seccion titulo="Políticas"
              descripcion="Una de cada tipo. Las tarifas de la política de envío son las que se cobran en el checkout según la dirección del cliente: GAM o Resto del país.">
              {politicas.length === 0 && <p className="mt-4 text-sm text-slate-500">Todavía no hay políticas.</p>}
              {politicas.map((p, i) => {
                const usadas = politicas.map((x) => x.tipo);
                const zonasUsadas = (p.configuracion_envio || []).map((t) => t.zona);
                const zonaLibre = ZONAS_ENVIO.find((z) => !zonasUsadas.includes(z));
                return (
                  <div key={i} className="mt-4 rounded-xl border border-slate-200 p-4">
                    {/* Hallazgo 36: la papelera junto al tipo, no en el otro extremo. */}
                    <div className="flex items-start gap-2">
                      <div className="w-full max-w-[240px]">
                        <Seleccion etiqueta="Tipo" value={p.tipo} error={mensajeDe(estPoliticas.errores, `politicas.${i}.tipo`)}
                          onChange={(e) => setPoliticas(cambiarEn(politicas, i, 'tipo', e.target.value))}>
                          {TIPOS_POLITICA.map(([v, t]) => {
                            const usada = v !== p.tipo && usadas.includes(v);
                            return <option key={v} value={v} disabled={usada}>{textoOpcion(t, usada, true)}</option>;
                          })}
                        </Seleccion>
                      </div>
                      <BotonQuitar etiqueta="Quitar política" className="mt-[2.6rem] shrink-0"
                        onClick={() => { setPoliticas(quitarDe(politicas, i)); limpiarPoliticas(); }} />
                    </div>
                    <AreaTexto etiqueta="Contenido" value={p.contenido} maxLength={2000} error={mensajeDe(estPoliticas.errores, `politicas.${i}.contenido`)}
                      ayuda="De 10 a 2000 caracteres." onChange={(e) => setPoliticas(cambiarEn(politicas, i, 'contenido', e.target.value))} />
                    {p.tipo === 'envio' && (
                      <div className="mt-3 rounded-lg bg-slate-50 p-3">
                        <p className="text-sm font-semibold text-slate-700">Tarifas de envío</p>
                        {(p.configuracion_envio || []).map((t, k) => (
                          <div key={k} className="grid gap-2 sm:grid-cols-[1fr_150px_auto] sm:items-start">
                            {/* Hallazgo 34: las zonas que reconoce el checkout (M5). */}
                            <Seleccion etiqueta="Zona" value={t.zona || ''} onChange={(e) => cambiarTarifa(i, k, 'zona', e.target.value)}
                              error={mensajeDe(estPoliticas.errores, `politicas.${i}.configuracion_envio.${k}.zona`)}>
                              {!ZONAS_ENVIO.includes(t.zona) && <option value={t.zona || ''}>{t.zona ? `${t.zona} (elegí GAM o Resto del país)` : 'Elegí la zona'}</option>}
                              {ZONAS_ENVIO.map((z) => {
                                const usada = z !== t.zona && zonasUsadas.includes(z);
                                return <option key={z} value={z} disabled={usada}>{textoOpcion(z, usada, true)}</option>;
                              })}
                            </Seleccion>
                            <Campo etiqueta="Costo (₡)" type="number" min="0" max="100000" step="1" value={t.costo ?? ''}
                              onChange={(e) => cambiarTarifa(i, k, 'costo', e.target.value)}
                              error={mensajeDe(estPoliticas.errores, `politicas.${i}.configuracion_envio.${k}.costo`)} />
                            <BotonQuitar etiqueta="Quitar tarifa" className="sm:mt-[2.6rem]"
                              onClick={() => {
                                setPoliticas(cambiarEn(politicas, i, 'configuracion_envio', quitarDe(p.configuracion_envio, k)));
                                limpiarPoliticas();
                              }} />
                          </div>
                        ))}
                        <Boton variante="neutro" className="mt-2" disabled={!zonaLibre}
                          onClick={() => setPoliticas(cambiarEn(politicas, i, 'configuracion_envio', [...(p.configuracion_envio || []), { zona: zonaLibre, costo: '', moneda: 'CRC' }]))}>
                          <Plus size={16} /> Agregar tarifa
                        </Boton>
                      </div>
                    )}
                  </div>
                );
              })}
              {(() => {
                const libre = TIPOS_POLITICA.find(([v]) => !politicas.some((p) => p.tipo === v))?.[0];
                return (
                  <Boton variante="neutro" className="mt-4" disabled={!libre}
                    onClick={() => setPoliticas([...politicas, { tipo: libre, contenido: '', configuracion_envio: [] }])}>
                    <Plus size={16} /> Agregar política
                  </Boton>
                );
              })()}
              <PieGuardado estado={estPoliticas} onGuardar={guardarListaPoliticas} />
            </Seccion>
          )}

          {seccion === 'destacar' && (
            /* Destacar mi comercio: solicitud de publicidad que revisa la plataforma */
            <Seccion titulo="Destacar mi comercio"
              descripcion="Los comercios destacados aparecen en la portada del portal. Elegí por cuántos días; la plataforma revisa tu solicitud y, al aprobarla, el periodo empieza ese día (o, si ya estás destacado, cuando termina el actual).">
              {vigenteDestacado && (
                <p className="mt-4 rounded-xl bg-green-50 p-3 text-sm font-semibold text-green-800 ring-1 ring-green-200">
                  Tu comercio está destacado hasta el {fechaCorta(vigenteDestacado.fin)}.
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
                  {inicioExtension && (
                    <p className="mt-3 text-sm text-slate-600">
                      Ya estás destacado: el periodo nuevo empezaría el {inicioExtension.toLocaleDateString('es-CR')}, cuando termine el actual.
                    </p>
                  )}
                  <PieGuardado estado={estDestacar} texto={inicioExtension ? 'Extender destacado' : 'Solicitar destacado'}
                    onGuardar={() => guardarDestacar(() => solicitarPublicidad(idComercio, diasDestacado),
                      'Solicitud enviada: la plataforma la va a revisar.', (datos) => setPublicidad(datos))} />
                </>
              )}
              {publicidad?.publicidades?.length > 0 && (
                <div className="mt-6">
                  <p className="text-sm font-semibold text-slate-700">Historial</p>
                  <ul className="mt-2 divide-y divide-slate-100 text-sm">
                    {/* Hallazgo 37: fechas y, si se rechazó o se retiró, el motivo. */}
                    {publicidad.publicidades.map((p) => {
                      const estadoTexto = estadoPublicidad(p);
                      return (
                        <li key={p.id_publicidad_comercio} className="py-2">
                          <div className="flex flex-wrap justify-between gap-2">
                            <span className="text-slate-700">
                              {p.dias_solicitados ? `${p.dias_solicitados} días` : 'Publicidad'}
                              {p.estado === 'Aprobada' ? ` · del ${fechaCorta(p.inicio)} al ${fechaCorta(p.fin)}` : ''}
                              {p.solicitado_el ? ` · solicitado el ${fechaCorta(p.solicitado_el)}` : ''}
                            </span>
                            <span className={`font-semibold ${['Rechazada', 'Retirado'].includes(estadoTexto) ? 'text-red-700' : estadoTexto === 'Vigente' ? 'text-green-700' : 'text-slate-500'}`}>{estadoTexto}</span>
                          </div>
                          {p.motivo && <p className="mt-1 text-xs text-slate-600">Motivo: {p.motivo}</p>}
                        </li>
                      );
                    })}
                  </ul>
                </div>
              )}
            </Seccion>
          )}

          {listasDeImagenes.filter(({ clave }) => clave === seccion).map(({ clave, titulo, lista, setLista, campoId, estado, guardar, limpiar, enviar, conActivo, medidas, uso, vacio }) => {
            /* CU-0004 y CU-0005: banners y galería */
            const portada = conActivo ? lista.findIndex((x) => x.activo && (x.ruta || x.imagen)) : -1;
            return (
              <Seccion key={clave} titulo={titulo} descripcion={`${medidas} ${uso}`}>
                {lista.length === 0 && <p className="mt-4 text-sm text-slate-500">Todavía no hay imágenes.</p>}
                <div className="mt-2 grid gap-3 md:grid-cols-2">
                  {lista.map((x, i) => {
                    const fila = x[campoId] ?? x.clave;
                    return (
                    <div key={fila} className="rounded-xl border border-slate-200 p-3">
                      <div className="flex items-start justify-between gap-2">
                        <span className="relative w-full max-w-[200px]">
                          <Miniatura ruta={x.ruta} archivo={x.imagen} alt={x.texto_alternativo}
                            className="h-24 w-full max-w-[200px] rounded-lg object-cover" vacio={vacio} />
                          {i === portada && (
                            <span className="absolute bottom-1 left-1 rounded bg-[#1E3A8A] px-1.5 py-0.5 text-[11px] font-bold text-white">Portada</span>
                          )}
                        </span>
                        {/* Hallazgo 27: ordenar con flechas. */}
                        <span className="flex shrink-0 gap-1">
                          <Boton variante="neutro" className="px-2.5" aria-label="Subir una posición" disabled={i === 0}
                            onClick={() => { setLista(mover(lista, i, -1)); limpiar(); }}><ArrowUp size={16} /></Boton>
                          <Boton variante="neutro" className="px-2.5" aria-label="Bajar una posición" disabled={i === lista.length - 1}
                            onClick={() => { setLista(mover(lista, i, 1)); limpiar(); }}><ArrowDown size={16} /></Boton>
                          <BotonQuitar etiqueta="Quitar imagen"
                            onClick={() => { setLista(quitarDe(lista, i)); olvidarErrorImagen(clave, fila); limpiar(); }} />
                        </span>
                      </div>
                      {/* Hallazgo 30: para qué sirve el texto alternativo. */}
                      <Campo etiqueta="Descripción de la imagen" value={x.texto_alternativo} maxLength={150}
                        placeholder="Ej.: Fachada de la tienda en Cañas"
                        ayuda="Describí qué se ve en la foto. La usan los lectores de pantalla de personas con discapacidad visual y aparece si la imagen no carga."
                        onChange={(e) => setLista(cambiarEn(lista, i, 'texto_alternativo', e.target.value))}
                        error={mensajeDe(estado.errores, `${clave}.${i}.texto_alternativo`)} />
                      <Campo etiqueta={x.ruta ? 'Reemplazar imagen (opcional)' : 'Imagen'} type="file" accept="image/jpeg,image/png,image/webp"
                        onChange={(e) => elegirImagen(clave, setLista, lista, i, e.target.files?.[0] || null, fila)}
                        error={erroresImagen[`${clave}.${fila}`] || mensajeDe(estado.errores, `${clave}.${i}.imagen`)} />
                      {conActivo && (
                        <label className="mt-2 flex items-center gap-2 text-sm text-slate-700">
                          <input type="checkbox" checked={x.activo} onChange={(e) => setLista(cambiarEn(lista, i, 'activo', e.target.checked))} />
                          Visible en el micrositio
                        </label>
                      )}
                    </div>
                    );
                  })}
                </div>
                <Boton variante="neutro" className="mt-4"
                  onClick={() => setLista([...lista, conActivo
                    ? { clave: claveNueva(), texto_alternativo: '', activo: true, imagen: null }
                    : { clave: claveNueva(), texto_alternativo: '', imagen: null }])}>
                  <Plus size={16} /> Agregar imagen
                </Boton>
                <MensajeCampo mensaje={mensajeDe(estado.errores, clave)} />
                <PieGuardado estado={estado} onGuardar={() => {
                  // Hallazgo 48: las imágenes nuevas van en un solo envío; si juntas pasan del límite, se avisa aquí.
                  const total = lista.reduce((suma, x) => suma + (x.imagen?.size || 0), 0);
                  const accion = total > MAXIMO_ENVIO
                    ? async () => ({ ok: false, estado: 422, error: { detalles: { [clave]: [
                      `Las imágenes nuevas pesan ${pesoTexto(total)} juntas y se pueden enviar hasta 7 MB a la vez. Guardá algunas primero y después agregá las demás.`] } } })
                    : () => enviar(idComercio, paraEnviar(lista, campoId));
                  guardar(accion, `${titulo}: cambios guardados.`, () => recargar(clave));
                }} />
              </Seccion>
            );
          })}
        </Tarjeta>

        <div className="hidden xl:block">
          <VistaPrevia idComercio={idComercio} nombre={nombre} logotipo={logotipo} descripcion={descripcion} horarios={horarios} contactos={contactos} />
        </div>
      </div>
    </div>
  );
}
