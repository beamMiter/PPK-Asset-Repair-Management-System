import net from 'node:net';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

// Helper: resolve LAN host for HMR so other devices can access dev server
function resolveHmrHost(env){
  // Priority: explicit VITE_HMR_HOST -> fallback 'localhost'
  // (Prevents Vite from picking up internal Docker IPs which browsers can't reach)
  return env.VITE_HMR_HOST || 'localhost';
}

// Is `port` already taken on this address?
function isBusy(port, host) {
  return new Promise((resolve) => {
    const probe = net.createServer();
    probe.once('error', (e) => resolve(e.code === 'EADDRINUSE')); // e.g. EADDRNOTAVAIL (no IPv6) => not busy
    probe.once('listening', () => probe.close(() => resolve(false)));
    probe.listen(port, host);
  });
}

// First free port at/after `start`, checked on IPv6 loopback, IPv4 loopback and the wildcard.
// Vite binds 0.0.0.0 (IPv4 only), which succeeds even when *another* Vite already owns
// [::1]:5173 — and on macOS `localhost` resolves to ::1 first, so the browser then talks to
// that other project's server and gets HTML back instead of this project's CSS/JS. Probing
// ::1 too means `npm run dev` never lands on a port a second project is using.
async function findFreePort(start) {
  for (let p = start; p < start + 50; p++) {
    const busy = await Promise.all(['::1', '127.0.0.1', '0.0.0.0'].map((h) => isBusy(p, h)));
    if (!busy.some(Boolean)) return p;
  }
  return start;
}

export default defineConfig(async ({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const hmrHost = resolveHmrHost(env);

  // Dev-server port: start from VITE_PORT (default 5173, which Docker maps) and move up to the
  // first free one. laravel-vite-plugin writes the port actually used into public/hot.
  const wantedPort = Number(env.VITE_PORT || 5173);
  const port = command === 'serve' ? await findFreePort(wantedPort) : wantedPort;
  if (port !== wantedPort) {
    console.log(`\n  [vite] port ${wantedPort} is in use (another Vite?) — using ${port} instead\n`);
  }
  // HMR must point at the port the server really listens on. VITE_HMR_PORT is only honoured
  // when we kept the requested port; a value left over from the default would send HMR to the
  // other project's server.
  const hmrPort = port === wantedPort ? Number(env.VITE_HMR_PORT || port) : port;
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
      port,
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
