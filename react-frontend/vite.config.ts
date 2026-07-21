import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { readdirSync } from 'node:fs'
import { basename, resolve } from 'node:path'

const root = __dirname
const outDir = resolve(root, '../front_end/openVRE/public/assets/react')

function getIslandEntries(): Record<string, string> {
  const entriesDir = resolve(root, 'src/entries')

  return Object.fromEntries(
    readdirSync(entriesDir)
      .filter((file) => file.endsWith('.tsx') && !file.startsWith('_'))
      .map((file) => [basename(file, '.tsx'), resolve(entriesDir, file)]),
  )
}

function isReactVendorModule(id: string): boolean {
  return (
    id.includes('node_modules/react/') ||
    id.includes('node_modules/react-dom/') ||
    id.includes('node_modules/scheduler/')
  )
}

export default defineConfig({
  plugins: [
    react({
      reactRefreshHost: process.env.REACT_VITE_DEV_SERVER ?? 'http://localhost:5173',
    }),
  ],
  css: {
    transformer: 'lightningcss',
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: true,
    origin: process.env.REACT_VITE_ORIGIN ?? 'http://localhost:8088',
    hmr: {
      host: 'localhost',
      port: Number(process.env.REACT_VITE_PORT ?? 5173),
    },
    watch: process.env.REACT_VITE_POLLING ? { usePolling: true, interval: 300 } : undefined,
  },
  build: {
    cssMinify: 'lightningcss',
    outDir,
    emptyOutDir: true,
    modulePreload: false,
    rollupOptions: {
      input: {
        ...getIslandEntries(),
        theme: resolve(root, 'src/styles/theme.css'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name][extname]',
        manualChunks(id) {
          if (isReactVendorModule(id)) {
            return 'react-vendor'
          }
        },
      },
    },
  },
})
