import { useCallback, useEffect, useRef, useState } from 'react';

// Ejecuta una llamada de m1Api cuando cambian las dependencias y expone su estado:
//   'cargando'     primera carga, todavía sin datos
//   'actualizando' nueva carga con los datos anteriores en pantalla
//   'listo'        datos recibidos
//   'error'        la API respondió con error (resultado trae { estado, error } del núcleo)
// Con activa = false no llama a la API (por ejemplo, si el término no cumple RN-M1-08).
export default function useConsulta(llamada, dependencias, activa = true) {
  const [estado, setEstado] = useState(activa ? 'cargando' : 'inactiva');
  const [datos, setDatos] = useState(null);
  const [resultado, setResultado] = useState(null);
  const [intento, setIntento] = useState(0);
  const llamadaActual = useRef(llamada);
  llamadaActual.current = llamada;

  useEffect(() => {
    if (!activa) {
      setEstado('inactiva');
      return undefined;
    }

    let vigente = true;
    setEstado((anterior) => (anterior === 'listo' || anterior === 'actualizando' ? 'actualizando' : 'cargando'));

    llamadaActual.current().then((respuesta) => {
      if (!vigente) return;
      setResultado(respuesta);
      if (respuesta.ok) {
        setDatos(respuesta.datos);
        setEstado('listo');
      } else {
        setEstado('error');
      }
    });

    return () => { vigente = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...dependencias, activa, intento]);

  const reintentar = useCallback(() => setIntento((n) => n + 1), []);

  return { estado, datos, resultado, reintentar };
}
