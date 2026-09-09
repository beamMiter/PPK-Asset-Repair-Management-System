import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

// Helper: resolve LAN host for HMR so other devices can access dev server
function resolveHmrHost(env){
  // Priority: explicit VITE_HMR_HOST -> fallback 'localhost'
  // (Prevents Vite from picking up internal Docker IPs which browsers can't reach)
  return env.VITE_HMR_HOST || 'localhost';
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const hmrHost = resolveHmrHost(env);
  const hmrPort = Number(env.VITE_HMR_PORT || 5173);
  const hmrProtocol = env.VITE_HMR_PROTOCOL || 'http';
  const useHttps = hmrProtocol === 'https';

  return {
    plugins: [
      laravel({
        input: [
          'resources/css/app.css',
          'resources/css/toast.css',
          'resources/js/app.js',
          // Page-specific bundles
          'resources/js/repair/dashboard.js',
          'resources/js/settings/sla/dashboard.js',
          'resources/js/maintenance/rating/technicians-dashboard.js',
        ],
        refresh: true,
      }),
    ],
    server: {
      host: '0.0.0.0', // expose to LAN
      port: 5173,
      strictPort: true,
      https: useHttps,
      hmr: {
        host: hmrHost,
        port: hmrPort,
        protocol: hmrProtocol,
      },
    },
  };
});
