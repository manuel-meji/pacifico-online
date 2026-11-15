import { useCallback, useEffect, useState } from 'react';
import './m7.css';

import {
  obtenerUsuario,
  cerrarSesion as cerrarSesionApi,
} from '../Nucleo/services/sesionApi';

import { SesionM7Context } from './context/SesionM7Context';

import TableroEjecutivoView from './views/TableroEjecutivoView';
import PlanCuentasView from './views/PlanCuentasView';
import LibroDiarioView from './views/LibroDiarioView';
import LibroMayorView from './views/LibroMayorView';
import BalanceGeneralView from './views/BalanceGeneralView';
import EstadoResultadosView from './views/EstadoResultadosView';
import CuentasPorCobrarPagarView from './views/CuentasPorCobrarPagarView';
import CentrosCostoView from './views/CentrosCostoView';

const ROLES_PERMITIDOS = [
  'encargado_comercio',
];

function PantallaCarga() {
  return (
    <div className="m7-acceso">
      <p>Verificando sesión y permisos...</p>
    </div>
  );
}

function PantallaError({ mensaje, onReintentar }) {
  return (
    <div className="m7-acceso">
      <h2>No se pudo verificar el acceso</h2>
      <p>{mensaje}</p>

      <button type="button" onClick={onReintentar}>
        Reintentar
      </button>

      <a href="/app.html">Volver al portal</a>
    </div>
  );
}

function PantallaAccesoDenegado() {
  return (
    <div className="m7-acceso">
      <h2>Acceso no autorizado</h2>
      <p>
        Tu usuario no tiene permisos para ingresar al módulo de
        Contabilidad y Reportes Gerenciales.
      </p>

      <a href="/app.html">Volver al portal</a>
    </div>
  );
}

function ContenidoM7() {
  const [seccionActiva, setSeccionActiva] = useState('Tablero ejecutivo');

  return (
    <div className="m7-app">
      {seccionActiva === 'Tablero ejecutivo' && (
        <TableroEjecutivoView onNavegar={setSeccionActiva} />
      )}

      {seccionActiva === 'Plan de cuentas' && (
        <PlanCuentasView onNavegar={setSeccionActiva} />
      )}

      {seccionActiva === 'Libro diario' && (
        <LibroDiarioView onNavegar={setSeccionActiva} />
      )}

      {seccionActiva === 'Libro mayor' && (
        <LibroMayorView onNavegar={setSeccionActiva} />
      )}

      {seccionActiva === 'Balance general' && (
        <BalanceGeneralView onNavegar={setSeccionActiva} />
      )}

      {seccionActiva === 'Estado de resultados' && (
        <EstadoResultadosView onNavegar={setSeccionActiva} />
      )}

      {seccionActiva === 'Cuentas por cobrar y pagar' && (
        <CuentasPorCobrarPagarView onNavegar={setSeccionActiva} />
      )}

      {seccionActiva === 'Centros de costo' && (
        <CentrosCostoView onNavegar={setSeccionActiva} />
      )}
    </div>
  );
}

export default function M7App() {
  const [estado, setEstado] = useState('cargando');
  const [usuario, setUsuario] = useState(null);
  const [mensajeError, setMensajeError] = useState('');
  const [intento, setIntento] = useState(0);

  const [cerrandoSesion, setCerrandoSesion] = useState(false);
  const [errorCerrarSesion, setErrorCerrarSesion] = useState('');

  useEffect(() => {
    let vigente = true;

    async function verificarSesion() {
      setEstado('cargando');
      setMensajeError('');

      const resultado = await obtenerUsuario();

      if (!vigente) return;

      if (!resultado.ok) {
        if (resultado.estado === 401) {
          window.location.replace('/app.html?pantalla=ingresar');
          return;
        }

        if (resultado.estado === 403) {
          setEstado('denegado');
          return;
        }

        setMensajeError(
          resultado.error?.mensaje ||
          'No fue posible comunicarse con el servicio de sesión.'
        );
        setEstado('error');
        return;
      }

      const usuarioActual = resultado.datos?.user;

      if (!usuarioActual) {
        setMensajeError('El servicio no devolvió los datos del usuario.');
        setEstado('error');
        return;
      }

      if (!ROLES_PERMITIDOS.includes(usuarioActual.rol)) {
        setUsuario(usuarioActual);
        setEstado('denegado');
        return;
      }

      setUsuario(usuarioActual);
      setEstado('autorizado');
    }

    verificarSesion();

    return () => {
      vigente = false;
    };
  }, [intento]);

  const reintentar = useCallback(() => {
    setIntento((actual) => actual + 1);
  }, []);

  const ejecutarCierreSesion = useCallback(async () => {
    if (cerrandoSesion) return;

    setCerrandoSesion(true);
    setErrorCerrarSesion('');

    const resultado = await cerrarSesionApi();

    if (resultado.ok || resultado.estado === 401) {
      window.location.replace('/app.html?pantalla=ingresar');
      return;
    }

    setErrorCerrarSesion(
      resultado.error?.mensaje ||
      'No fue posible cerrar la sesión. Inténtalo nuevamente.'
    );

    setCerrandoSesion(false);
  }, [cerrandoSesion]);

  if (estado === 'cargando') {
    return <PantallaCarga />;
  }

  if (estado === 'error') {
    return (
      <PantallaError
        mensaje={mensajeError}
        onReintentar={reintentar}
      />
    );
  }

  if (estado === 'denegado') {
    return <PantallaAccesoDenegado />;
  }

  return (
    <SesionM7Context.Provider
      value={{
        usuario,
        cerrarSesion: ejecutarCierreSesion,
        cerrandoSesion,
        errorCerrarSesion,
      }}
    >
      <ContenidoM7 />
    </SesionM7Context.Provider>
  );
}