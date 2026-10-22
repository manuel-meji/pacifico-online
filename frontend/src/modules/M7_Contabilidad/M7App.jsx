
import { useState } from 'react';
import './m7.css';

import TableroEjecutivoView from './views/TableroEjecutivoView';
import PlanCuentasView from './views/PlanCuentasView';
import LibroDiarioView from './views/LibroDiarioView';
import LibroMayorView from './views/LibroMayorView';
import BalanceGeneralView from './views/BalanceGeneralView';
import EstadoResultadosView from './views/EstadoResultadosView';
import CuentasPorCobrarPagarView from './views/CuentasPorCobrarPagarView';
import CentrosCostoView from './views/CentrosCostoView';

export default function M7App() {
  const [seccionActiva, setSeccionActiva] = useState('Tablero ejecutivo');

  return (
    <div className="m7-app">
      {seccionActiva === 'Tablero ejecutivo' && (
        <TableroEjecutivoView
          onNavegar={setSeccionActiva}
        />
      )}

      {seccionActiva === 'Plan de cuentas' && (
        <PlanCuentasView
          onNavegar={setSeccionActiva}
        />
      )}
      
      {seccionActiva === 'Libro diario' && (
  <LibroDiarioView
    onNavegar={setSeccionActiva}
  />
)}

{seccionActiva === 'Libro mayor' && (
  <LibroMayorView
    onNavegar={setSeccionActiva}
  />
)}

{seccionActiva === 'Balance general' && (
  <BalanceGeneralView
    onNavegar={setSeccionActiva}
  />
)}

{seccionActiva === 'Estado de resultados' && (
  <EstadoResultadosView
    onNavegar={setSeccionActiva}
  />
)}

{seccionActiva === 'Cuentas por cobrar y pagar' && (
  <CuentasPorCobrarPagarView
    onNavegar={setSeccionActiva}
  />
)}

{seccionActiva === 'Centros de costo' && (
  <CentrosCostoView onNavegar={setSeccionActiva} />
)}
    </div>
  );
}
