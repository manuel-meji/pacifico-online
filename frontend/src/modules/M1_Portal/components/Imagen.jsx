import { useState } from 'react';
import Icono from './IconoComercio';

// Imagen que, si no viene URL o no carga, muestra un ícono vectorial en su lugar (design.md: sin emojis).
export default function Imagen({ src, alt = '', className = '', icono = 'paquete', claseIcono = 'h-14 w-14 text-brandBlue/40', ...resto }) {
  const [fallo, setFallo] = useState(false);
  if (!src || fallo) return <Icono nombre={icono} className={claseIcono} />;
  return <img src={src} alt={alt} className={className} onError={() => setFallo(true)} {...resto} />;
}
