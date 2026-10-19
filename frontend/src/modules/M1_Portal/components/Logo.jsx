import logo from '../assets/logo-pacificos.png';

// Logotipo de Pacíficos Online (assets/logo-pacificos.png) con el nombre al lado.
export default function Logo() {
  return (
    <span className="inline-flex items-center gap-2">
      <img src={logo} alt="" className="h-9 w-9 shrink-0 rounded-full" />
      <span className="whitespace-nowrap text-lg font-bold text-white">Pacíficos Online</span>
    </span>
  );
}
