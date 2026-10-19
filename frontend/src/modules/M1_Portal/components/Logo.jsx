// Logo PROVISIONAL de Pacíficos Online (todavía no hay logo final).
// Cuando esté la imagen real, guárdela en src/assets/logo.png y cambie solo este archivo por:
//   import logo from '../../../assets/logo.png';
//   export default function Logo() {
//     return <img src={logo} alt="Pacíficos Online" className="h-10 w-auto" />;
//   }
export default function Logo() {
  return (
    <span className="inline-flex items-center gap-2">
      <span aria-hidden className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-(--m1-verde) text-lg font-bold text-(--m1-azul)">P</span>
      <span className="whitespace-nowrap text-lg font-bold text-white">Pacíficos Online</span>
    </span>
  );
}
