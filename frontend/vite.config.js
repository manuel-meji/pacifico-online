import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { viteSingleFile } from 'vite-plugin-singlefile';

// Entrada del proyecto: app.html.
// `npm run dev` abre el prototipo; `npm run build:single` genera un HTML de una sola pieza (dist-single/app.html).
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
  build: { rollupOptions: { input: 'app.html' } },
}));
