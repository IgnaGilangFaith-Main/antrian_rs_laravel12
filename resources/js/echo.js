import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

// Reverb = self-hosted (butuh daemon + port sendiri).
// Pusher = hosted (shared hosting cukup). Pilih lewat VITE_BROADCASTER di .env.
const isReverb = import.meta.env.VITE_BROADCASTER !== "pusher";

window.Echo = new Echo(
    isReverb
        ? {
              broadcaster: "reverb",
              key: import.meta.env.VITE_REVERB_APP_KEY,
              wsHost: import.meta.env.VITE_REVERB_HOST,
              wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
              wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
              forceTLS:
                  (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
              enabledTransports: ["ws", "wss"],
          }
        : {
              broadcaster: "pusher",
              key: import.meta.env.VITE_PUSHER_APP_KEY,
              cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? "mt1",
              forceTLS: true,
          },
);
