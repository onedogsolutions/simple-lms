import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './',
  build: {
    target: 'es2022',
    outDir: 'build',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        admin: resolve(root, 'src/admin/main.jsx'),
        'player-tracker': resolve(root, 'src/frontend/player-tracker.js'),
        account: resolve(root, 'src/frontend/account.js'),
      },
      output: {
        entryFileNames: (chunk) =>
          chunk.name === 'admin'
            ? 'admin/[name].js'
            : 'frontend/[name].js',
        assetFileNames: (asset) =>
          asset.name?.startsWith('admin')
            ? 'admin/[name][extname]'
            : 'frontend/[name][extname]',
      },
    },
  },
});
