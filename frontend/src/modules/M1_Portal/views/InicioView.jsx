
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

      {/* Encabezado del Portal Principal */}
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

      </main>
    </div>
  );
}
