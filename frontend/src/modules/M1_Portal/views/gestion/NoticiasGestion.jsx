import { useState } from 'react';
import ArticuloNoticia from '../../components/ArticuloNoticia';
import { Aviso, BotonPrincipal } from '../../components/componentes';
import { AvisoVista, CargandoBloque, ErrorVista, Vacio, mensajeDeDatosInvalidos } from '../../components/EstadosVista';
import Icono from '../../components/IconoComercio';
import TarjetaNoticia from '../../components/TarjetaNoticia';
import { fechaLarga, horaLocalAIso, isoAHoraLocal } from '../../services/formato';
import { listarPromociones } from '../../services/m1Api';
import {
  actualizarNoticia, archivarNoticia, crearNoticia, listarNoticiasAdministracion,
  listarPromocionesAdministracion, publicarNoticia,
} from '../../services/noticiasApi';
import useConsulta from '../../services/useConsulta';
import { LIMITES, MAX_PROMOCIONES, largo, validarNoticia } from '../../services/validacionNoticia';

// Noticias de la plataforma en el panel de gestión (contrato M1 1.4.0, SC-08). Solo encargado_plataforma:
// el panel decide quién ve la pestaña (views/gestion/secciones.js) y el backend vuelve a exigir el rol.
// Estados: borrador, publicada y archivada. Una archivada ya no se edita, no se publica ni se archiva.
// Recibe { usuario } como las demás secciones, pero no lo necesita ni usa el contexto del portal, así que
// se puede montar fuera de él.

const CAMPOS_TEXTO = [
  { id: 'titulo', etiqueta: 'Título' },
  { id: 'resumen', etiqueta: 'Resumen', multilinea: 3, ayuda: 'Texto corto que se muestra en la portada.' },
  { id: 'contenido', etiqueta: 'Contenido', multilinea: 8, ayuda: 'Texto sin formato. Separe los párrafos con una línea en blanco.' },
];
const CAMPOS_FORMULARIO = ['titulo', 'resumen', 'contenido', 'imagen_url', 'fecha_fin', 'ids_promocion'];

const FILTROS = [['', 'Todas'], ['borrador', 'Borradores'], ['publicada', 'Publicadas'], ['archivada', 'Archivadas']];
const ESTADOS = { borrador: 'Borrador', publicada: 'Publicada', archivada: 'Archivada' };
const INSIGNIAS = {
  borrador: 'border-blue-200 bg-blue-50 text-blue-800',
  publicada: 'border-green-200 bg-green-50 text-green-700',
  archivada: 'border-gray-300 bg-gray-100 text-gray-700',
};

// ERR-M1-006 (409): la noticia cambió de estado y ya no admite la acción. Texto propio por acción.
const CONFLICTOS = {
  guardar: 'Esta noticia está archivada y ya no se puede modificar.',
  publicar: 'Esta noticia ya está publicada o archivada, así que no se puede publicar de nuevo.',
  archivar: 'Esta noticia ya estaba archivada.',
};

const claseCampo = (error) => `w-full min-h-[44px] rounded-lg border bg-white px-3 py-2 text-base font-medium text-gray-800 focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30 ${error ? 'border-red-600' : 'border-gray-200 hover:border-gray-300'}`;
const BOTON_SECUNDARIO = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg border border-brandBlue px-4 py-2 text-sm font-semibold text-brandBlue transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brandBlue focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const BOTON_PELIGRO = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg border border-red-600 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const BOTON_PRIMARIO = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2 text-sm font-bold text-white transition enabled:hover:bg-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500';

const esDatosInvalidos = (respuesta) => respuesta.estado === 422 || respuesta.error?.codigo === 'ERR-005';

// Texto de un error que no es de un campo. Solo el mensaje, nunca el código (PC3, sección 5.5).
function textoError(respuesta, accion) {
  if (respuesta.estado === 409) return CONFLICTOS[accion];
  if (respuesta.estado === 401) return 'Su sesión expiró. Vuelva a ingresar.';
  if (respuesta.estado === 403) return 'Su cuenta no tiene permiso para esta acción.';
  if (respuesta.estado === 404) return 'La noticia ya no existe.';
  if (esDatosInvalidos(respuesta)) return mensajeDeDatosInvalidos(respuesta.error);
  return respuesta.error?.mensaje || 'No se pudo completar la acción.';
}

// ERR-005 trae `detalles` como mapa de campo a mensaje. Los campos del formulario se muestran debajo de
// cada uno ("ids_promocion.0" cuenta como ids_promocion); lo demás pasa al aviso general.
function erroresDelServidor(error) {
  const errores = {};
  const generales = [];
  for (const [clave, mensaje] of Object.entries(error?.detalles || {})) {
    const texto = Array.isArray(mensaje) ? mensaje[0] : mensaje;
    const campo = clave.split('.')[0];
    if (CAMPOS_FORMULARIO.includes(campo)) errores[campo] = errores[campo] || texto;
    else generales.push(texto);
  }
  if (!Object.keys(errores).length && !generales.length) generales.push(error?.mensaje || 'Revise los datos ingresados.');
  if (generales.length) errores.general = generales.join(' ');
  return errores;
}

// El PUT reemplaza todo: siempre van fecha_fin (si hay) e ids_promocion. imagen_url vacía va como null.
function datosDelFormulario(f) {
  return {
    titulo: f.titulo.trim(),
    resumen: f.resumen.trim(),
    contenido: f.contenido.trim(),
    imagen_url: f.imagen_url.trim() || null,
    ...(f.fecha_fin ? { fecha_fin: horaLocalAIso(f.fecha_fin) } : {}),
    ids_promocion: f.ids_promocion,
  };
}

const formularioDe = (noticia) => ({
  titulo: noticia?.titulo ?? '',
  resumen: noticia?.resumen ?? '',
  contenido: noticia?.contenido ?? '',
  imagen_url: noticia?.imagen_url ?? '',
  fecha_fin: noticia?.fecha_fin ? isoAHoraLocal(noticia.fecha_fin) : '',
  ids_promocion: noticia?.ids_promocion ?? [],
});

// `limite` muestra el contador de caracteres. No se usa el atributo maxLength porque cuenta unidades UTF-16
// y los espacios de los extremos, y cortaría textos que el backend sí acepta (cuenta caracteres, sin extremos).
function Campo({ id, etiqueta, error, ayuda, multilinea, limite, ...props }) {
  const idCampo = `campo-${id}`;
  const descripcion = [error && `${idCampo}-error`, ayuda && `${idCampo}-ayuda`].filter(Boolean).join(' ') || undefined;
  const accesibilidad = { id: idCampo, 'aria-invalid': !!error, 'aria-describedby': descripcion };
  const usados = limite ? largo(String(props.value ?? '').trim()) : 0;
  return (
    <div>
      <div className="mb-1 flex items-baseline justify-between gap-2">
        <label htmlFor={idCampo} className="block text-sm font-medium text-gray-800">{etiqueta}</label>
        {limite && (
          <span className={`shrink-0 text-xs ${usados > limite ? 'font-semibold text-red-600' : 'text-gray-500'}`}>
            {usados.toLocaleString('es-CR')} de {limite.toLocaleString('es-CR')}
          </span>
        )}
      </div>
      {multilinea
        ? <textarea rows={multilinea} {...accesibilidad} {...props} className={claseCampo(error)} />
        : <input {...accesibilidad} {...props} className={claseCampo(error)} />}
      {ayuda && <p id={`${idCampo}-ayuda`} className="mt-1 text-xs text-gray-600">{ayuda}</p>}
      {error && <p id={`${idCampo}-error`} className="mt-1 break-words text-xs text-red-600">{error}</p>}
    </div>
  );
}

// Casillas con las promociones vigentes por título (máximo 10). Las ya enlazadas que no vengan en la lista
// (porque dejaron de estar vigentes) se conservan con su id para que no se pierdan al guardar. Si la lista
// no cargó, se avisa y el resto del formulario sigue funcionando (las ya enlazadas no se tocan).
function SelectorPromociones({ promociones, seleccion, error, onCambio }) {
  const { estado, datos } = promociones;
  const conocidas = estado === 'listo' && Array.isArray(datos) ? datos : [];
  const faltantes = estado === 'listo' ? seleccion.filter((id) => !conocidas.some((p) => p.id_promocion === id)) : [];
  const llenas = seleccion.length >= MAX_PROMOCIONES;

  const casilla = (id, texto, detalle) => {
    const marcada = seleccion.includes(id);
    return (
      <label key={id} className={`flex min-h-[44px] cursor-pointer items-start gap-3 rounded-lg px-2 py-2 text-sm hover:bg-gray-50 ${!marcada && llenas ? 'opacity-60' : ''}`}>
        <input type="checkbox" checked={marcada} disabled={!marcada && llenas} onChange={() => onCambio(id)}
          className="mt-0.5 h-5 w-5 shrink-0 accent-brandDarkBlue" />
        <span className="min-w-0 break-words text-gray-800">
          {texto}
          {detalle && <span className="block text-xs text-gray-600">{detalle}</span>}
        </span>
      </label>
    );
  };

  return (
    <fieldset aria-describedby={error ? 'campo-ids_promocion-error' : undefined}>
      <legend className="mb-1 text-sm font-medium text-gray-800">Promociones enlazadas (opcional)</legend>
      {estado === 'cargando' && <p role="status" className="text-sm text-gray-600">Cargando promociones…</p>}
      {estado === 'error' && <AvisoVista>No se pudieron cargar las promociones. Puede guardar la noticia sin cambiar las promociones enlazadas.</AvisoVista>}
      {estado === 'listo' && !conocidas.length && !faltantes.length && <p className="text-sm text-gray-600">No hay promociones vigentes para enlazar.</p>}
      {estado === 'listo' && (conocidas.length > 0 || faltantes.length > 0) && (
        <div className="max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1">
          {conocidas.map((p) => casilla(p.id_promocion, p.titulo, `Vigente hasta el ${fechaLarga(p.fecha_fin)}`))}
          {faltantes.map((id) => casilla(id, `Promoción ${id}`, 'Ya no está vigente; el portal no la muestra'))}
        </div>
      )}
      {estado === 'listo' && <p className="mt-1 text-xs text-gray-600">{seleccion.length} de {MAX_PROMOCIONES} elegidas.</p>}
      {error && <p id="campo-ids_promocion-error" className="mt-1 break-words text-xs text-red-600">{error}</p>}
    </fieldset>
  );
}

// Ícono de ojo con los trazos de Lucide (design.md: SVG en línea, sin emojis).
function IconoOjo() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 shrink-0">
      <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

// Cómo se verá la noticia en el portal con lo que hay escrito en el formulario, sin guardar nada: la
// tarjeta de la portada (TarjetaNoticia) y la página de la noticia (ArticuloNoticia), los mismos
// componentes del portal. Las promociones enlazadas se muestran con su producto si el portal las tiene
// vigentes ahora (GET /promociones); las demás se cuentan en un aviso.
function VistaPreviaNoticia({ formulario, fechaPublicacion }) {
  const vigentes = useConsulta(listarPromociones, []);
  // Sin publicar todavía, se muestra con la fecha de hoy.
  const [ahora] = useState(() => new Date().toISOString());

  const promociones = (Array.isArray(vigentes.datos) ? vigentes.datos : [])
    .filter((p) => formulario.ids_promocion.includes(p.id_promocion) && p.producto);
  const sinMostrar = formulario.ids_promocion.length - promociones.length;

  const noticia = {
    id_noticia: 0,
    slug: '',
    titulo: formulario.titulo.trim() || 'Título de la noticia',
    resumen: formulario.resumen.trim() || 'Resumen de la noticia.',
    contenido: formulario.contenido.trim() || 'Contenido de la noticia.',
    imagen_url: formulario.imagen_url.trim() || null,
    fecha_publicacion: fechaPublicacion || ahora,
    promociones,
  };

  return (
    <section aria-label="Vista previa de la noticia" className="space-y-5 rounded-xl border border-gray-200 bg-brandLightBg p-3 sm:p-5">
      <div>
        <p className="mb-2 text-sm font-semibold text-gray-700">En la portada</p>
        <div className="max-w-sm">
          <TarjetaNoticia noticia={noticia} />
        </div>
      </div>
      <div>
        <p className="mb-2 text-sm font-semibold text-gray-700">Página de la noticia</p>
        {/* El formulario es un h3: el título de la noticia va un nivel debajo, con el mismo tamaño que en el portal. */}
        <ArticuloNoticia noticia={noticia} nivelTitulo={4} />
      </div>
      {vigentes.estado === 'cargando' && formulario.ids_promocion.length > 0 && (
        <p role="status" className="text-sm text-gray-600">Cargando las promociones enlazadas…</p>
      )}
      {vigentes.estado === 'error' && formulario.ids_promocion.length > 0 && (
        <AvisoVista>No se pudieron cargar las promociones para la vista previa.</AvisoVista>
      )}
      {vigentes.estado === 'listo' && sinMostrar > 0 && (
        <AvisoVista>
          {sinMostrar === 1
            ? 'Una de las promociones enlazadas no se muestra porque el portal no la tiene vigente en este momento.'
            : `${sinMostrar} de las promociones enlazadas no se muestran porque el portal no las tiene vigentes en este momento.`}
        </AvisoVista>
      )}
    </section>
  );
}

function FormularioNoticia({ noticia, promociones, onGuardado, onConflicto, onCancelar }) {
  const editando = !!noticia;
  const [f, setF] = useState(() => formularioDe(noticia));
  const [verPrevia, setVerPrevia] = useState(false);
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [conflicto, setConflicto] = useState(null);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  const alternarPromocion = (id) => setF({
    ...f,
    ids_promocion: f.ids_promocion.includes(id) ? f.ids_promocion.filter((x) => x !== id) : [...f.ids_promocion, id],
  });

  const enfocarPrimerError = (errs) => {
    const primero = CAMPOS_FORMULARIO.find((c) => errs[c]);
    if (primero) document.getElementById(`campo-${primero}`)?.focus();
  };

  async function enviar(e) {
    e.preventDefault();
    setConflicto(null);
    const errs = validarNoticia(f);
    setErrores(errs);
    if (Object.keys(errs).length) { enfocarPrimerError(errs); return; }

    setEnviando(true);
    const datos = datosDelFormulario(f);
    const respuesta = editando ? await actualizarNoticia(noticia.id_noticia, datos) : await crearNoticia(datos);
    setEnviando(false);

    if (respuesta.ok) { onGuardado(respuesta.datos, editando); return; }
    if (respuesta.estado === 409) { setConflicto(textoError(respuesta, 'guardar')); onConflicto(); return; }
    if (esDatosInvalidos(respuesta)) {
      const errs422 = erroresDelServidor(respuesta.error);
      setErrores(errs422);
      enfocarPrimerError(errs422);
      return;
    }
    setErrores({ general: textoError(respuesta, 'guardar') });
  }

  return (
    <form onSubmit={enviar} noValidate aria-label={editando ? 'Editar noticia' : 'Nueva noticia'}
      className="space-y-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
      <h3 className="text-lg font-bold text-brandDarkBlue">{editando ? 'Editar noticia' : 'Nueva noticia'}</h3>
      {!editando && <p className="text-sm text-gray-600">Se guarda como borrador. El portal la muestra cuando la publique.</p>}
      {conflicto && <AvisoVista>{conflicto}</AvisoVista>}
      {errores.general && <Aviso>{errores.general}</Aviso>}

      {CAMPOS_TEXTO.map(({ id, etiqueta, multilinea, ayuda }) => (
        <Campo key={id} id={id} etiqueta={etiqueta} multilinea={multilinea} ayuda={ayuda} limite={LIMITES[id]}
          value={f[id]} onChange={set(id)} error={errores[id]} />
      ))}
      <Campo id="imagen_url" etiqueta="Imagen (URL, opcional)" type="url" inputMode="url" value={f.imagen_url}
        onChange={set('imagen_url')} error={errores.imagen_url} ayuda="Dirección de la imagen. Debe empezar con http:// o https://." />
      <Campo id="fecha_fin" etiqueta="Fecha de fin (opcional)" type="datetime-local" value={f.fecha_fin}
        onChange={set('fecha_fin')} error={errores.fecha_fin}
        ayuda={editando && noticia.estado === 'publicada'
          ? 'Hora de Costa Rica. Debe ser posterior a la fecha de publicación.'
          : 'Hora de Costa Rica. Si se deja vacía, la noticia se muestra durante un año.'} />
      <SelectorPromociones promociones={promociones} seleccion={f.ids_promocion} error={errores.ids_promocion} onCambio={alternarPromocion} />

      {verPrevia && <VistaPreviaNoticia formulario={f} fechaPublicacion={noticia?.fecha_publicacion} />}

      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" onClick={() => setVerPrevia(!verPrevia)} aria-expanded={verPrevia} className={`${BOTON_SECUNDARIO} sm:mr-auto`}>
          <IconoOjo /> {verPrevia ? 'Ocultar vista previa' : 'Vista previa'}
        </button>
        <button type="button" onClick={onCancelar} className={BOTON_SECUNDARIO}>Cancelar</button>
        <div className="sm:w-48">
          <BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Guardar borrador'}</BotonPrincipal>
        </div>
      </div>
    </form>
  );
}

function TarjetaGestion({ noticia, ocupada, confirmando, onEditar, onPublicar, onPedirArchivar, onConfirmarArchivar, onCancelarArchivar }) {
  const { estado } = noticia;
  return (
    <li className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <h3 className="min-w-0 break-words text-base font-bold text-gray-900">{noticia.titulo}</h3>
        <span className={`inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${INSIGNIAS[estado] || INSIGNIAS.borrador}`}>
          {ESTADOS[estado] || estado}
        </span>
      </div>
      <dl className="mt-2 grid gap-x-6 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
        <div className="flex flex-wrap items-center gap-1.5">
          <Icono nombre="calendario" className="h-4 w-4 shrink-0" />
          <dt className="font-medium">Publicación:</dt>
          <dd>{noticia.fecha_publicacion ? fechaLarga(noticia.fecha_publicacion) : 'Sin publicar'}</dd>
        </div>
        <div className="flex flex-wrap items-center gap-1.5">
          <Icono nombre="calendario" className="h-4 w-4 shrink-0" />
          <dt className="font-medium">Fin:</dt>
          <dd>{noticia.fecha_fin ? fechaLarga(noticia.fecha_fin) : 'Sin fecha de fin'}</dd>
        </div>
        {noticia.ids_promocion?.length > 0 && (
          <div className="flex flex-wrap items-center gap-1.5">
            <Icono nombre="etiqueta" className="h-4 w-4 shrink-0" />
            <dt className="font-medium">Promociones enlazadas:</dt>
            <dd>{noticia.ids_promocion.length}</dd>
          </div>
        )}
      </dl>

      {estado !== 'archivada' && !confirmando && (
        <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
          <button type="button" disabled={ocupada} onClick={onEditar} className={BOTON_SECUNDARIO}>Editar</button>
          {estado === 'borrador' && (
            <button type="button" disabled={ocupada} onClick={onPublicar} className={BOTON_PRIMARIO}>
              <Icono nombre="check" className="h-4 w-4" /> {ocupada ? 'Publicando…' : 'Publicar'}
            </button>
          )}
          <button type="button" disabled={ocupada} onClick={onPedirArchivar} className={BOTON_PELIGRO}>Archivar</button>
        </div>
      )}

      {confirmando && (
        <div role="alertdialog" aria-label="Confirmar archivado" className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3">
          <p className="break-words text-sm text-red-800">¿Archivar «{noticia.titulo}»? Una noticia archivada deja de mostrarse en el portal y ya no se puede editar ni publicar.</p>
          <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button type="button" disabled={ocupada} onClick={onConfirmarArchivar} className={BOTON_PELIGRO}>
              {ocupada ? 'Archivando…' : 'Sí, archivar'}
            </button>
            <button type="button" disabled={ocupada} onClick={onCancelarArchivar} className={BOTON_SECUNDARIO}>Cancelar</button>
          </div>
        </div>
      )}
    </li>
  );
}

export default function NoticiasGestion() {
  const [filtro, setFiltro] = useState('');
  const [formulario, setFormulario] = useState(null); // null | { noticia: Noticia | null }
  const [aviso, setAviso] = useState(null); // { tipo: 'exito' | 'conflicto' | 'error', texto }
  const [ocupada, setOcupada] = useState(null); // id_noticia con una acción en curso
  const [confirmando, setConfirmando] = useState(null); // id_noticia que espera confirmar el archivado

  const lista = useConsulta(() => listarNoticiasAdministracion(filtro), [filtro]);
  // Solo se enlazan promociones vigentes.
  const promociones = useConsulta(() => listarPromocionesAdministracion('vigente'), []);

  const noticias = Array.isArray(lista.datos) ? lista.datos : [];
  const conLista = lista.estado === 'listo' || lista.estado === 'actualizando';

  // Resultado de publicar o archivar: éxito, 409 (aviso amarillo) u otro error. Siempre se recarga la
  // lista, porque tras un 409 la noticia cambió de estado desde que se cargó.
  function resolverAccion(respuesta, accion, textoExito) {
    setOcupada(null);
    setConfirmando(null);
    if (respuesta.ok) setAviso({ tipo: 'exito', texto: textoExito });
    else setAviso({ tipo: respuesta.estado === 409 ? 'conflicto' : 'error', texto: textoError(respuesta, accion) });
    lista.reintentar();
  }

  async function publicar(noticia) {
    setAviso(null);
    setOcupada(noticia.id_noticia);
    resolverAccion(await publicarNoticia(noticia.id_noticia), 'publicar', `La noticia «${noticia.titulo}» se publicó.`);
  }

  async function archivar(noticia) {
    setAviso(null);
    setOcupada(noticia.id_noticia);
    resolverAccion(await archivarNoticia(noticia.id_noticia), 'archivar', `La noticia «${noticia.titulo}» se archivó.`);
  }

  function guardado(noticia, editando) {
    setFormulario(null);
    setAviso({ tipo: 'exito', texto: editando ? `La noticia «${noticia.titulo}» se actualizó.` : `La noticia «${noticia.titulo}» se creó como borrador.` });
    lista.reintentar();
  }

  function abrirFormulario(noticia) {
    setAviso(null);
    setConfirmando(null);
    setFormulario({ noticia });
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <h2 className="text-lg font-bold text-brandDarkBlue">Noticias de la plataforma</h2>
          <p className="mt-1 text-sm text-gray-600">Se muestran en la portada del portal mientras estén publicadas y dentro de su periodo.</p>
        </div>
        {!formulario && (
          <button type="button" onClick={() => abrirFormulario(null)} className={`${BOTON_PRIMARIO} w-full sm:w-auto`}>
            <Icono nombre="noticia" className="h-4 w-4" /> Nueva noticia
          </button>
        )}
      </div>

      {aviso?.tipo === 'exito' && <Aviso tipo="exito">{aviso.texto}</Aviso>}
      {aviso?.tipo === 'conflicto' && <AvisoVista>{aviso.texto}</AvisoVista>}
      {aviso?.tipo === 'error' && <Aviso>{aviso.texto}</Aviso>}

      {formulario && (
        <FormularioNoticia key={formulario.noticia?.id_noticia ?? 'nueva'} noticia={formulario.noticia}
          promociones={promociones} onGuardado={guardado} onConflicto={lista.reintentar} onCancelar={() => setFormulario(null)} />
      )}

      <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
        {FILTROS.map(([valor, texto]) => (
          <button key={texto} type="button" aria-pressed={filtro === valor} onClick={() => setFiltro(valor)}
            className={`min-h-[44px] rounded-full border px-4 text-sm font-medium transition ${filtro === valor ? 'border-brandDarkBlue bg-brandDarkBlue text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-brandBlue'}`}>
            {texto}
          </button>
        ))}
      </div>

      {lista.estado === 'cargando' && <CargandoBloque etiqueta="Cargando noticias" />}
      {lista.estado === 'error' && <ErrorVista resultado={lista.resultado} onReintentar={lista.reintentar} />}
      {conLista && !noticias.length && (
        <Vacio icono="noticia" titulo={filtro ? 'No hay noticias en este estado' : 'Todavía no hay noticias'}>
          {filtro ? 'Pruebe con otro estado.' : 'Cree la primera con «Nueva noticia».'}
        </Vacio>
      )}
      {conLista && noticias.length > 0 && (
        <ul className="space-y-3" aria-busy={lista.estado === 'actualizando'}>
          {noticias.map((noticia) => (
            <TarjetaGestion key={noticia.id_noticia} noticia={noticia}
              ocupada={ocupada === noticia.id_noticia}
              confirmando={confirmando === noticia.id_noticia}
              onEditar={() => abrirFormulario(noticia)}
              onPublicar={() => publicar(noticia)}
              onPedirArchivar={() => { setAviso(null); setConfirmando(noticia.id_noticia); }}
              onConfirmarArchivar={() => archivar(noticia)}
              onCancelarArchivar={() => setConfirmando(null)} />
          ))}
        </ul>
      )}
    </div>
  );
}
