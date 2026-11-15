import { nombreDia } from '../services/formato';
import Icono from './IconoComercio';
import Imagen from './Imagen';

// Información pública del comercio del esquema Micrositio: galería, horarios, contacto, redes sociales y políticas.
// Los campos opcionales vacíos no se muestran (CU-009 A3).
export default function InfoComercio({ comercio }) {
  const galeria = comercio.galeria ?? [];
  const horarios = comercio.horarios ?? [];
  const contacto = comercio.contacto ?? {};
  const redes = comercio.redes_sociales ?? [];
  const politicas = comercio.politicas ?? [];
  const hayContacto = contacto.telefono || contacto.correo || contacto.direccion || redes.length > 0;

  if (!galeria.length && !horarios.length && !hayContacto && !politicas.length) {
    return <p className="rounded-xl border border-gray-100 bg-white p-4 text-sm text-gray-600 shadow-sm">Este comercio todavía no publicó su información de contacto.</p>;
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      {galeria.length > 0 && (
        <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:col-span-2">
          <h3 className="font-semibold text-gray-900">Galería</h3>
          <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {galeria.map((imagen) => (
              <li key={imagen.imagen_url} className="relative grid aspect-video place-items-center overflow-hidden rounded-lg bg-gray-100">
                <Imagen src={imagen.imagen_url} alt={imagen.texto_alternativo ?? `Imagen de ${comercio.nombre_comercial}`}
                  className="absolute inset-0 h-full w-full object-cover" icono="tienda" claseIcono="h-8 w-8 text-gray-300" />
              </li>
            ))}
          </ul>
        </section>
      )}

      {horarios.length > 0 && (
        <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
          <h3 className="font-semibold text-gray-900">Horario de atención</h3>
          <dl className="mt-2 space-y-1 text-sm">
            {horarios.map((h) => (
              <div key={h.dia_semana} className="flex justify-between gap-2">
                <dt className="text-gray-600">{nombreDia(h.dia_semana)}</dt>
                <dd className="text-gray-900">{h.cerrado || !h.hora_apertura ? 'Cerrado' : `${h.hora_apertura} a ${h.hora_cierre}`}</dd>
              </div>
            ))}
          </dl>
        </section>
      )}

      {hayContacto && (
        <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
          <h3 className="font-semibold text-gray-900">Contacto</h3>
          <ul className="mt-2 space-y-1 text-sm text-gray-700">
            {contacto.telefono && <li className="flex items-center gap-2"><Icono nombre="telefono" className="h-4 w-4 text-brandBlue" /> {contacto.telefono}</li>}
            {contacto.correo && <li className="flex items-center gap-2 break-all"><Icono nombre="correo" className="h-4 w-4 text-brandBlue" /> {contacto.correo}</li>}
            {contacto.direccion && <li className="flex items-start gap-2"><Icono nombre="ubicacion" className="mt-0.5 h-4 w-4 text-brandBlue" /> {contacto.direccion}</li>}
          </ul>
          {redes.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {redes.map((r) => (
                <a key={r.url} href={r.url} target="_blank" rel="noreferrer" className="rounded-lg border border-brandBlue px-3 py-1 text-xs font-semibold text-brandBlue transition hover:bg-blue-50">{r.plataforma}</a>
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
                <dt className="font-medium capitalize text-gray-800">{p.tipo}</dt>
                <dd className="text-gray-600">{p.descripcion}</dd>
              </div>
            ))}
          </dl>
        </section>
      )}
    </div>
  );
}
