import PaginaPortal, { usePortal } from '../../components/PaginaPortal';
import Icono from '../../components/IconoComercio';
import { ROLES_GESTION, seccionesPara } from './secciones';

const NOMBRE_ROL = { encargado_comercio: 'Encargado de comercio', encargado_plataforma: 'Encargado de la plataforma' };

// Panel de gestión de M1 (pantalla=gestion): pestañas por rol registradas en views/gestion/secciones.js.
// Solo para encargado_comercio y encargado_plataforma; el backend vuelve a exigir el rol en cada ruta.
export default function GestionView({ seccion }) {
  const { ir, usuario, cargandoUsuario } = usePortal();
  const secciones = usuario ? seccionesPara(usuario.rol) : [];
  const actual = secciones.find((s) => s.clave === seccion) ?? secciones[0];

  let contenido;
  if (cargandoUsuario) {
    contenido = <p role="status" className="mt-8 text-center text-sm text-gray-600">Cargando…</p>;
  } else if (!usuario || !ROLES_GESTION.includes(usuario.rol)) {
    contenido = (
      <div className="mx-auto mt-8 max-w-md rounded-xl border border-gray-100 bg-white p-6 text-center shadow-sm">
        <Icono nombre="panel" className="mx-auto h-8 w-8 text-brandBlue" />
        <p className="mt-3 text-sm text-gray-700">
          {usuario ? 'Su cuenta no tiene acceso al panel de gestión.' : 'Inicie sesión con una cuenta de comercio o de la plataforma para usar el panel de gestión.'}
        </p>
        {!usuario && (
          <button type="button" onClick={() => ir('ingresar')}
            className="mt-4 inline-flex min-h-[44px] items-center justify-center rounded-lg bg-brandDarkBlue px-5 font-bold text-white hover:bg-brandBlue">
            Ingresar
          </button>
        )}
      </div>
    );
  } else {
    const { Vista } = actual;
    contenido = (
      <>
        <div role="tablist" aria-label="Secciones del panel" className="mt-5 flex gap-2 overflow-x-auto border-b border-gray-200">
          {secciones.map((s) => {
            const activa = s.clave === actual.clave;
            return (
              <button key={s.clave} type="button" role="tab" aria-selected={activa} onClick={() => ir('gestion', { seccion: s.clave }, { reemplazar: true })}
                className={`flex min-h-[44px] shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition ${activa ? 'border-brandDarkBlue text-brandDarkBlue' : 'border-transparent text-gray-600 hover:text-brandBlue'}`}>
                <Icono nombre={s.icono} className="h-4 w-4" /> {s.nombre}
              </button>
            );
          })}
        </div>
        <div role="tabpanel" className="mt-6">
          <Vista usuario={usuario} />
        </div>
      </>
    );
  }

  return (
    <PaginaPortal>
      <div className="mt-6 flex flex-wrap items-end justify-between gap-2">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold text-brandDarkBlue"><Icono nombre="panel" className="h-6 w-6" /> Panel de gestión</h1>
          {usuario && ROLES_GESTION.includes(usuario.rol) && (
            <p className="mt-1 text-sm text-gray-600">{usuario.nombre} · {NOMBRE_ROL[usuario.rol]}</p>
          )}
        </div>
      </div>
      {contenido}
    </PaginaPortal>
  );
}
