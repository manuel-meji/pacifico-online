import { useEffect, useRef, useState } from 'react';
import Icono from '../IconoComercio';
import { Aviso } from '../componentes';
import { buscarProductos, obtenerFichaProducto } from '../../services/m1Api';
import { actualizarPromocion, crearPromocion } from '../../services/promocionesApi';
import { colones, textoAtributos } from '../../services/formato';
import { aCampoCostaRica, aIsoCostaRica, ahoraCampoCostaRica } from '../../services/horaCostaRica';
import { Boton, Campo, Dialogo, erroresPorCampo, mensajeErrorPromocion, mensajeGeneral } from './promocionesUi';

const LARGO_MINIMO_TERMINO = 2;
const CAMPOS = ['id_producto', 'titulo', 'descripcion', 'tipo', 'valor', 'alcance', 'variaciones', 'fecha_inicio', 'fecha_fin'];

// Precio de una variación con la promoción (2 decimales), igual que el backend.
const precioConPromocion = (precio, tipo, valor) =>
  tipo === 'porcentaje' ? Math.round(precio * (100 - valor)) / 100 : valor;

// Validación en pantalla con las mismas reglas del contrato (el backend vuelve a validar).
function validar(datos, ficha) {
  const errores = {};
  const valor = Number(datos.valor);
  const afectadas = (ficha?.variaciones ?? []).filter((v) => datos.alcance === 'producto' || datos.variaciones.includes(v.id_variacion));

  if (!datos.id_producto) errores.id_producto = 'Elija un producto de su comercio.';
  if (!datos.titulo.trim()) errores.titulo = 'Es obligatorio.';
  if (datos.alcance === 'variaciones' && datos.variaciones.length === 0) errores.variaciones = 'Marque al menos una variación.';

  if (datos.valor === '' || !Number.isFinite(valor) || valor <= 0) errores.valor = 'Debe ser un número mayor que 0.';
  else if (!/^\d+(\.\d{1,2})?$/.test(String(datos.valor))) errores.valor = 'Como máximo 2 decimales.';
  else if (datos.tipo === 'porcentaje' && valor >= 100) errores.valor = 'Un porcentaje debe ser menor que 100.';
  else if (datos.tipo === 'precio_fijo' && afectadas.length && valor >= Math.min(...afectadas.map((v) => v.precio))) {
    errores.valor = `Debe ser menor que el precio normal de cada variación afectada (${colones(Math.min(...afectadas.map((v) => v.precio)))}).`;
  }

  if (!aIsoCostaRica(datos.fecha_inicio)) errores.fecha_inicio = 'Elija la fecha y la hora de inicio.';
  if (!aIsoCostaRica(datos.fecha_fin)) errores.fecha_fin = 'Elija la fecha y la hora de fin.';
  else if (datos.fecha_inicio && datos.fecha_fin <= datos.fecha_inicio) errores.fecha_fin = 'Debe ser posterior al inicio.';
  else if (datos.fecha_fin <= ahoraCampoCostaRica()) errores.fecha_fin = 'Debe ser posterior al momento actual.';

  return errores;
}

// Crear una promoción o editar una programada (contrato M1 1.4.0, esquema DatosPromocion).
// El producto se elige con la búsqueda de M1 con id_comercio fijo y sus variaciones salen de la ficha.
// Una programada no cambia de producto.
export default function FormularioPromocion({ idComercio, promocion = null, onGuardada, onCerrar }) {
  const editando = !!promocion;
  const [datos, setDatos] = useState(() => ({
    id_producto: promocion?.id_producto ?? null,
    titulo: promocion?.titulo ?? '',
    descripcion: promocion?.descripcion ?? '',
    tipo: promocion?.tipo ?? 'porcentaje',
    valor: promocion ? String(promocion.valor) : '',
    alcance: promocion?.alcance ?? 'producto',
    variaciones: promocion?.variaciones ?? [],
    fecha_inicio: promocion ? aCampoCostaRica(promocion.fecha_inicio) : '',
    fecha_fin: promocion ? aCampoCostaRica(promocion.fecha_fin) : '',
  }));
  const [ficha, setFicha] = useState(null);
  const [errores, setErrores] = useState({});
  const [errorGeneral, setErrorGeneral] = useState('');
  const [guardando, setGuardando] = useState(false);
  const formulario = useRef(null);
  const [intentos, setIntentos] = useState(0);

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

  // Ficha del producto elegido: nombre y variaciones con su precio.
  useEffect(() => {
    if (!datos.id_producto) { setFicha(null); return undefined; }
    let vigente = true;
    obtenerFichaProducto(datos.id_producto).then((r) => {
      if (!vigente) return;
      if (r.ok) setFicha(r.datos);
      else { setFicha(null); setErrorGeneral(mensajeErrorPromocion(r)); }
    });
    return () => { vigente = false; };
  }, [datos.id_producto]);

  const guardar = async (evento) => {
    evento.preventDefault();
    setErrorGeneral('');
    const locales = validar(datos, ficha);
    if (Object.keys(locales).length) { setErrores(locales); setIntentos((n) => n + 1); return; }

    const cuerpo = {
      id_producto: datos.id_producto,
      titulo: datos.titulo.trim(),
      descripcion: datos.descripcion.trim() || null,
      tipo: datos.tipo,
      valor: Number(datos.valor),
      alcance: datos.alcance,
      ...(datos.alcance === 'variaciones' ? { variaciones: datos.variaciones } : {}),
      fecha_inicio: aIsoCostaRica(datos.fecha_inicio),
      fecha_fin: aIsoCostaRica(datos.fecha_fin),
    };

    setGuardando(true);
    const respuesta = editando ? await actualizarPromocion(promocion.id_promocion, cuerpo) : await crearPromocion(cuerpo);
    setGuardando(false);

    if (respuesta.ok) { onGuardada(respuesta.datos); return; }
    setErrores(erroresPorCampo(respuesta));
    setErrorGeneral(mensajeGeneral(respuesta, CAMPOS));
    setIntentos((n) => n + 1);
  };

  const valorNumero = Number(String(datos.valor).replace(',', '.'));
  const afectadas = (ficha?.variaciones ?? []).filter((v) => datos.alcance === 'producto' || datos.variaciones.includes(v.id_variacion));

  return (
    <Dialogo titulo={editando ? 'Editar promoción programada' : 'Nueva promoción'} onCerrar={onCerrar}
      pie={(
        <>
          <Boton onClick={onCerrar}>Cancelar</Boton>
          <Boton variante="principal" icono="check" type="submit" form="formulario-promocion" disabled={guardando}>
            {guardando ? 'Guardando...' : editando ? 'Guardar cambios' : 'Crear promoción'}
          </Boton>
        </>
      )}>
      <form ref={formulario} id="formulario-promocion" onSubmit={guardar} noValidate className="space-y-4">
        {errorGeneral && <Aviso>{errorGeneral}</Aviso>}

        {editando ? (
          <div>
            <p className="mb-1 text-sm font-medium text-gray-800">Producto</p>
            <p className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-base text-gray-800">{ficha?.nombre ?? `Producto ${datos.id_producto}`}</p>
            <p className="mt-1 text-xs text-gray-500">Una promoción programada no cambia de producto.</p>
          </div>
        ) : (
          <SelectorProducto idComercio={idComercio} seleccionado={datos.id_producto ? { id: datos.id_producto, nombre: ficha?.nombre } : null}
            error={errores.id_producto}
            onElegir={(id) => { cambiar('id_producto', id); cambiar('variaciones', []); setErrorGeneral(''); }} />
        )}

        <Campo id="promo-titulo" etiqueta="Título" maxLength={120} value={datos.titulo} error={errores.titulo}
          onChange={(e) => cambiar('titulo', e.target.value)} placeholder="Por ejemplo: Semana de la camisa" />
        <Campo id="promo-descripcion" como="textarea" etiqueta="Descripción (opcional)" maxLength={500} value={datos.descripcion} error={errores.descripcion}
          onChange={(e) => cambiar('descripcion', e.target.value)} />

        <div className="grid gap-4 sm:grid-cols-2">
          <Campo id="promo-tipo" como="select" etiqueta="Tipo" value={datos.tipo} error={errores.tipo} onChange={(e) => cambiar('tipo', e.target.value)}>
            <option value="porcentaje">Porcentaje de descuento</option>
            <option value="precio_fijo">Precio fijo</option>
          </Campo>
          <Campo id="promo-valor" etiqueta={datos.tipo === 'porcentaje' ? 'Porcentaje (mayor que 0 y menor que 100)' : 'Precio fijo en colones'}
            inputMode="decimal" value={datos.valor} error={errores.valor}
            onChange={(e) => cambiar('valor', e.target.value.replace(',', '.').replace(/[^\d.]/g, ''))}
            placeholder={datos.tipo === 'porcentaje' ? '20' : '12500'} />
        </div>

        <fieldset>
          <legend className="mb-1 text-sm font-medium text-gray-800">Alcance</legend>
          <div className="flex flex-col gap-2 sm:flex-row">
            {[['producto', 'Todo el producto'], ['variaciones', 'Variaciones específicas']].map(([valor, texto]) => (
              <label key={valor} className={`flex min-h-[44px] flex-1 cursor-pointer items-center gap-2 rounded-lg border px-3 text-base ${datos.alcance === valor ? 'border-brandDarkBlue bg-blue-50 font-semibold text-brandDarkBlue' : 'border-gray-200'}`}>
                <input type="radio" name="promo-alcance" value={valor} checked={datos.alcance === valor} onChange={() => cambiar('alcance', valor)} className="h-4 w-4 accent-brandDarkBlue" />
                {texto}
              </label>
            ))}
          </div>
          {errores.alcance && <p role="alert" className="mt-1 text-xs text-red-600">{errores.alcance}</p>}
        </fieldset>

        {ficha && (ficha.variaciones ?? []).length > 0 && (
          <fieldset aria-describedby={errores.variaciones ? 'promo-variaciones-error' : undefined}>
            <legend className="mb-1 text-sm font-medium text-gray-800">
              {datos.alcance === 'variaciones' ? 'Variaciones con la promoción' : 'Precio con la promoción'}
            </legend>
            <ul className="space-y-2">
              {ficha.variaciones.map((v) => {
                const marcada = datos.alcance === 'producto' || datos.variaciones.includes(v.id_variacion);
                const promo = Number.isFinite(valorNumero) && valorNumero > 0 && marcada ? precioConPromocion(v.precio, datos.tipo, valorNumero) : null;
                return (
                  <li key={v.id_variacion}>
                    <label className={`flex min-h-[44px] items-center gap-3 rounded-lg border px-3 py-2 ${marcada ? 'border-brandBlue/50 bg-blue-50/40' : 'border-gray-200'} ${datos.alcance === 'variaciones' ? 'cursor-pointer' : ''}`}>
                      {datos.alcance === 'variaciones' && (
                        <input type="checkbox" checked={marcada} className="h-5 w-5 accent-brandDarkBlue"
                          onChange={(e) => cambiar('variaciones', e.target.checked ? [...datos.variaciones, v.id_variacion] : datos.variaciones.filter((id) => id !== v.id_variacion))} />
                      )}
                      <span className="min-w-0 flex-1 break-words text-sm text-gray-800">{textoAtributos(v.atributos) || v.sku}</span>
                      <span className="shrink-0 text-right text-sm">
                        <span className={promo != null ? 'block text-xs text-gray-500 line-through' : 'block text-gray-800'}>{colones(v.precio)}</span>
                        {promo != null && <span className={`block font-bold ${promo < v.precio ? 'text-red-700' : 'text-gray-500'}`}>{colones(promo)}</span>}
                      </span>
                    </label>
                  </li>
                );
              })}
            </ul>
            {errores.variaciones && <p id="promo-variaciones-error" role="alert" className="mt-1 text-xs text-red-600">{errores.variaciones}</p>}
            {datos.tipo === 'precio_fijo' && afectadas.length > 0 && (
              <p className="mt-1 text-xs text-gray-500">El precio fijo debe ser menor que {colones(Math.min(...afectadas.map((v) => v.precio)))}.</p>
            )}
          </fieldset>
        )}

        <div className="grid gap-4 sm:grid-cols-2">
          <Campo id="promo-inicio" type="datetime-local" etiqueta="Inicio (hora de Costa Rica)" value={datos.fecha_inicio} error={errores.fecha_inicio}
            onChange={(e) => cambiar('fecha_inicio', e.target.value)} />
          <Campo id="promo-fin" type="datetime-local" etiqueta="Fin (hora de Costa Rica)" value={datos.fecha_fin} error={errores.fecha_fin}
            min={datos.fecha_inicio || ahoraCampoCostaRica()} onChange={(e) => cambiar('fecha_fin', e.target.value)} />
        </div>
      </form>
    </Dialogo>
  );
}

// Busca entre los productos del comercio (GET /productos/busqueda con id_comercio, RN-M1-08: 2 letras).
function SelectorProducto({ idComercio, seleccionado, error, onElegir }) {
  const [termino, setTermino] = useState('');
  const [resultado, setResultado] = useState(null);
  const [aviso, setAviso] = useState('');
  const [buscando, setBuscando] = useState(false);

  const buscar = async () => {
    const q = termino.trim();
    if (q.length < LARGO_MINIMO_TERMINO) { setAviso('Escriba al menos 2 letras del nombre del producto.'); return; }
    setAviso('');
    setBuscando(true);
    const respuesta = await buscarProductos({ q, id_comercio: idComercio, por_pagina: 10 });
    setBuscando(false);
    if (respuesta.ok) setResultado(respuesta.datos.productos);
    else { setResultado(null); setAviso(mensajeErrorPromocion(respuesta)); }
  };

  return (
    <div>
      <label htmlFor="promo-buscar-producto" className="mb-1 block text-sm font-medium text-gray-800">Producto</label>
      {seleccionado && (
        <p className="mb-2 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
          <Icono nombre="check" className="h-4 w-4 shrink-0" /> <span className="min-w-0 break-words">{seleccionado.nombre ?? `Producto ${seleccionado.id}`}</span>
        </p>
      )}
      <div className="flex gap-2">
        <input id="promo-buscar-producto" type="search" value={termino} maxLength={100} placeholder="Buscar entre sus productos"
          aria-invalid={!!error} aria-describedby={error ? 'promo-producto-error' : undefined}
          onChange={(e) => setTermino(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); buscar(); } }}
          className={`min-h-[44px] w-full min-w-0 rounded-lg border bg-white px-3 py-2 text-base focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30 ${error ? 'border-red-600' : 'border-gray-200'}`} />
        <Boton variante="principal" icono="buscar" onClick={buscar} disabled={buscando} aria-label="Buscar producto" className="shrink-0 px-3" />
      </div>
      {error && <p id="promo-producto-error" role="alert" className="mt-1 text-xs text-red-600">{error}</p>}
      {aviso && <p role="status" className="mt-1 text-xs text-gray-600">{aviso}</p>}
      {resultado && (
        resultado.length === 0
          ? <p className="mt-2 text-sm text-gray-600">No encontramos productos de su comercio con ese nombre.</p>
          : (
            <ul className="mt-2 max-h-56 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-1">
              {resultado.map((p) => (
                <li key={p.id_producto}>
                  <button type="button" onClick={() => { onElegir(p.id_producto); setResultado(null); setTermino(''); }}
                    className={`flex min-h-[44px] w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-blue-50 ${seleccionado?.id === p.id_producto ? 'bg-blue-50 font-semibold' : ''}`}>
                    <span className="min-w-0 break-words">{p.nombre}</span>
                    <span className="shrink-0 text-gray-600">{colones(p.precio)}</span>
                  </button>
                </li>
              ))}
            </ul>
          )
      )}
    </div>
  );
}
