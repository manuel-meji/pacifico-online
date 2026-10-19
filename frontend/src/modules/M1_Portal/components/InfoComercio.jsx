// Información pública del comercio que entrega M2 (ComercioPublico): horarios, contacto, redes y políticas.
export default function InfoComercio({ comercio }) {
  const { horarios = [], contacto, redes_sociales = [], politicas = [] } = comercio;
  return (
    <div className="grid gap-4 md:grid-cols-2">
      <section className="rounded-lg border border-gray-200 bg-white p-4">
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
        <section className="rounded-lg border border-gray-200 bg-white p-4">
          <h3 className="font-semibold text-gray-900">Contacto</h3>
          <ul className="mt-2 space-y-1 text-sm text-gray-700">
            {contacto.telefono && <li>📞 {contacto.telefono}</li>}
            {contacto.correo && <li className="break-all">✉️ {contacto.correo}</li>}
            {contacto.direccion && <li>📍 {contacto.direccion}</li>}
          </ul>
          {redes_sociales.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {redes_sociales.map((r) => (
                <a key={r.red} href={r.url} className="rounded-full border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100">{r.red}</a>
              ))}
            </div>
          )}
        </section>
      )}

      {politicas.length > 0 && (
        <section className="rounded-lg border border-gray-200 bg-white p-4 md:col-span-2">
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
