import EncabezadoM7 from '../components/EncabezadoM7';
import TarjetaIndicador from '../components/TarjetaIndicador';
import {
  indicadores,
  asientos,
  datosMensuales,
  opcionesContables,
} from '../services/tableroService';

export default function TableroEjecutivoView({ onNavegar }) {
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
            <strong>Octubre 2026</strong>
          </div>
        </div>

        <div className="m7-demo-note">
          <span className="m7-demo-dot" />
          Prototipo de demostración: los datos mostrados son de ejemplo.
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
                  Comparación ilustrativa de ingresos y costo de ventas.
                </p>
              </div>
              <span className="m7-tag">2026</span>
            </div>

            <div className="m7-chart">
              {datosMensuales.map((dato) => (
                <div className="m7-chart-month" key={dato.mes}>
                  <div className="m7-bars">
                    <div
                      className="m7-bar ingresos"
                      style={{ height: `${dato.ingresos}%` }}
                      title={`Ingresos: ${dato.ingresos}`}
                    />
                    <div
                      className="m7-bar costos"
                      style={{ height: `${dato.costos}%` }}
                      title={`Costos: ${dato.costos}`}
                    />
                  </div>
                  <span>{dato.mes}</span>
                </div>
              ))}
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

          <div className="m7-table-wrap">
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
