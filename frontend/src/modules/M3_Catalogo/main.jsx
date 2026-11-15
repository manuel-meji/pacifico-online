// Entrada de M3 (frontend/m3.html). La pantalla vive en M3Principal.jsx para poder probarla.
import { createRoot } from 'react-dom/client';

import '../../index.css';

import M3Principal from './M3Principal';

createRoot(document.getElementById('root')).render(
  <M3Principal />
);
