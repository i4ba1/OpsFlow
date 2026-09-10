import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const TOKEN_KEY = 'opsflow_token';

let echo: Echo<'reverb'> | null = null;

/**
 * Lazily create the shared Reverb (Pusher protocol) client. The bearer token is
 * sent to /broadcasting/auth so private channels authorize against Sanctum, the
 * same token the REST client uses.
 *
 * The instance is created per authenticated session and torn down on logout
 * (see RealtimeProvider), so the auth header never goes stale.
 */
export function getEcho(): Echo<'reverb'> {
  if (echo !== null) {
    return echo;
  }

  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    Pusher,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${localStorage.getItem(TOKEN_KEY) ?? ''}`,
      },
    },
  });

  return echo;
}

export function disconnectEcho(): void {
  echo?.disconnect();
  echo = null;
}
