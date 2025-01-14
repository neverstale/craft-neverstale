import path from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import autoprefixer from 'autoprefixer'
import tailwindcss from 'tailwindcss'
import postcssImport from 'postcss-import'
import tailwindNesting from 'tailwindcss/nesting';

const outDir = './src/web/assets/neverstale/dist'
const inputPath = path => `./src/web/assets/neverstale/src/${path}`

const NEVERSTALE_VITE_DEV_PORT = 3333
// https://vite.dev/config/


export default defineConfig(({ command }) => ({
  base: command === 'serve' ? '' : '/dist/',
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: outDir,
    sourcemap: true,
    rollupOptions: {
      input: {
        app: inputPath('main.ts'),
      },
    },
  },
  css: {
    postcss: {
      to: 'dist',
      from: inputPath('styles/neverstale.css'),
      plugins: [
        postcssImport,
        tailwindNesting,
        tailwindcss('./tailwind.config.mjs'),
        autoprefixer,
      ],
    },
  },
  plugins: [
    vue()
  ],
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
