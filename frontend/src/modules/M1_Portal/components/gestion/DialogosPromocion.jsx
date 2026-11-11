import { useState } from 'react';
import { Aviso } from '../componentes';
import { desactivarPromocion, actualizarPromocion, ocultarPromocion, ordenarPromocion } from '../../services/promocionesApi';
import { aCampoCostaRica, aIsoCostaRica, ahoraCampoCostaRica } from '../../services/horaCostaRica';
import { Boton, Campo, Dialogo, erroresPorCampo, mensajeGeneral } from './promocionesUi';

// Envía una acción y deja el resultado en pantalla: errores por campo (422) o el mensaje general (409, 404...).
function useAccion(onListo, camposVisibles = []) {
  const [enviando, setEnviando] = useState(false);
  const [errores, setErrores] = useState({});
  const [errorGeneral, setErrorGeneral] = useState('');

  const ejecutar = async (llamada) => {
    setEnviando(true);
    setErrorGeneral('');
    const respuesta = await llamada();
    setEnviando(false);
    if (respuesta.ok) { onListo(respuesta.datos); return; }
    setErrores(erroresPorCampo(respuesta));
    setErrorGeneral(mensajeGeneral(respuesta, camposVisibles));
  };
  return { enviando, errores, setErrores, errorGeneral, ejecutar };
}

// Comercio: adelantar el fin de una promoción que ya inició (PUT con AdelantoPromocion).
export function DialogoAdelantarFin({ promocion, onListo, onCerrar }) {
  const [fechaFin, setFechaFin] = useState(aCampoCostaRica(promocion.fecha_fin));
  const { enviando, errores, setErrores, errorGeneral, ejecutar } = useAccion(onListo, ['fecha_fin']);
  const actual = aCampoCostaRica(promocion.fecha_fin);

  const enviar = (evento) => {
    evento.preventDefault();
    if (!aIsoCostaRica(fechaFin)) { setErrores({ fecha_fin: 'Elija la fecha y la hora de fin.' }); return; }
    if (fechaFin >= actual) { setErrores({ fecha_fin: 'Solo se puede adelantar: elija una fecha anterior al fin actual.' }); return; }
    if (fechaFin <= ahoraCampoCostaRica()) { setErrores({ fecha_fin: 'Debe ser posterior al momento actual. Para terminarla ya, desactívela.' }); return; }
    ejecutar(() => actualizarPromocion(promocion.id_promocion, { fecha_fin: aIsoCostaRica(fechaFin) }));
  };

  return (
    <Dialogo titulo="Adelantar el fin" onCerrar={onCerrar}
      pie={<><Boton onClick={onCerrar}>Cancelar</Boton><Boton variante="principal" icono="reloj" type="submit" form="form-adelantar" disabled={enviando}>{enviando ? 'Guardando...' : 'Adelantar fin'}</Boton></>}>
      <form id="form-adelantar" onSubmit={enviar} noValidate className="space-y-4">
        {errorGeneral && <Aviso>{errorGeneral}</Aviso>}
        <p className="text-sm text-gray-700">
          <span className="font-semibold">{promocion.titulo}</span> ya inició: solo se puede terminar antes de lo programado.
        </p>
        <Campo id="adelantar-fin" type="datetime-local" etiqueta="Nuevo fin (hora de Costa Rica)" value={fechaFin} error={errores.fecha_fin}
          min={ahoraCampoCostaRica()} max={actual} onChange={(e) => { setFechaFin(e.target.value); setErrores({}); }} />
      </form>
    </Dialogo>
  );
}

// Comercio: desactivar (no se puede deshacer).
export function DialogoDesactivar({ promocion, onListo, onCerrar }) {
  const { enviando, errorGeneral, ejecutar } = useAccion(onListo);
  return (
    <Dialogo titulo="Desactivar promoción" onCerrar={onCerrar}
      pie={<><Boton onClick={onCerrar}>Cancelar</Boton><Boton variante="peligro" icono="prohibido" disabled={enviando} onClick={() => ejecutar(() => desactivarPromocion(promocion.id_promocion))}>{enviando ? 'Desactivando...' : 'Desactivar'}</Boton></>}>
      <div className="space-y-3">
        {errorGeneral && <Aviso>{errorGeneral}</Aviso>}
        <p className="text-sm text-gray-700">
          <span className="font-semibold">{promocion.titulo}</span> dejará de mostrarse y el producto vuelve a su precio normal en el carrito. Esta acción no se puede deshacer.
        </p>
      </div>
    </Dialogo>
  );
}

// Plataforma: ocultar con motivo obligatorio (OcultamientoPromocion, 1 a 500 caracteres).
export function DialogoOcultar({ promocion, onListo, onCerrar }) {
  const [motivo, setMotivo] = useState('');
  const { enviando, errores, setErrores, errorGeneral, ejecutar } = useAccion(onListo, ['motivo']);

  const enviar = (evento) => {
    evento.preventDefault();
    if (!motivo.trim()) { setErrores({ motivo: 'Escriba el motivo: el comercio lo verá.' }); return; }
    ejecutar(() => ocultarPromocion(promocion.id_promocion, motivo.trim()));
  };

  return (
    <Dialogo titulo="Ocultar promoción" onCerrar={onCerrar}
      pie={<><Boton onClick={onCerrar}>Cancelar</Boton><Boton variante="peligro" icono="ojoTachado" type="submit" form="form-ocultar" disabled={enviando}>{enviando ? 'Ocultando...' : 'Ocultar'}</Boton></>}>
      <form id="form-ocultar" onSubmit={enviar} noValidate className="space-y-4">
        {errorGeneral && <Aviso>{errorGeneral}</Aviso>}
        <p className="text-sm text-gray-700">
          Mientras esté oculta, <span className="font-semibold">{promocion.titulo}</span> no se muestra en el portal ni rebaja el precio en el carrito.
        </p>
        <Campo id="ocultar-motivo" como="textarea" etiqueta="Motivo" maxLength={500} value={motivo} error={errores.motivo}
          ayuda={`${motivo.length} de 500 caracteres`} onChange={(e) => { setMotivo(e.target.value); setErrores({}); }} />
      </form>
    </Dialogo>
  );
}

// Plataforma: posición en la sección del portal (1 va primero; vacío la manda al final).
export function DialogoOrden({ promocion, onListo, onCerrar }) {
  const [orden, setOrden] = useState(promocion.orden == null ? '' : String(promocion.orden));
  const { enviando, errores, setErrores, errorGeneral, ejecutar } = useAccion(onListo, ['orden']);

  const enviar = (evento) => {
    evento.preventDefault();
    const numero = orden === '' ? null : Number(orden);
    if (numero !== null && (!Number.isInteger(numero) || numero < 1 || numero > 32767)) {
      setErrores({ orden: 'Debe ser un número entero de 1 a 32767, o vacío para mandarla al final.' });
      return;
    }
    ejecutar(() => ordenarPromocion(promocion.id_promocion, numero));
  };

  return (
    <Dialogo titulo="Orden en el portal" onCerrar={onCerrar}
      pie={<><Boton onClick={onCerrar}>Cancelar</Boton><Boton variante="principal" icono="check" type="submit" form="form-orden" disabled={enviando}>{enviando ? 'Guardando...' : 'Guardar orden'}</Boton></>}>
      <form id="form-orden" onSubmit={enviar} noValidate className="space-y-4">
        {errorGeneral && <Aviso>{errorGeneral}</Aviso>}
        <Campo id="orden-posicion" etiqueta={`Posición de "${promocion.titulo}"`} inputMode="numeric" value={orden} error={errores.orden}
          ayuda="1 va primero. Déjelo vacío para mandarla al final." onChange={(e) => { setOrden(e.target.value.replace(/\D/g, '')); setErrores({}); }} />
      </form>
    </Dialogo>
  );
}
