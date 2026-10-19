import Icono from './IconoComercio';

// Información pública del comercio que entrega M2 (ComercioPublico): horarios, contacto, redes y políticas.
export default function InfoComercio({ comercio }) {
  const { horarios = [], contacto, redes_sociales = [], politicas = [] } = comercio;
  return (
    <div className="grid gap-4 md:grid-cols-2">
      <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <h3 className="font-semibold text-gray-900">Horario de atención</h3>
        <dl className="mt-2 space-y-1 text-sm">
          {horarios.map((h) => (
            <div key={h.dia} className="flex justify-between gap-2">
              <dt className="text-gray-600">{h.dia}</dt>
              <dd className="text-gray-900">{h.apertura ? `${h.apertura} a ${h.cierre}` : 'Cerrado'}</dd>
            </div>
          ))}
        </dl>
      </section>

      {contacto && (
        <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
          <h3 className="font-semibold text-gray-900">Contacto</h3>
          <ul className="mt-2 space-y-1 text-sm text-gray-700">
            {contacto.telefono && <li className="flex items-center gap-2"><Icono nombre="telefono" className="h-4 w-4 text-brandBlue" /> {contacto.telefono}</li>}
            {contacto.correo && <li className="flex items-center gap-2 break-all"><Icono nombre="correo" className="h-4 w-4 text-brandBlue" /> {contacto.correo}</li>}
            {contacto.direccion && <li className="flex items-start gap-2"><Icono nombre="ubicacion" className="mt-0.5 h-4 w-4 text-brandBlue" /> {contacto.direccion}</li>}
          </ul>
          {redes_sociales.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {redes_sociales.map((r) => (
                <a key={r.red} href={r.url} className="rounded-lg border border-brandBlue px-3 py-1 text-xs font-semibold text-brandBlue transition hover:bg-blue-50">{r.red}</a>
              ))}
            </div>
          )}
        </section>
      )}

      {politicas.length > 0 && (
        <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:col-span-2">
          <h3 className="font-semibold text-gray-900">Políticas del comercio</h3>
          <dl className="mt-2 grid gap-3 text-sm sm:grid-cols-3">
            {politicas.map((p) => (
              <div key={p.tipo}>
                <dt className="font-medium text-gray-800">{p.tipo}</dt>
                <dd className="text-gray-600">{p.texto}</dd>
              </div>
            ))}
          </dl>
        </section>
      )}
    </div>
  );
}
