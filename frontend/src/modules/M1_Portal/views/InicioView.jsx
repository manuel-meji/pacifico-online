
import EncabezadoPortal from '../components/EncabezadoPortal';
import CarruselComercios from '../components/CarruselComercios';

// Categorías temporales generales de Pacíficos Online.
const categoriasTemporales = [
  { idCategoria: 1, nombre: 'Moda', icono: 'tienda' },
  { idCategoria: 2, nombre: 'Tecnología', icono: 'red' },
  { idCategoria: 3, nombre: 'Hogar', icono: 'inicio' },
  { idCategoria: 4, nombre: 'Artesanías', icono: 'bombillo' },
  { idCategoria: 5, nombre: 'Alimentos', icono: 'paquete' },
  { idCategoria: 6, nombre: 'Belleza', icono: 'verificado' },
];

export default function InicioView({ onIr }) {
  return (
    <div className="min-h-screen bg-gray-50">

      {}
      <EncabezadoPortal
        categorias={categoriasTemporales}
        cantidadCarrito={0}
        onMiCuenta={() => onIr('ingresar')}
      />

      {/* Contenido principal */}
      <main className="mx-auto max-w-7xl px-6 py-10">

        <h1 className="text-3xl font-bold text-blue-900">
          Bienvenido a Pacíficos Online
        </h1>

        <p className="mt-4 text-gray-600">
          Descubrí productos y comercios de nuestra plataforma.
        </p>

        {}
        <CarruselComercios onVerComercio={() => onIr('comercio')} />
    
<section className="mt-12">
  <div className="mb-6">
    <h2 className="text-2xl font-bold text-brandDarkBlue">
      Productos más vendidos
    </h2>
    <p className="mt-2 text-sm text-gray-600">
      Descubrí algunos de los productos destacados de nuestra plataforma.
    </p>
  </div>

  <div className="max-w-xs overflow-hidden rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:shadow-md">
    <button
      type="button"
      onClick={() => onIr('producto')}
      className="w-full text-left"
    >
      <div className="flex h-48 items-center justify-center rounded-lg bg-gray-50 p-4">
        <img
          src="https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=800&q=80"
          alt="Laptop Acer Nitro Lite"
          className="max-h-full object-contain"
        />
      </div>

      <h3 className="mt-4 text-sm font-semibold text-gray-800">
        Acer Nitro Lite Core i5 / 16GB RAM / RTX 4050
      </h3>

      <p className="mt-2 text-lg font-extrabold text-brandDarkBlue">
        ₡549,900
      </p>

      <span className="mt-3 inline-block text-sm font-semibold text-brandBlue">
        Ver detalles
      </span>
    </button>
  </div>
</section>

      </main>
    </div>
    
  );
}
