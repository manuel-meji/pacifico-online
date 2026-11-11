
import { useEffect, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';
import TarjetaIndicador from '../components/TarjetaIndicador';
import { asientos } from '../services/tableroService';
import { consultarReporteGerencial } from '../services/reporteGerencialApi';

function formatoMoneda(valor) {
  return `₡${Number(valor ?? 0).toLocaleString('es-CR', {
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

  const indicadores = [
    {
      titulo: 'Ingresos',
      valor: reporte ? formatoMoneda(reporte.resumen.ingresos) : '—',
      detalle: 'Ingresos registrados por ventas',
      color: 'azul',
    },
    {
      titulo: 'Costos de ventas',
      valor: reporte ? formatoMoneda(reporte.resumen.costos) : '—',
      detalle: 'Costos registrados de ventas',
      color: 'verde',
    },
    {
      titulo: 'Utilidad bruta',
      valor: reporte ? formatoMoneda(reporte.resumen.utilidad_bruta) : '—',
      detalle: 'Ingresos menos costos de ventas',
      color: 'azul',
    },
  ];

  const datosMensuales = reporte?.mensual ?? [];

  const maximoMensual = Math.max(
    0,
    ...datosMensuales.flatMap((dato) => [
      Number(dato.ingresos),
      Number(dato.costos),
    ])
  ) || 1;

  const alturaBarra = (valor) =>
    (Number(valor) / maximoMensual) * 100;

  return (
  <div className="m7-layout">
    <EncabezadoM7
      seccionActiva="Tablero ejecutivo"
      onNavegar={onNavegar}
    />
      <main id="contenido" className="m7-main">
        <div className="m7-page-heading">
          <div>
            <p className="m7-breadcrumb">Pacíficos Online / M7 / Inicio</p>
            <h1>Tablero ejecutivo</h1>
            <p className="m7-subtitle">
              Resumen de la información financiera del comercio.
            </p>
          </div>

          <div className="m7-period">
            <span>Período</span>
            <strong>
              {reporte
                ? `${reporte.fecha_desde} al ${reporte.fecha_hasta}`
                : 'Cargando período...'}
            </strong>
          </div>
        </div>

        <div className="m7-demo-note">
          <span className="m7-demo-dot" />
          {cargando
            ? 'Cargando información contable...'
            : error
              ? `Error al cargar el reporte: ${error}`
              : 'Los indicadores y el resumen mensual provienen de registros contables. Las demás secciones son demostrativas.'}
        </div>

<section className="m7-indicators" aria-label="Indicadores financieros">
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
                        style={{ height: `${alturaBarra(dato.ingresos)}%` }}
                        title={`Ingresos: ${formatoMoneda(dato.ingresos)}`}
                      />
                      <div
                        className="m7-bar costos"
                        style={{ height: `${alturaBarra(dato.costos)}%` }}
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
                    : 'No hay movimientos contables para el período consultado.'}
                </p>
              )}
            </div>

            <div className="m7-legend">
              <span><i className="m7-legend-blue" />Ingresos</span>
              <span><i className="m7-legend-green" />Costo de ventas</span>
            </div>
          </article>

          <article className="m7-card m7-status-card">
            <div className="m7-section-heading">
              <div>
                <h2>Situación contable</h2>
                <p className="m7-muted">Indicadores demostrativos</p>
              </div>
            </div>

            <div className="m7-status-item">
              <div className="m7-status-label">
                <span>Asientos registrados</span>
                <strong>24</strong>
              </div>
              <div className="m7-progress">
                <span className="registrados" />
              </div>
            </div>

            <div className="m7-status-item">
              <div className="m7-status-label">
                <span>Asientos pendientes</span>
                <strong>3</strong>
              </div>
              <div className="m7-progress">
                <span className="pendientes" />
              </div>
            </div>

            <div className="m7-balance">
              <span className="m7-balance-icon">✓</span>
              <div>
                <p>Balance contable</p>
                <strong>Cuadrado</strong>
                <span>Estado ilustrativo para el prototipo.</span>
              </div>
            </div>
          </article>
        </section>

        <section className="m7-card m7-journal">
          <div className="m7-journal-heading">
            <div>
              <h2>Últimos asientos contables</h2>
              <p className="m7-muted">Movimientos recientes de ejemplo.</p>
            </div>
            <span className="m7-tag">Vista demostrativa</span>
          </div>


          {/* Vista móvil: últimos asientos como tarjetas */}
          <div className="m7-journal-mobile">
            {asientos.map((asiento) => (
              <article className="m7-journal-entry" key={asiento.numero}>
                <div className="m7-journal-entry-heading">
                  <strong className="m7-entry-number">
                    {asiento.numero}
                  </strong>
                  <span className="m7-status-badge">
                    {asiento.estado}
                  </span>
                </div>

                <p>
                  <span>Fecha</span>
                  <strong>{asiento.fecha}</strong>
                </p>
                <p>
                  <span>Descripción</span>
                  <strong>{asiento.descripcion}</strong>
                </p>
                <p>
                  <span>Tipo</span>
                  <strong>{asiento.tipo}</strong>
                </p>
              </article>
            ))}
          </div>

          {/* Vista de escritorio: tabla original */}
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
                {asientos.map((asiento) => (
                  <tr key={asiento.numero}>
                    <td className="m7-entry-number">{asiento.numero}</td>
                    <td>{asiento.fecha}</td>
                    <td>{asiento.descripcion}</td>
                    <td>{asiento.tipo}</td>
                    <td>
                      <span className="m7-status-badge">
                        {asiento.estado}
                      </span>
                    </td>
                  </tr>
                ))}
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
