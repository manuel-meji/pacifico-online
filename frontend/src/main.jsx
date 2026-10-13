import { createRoot } from 'react-dom/client';
import './index.css';
import M5App from './modulos/m5-carrito-pedidos/M5App';

// Modo de simulación para la validación: window.SIMULAR_M5 (archivos de doble clic)
// o ?simular=stock / ?simular=comercio en la dirección.
const simular = window.SIMULAR_M5 || new URLSearchParams(location.search).get('simular');

createRoot(document.getElementById('root')).render(<M5App simular={simular} />);
