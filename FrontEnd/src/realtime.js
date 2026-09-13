import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { token } from "./lib";

window.Pusher = Pusher;

export function createEcho() {
  if (import.meta.env.VITE_REVERB_ENABLED !== "true") return null;
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  if (!key) return null;

  return new Echo({
    broadcaster: "reverb",
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: `${import.meta.env.VITE_API_BASE_URL || "/api"}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: token.get() ? `Bearer ${token.get()}` : "",
      },
    },
  });
}
