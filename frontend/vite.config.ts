/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    // The dev server sits behind nginx on :8080 in the Docker Compose stack;
    // make the HMR websocket dial back through the public nginx port.
    hmr: {
      clientPort: 8080,
    },
    // Allow direct local development (npm run dev outside Docker): proxy API
    // calls to the compose nginx entrypoint when it is running.
    proxy: {
      '/api': 'http://localhost:8080',
      '/sanctum': 'http://localhost:8080',
      '/up': 'http://localhost:8080',
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/tests/setup.ts',
    css: false,
    // Keep Vitest to the app source; e2e/*.spec.ts belongs to Playwright.
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
  },
});
