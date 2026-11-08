import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './tema.css';
import M4App from './M4App';

// Entrada propia de M4 (frontend/m4.html), igual que M1 con m1.html: no modifica src/main.jsx ni src/index.css.
// Modo de simulación: window.SIMULAR_M4 (archivos de doble clic de frontend/prototipos/M4_Inventario/)
// o ?simular=suspendido (ERR-M4-015) / ?simular=m2 (ERR-006) en la dirección. Igual que M1 y M5.
const simular = window.SIMULAR_M4 || new URLSearchParams(window.location.search).get('simular');

createRoot(document.getElementById('root')).render(<StrictMode><M4App simular={simular} /></StrictMode>);
