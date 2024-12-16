import path from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const NEVERSTALE_VITE_DEV_PORT = 3333
// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  base: command === 'serve' ? '' : '/dist/',
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: './src/web/assets/neverstale/dist',
    rollupOptions: {
      input: {
        app: './src/web/assets/neverstale/src/main.ts',
      },
      output: {
        sourcemap: true,
      },
    },
  },
  plugins: [vue()],
  resolve: {
    alias: {
      '~': path.resolve(__dirname, 'node_modules'),
      '@': path.resolve(__dirname, 'src/web/assets/neverstale/src'),
      vue: 'vue/dist/vue.esm-bundler',
    },
    preserveSymlinks: true,
  },
  server: {
    fs: {
      strict: false,
    },
    host: '0.0.0.0',
    origin: `http://localhost:${NEVERSTALE_VITE_DEV_PORT}`,
    port: NEVERSTALE_VITE_DEV_PORT,
    strictPort: true,
  },
}))
