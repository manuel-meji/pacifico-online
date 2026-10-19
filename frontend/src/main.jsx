import { createRoot } from 'react-dom/client';
import './index.css';
import M1App from './modules/M1_Portal/M1App';
import M5App from './modules/M5_Pedidos/M5App';

const params = new URLSearchParams(location.search);

// Módulo a abrir: window.MODULO (archivos de doble clic) o ?modulo=m1 en la dirección.
// Sin ninguno de los dos se abre M5.
const modulo = window.MODULO || params.get('modulo');

if (modulo === 'm1') {
  // Modo de simulación de M1: window.SIMULAR_M1 (archivos de doble clic)
  // o ?simular=correo / ?simular=credenciales en la dirección.
  const simularM1 = window.SIMULAR_M1 || params.get('simular');
  document.title = 'Pacíficos Online · M1 Portal público';
  createRoot(document.getElementById('root')).render(<M1App simular={simularM1} />);
} else {
  // Modo de simulación para la validación: window.SIMULAR_M5 (archivos de doble clic)
  // o ?simular=stock / ?simular=comercio en la dirección.
  const simular = window.SIMULAR_M5 || params.get('simular');

  createRoot(document.getElementById('root')).render(<M5App simular={simular} />);
}
