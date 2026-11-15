
import { useState } from 'react';
import logo from '../assets/logo-pacificos.png';
import IconoM7 from './IconoM7';
import { useSesionM7 } from '../context/SesionM7Context';

const secciones = [
  'Tablero ejecutivo',
  'Plan de cuentas',
  'Libro diario',
  'Libro mayor',
  'Balance general',
  'Estado de resultados',
  'Cuentas por cobrar y pagar',
  'Centros de costo',
];

const nombresRoles = {
  encargado_comercio: 'Encargado de comercio',
};

export default function EncabezadoM7({
  seccionActiva = 'Tablero ejecutivo',
  onNavegar,
}) {
  const [busqueda, setBusqueda] = useState('');
  const [mensajeBusqueda, setMensajeBusqueda] = useState('');

  const {
    usuario,
    cerrarSesion,
    cerrandoSesion,
    errorCerrarSesion,
  } = useSesionM7();

  const buscarSeccion = (e) => {
    e.preventDefault();

    const texto = busqueda.trim().toLocaleLowerCase('es');

    if (!texto) {
      setMensajeBusqueda('Escribe el nombre de una sección.');
      return;
    }

    const coincidencias = secciones.filter((seccion) =>
      seccion.toLocaleLowerCase('es').includes(texto)
    );

    if (coincidencias.length === 0) {
      setMensajeBusqueda('No se encontraron secciones.');
      return;
    }

    if (coincidencias.length === 1) {
      onNavegar?.(coincidencias[0]);
      setMensajeBusqueda('');
      return;
    }

    const coincidenciaExacta = coincidencias.find(
      (seccion) => seccion.toLocaleLowerCase('es') === texto
    );

    if (coincidenciaExacta) {
      onNavegar?.(coincidenciaExacta);
      setMensajeBusqueda('');
      return;
    }

    setMensajeBusqueda(
      `Hay ${coincidencias.length} coincidencias. Escribe un poco más para precisar.`
    );
  };

  const nombreUsuario = usuario?.nombre || usuario?.correo || 'Usuario';
  const rolUsuario = nombresRoles[usuario?.rol] || usuario?.rol || '';

  return (
    <>
      {/* Franja superior corporativa */}
      <div className="bg-[#1e3a8a] px-4 py-2 text-xs text-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-4">
            <span className="flex items-center gap-1.5">
              <IconoM7 nombre="ubicacion" className="h-4 w-4 text-[#a3be4d]" />
              Envíos rápidos a todo Costa Rica
            </span>

            <span className="flex items-center gap-1.5">
              <IconoM7 nombre="telefono" className="h-4 w-4 text-[#a3be4d]" />
              Soporte y Ventas: +506 2000-0000
            </span>
          </div>

          <div className="hidden gap-4 sm:flex">
            <a href="#ayuda" className="hover:underline">
              Ayuda
            </a>
          </div>
        </div>
      </div>

      {/* Encabezado blanco con el logo original */}
      <header className="bg-white shadow-sm">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-4 py-3">
          <a
            href="/m2.html"
            className="flex shrink-0 items-center gap-3"
            aria-label="Pacíficos Online, volver al panel de comercio"
          >
            <img src={logo} alt="" className="h-11 w-11 rounded-full" />

            <span className="leading-tight">
              <span className="block text-xl font-extrabold tracking-tight text-[#1e3a8a]">
                Pacíficos
              </span>
              <span className="-mt-1 block text-xs font-bold tracking-widest text-[#a3be4d]">
                ONLINE
              </span>
            </span>
          </a>

          <form
            role="search"
            className="relative order-3 flex basis-full items-center sm:order-none sm:mx-4 sm:flex-1"
            onSubmit={buscarSeccion}
          >
            <label className="sr-only" htmlFor="buscar-contabilidad">
              Buscar una sección contable
            </label>

            <input
              id="buscar-contabilidad"
              type="search"
              value={busqueda}
              onChange={(e) => {
                setBusqueda(e.target.value);
                setMensajeBusqueda('');
              }}
              placeholder="Buscar secciones contables..."
              aria-describedby="mensaje-busqueda-contable"
              className="w-full rounded-full border-2 border-gray-200 py-2.5 pl-5 pr-12 text-sm transition focus:border-[#3b82f6] focus:outline-none"
            />

            <button
              type="submit"
              className="absolute right-1 rounded-full bg-[#1e3a8a] p-2 text-white transition hover:bg-[#3b82f6]"
              aria-label="Buscar"
            >
              <IconoM7 nombre="buscar" className="h-4 w-4" />
            </button>

            {mensajeBusqueda && (
              <p
                id="mensaje-busqueda-contable"
                role="status"
                className="absolute left-0 top-full z-50 mt-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 shadow-md"
              >
                {mensajeBusqueda}
              </p>
            )}
          </form>

          {/* Usuario y acciones de sesión */}
          <div className="ml-auto flex min-w-0 flex-col items-start gap-1">
            <div className="flex min-w-0 items-start gap-3">
              <div className="min-w-0 text-left">
                <div className="flex min-w-0 items-center gap-2 text-sm font-semibold text-gray-800">
                  <IconoM7 nombre="usuario" className="h-5 w-5 shrink-0" />

                  <span className="max-w-[220px] truncate">
                    {nombreUsuario}
                  </span>
                </div>

                <span className="mt-1 block text-xs text-gray-500">
                  {rolUsuario}
                </span>
              </div>

              <button
                type="button"
                onClick={cerrarSesion}
                disabled={cerrandoSesion}
                className="shrink-0 inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <IconoM7 nombre="salir" className="h-4 w-4" />
                {cerrandoSesion ? 'Cerrando sesión...' : 'Salir'}
              </button>
            </div>

           <div className="mt-1 flex flex-wrap gap-3 text-xs">
              <a
                href="/m2.html"
                className="font-medium text-blue-800 hover:underline"
              >
                Comercio
              </a>

              <a
                href="/app.html"
                className="font-medium text-blue-800 hover:underline"
              >
                Portal
              </a>
            </div>

            {errorCerrarSesion && (
              <p
                role="alert"
                className="max-w-[260px] text-xs text-red-600"
              >
                {errorCerrarSesion}
              </p>
            )}
          </div>
        </div>
      </header>

      {/* Navegación contable azul */}
      <nav
        className="border-t border-blue-900 bg-[#1e3a8a] text-sm font-medium text-white"
        aria-label="Secciones contables"
      >
        <div className="mx-auto grid max-w-7xl grid-cols-2 gap-2 px-3 py-2 sm:flex sm:flex-wrap sm:gap-5 sm:px-4">
          {secciones.map((seccion) => {
            const activa = seccion === seccionActiva;
            const disponible = true;

            return (
              <button
                key={seccion}
                type="button"
                onClick={() => {
                  if (disponible && onNavegar) {
                    onNavegar(seccion);
                  }
                }}
                disabled={!disponible}
                aria-current={activa ? 'page' : undefined}
                className={`flex min-w-0 items-center justify-center break-words border-b-[3px] px-2 py-3 text-center transition ${activa
                  ? 'border-[#a3be4d] text-[#a3be4d]'
                  : disponible
                    ? 'border-transparent text-white hover:text-[#a3be4d]'
                    : 'cursor-not-allowed border-transparent text-white/50'
                  }`}
              >
                {seccion}
              </button>
            );
          })}
        </div>
      </nav>
    </>
  );
}