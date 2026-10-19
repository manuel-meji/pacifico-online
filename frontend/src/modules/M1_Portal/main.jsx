import { createRoot } from 'react-dom/client';
import './tema.css';
import M1App from './M1App';

// Entrada propia de M1 (m1.html) para no tocar src/main.jsx, que hoy abre el prototipo de M5.
// Modo de simulación: window.SIMULAR_M1 (archivos de doble clic) o ?simular=suspendido|vacio|sininventario.
const simular = window.SIMULAR_M1 || new URLSearchParams(location.search).get('simular');
// Pantalla inicial: window.PANTALLA_M1 (archivos de doble clic) o ?pantalla=ingresar|crear. Sin ninguno, la ficha de comercio.
const pantalla = window.PANTALLA_M1 || new URLSearchParams(location.search).get('pantalla') || undefined;

createRoot(document.getElementById('root')).render(<M1App pantalla={pantalla} simular={simular} />);
