
import { useEffect, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';
import TarjetaIndicador from '../components/TarjetaIndicador';
import { consultarReporteGerencial } from '../services/reporteGerencialApi';

function formatoMoneda(valor) {
  if (valor === null || valor === undefined || valor === '') {
    return '—';
  }

  return `₡${Number(valor).toLocaleString('es-CR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export default function TableroEjecutivoView({ onNavegar }) {
  const [reporte, setReporte] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let activo = true;

    async function cargarReporte() {
      try {
        const datos = await consultarReporteGerencial();

        if (activo) {
          setReporte(datos);
          setError('');
        }
      } catch (err) {
        if (activo) {
          setError(err.message || 'No se pudo cargar el reporte.');
        }
      } finally {
        if (activo) {
          setCargando(false);
        }
      }
    }

    cargarReporte();

    return () => {
      activo = false;
    };
  }, []);

  const resumen = reporte?.resumen ?? {};

  const indicadores = [
    {
      titulo: 'Ingresos',
      valor: formatoMoneda(resumen.ingresos),
      detalle: 'Ingresos registrados por ventas',
      color: 'azul',
    },
    {
      titulo: 'Costos de ventas',
      valor: formatoMoneda(resumen.costos),
      detalle: 'Costos registrados de ventas',
      color: 'verde',
    },
    {
      titulo: 'Utilidad bruta',
      valor: formatoMoneda(resumen.utilidad_bruta),
      detalle: 'Ingresos menos costos de ventas',
      color: 'azul',
    },
  ];

  const datosMensuales = reporte?.mensual ?? [];

  const maximoMensual = Math.max(
    0,
    ...datosMensuales.flatMap((dato) => [
      Number(dato.ingresos) || 0,
      Number(dato.costos) || 0,
    ])
  ) || 1;

  const alturaBarra = (valor) =>
    ((Number(valor) || 0) / maximoMensual) * 100;

  return (
    <div className="m7-layout">
      <EncabezadoM7
        seccionActiva="Tablero ejecutivo"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="m7-main">
        <div className="m7-page-heading">
          <div>
            <p className="m7-breadcrumb">
              Pacíficos Online / M7 / Inicio
            </p>
            <h1>Tablero ejecutivo</h1>
            <p className="m7-subtitle">
              Resumen de la información financiera del comercio.
            </p>
          </div>

          <div className="m7-period">
            <span>Período</span>
            <strong>
              {reporte
                ? `${reporte.fecha_desde ?? '—'} al ${reporte.fecha_hasta ?? '—'}`
                : 'No disponible'}
            </strong>
          </div>
        </div>

        <div className="m7-demo-note">
          <span className="m7-demo-dot" />
          {cargando
            ? 'Cargando información contable...'
            : error
              ? `Error al cargar el reporte: ${error}`
              : 'Los indicadores y el resumen mensual se muestran con la información disponible del reporte contable.'}
        </div>

        <section
          className="m7-indicators"
          aria-label="Indicadores financieros"
        >
          {indicadores.map((indicador) => (
            <TarjetaIndicador
              key={indicador.titulo}
              titulo={indicador.titulo}
              valor={indicador.valor}
              detalle={indicador.detalle}
              color={indicador.color}
            />
          ))}
        </section>

        <section className="m7-dashboard-grid">
          <article className="m7-card m7-chart-card">
            <div className="m7-section-heading">
              <div>
                <h2>Resumen mensual</h2>
                <p className="m7-muted">
                  Comparación de ingresos y costo de ventas registrados.
                </p>
              </div>
              <span className="m7-tag">Por período</span>
            </div>

            <div className="m7-chart">
              {datosMensuales.length > 0 ? (
                datosMensuales.map((dato) => (
                  <div className="m7-chart-month" key={dato.periodo}>
                    <div className="m7-bars">
                      <div
                        className="m7-bar ingresos"
                        style={{
                          height: `${alturaBarra(dato.ingresos)}%`,
                        }}
                        title={`Ingresos: ${formatoMoneda(dato.ingresos)}`}
                      />
                      <div
                        className="m7-bar costos"
                        style={{
                          height: `${alturaBarra(dato.costos)}%`,
                        }}
                        title={`Costos: ${formatoMoneda(dato.costos)}`}
                      />
                    </div>
                    <span>{dato.periodo}</span>
                  </div>
                ))
              ) : (
                <p className="m7-muted">
                  {cargando
                    ? 'Cargando datos...'
                    : 'No hay movimientos contables disponibles para el período consultado.'}
                </p>
              )}
            </div>

            <div className="m7-legend">
              <span>
                <i className="m7-legend-blue" />
                Ingresos
              </span>
              <span>
                <i className="m7-legend-green" />
                Costo de ventas
              </span>
            </div>
          </article>

          <article className="m7-card m7-status-card">
            <div className="m7-section-heading">
              <div>
                <h2>Situación contable</h2>
                <p className="m7-muted">
                  Estado de la información contable.
                </p>
              </div>
            </div>

            <div className="m7-status-item">
              <div className="m7-status-label">
                <span>Asientos registrados</span>
                <strong>—</strong>
              </div>
            </div>

            <div className="m7-status-item">
              <div className="m7-status-label">
                <span>Asientos pendientes</span>
                <strong>—</strong>
              </div>
            </div>

            <div className="m7-balance">
              <span className="m7-balance-icon">—</span>
              <div>
                <p>Balance contable</p>
                <strong>Sin información</strong>
                <span>
                  El estado del balance estará disponible cuando se
                  cuente con los datos correspondientes.
                </span>
              </div>
            </div>
          </article>
        </section>

        <section className="m7-card m7-journal">
          <div className="m7-journal-heading">
            <div>
              <h2>Últimos asientos contables</h2>
              <p className="m7-muted">
                Movimientos registrados en el libro diario.
              </p>
            </div>
            <span className="m7-tag">Libro diario</span>
          </div>

          {/* Vista móvil */}
          <div className="m7-journal-mobile">
            <p className="m7-muted">
              No hay asientos disponibles para mostrar.
            </p>
          </div>

          {/* Vista de escritorio */}
          <div className="m7-table-wrap m7-journal-desktop">
            <table>
              <thead>
                <tr>
                  <th>N.º de asiento</th>
                  <th>Fecha</th>
                  <th>Descripción</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colSpan="5">
                    <p className="m7-muted">
                      No hay asientos disponibles para mostrar.
                    </p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <footer className="m7-footer">
          Pacíficos Online · M7 ERP Contable y Reportes Gerenciales
        </footer>
      </main>
    </div>
  );
}
