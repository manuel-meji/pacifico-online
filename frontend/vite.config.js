import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { viteSingleFile } from 'vite-plugin-singlefile';

// Entrada de la aplicación integrada: app.html (portal de M1: inicio, búsqueda e inicio de sesión).
// Cada módulo conserva su propia página: carrito.html (M5), m1.html, m2.html, m4.html, m6.html, m7.html.
// `npm run dev` abre el portal; `npm run build` compila todas las páginas.
// `npm run build:single` genera el prototipo de M5 en un HTML de una sola pieza (dist-single/carrito.html).
const PAGINAS = {
  app: 'app.html',
  inicio: 'inicio.html',
  carrito: 'carrito.html',
  m1: 'm1.html',
  m2: 'm2.html',
  m4: 'm4.html',
  m6: 'm6.html',
  m7: 'm7.html',
};

export default defineConfig(({ mode }) => ({
  plugins: [react(), tailwindcss(), ...(mode === 'single' ? [viteSingleFile()] : [])],
  server: {
    open: '/app.html',
    // Proxy al backend Laravel (php artisan serve) para evitar CORS y compartir cookies de sesión.
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/login': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/logout': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
  build: { rollupOptions: { input: mode === 'single' ? 'carrito.html' : PAGINAS } },
}));
