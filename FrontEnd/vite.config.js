import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiBase = env.VITE_API_BASE_URL || '/api'
  const apiTarget = env.VITE_DEV_API_TARGET || 'http://127.0.0.1:8000'

  return {
    plugins: [react(), tailwindcss()],
    server: {
      host: env.VITE_DEV_SERVER_HOST || '127.0.0.1',
      port: Number(env.VITE_DEV_SERVER_PORT) || 5173,
      proxy: {
        [apiBase]: {
          target: apiTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
